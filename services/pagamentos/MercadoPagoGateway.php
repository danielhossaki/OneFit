<?php
declare(strict_types=1);

namespace OneFit\Pagamentos;

require_once __DIR__ . '/PagamentoGateway.php';
require_once __DIR__ . '/PagamentoException.php';
require_once __DIR__ . '/DiagnosticoPagamento.php';
require_once __DIR__ . '/../../config/pagamentos.php';

final class MercadoPagoGateway implements PagamentoGateway
{
    // Politica explicita do MVP: suporte WCS-49381 / bug DXT40.
    // Nao deriva do token. Production exige outra politica e revisao futura.
    private const CHECKOUT_FIELD = 'init_point';
    // Sem I/O no construtor. Este MVP deliberadamente nao habilita production.
    public function __construct(private ?\MercadoPago\Net\MPHttpClient $http = null) {}

    private function executar(string $operacao, \Closure $acao): array
    {
        try {
            \onefitInicializarMercadoPago('testing');
            \MercadoPago\MercadoPagoConfig::$BASE_URL = 'https://api.mercadopago.com';
            \MercadoPago\MercadoPagoConfig::setConnectionTimeout(10000);
            \MercadoPago\MercadoPagoConfig::setMaxRetries(0);
            require_once __DIR__ . '/MercadoPagoHttpClient.php';
            $this->http ??= new MercadoPagoHttpClient();
            return $acao();
        } catch (\MercadoPago\Exceptions\MPApiException $erro) {
            $http = $erro->getStatusCode();
            $requestId = $this->http !== null && method_exists($this->http, 'getRequestId') ? $this->http->getRequestId() : null;
            if (!is_string($requestId) || !preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $requestId)) $requestId = null;
            error_log(json_encode(['contexto' => $operacao, 'http' => $http, 'request_id' => $requestId]));
            $safe = DiagnosticoPagamento::extrair($erro->getApiResponse()->getContent());
            throw new PagamentoException($http, $requestId, $safe);
        } catch (\Throwable) {
            error_log(json_encode(['contexto' => $operacao, 'http' => null, 'request_id' => null]));
            throw new PagamentoException();
        }
    }

    public function criarPreferencia(array $payload, string $idempotencia): array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $idempotencia)) throw new \InvalidArgumentException('Idempotencia invalida.');
        return $this->executar('preferencia.criar', function () use ($payload, $idempotencia) {
            // Conversao numerica somente na fronteira JSON exigida pelo SDK.
            $payload['items'][0]['unit_price'] = (float) $payload['items'][0]['unit_price'];
            $options = new \MercadoPago\Client\Common\RequestOptions();
            $options->setCustomHeaders(['x-idempotency-key' => $idempotencia]);
            $client = new \MercadoPago\Client\Preference\PreferenceClient($this->http);
            return $this->preferencia($client->create($payload, $options));
        });
    }

    public function consultarPreferencia(string $id): array
    {
        self::validarId($id);
        return $this->executar('preferencia.consultar', function () use ($id) {
            return $this->preferencia((new \MercadoPago\Client\Preference\PreferenceClient($this->http))->get($id));
        });
    }

    public function consultarPagamento(string $id): array
    {
        if (!preg_match('/^[0-9]{1,18}$/D', $id)) throw new \InvalidArgumentException('Identificador invalido.');
        return $this->executar('pagamento.consultar', function () use ($id) {
            $p = (new \MercadoPago\Client\Payment\PaymentClient($this->http))->get((int) $id);
            if (($p->live_mode ?? null) !== false) throw new PagamentoException();
            return ['id' => (string) $p->id, 'status' => $p->status ?? null,
                'status_detail' => $p->status_detail ?? null,
                'external_reference' => $p->external_reference ?? null,
                'currency_id' => $p->currency_id ?? null,
                'payment_method_id' => $p->payment_method_id ?? null,
                'live_mode' => false];
        });
    }

    private static function validarId(string $id): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,100}$/D', $id)) throw new \InvalidArgumentException('Identificador invalido.');
    }

    private function preferencia(object $p): array
    {
        // Conta vendedora test_user BR/MLB validada na etapa 7; comprador de teste.
        // O suporte orientou init_point neste fluxo. Nao usar fallback.
        $url = $p->{self::CHECKOUT_FIELD} ?? '';
        $partes = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || ($partes['scheme'] ?? '') !== 'https'
            || !in_array($partes['host'] ?? '', ['www.mercadopago.com.br', 'mercadopago.com.br'], true)
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['port'])
            || isset($partes['fragment'])) {
            throw new PagamentoException(); // Nunca fallback para sandbox_init_point.
        }
        return ['id' => $p->id, 'init_point' => $url, 'sandbox_init_point' => null,
            'checkout_url' => $url, 'external_reference' => $p->external_reference ?? null,
            'ambiente' => 'testing'];
    }
}
