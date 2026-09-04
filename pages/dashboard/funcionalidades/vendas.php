<?php
/**
 * funcionalidades/vendas.php
 * Logística das vendas do marketplace (tabela `pedido_item`): avançar o
 * status de um item (preparando -> despachado -> entregue), marcar
 * devolução/extravio e registrar o código de rastreio. Acessível por
 * admin (qualquer item) e vendedor (só os próprios).
 */

$bo_papeis_permitidos = ['admin', 'vendedor'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$souVendedor = ($_SESSION['tipo_usuario'] ?? '') === 'vendedor';
$idVendedorLogado = (int) ($_SESSION['id_usuario'] ?? 0);

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

const VENDAS_STATUS_VALIDOS = ['aguardando', 'preparando', 'despachado', 'entregue', 'devolvido', 'extraviado'];

/**
 * Um vendedor só pode agir sobre os próprios itens de venda — nunca confia
 * no id vindo do POST sozinho. Admin não tem essa restrição.
 */
function venda_pertence_ao_vendedor(mysqli $conn, int $id, int $idVendedor): bool
{
    $stmt = $conn->prepare('SELECT id_item FROM pedido_item WHERE id_item = ? AND id_vendedor = ?');
    $stmt->bind_param('ii', $id, $idVendedor);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

if ($acao === 'update-status') {
    $status = bo_str('status_logistica');
    $codigoRastreio = bo_str('codigo_rastreio');

    if (!$id || !in_array($status, VENDAS_STATUS_VALIDOS, true)) {
        bo_flash('error', 'Venda ou status inválido.');
        bo_redirect($secao);
    }
    if ($souVendedor && !venda_pertence_ao_vendedor($conn, $id, $idVendedorLogado)) {
        bo_flash('error', 'Venda inválida.');
        bo_redirect($secao);
    }

    $stmt = $conn->prepare('UPDATE pedido_item SET status_logistica = ?, codigo_rastreio = ? WHERE id_item = ?');
    $stmt->bind_param('ssi', $status, $codigoRastreio, $id);
    $stmt->execute();
    $stmt->close();

    bo_flash('success', 'Status da venda atualizado.');
    bo_redirect($secao);
}

bo_flash('error', 'Ação inválida.');
bo_redirect($secao);
