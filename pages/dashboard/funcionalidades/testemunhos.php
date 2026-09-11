<?php
/**
 * funcionalidades/testemunhos.php
 * Moderação (aba admin "Comentários"): aprovar/reprovar o depoimento
 * enviado pelo aluno e, depois de aprovado, ativar/desativar sua exibição
 * na home (tabela `testemunhos`).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if (!$id) {
    bo_flash('error', 'Comentário inválido.');
    bo_redirect($secao);
}

if ($acao === 'delete') {
    $stmt = $conn->prepare('DELETE FROM testemunhos WHERE id_testemunho = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Comentário excluído.');
    bo_redirect($secao);
}

if ($acao === 'aprovar') {
    $stmt = $conn->prepare("UPDATE testemunhos SET aprovacao = 'aprovado' WHERE id_testemunho = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Comentário aprovado e liberado para a home.');
    bo_redirect($secao);
}

if ($acao === 'reprovar') {
    $stmt = $conn->prepare("UPDATE testemunhos SET aprovacao = 'reprovado' WHERE id_testemunho = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Comentário reprovado.');
    bo_redirect($secao);
}

if ($acao === 'toggle-status') {
    $stmt = $conn->prepare("UPDATE testemunhos SET visibilidade = IF(visibilidade = 'ativo', 'inativo', 'ativo') WHERE id_testemunho = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Visibilidade do comentário atualizada.');
    bo_redirect($secao);
}

bo_flash('error', 'Ação inválida.');
bo_redirect($secao);
