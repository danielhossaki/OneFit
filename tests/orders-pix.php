<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../services/pagamentos/OrdersPixGateway.php';
use OneFit\Pagamentos\{OrdersPixService,OrdersPixGateway,PagamentoException,DiagnosticoPagamento};
$n=0;
function okPix(bool $value):void{global $n;$n++;if(!$value)throw new RuntimeException('Falha teste '.$n);}
function failsPix(Closure $f):void{try{$f();}catch(Throwable){okPix(true);return;}okPix(false);}
$service=new OrdersPixService();$gateway=new OrdersPixGateway();
okPix(!class_exists(MercadoPago\MercadoPagoConfig::class,false));
$prep=$service->prepararTesteOficial();$payload=$prep['payload'];
okPix($payload['total_amount']==='50.00'&&$payload['transactions']['payments'][0]['amount']==='50.00');
okPix($payload['type']==='online'&&$payload['processing_mode']==='automatic');
okPix($payload['payer']===['email'=>'test_user_br@testuser.com','first_name'=>'APRO']);
okPix($payload['transactions']['payments'][0]['payment_method']===['id'=>'pix','type'=>'bank_transfer']);
okPix((bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$prep['idempotencia']));
okPix($prep['idempotencia']!==$service->prepararTesteOficial()['idempotencia']);
okPix((bool)preg_match('/^[a-f0-9]{32}$/D',$payload['external_reference']));
okPix(OrdersPixService::decimal(1)==='0.01');failsPix(fn()=>OrdersPixService::decimal(0));failsPix(fn()=>OrdersPixService::decimal(-1));
$data=['id'=>'ORDTEST123','country_code'=>'BRA','total_amount'=>'50.00','external_reference'=>$payload['external_reference'],'status'=>'action_required','status_detail'=>'waiting_transfer','transactions'=>['payments'=>[['id'=>'PAYTEST123','amount'=>'50.00','status'=>'action_required','status_detail'=>'waiting_transfer','payment_method'=>['id'=>'pix','type'=>'bank_transfer','ticket_url'=>'https://www.mercadopago.com.br/test','qr_code'=>'fixture','qr_code_base64'=>'Zml4dHVyZQ==']]]]];
$data+=['user_id'=>'123456','created_date'=>'2026-01-01T00:00:00Z','last_updated_date'=>'2026-01-01T00:01:00Z'];
$norm=OrdersPixService::normalizar($data);
okPix($norm['status_interno']==='pendente'&&$norm['pagamento_id']==='PAYTEST123');
okPix($norm['ticket_url']!==null&&$norm['qr_code']==='fixture'&&$norm['qr_code_base64']==='Zml4dHVyZQ==');
foreach([['processed','accredited','aprovado'],['processing',null,'pendente'],['failed','rejected','recusado'],['expired',null,'expirado']] as [$s,$d,$expected])okPix(OrdersPixService::statusInterno($s,$d)===$expected);
failsPix(fn()=>OrdersPixService::statusInterno('unexpected',null));
$bad=$data;$bad['total_amount']='0.00';failsPix(fn()=>OrdersPixService::normalizar($bad));
$bad=$data;$bad['transactions']['payments'][0]['payment_method']['ticket_url']='https://evil.example';failsPix(fn()=>OrdersPixService::normalizar($bad));
$safe=DiagnosticoPagamento::extrair(['message'=>'invalid pessoa@example.com APP_USR-secret','payer'=>['email'=>'pessoa@example.com']]);
okPix(!str_contains(json_encode($safe),'@')&&!str_contains(json_encode($safe),'APP_USR'));
require_once __DIR__.'/../vendor/autoload.php';onefitEnv('MERCADO_PAGO_ENVIRONMENT');
putenv('MERCADO_PAGO_ENVIRONMENT=testing');putenv('MERCADO_PAGO_ACCESS_TOKEN=TEST-fixture');putenv('MERCADO_PAGO_PUBLIC_KEY=TEST-fixture');
$fake=new class($data) implements MercadoPago\Net\MPHttpClient{
 public array $calls=[];public bool $fail=false;public function __construct(public array $data){}
 public function send(MercadoPago\Net\MPRequest $r):MercadoPago\Net\MPResponse{
 if($r->getUri()==='/users/me')return new MercadoPago\Net\MPResponse(200,['id'=>'123456','country_id'=>'BR','site_id'=>'MLB','tags'=>['test_user']]);
 $key=array_values(array_filter($r->getHeaders(),fn($h)=>str_starts_with($h,'X-Idempotency-Key:')));
 $this->calls[]=[$r->getMethod(),$r->getUri(),$key];
 if($this->fail)throw new MercadoPago\Exceptions\MPApiException('SEGREDO',new MercadoPago\Net\MPResponse(400,['message'=>'invalid SEGREDO']));
 return new MercadoPago\Net\MPResponse(201,$this->data);}
};
$adapter=new OrdersPixGateway($fake);okPix(count($fake->calls)===0);
$prep['pagador']=OneFit\Pagamentos\PagadorPix::testing();$adapter->criarOrder($prep);$adapter->criarOrder($prep);
okPix($fake->calls[0]===$fake->calls[1]&&$fake->calls[0][2]===['X-Idempotency-Key: '.$prep['idempotencia']]);
$adapter->consultarOrder('ORDTEST123');okPix($fake->calls[2][0]==='GET');
$fake->fail=true;try{$adapter->criarOrder($prep);}catch(PagamentoException $e){okPix($e->httpStatus===400&&!str_contains($e->getMessage(),'SEGREDO')&&!str_contains(json_encode($e->diagnosticoTecnico()),'SEGREDO'));}
okPix(!isset($GLOBALS['conn']));echo 'OK: '.$n.' verificacoes Orders/Pix; rede=0; banco=0.',PHP_EOL;
