<?php
declare(strict_types=1);

namespace OneFit\Pagamentos;

final class PagamentoException extends \RuntimeException
{
    public function __construct(public readonly ?int $httpStatus = null, public readonly ?string $requestId = null,
        private readonly array $diagnostico = [])
    {
        parent::__construct('Nao foi possivel processar a solicitacao de pagamento.');
    }

    /** Apenas diagnostico tecnico; nunca enviar este array a interface publica. */
    public function diagnosticoTecnico(): array
    {
        return ['http' => $this->httpStatus, 'x-request-id' => $this->requestId] + $this->diagnostico;
    }
}
