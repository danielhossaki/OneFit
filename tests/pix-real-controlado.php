<?php
declare(strict_types=1);
// Manual, explicit, one-shot diagnostic. Never part of the automated test suite.
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--uma-order-tecnica'){http_response_code(404);exit(1);}
ini_set('display_errors','0');ini_set('log_errors','0');
require __DIR__.'/../services/pagamentos/PixCheckout.php';
use OneFit\Pagamentos\{PixRepositorio,PixCheckout,OrdersPixGateway,OrdersPixGatewayInterface,OrdersPixValidacao};
final class OneShotPix implements OrdersPixGatewayInterface {
    private OrdersPixGateway $gateway;private ?array $identity=null;public int $posts=0;public int $gets=0;public array $safe=[];
    public function __construct(){$this->gateway=new OrdersPixGateway();}
    public function consultarIdentidade():array{return $this->identity??=$this->gateway->consultarIdentidade();}
    public function criarOrder(array $p):array{if($this->posts++!==0)throw new RuntimeException();try{$d=$this->gateway->criarOrder($p);}catch(OneFit\Pagamentos\PagamentoException $e){$this->safe[]=$e->diagnosticoTecnico();throw $e;}$this->record($d);return $d;}
    public function consultarOrder(string $id):array{if($this->gets++!==0)throw new RuntimeException();$d=$this->gateway->consultarOrder($id);$this->record($d);return $d;}
    private function record(array $d):void{$this->safe[]=['http'=>$d['http'],'order_mascarada'=>substr($d['id'],0,5).'…'.substr($d['id'],-4),'pagamento_mascarado'=>substr($d['pagamento_id'],0,5).'…'.substr($d['pagamento_id'],-4),'status'=>$d['status'],'pagamento_status'=>$d['pagamento_status'],'detalhe'=>$d['pagamento_status_detail'],'valor'=>'50.00','moeda'=>$d['moeda'],'qr_presente'=>!empty($d['qr_code']),'imagem_presente'=>!empty($d['qr_code_base64']),'ticket_presente'=>!empty($d['ticket_url']),'vencimento_retornado'=>$d['expiracao']??null,'request_id'=>$d['request_id']??null,'live_mode'=>$d['live_mode']??null];}
}
function realSnapshot(mysqli $db):array{
    $out=[];foreach(['usuarios','cadastro_planos','matricula','cobrancas','pagamentos','pagamento_eventos','pagamento','pedido','pedido_item','produtos','cashback','notificacoes','preferencias_usuario','pix_reservas','pix_notificacoes','pix_testes_tecnicos'] as $t){$rows=$db->query("SELECT * FROM `$t`")->fetch_all(MYSQLI_ASSOC);$encoded=array_map('serialize',$rows);sort($encoded,SORT_STRING);$out[$t]=[count($rows),hash('sha256',serialize($encoded))];}return $out;
}
$ids=[];$ok=false;$stage='preflight';$g=null;$before=[];
try{
    $private=realpath($argv[2]??'');$public=realpath(__DIR__.'/../../../../');
    if(!$private||!is_dir($private)||str_starts_with(strtolower($private),strtolower($public)))throw new RuntimeException();
    require __DIR__.'/../config/conn.php';onefitPagamentosConfiguracao('testing');
    $before=realSnapshot($conn);$r=new PixRepositorio($conn);
    $guard=@fopen($private.'/order-integrada-once.lock','x');if(!$guard)throw new RuntimeException();fwrite($guard,'Single attempt reserved '.gmdate('c'));fclose($guard);
    $stage='identity';$g=new OneShotPix();OrdersPixValidacao::vendedor($g->consultarIdentidade());
    echo json_encode(['conta_teste'=>true,'pais'=>'BR','site'=>'MLB','ambiente'=>'testing']),PHP_EOL;
    $stage='fixtures';$tag=bin2hex(random_bytes(8));
    $r->sql("INSERT INTO usuarios (nome,senha,nacionalidade,data_nascimento,genero,cpf,endereco,cidade_estado,email,celular,status,email_verificado) VALUES ('Fixture Pix tecnico','!disabled','fixture','2000-01-01','outro',?,'fixture','fixture',?,'fixture','ativo',1)",['T'.substr($tag,0,13),$tag.'@example.invalid']);$ids['user']=$usuarioTecnico=(int)$conn->insert_id;
    $r->sql("INSERT INTO cadastro_planos (nome,valor,duracao_dias,status) VALUES (?,'50.00',31,'ativo')",['fixture-oficial-pix-'.$tag]);$ids['plan']=$planoTecnico=(int)$conn->insert_id;
    $r->sql('INSERT INTO pix_testes_tecnicos (id_usuario,id_plano) VALUES (?,?)',[$usuarioTecnico,$planoTecnico]);
    $stage='single-post';$s=new PixCheckout($conn,$g);$a=$s->matricula($usuarioTecnico,$planoTecnico);$ref=$s->referencia($usuarioTecnico,$a['id_cobranca']);$local=$s->dados($usuarioTecnico,$ref);
    if($local['valor']!=='50.00')throw new RuntimeException();
    if($local['status']==='pendente'){$stage='single-poll';sleep(6);$local=$s->dados($usuarioTecnico,$ref,true);}
    $stage='local-check';$m=$r->um('SELECT status,data_inicio,data_fim,duracao_contratada_dias FROM matricula WHERE id_matricula=?',[$a['id_matricula']]);
    $ok=$local['status']==='aprovado'&&$m['status']==='ativa'&&$m['data_inicio']!==null&&$m['data_fim']!==null;
    echo json_encode(['resultado'=>$ok?'aprovacao_local_confirmada':'inconclusivo','vencimento_local'=>$local['vencimento'],'posts'=>$g->posts,'consultas_order'=>$g->gets,'respostas_sanitizadas'=>$g->safe],JSON_UNESCAPED_UNICODE),PHP_EOL;
}catch(Throwable){echo json_encode(['etapa'=>$stage,'erro'=>'validacao_nao_concluida_sem_repeticao','posts'=>$g?->posts??0,'consultas_order'=>$g?->gets??0,'respostas_sanitizadas'=>$g?->safe??[]]),PHP_EOL;}
finally{
    if($ids){try{
        $conn=new mysqli(onefitEnv('DB_HOST'),onefitEnv('DB_USER'),onefitEnv('DB_PASSWORD'),onefitEnv('DB_NAME'));$conn->set_charset('utf8mb4');$r=new PixRepositorio($conn);
        $r->transacao(function()use($r,$ids){$u=$ids['user'];
            $r->sql('DELETE FROM pix_testes_tecnicos WHERE id_usuario=?',[$u]);$r->sql('DELETE FROM notificacoes WHERE usuario_id=?',[$u]);$r->sql('DELETE FROM pix_notificacoes WHERE id_usuario=?',[$u]);
            $r->sql('DELETE e FROM pagamento_eventos e JOIN pagamentos p ON p.id_pagamento=e.id_pagamento JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$u]);
            $r->sql('DELETE p FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$u]);$r->sql('DELETE FROM cobrancas WHERE id_usuario=?',[$u]);$r->sql('DELETE FROM matricula WHERE id_usuario=?',[$u]);$r->sql('DELETE FROM usuarios WHERE id_usuario=?',[$u]);if(isset($ids['plan']))$r->sql('DELETE FROM cadastro_planos WHERE id_plano=?',[$ids['plan']]);
        });$unchanged=$before===realSnapshot($conn);if(!$unchanged)$ok=false;echo json_encode(['limpeza'=>'concluida','dados_antigos_iguais'=>$unchanged]),PHP_EOL;
    }catch(Throwable){$ok=false;echo '{"limpeza":"falhou_revisao_manual_necessaria"}',PHP_EOL;}}
}
exit($ok?0:1);
