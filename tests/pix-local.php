<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require __DIR__.'/../services/pagamentos/PixTesteLocal.php';
use OneFit\Pagamentos\PixTesteLocal;
onefitEnv('MERCADO_PAGO_ENVIRONMENT');$old=getenv('MERCADO_PAGO_ENVIRONMENT');$server=$_SERVER;putenv('MERCADO_PAGO_ENVIRONMENT=testing');
$n=0;function localCheck($v){global $n;if(!$v)throw new RuntimeException('Local assertion');$n++;}
try{
foreach([['localhost','127.0.0.1'],['127.0.0.1:80','127.0.0.1'],['[::1]','::1']] as [$host,$ip]){$_SERVER=['HTTP_HOST'=>$host,'REMOTE_ADDR'=>$ip,'SERVER_ADDR'=>$ip];localCheck(PixTesteLocal::permitido());}
$valid=['HTTP_HOST'=>'localhost','REMOTE_ADDR'=>'127.0.0.1','SERVER_ADDR'=>'127.0.0.1'];
foreach([['REMOTE_ADDR'=>'192.0.2.1'],['SERVER_ADDR'=>'192.0.2.1'],['HTTP_HOST'=>'example.invalid'],['HTTP_X_FORWARDED_FOR'=>'127.0.0.1'],['HTTP_FORWARDED'=>'for=127.0.0.1']] as $change){$_SERVER=array_replace($valid,$change);localCheck(!PixTesteLocal::permitido());}
$_SERVER=$valid;putenv('MERCADO_PAGO_ENVIRONMENT=production');localCheck(!PixTesteLocal::permitido());putenv('MERCADO_PAGO_ENVIRONMENT=testing');
$c=['origem'=>'matricula','chave_negocio'=>PixTesteLocal::PREFIXO.'fixture','valor_cobrar'=>'50.00'];$p=['valor'=>'50.00','ambiente'=>'testing'];PixTesteLocal::validar($c,$p);localCheck(true);
foreach([['valor'=>'49.00'],['ambiente'=>'production']] as $change){try{PixTesteLocal::validar($c,array_replace($p,$change));throw new LogicException();}catch(OneFit\Pagamentos\PagamentoException){localCheck(true);}}
}finally{$_SERVER=$server;putenv('MERCADO_PAGO_ENVIRONMENT='.$old);}
echo "OK: $n verificacoes local/testing; rede=0; banco=0.\n";
