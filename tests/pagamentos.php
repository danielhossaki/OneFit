<?php
declare(strict_types=1);
// Executar somente via CLI: php tests/pagamentos.php. Sem banco ou rede.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../services/pagamentos/PagamentoService.php';
require_once __DIR__ . '/../services/pagamentos/MercadoPagoGateway.php';

use OneFit\Pagamentos\{PagamentoGateway, PagamentoService, MercadoPagoGateway, PagamentoException};

$checks = 0;
function checkPayment(bool $ok): void { global $checks; $checks++; if (!$ok) throw new RuntimeException('Teste falhou: ' . $checks); }
function rejectsPayment(Closure $fn, string $class): void {
    try { $fn(); } catch (Throwable $e) { checkPayment($e instanceof $class); return; }
    checkPayment(false);
}
$fake = new class implements PagamentoGateway {
    public int $calls = 0;
    public array $payload = [];
    public bool $fail = false;
    public function criarPreferencia(array $payload, string $idempotencia): array {
        $this->calls++; $this->payload = $payload;
        if ($this->fail) throw new RuntimeException('SEGREDO_FICTICIO_NAO_EXIBIR');
        return ['id' => 'fixture'];
    }
    public function consultarPreferencia(string $id): array { return []; }
    public function consultarPagamento(string $id): array { return []; }
};
$clock = new DateTimeImmutable('2026-09-05T12:00:00+00:00');
$service = new PagamentoService($fake, fn() => $clock);
$gateway = new MercadoPagoGateway();
checkPayment(!class_exists(MercadoPago\MercadoPagoConfig::class, false));
checkPayment($fake->calls === 0);
foreach ([0, -1, 1000000000000] as $v) rejectsPayment(fn() => $service->prepararMatricula($v, 'Plano'), InvalidArgumentException::class);
foreach ([1 => '0.01', 99 => '0.99', 9900 => '99.00', 17999 => '179.99'] as $v => $decimal) checkPayment(PagamentoService::decimal($v) === $decimal);
$a = $service->prepararMatricula(9900, 'Plano iniciante');
$b = $service->prepararMatricula(9900, 'Plano iniciante');
checkPayment((bool) preg_match('/^[a-f0-9]{32}$/D', $a['referencia_externa']));
checkPayment($a['referencia_externa'] !== $b['referencia_externa']);
checkPayment($a['chave_idempotencia'] !== $b['chave_idempotencia']);
checkPayment((new DateTimeImmutable($a['payload']['expiration_date_to']))->getTimestamp() - $clock->getTimestamp() === 259200);
checkPayment($a['expira_em'] === $a['payload']['date_of_expiration'] && $a['expira_em'] === $a['payload']['expiration_date_to']);
checkPayment(!array_key_exists('payment_methods', $a['payload']));
foreach (['default_payment_method_id', 'default_payment_type_id', 'excluded_payment_methods', 'excluded_payment_types'] as $field) {
    checkPayment(!array_key_exists($field, $a['payload']['payment_methods'] ?? []));
}
$confirmed = [['id'=>'pix','payment_type_id'=>'bank_transfer'],['id'=>'visa','payment_type_id'=>'credit_card'],['id'=>'account_money','payment_type_id'=>'account_money']];
$filtered = $service->prepararMatricula(100, 'Plano', '', [], null, $confirmed);
checkPayment($filtered['payload']['payment_methods']['excluded_payment_types'] === [['id'=>'credit_card']]);
$safe = OneFit\Pagamentos\DiagnosticoPagamento::extrair(['message'=>'invalid date_of_expiration', 'error'=>'bad_request', 'cause'=>[['code'=>'invalid_payment_type','description'=>'email pessoa@example.com APP_USR-segredo 12345678900','data'=>['email'=>'pessoa@example.com','field'=>'payment_methods']]],'payer'=>['name'=>'Pessoa']]);
checkPayment($safe['message'] === 'invalid date_of_expiration');
checkPayment(!str_contains(json_encode($safe), 'pessoa') && !str_contains(json_encode($safe), 'APP_USR') && !str_contains(json_encode($safe), '12345678900'));
checkPayment($safe['cause'][0]['data'] === ['field'=>'payment_methods']);
checkPayment(!isset($a['payload']['auto_return'], $a['payload']['payer']));
checkPayment(!isset($a['payload']['auto_return']) && !isset($a['payload']['notification_url']) && !isset($a['payload']['payer']));
$urls = ['success' => 'https://onefit.example/sucesso', 'failure' => 'https://onefit.example/falha', 'pending' => 'https://onefit.example/pendente'];
$withUrls = $service->prepararMatricula(9900, 'Plano', '', $urls, 'https://onefit.example/webhook');
checkPayment($withUrls['payload']['auto_return'] === 'approved' && $withUrls['payload']['back_urls'] === $urls);
checkPayment(!array_key_exists('default_payment_method_id', $filtered['payload']['payment_methods'])
    && !array_key_exists('default_payment_type_id', $filtered['payload']['payment_methods']));
checkPayment($a['payload']['items'][0]['quantity'] === 1 && $a['payload']['items'][0]['currency_id'] === 'BRL');
$service->criarPreferencia($a);
checkPayment($fake->payload['items'][0]['unit_price'] === '99.00');
$tampered = $a; $tampered['payload']['items'][0]['unit_price'] = '0.01';
rejectsPayment(fn() => $service->criarPreferencia($tampered), InvalidArgumentException::class);
$fake->fail = true;
try { $service->criarPreferencia($a); } catch (PagamentoException $e) {
    checkPayment(!str_contains($e->getMessage(), 'SEGREDO') && $e->getPrevious() === null);
}
rejectsPayment(fn() => $service->prepararMatricula(100, 'Plano', '', ['success' => 'http://localhost']), InvalidArgumentException::class);

// Transporte falso do SDK: nao usa curl e nunca chama o cliente HTTP real.
require_once __DIR__ . '/../vendor/autoload.php';
onefitEnv('MERCADO_PAGO_ENVIRONMENT'); // Carrega uma vez; substituicoes so no processo.
putenv('MERCADO_PAGO_ENVIRONMENT=testing');
putenv('MERCADO_PAGO_ACCESS_TOKEN=TEST-fixture');
putenv('MERCADO_PAGO_PUBLIC_KEY=TEST-fixture');
$transport = new class implements MercadoPago\Net\MPHttpClient {
    public bool $sandbox = true;
    public ?string $initPoint = 'https://www.mercadopago.com.br/checkout/v1/redirect';
    public bool $fail = false;
    public int $calls = 0;
    public function send(MercadoPago\Net\MPRequest $request): MercadoPago\Net\MPResponse {
        $this->calls++;
        if ($this->fail) throw new MercadoPago\Exceptions\MPApiException('SEGREDO_FICTICIO', new MercadoPago\Net\MPResponse(401, ['secret' => 'SEGREDO_FICTICIO']));
        return new MercadoPago\Net\MPResponse(200, ['id' => 'fixture',
            'init_point' => $this->initPoint,
            'sandbox_init_point' => $this->sandbox ? 'https://sandbox.mercadopago.com.br/checkout/v1/redirect' : null]);
    }
};
$adapter = new MercadoPagoGateway($transport);
checkPayment($transport->calls === 0);
$result = $adapter->criarPreferencia($a['payload'], $a['chave_idempotencia']);
checkPayment($result['checkout_url'] === $transport->initPoint && $result['init_point'] === $transport->initPoint);
checkPayment($result['ambiente'] === 'testing' && $result['sandbox_init_point'] === null);
checkPayment(MercadoPago\MercadoPagoConfig::getRuntimeEnviroment() === 'server');
$transport->sandbox = false;
$result = $adapter->consultarPreferencia('fixture');
checkPayment($result['checkout_url'] === $transport->initPoint);
// Campo sandbox presente nunca substitui init_point ausente ou invalido.
$transport->sandbox = true;
$transport->initPoint = null;
rejectsPayment(fn() => $adapter->consultarPreferencia('fixture'), PagamentoException::class);
foreach (['http://www.mercadopago.com.br/checkout', 'https://mercadopago.com.br.evil.example/checkout',
    'https://sandbox.mercadopago.com.br/checkout', 'https://usuario@www.mercadopago.com.br/checkout'] as $url) {
    $transport->initPoint = $url;
    rejectsPayment(fn() => $adapter->consultarPreferencia('fixture'), PagamentoException::class);
}
$transport->initPoint = 'https://www.mercadopago.com.br/checkout/v1/redirect';
// Mudar somente credenciais nao muda a politica; fixtures somente em memoria.
putenv('MERCADO_PAGO_ACCESS_TOKEN=APP_USR-fixture');
putenv('MERCADO_PAGO_PUBLIC_KEY=APP_USR-fixture');
checkPayment($adapter->consultarPreferencia('fixture')['checkout_url'] === $transport->initPoint);
$beforeCalls = $transport->calls;
putenv('MERCADO_PAGO_ENVIRONMENT=production');
rejectsPayment(fn() => $adapter->consultarPreferencia('fixture'), PagamentoException::class);
checkPayment($transport->calls === $beforeCalls);
putenv('MERCADO_PAGO_ENVIRONMENT=testing');
$transport->fail = true;
try { $adapter->consultarPreferencia('fixture'); } catch (PagamentoException $e) {
    checkPayment($e->httpStatus === 401 && $e->getPrevious() === null && !str_contains($e->getMessage(), 'SEGREDO'));
}
checkPayment(!isset($GLOBALS['conn']));
echo 'OK: ', $checks, ' verificacoes; rede real=0; banco=0.', PHP_EOL;
