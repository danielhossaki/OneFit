<?php
/**
 * actions/senha.php
 * Handler da tela "Meu perfil" (todos os perfis): troca a senha do
 * próprio usuário logado, exigindo confirmação da senha atual.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$senhaAtual = bo_str('senha_atual');
$senhaNova = bo_str('senha_nova');
$senhaConfirma = bo_str('senha_confirma');
$idUsuario = (int) $_SESSION['id_usuario'];

if (!$senhaAtual || !$senhaNova || !$senhaConfirma) {
    bo_flash('error', 'Preencha a senha atual, a nova senha e a confirmação.');
    bo_redirect_perfil();
}
if (strlen($senhaNova) < 6) {
    bo_flash('error', 'A nova senha precisa ter pelo menos 6 caracteres.');
    bo_redirect_perfil();
}
if ($senhaNova !== $senhaConfirma) {
    bo_flash('error', 'A confirmação não bate com a nova senha.');
    bo_redirect_perfil();
}

$stmt = $conn->prepare('SELECT senha FROM usuarios WHERE id_usuario = ? LIMIT 1');
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row || !password_verify($senhaAtual, $row['senha'])) {
    bo_flash('error', 'A senha atual informada está incorreta.');
    bo_redirect_perfil();
}

$novoHash = password_hash($senhaNova, PASSWORD_DEFAULT);
$stmt = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id_usuario = ?');
$stmt->bind_param('si', $novoHash, $idUsuario);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Senha alterada com sucesso!');
bo_redirect_perfil();
