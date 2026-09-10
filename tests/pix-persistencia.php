<?php
declare(strict_types=1);
// Explicit CLI opt-in; never execute through the web or a normal unit test run.
if (PHP_SAPI!=='cli'||!in_array('--development-fixtures',$argv??[],true)) { http_response_code(404); exit(1); }
ini_set('display_errors','0'); ini_set('log_errors','0');
require_once __DIR__.'/../services/pagamentos/MatriculaPixService.php';
use OneFit\Pagamentos\{MatriculaPixService,OrdersPixGatewayInterface,PixRepositorio,PagamentoException};

final class FakePixPersistencia implements OrdersPixGatewayInterface
{
    public function consultarIdentidade():array {return ['id'=>'123456','pais'=>'BR','site'=>'MLB','test_user'=>true];}
    public array $orders=[]; public array $keys=[]; public int $calls=0;
    public string $mode='pending'; public ?Closure $during=null; public array $change=[];
    public function criarOrder(array $p): array
    {
        $this->calls++;$this->keys[]=$p['idempotencia'];
        if ($this->during) { $fn=$this->during; $this->during=null; $fn(); }
        if ($this->mode==='timeout_before') throw new RuntimeException('sensitive fixture must not escape');
        $key=$p['idempotencia'];
        $this->orders[$key]??=['id'=>'ORD'.strtoupper(str_replace('-','',$key)),'pagamento_id'=>'PAY'.strtoupper(str_replace('-','',$key)),
            'ambiente'=>'testing','moeda'=>'BRL','valor_centavos'=>$p['valor_centavos'],'external_reference'=>$p['external_reference'],'recebedor_id'=>'123456','http'=>200,'request_id'=>'fake-request',
            'provedor_criado_em'=>'2026-01-01T00:00:00Z','provedor_atualizado_em'=>'2026-01-01T00:01:00Z'];
        if ($this->mode==='timeout_after') throw new RuntimeException('secret fixture');
        return $this->response($this->orders[$key]);
    }
    private function response(array $base): array
    {
        [$s,$d]=match($this->mode){'approved'=>['processed','accredited'],'rejected'=>['failed','rejected'],'cancelled'=>['canceled','canceled'],'expired'=>['expired','expired'],default=>['action_required','waiting_transfer']};
        return array_replace($base,['status'=>$s,'status_detail'=>$d,'pagamento_status'=>$s,'pagamento_status_detail'=>$d,'aprovado_em'=>(new DateTimeImmutable('now',new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s.uP')],$this->change);
    }
    public function consultarOrder(string $id): array
    {
        foreach($this->orders as $o) if($o['id']===$id)return $this->response($o);
        throw new RuntimeException('not found');
    }
}
$users=[];$plans=[];$tests=0;$stage='setup';$before=[];$ok=false;
function check(bool $v):void {global $tests;if(!$v)throw new RuntimeException('assertion');$tests++;}
function denied(Closure $fn):void {try{$fn();}catch(PagamentoException $e){check($e->getMessage()==='Nao foi possivel processar a solicitacao de pagamento.');return;}throw new RuntimeException('expected rejection');}
function snapshot(mysqli $db):array {
    $out=[];foreach(['usuarios','cadastro_planos','matricula','cobrancas','pagamentos','pagamento_eventos','pagamento','pedido'] as $t){
        $pk=$db->query("SHOW KEYS FROM `$t` WHERE Key_name='PRIMARY'")->fetch_assoc()['Column_name'];
        $rows=$db->query("SELECT * FROM `$t` ORDER BY `$pk`")->fetch_all(MYSQLI_ASSOC);
        $out[$t]=['count'=>count($rows),'hash'=>hash('sha256',serialize($rows))];
    }return $out;
}
try {
    require __DIR__.'/../config/conn.php';
    $r=new PixRepositorio($conn);$before=snapshot($conn);
    foreach(['ativo','inativo'] as $status){$r->sql('INSERT INTO cadastro_planos (nome,valor,duracao_dias,status) VALUES (?,\'17.39\',43,?)',['fixture-pix-'.bin2hex(random_bytes(8)),$status]);$plans[]=(int)$conn->insert_id;}
    $newUser=function()use($r,$conn,&$users):int{
        $tag=bin2hex(random_bytes(10));
        $r->sql("INSERT INTO usuarios (nome,senha,nacionalidade,data_nascimento,genero,cpf,endereco,cidade_estado,email,celular,status,email_verificado) VALUES ('Fixture Pix','!disabled','fixture','2000-01-01','outro',?,'fixture','fixture',?,'fixture','ativo',1)",['T'.substr($tag,0,13),$tag.'@example.invalid']);
        $users[]=(int)$conn->insert_id;return end($users);
    };
    $f=new FakePixPersistencia();$s=new MatriculaPixService($conn,$f);$u=$newUser();
    $stage='validation';denied(fn()=>$s->iniciar(0,$plans[0]));denied(fn()=>$s->iniciar($u,2147483647));denied(fn()=>$s->iniciar($u,$plans[1]));
    $stage='pending';$_POST=['valor'=>'0.01','recebedor_id'=>'evil','status'=>'approved'];
    $a=$s->iniciar($u,$plans[0]);$id=$a['id_cobranca'];
    $p=$r->um('SELECT * FROM pagamentos WHERE id_cobranca=?',[$id]);$m=$r->um('SELECT * FROM matricula WHERE id_matricula=?',[$a['id_matricula']]);
    check($p['valor']==='17.39');check($m['valor_contratado']==='17.39');check((int)$m['duracao_contratada_dias']===43);check($m['data_inicio']===null&&$m['data_fim']===null);check($m['status']==='pendente');check($a['status']==='pendente');
    check((bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$p['idempotencia_uuid']));check(str_replace('-','',$p['idempotencia_uuid'])===$p['chave_idempotencia']);check($f->keys[0]===$p['idempotencia_uuid']);
    check($s->iniciar($u,$plans[0])['id_cobranca']===$id&&$f->calls===1);
    $stage='unique';try{$r->sql("INSERT INTO cobrancas (id_usuario,origem,id_matricula,chave_negocio,chave_tentativa_ativa,valor_bruto,valor_cobrar) SELECT id_usuario,origem,id_matricula,?,chave_tentativa_ativa,valor_bruto,valor_cobrar FROM cobrancas WHERE id_cobranca=?",[bin2hex(random_bytes(16)),$id]);throw new RuntimeException('duplicate accepted');}catch(mysqli_sql_exception $e){check($e->getCode()===1062);}
    $stage='ownership';$other=$newUser();denied(fn()=>$s->consultarLocal($other,$id));denied(fn()=>$s->recuperar($other,$id));
    $stage='divergences';foreach(['valor_centavos'=>1,'moeda'=>'USD','external_reference'=>str_repeat('a',32),'recebedor_id'=>'999','id'=>'ORDWRONG','pagamento_id'=>'PAYWRONG'] as $k=>$v){$f->change=[$k=>$v];denied(fn()=>$s->recuperar($u,$id));check($r->um('SELECT status FROM matricula WHERE id_matricula=?',[$a['id_matricula']])['status']==='pendente');}$f->change=[];
    $stage='approval';$f->mode='approved';$s->recuperar($u,$id);$approved=$r->um('SELECT * FROM matricula WHERE id_matricula=?',[$a['id_matricula']]);check($approved['status']==='ativa');check((new DateTimeImmutable($approved['data_inicio']))->diff(new DateTimeImmutable($approved['data_fim']))->days===43);
    $count=$r->um('SELECT COUNT(*) n FROM pagamento_eventos WHERE id_pagamento=?',[$p['id_pagamento']])['n'];$s->recuperar($u,$id);check($approved===$r->um('SELECT * FROM matricula WHERE id_matricula=?',[$a['id_matricula']]));check($count===$r->um('SELECT COUNT(*) n FROM pagamento_eventos WHERE id_pagamento=?',[$p['id_pagamento']])['n']);
    check($r->um('SELECT chave_tentativa_ativa FROM cobrancas WHERE id_cobranca=?',[$id])['chave_tentativa_ativa']===null);
    $stage='terminals';foreach(['rejected','cancelled','expired'] as $mode){$f->mode=$mode;$uid=$newUser();$x=$s->iniciar($uid,$plans[0]);check($r->um('SELECT status FROM matricula WHERE id_matricula=?',[$x['id_matricula']])['status']==='pendente');$f->mode='pending';$y=$s->iniciar($uid,$plans[0]);check($x['id_cobranca']!==$y['id_cobranca']);check($r->um('SELECT COUNT(*) n FROM cobrancas WHERE id_usuario=?',[$uid])['n']==2);}
    $stage='timeouts';foreach(['timeout_before','timeout_after'] as $mode){$uid=$newUser();$f->mode=$mode;denied(fn()=>$s->iniciar($uid,$plans[0]));$c=$r->um('SELECT id_cobranca FROM cobrancas WHERE id_usuario=?',[$uid]);$cid=(int)$c['id_cobranca'];$calls=$f->calls;$s->iniciar($uid,$plans[0]);check($calls===$f->calls);$uuid=end($f->keys);$f->mode='pending';$s->recuperar($uid,$cid);check(end($f->keys)===$uuid);check($r->um('SELECT COUNT(*) n FROM pagamentos WHERE id_cobranca=?',[$cid])['n']==1);}
    $stage='expired_lease';$uid=$newUser();$f->mode='timeout_before';denied(fn()=>$s->iniciar($uid,$plans[0]));$c=$r->um('SELECT id_cobranca FROM cobrancas WHERE id_usuario=?',[$uid]);$cid=(int)$c['id_cobranca'];$uuid=end($f->keys);$r->sql("UPDATE pagamentos SET envio_estado='enviando',envio_bloqueio_ate=DATE_SUB(UTC_TIMESTAMP(6),INTERVAL 1 SECOND) WHERE id_cobranca=?",[$cid]);$f->mode='pending';$s->recuperar($uid,$cid);check(end($f->keys)===$uuid);
    $stage='overlap';$uid=$newUser();
    $db2=new mysqli(onefitEnv('DB_HOST'),onefitEnv('DB_USER'),onefitEnv('DB_PASSWORD'),onefitEnv('DB_NAME'));$db2->set_charset('utf8mb4');
    $s2=new MatriculaPixService($db2,$f);
    $f->during=function()use($s2,$uid,$plans,$f){$calls=$f->calls;$s2->iniciar($uid,$plans[0]);check($calls===$f->calls);};$s->iniciar($uid,$plans[0]);$db2->close();
    check(count(array_unique(array_column($r->sql("SELECT referencia_externa FROM pagamentos WHERE integracao='orders_pix'")->get_result()->fetch_all(MYSQLI_ASSOC),'referencia_externa'))) === (int)$r->um("SELECT COUNT(*) n FROM pagamentos WHERE integracao='orders_pix'")['n']);
    $ok=true;
} catch(Throwable $e) { echo json_encode(['tests'=>'failed','stage'=>$stage,'code'=>(int)$e->getCode(),'passed'=>$tests]),PHP_EOL; }
finally {
    if(isset($r)) {
        try {
            // A lost connection must not prevent fixture cleanup.
            $conn=new mysqli(onefitEnv('DB_HOST'),onefitEnv('DB_USER'),onefitEnv('DB_PASSWORD'),onefitEnv('DB_NAME'));
            $conn->set_charset('utf8mb4');$r=new PixRepositorio($conn);
            $r->transacao(function()use($r,$users,$plans){foreach($users as $uid){
                $r->sql('DELETE e FROM pagamento_eventos e JOIN pagamentos p ON p.id_pagamento=e.id_pagamento JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$uid]);
                $r->sql('DELETE p FROM pagamentos p JOIN cobrancas c ON c.id_cobranca=p.id_cobranca WHERE c.id_usuario=?',[$uid]);
                $r->sql('DELETE FROM cobrancas WHERE id_usuario=?',[$uid]);$r->sql('DELETE FROM matricula WHERE id_usuario=?',[$uid]);$r->sql('DELETE FROM usuarios WHERE id_usuario=?',[$uid]);
            }foreach($plans as $pid)$r->sql('DELETE FROM cadastro_planos WHERE id_plano=?',[$pid]);});
            $after=snapshot($conn);check($before===$after);
            echo json_encode(['tests_passed'=>$tests,'cleanup'=>'complete','original_rows_unchanged'=>$before===$after,'counts'=>array_map(fn($x)=>$x['count'],$after),'network_gateway'=>'fake_only']),PHP_EOL;
        }catch(Throwable){$ok=false;echo 'Cleanup/integrity verification failed; manual review required.',PHP_EOL;}
    }
}
exit($ok?0:1);
