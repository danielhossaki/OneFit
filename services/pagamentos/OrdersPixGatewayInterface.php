<?php
declare(strict_types=1);
namespace OneFit\Pagamentos;

/** Backend only. Implementations must return verified, normalized provider data. */
interface OrdersPixGatewayInterface
{
    /** Only id, pais, site, and explicit test_user (bool|null). */
    public function consultarIdentidade(): array;
    public function criarOrder(array $preparacao): array;
    public function consultarOrder(string $id): array;
}
