<?php
/**
 * funcionalidades/funcoes.php
 * CRUD da tela "Funções" do admin (tabela `funcao`, referenciada por
 * `permissoes.funcao`). Mesmo padrão de categorias.php.
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Função inválida.');
        bo_redirect($secao);
    }
    $check = $conn->prepare('SELECT id_permissao FROM permissoes WHERE funcao = ? LIMIT 1');
    $check->bind_param('i', $id);
    $check->execute();
    if ($check->get_result()->fetch_assoc()) {
        $check->close();
        bo_flash('error', 'Não é possível excluir: esta função está em uso em uma ou mais permissões.');
        bo_redirect($secao);
    }
    $check->close();

    $stmt = $conn->prepare('DELETE FROM funcao WHERE id_funcao = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Função excluída.');
    bo_redirect($secao);
}

$nome = bo_str('nome');
if (!$nome) {
    bo_flash('error', 'Informe o nome da função.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Função inválida.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE funcao SET nome = ? WHERE id_funcao = ?');
    $stmt->bind_param('si', $nome, $id);
    try {
        $stmt->execute();
    } catch (\Throwable $e) {
        bo_flash('error', 'Já existe uma função com este nome.');
        bo_redirect($secao);
    }
    $stmt->close();
    bo_flash('success', 'Função atualizada.');
    bo_redirect($secao);
}

// A tabela `funcao` não tem AUTO_INCREMENT na chave primária: calcula o
// próximo ID manualmente antes de gravar.
$novoId = 1;
if ($r = $conn->query('SELECT COALESCE(MAX(id_funcao), 0) + 1 AS proximo FROM funcao')) {
    $novoId = (int) $r->fetch_assoc()['proximo'];
}

$stmt = $conn->prepare('INSERT INTO funcao (id_funcao, nome) VALUES (?, ?)');
$stmt->bind_param('is', $novoId, $nome);
try {
    $stmt->execute();
} catch (\Throwable $e) {
    bo_flash('error', 'Já existe uma função com este nome.');
    bo_redirect($secao);
}
$stmt->close();

bo_flash('success', 'Função criada.');
bo_redirect($secao);
