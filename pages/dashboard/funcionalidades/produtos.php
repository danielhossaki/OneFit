<?php
/**
 * funcionalidades/produtos.php
 * CRUD da tela "Produtos" do admin (tabela `produtos`) — também usado pelo
 * vendedor na tela "Vendas Marketplace" (mesma tabela, filtrada por dono).
 */

$bo_papeis_permitidos = ['admin', 'vendedor'];
require __DIR__ . '/_shared.php';
bo_check_csrf();

$souVendedor = ($_SESSION['tipo_usuario'] ?? '') === 'vendedor';
$idVendedorLogado = (int) ($_SESSION['id_usuario'] ?? 0);

$acao = bo_str('acao');
$id = (int) bo_str('id');
$secao = bo_secao_atual();

/**
 * Um vendedor só pode agir sobre os próprios produtos — nunca confia no id
 * vindo do POST sozinho. Admin não tem essa restrição.
 */
function prod_pertence_ao_vendedor(mysqli $conn, int $id, int $idVendedor): bool
{
    $stmt = $conn->prepare('SELECT id_produto FROM produtos WHERE id_produto = ? AND id_vendedor = ?');
    $stmt->bind_param('ii', $id, $idVendedor);
    $stmt->execute();
    $ok = (bool) $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $ok;
}

if ($acao === 'toggle-status') {
    if (!$id || ($souVendedor && !prod_pertence_ao_vendedor($conn, $id, $idVendedorLogado))) {
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
    if (!$id || ($souVendedor && !prod_pertence_ao_vendedor($conn, $id, $idVendedorLogado))) {
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
    if (!$id || ($souVendedor && !prod_pertence_ao_vendedor($conn, $id, $idVendedorLogado))) {
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

// Produto novo: se quem cadastra é vendedor, o produto já nasce com dono
// (não confia em id_vendedor vindo do POST); se é admin, fica sem dono
// (produto "ONE FIT"), já que a atribuição de produtos a vendedores é
// feita pelo próprio vendedor no autoatendimento da tela de Vendas.
$idVendedorNovo = $souVendedor ? $idVendedorLogado : null;

$stmt = $conn->prepare('INSERT INTO produtos (id_vendedor, nome, categoria, preco, desconto, cashback_valor, estoque, imagem, descricao, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "ativo")');
$stmt->bind_param('issdddiss', $idVendedorNovo, $nome, $categoria, $preco, $desconto, $cashback, $estoque, $imagem, $descricao);
$stmt->execute();
$stmt->close();

bo_flash('success', 'Produto criado.');
bo_redirect($secao);
