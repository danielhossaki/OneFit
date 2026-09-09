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

$address = null;
foreach ($conn->query('SELECT id_endereco, id_usuario, cep FROM enderecos_entrega')->fetch_all(MYSQLI_ASSOC) as $candidate) {
    if (!bo_listar_opcoes_frete($conn, $candidate['cep'])) { $address = $candidate; break; }
}
if (!$address || !cart_pix_local()) throw new RuntimeException('Teste requer APP_URL local e endereco real sem frete');
$products=$conn->query("SELECT id_produto,nome,preco FROM produtos WHERE nome IN ('Strap para Musculação','Munhequeira OneFit')")->fetch_all(MYSQLI_ASSOC);
checkout_check(count($products)===2,'Produtos do cenario ausentes');
$cart=array_fill_keys(array_column($products,'id_produto'),1);
$session=['id_usuario'=>(int)$address['id_usuario'],'carrinho'=>$cart,'checkout_endereco_id'=>(int)$address['id_endereco'],'csrf_token'=>bin2hex(random_bytes(32)),'checkout_token'=>bin2hex(random_bytes(32))];
$post=['csrf_token'=>$session['csrf_token'],'checkout_token'=>$session['checkout_token'],'forma_pagamento'=>'pix','cashback_usado'=>0];
$before=$session;
$result=cart_processar_checkout($conn,$session,$post);
checkout_check(abs($result['total']-62.80)<0.001,'Total do exemplo divergente');
checkout_check($result['frete']===0.0,'Frete local incorreto');
checkout_check($session['carrinho']===[],'Carrinho nao foi limpo');
$order=$conn->query('SELECT * FROM pedido')->fetch_assoc();
checkout_check($order['forma_pagamento']==='pix' && $order['status']==='aguardando','Pix/status incorretos');
checkout_check((int)$order['id_usuario']===(int)$address['id_usuario'],'Comprador incorreto');
$items=$conn->query('SELECT * FROM pedido_item')->fetch_all(MYSQLI_ASSOC);
checkout_check(count($items)===2,'Itens ausentes');
foreach($items as $item) {
 checkout_check($item['id_pedido']===$order['id_pedido'],'Pedido diferente por item');
 checkout_check($item['id_transportadora']===null && $item['confirmado_recebimento_em']===null,'Entrega inventada');
}
checkout_fails(function() use($conn,&$session,$post){cart_processar_checkout($conn,$session,$post);});
$card=$before;
checkout_fails(function() use($conn,&$card,$post){cart_processar_checkout($conn,$card,array_replace($post,['forma_pagamento'=>'cartao']));});
checkout_check($card===$before,'Falha no cartao perdeu carrinho');
checkout_check((int)$conn->query('SELECT COUNT(*) FROM pedido')->fetch_row()[0]===1,'Pedido duplicado');
echo "OK: $checks verificacoes; Pix Strap + Munhequeira = 62,80; um pedido e dois itens. Somente tabelas temporarias.\n";
$conn->close();
