<?php
declare(strict_types=1);
ini_set('display_errors','0');header('Cache-Control: no-store');
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit;}
if(strtolower(trim(explode(';',$_SERVER['CONTENT_TYPE']??'')[0]))!=='application/json'){http_response_code(415);exit;}
require __DIR__.'/../../config/env.php';require __DIR__.'/../../services/pagamentos/PixWebhook.php';
try{
    $secret=onefitEnv('MERCADO_PAGO_WEBHOOK_SECRET','');if(!$secret||onefitEnv('MERCADO_PAGO_ENVIRONMENT','testing')!=='testing'){http_response_code(503);exit;}
    $raw=file_get_contents('php://input',false,null,0,16385);if(strlen($raw)>16384)throw new RuntimeException();$b=json_decode($raw,true,16,JSON_THROW_ON_ERROR);unset($raw);
    $ids=[];foreach(explode('&',$_SERVER['QUERY_STRING']??'') as $pair){$kv=explode('=',$pair,2);if(urldecode($kv[0])==='data.id')$ids[]=urldecode($kv[1]??'');}
    if(count($ids)!==1||!is_string($b['data']['id']??null)&&!is_int($b['data']['id']??null)||strtolower((string)$b['data']['id'])!==strtolower($ids[0]))throw new RuntimeException();
    $key=OneFit\Pagamentos\PixSeguranca::webhook($_SERVER['HTTP_X_SIGNATURE']??'',$_SERVER['HTTP_X_REQUEST_ID']??'',$ids[0],$secret,time());
    $type=str_starts_with(strtoupper($ids[0]),'ORD')?'order':'payment';
    if(($b['type']??'')!==$type){http_response_code(400);exit;}
    require __DIR__.'/../../config/conn.php';(new OneFit\Pagamentos\PixWebhook(new OneFit\Pagamentos\PixRepositorio($conn)))->receber($key,strtoupper($ids[0]),$type);
    http_response_code(202);
}catch(Throwable){http_response_code(400);}
