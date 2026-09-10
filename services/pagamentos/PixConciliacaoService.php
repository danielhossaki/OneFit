<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/CobrancaPixRepositorio.php';
require_once __DIR__.'/OrdersPixService.php';
require_once __DIR__.'/PixEfeitos.php';
require_once __DIR__.'/PixTesteLocal.php';

/** Internal only: receives the result of the injected server gateway, never HTTP input. */
final class PixConciliacaoService
{
    public function __construct(private CobrancaPixRepositorio $r, private ?\Closure $clock=null,private bool $integrado=false) {}
    public function aplicar(int $usuario,int $id,int $versao,array $d): void
    {
        $this->r->transacao(function () use ($usuario,$id,$versao,$d) {
            $x=$this->r->tentativa($usuario,$id); $p=$x['p']; $c=$x['c'];
            PixTesteLocal::validar($c,$p);
            if ((int)$p['envio_versao']!==$versao) return; // stale worker
            if (($d['ambiente']??null)!=='testing' || ($d['moeda']??null)!=='BRL'
                || ($d['valor_centavos']??null)!==MatriculaPixService::centavos($p['valor'])
                || ($d['external_reference']??null)!==$p['referencia_externa']
                || ($d['recebedor_id']??null)!==$p['recebedor_esperado_id']
                || !preg_match('/^ORD[A-Z0-9]{1,60}$/D',$d['id']??'')
                || !preg_match('/^PAY[A-Z0-9]{1,60}$/D',$d['pagamento_id']??'')
                || ($p['order_id']!==null && $p['order_id']!==$d['id'])
                || ($p['id_externo']!==null && $p['id_externo']!==$d['pagamento_id'])) throw new PagamentoException();
            foreach (['status','status_detail','pagamento_status','pagamento_status_detail'] as $k) {
                if (!is_string($d[$k]??null)||!preg_match('/^[a-z_]{1,60}$/D',$d[$k])) throw new PagamentoException();
            }
            $status=OrdersPixService::statusInterno($d['pagamento_status'],$d['pagamento_status_detail']);
            $order=OrdersPixService::statusInterno($d['status'],$d['status_detail']);
            if ($status!==$order) throw new PagamentoException();
            $now=($this->clock?($this->clock)():new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));
            $created=OrdersPixValidacao::data($d['provedor_criado_em']??null,$now);
            $updated=OrdersPixValidacao::data($d['provedor_atualizado_em']??null,$now);
            if ($updated<$created) throw new PagamentoException();
            $visual=$this->integrado?PixSeguranca::preservarVisual($d,$p,$status):null;
            $http=$d['http']??null;if(!is_int($http)||$http<200||$http>299)throw new PagamentoException();
            // Provider update timestamps never replace local approval timestamps.
            $approval=null;
            if ($status==='aprovado') {
                if ($d['status']!=='processed'||$d['pagamento_status']!=='processed'||$d['pagamento_status_detail']!=='accredited') throw new PagamentoException();
                $approval=$now; // Local confirmation, NOT the exact bank approval time.
            }
            // Once terminal, stale or contradictory observations cannot overwrite history.
            if ($c['status']!=='aberta') {
                if ($p['status_interno']!==$status) throw new PagamentoException();
                if($this->integrado)$this->r->sql("UPDATE pagamentos SET ultima_consulta_em=?,envio_estado='confirmado',envio_bloqueio_ate=NULL,envio_codigo_erro=NULL WHERE id_pagamento=?",[$now->format('Y-m-d H:i:s.u'),$p['id_pagamento']]);
                return;
            }
            $event=hash('sha256',implode('|',['v1','conciliacao','testing',$p['id_pagamento'],$d['id'],$d['pagamento_id'],$d['status'],$d['status_detail'],$d['pagamento_status'],$d['pagamento_status_detail']]));
            $existing=$this->r->um('SELECT id_evento FROM pagamento_eventos WHERE chave_evento=?',[$event]);
            if (!$existing) $this->r->sql("INSERT INTO pagamento_eventos (id_pagamento,chave_evento,fonte,tipo,recurso_externo,status_anterior,status_novo,processamento,tentativas,processado_em) VALUES (?,?,'conciliacao','orders_pix',?,?,?,'processado',1,?)",[$p['id_pagamento'],$event,$d['id'],$p['status_interno'],$status,$now->format('Y-m-d H:i:s.u')]);
            $request=$d['request_id']??null;
            if (!is_string($request)||!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$request)) $request=null;
            $http=$d['http']??null; if (!is_int($http)||$http<200||$http>299) throw new PagamentoException();
            $this->r->sql("UPDATE pagamentos SET order_id=?,id_externo=?,order_status=?,order_status_detalhe=?,status_provedor=?,status_detalhe=?,status_interno=?,envio_estado='confirmado',envio_bloqueio_ate=NULL,envio_codigo_erro=NULL,envio_http=?,envio_request_id=?,ultima_consulta_em=?,aprovado_em=COALESCE(aprovado_em,?) WHERE id_pagamento=?",[$d['id'],$d['pagamento_id'],$d['status'],$d['status_detail'],$d['pagamento_status'],$d['pagamento_status_detail'],$status,$http,$request,$now->format('Y-m-d H:i:s.u'),$approval?->format('Y-m-d H:i:s.u'),$p['id_pagamento']]);
            if ($approval) {
                if(($c['origem']??'matricula')==='matricula'){
                $m=$this->r->um('SELECT * FROM matricula WHERE id_matricula=? AND id_usuario=? FOR UPDATE',[$c['id_matricula'],$usuario]);
                if (!$m || $m['status']!=='pendente'||$m['data_inicio']!==null||$m['data_fim']!==null||(int)$m['duracao_contratada_dias']<=0) throw new PagamentoException();
                $start=$approval->setTimezone(new \DateTimeZone('America/Sao_Paulo'));
                $this->r->sql("UPDATE matricula SET status='ativa',data_inicio=?,data_fim=? WHERE id_matricula=?",[$start->format('Y-m-d'),$start->modify('+'.$m['duracao_contratada_dias'].' days')->format('Y-m-d'),$m['id_matricula']]);
                $this->r->sql("UPDATE usuarios SET status='ativo' WHERE id_usuario=? AND status='pendente_pagamento'",[$usuario]);
                }
                $this->r->sql("UPDATE cobrancas SET status='liquidada',chave_tentativa_ativa=NULL,encerrada_em=? WHERE id_cobranca=?",[$now->format('Y-m-d H:i:s.u'),$id]);
            } elseif (in_array($status,['recusado','cancelado','expirado'],true)) {
                $this->r->sql("UPDATE cobrancas SET status=?,chave_tentativa_ativa=NULL,encerrada_em=? WHERE id_cobranca=?",[$status==='expirado'?'expirada':'cancelada',$now->format('Y-m-d H:i:s.u'),$id]);
            }
            if($this->integrado){
                $expiry=PixSeguranca::vencimento($d['expiracao']??null,$created);
                $this->r->sql('UPDATE pagamentos SET expira_em=? WHERE id_pagamento=?',[$expiry,$p['id_pagamento']]);
                $this->r->sql('UPDATE cobrancas SET expira_em=? WHERE id_cobranca=?',[$expiry,$id]);
                $this->r->sql('UPDATE pix_reservas SET expira_em=? WHERE id_cobranca=?',[$expiry,$id]);
                $this->r->sql('UPDATE pagamentos SET pix_qr=?,pix_qr_base64=?,pix_ticket_url=?,provedor_criado_em=?,provedor_atualizado_em=? WHERE id_pagamento=?',[!in_array($status,['criado','pendente'],true)?null:$visual['qr_code'],!in_array($status,['criado','pendente'],true)?null:$visual['qr_code_base64'],!in_array($status,['criado','pendente'],true)?null:$visual['ticket_url'],(new \DateTimeImmutable($created))->format('Y-m-d H:i:s.u'),(new \DateTimeImmutable($updated))->format('Y-m-d H:i:s.u'),$p['id_pagamento']]);
                if(in_array($status,['aprovado','recusado','cancelado','expirado'],true))(new PixEfeitos($this->r))->aplicar($c,$status);
            }
        });
    }
}
