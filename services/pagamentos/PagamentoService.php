<?php
declare(strict_types=1);

namespace OneFit\Pagamentos;

require_once __DIR__ . '/PagamentoGateway.php';
require_once __DIR__ . '/PagamentoException.php';

/** Sem conexao ao banco ou entrada HTTP. O futuro chamador deve consultar o
 * plano no banco e validar propriedade/status antes de fornecer o valor.
 * Nunca passar POST/GET, preco do navegador ou dados pessoais para este helper.
 */
final class PagamentoService
{
    public function __construct(private PagamentoGateway $gateway, private ?\Closure $relogio = null) {}

    public static function decimal(int $centavos): string
    {
        if ($centavos <= 0 || $centavos > 999999999999) {
            throw new \InvalidArgumentException('Valor fora do intervalo permitido.');
        }
        return intdiv($centavos, 100) . '.' . str_pad((string) ($centavos % 100), 2, '0', STR_PAD_LEFT);
    }

    public function prepararMatricula(
        int $valorServidorCentavos,
        string $titulo,
        string $descricao = '',
        array $urlsRetorno = [],
        ?string $urlWebhook = null,
        array $meiosConfirmados = []
    ): array {
        $valor = self::decimal($valorServidorCentavos);
        if (trim($titulo) === '' || strlen($titulo) > 150 || strlen($descricao) > 500) {
            throw new \InvalidArgumentException('Descricao do plano invalida.');
        }
        $agora = $this->relogio ? ($this->relogio)() : new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $agora = $agora->setTimezone(new \DateTimeZone('UTC'));
        $referencia = bin2hex(random_bytes(16));
        $expira = $agora->modify('+3 days')->format('Y-m-d\TH:i:s.vP');
        $excluidos = [];
        $tiposPix = ['bank_transfer', 'account_money'];
        foreach ($meiosConfirmados as $meio) {
            if (($meio['id'] ?? '') === 'pix') $tiposPix[] = $meio['payment_type_id'];
        }
        foreach ($meiosConfirmados as $meio) {
            $tipo = $meio['payment_type_id'] ?? '';
            if (!is_string($tipo) || !preg_match('/^[a-z_]{1,40}$/D', $tipo)) {
                throw new \InvalidArgumentException('Tipo de pagamento invalido.');
            }
            if (!in_array($tipo, $tiposPix, true)) $excluidos[$tipo] = ['id' => $tipo];
        }
        $payload = [
            'items' => [['title' => $titulo, 'description' => $descricao, 'quantity' => 1,
                'currency_id' => 'BRL', 'unit_price' => $valor]],
            'external_reference' => $referencia,
            'expires' => true,
            'expiration_date_from' => $agora->format('Y-m-d\TH:i:s.vP'),
            'expiration_date_to' => $expira,
            'date_of_expiration' => $expira,
        ];
        // Sem metodo padrao. Sem exclusoes configuradas, omitir payment_methods
        // inteiramente: nao enviar null, string vazia ou listas vazias.
        if ($excluidos !== []) {
            $payload['payment_methods'] = ['excluded_payment_types' => array_values($excluidos)];
        }
        // Saldo Mercado Pago (account_money) nao pode ser excluido no Checkout
        // Pro via preferencias. Priorizar Pix nao garante exclusividade absoluta.
        if ($urlsRetorno !== []) {
            if (count($urlsRetorno) !== 3 || array_diff(['success', 'failure', 'pending'], array_keys($urlsRetorno))) {
                throw new \InvalidArgumentException('URLs de retorno incompletas.');
            }
            foreach ($urlsRetorno as $url) self::validarUrl($url);
            $payload['back_urls'] = $urlsRetorno;
            $payload['auto_return'] = 'approved'; // Apenas navegacao; nunca aprova no banco.
        }
        if ($urlWebhook !== null) {
            self::validarUrl($urlWebhook);
            $payload['notification_url'] = $urlWebhook;
        }
        return ['origem' => 'matricula', 'valor_centavos' => $valorServidorCentavos, 'expira_em' => $expira,
            'referencia_externa' => $referencia, 'chave_idempotencia' => bin2hex(random_bytes(16)),
            'payload' => $payload];
    }

    private static function validarUrl(string $url): void
    {
        $partes = parse_url($url);
        if (!filter_var($url, FILTER_VALIDATE_URL) || ($partes['scheme'] ?? '') !== 'https'
            || isset($partes['user']) || isset($partes['pass']) || isset($partes['fragment'])) {
            throw new \InvalidArgumentException('URL HTTPS invalida.');
        }
    }

    /** Operacao explicita. Persistir a preparacao antes do envio, futuramente.
     * Reutilizar a mesma preparacao em retries; nao gerar outra chave cegamente.
     * A API de preferencias nao oferece garantia local de unicidade no banco.
     */
    public function criarPreferencia(array $preparacao): array
    {
        $valor = self::decimal($preparacao['valor_centavos']);
        if (($preparacao['origem'] ?? '') !== 'matricula'
            || ($preparacao['payload']['items'][0]['unit_price'] ?? null) !== $valor
            || ($preparacao['payload']['external_reference'] ?? null) !== ($preparacao['referencia_externa'] ?? null)) {
            throw new \InvalidArgumentException('Preparacao inconsistente.');
        }
        try {
            return $this->gateway->criarPreferencia($preparacao['payload'], $preparacao['chave_idempotencia']);
        } catch (PagamentoException $erro) {
            throw $erro;
        } catch (\Throwable) {
            throw new PagamentoException(); // Sem mensagem externa ou previous.
        }
    }
}
