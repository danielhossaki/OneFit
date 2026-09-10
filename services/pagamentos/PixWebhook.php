<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PixCheckout.php';
final class PixWebhook
{
    public function __construct(private PixRepositorio $r){}
    /** CLI maintenance: GET saved pending Orders only; never resend uncertain POSTs.
     * Keep reservations if the provider remains pending, including after the deadline.
     */
    public function manutencao(OrdersPixGatewayInterface $gateway,int $limit=10):void
    {
        $rows=$this->r->sql("SELECT c.id_usuario,c.id_cobranca FROM cobrancas c JOIN pagamentos p ON p.id_cobranca=c.id_cobranca JOIN pix_testes_tecnicos t ON t.id_usuario=c.id_usuario WHERE c.status='aberta' AND p.order_id IS NOT NULL AND (p.ultima_consulta_em IS NULL OR p.ultima_consulta_em<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 30 SECOND)) ORDER BY p.ultima_consulta_em LIMIT ".max(1,min(50,$limit)))->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach($rows as $c)try{(new MatriculaPixService($this->r->db,$gateway,null,true))->consultarConfiavel((int)$c['id_usuario'],(int)$c['id_cobranca']);}catch(\Throwable){/* lease + sanitized recovery state already persisted */}
        $out=$this->r->sql('SELECT DISTINCT id_cobranca FROM pix_notificacoes WHERE entregue_em IS NULL LIMIT 50')->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach($out as $c)(new PixEfeitos($this->r))->entregar((int)$c['id_cobranca']);
    }
    /** Store authenticated metadata only. Callers already checked signature. */
    public function receber(string $key,string $resource,string $type):void
    {
        if(!in_array($type,['order','payment'],true))throw new PagamentoException();
        $this->r->sql("INSERT INTO pagamento_eventos (chave_evento,fonte,tipo,recurso_externo) VALUES (?,'webhook',?,?) ON DUPLICATE KEY UPDATE chave_evento=VALUES(chave_evento)",[$key,$type,$resource]);
    }
    public function processar(OrdersPixGatewayInterface $gateway,int $limit=10):void
    {
        $ids=$this->r->sql("SELECT id_evento FROM pagamento_eventos WHERE fonte='webhook' AND (processamento IN ('recebido','erro') OR (processamento='processando' AND ultima_tentativa_em<DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 2 MINUTE))) AND (proxima_tentativa_em IS NULL OR proxima_tentativa_em<=UTC_TIMESTAMP(6)) ORDER BY id_evento LIMIT ".max(1,min(50,$limit)))->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach($ids as $i){
            $event=$this->r->transacao(function()use($i){$e=$this->r->um('SELECT * FROM pagamento_eventos WHERE id_evento=? FOR UPDATE',[$i['id_evento']]);if(in_array($e['processamento'],['processado','ignorado']))return null;if($e['processamento']==='processando'&&strtotime($e['ultima_tentativa_em'].' UTC')>time()-120)return null;$this->r->sql("UPDATE pagamento_eventos SET processamento='processando',tentativas=tentativas+1,ultima_tentativa_em=UTC_TIMESTAMP(6) WHERE id_evento=?",[$e['id_evento']]);return $e;});if(!$event)continue;
            try{
                $p=$this->r->um('SELECT p.id_pagamento,p.id_cobranca,c.id_usuario,p.order_id FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE p.order_id=? OR p.id_externo=?',[$event['recurso_externo'],$event['recurso_externo']]);
                if(!$p||!$p['order_id']){
                    // Unrelated events are retained, but stop retrying after a day.
                    if(strtotime($event['recebido_em'].' UTC')<time()-86400){$this->r->sql("UPDATE pagamento_eventos SET processamento='ignorado',codigo_erro='recurso_sem_vinculo',processado_em=UTC_TIMESTAMP(6) WHERE id_evento=?",[$event['id_evento']]);continue;}
                    throw new PagamentoException();
                }
                $checkout=new PixCheckout($this->r->db,$gateway);$checkout->tecnico((int)$p['id_usuario']);
                (new MatriculaPixService($this->r->db,$gateway,null,true))->consultarConfiavel((int)$p['id_usuario'],(int)$p['id_cobranca']);
                // A live lease/rate limit means no provider read yet: retry, not ack processing.
                $fresh=$this->r->um('SELECT ultima_consulta_em FROM pagamentos WHERE id_pagamento=?',[$p['id_pagamento']]);
                if(!$fresh['ultima_consulta_em']||strcmp($fresh['ultima_consulta_em'],$event['recebido_em'])<0)throw new PagamentoException();
                $this->r->sql("UPDATE pagamento_eventos SET id_pagamento=?,processamento='processado',processado_em=UTC_TIMESTAMP(6),codigo_erro=NULL WHERE id_evento=?",[$p['id_pagamento'],$event['id_evento']]);
            }catch(\Throwable){$this->r->sql("UPDATE pagamento_eventos SET processamento='erro',codigo_erro='conciliacao_pendente',proxima_tentativa_em=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 30 SECOND) WHERE id_evento=?",[$event['id_evento']]);}
        }
    }
}
