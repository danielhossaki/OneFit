<?php
/**
 * funcionalidades/planos.php
 * CRUD da tela "Cadastro de Planos" do admin (tabela `cadastro_planos`).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'toggle-status') {
    if (!$id) {
        bo_flash('error', 'Plano inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare("UPDATE cadastro_planos SET status = IF(status = 'ativo', 'inativo', 'ativo') WHERE id_plano = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Status do plano atualizado.');
    bo_redirect($secao);
}

$nome = bo_str('nome');
$valor = bo_num('valor');
$ciclo = bo_str('ciclo');
$status = bo_str('status') === 'inativo' ? 'inativo' : 'ativo';
$descricao = bo_str('descricao');
$beneficios = bo_str('beneficios');
$dias = ['Mensal' => 30, 'Trimestral' => 90, 'Semestral' => 180, 'Anual' => 365][$ciclo] ?? 30;

if (!$nome || $valor <= 0) {
    bo_flash('error', 'Preencha nome e um valor válido.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Plano inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE cadastro_planos SET nome=?, valor=?, duracao_dias=?, status=?, descricao=?, beneficios=? WHERE id_plano=?');
    $stmt->bind_param('sdisssi', $nome, $valor, $dias, $status, $descricao, $beneficios, $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Plano atualizado.');
    bo_redirect($secao);
}

$stmt = $conn->prepare('INSERT INTO cadastro_planos (nome, valor, duracao_dias, status, descricao, beneficios) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->bind_param('sdisss', $nome, $valor, $dias, $status, $descricao, $beneficios);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Plano criado.');
bo_redirect($secao);
