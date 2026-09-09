<?php
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/dashboard/includes/compras.php';
require __DIR__ . '/../pages/dashboard/includes/helpers.php';
require __DIR__ . '/../pages/dashboard/includes/admin-forms.php';
define('BASE_URL', '/AN25/OneFit/');
$_SESSION = ['csrf_token' => bin2hex(random_bytes(32))];
$users = $conn->query('SELECT DISTINCT id_usuario FROM pedido')->fetch_all(MYSQLI_ASSOC);
$checks = 0;
foreach ($users as $user) {
    foreach (['', 'aguardando', 'entregue', 'cancelado', 'devolvido'] as $status) {
        [$comprasPedidos, $comprasHistorico] = bo_carregar_pedidos($conn, (int) $user['id_usuario'], '', $status);
        ob_start();
        require __DIR__ . '/../pages/dashboard/components/compras-resultados.php';
        $html = ob_get_clean();
        $document = new DOMDocument();
        @$document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $total = (int) $xpath->query('//div[@class="bo-card-value"]')->item(0)->textContent;
        if ($total !== count($comprasPedidos) + count($comprasHistorico)) throw new RuntimeException('Total duplicado');
        $tracking = $xpath->query('(//table)[1]/tbody/tr/td[1]');
        $history = $xpath->query('(//table)[2]/tbody/tr/td[1]');
        $trackingIds = array_map(static fn($node) => trim($node->textContent), iterator_to_array($tracking));
        $historyIds = array_map(static fn($node) => trim($node->textContent), iterator_to_array($history));
        foreach ($comprasPedidos as $order) {
            if (!in_array($order['transacao'], $trackingIds, true) || !in_array($order['transacao'], $historyIds, true)) {
                throw new RuntimeException('Compra em andamento não aparece nas duas tabelas');
            }
        }
        foreach ($comprasHistorico as $order) {
            if (!in_array($order['transacao'], $historyIds, true)) throw new RuntimeException('Compra finalizada ausente');
        }
        $checks++;
    }
}
echo "OK: $checks renderizações com compras reais, histórico completo e total sem duplicação.\n";
