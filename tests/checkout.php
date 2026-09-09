<?php
// SQL real em tabelas TEMPORARY privadas desta conexão. Nenhuma compra,
// alteração de estoque ou lançamento de cashback persiste na base de uso.
require __DIR__ . '/../config/conn.php';
require __DIR__ . '/../pages/carrinho/checkout.php';
$checks = 0;
function checkout_check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function checkout_fails(callable $action): void {
    $failed = false;
    try { $action(); } catch (Throwable $error) { $failed = true; }
    checkout_check($failed, 'Operação inválida foi aceita');
}
// As cópias usam somente registros já existentes, sem inventar usuários/produtos.
foreach (['pedido', 'pedido_item', 'produtos', 'cashback'] as $table) {
    $rows = in_array($table, ['produtos', 'cashback'], true)
        ? $conn->query('SELECT * FROM ' . $table)->fetch_all(MYSQLI_ASSOC) : [];
    $conn->query('CREATE TEMPORARY TABLE checkout_schema_' . $table . ' LIKE ' . $table);
    $conn->query('CREATE TEMPORARY TABLE ' . $table . ' LIKE checkout_schema_' . $table);
    foreach ($rows as $row) {
        $columns = implode(',', array_map(static fn($c) => '`' . $c . '`', array_keys($row)));
        $statement = $conn->prepare('INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')');
        $values = array_values($row);
        $statement->bind_param(str_repeat('s', count($values)), ...$values);
        $statement->execute();
        $statement->close();
    }
}
$address = $conn->query("SELECT e.id_endereco, e.id_usuario, e.cep FROM enderecos_entrega e
    JOIN usuarios u ON u.id_usuario = e.id_usuario
    JOIN faixas_cep_frete f ON REPLACE(e.cep, '-', '') BETWEEN f.cep_inicial AND f.cep_final
    JOIN transportadoras t ON t.id_transportadora = f.id_transportadora AND t.status = 'ativo' LIMIT 1")->fetch_assoc();
$products = $conn->query("SELECT * FROM produtos WHERE status = 'ativo' AND estoque >= 4 AND cashback_valor > 0 ORDER BY id_produto LIMIT 2")->fetch_all(MYSQLI_ASSOC);
if (!$address || count($products) < 2) throw new RuntimeException('Faltam registros existentes adequados ao teste');
$cart = [(int) $products[0]['id_produto'] => 1, (int) $products[1]['id_produto'] => 2];
$session = ['id_usuario' => (int) $address['id_usuario'], 'carrinho' => $cart,
    'checkout_endereco_id' => (int) $address['id_endereco'], 'csrf_token' => bin2hex(random_bytes(32)),
    'checkout_token' => bin2hex(random_bytes(32))];
$initialSession = $session;
$post = ['csrf_token' => $session['csrf_token'], 'checkout_token' => $session['checkout_token'], 'forma_pagamento' => 'pix', 'cashback_usado' => 0,
    'preco' => 0.01, 'valor_total' => 0.01, 'id_usuario' => 0];
$result = cart_processar_checkout($conn, $session, $post);
checkout_check($session['carrinho'] === [] && !isset($session['checkout_token']), 'Carrinho/token não consumidos após commit');
checkout_check($session['ultimo_pedido'] === $result, 'Recibo ausente');
$orders = $conn->query('SELECT * FROM pedido')->fetch_all(MYSQLI_ASSOC);
checkout_check(count($orders) === 1, 'Não criou um único pedido');
checkout_check($orders[0]['status'] === 'aguardando' && (int) $orders[0]['id_usuario'] === $initialSession['id_usuario'], 'Status/usuário inválido');
checkout_check($result['transacao'] === 'TRX-' . str_pad($orders[0]['id_pedido'], 4, '0', STR_PAD_LEFT), 'ID de transação divergente');
$items = $conn->query('SELECT * FROM pedido_item ORDER BY id_produto')->fetch_all(MYSQLI_ASSOC);
checkout_check(count($items) === 2 && count(array_unique(array_column($items, 'id_pedido'))) === 1, 'Itens separados em pedidos diferentes');
$expected = 0;
$sellers = [];
foreach ($products as $index => $product) {
    $qty = $cart[$product['id_produto']];
    $subtotal = round(round((float) $product['preco'] * (1 - (float) $product['desconto'] / 100), 2) * $qty, 2);
    $expected += $subtotal;
    $sellers[(int) ($product['id_vendedor'] ?? 0)] = ['nome' => ''];
    checkout_check((float) $items[$index]['subtotal'] === $subtotal && (int) $items[$index]['quantidade'] === $qty, 'Preço/quantidade incorretos');
    $stock = $conn->query('SELECT estoque FROM produtos WHERE id_produto = ' . (int) $product['id_produto'])->fetch_row()[0];
    checkout_check((int) $stock === (int) $product['estoque'] - $qty, 'Estoque incorreto');
}
$expected += cart_calcular_fretes($conn, $sellers, $address['cep'])['total'];
checkout_check(abs($result['total'] - $expected) < 0.001, 'Total calculado no servidor diverge');
checkout_fails(function () use ($conn, &$session, $post) { cart_processar_checkout($conn, $session, $post); });
checkout_check((int) $conn->query('SELECT COUNT(*) FROM pedido')->fetch_row()[0] === 1, 'Reenvio duplicou pedido');
$renewedSession = array_replace($initialSession, ['checkout_token' => bin2hex(random_bytes(32))]);
checkout_fails(function () use ($conn, &$renewedSession, $post) { cart_processar_checkout($conn, $renewedSession, $post); });
checkout_check($renewedSession['carrinho'] === $cart, 'Reenvio antigo consumiu um novo carrinho');
$anonymous = array_replace($initialSession, ['id_usuario' => 0]);
checkout_fails(function () use ($conn, &$anonymous, $post) { cart_processar_checkout($conn, $anonymous, $post); });
foreach (['csrf_token' => 'invalido', 'checkout_token' => 'invalido'] as $key => $value) {
    $invalid = $initialSession;
    checkout_fails(function () use ($conn, &$invalid, $post, $key, $value) { cart_processar_checkout($conn, $invalid, array_replace($post, [$key => $value])); });
    checkout_check($invalid === $initialSession, 'Falha alterou a sessão');
}
foreach ([[], [0 => 1], [$products[0]['id_produto'] => 999999], [$products[0]['id_produto'] => 1.5], $cart + [0 => 1]] as $invalidCart) {
    $invalid = array_replace($initialSession, ['carrinho' => $invalidCart]);
    $before = $invalid;
    checkout_fails(function () use ($conn, &$invalid, $post) { cart_processar_checkout($conn, $invalid, $post); });
    checkout_check($invalid === $before, 'Erro perdeu o carrinho');
}
$invalid = array_replace($initialSession, ['checkout_endereco_id' => 0]);
checkout_fails(function () use ($conn, &$invalid, $post) { cart_processar_checkout($conn, $invalid, $post); });
// Falha real de SQL depois de inserir pedido, itens e baixar estoque.
$stockBefore = $conn->query('SELECT id_produto, estoque FROM produtos ORDER BY id_produto')->fetch_all(MYSQLI_ASSOC);
$cashbackBefore = (int) $conn->query('SELECT COUNT(*) FROM cashback')->fetch_row()[0];
$conn->query('ALTER TABLE cashback DROP COLUMN descricao'); // Apenas a tabela TEMPORARY.
$failedSession = $initialSession;
checkout_fails(function () use ($conn, &$failedSession, $post) { cart_processar_checkout($conn, $failedSession, $post); });
checkout_check($failedSession === $initialSession, 'Falha SQL consumiu carrinho/token');
checkout_check((int) $conn->query('SELECT COUNT(*) FROM pedido')->fetch_row()[0] === 1, 'Rollback deixou pedido parcial');
checkout_check((int) $conn->query('SELECT COUNT(*) FROM pedido_item')->fetch_row()[0] === 2, 'Rollback deixou itens parciais');
checkout_check($stockBefore === $conn->query('SELECT id_produto, estoque FROM produtos ORDER BY id_produto')->fetch_all(MYSQLI_ASSOC), 'Rollback não restaurou estoque');
checkout_check($cashbackBefore === (int) $conn->query('SELECT COUNT(*) FROM cashback')->fetch_row()[0], 'Rollback alterou cashback');
echo "OK: $checks verificações de checkout com SQL real; tabelas temporárias descartadas ao fechar a conexão.\n";
$conn->close();
