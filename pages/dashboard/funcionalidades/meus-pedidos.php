<?php
/**
 * funcionalidades/meus-pedidos.php
 * Ações do comprador sobre os próprios pedidos do marketplace: confirmar
 * recebimento de um item, e solicitar devolução de um pedido já enviado.
 */

$bo_papeis_permitidos = ['admin', 'vendedor', 'aluno', 'profissional'];
require __DIR__ . '/_shared.php';
require __DIR__ . '/../includes/compras.php';
bo_check_csrf();

$idUsuarioLogado = (int) ($_SESSION['id_usuario'] ?? 0);
$acao = bo_str('acao');
$idItem = (int) bo_str('id_item');
$secao = bo_secao_atual();

if ($acao === 'confirmar-recebimento') {
    // Só o próprio comprador pode confirmar, e só depois que o admin/vendedor
    // já marcou o item como entregue em Vendas Marketplace — nunca confia no
    // id_item vindo do POST sozinho. Antes dessa confirmação o pedido
    // continua no Acompanhamento, mesmo que a logística já diga "entregue".
    $stmt = $conn->prepare(
        'SELECT pi.id_item, pi.id_pedido, pi.status_logistica, pi.confirmado_recebimento
         FROM pedido_item pi
         INNER JOIN pedido pe ON pe.id_pedido = pi.id_pedido
         WHERE pi.id_item = ? AND pe.id_usuario = ?'
    );
    $stmt->bind_param('ii', $idItem, $idUsuarioLogado);
    $stmt->execute();
    $item = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$item || $item['status_logistica'] !== 'entregue' || (int) $item['confirmado_recebimento'] === 1) {
        bo_flash('error', 'Pedido inválido ou ainda não entregue.');
        bo_redirect($secao);
    }

    $stmtUpd = $conn->prepare(
        'UPDATE pedido_item SET confirmado_recebimento = 1, confirmado_recebimento_em = NOW() WHERE id_item = ?'
    );
    $stmtUpd->bind_param('i', $idItem);
    $stmtUpd->execute();
    $stmtUpd->close();

    // pedido.status é a única fonte de verdade do status do pedido — sempre
    // recalculada a partir do status_logistica real dos itens, nunca gravada
    // "à mão" aqui (é o mesmo valor que o admin vê/define em Vendas Marketplace).
    bo_recalcular_status_pedido($conn, (int) $item['id_pedido']);

    bo_flash('success', 'Entrega confirmada. Obrigado!');
    bo_redirect($secao);
}

if ($acao === 'solicitar-devolucao') {
    $idPedido = (int) bo_str('id_pedido');
    $motivo = bo_str('motivo');

    if ($motivo === '') {
        bo_flash('error', 'Informe o motivo da devolução.');
        bo_redirect($secao);
    }

    // Nunca confia no id_pedido vindo do POST sozinho: confere que o pedido
    // pertence ao usuário logado e que o status atual permite devolução.
    $stmt = $conn->prepare('SELECT id_pedido, status FROM pedido WHERE id_pedido = ? AND id_usuario = ?');
    $stmt->bind_param('ii', $idPedido, $idUsuarioLogado);
    $stmt->execute();
    $pedido = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$pedido || !bo_status_permite_devolucao($pedido['status'])) {
        bo_flash('error', 'Este pedido não pode ser devolvido no momento.');
        bo_redirect($secao);
    }

    // uq_devolucao_pedido garante uma única solicitação por pedido; se já
    // existir (mesmo recusada), não deixa duplicar.
    $stmtExiste = $conn->prepare('SELECT id_devolucao FROM pedido_devolucao WHERE id_pedido = ?');
    $stmtExiste->bind_param('i', $idPedido);
    $stmtExiste->execute();
    $jaExiste = (bool) $stmtExiste->get_result()->fetch_assoc();
    $stmtExiste->close();

    if ($jaExiste) {
        bo_flash('error', 'Já existe uma solicitação de devolução para este pedido.');
        bo_redirect($secao);
    }

    $motivoCurto = mb_substr($motivo, 0, 80);
    $stmtIns = $conn->prepare("INSERT INTO pedido_devolucao (id_pedido, motivo, observacao, status, data_solicitacao) VALUES (?, ?, ?, 'pendente', NOW())");
    $stmtIns->bind_param('iss', $idPedido, $motivoCurto, $motivo);
    $stmtIns->execute();
    $stmtIns->close();

    try {
        require_once __DIR__ . '/../../../config/notificacoes.php';
        criarNotificacao($idUsuarioLogado, 'Devolução solicitada',
            'Sua solicitação de devolução do pedido #' . $idPedido . ' foi registrada e está em análise.',
            'compra', '/AN25/OneFit/pages/dashboard/dashboard.php?section=compras');
    } catch (Throwable $erroNotificacao) {
        error_log('ONE FIT: falha ao notificar solicitação de devolução do pedido #' . $idPedido . '; código ' . $erroNotificacao->getCode());
    }

    bo_flash('success', 'Devolução solicitada. Você será avisado quando for analisada.');
    bo_redirect($secao);
}

bo_flash('error', 'Ação inválida.');
bo_redirect($secao);
