<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__.'/PagamentoException.php';
require_once __DIR__.'/OrdersPixValidacao.php';
final class PixSeguranca
{
    /** Orders/Pix defaults to 24h (not Checkout Pro). Expiry never proves non-payment. */
    public static function vencimento(mixed $value,string $created):string
    {
        $start=new \DateTimeImmutable($created);
        try{
            if($value===null)$end=$start->modify('+24 hours');
            elseif(is_string($value)&&preg_match('/^P(?=\d|T\d)(?:\d{1,2}D)?(?:T(?:\d{1,3}H)?(?:\d{1,3}M)?(?:\d{1,3}S)?)?$/D',$value))$end=$start->add(new \DateInterval($value));
            else{$valid=OrdersPixValidacao::data($value,$start->modify('+30 days'));$end=new \DateTimeImmutable($valid);}
        }catch(\Throwable){throw new PagamentoException();}
        if($end<=$start||$end>$start->modify('+30 days'))throw new PagamentoException();
        return $end->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s.u');
    }
    public static function csrf(mixed $esperado,mixed $recebido):void
    {
        if(!is_string($esperado)||strlen($esperado)!==64||!is_string($recebido)||!hash_equals($esperado,$recebido))throw new PagamentoException();
    }
    public static function visual(array $d):array
    {
        $qr=$d['qr_code']??null;$b64=$d['qr_code_base64']??null;$url=$d['ticket_url']??null;
        if($qr!==null&&(!is_string($qr)||!preg_match('/^[\x20-\x7E]{1,4096}$/D',$qr)))throw new PagamentoException();
        if($b64==='')$b64=null;
        if($b64!==null){
            if(!is_string($b64)||strlen($b64)>1400000)throw new PagamentoException();
            $png=base64_decode($b64,true);$size=$png===false?false:@getimagesizefromstring($png);
            if(!$size||$size[2]!==IMAGETYPE_PNG||$size[0]>2048||$size[1]>2048)throw new PagamentoException();
        }
        if($url==='')$url=null;
        if($url!==null){$u=is_string($url)?parse_url($url):false;
            if(!$u||strlen($url)>2048||!filter_var($url,FILTER_VALIDATE_URL)||($u['scheme']??'')!=='https'||!in_array($u['host']??'',['www.mercadopago.com.br','mercadopago.com.br'],true)||isset($u['user'])||isset($u['pass'])||isset($u['port'])||isset($u['fragment']))throw new PagamentoException();
        }
        return ['qr_code'=>$qr,'qr_code_base64'=>$b64,'ticket_url'=>$url];
    }
    public static function preservarVisual(array $novo,array $salvo,string $status):array
    {
        if(!in_array($status,['criado','pendente'],true))return self::visual([]);
        $novo=self::visual($novo);
        return self::visual([
            'qr_code'=>$novo['qr_code']??$salvo['pix_qr']??null,
            'qr_code_base64'=>$novo['qr_code_base64']??$salvo['pix_qr_base64']??null,
            'ticket_url'=>$novo['ticket_url']??$salvo['pix_ticket_url']??null,
        ]);
    }
    /** Local replay policy: +/-300 seconds; accepts seconds and milliseconds.
     * Manifest follows Mercado Pago docs; body is never financial evidence.
     */
    public static function webhook(string $signature,string $request,string $resource,#[\SensitiveParameter] string $secret,int $now):string
    {
        if($secret===''||!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$request)||!preg_match('/^(?:ORD[A-Z0-9]{1,60}|PAY[A-Z0-9]{1,60}|[0-9]{1,18})$/iD',$resource))throw new PagamentoException();
        $parts=[];foreach(explode(',',$signature) as $p){$kv=explode('=',trim($p),2);if(count($kv)!==2||isset($parts[$kv[0]]))throw new PagamentoException();$parts[$kv[0]]=$kv[1];}
        $ts=$parts['ts']??'';$v=$parts['v1']??'';
        if(count($parts)!==2||!preg_match('/^(?:[0-9]{10}|[0-9]{13})$/D',$ts)||!preg_match('/^[a-f0-9]{64}$/D',$v))throw new PagamentoException();
        $seconds=strlen($ts)===13?intdiv((int)$ts,1000):(int)$ts;
        if(abs($now-$seconds)>300)throw new PagamentoException();
        $manifest='id:'.strtolower($resource).';request-id:'.$request.';ts:'.$ts.';';
        if(!hash_equals(hash_hmac('sha256',$manifest,$secret),$v))throw new PagamentoException();
        return hash('sha256','testing|'.$manifest);
    }
}
