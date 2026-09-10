<?php

// Helper interno: incluir este arquivo nao inicializa o SDK nem chama a API.
require_once __DIR__ . '/env.php';

final class OnefitPagamentoConfiguracaoException extends RuntimeException
{
    public function __construct(int $codigo)
    {
        // Nao anexar excecoes originais, valores ou contexto de credenciais.
        parent::__construct('O servico de pagamentos esta temporariamente indisponivel.', $codigo);
    }
}

/** Retorna somente metadados seguros, nunca credenciais. Nao inicializa o SDK. */
function onefitPagamentosConfiguracao(string $ambienteEsperado = 'testing'): array
{
    $ambiente = trim(onefitEnv('MERCADO_PAGO_ENVIRONMENT', 'testing') ?? 'testing');
    if (!in_array($ambiente, ['testing', 'production'], true)
        || !in_array($ambienteEsperado, ['testing', 'production'], true)) {
        throw new OnefitPagamentoConfiguracaoException(1001);
    }
    // O chamador informa o ambiente de forma interna, nunca pelo POST/GET.
    // O MVP usa o padrao testing. Production exige escolha explicita futura.
    if ($ambiente !== $ambienteEsperado) {
        throw new OnefitPagamentoConfiguracaoException(1002);
    }

    $token = onefitEnv('MERCADO_PAGO_ACCESS_TOKEN', '') ?? '';
    $publicKey = onefitEnv('MERCADO_PAGO_PUBLIC_KEY', '') ?? '';
    $webhookSecret = onefitEnv('MERCADO_PAGO_WEBHOOK_SECRET', '') ?? '';
    if (strlen(trim($token)) === 0 || strlen(trim($publicKey)) === 0) {
        throw new OnefitPagamentoConfiguracaoException(1003);
    }
    if (preg_match('/\s/', $token) || preg_match('/\s/', $publicKey)) {
        throw new OnefitPagamentoConfiguracaoException(1004);
    }

    // Prefixos nao comprovam a conta/ambiente: contas de teste podem usar APP_USR.
    // Rejeitar apenas contradicoes conhecidas. Confirmacao de conta e live_mode
    // depende da futura integracao com a API; nunca inferir sandbox de LOCAL.
    $tokenTeste = str_starts_with($token, 'TEST-');
    $chaveTeste = str_starts_with($publicKey, 'TEST-');
    if (($ambiente === 'production' && ($tokenTeste || $chaveTeste))
        || $tokenTeste !== $chaveTeste) {
        throw new OnefitPagamentoConfiguracaoException(1005);
    }

    return [
        'ambiente' => $ambiente,
        'access_token_presente' => true,
        'public_key_presente' => true,
        'webhook_secret_presente' => strlen(trim($webhookSecret)) > 0,
    ];
}

/** Inicializacao explicita, apenas local. Nenhum cliente ou pagamento e criado. */
function onefitInicializarMercadoPago(string $ambienteEsperado = 'testing'): void
{
    // Limpar estado anterior se a revalidacao falhar em um processo persistente.
    if (class_exists(\MercadoPago\MercadoPagoConfig::class, false)) {
        \MercadoPago\MercadoPagoConfig::setAccessToken('');
    }
    onefitPagamentosConfiguracao($ambienteEsperado);
    $autoload = dirname(__DIR__) . '/vendor/autoload.php';
    if (!is_readable($autoload)) {
        throw new OnefitPagamentoConfiguracaoException(1006);
    }
    try {
        require_once $autoload;
        if (!class_exists(\MercadoPago\MercadoPagoConfig::class)) {
            throw new OnefitPagamentoConfiguracaoException(1006);
        }
        // SERVER preserva verificacao TLS tambem em testing. LOCAL desativa TLS.
        \MercadoPago\MercadoPagoConfig::setRuntimeEnviroment(\MercadoPago\MercadoPagoConfig::SERVER);
        \MercadoPago\MercadoPagoConfig::setAccessToken(onefitEnv('MERCADO_PAGO_ACCESS_TOKEN', '') ?? '');
    } catch (Throwable) {
        throw new OnefitPagamentoConfiguracaoException(1006);
    }
}

// Nos futuros endpoints: capturar OnefitPagamentoConfiguracaoException e mostrar
// apenas getMessage(), sem stack trace, dump ou log das variaveis de ambiente.
