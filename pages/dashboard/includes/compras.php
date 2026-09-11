<?php
require_once __DIR__ . '/../../../config/interface.php';
/**
 * Carrega os pedidos (marketplace) de um usuário, já separados em
 * "em andamento" e "histórico" — usado pelas telas "Minhas compras"
 * do profissional e do aluno. Cada pedido traz sua lista de itens
 * ('itens'), com o nome do vendedor de cada um ("ONE FIT" para produtos
 * legados sem vendedor, id_vendedor NULL) e o status de logística.
 *
 * `pedido.status` é a única fonte de verdade do status exibido: é o mesmo
 * valor que o admin/vendedor definem (indiretamente, via
 * bo_recalcular_status_pedido()) na tela Vendas Marketplace. Nada aqui
 * recalcula um status "visual" diferente do que está gravado no banco.
 *
 * @return array{0: array, 1: array}
 */
function bo_carregar_pedidos(mysqli $conn, int $idUsuario, string $busca = '', string $status = ''): array
{
    $finalizados = ['entregue', 'cancelado', 'devolvido'];
    $emAndamento = bo_buscar_pedidos_por_situacao($conn, $idUsuario, $busca, $status, $finalizados, false);
    $historico = bo_buscar_pedidos_por_situacao($conn, $idUsuario, $busca, $status, $finalizados, true);
    return [$emAndamento, $historico];
}

/**
 * Consulta específica para Acompanhamento (histórico=false, status ainda em
 * andamento) ou Histórico (histórico=true, status finalizado/cancelado/
 * devolvido) — não busca tudo para depois filtrar em PHP, evitando que uma
 * seção "vaze" pedidos da outra.
 */
function bo_buscar_pedidos_por_situacao(mysqli $conn, int $idUsuario, string $busca, string $status, array $finalizados, bool $historico): array
{
    $placeholders = implode(',', array_fill(0, count($finalizados), '?'));
    $comparador = $historico ? 'IN' : 'NOT IN';

    $busca = trim($busca);
    $like = '%' . strtr($busca, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
    // O identificador exibido deriva da chave real do pedido, sem coluna duplicada.
    $idBusca = preg_match('/^TRX-?0*([0-9]+)$/i', $busca, $match) ? (int) $match[1] : 0;

    $stmt = $conn->prepare("SELECT pe.id_pedido, pe.status, pe.data_pedido, pe.valor_total,
            pi.id_item, pi.quantidade, pi.status_logistica, pi.confirmado_recebimento,
            pi.confirmado_recebimento_em, pi.codigo_rastreio, pr.nome AS produto_nome,
            COALESCE(v.nome, 'ONE FIT') AS vendedor_nome
        FROM pedido pe
        JOIN pedido_item pi ON pi.id_pedido = pe.id_pedido
        JOIN produtos pr ON pr.id_produto = pi.id_produto
        LEFT JOIN usuarios v ON v.id_usuario = pi.id_vendedor
        WHERE pe.id_usuario = ?
          AND pe.status $comparador ($placeholders)
          AND (? = '' OR pe.status = ?)
          AND (? = '' OR LOWER(CONCAT('TRX-', LPAD(pe.id_pedido, GREATEST(4, CHAR_LENGTH(pe.id_pedido)), '0'))) LIKE LOWER(?) ESCAPE '!'
               OR pe.id_pedido = ? OR CAST(pe.id_pedido AS CHAR) LIKE ? ESCAPE '!'
               OR EXISTS (SELECT 1 FROM pedido_item busca_item
                   JOIN produtos busca_produto ON busca_produto.id_produto = busca_item.id_produto
                   WHERE busca_item.id_pedido = pe.id_pedido
                     AND LOWER(busca_produto.nome) LIKE LOWER(?) ESCAPE '!'))
        ORDER BY pe.data_pedido DESC, pe.id_pedido DESC, pi.id_item ASC");

    $tipos = 'i' . str_repeat('s', count($finalizados)) . 'ssssiss';
    $params = array_merge([$idUsuario], $finalizados, [$status, $status, $busca, $like, $idBusca, $like, $like]);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();

    $statusLabel = bo_status_pedido_labels();
    $statusLogisticaLabel = ['aguardando' => onefitTraduzir('Aguardando'), 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => onefitTraduzir('Devolvido'), 'extraviado' => onefitTraduzir('Extraviado')];

    $pedidos = [];
    while ($row = $res->fetch_assoc()) {
        $idPedido = (int) $row['id_pedido'];
        if (!isset($pedidos[$idPedido])) {
            $pedidos[$idPedido] = [
                'idPedido' => $idPedido,
                'transacao' => 'TRX-' . str_pad((string) $idPedido, 4, '0', STR_PAD_LEFT),
                'valor' => (float) $row['valor_total'],
                'status' => $statusLabel[$row['status']] ?? ucfirst($row['status']),
                'data' => date('d/m/Y H:i', strtotime($row['data_pedido'])),
                'statusBanco' => $row['status'],
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
            'codigoRastreio' => $row['codigo_rastreio'],
        ];
    }
    $stmt->close();

    foreach ($pedidos as &$pedido) {
        $pedido['produto'] = implode(', ', array_column($pedido['itens'], 'produto'));
    }
    unset($pedido);

    return array_values($pedidos);
}

/**
 * Labels de exibição de `pedido.status` — usados tanto em "Minhas compras"
 * quanto em qualquer outro lugar que precise mostrar o status de um pedido
 * ao usuário. Único lugar do código que traduz o valor gravado no banco
 * para o texto mostrado, para nunca haver dois textos diferentes para o
 * mesmo status.
 */
function bo_status_pedido_labels(): array
{
    return [
        'aguardando' => onefitTraduzir('Aguardando'),
        'preparando' => onefitTraduzir('Em preparação'),
        'despachado' => onefitTraduzir('Enviado'),
        'entregue' => onefitTraduzir('Finalizado'),
        'cancelado' => onefitTraduzir('Cancelado'),
        'devolvido' => onefitTraduzir('Devolvido'),
        'extraviado' => onefitTraduzir('Extraviado'),
        // Legados: nunca gravados pelo código atual, mantidos só para não
        // quebrar caso existam pedidos antigos com esses valores.
        'pago' => 'Pago',
        'processando' => 'Processando',
    ];
}

/**
 * Recalcula e grava `pedido.status` a partir do status_logistica atual de
 * todos os itens do pedido — chamada sempre que um item muda de status
 * (Vendas Marketplace, confirmação de recebimento, conclusão de devolução),
 * para que `pedido.status` seja sempre a mesma informação mostrada ao
 * admin (por item) e ao comprador (agregada por pedido). Não há um status
 * "do usuário" calculado à parte: esta função grava a única fonte de
 * verdade usada por todas as telas.
 */
function bo_recalcular_status_pedido(mysqli $conn, int $idPedido): string
{
    $stmtAtual = $conn->prepare('SELECT status FROM pedido WHERE id_pedido = ?');
    $stmtAtual->bind_param('i', $idPedido);
    $stmtAtual->execute();
    $statusAtual = (string) ($stmtAtual->get_result()->fetch_assoc()['status'] ?? 'aguardando');
    $stmtAtual->close();

    // 'cancelado' é definitivo: nada no sistema hoje cancela um pedido
    // automaticamente, então só preservamos esse valor se já estiver setado.
    if ($statusAtual === 'cancelado') {
        return 'cancelado';
    }

    $stmt = $conn->prepare('SELECT status_logistica, confirmado_recebimento FROM pedido_item WHERE id_pedido = ?');
    $stmt->bind_param('i', $idPedido);
    $stmt->execute();
    $res = $stmt->get_result();
    $contagem = [];
    $total = 0;
    $confirmados = 0;
    while ($row = $res->fetch_assoc()) {
        $status = $row['status_logistica'];
        $contagem[$status] = ($contagem[$status] ?? 0) + 1;
        $total++;
        if ($status === 'entregue' && (int) $row['confirmado_recebimento'] === 1) {
            $confirmados++;
        }
    }
    $stmt->close();

    // "Finalizado" só quando TODOS os itens estão entregues E o comprador já
    // confirmou o recebimento de cada um — a logística dizer "entregue" não
    // basta sozinha (ver bo_status_pedido_labels(): 'entregue' = Finalizado).
    if ($total > 0 && ($contagem['devolvido'] ?? 0) === $total) {
        $novoStatus = 'devolvido';
    } elseif ($total > 0 && $confirmados === $total) {
        $novoStatus = 'entregue';
    } elseif (!empty($contagem['extraviado'])) {
        $novoStatus = 'extraviado';
    } elseif (!empty($contagem['despachado']) || !empty($contagem['entregue'])) {
        // Item já entregue mas ainda não confirmado pelo comprador conta
        // como "Enviado" no status do pedido, até a confirmação.
        $novoStatus = 'despachado';
    } elseif (!empty($contagem['preparando'])) {
        $novoStatus = 'preparando';
    } else {
        $novoStatus = 'aguardando';
    }

    $stmtUpd = $conn->prepare('UPDATE pedido SET status = ? WHERE id_pedido = ?');
    $stmtUpd->bind_param('si', $novoStatus, $idPedido);
    $stmtUpd->execute();
    $stmtUpd->close();

    return $novoStatus;
}

/**
 * Status de pedido que permitem o comprador solicitar devolução: só depois
 * que o pedido já foi enviado (o comprador tem algo em mãos ou a caminho
 * para devolver). Um pedido "aguardando"/"preparando" não pode ser
 * devolvido — ainda não foi entregue.
 */
function bo_status_permite_devolucao(string $statusPedido): bool
{
    return in_array($statusPedido, ['despachado', 'entregue'], true);
}

/**
 * Devoluções (tabela `pedido_devolucao`) já solicitadas para os pedidos
 * informados, indexadas por id_pedido (no máximo uma por pedido — chave
 * única no banco). Usada para decidir se mostra "Solicitar devolução" ou o
 * status da solicitação já existente.
 */
function bo_carregar_devolucoes_por_pedido(mysqli $conn, array $idsPedido): array
{
    $idsPedido = array_values(array_unique(array_map('intval', $idsPedido)));
    if (!$idsPedido) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($idsPedido), '?'));
    $stmt = $conn->prepare("SELECT id_devolucao, id_pedido, motivo, observacao, status, data_solicitacao, data_analise, data_conclusao
        FROM pedido_devolucao WHERE id_pedido IN ($placeholders)");
    $stmt->bind_param(str_repeat('i', count($idsPedido)), ...$idsPedido);
    $stmt->execute();
    $res = $stmt->get_result();
    $porPedido = [];
    while ($row = $res->fetch_assoc()) {
        $porPedido[(int) $row['id_pedido']] = $row;
    }
    $stmt->close();
    return $porPedido;
}

/**
 * Label de exibição do status da solicitação de devolução — mesma ideia de
 * bo_status_pedido_labels(): único lugar que traduz o valor do banco.
 */
function bo_status_devolucao_labels(): array
{
    return [
        'pendente' => 'Devolução solicitada — aguardando análise',
        'aceita' => 'Devolução aprovada — aguardando conclusão',
        'recusada' => 'Devolução recusada',
        'concluida' => 'Devolução concluída',
    ];
}
