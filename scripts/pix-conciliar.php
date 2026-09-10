<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'||!in_array('--processar',$argv,true)){http_response_code(404);exit(1);}
ini_set('display_errors','0');ini_set('log_errors','0');
try{require __DIR__.'/../config/conn.php';require __DIR__.'/../services/pagamentos/PixWebhook.php';
    $worker=new OneFit\Pagamentos\PixWebhook(new OneFit\Pagamentos\PixRepositorio($conn));$gateway=new OneFit\Pagamentos\OrdersPixGateway();
    $worker->processar($gateway);$worker->manutencao($gateway);
    echo "Fila processada; detalhes pessoais omitidos.\n";
}catch(Throwable){echo "Falha controlada na conciliacao.\n";exit(1);}
