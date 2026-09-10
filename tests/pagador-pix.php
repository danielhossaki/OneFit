<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../services/pagamentos/PagadorPixResolver.php';
use OneFit\Pagamentos\{PixRepositorio,PagadorPixResolver,PagadorPix,PagamentoException};
$n=0;
function payerCheck(bool $b):void{global $n;if(!$b)throw new RuntimeException('Assertion '.($n+1));$n++;}
$repo=new class extends PixRepositorio {
    public ?array $row=['id_usuario'=>7,'email'=>'fixture@example.invalid','email_verificado'=>1,'status'=>'ativo'];
    public array $args=[];
    public function __construct(){}
    public function um(string $sql,array $args=[]):?array{$this->args=$args;return $this->row;}
};
$resolver=new PagadorPixResolver($repo);
$_POST=['email'=>'attacker@example.invalid','nome'=>'APRO','id_usuario'=>99,'cpf'=>'fixture'];
$_SESSION=['id_usuario'=>7,'email'=>'stale@example.invalid'];
$prod=$resolver->resolver(7,'production');payerCheck($prod->paraApi()===['email'=>'fixture@example.invalid']);payerCheck($repo->args===[7]);
$repo->row=['id_usuario'=>7,'email'=>'fixture@example.invalid','email_verificado'=>1,'status'=>'pendente_pagamento'];
$pending=$resolver->resolver(7,'testing');payerCheck(!$pending->fixtureOficial());
$repo->row=['id_usuario'=>7,'email'=>'fixture@example.invalid','email_verificado'=>1,'status'=>'ativo'];
$test=$resolver->resolver(7);payerCheck($test->paraApi()===['email'=>'test_user_br@testuser.com']);payerCheck(!$test->fixtureOficial());payerCheck(!isset($test->paraApi()['first_name']));
payerCheck(PagadorPix::testing(true)->paraApi()['first_name']==='APRO');
$original=$repo->row;
foreach([null,array_replace($original,['email'=>'']),array_replace($original,['email'=>'invalid']),array_replace($original,['email_verificado'=>0]),array_replace($original,['id_usuario'=>99]),array_replace($original,['status'=>'bloqueado'])] as $row){
    $repo->row=$row;foreach(['testing','production'] as $env){try{$resolver->resolver(7,$env);throw new RuntimeException('Not rejected');}catch(PagamentoException $e){payerCheck(!str_contains($e->getMessage(),'@')&&$e->getPrevious()===null);}}
}
$repo->row=$original;try{$resolver->resolver(0);throw new RuntimeException();}catch(PagamentoException){payerCheck(true);}
ob_start();var_dump($prod);$debug=ob_get_clean();payerCheck(!str_contains($debug,'fixture@'));payerCheck(!str_contains(json_encode($prod),'fixture@'));
try{$prod->ambiente='testing';throw new RuntimeException();}catch(Error){payerCheck(true);}
try{serialize($prod);throw new RuntimeException();}catch(PagamentoException){payerCheck(true);}
echo 'OK: '.$n.' verificacoes pagador; rede=0; banco=0.',PHP_EOL;
