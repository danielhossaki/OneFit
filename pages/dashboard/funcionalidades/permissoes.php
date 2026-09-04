<?php
/**
 * funcionalidades/permissoes.php
 * CRUD da tela "Permissões" do admin (tabela `permissoes`, com FK `funcao`
 * para a tabela `funcao`). Quando a função escolhida for uma das 3 conhecidas
 * pelo sistema de login (admin/profissional/aluno), sincroniza também
 * usuarios.tipo_usuario — é esse campo que controla o acesso real.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

/**
 * Sincroniza usuarios.tipo_usuario com a função concedida, quando o nome da
 * função bater com um dos 3 tipos reconhecidos pelo login (comparação
 * sem acento/maiúsculas). Funções personalizadas não alteram o acesso real.
 */
function bo_sincronizar_tipo_usuario(mysqli $conn, string $email, string $funcaoNome): void
{
    $tipo = strtolower($funcaoNome);
    if (!in_array($tipo, ['admin', 'profissional', 'aluno', 'vendedor'], true)) {
        return;
    }
    $stmt = $conn->prepare('UPDATE usuarios SET tipo_usuario = ? WHERE email = ?');
    $stmt->bind_param('ss', $tipo, $email);
    $stmt->execute();
    $stmt->close();
}

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Permissão inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('SELECT email FROM permissoes WHERE id_permissao = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $stmt = $conn->prepare('DELETE FROM permissoes WHERE id_permissao = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();

    if ($row) {
        bo_sincronizar_tipo_usuario($conn, $row['email'], 'aluno');
    }

    bo_flash('success', 'Permissão removida.');
    bo_redirect($secao);
}

$email = bo_str('email');
$idFuncao = (int) bo_str('funcao');

if (!$idFuncao) {
    bo_flash('error', 'Selecione uma função válida.');
    bo_redirect($secao);
}

$funcaoStmt = $conn->prepare('SELECT nome FROM funcao WHERE id_funcao = ?');
$funcaoStmt->bind_param('i', $idFuncao);
$funcaoStmt->execute();
$funcaoRow = $funcaoStmt->get_result()->fetch_assoc();
$funcaoStmt->close();
if (!$funcaoRow) {
    bo_flash('error', 'Função não encontrada.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Permissão inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE permissoes SET funcao = ? WHERE id_permissao = ?');
    $stmt->bind_param('ii', $idFuncao, $id);
    $stmt->execute();
    $stmt->close();

    $emailStmt = $conn->prepare('SELECT email FROM permissoes WHERE id_permissao = ?');
    $emailStmt->bind_param('i', $id);
    $emailStmt->execute();
    $emailRow = $emailStmt->get_result()->fetch_assoc();
    $emailStmt->close();
    if ($emailRow) {
        bo_sincronizar_tipo_usuario($conn, $emailRow['email'], $funcaoRow['nome']);
    }

    bo_flash('success', 'Permissão salva.');
    bo_redirect($secao);
}

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    bo_flash('error', 'Informe um e-mail válido.');
    bo_redirect($secao);
}

$check = $conn->prepare('SELECT nome FROM usuarios WHERE email = ? LIMIT 1');
$check->bind_param('s', $email);
$check->execute();
$usuario = $check->get_result()->fetch_assoc();
$check->close();
if (!$usuario) {
    bo_flash('error', 'Nenhum usuário encontrado com este e-mail.');
    bo_redirect($secao);
}

$dup = $conn->prepare('SELECT id_permissao FROM permissoes WHERE email = ? LIMIT 1');
$dup->bind_param('s', $email);
$dup->execute();
if ($dup->get_result()->fetch_assoc()) {
    $dup->close();
    bo_flash('error', 'Este usuário já possui uma permissão cadastrada. Edite a existente.');
    bo_redirect($secao);
}
$dup->close();

// A tabela `permissoes` não tem AUTO_INCREMENT na chave primária: calcula o
// próximo ID manualmente antes de gravar.
$novoId = 1;
if ($r = $conn->query('SELECT COALESCE(MAX(id_permissao), 0) + 1 AS proximo FROM permissoes')) {
    $novoId = (int) $r->fetch_assoc()['proximo'];
}

$nome = $usuario['nome'];
$stmt = $conn->prepare('INSERT INTO permissoes (id_permissao, nome, email, funcao) VALUES (?, ?, ?, ?)');
$stmt->bind_param('issi', $novoId, $nome, $email, $idFuncao);
$stmt->execute();
$stmt->close();

bo_sincronizar_tipo_usuario($conn, $email, $funcaoRow['nome']);

bo_flash('success', 'Permissão cadastrada.');
bo_redirect($secao);
