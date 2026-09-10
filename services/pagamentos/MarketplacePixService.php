<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/MatriculaPixService.php';
final class MarketplacePixService
{
    private PixRepositorio $r;
    public function __construct(\mysqli $db,private OrdersPixGatewayInterface $gateway,private ?\Closure $clock=null,private bool $fixtureOficial=false){$this->r=new PixRepositorio($db);}
    public static function centavos(string $v):int
    {
        if(!preg_match('/^([0-9]{1,8})\.([0-9]{2})$/D',$v,$m))throw new PagamentoException();return (int)$m[1]*100+(int)$m[2];
    }
    private static function decimal(int $v):string{return intdiv($v,100).'.'.str_pad((string)($v%100),2,'0',STR_PAD_LEFT);}
    public function iniciar(int $user,array $carrinho,int $endereco,int $transportadora,string $cashback='0.00'):array
    {
        (new PagadorPixResolver($this->r))->resolver($user);
        if(!$carrinho||count($carrinho)>100)throw new PagamentoException();ksort($carrinho,SORT_NUMERIC);
        foreach($carrinho as $pid=>$qty)if(!ctype_digit((string)$pid)||(int)$pid<=0||!is_int($qty)||$qty<=0||$qty>1000)throw new PagamentoException();
        $desired=self::centavos($cashback);$receiver=OrdersPixValidacao::vendedor($this->gateway->consultarIdentidade());
        $id=$this->r->transacao(function()use($user,$carrinho,$endereco,$transportadora,$desired,$receiver){
            $this->r->usuario($user);
            $key=hash('sha256',json_encode(['pedido-v1',$user,$carrinho,$endereco,$transportadora]));
            $old=$this->r->um("SELECT id_cobranca FROM cobrancas WHERE origem='marketplace' AND chave_tentativa_ativa=? FOR UPDATE",[$key]);if($old)return (int)$old['id_cobranca'];
            $address=$this->r->um('SELECT * FROM enderecos_entrega WHERE id_endereco=? AND id_usuario=?',[$endereco,$user]);if(!$address)throw new PagamentoException();
            $shipping=$this->r->um("SELECT f.id_transportadora,f.valor_frete FROM faixas_cep_frete f JOIN transportadoras t ON t.id_transportadora=f.id_transportadora WHERE t.status='ativo' AND f.id_transportadora=? AND ? BETWEEN f.cep_inicial AND f.cep_final ORDER BY f.valor_frete LIMIT 1",[$transportadora,preg_replace('/\D/','',$address['cep'])]);if(!$shipping)throw new PagamentoException();
            $items=[];$groups=[];$total=0;$earned=0;
            foreach($carrinho as $pid=>$qty){
                $p=$this->r->um('SELECT id_produto,id_vendedor,preco,desconto,cashback_valor,estoque,status FROM produtos WHERE id_produto=? FOR UPDATE',[$pid]);
                if(!$p||$p['status']!=='ativo')throw new PagamentoException();
                // Current locking read: an earlier address read may have opened an
                // older REPEATABLE READ snapshot before another buyer committed.
                $reservations=$this->r->sql("SELECT quantidade FROM pix_reservas WHERE id_produto=? AND estado='ativa' FOR UPDATE",[$pid])->get_result()->fetch_all(MYSQLI_ASSOC);
                $reserved=0;foreach($reservations as $reservation)$reserved+=(int)$reservation['quantidade'];
                if($qty>(int)$p['estoque']-$reserved)throw new PagamentoException();
                $discount=self::centavos($p['desconto']??'0.00');if($discount>10000)throw new PagamentoException();
                $unit=intdiv(self::centavos($p['preco'])*(10000-$discount)+5000,10000);
                $sub=$unit*$qty;$cb=self::centavos($p['cashback_valor'])*$qty;
                $total+=$sub;$earned+=$cb;$seller=(int)($p['id_vendedor']??0);$groups[$seller]=true;
                $items[]=[$pid,$p['id_vendedor'],$qty,$unit,$sub];
            }
            $frete=self::centavos($shipping['valor_frete']);$total+=$frete*count($groups);if($total>9999999999||$earned>9999999999)throw new PagamentoException();
            $balance=$this->r->um("SELECT COALESCE(SUM(CASE WHEN tipo='credito' THEN valor ELSE -valor END),0) saldo FROM cashback WHERE id_usuario=? AND status<>'cancelado'",[$user])['saldo'];
            $pending=$this->r->um("SELECT COALESCE(SUM(cashback_aplicado),0) saldo FROM cobrancas WHERE id_usuario=? AND status='aberta'",[$user])['saldo'];
            $available=max(0,(str_starts_with($balance,'-')?0:self::centavos($balance))-self::centavos($pending));$used=min($desired,$available,$total);$due=$total-$used;
            if($due<=0)throw new PagamentoException(); // No fictitious provider payment for zero.
            if($this->fixtureOficial&&$due!==5000)throw new PagamentoException(); // Before any local records.
            $this->r->sql("INSERT INTO pedido (id_usuario,id_endereco_entrega,valor_total,forma_pagamento,status,endereco_cep,endereco_logradouro,endereco_numero,endereco_complemento,endereco_bairro,endereco_cidade,endereco_uf) VALUES (?,?,?,'pix','aguardando',?,?,?,?,?,?,?)",[$user,$endereco,self::decimal($total),$address['cep'],$address['logradouro'],$address['numero'],$address['complemento'],$address['bairro'],$address['cidade'],$address['uf']]);$order=(int)$this->r->db->insert_id;
            $charged=[];foreach($items as [$pid,$seller,$qty,$unit,$sub]){$cost=isset($charged[$seller??0])?0:$frete;$charged[$seller??0]=true;
                $this->r->sql('INSERT INTO pedido_item (id_pedido,id_produto,id_vendedor,quantidade,preco_unitario,subtotal,id_transportadora,valor_frete) VALUES (?,?,?,?,?,?,?,?)',[$order,$pid,$seller,$qty,self::decimal($unit),self::decimal($sub),$shipping['id_transportadora'],self::decimal($cost)]);
            }
            $this->r->sql("INSERT INTO cobrancas (id_usuario,origem,id_pedido,chave_negocio,chave_tentativa_ativa,valor_bruto,cashback_aplicado,valor_cobrar,cashback_ganho,expira_em) VALUES (?,'marketplace',?,?,?,?,?,?,?,DATE_ADD(UTC_TIMESTAMP(6),INTERVAL 1 DAY))",[$user,$order,bin2hex(random_bytes(16)),$key,self::decimal($total),self::decimal($used),self::decimal($due),self::decimal($earned)]);$id=(int)$this->r->db->insert_id;
            foreach($items as [$pid,$seller,$qty])$this->r->sql('INSERT INTO pix_reservas (id_cobranca,id_produto,quantidade,expira_em) SELECT ?,?,?,expira_em FROM cobrancas WHERE id_cobranca=?',[$id,$pid,$qty,$id]);
            $uuid=OrdersPixService::uuidV4();$this->r->sql("INSERT INTO pagamentos (id_cobranca,referencia_externa,chave_idempotencia,idempotencia_uuid,valor,integracao,recebedor_esperado_id,meio_pagamento,envio_estado,expira_em) SELECT ?,?,?,?,?, 'orders_pix',?,'pix','preparado',expira_em FROM cobrancas WHERE id_cobranca=?",[$id,bin2hex(random_bytes(16)),str_replace('-','',$uuid),$uuid,self::decimal($due),$receiver,$id]);return $id;
        });
        return (new MatriculaPixService($this->r->db,$this->gateway,$this->clock,true))->recuperar($user,$id);
    }
}
