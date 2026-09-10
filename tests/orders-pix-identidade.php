<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../services/pagamentos/MatriculaPixService.php';
require_once __DIR__.'/../services/pagamentos/OrdersPixGateway.php';
require_once __DIR__.'/../vendor/autoload.php';
use OneFit\Pagamentos\{OrdersPixGateway,OrdersPixService,OrdersPixValidacao,PagamentoException,CobrancaPixRepositorio,PixConciliacaoService};
$n=0;
function identityCheck(bool $v):void{global $n;if(!$v)throw new RuntimeException('Assertion '.($n+1));$n++;}
function identityReject(Closure $f):void{try{$f();}catch(PagamentoException){identityCheck(true);return;}throw new RuntimeException('Expected rejection');}
$now=new DateTimeImmutable('2026-09-08T15:00:00Z');$clock=fn()=>$now;
$data=['id'=>'ORDFIXTURE','user_id'=>'123456','country_code'=>'BR','currency'=>'BRL','created_date'=>'2026-09-07T10:00:00-03:00','last_updated_date'=>'2026-09-07T11:00:00-03:00',
    'total_amount'=>'17.39','external_reference'=>str_repeat('a',32),'status'=>'processed','status_detail'=>'accredited','live_mode'=>true,
    'transactions'=>['payments'=>[['id'=>'PAYFIXTURE','amount'=>'17.39','status'=>'processed','status_detail'=>'accredited','payment_method'=>['id'=>'pix','type'=>'bank_transfer']]]]];
$d=OrdersPixService::normalizar($data,$now);
identityCheck($d['recebedor_id']==='123456');
identityCheck($d['provedor_criado_em']==='2026-09-07T13:00:00.000000Z');
identityCheck($d['provedor_atualizado_em']==='2026-09-07T14:00:00.000000Z');
identityCheck(!array_key_exists('aprovado_em',$d));
foreach(['created_date','last_updated_date'] as $key){foreach([null,'invalid','2026-02-30T00:00:00Z','2026-09-08T15:06:00Z','2026-09-08T10:00:00','1999-01-01T00:00:00Z'] as $value){$bad=$data;$bad[$key]=$value;identityReject(fn()=>OrdersPixService::normalizar($bad,$now));}}
identityCheck(OrdersPixValidacao::data('2026-09-07T10:00:00.123456789Z',$now)==='2026-09-07T10:00:00.123456Z');
$bad=$data;$bad['last_updated_date']='2026-09-06T00:00:00Z';identityReject(fn()=>OrdersPixService::normalizar($bad,$now));
$bad=$data;unset($bad['user_id']);$bad['payer']=['first_name'=>'APRO'];identityReject(fn()=>OrdersPixService::normalizar($bad,$now));
onefitEnv('MERCADO_PAGO_ENVIRONMENT');putenv('MERCADO_PAGO_ENVIRONMENT=testing');putenv('MERCADO_PAGO_ACCESS_TOKEN=TEST-fixture');putenv('MERCADO_PAGO_PUBLIC_KEY=TEST-fixture');
$transport=new class($data) implements MercadoPago\Net\MPHttpClient {
    public int $posts=0;public int $calls=0;public ?array $sent=null;
    public array $identity=['id'=>'123456','country_id'=>'BR','site_id'=>'MLB','tags'=>['test_user'],'email'=>'ignored@example.invalid'];
    public function __construct(public array $order){}
    public function send(MercadoPago\Net\MPRequest $r):MercadoPago\Net\MPResponse {
        $this->calls++;
        if($r->getUri()==='/users/me')return new MercadoPago\Net\MPResponse(200,$this->identity);
        if($r->getMethod()==='POST'){$this->posts++;$this->sent=json_decode($r->getPayload(),true);}
        return new MercadoPago\Net\MPResponse(200,$this->order);
    }
};
$g=new OrdersPixGateway($transport,$clock);identityCheck($transport->calls===0);
new OneFit\Pagamentos\MatriculaPixService(new mysqli(),$g,$clock);
identityCheck($transport->calls===0); // Real adapter accepted without construction I/O.
$identity=$g->consultarIdentidade();identityCheck(array_keys($identity)===['id','pais','site','test_user']);identityCheck(OrdersPixValidacao::vendedor($identity)==='123456');
$p=['ambiente'=>'testing','moeda'=>'BRL','valor_centavos'=>1739,'idempotencia'=>OrdersPixService::uuidV4(),'external_reference'=>str_repeat('a',32),'recebedor_esperado_id'=>'123456'];
$p['pagador']=OneFit\Pagamentos\PagadorPix::testing();$g->criarOrder($p);identityCheck(!isset($transport->sent['payer']['first_name']));identityCheck($transport->posts===1);
$transport->identity['tags']=[];$g=new OrdersPixGateway($transport,$clock);identityReject(fn()=>$g->criarOrder($p));identityCheck($transport->posts===1);
$transport->identity['tags']=['test_user'];$transport->identity['id']='654321';$g=new OrdersPixGateway($transport,$clock);identityReject(fn()=>$g->criarOrder($p));identityCheck($transport->posts===1);
$transport->identity['id']='123456';
$blocked=$p;$blocked['pagador']=OneFit\Pagamentos\PagadorPix::cadastro('fixture@example.invalid');
$calls=$transport->calls;identityReject(fn()=>$g->criarOrder($blocked));identityCheck($transport->calls===$calls);
$blocked=$p;unset($blocked['pagador']);$blocked['payer']=['email'=>'forged@testuser.com'];
identityReject(fn()=>$g->criarOrder($blocked));identityCheck($transport->calls===$calls);

/** Repository double: no mysqli connection, no SQL execution. SQL commands are
 * captured to exercise the actual reconciliation transaction/state transitions.
 */
final class IdentityRepository extends CobrancaPixRepositorio {
    public array $payment;public array $charge;public array $matricula;public array $events=[];public array $writes=[];
    public function __construct(){
        $this->payment=['envio_versao'=>1,'valor'=>'17.39','referencia_externa'=>str_repeat('a',32),'recebedor_esperado_id'=>'123456','order_id'=>null,'id_externo'=>null,'status_interno'=>'pendente','id_pagamento'=>1];
        $this->charge=['status'=>'aberta','id_matricula'=>1];$this->matricula=['id_matricula'=>1,'status'=>'pendente','data_inicio'=>null,'data_fim'=>null,'duracao_contratada_dias'=>43];
    }
    public function transacao(Closure $f):mixed {
        $old=[$this->payment,$this->charge,$this->matricula,$this->events,$this->writes];
        try{return $f();}catch(Throwable $e){[$this->payment,$this->charge,$this->matricula,$this->events,$this->writes]=$old;throw $e;}
    }
    public function tentativa(int $usuario,int $cobranca):array {return ['p'=>$this->payment,'c'=>$this->charge];}
    public function um(string $sql,array $args=[]):?array {
        if(str_contains($sql,'FROM matricula'))return $this->matricula;
        if(str_contains($sql,'FROM pagamento_eventos'))return isset($this->events[$args[0]])?['id_evento'=>1]:null;
        throw new RuntimeException('Unexpected read');
    }
    public function sql(string $sql,array $args=[]):mysqli_stmt {
        $this->writes[]=[$sql,$args];
        if(str_starts_with($sql,'INSERT INTO pagamento_eventos'))$this->events[$args[1]]=true;
        elseif(str_starts_with($sql,'UPDATE pagamentos')){$this->payment['status_interno']=$args[6];$this->payment['aprovado_em']=$args[10];$this->payment['ultima_consulta_em']=$args[9];}
        elseif(str_starts_with($sql,'UPDATE matricula')){$this->matricula['status']='ativa';$this->matricula['data_inicio']=$args[0];$this->matricula['data_fim']=$args[1];}
        elseif(str_starts_with($sql,"UPDATE cobrancas SET status='liquidada'"))$this->charge['status']='liquidada';
        return new class extends mysqli_stmt {public function __construct(){}};
    }
}
$d+=['http'=>200,'request_id'=>'fixture'];
foreach([null,'654321'] as $receiver){$r=new IdentityRepository();$bad=$d;$bad['recebedor_id']=$receiver;identityReject(fn()=>(new PixConciliacaoService($r,$clock))->aplicar(1,1,1,$bad));identityCheck($r->matricula['status']==='pendente'&&$r->writes===[]);}
$r=new IdentityRepository();$pending=$d;foreach(['status','pagamento_status'] as $k)$pending[$k]='action_required';foreach(['status_detail','pagamento_status_detail'] as $k)$pending[$k]='waiting_transfer';
(new PixConciliacaoService($r,$clock))->aplicar(1,1,1,$pending);identityCheck($r->matricula['data_inicio']===null);identityCheck($r->payment['aprovado_em']===null);
$r=new IdentityRepository();$s=new PixConciliacaoService($r,$clock);$s->aplicar(1,1,1,$d);
identityCheck($r->matricula['data_inicio']==='2026-09-08');identityCheck($r->matricula['data_fim']==='2026-10-21');
identityCheck($r->payment['aprovado_em']==='2026-09-08 15:00:00.000000');identityCheck($r->payment['ultima_consulta_em']==='2026-09-08 15:00:00.000000');
$old=[$r->matricula,$r->payment,count($r->events),count($r->writes)];
(new PixConciliacaoService($r,fn()=>$now->modify('+1 day')))->aplicar(1,1,1,$d);identityCheck($old===[$r->matricula,$r->payment,count($r->events),count($r->writes)]);
foreach(['provedor_criado_em','provedor_atualizado_em'] as $k){$bad=$d;unset($bad[$k]);$r=new IdentityRepository();identityReject(fn()=>(new PixConciliacaoService($r,$clock))->aplicar(1,1,1,$bad));}
echo 'OK: '.$n.' verificacoes identidade/datas/conciliacao; rede=0; banco=0.',PHP_EOL;
