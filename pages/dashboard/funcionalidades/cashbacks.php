<?php
/**
 * funcionalidades/cashbacks.php
 * CRUD da tela "Cashbacks" do admin (tabela `cashback`): lançamento
 * manual, distribuição em massa e exclusão de transação.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Transação inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('DELETE FROM cashback WHERE id_cashback = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Transação excluída.');
    bo_redirect($secao);
}

if ($acao === 'massa') {
    $dataLanc = bo_str('data');
    $valor = bo_num('valor');
    $alvo = bo_str('alvo');
    if (!$dataLanc || $valor <= 0) {
        bo_flash('error', 'Preencha data e valor.');
        bo_redirect($secao);
    }

    $sqlUsuarios = $alvo === 'Ativos' ? "SELECT id_usuario FROM usuarios WHERE status = 'ativo'" : 'SELECT id_usuario FROM usuarios';
    $res = $conn->query($sqlUsuarios);
    $descricaoMassa = 'Distribuição em massa (admin)';
    $stmt = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, "credito", "uso", ?, "disponivel", ?)');
    $count = 0;
    while ($row = $res->fetch_assoc()) {
        $uid = (int) $row['id_usuario'];
        $stmt->bind_param('idss', $uid, $valor, $descricaoMassa, $dataLanc);
        $stmt->execute();
        $count++;
    }
    $stmt->close();
    bo_flash('success', "Cashback distribuído para {$count} usuário(s).");
    bo_redirect($secao);
}

// lançamento manual (create)
$dataLanc = bo_str('data');
$tipo = bo_str('tipo');
$valor = bo_num('valor');
$usuarioId = (int) bo_str('usuarioId');

if (!$dataLanc || !in_array($tipo, ['credito', 'debito'], true) || $valor <= 0 || !$usuarioId) {
    bo_flash('error', 'Preencha data, tipo, valor e um ID de usuário válido.');
    bo_redirect($secao);
}

$check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?');
$check->bind_param('i', $usuarioId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) {
    $check->close();
    bo_flash('error', 'Usuário não encontrado.');
    bo_redirect($secao);
}
$check->close();

$statusCb = $tipo === 'debito' ? 'utilizado' : 'disponivel';
$descricao = 'Lançamento manual pelo admin';
$stmt = $conn->prepare('INSERT INTO cashback (id_usuario, valor, tipo, origem, descricao, status, data_criacao) VALUES (?, ?, ?, "uso", ?, ?, ?)');
$stmt->bind_param('idssss', $usuarioId, $valor, $tipo, $descricao, $statusCb, $dataLanc);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Cashback lançado.');
bo_redirect($secao);
