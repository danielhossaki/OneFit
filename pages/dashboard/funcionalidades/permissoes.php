<?php
/**
 * funcionalidades/permissoes.php
 * "Permissões" não tem tabela própria: eleva ou revoga o acesso alterando
 * usuarios.tipo_usuario (aluno/profissional/admin).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$secao = bo_secao_atual();
$usuarioId = (int) bo_str('usuarioId');
if (!$usuarioId) {
    $usuarioId = (int) bo_str('id');
}

if (!$usuarioId) {
    // Cadastro novo: identifica o usuário pelo e-mail informado, não por ID.
    $email = bo_str('email');
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        bo_flash('error', 'Informe um e-mail válido.');
        bo_redirect($secao);
    }
    $check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE email = ? LIMIT 1');
    $check->bind_param('s', $email);
    $check->execute();
    $row = $check->get_result()->fetch_assoc();
    $check->close();
    if (!$row) {
        bo_flash('error', 'Nenhum usuário encontrado com este e-mail.');
        bo_redirect($secao);
    }
    $usuarioId = (int) $row['id_usuario'];
} else {
    $check = $conn->prepare('SELECT id_usuario FROM usuarios WHERE id_usuario = ?');
    $check->bind_param('i', $usuarioId);
    $check->execute();
    if (!$check->get_result()->fetch_assoc()) {
        $check->close();
        bo_flash('error', 'Usuário não encontrado.');
        bo_redirect($secao);
    }
    $check->close();
}

if ($acao === 'delete') {
    $stmt = $conn->prepare("UPDATE usuarios SET tipo_usuario = 'aluno' WHERE id_usuario = ?");
    $stmt->bind_param('i', $usuarioId);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Permissão removida.');
    bo_redirect($secao);
}

$funcao = strtolower(bo_str('funcao'));
if (!in_array($funcao, ['admin', 'profissional'], true)) {
    bo_flash('error', 'Selecione uma função válida.');
    bo_redirect($secao);
}

$stmt = $conn->prepare('UPDATE usuarios SET tipo_usuario = ? WHERE id_usuario = ?');
$stmt->bind_param('si', $funcao, $usuarioId);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Permissão salva.');
bo_redirect($secao);
