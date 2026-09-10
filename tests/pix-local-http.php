<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||($argv[1]??'')!=='--uma-order-local'){http_response_code(404);exit(1);}
ini_set('display_errors','0');ini_set('log_errors','0');
require __DIR__.'/../services/pagamentos/PixCheckout.php';
use OneFit\Pagamentos\PixRepositorio;
$ids=[];$ok=false;$stage='setup';$sent=0;$before=[];
function localSnapshot(mysqli $db):array{$out=[];foreach(['usuarios','cadastro_planos','matricula','cobrancas','pagamentos','pagamento_eventos','pagamento','pedido','pedido_item','produtos','cashback','notificacoes','pix_notificacoes','pix_testes_tecnicos'] as $t){$rows=$db->query("SELECT * FROM `$t`")->fetch_all(MYSQLI_ASSOC);$v=array_map('serialize',$rows);sort($v);$out[$t]=[count($rows),hash('sha256',serialize($v))];}return $out;}
function localHttp(string $route,string $sid,?array $post=null):array{
    $c=curl_init('http://localhost/AN25/OneFit/'.$route);curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>3,CURLOPT_COOKIE=>'PHPSESSID='.$sid,CURLOPT_TIMEOUT=>60,CURLOPT_HTTPHEADER=>['Accept: application/json']]);if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($post)]);
    $body=curl_exec($c);$code=curl_getinfo($c,CURLINFO_HTTP_CODE);$url=curl_getinfo($c,CURLINFO_EFFECTIVE_URL);curl_close($c);return [$code,(string)$body,$url];
}
try{
    $private=realpath($argv[2]??'');if(!$private||str_starts_with(strtolower($private),strtolower(realpath(__DIR__.'/../../../../'))))throw new RuntimeException();
    require __DIR__.'/../config/conn.php';onefitPagamentosConfiguracao('testing');$before=localSnapshot($conn);$r=new PixRepositorio($conn);$tag=bin2hex(random_bytes(8));
    $r->sql("INSERT INTO usuarios (nome,senha,nacionalidade,data_nascimento,genero,cpf,endereco,cidade_estado,email,celular,status,email_verificado) VALUES ('Fixture manual local','!disabled','fixture','2000-01-01','outro',?,'fixture','fixture',?,'fixture','ativo',1)",['T'.substr($tag,0,13),$tag.'@example.invalid']);$ids['user']=$fixtureUser=(int)$conn->insert_id;
    $r->sql("INSERT INTO cadastro_planos (nome,valor,duracao_dias,status) VALUES (?,'17.39',31,'ativo')",['fixture-local-http-'.$tag]);$ids['plan']=$fixturePlan=(int)$conn->insert_id;
    session_id(bin2hex(random_bytes(16)));session_start();$sid=session_id();$_SESSION=['id_usuario'=>$fixtureUser,'csrf_token'=>bin2hex(random_bytes(32))];$csrf=$_SESSION['csrf_token'];session_write_close();
    $stage='step1';[$code,$html]=localHttp('pages/matricula/matricula.php',$sid);if($code!==200||!str_contains($html,'data-step-current="1"')||!preg_match('/data-wizard="([a-f0-9]{32})"/',$html,$matches))throw new RuntimeException();
    $base=['csrf_token'=>$csrf,'fluxo'=>$matches[1]];
    $steps=[1=>['nome'=>'Fixture manual','cpf'=>'52998224725','nascimento'=>'2000-01-01','genero'=>'outro','telefone'=>'11999999999','email'=>'fixture@example.invalid'],2=>['cep'=>'01001000','endereco'=>'Fixture','numero'=>'1','complemento'=>'','bairro'=>'Fixture','cidade'=>"S\u{00e3}o Paulo",'estado'=>'SP'],3=>['id_plano'=>(string)$fixturePlan]];
    foreach($steps as $step=>$fields){$stage='step'.$step;[$code,$body]=localHttp('pages/matricula/matricula.php',$sid,$base+['acao'=>'wizard','etapa'=>$step]+$fields);if($code!==200||(json_decode($body,true)['etapa']??null)!==$step+1)throw new RuntimeException();}
    $stage='single-order';$guard=@fopen($private.'/pix-local-http-once.lock','x');if(!$guard)throw new RuntimeException();fwrite($guard,'One local attempt '.gmdate('c'));fclose($guard);$sent=1;
    [$code,$html,$url]=localHttp('pages/pagamentos/api.php',$sid,$base+['acao'=>'matricula','id_plano'=>$fixturePlan,'termos'=>'on']);if($code!==200||!str_contains($url,'/pagamentos/pix.php?r='))throw new RuntimeException();
    $p=$r->um('SELECT p.*,c.id_matricula,c.valor_cobrar FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$fixtureUser]);if(!$p)throw new RuntimeException();
    echo json_encode(['criacao_http'=>(int)$p['envio_http'],'tela_pix_http'=>$code,'valor_tecnico'=>$p['valor'],'valor_plano'=>'17.39','qr_presente'=>!empty($p['pix_qr']),'imagem_presente'=>!empty($p['pix_qr_base64']),'status_inicial'=>$p['status_interno']]),PHP_EOL;
    $stage='poll';sleep(6);[$code,$body]=localHttp('pages/pagamentos/api.php',$sid,['acao'=>'status','csrf_token'=>$csrf,'referencia'=>$p['referencia_externa']]);$status=json_decode($body,true);if($code!==200||($status['status']??null)!=='aprovado')throw new RuntimeException();
    $m=$r->um('SELECT status,data_inicio,data_fim,valor_contratado FROM matricula WHERE id_matricula=?',[$p['id_matricula']]);if($m['status']!=='ativa'||$m['valor_contratado']!=='17.39')throw new RuntimeException();
    $stage='repeat';sleep(2);[$repeat]=localHttp('pages/pagamentos/api.php',$sid,$base+['acao'=>'matricula','id_plano'=>$fixturePlan,'termos'=>'on']);if($repeat!==200)throw new RuntimeException();
    if($m!==$r->um('SELECT status,data_inicio,data_fim,valor_contratado FROM matricula WHERE id_matricula=?',[$p['id_matricula']]))throw new RuntimeException();
    $count=(int)$r->um('SELECT COUNT(*) n FROM cobrancas WHERE id_usuario=?',[$fixtureUser])['n'];if($count!==1)throw new RuntimeException();
    echo json_encode(['poll_http'=>$code,'status'=>'aprovado','matricula_ativada_uma_vez'=>true,'matriculas_criadas'=>1,'cobrancas_criadas'=>1,'pagamentos_criados'=>1,'criacao_solicitada'=>1,'reenvio_reutilizou'=>true]),PHP_EOL;$ok=true;
}catch(Throwable){echo json_encode(['falha_etapa'=>$stage,'criacao_solicitada'=>$sent,'erro'=>'validacao_nao_concluida_sem_nova_order']),PHP_EOL;}
finally{
    if(isset($sid)){session_id($sid);session_start();$_SESSION=[];session_destroy();}
    if($ids){try{$conn=new mysqli(onefitEnv('DB_HOST'),onefitEnv('DB_USER'),onefitEnv('DB_PASSWORD'),onefitEnv('DB_NAME'));$conn->set_charset('utf8mb4');$r=new PixRepositorio($conn);$r->transacao(function()use($r,$ids){$u=$ids['user'];$r->sql('DELETE FROM notificacoes WHERE usuario_id=?',[$u]);$r->sql('DELETE FROM pix_notificacoes WHERE id_usuario=?',[$u]);$r->sql('DELETE e FROM pagamento_eventos e JOIN pagamentos p ON p.id_pagamento=e.id_pagamento JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$u]);$r->sql('DELETE p FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$u]);$r->sql('DELETE FROM cobrancas WHERE id_usuario=?',[$u]);$r->sql('DELETE FROM matricula WHERE id_usuario=?',[$u]);$r->sql('DELETE FROM usuarios WHERE id_usuario=?',[$u]);if(isset($ids['plan']))$r->sql('DELETE FROM cadastro_planos WHERE id_plano=?',[$ids['plan']]);});$equal=$before===localSnapshot($conn);$ok=$ok&&$equal;echo json_encode(['limpeza_fixtures'=>'concluida','dados_anteriores_iguais'=>$equal]),PHP_EOL;}catch(Throwable){$ok=false;echo '{"limpeza":"falhou_revisao_necessaria"}',PHP_EOL;}}
}
exit($ok?0:1);
