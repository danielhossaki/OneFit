<?php
/**
 * funcionalidades/devolucoes.php
 * Aprovação, recusa e conclusão das devoluções solicitadas pelo comprador
 * (tabela `pedido_devolucao`), acessível só pelo admin. Concluir uma
 * devolução aprovada marca os itens/pedido como devolvidos e credita o
 * valor do pedido na carteira de cashback do comprador.
 */

$bo_papeis_permitidos = ['admin'];
require __DIR__ . '/_shared.php';
require __DIR__ . '/../includes/compras.php';
bo_check_csrf();

$idAdmin = (int) ($_SESSION['id_usuario'] ?? 0);
$acao = bo_str('acao');
$idDevolucao = (int) bo_str('id');
$secao = bo_secao_atual();

function devolucao_por_id(mysqli $conn, int $id): ?array
{
    $stmt = $conn->prepare('SELECT d.*, pe.id_usuario, pe.valor_total FROM pedido_devolucao d JOIN pedido pe ON pe.id_pedido = d.id_pedido WHERE d.id_devolucao = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

if ($acao === 'aprovar') {
    $devolucao = devolucao_por_id($conn, $idDevolucao);
    if (!$devolucao || $devolucao['status'] !== 'pendente') {
        bo_flash('error', 'Solicitação inválida.');
        bo_redirect($secao);
    }

    $stmt = $conn->prepare("UPDATE pedido_devolucao SET status = 'aceita', data_analise = NOW(), id_admin = ? WHERE id_devolucao = ? AND status = 'pendente'");
    $stmt->bind_param('ii', $idAdmin, $idDevolucao);
    $stmt->execute();
    $stmt->close();

    try {
        require_once __DIR__ . '/../../../config/notificacoes.php';
        criarNotificacao((int) $devolucao['id_usuario'], 'Devolução aprovada',
            'A devolução do pedido #' . $devolucao['id_pedido'] . ' foi aprovada e está em processo de conclusão.',
            'compra', '/AN25/OneFit/pages/dashboard/dashboard.php?section=compras');
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha ao notificar aprovação de devolução #' . $idDevolucao . '; código ' . $erroNotificacao->getCode());
    }

    bo_flash('success', 'Devolução aprovada.');
    bo_redirect($secao);
}

if ($acao === 'recusar') {
    $justificativa = bo_str('resposta_admin');
    $devolucao = devolucao_por_id($conn, $idDevolucao);
    if (!$devolucao || $devolucao['status'] !== 'pendente' || $justificativa === '') {
        bo_flash('error', 'Informe o motivo da recusa.');
        bo_redirect($secao);
    }

    $observacao = trim($devolucao['observacao'] . "\nRecusada pelo admin: " . $justificativa);
    $observacao = mb_substr($observacao, 0, 1000);
    $stmt = $conn->prepare("UPDATE pedido_devolucao SET status = 'recusada', data_analise = NOW(), id_admin = ?, observacao = ? WHERE id_devolucao = ? AND status = 'pendente'");
    $stmt->bind_param('isi', $idAdmin, $observacao, $idDevolucao);
    $stmt->execute();
    $stmt->close();

    try {
        require_once __DIR__ . '/../../../config/notificacoes.php';
        criarNotificacao((int) $devolucao['id_usuario'], 'Devolução recusada',
            'A devolução do pedido #' . $devolucao['id_pedido'] . ' foi recusada. Motivo: ' . $justificativa,
            'compra', '/AN25/OneFit/pages/dashboard/dashboard.php?section=compras');
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha ao notificar recusa de devolução #' . $idDevolucao . '; código ' . $erroNotificacao->getCode());
    }

    bo_flash('success', 'Devolução recusada.');
    bo_redirect($secao);
}

if ($acao === 'concluir') {
    $devolucao = devolucao_por_id($conn, $idDevolucao);
    if (!$devolucao || $devolucao['status'] !== 'aceita') {
        bo_flash('error', 'Solicitação inválida.');
        bo_redirect($secao);
    }

    $idPedido = (int) $devolucao['id_pedido'];
    $idUsuario = (int) $devolucao['id_usuario'];
    $valorCredito = (float) $devolucao['valor_total'];

    $conn->begin_transaction();
    try {
        $stmtItens = $conn->prepare("UPDATE pedido_item SET status_logistica = 'devolvido' WHERE id_pedido = ?");
        $stmtItens->bind_param('i', $idPedido);
        $stmtItens->execute();
        $stmtItens->close();

        // pedido.status é recalculado (mesma função usada em vendas.php e
        // meus-pedidos.php) em vez de gravado direto, para continuar sendo a
        // única fonte de verdade do status do pedido.
        bo_recalcular_status_pedido($conn, $idPedido);

        $descricaoCredito = 'Devolução do pedido #' . $idPedido;
        $stmtCashback = $conn->prepare("INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, 'credito', 'devolucao', ?, 'disponivel', NOW())");
        $stmtCashback->bind_param('ids', $idUsuario, $valorCredito, $descricaoCredito);
        $stmtCashback->execute();
        $idCashback = (int) $conn->insert_id;
        $stmtCashback->close();

        $stmtConcluir = $conn->prepare("UPDATE pedido_devolucao SET status = 'concluida', data_conclusao = NOW(), id_cashback = ? WHERE id_devolucao = ? AND status = 'aceita'");
        $stmtConcluir->bind_param('ii', $idCashback, $idDevolucao);
        $stmtConcluir->execute();
        $stmtConcluir->close();

        $conn->commit();
    } catch (Throwable $erroDevolucao) {
        $conn->rollback();
        error_log('ONE FIT: falha ao concluir devolução #' . $idDevolucao . '; código ' . $erroDevolucao->getCode());
        bo_flash('error', 'Não foi possível concluir a devolução.');
        bo_redirect($secao);
    }

    try {
        require_once __DIR__ . '/../../../config/notificacoes.php';
        criarNotificacao($idUsuario, 'Devolução concluída',
            'A devolução do pedido #' . $idPedido . ' foi concluída. O valor de ' . number_format($valorCredito, 2, ',', '.') . ' foi creditado no seu saldo.',
            'compra', '/AN25/OneFit/pages/dashboard/dashboard.php?section=compras');
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha ao notificar conclusão de devolução #' . $idDevolucao . '; código ' . $erroNotificacao->getCode());
    }

    bo_flash('success', 'Devolução concluída e valor creditado ao comprador.');
    bo_redirect($secao);
}

bo_flash('error', 'Ação inválida.');
bo_redirect($secao);
