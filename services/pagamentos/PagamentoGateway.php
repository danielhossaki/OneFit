<?php
declare(strict_types=1);

namespace OneFit\Pagamentos;

interface PagamentoGateway
{
    public function criarPreferencia(array $payload, string $idempotencia): array;
    public function consultarPreferencia(string $id): array;
    public function consultarPagamento(string $id): array;
}
