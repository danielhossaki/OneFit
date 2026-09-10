<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/OrdersPixGatewayInterface.php';
require_once __DIR__.'/OrdersPixService.php';
require_once __DIR__.'/MatriculaPixRepositorio.php';
require_once __DIR__.'/CobrancaPixRepositorio.php';
require_once __DIR__.'/PixConciliacaoService.php';
require_once __DIR__.'/PagadorPixResolver.php';
require_once __DIR__.'/PixTesteLocal.php';

/** Internal service: user ID must come from an authenticated server session.
 * No controller is exposed. Identity is obtained independently before persistence.
 */
final class MatriculaPixService
{
    private CobrancaPixRepositorio $r;
    private MatriculaPixRepositorio $m;
    public function __construct(\mysqli $db, private OrdersPixGatewayInterface $gateway,
        private ?\Closure $clock = null,private bool $integrado=false,private bool $testeLocal=false)
    {
        $this->r = new CobrancaPixRepositorio($db); $this->m = new MatriculaPixRepositorio($db);
    }
    private function agora(): \DateTimeImmutable
    {
        return ($this->clock ? ($this->clock)() : new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));
    }
    public static function centavos(string $decimal): int
    {
        if (!preg_match('/^([0-9]{1,8})\.([0-9]{2})$/D', $decimal, $m)) throw new PagamentoException();
        $v = (int)$m[1]*100+(int)$m[2]; if ($v<=0) throw new PagamentoException(); return $v;
    }
    /** Client values intentionally have no place in this signature. */
    public function iniciar(int $usuarioAutenticado, int $plano): array
    {
        if($this->testeLocal)PixTesteLocal::exigir();
        (new PagadorPixResolver($this->r))->resolver($usuarioAutenticado,'testing');
        $this->r->transacao(fn()=>$this->r->usuario($usuarioAutenticado));
        $recebedor=$this->identidade(); // No transaction is open during GET /users/me.
        $id = $this->r->transacao(function () use ($usuarioAutenticado,$plano,$recebedor) {
            $this->r->usuario($usuarioAutenticado); $p=$this->m->plano($plano);
            self::centavos($p['valor']);
            $slot=hash('sha256', 'v1:matricula:'.$usuarioAutenticado.':'.$plano);
            $c=$this->r->um("SELECT id_cobranca FROM cobrancas WHERE origem='matricula' AND chave_tentativa_ativa=? FOR UPDATE", [$slot]);
            if ($c) return (int)$c['id_cobranca'];
            $mat=$this->m->criar($usuarioAutenticado,$p);
            $valor=$this->testeLocal?'50.00':$p['valor'];
            $negocio=($this->testeLocal?PixTesteLocal::PREFIXO:'').bin2hex(random_bytes(16));
            $this->r->sql("INSERT INTO cobrancas (id_usuario,origem,id_matricula,chave_negocio,chave_tentativa_ativa,valor_bruto,valor_cobrar,status) VALUES (?,'matricula',?,?,?, ?,?,'aberta')", [$usuarioAutenticado,$mat,$negocio,$slot,$valor,$valor]);
            $id=(int)$this->r->db->insert_id; $uuid=OrdersPixService::uuidV4();
            $this->r->sql("INSERT INTO pagamentos (id_cobranca,referencia_externa,chave_idempotencia,valor,integracao,idempotencia_uuid,recebedor_esperado_id,meio_pagamento,envio_estado) VALUES (?,?,?,?,'orders_pix',?,?,'pix','preparado')", [$id,bin2hex(random_bytes(16)),str_replace('-','',$uuid),$valor,$uuid,$recebedor]);
            if($this->integrado){$this->r->sql('UPDATE cobrancas SET expira_em=DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 DAY) WHERE id_cobranca=?',[$id]);$this->r->sql('UPDATE pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca SET p.expira_em=c.expira_em WHERE p.id_cobranca=?',[$id]);}
            return $id;
        });
        return $this->enviar($usuarioAutenticado,$id,false);
    }
    public function consultarLocal(int $usuario, int $id): array
    {
        return $this->r->transacao(function () use ($usuario,$id) {
            $x=$this->r->tentativa($usuario,$id);
            return ['id_cobranca'=>$id,'id_matricula'=>(int)$x['c']['id_matricula'],'status'=>$x['p']['status_interno'],'envio'=>$x['p']['envio_estado']];
        });
    }
    /** Explicit recovery; never initiated automatically by duplicate clicks. */
    public function recuperar(int $usuario, int $id): array { return $this->enviar($usuario,$id,true); }
    /** Worker-only: re-read a saved Order, including an already terminal one. Never POST. */
    public function consultarConfiavel(int $usuario,int $id):array{return $this->enviar($usuario,$id,true,true);}
    private function identidade(): string
    {
        try { return OrdersPixValidacao::vendedor($this->gateway->consultarIdentidade()); }
        catch (\Throwable) { throw new PagamentoException(); }
    }
    private function enviar(int $usuario,int $id,bool $recuperar,bool $somenteConsulta=false): array
    {
        $local=$this->consultarLocal($usuario,$id);
        if (!$somenteConsulta&&in_array($local['status'],['aprovado','recusado','cancelado','expirado','estornado'],true)) {if($this->integrado)(new PixEfeitos($this->r))->entregar($id);return $local;}
        $pagador=(new PagadorPixResolver($this->r))->resolver($usuario,'testing');
        $recebedor=$this->identidade();
        $now=$this->agora();
        $p=$this->r->transacao(function () use ($usuario,$id,$recuperar,$now,$recebedor,$somenteConsulta) {
            $x=$this->r->tentativa($usuario,$id); $p=$x['p'];
            PixTesteLocal::validar($x['c'],$p);
            if ($p['recebedor_esperado_id']!==$recebedor) throw new PagamentoException();
            if ($somenteConsulta&&!$p['order_id'])throw new PagamentoException();
            if (!$somenteConsulta&&$x['c']['status']!=='aberta') return null;
            if ($p['envio_bloqueio_ate'] && $p['envio_bloqueio_ate']>$now->format('Y-m-d H:i:s.u')) return null;
            if($this->integrado&&$p['ultima_consulta_em']&&$p['ultima_consulta_em']>$now->modify('-5 seconds')->format('Y-m-d H:i:s.u'))return null;
            if ($p['envio_estado']!=='preparado' && !$recuperar) return null;
            // Unknown sends beyond the conservative recovery window need manual review.
            if (!$p['order_id'] && $p['envio_iniciado_em'] && new \DateTimeImmutable($p['envio_iniciado_em'],new \DateTimeZone('UTC')) < $now->modify('-10 minutes')) throw new PagamentoException();
            $this->r->sql("UPDATE pagamentos SET envio_estado='enviando',envio_versao=envio_versao+1,envio_tentativas=envio_tentativas+1,envio_iniciado_em=COALESCE(envio_iniciado_em,?),envio_bloqueio_ate=? WHERE id_pagamento=?", [$now->format('Y-m-d H:i:s.u'),$now->modify('+60 seconds')->format('Y-m-d H:i:s.u'),$p['id_pagamento']]);
            $p['envio_versao']=(int)$p['envio_versao']+1; return $p;
        });
        if (!$p) return $this->consultarLocal($usuario,$id);
        try {
            // This domain envelope has no payer or synthetic APRO fixture.
            $data=$p['order_id'] ? $this->gateway->consultarOrder($p['order_id']) : $this->gateway->criarOrder([
                'ambiente'=>'testing','moeda'=>'BRL','valor_centavos'=>self::centavos($p['valor']),
                'idempotencia'=>$p['idempotencia_uuid'],'external_reference'=>$p['referencia_externa'],
                'recebedor_esperado_id'=>$p['recebedor_esperado_id'],'pagador'=>$pagador]);
            (new PixConciliacaoService($this->r,$this->clock,$this->integrado))->aplicar($usuario,$id,$p['envio_versao'],$data);
            if($this->integrado)(new PixEfeitos($this->r))->entregar($id);
        } catch (\Throwable) {
            $this->r->transacao(function () use ($usuario,$id,$p) {
                $this->r->tentativa($usuario,$id);
                $this->r->sql("UPDATE pagamentos SET envio_estado='incerto',envio_codigo_erro='resultado_nao_confirmado',envio_bloqueio_ate=NULL WHERE id_pagamento=? AND envio_versao=?", [$p['id_pagamento'],$p['envio_versao']]);
            });
            throw new PagamentoException();
        }
        return $this->consultarLocal($usuario,$id);
    }
}
