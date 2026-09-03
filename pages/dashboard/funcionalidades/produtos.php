<?php
/**
 * funcionalidades/produtos.php
 * CRUD da tela "Produtos" do admin (tabela `produtos`).
 */

require __DIR__ . '/_shared.php';
bo_check_csrf();

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

if ($acao === 'toggle-status') {
    if (!$id) {
        bo_flash('error', 'Produto inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare("UPDATE produtos SET status = IF(status = 'ativo', 'inativo', 'ativo') WHERE id_produto = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Status do produto atualizado.');
    bo_redirect($secao);
}

if ($acao === 'delete') {
    if (!$id) {
        bo_flash('error', 'Produto inválido.');
        bo_redirect($secao);
    }
    try {
        $stmt = $conn->prepare('DELETE FROM produtos WHERE id_produto = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $stmt->close();
        bo_flash('success', 'Produto excluído.');
    } catch (\Throwable $e) {
        bo_flash('error', 'Não é possível excluir: este produto já tem pedidos ou avaliações vinculadas. Inative-o em vez de excluir.');
    }
    bo_redirect($secao);
}

$nome = bo_str('nome');
$categoria = bo_str('categoria');
$preco = bo_num('preco');
$desconto = bo_num('desconto');
$cashback = bo_num('cashback');
$estoque = (int) bo_num('estoque');
$imagemUpload = bo_processar_upload_imagem('imagem_arquivo', 'produtos');
$imagem = $imagemUpload ?? bo_str('imagem_atual');
$descricao = bo_str('descricao');

if (!$nome || $preco <= 0) {
    bo_flash('error', 'Preencha nome e um preço válido.');
    bo_redirect($secao);
}

if ($acao === 'update') {
    if (!$id) {
        bo_flash('error', 'Produto inválido.');
        bo_redirect($secao);
    }
    $stmt = $conn->prepare('UPDATE produtos SET nome=?, categoria=?, preco=?, desconto=?, cashback_valor=?, estoque=?, imagem=?, descricao=? WHERE id_produto=?');
    $stmt->bind_param('ssdddissi', $nome, $categoria, $preco, $desconto, $cashback, $estoque, $imagem, $descricao, $id);
    $stmt->execute();
    $stmt->close();
    bo_flash('success', 'Produto atualizado.');
    bo_redirect($secao);
}

$stmt = $conn->prepare('INSERT INTO produtos (nome, categoria, preco, desconto, cashback_valor, estoque, imagem, descricao, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, "ativo")');
$stmt->bind_param('ssdddiss', $nome, $categoria, $preco, $desconto, $cashback, $estoque, $imagem, $descricao);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Produto criado.');
bo_redirect($secao);
