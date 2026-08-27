<?php
/**
 * funcionalidades/pagamentos.php
 * CRUD da tela "Pagamentos" do admin (tabela `pagamento`, vinculada à
 * matrícula mais recente do usuário informado).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Pagamento inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('DELETE FROM pagamento WHERE id_pagamento = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Pagamento excluído.');
    bo_redirect($secao);
}

$dataPagamento = bo_str('data');
$tipo = bo_str('tipo');
$valor = bo_num('valor');
$usuarioId = (int) bo_str('usuarioId');
$forma = $tipo === 'PIX' ? 'pix' : 'cartao';

if (!$dataPagamento || $valor <= 0 || !$usuarioId) {
    bo_flash('error', 'Preencha data, valor e um ID de usuário válido.');
    bo_redirect($secao);
}

$stmtM = $conn->prepare('SELECT id_matricula FROM matricula WHERE id_usuario = ? ORDER BY data_matricula DESC, id_matricula DESC LIMIT 1');
$stmtM->bind_param('i', $usuarioId);
$stmtM->execute();
$mat = $stmtM->get_result()->fetch_assoc();
$stmtM->close();
if (!$mat) {
    bo_flash('error', 'Este usuário não possui matrícula para vincular o pagamento.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Pagamento inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE pagamento SET valor = ?, data_vencimento = ?, data_pagamento = ?, forma_pagamento = ? WHERE id_pagamento = ?');
    $stmt->bind_param('dsssi', $valor, $dataPagamento, $dataPagamento, $forma, $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Pagamento atualizado.');
    bo_redirect($secao);
}

$idMatricula = (int) $mat['id_matricula'];
$stmt = $conn->prepare('INSERT INTO pagamento (id_matricula, valor, data_vencimento, data_pagamento, forma_pagamento, status) VALUES (?, ?, ?, ?, ?, "aprovado")');
$stmt->bind_param('idsss', $idMatricula, $valor, $dataPagamento, $dataPagamento, $forma);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Pagamento registrado.');
bo_redirect($secao);
