<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PixSeguranca.php';
final class PixEfeitos
{
    public function __construct(private PixRepositorio $r){}
    /** Called inside financial transaction, only for its first terminal transition. */
    public function aplicar(array $c,string $status):void
    {
        $id=$c['id_cobranca'];$user=$c['id_usuario'];
        if($c['origem']==='marketplace'){
            $order=$this->r->um('SELECT id_pedido,status FROM pedido WHERE id_pedido=? AND id_usuario=? FOR UPDATE',[$c['id_pedido'],$user]);
            if(!$order||$order['status']!=='aguardando')throw new PagamentoException();
            $reservas=$this->r->sql('SELECT * FROM pix_reservas WHERE id_cobranca=? ORDER BY id_produto FOR UPDATE',[$id])->get_result()->fetch_all(MYSQLI_ASSOC);
            if(!$reservas)throw new PagamentoException();
            foreach($reservas as $v){
                if($v['estado']!=='ativa')throw new PagamentoException();
                if($status==='aprovado'){
                    $q=$this->r->sql('UPDATE produtos SET estoque=estoque-? WHERE id_produto=? AND estoque>=?',[$v['quantidade'],$v['id_produto'],$v['quantidade']]);
                    if($q->affected_rows!==1)throw new PagamentoException();
                }
            }
            $this->r->sql('UPDATE pix_reservas SET estado=? WHERE id_cobranca=?',[$status==='aprovado'?'consumida':'liberada',$id]);
            $this->r->sql('UPDATE pedido SET status=? WHERE id_pedido=?',[$status==='aprovado'?'pago':'cancelado',$c['id_pedido']]);
            if($status==='aprovado')foreach([['debito','uso',$c['cashback_aplicado'],'utilizado'],['credito','produto',$c['cashback_ganho'],'disponivel']] as [$tipo,$origem,$valor,$estado]){
                if(MarketplacePixService::centavos($valor)>0)$this->r->sql('INSERT INTO cashback (id_usuario,id_cobranca,valor,tipo,origem,status,descricao) VALUES (?,?,?,?,?,?,?)',[$user,$id,$valor,$tipo,$origem,$estado,'Pagamento Pix confirmado']);
            }
        }
        if($status==='aprovado'){
            $tipo=$c['origem']==='matricula'?'info':'compra';
            $this->r->sql('INSERT INTO pix_notificacoes (id_cobranca,id_usuario,tipo) VALUES (?,?,?)',[$id,$user,$tipo]);
            if($c['origem']==='marketplace'){
                $sellers=$this->r->sql('SELECT DISTINCT id_vendedor FROM pedido_item WHERE id_pedido=? AND id_vendedor IS NOT NULL AND id_vendedor<>?',[$c['id_pedido'],$user])->get_result()->fetch_all(MYSQLI_ASSOC);
                foreach($sellers as $s)$this->r->sql("INSERT INTO pix_notificacoes (id_cobranca,id_usuario,tipo) VALUES (?,?,'compra')",[$id,$s['id_vendedor']]);
            }
        }
    }
    /** Separate transaction: notification failure cannot undo financial approval. */
    public function entregar(int $id):void
    {
        try{$this->r->transacao(function()use($id){
            $items=$this->r->sql('SELECT * FROM pix_notificacoes WHERE id_cobranca=? AND entregue_em IS NULL FOR UPDATE',[$id])->get_result()->fetch_all(MYSQLI_ASSOC);
            foreach($items as $item){
                $pref=$this->r->um('SELECT atualizacoes_compras FROM preferencias_usuario WHERE id_usuario=?',[$item['id_usuario']]);
                if($item['tipo']==='info'||!$pref||(int)$pref['atualizacoes_compras']===1)
                    $this->r->sql('INSERT INTO notificacoes (usuario_id,titulo,mensagem,tipo,link,criada_em) VALUES (?,?,?,?,?,UTC_TIMESTAMP())',[$item['id_usuario'],$item['tipo']==='info'?'Matricula aprovada':'Pedido confirmado','Pagamento confirmado com sucesso.',$item['tipo'],'/AN25/OneFit/pages/dashboard/dashboard.php']);
                $this->r->sql('UPDATE pix_notificacoes SET entregue_em=UTC_TIMESTAMP(6) WHERE id_cobranca=? AND id_usuario=? AND tipo=?',[$id,$item['id_usuario'],$item['tipo']]);
            }
        });}catch(\Throwable){error_log('OneFit Pix: notificacao pendente de reprocessamento');}
    }
}
