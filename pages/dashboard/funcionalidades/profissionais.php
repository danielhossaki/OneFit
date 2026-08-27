<?php
/**
 * funcionalidades/profissionais.php
 * CRUD da tela "Profissionais" do admin (tabela `cadastro_profissional`).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Profissional inválido.');
        bo_redirect($secao);
    }
    try {
        $stmt = $conn->prepare('DELETE FROM cadastro_profissional WHERE id_profissional = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        bo_flash('success', 'Profissional excluído.');
    } catch (\Throwable $e) {
        bo_flash('error', 'Não é possível excluir: este profissional possui agendamentos vinculados. Inative-o em vez de excluir.');
    }
    bo_redirect($secao);
}

$nome = bo_str('nome');
$funcao = bo_str('funcao');
$documento = bo_str('documento');
$status = bo_str('status') === 'inativo' ? 'inativo' : 'ativo';
$email = bo_str('email');
$celularDigitado = bo_str('celular') ?: bo_str('telefone');
$celular = preg_replace('/\D/', '', $celularDigitado);
$descricao = bo_str('descricao');
$fotoUpload = bo_processar_upload_imagem('foto_arquivo', 'profissionais');
$foto = $fotoUpload ?? bo_str('foto');

if (!$nome || !$email || !$celular) {
    bo_flash('error', 'Preencha nome, e-mail e celular.');
    bo_redirect($secao);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bo_flash('error', 'Informe um e-mail válido.');
    bo_redirect($secao);
}
if (!bo_valida_celular($celular)) {
    bo_flash('error', 'Informe um celular/telefone válido, com DDD (10 ou 11 números).');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Profissional inválido.');
        bo_redirect($secao);
    }
    $check = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE email = ? AND id_profissional <> ? LIMIT 1');
    $check->bind_param('si', $email, $id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        bo_flash('error', 'Já existe outro profissional com este e-mail.');
        bo_redirect($secao);
    }
    $check->close();

    $stmt = $conn->prepare('UPDATE cadastro_profissional SET nome=?, especialidade=?, registro_profissional=?, status=?, email=?, celular=?, descricao=?, foto=? WHERE id_profissional=?');
    $stmt->bind_param('ssssssssi', $nome, $funcao, $documento, $status, $email, $celular, $descricao, $foto, $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Profissional atualizado.');
    bo_redirect($secao);
}

$check = $conn->prepare('SELECT id_profissional FROM cadastro_profissional WHERE email = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
if ($check->get_result()->fetch_assoc()) {
    $check->close();
    bo_flash('error', 'Já existe um profissional com este e-mail.');
    bo_redirect($secao);
}
$check->close();

$stmt = $conn->prepare('INSERT INTO cadastro_profissional (nome, especialidade, registro_profissional, status, email, celular, descricao, foto) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
$stmt->bind_param('ssssssss', $nome, $funcao, $documento, $status, $email, $celular, $descricao, $foto);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Profissional criado.');
bo_redirect($secao);
