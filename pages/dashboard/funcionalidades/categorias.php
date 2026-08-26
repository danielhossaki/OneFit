<?php
/**
 * funcionalidades/categorias.php
 * CRUD da tela "Categorias" do admin (tabela `categorias`). Ao renomear,
 * propaga o novo nome para os produtos que usavam o nome antigo.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Categoria inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('DELETE FROM categorias WHERE id_categoria = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Categoria excluída.');
    bo_redirect($secao);
}

$nome = bo_str('nome');
if (!$nome) {
    bo_flash('error', 'Informe o nome da categoria.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Categoria inválida.');
        bo_redirect($secao);
    }
    $stmtOld = $conn->prepare('SELECT nome FROM categorias WHERE id_categoria = ?');
    $stmtOld->bind_param('i', $id);
    $stmtOld->execute();
    $old = $stmtOld->get_result()->fetch_assoc();
    $stmtOld->close();

    $stmt = $conn->prepare('UPDATE categorias SET nome = ? WHERE id_categoria = ?');
    $stmt->bind_param('si', $nome, $id);
    try {
        $stmt->execute();
    } catch (\Throwable $e) {
        bo_flash('error', 'Já existe uma categoria com este nome.');
        bo_redirect($secao);
    }
    $stmt->close();

    if ($old && $old['nome'] !== $nome) {
        $stmtP = $conn->prepare('UPDATE produtos SET categoria = ? WHERE categoria = ?');
        $stmtP->bind_param('ss', $nome, $old['nome']);
        $stmtP->execute();
        $stmtP->close();
    }

    bo_flash('success', 'Categoria atualizada.');
    bo_redirect($secao);
}

$stmt = $conn->prepare('INSERT INTO categorias (nome) VALUES (?)');
$stmt->bind_param('s', $nome);
try {
    $stmt->execute();
} catch (\Throwable $e) {
    bo_flash('error', 'Já existe uma categoria com este nome.');
    bo_redirect($secao);
}
$stmt->close();

bo_flash('success', 'Categoria criada.');
bo_redirect($secao);
