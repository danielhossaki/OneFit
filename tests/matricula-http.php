<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
ini_set('display_errors','0');ini_set('log_errors','0');
$ok=false;$checks=0;
function enrollmentHttp(string $route,?string $cookie=null,?string $post=null):array{
    $c=curl_init('http://localhost/AN25/OneFit/'.$route);
    curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_MAXREDIRS=>4,CURLOPT_COOKIEFILE=>'']);
    if($cookie)curl_setopt($c,CURLOPT_COOKIE,'PHPSESSID='.$cookie);
    if($post!==null)curl_setopt_array($c,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$post]);
    $body=curl_exec($c);$status=curl_getinfo($c,CURLINFO_HTTP_CODE);curl_close($c);
    return [$status,(string)$body];
}
function enrollmentAssert(bool $value):void{global $checks;if(!$value)throw new RuntimeException();$checks++;}
try{
    [$code,$body]=enrollmentHttp('pages/matricula/matricula.php');
    enrollmentAssert($code===200&&str_contains($body,'data-authenticated="0"'));
    foreach([1,2,3,4] as $step)enrollmentAssert(str_contains($body,'data-step="'.$step.'"'));
    enrollmentAssert(str_contains($body,'name="id_plano"')&&!str_contains($body,'Matricular com Pix'));
    session_id(bin2hex(random_bytes(16)));session_start();$sid=session_id();
    // Render-only synthetic session: no real account, no valid payment submission.
    $_SESSION=['id_usuario'=>PHP_INT_MAX,'csrf_token'=>bin2hex(random_bytes(32))];session_write_close();
    [$code,$body]=enrollmentHttp('pages/matricula/matricula.php',$sid);
    enrollmentAssert($code===200&&str_contains($body,'data-authenticated="1"'));
    enrollmentAssert(str_contains($body,'action="../pagamentos/api.php"')&&str_contains($body,'name="csrf_token"'));
    enrollmentAssert(str_contains($body,'Continuar para o Pix')&&!str_contains($body,'saldo fictício')&&!str_contains($body,'registros técnicos'));
    enrollmentAssert(!preg_match('/Fatal error|Warning:|Parse error/',$body));
    enrollmentAssert(!file_exists(__DIR__.'/../pages/pagamentos/planos.php'));
    enrollmentAssert(str_contains($body,'data-step-current="1"'));
    preg_match('/data-wizard="([a-f0-9]{32})"/',$body,$flow);
    [$code,$reload]=enrollmentHttp('pages/matricula/matricula.php?fluxo='.$flow[1].'&etapa=3',$sid);
    enrollmentAssert($code===200&&str_contains($reload,'data-step-current="1"'));
    session_id($sid);session_start();$csrf=$_SESSION['csrf_token'];session_write_close();
    [$code]=enrollmentHttp('pages/matricula/matricula.php',$sid,http_build_query(['acao'=>'wizard','etapa'=>3,'id_plano'=>1,'fluxo'=>$flow[1],'csrf_token'=>$csrf]));enrollmentAssert($code===422);
    [$code]=enrollmentHttp('pages/pagamentos/api.php',$sid,http_build_query(['acao'=>'matricula','id_plano'=>1,'fluxo'=>$flow[1],'csrf_token'=>$csrf,'termos'=>'on']));enrollmentAssert($code===409);
    [$code]=enrollmentHttp('pages/pagamentos/api.php',null,'acao=matricula&id_plano=1');enrollmentAssert($code===401);
    [$code]=enrollmentHttp('pages/pagamentos/api.php',$sid,'acao=matricula&id_plano=1&csrf_token=invalid');enrollmentAssert($code===403);
    $ok=true;
}catch(Throwable){}finally{if(isset($sid)){session_id($sid);session_start();$_SESSION=[];session_destroy();}}
echo json_encode(['http_checks'=>$checks,'passed'=>$ok,'database_writes'=>0,'mercado_pago_calls'=>0]),PHP_EOL;
exit($ok?0:1);
