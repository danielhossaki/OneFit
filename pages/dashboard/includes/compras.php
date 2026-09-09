<?php
/**
 * Carrega os pedidos (marketplace) de um usuário, já separados em
 * "em andamento" e "histórico" — usado pelas telas "Minhas compras"
 * do profissional e do aluno. Cada pedido traz sua lista de itens
 * ('itens'), com o nome do vendedor de cada um ("ONE FIT" para produtos
 * legados sem vendedor, id_vendedor NULL) e o status de logística.
 *
 * @return array{0: array, 1: array}
 */
function bo_carregar_pedidos(mysqli $conn, int $idUsuario, string $busca = '', string $status = ''): array
{
    $statusSql = "CASE WHEN pe.status IN ('cancelado','devolvido','entregue') THEN pe.status
        WHEN NOT EXISTS (SELECT 1 FROM pedido_item x WHERE x.id_pedido = pe.id_pedido AND x.status_logistica <> 'entregue') THEN 'entregue'
        WHEN NOT EXISTS (SELECT 1 FROM pedido_item x WHERE x.id_pedido = pe.id_pedido AND x.status_logistica <> 'devolvido') THEN 'devolvido'
        ELSE 'aguardando' END";
    $busca = trim($busca);
    $like = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    // O identificador exibido deriva da chave real do pedido, sem coluna duplicada.
    $idBusca = preg_match('/^TRX-?0*([0-9]+)$/i', $busca, $match) ? (int) $match[1] : 0;
    $stmt = $conn->prepare("SELECT pe.id_pedido, pe.status, ($statusSql) AS status_compra, pe.data_pedido, pe.valor_total,
            pi.id_item, pi.quantidade, pi.status_logistica, pi.confirmado_recebimento,
            pi.confirmado_recebimento_em, pr.nome AS produto_nome,
            COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
        FROM pedido pe
        JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido
        JOIN produtos pr ON pr.id_produto = pi.id_produto
        LEFT JOIN usuarios v ON v.id_usuario = pi.id_vendedor
        WHERE pe.id_usuario = ?
          AND (? = '' OR ($statusSql) = ?)
          AND (? = '' OR LOWER(CONCAT('TRX-', LPAD(pe.id_pedido, GREATEST(4, CHAR_LENGTH(pe.id_pedido)), '0'))) LIKE LOWER(?) ESCAPE '!'
               OR pe.id_pedido = ? OR CAST(pe.id_pedido AS CHAR) LIKE ? ESCAPE '!'
               OR EXISTS (SELECT 1 FROM pedido_item busca_item
                   JOIN produtos busca_produto ON busca_produto.id_produto = busca_item.id_produto
                   WHERE busca_item.id_pedido = pe.id_pedido
                     AND LOWER(busca_produto.nome) LIKE LOWER(?) ESCAPE '!'))
        ORDER BY pe.data_pedido DESC, pe.id_pedido DESC, pi.id_item ASC");
    $stmt->bind_param('issssiss', $idUsuario, $status, $status, $busca, $like, $idBusca, $like, $like);
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLabel = ['aguardando' => 'Aguardando', 'pago' => 'Pago', 'processando' => 'Processando', 'entregue' => 'Entregue', 'cancelado' => 'Cancelado', 'devolvido' => 'Devolvido'];
    $statusLogisticaLabel = ['aguardando' => 'Aguardando', 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => 'Devolvido', 'extraviado' => 'Extraviado'];
    $finalizados = ['entregue', 'cancelado', 'devolvido'];

    $pedidos = [];
    while ($row = $res->fetch_assoc()) {
        $idPedido = (int) $row['id_pedido'];
        if (!isset($pedidos[$idPedido])) {
            $pedidos[$idPedido] = [
                'transacao' => 'TRX-' . str_pad((string) $idPedido, 4, '0', STR_PAD_LEFT),
                'valor' => (float) $row['valor_total'],
                'status' => $statusLabel[$row['status_compra']],
                'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
                'statusBanco' => $row['status_compra'],
                'itens' => [],
                // Mantido por compatibilidade com quem ainda espera um resumo em texto.
                'produto' => '',
            ];
        }
        $pedidos[$idPedido]['itens'][] = [
            'idItem' => (int) $row['id_item'],
            'produto' => $row['produto_nome'],
            'quantidade' => (int) $row['quantidade'],
            'vendedor' => $row['vendedor_nome'],
            'statusLogisticaBanco' => $row['status_logistica'],
            'statusLogistica' => $statusLogisticaLabel[$row['status_logistica']] ?? ucfirst($row['status_logistica']),
            'confirmadoRecebimento' => (bool) $row['confirmado_recebimento'],
            'confirmadoRecebimentoEm' => $row['confirmado_recebimento_em'] ? date('d/m/Y H:i', strtotime($row['confirmado_recebimento_em'])) : null,
        ];
    }
    $stmt->close();

    $emAndamento = [];
    $historico = [];
    foreach ($pedidos as $pedido) {
        $pedido['produto'] = implode(', ', array_column($pedido['itens'], 'produto'));
        if (in_array($pedido['statusBanco'], $finalizados, true)) {
            $historico[] = $pedido;
        } else {
            $emAndamento[] = $pedido;
        }
    }

    return [$emAndamento, $historico];
}

