<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit(1);}
require __DIR__.'/../config/matricula-wizard.php';
$n=0;function wizardCheck(bool $v):void{global $n;if(!$v)throw new RuntimeException('Wizard assertion');$n++;}
function wizardDenied(Closure $f):void{try{$f();}catch(RuntimeException){wizardCheck(true);return;}throw new RuntimeException('Expected rejection');}
$session=['id_usuario'=>123,'csrf_token'=>str_repeat('a',64),'carrinho'=>[9=>2]];$w=MatriculaWizard::novo($session);
wizardCheck($w['etapa']===1&&$w['validado']===0);$base=['fluxo'=>$w['id'],'csrf_token'=>$session['csrf_token']];
$plan=fn($id)=>$id===2;$city=fn($uf,$city)=>$uf==='SP'&&$city==='Fixture';
wizardDenied(fn()=>MatriculaWizard::avancar($session,$base+['etapa'=>'3','id_plano'=>'2'],$plan,$city));
wizardDenied(fn()=>MatriculaWizard::confirmar($session,$base+['id_plano'=>'2','termos'=>'on']));
$data=$base+['etapa'=>'1','nome'=>'Fixture','cpf'=>'52998224725','nascimento'=>'2000-01-01','genero'=>'outro','telefone'=>'11999999999','email'=>'fixture@example.invalid'];
wizardCheck(MatriculaWizard::avancar($session,$data,$plan,$city)===2);
wizardDenied(fn()=>MatriculaWizard::avancar($session,$base+['etapa'=>'3','id_plano'=>'2'],$plan,$city));
$address=$base+['etapa'=>'2','cep'=>'00000000','endereco'=>'Fixture','numero'=>'1','bairro'=>'Fixture','cidade'=>'Fixture','estado'=>'SP'];
wizardDenied(function()use(&$session,$address,$plan,$city){MatriculaWizard::avancar($session,array_replace($address,['cep'=>'invalid']),$plan,$city);});
wizardCheck($session['matricula_wizard']['dados']['nome']==='Fixture');wizardCheck($session['matricula_wizard']['dados']['cep']==='invalid');
wizardCheck(MatriculaWizard::avancar($session,$address,$plan,$city)===3);
wizardCheck(MatriculaWizard::avancar($session,$base+['etapa'=>'3','id_plano'=>'2'],$plan,$city)===4);
wizardDenied(fn()=>MatriculaWizard::confirmar($session,$base+['id_plano'=>'3','termos'=>'on']));
MatriculaWizard::confirmar($session,$base+['id_plano'=>'2','termos'=>'on']);wizardCheck($session['matricula_wizard']['confirmado']);
$session['matricula_wizard']['referencia']=str_repeat('b',32);$before=$session;
MatriculaWizard::confirmar($session,$base+['id_plano'=>'2','termos'=>'on']);wizardCheck($session===$before);
wizardDenied(fn()=>MatriculaWizard::avancar($session,$data,$plan,$city));wizardCheck($session['matricula_wizard']['referencia']===str_repeat('b',32));
$other=$session;$other['id_usuario']=124;wizardDenied(fn()=>MatriculaWizard::confirmar($other,$base+['id_plano'=>'2','termos'=>'on']));
$new=MatriculaWizard::novo($session);wizardCheck($new['id']!==$w['id']&&$new['etapa']===1);wizardCheck($session['id_usuario']===123&&$session['carrinho']===[9=>2]);
wizardDenied(fn()=>MatriculaWizard::confirmar($session,$base+['id_plano'=>'2','termos'=>'on']));
wizardCheck(!isset($new['dados']['password']));
$guest=['csrf_token'=>str_repeat('c',64)];$gw=MatriculaWizard::novo($guest);$gb=['fluxo'=>$gw['id'],'csrf_token'=>$guest['csrf_token']];
$gd=array_replace($data,$gb,['password'=>'fixture-password','confirmar_senha'=>'fixture-password']);
wizardDenied(fn()=>MatriculaWizard::avancar($guest,array_replace($gd,['confirmar_senha'=>'different']),$plan,$city));
wizardCheck(MatriculaWizard::avancar($guest,$gd,$plan,$city)===2);
wizardCheck(MatriculaWizard::avancar($guest,array_replace($address,$gb),$plan,$city)===3);
wizardCheck(MatriculaWizard::avancar($guest,$gb+['etapa'=>'3','id_plano'=>'2'],$plan,$city)===4);
$final=$gb+$guest['matricula_wizard']['dados']+['termos'=>'on'];
wizardDenied(fn()=>MatriculaWizard::confirmar($guest,array_replace($final,['cep'=>'invalid'])));
MatriculaWizard::confirmar($guest,$final);wizardCheck(!isset($guest['matricula_wizard']['dados']['password']));
echo "OK: $n verificacoes wizard; banco=0; rede=0.\n";
