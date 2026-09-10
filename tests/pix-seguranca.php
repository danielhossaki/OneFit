<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../services/pagamentos/PixSeguranca.php';
use OneFit\Pagamentos\PixSeguranca;
$n=0;function secureCheck($v){global $n;if(!$v)throw new RuntimeException('Security assertion');$n++;}function secureFail($f){try{$f();}catch(Throwable){secureCheck(true);return;}throw new RuntimeException('Expected rejection');}
$now=1700000000;$id='ORDFIXTURE';$request='fixture-request';$secret='fixture-only';$manifest='id:ordfixture;request-id:'.$request.';ts:'.$now.';';$signature='ts='.$now.',v1='.hash_hmac('sha256',$manifest,$secret);
secureCheck(strlen(PixSeguranca::webhook($signature,$request,$id,$secret,$now))===64);
secureCheck(PixSeguranca::webhook($signature,$request,$id,$secret,$now)===PixSeguranca::webhook($signature,$request,$id,$secret,$now));
foreach([['',$request,$id,$secret,$now],[$signature,$request,'ORDOTHER',$secret,$now],[$signature,'changed',$id,$secret,$now],[$signature,$request,$id,'',$now],[$signature,$request,$id,$secret,$now+301],[$signature,$request,$id,$secret,$now-301],[$signature.',ts='.$now,$request,$id,$secret,$now],[$signature,$request,'bad/id',$secret,$now]] as $args)secureFail(fn()=>PixSeguranca::webhook(...$args));
secureFail(fn()=>PixSeguranca::csrf('', ''));secureFail(fn()=>PixSeguranca::csrf(str_repeat('a',64),str_repeat('b',64)));PixSeguranca::csrf(str_repeat('a',64),str_repeat('a',64));secureCheck(true);
foreach(['http://mercadopago.com.br','https://evil.example','https://mercadopago.com.br@evil.example','https://mercadopago.com.br:444/path','javascript:alert(1)'] as $url)secureFail(fn()=>PixSeguranca::visual(['ticket_url'=>$url]));
foreach(['bad','PHN2Zz48L3N2Zz4='] as $qr)secureFail(fn()=>PixSeguranca::visual(['qr_code_base64'=>$qr]));
secureFail(fn()=>PixSeguranca::visual(['qr_code'=>"bad\ncode"]));secureCheck(PixSeguranca::visual([])['qr_code']===null);
$created='2026-09-01T12:00:00Z';
secureCheck(PixSeguranca::vencimento(null,$created)==='2026-09-02 12:00:00.000000');
secureCheck(PixSeguranca::vencimento('PT30M',$created)==='2026-09-01 12:30:00.000000');
secureCheck(PixSeguranca::vencimento('2026-09-02T09:00:00-03:00',$created)==='2026-09-02 12:00:00.000000');
foreach(['P31D','P0D','invalid','2026-09-31T12:00:00Z','2026-09-01T11:00:00Z'] as $v)secureFail(fn()=>PixSeguranca::vencimento($v,$created));
echo 'OK: '.$n.' verificacoes seguranca Pix; rede=0; banco=0.',PHP_EOL;
