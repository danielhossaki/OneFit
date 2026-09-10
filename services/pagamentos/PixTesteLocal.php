<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/../../config/env.php';
require_once __DIR__.'/PagamentoException.php';
final class PixTesteLocal
{
    public const PREFIXO='pix_local_v1:';
    public static function permitido():bool
    {
        // Socket addresses only. Host and forwarded headers never authorize access.
        $host=parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST);
        return \onefitEnv('MERCADO_PAGO_ENVIRONMENT','testing')==='testing'
            &&in_array($host,['localhost','127.0.0.1','[::1]'],true)
            &&!isset($_SERVER['HTTP_FORWARDED'])&&!isset($_SERVER['HTTP_X_FORWARDED_FOR'])
            &&in_array($_SERVER['REMOTE_ADDR']??'', ['127.0.0.1','::1'],true)
            &&in_array($_SERVER['SERVER_ADDR']??'', ['127.0.0.1','::1'],true);
    }
    public static function exigir():void{if(!self::permitido())throw new PagamentoException();}
    public static function marcada(array $charge):bool{return str_starts_with($charge['chave_negocio']??'',self::PREFIXO);}
    public static function validar(array $charge,array $payment):void
    {
        if(!self::marcada($charge))return;
        self::exigir();
        if(($charge['origem']??null)!=='matricula'||($charge['valor_cobrar']??null)!=='50.00'||($payment['valor']??null)!=='50.00'||($payment['ambiente']??null)!=='testing')throw new PagamentoException();
    }
}
