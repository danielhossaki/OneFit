<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;
require_once __DIR__ . '/OrdersPixService.php';
require_once __DIR__ . '/OrdersPixGatewayInterface.php';
require_once __DIR__ . '/PagadorPix.php';
require_once __DIR__ . '/DiagnosticoPagamento.php';
require_once __DIR__ . '/../../config/pagamentos.php';

final class OrdersPixGateway implements OrdersPixGatewayInterface
{
    private ?array $identidadeCache=null; // One gateway instance per request/credential.
    /** No caller-supplied email callback: only a backend-created immutable DTO. */
    public function __construct(private ?\MercadoPago\Net\MPHttpClient $http = null,
        private ?\Closure $clock = null) {}

    public function consultarIdentidade(): array
    {
        return $this->identidadeCache??=$this->executar('GET','/users/me');
    }

    public function criarOrder(#[\SensitiveParameter] array $preparacao): array
    {
        if (($preparacao['ambiente']??'')!=='testing'||($preparacao['moeda']??'')!=='BRL'
            || !preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D',$preparacao['idempotencia']??'')) throw new \InvalidArgumentException('Preparacao invalida.');
        $pagador=$preparacao['pagador']??null;
        if(!$pagador instanceof PagadorPix || $pagador->ambiente!=='testing')throw new PagamentoException();
        if($pagador->fixtureOficial() && (($preparacao['fixture_oficial']??false)!==true || $preparacao['valor_centavos']!==5000))throw new PagamentoException();
        $identity=$this->consultarIdentidade();
        $receiver=OrdersPixValidacao::vendedor($identity);
        if (isset($preparacao['recebedor_esperado_id']) && $preparacao['recebedor_esperado_id']!==$receiver) throw new PagamentoException();
        $value=OrdersPixService::decimal($preparacao['valor_centavos']);
        $expected=['type'=>'online','processing_mode'=>'automatic','external_reference'=>$preparacao['external_reference']??$preparacao['payload']['external_reference']??'',
            'total_amount'=>$value,'payer'=>$pagador->paraApi(),
            'transactions'=>['payments'=>[['amount'=>$value,'payment_method'=>['id'=>'pix','type'=>'bank_transfer']]]]];
        if (!preg_match('/^[a-f0-9]{32}$/D',$expected['external_reference'])) throw new \InvalidArgumentException('Payload invalido.');
        $result=$this->executar('POST','/v1/orders',$expected,$preparacao['idempotencia']);
        if ($result['recebedor_id']!==$receiver) throw new PagamentoException();
        return $result;
    }

    public function consultarOrder(string $id): array
    {
        if (!preg_match('/^ORD[A-Z0-9]{1,60}$/D',$id)) throw new \InvalidArgumentException('Order invalida.');
        return $this->executar('GET','/v1/orders/'.$id);
    }

    private function executar(string $method,string $path, #[\SensitiveParameter] ?array $payload=null,?string $uuid=null): array
    {
        try {
            \onefitInicializarMercadoPago('testing');
            require_once __DIR__.'/MercadoPagoHttpClient.php';
            $this->http ??= new MercadoPagoHttpClient();
            $headers=['Authorization: Bearer '.\MercadoPago\MercadoPagoConfig::getAccessToken(),'Content-Type: application/json','Accept: application/json'];
            if ($uuid!==null) $headers[]='X-Idempotency-Key: '.$uuid;
            $response=$this->http->send(new \MercadoPago\Net\MPRequest($path,$method,$payload===null?null:json_encode($payload,JSON_THROW_ON_ERROR),$headers));
            if ($path==='/users/me') {
                $raw=$response->getContent();$id=$raw['id']??null;
                if (is_int($id)) $id=(string)$id;
                if (!is_string($id)||!preg_match('/^[1-9][0-9]{0,99}$/D',$id)) throw new PagamentoException();
                $country=$raw['country_id']??null;$site=$raw['site_id']??null;
                if (!is_string($country)||!preg_match('/^[A-Z]{2}$/D',$country)||!is_string($site)||!preg_match('/^[A-Z]{3}$/D',$site)) throw new PagamentoException();
                return ['id'=>$id,'pais'=>$country,'site'=>$site,'test_user'=>is_array($raw['tags']??null)&&in_array('test_user',$raw['tags'],true)?true:null];
            }
            $normalized=OrdersPixService::normalizar($response->getContent(),$this->clock?($this->clock)():null);
            if ($payload!==null && ($normalized['external_reference']!==$payload['external_reference']||$normalized['valor']!==$payload['total_amount'])) throw new PagamentoException();
            return ['http'=>$response->getStatusCode(),'request_id'=>$this->requestId()]+$normalized;
        } catch (\MercadoPago\Exceptions\MPApiException $e) {
            throw new PagamentoException($e->getStatusCode(),$this->requestId(),DiagnosticoPagamento::extrair($e->getApiResponse()->getContent()));
        } catch (\Throwable) { throw new PagamentoException(null,$this->requestId()); }
    }

    private function requestId(): ?string
    {
        $id=$this->http!==null&&method_exists($this->http,'getRequestId')?$this->http->getRequestId():null;
        return is_string($id)&&preg_match('/^[a-zA-Z0-9_-]{1,100}$/D',$id)?$id:null;
    }
}
