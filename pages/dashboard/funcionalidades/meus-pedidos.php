<?php
/**
 * funcionalidades/meus-pedidos.php
 * Ações do comprador sobre os próprios pedidos do marketplace. Hoje só tem
 * a confirmação de recebimento: o cliente avisa que o item chegou, o que
 * marca o item como "entregue" e registra que foi o próprio cliente quem
 * confirmou (diferente de um admin/vendedor apenas alterar o status).
 */

$bo_papeis_permitidos = ['admin', 'vendedor', 'aluno', 'profissional'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$idUsuarioLogado = (int) ($_SESSION['id_usuario'] ?? 0);
$acao = bo_str('acao');
$idItem = (int) bo_str('id_item');
$secao = bo_secao_atual();

if ($acao === 'confirmar-recebimento') {
    // Só o próprio comprador pode confirmar, e só quando o item já foi
    // despachado — nunca confia no id_item vindo do POST sozinho.
    $stmt = $conn->prepare(
        'SELECT pi.id_item, pi.id_pedido, pi.status_logistica
         FROM pedido_item pi
         INNER JOIN pedido pe ON pe.id_pedido = pi.id_pedido
         WHERE pi.id_item = ? AND pe.id_usuario = ?'
    );
    $stmt->bind_param('ii', $idItem, $idUsuarioLogado);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item || $item['status_logistica'] !== 'despachado') {
        bo_flash('error', 'Pedido inválido ou ainda não despachado.');
        bo_redirect($secao);
    }

    $stmtUpd = $conn->prepare(
        "UPDATE pedido_item SET status_logistica = 'entregue', confirmado_recebimento = 1, confirmado_recebimento_em = NOW() WHERE id_item = ?"
    );
    $stmtUpd->bind_param('i', $idItem);
    $stmtUpd->execute();
    $stmtUpd->close();

    // Se todos os itens do pedido já estiverem entregues, o pedido inteiro
    // passa para "entregue" (é isso que move a compra para o histórico).
    $idPedido = (int) $item['id_pedido'];
    $stmtPendentes = $conn->prepare("SELECT COUNT(*) AS pendentes FROM pedido_item WHERE id_pedido = ? AND status_logistica != 'entregue'");
    $stmtPendentes->bind_param('i', $idPedido);
    $stmtPendentes->execute();
    $pendentes = (int) ($stmtPendentes->get_result()->fetch_assoc()['pendentes'] ?? 1);
    $stmtPendentes->close();

    if ($pendentes === 0) {
        $stmtPedido = $conn->prepare("UPDATE pedido SET status = 'entregue' WHERE id_pedido = ?");
        $stmtPedido->bind_param('i', $idPedido);
        $stmtPedido->execute();
        $stmtPedido->close();
    }

    bo_flash('success', 'Recebimento confirmado. Obrigado!');
    bo_redirect($secao);
}

bo_flash('error', 'Ação inválida.');
bo_redirect($secao);
