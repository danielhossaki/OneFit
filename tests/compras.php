<?php
// Verificação somente de leitura sobre os pedidos existentes. Não cria compras.
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/dashboard/includes/compras.php';
function checkCompras(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function comprasIds(array $groups): array {
    $ids = array_column(array_merge(...$groups), 'transacao');
    sort($ids);
    return $ids;
}
$users = $conn->query('SELECT DISTINCT id_usuario FROM pedido')->fetch_all(MYSQLI_ASSOC);
$checks = 0;
foreach ($users as $user) {
    $id = (int) $user['id_usuario'];
    $all = bo_carregar_pedidos($conn, $id);
    $orders = array_merge(...$all);
    $stmt = $conn->prepare('SELECT COUNT(DISTINCT pe.id_pedido) n FROM pedido pe JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido JOIN produtos pr ON pr.id_produto = pi.id_produto WHERE pe.id_usuario = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    checkCompras(count($orders) === (int) $stmt->get_result()->fetch_assoc()['n'], 'Total divergente');
    $stmt->close();
    foreach (['', 'aguardando', 'entregue', 'cancelado', 'devolvido'] as $status) {
        foreach (['', '1', "' OR 1=1 --", '%', '_', 'produto-inexistente-7ad3'] as $term) {
            $expected = array_values(array_filter($orders, static function ($order) use ($status, $term) {
                if ($status !== '' && $order['statusBanco'] !== $status) return false;
                if ($term === '' || stripos($order['transacao'], $term) !== false) return true;
                foreach ($order['itens'] as $item) if (stripos($item['produto'], $term) !== false) return true;
                return false;
            }));
            checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, $term, $status)) === comprasIds([$expected]), 'Busca/status divergentes');
            $checks++;
        }
    }
    foreach ($orders as $order) {
        $trx = $order['transacao'];
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, $trx)) === [$trx], 'Busca por transação');
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, str_replace('TRX-', 'trx00', $trx))) === [$trx], 'Transação sem hífen');
        $name = $order['itens'][0]['produto'];
        checkCompras(comprasIds(bo_carregar_pedidos($conn, $id, strtolower($name))) === comprasIds(bo_carregar_pedidos($conn, $id, strtoupper($name))), 'Maiúsculas/minúsculas');
        $found = array_merge(...bo_carregar_pedidos($conn, $id, $name));
        $matching = array_values(array_filter($found, static fn($p) => $p['transacao'] === $trx));
        checkCompras(count($matching) === 1 && $matching[0]['itens'] === $order['itens'], 'Itens incompletos');
        checkCompras(bo_carregar_pedidos($conn, 0, $trx) === [[], []], 'Vazamento de compras');
        $checks += 5;
    }
}
echo "OK: $checks verificações, " . count($users) . " compradores reais; nenhuma escrita no banco.\n";
