<?php
declare(strict_types=1);
require __DIR__.'/../services/pagamentos/PixSeguranca.php';
use OneFit\Pagamentos\PixSeguranca;
$checks=0;
function checkVisual(bool $ok):void{global $checks;if(!$ok)throw new RuntimeException('Falha visual');$checks++;}
$old=['pix_qr'=>'fixture-only','pix_qr_base64'=>null,'pix_ticket_url'=>null];
checkVisual(PixSeguranca::preservarVisual([],$old,'pendente')['qr_code']==='fixture-only');
checkVisual(PixSeguranca::preservarVisual(['qr_code'=>'novo'],$old,'pendente')['qr_code']==='novo');
foreach(['aprovado','expirado','recusado','cancelado'] as $status)checkVisual(PixSeguranca::preservarVisual([],$old,$status)['qr_code']===null);
foreach([['qr_code'=>"bad\nvalue"],['qr_code_base64'=>'invalid']] as $bad){try{PixSeguranca::preservarVisual($bad,$old,'pendente');throw new LogicException();}catch(OneFit\Pagamentos\PagamentoException){$checks++;}}
checkVisual(PixSeguranca::preservarVisual([],[],'pendente')['qr_code']===null);
echo "OK: $checks verificacoes visuais; rede=0; banco=0.\n";
