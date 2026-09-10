<?php
declare(strict_types=1);

namespace OneFit\Pagamentos;
require_once __DIR__ . '/DiagnosticoPagamento.php';

/** Transporte do SDK: timeout total, TLS e request ID, ausentes juntos no
 * transporte padrao 3.16.0. Carregado apenas durante uma operacao explicita.
 */
final class MercadoPagoHttpClient implements \MercadoPago\Net\MPHttpClient
{
    private ?string $requestId = null;
    public function getRequestId(): ?string { return $this->requestId; }

    public function send(#[\SensitiveParameter] \MercadoPago\Net\MPRequest $request): \MercadoPago\Net\MPResponse
    {
        $this->requestId = null;
        $uri = $request->getUri();
        if (!preg_match('~^/(users/me|checkout/preferences(?:/[a-zA-Z0-9_-]+)?|v1/payments/[0-9]+|v1/orders(?:/[a-zA-Z0-9]+)?)$~D', $uri)
            || !in_array($request->getMethod(), ['GET', 'POST'], true)
            || ($request->getMethod() === 'POST' && !in_array($uri, ['/checkout/preferences', '/v1/orders'], true))) {
            throw new PagamentoException();
        }
        $curl = curl_init('https://api.mercadopago.com' . $uri);
        try {
            curl_setopt_array($curl, [
                CURLOPT_CUSTOMREQUEST => $request->getMethod(),
                CURLOPT_HTTPHEADER => $request->getHeaders(),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT_MS => 10000,
                CURLOPT_TIMEOUT_MS => 25000,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_VERBOSE => false,
                CURLOPT_HEADERFUNCTION => function ($handle, $line) {
                    if (preg_match('/^x-request-id:\s*([a-zA-Z0-9_-]{1,100})\s*$/i', trim($line), $m)) {
                        $this->requestId = $m[1];
                    }
                    return strlen($line);
                },
            ]);
            if ($request->getMethod() === 'POST') curl_setopt($curl, CURLOPT_POSTFIELDS, $request->getPayload());
            $body = curl_exec($curl);
            $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($body === false) throw new PagamentoException(null, $this->requestId);
            // Preservar somente campos filtrados, nunca o corpo bruto.
            if ($status < 200 || $status >= 300) {
                $raw = json_decode($body, true);
                $safe = DiagnosticoPagamento::extrair(is_array($raw) ? $raw : []);
                unset($body, $raw);
                throw new \MercadoPago\Exceptions\MPApiException('Falha na API.', new \MercadoPago\Net\MPResponse($status, $safe));
            }
            $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($data)) throw new PagamentoException();
            return new \MercadoPago\Net\MPResponse($status, $data);
        } finally {
            curl_close($curl);
        }
    }
}
