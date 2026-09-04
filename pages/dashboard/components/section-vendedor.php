<?php
/**
 * components/section-vendedor.php
 * Tela "Vendas Marketplace" do perfil Vendedor — espelha a aba "Produtos"
 * do admin (filtrada pelos produtos do próprio vendedor) e adiciona a
 * logística das vendas (status de envio, transportadora, código de
 * rastreio). Depende de $vendedorProdutos, $vendedorProdutosResumo,
 * $vendedorVendas, $transportadorasAtivas, $categoriasAtivasOptions
 * (includes/db-data.php).
 */

$vendasStatusLabel = [
    'aguardando' => 'Aguardando',
    'preparando' => 'Preparando',
    'despachado' => 'Despachado',
    'entregue' => 'Entregue',
    'devolvido' => 'Devolvido',
    'extraviado' => 'Extraviado',
];
?>

<!-- ===== VENDEDOR · Vendas Marketplace ===== -->
<section class="bo-content-section" data-perfil="vendedor" data-section="vendas">
    <div class="bo-page-title">
        <div>
            <h1>Vendas Marketplace</h1>
            <p>Seus produtos e a logística das suas vendas no marketplace.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalProdutoNovo">
            <i class="bi bi-plus-lg"></i> Cadastro de Produto
        </button>
    </div>
    <?php bo_modal_produto(null, 'vendas', $categoriasAtivasOptions); ?>

    <ul class="nav nav-tabs bo-nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vendedorTabProdutos" type="button" role="tab">Produtos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#vendedorTabVendas" type="button" role="tab">Vendas e logística</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ===== Aba Produtos (ativos/inativos) ===== -->
        <div class="tab-pane fade show active" id="vendedorTabProdutos" role="tabpanel">
            <div class="row g-3 mb-3 mt-1">
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Total cadastrados</div>
                        <div class="bo-card-value"><?php echo $vendedorProdutosResumo['total']; ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Ativos</div>
                        <div class="bo-card-value"><?php echo $vendedorProdutosResumo['disponiveis']; ?></div>
                    </div>
                </div>
                <div class="col-4">
                    <div class="bo-card">
                        <div class="bo-card-label">Inativos / pausados</div>
                        <div class="bo-card-value"><?php echo $vendedorProdutosResumo['indisponiveis']; ?></div>
                    </div>
                </div>
            </div>

            <div class="bo-filters">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome ou ID"
                    data-bo-filter="search" data-bo-target="vendedorProdutos">
                <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="vendedorProdutos">
                    <option value="">Todos</option>
                    <option value="disponivel">Ativo</option>
                    <option value="indisponivel">Inativo / pausado</option>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="vendedorProdutos">
                        <thead>
                            <tr>
                                <th>Foto</th>
                                <th>ID</th>
                                <th>Nome</th>
                                <th>Preço</th>
                                <th>Estoque</th>
                                <th>Status</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendedorProdutos as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome']); ?>">
                                    <td>
                                        <div class="bo-thumb">
                                            <?php if ($p['imagem']): ?>
                                                <img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt="">
                                            <?php else: ?>
                                                <i class="bi bi-image"></i>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo bo_money($p['valorFinal']); ?></td>
                                    <td><?php echo $p['estoque']; ?></td>
                                    <td><?php echo bo_badge($p['status'] === 'disponivel', 'Ativo', 'Inativo'); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <?php bo_form_toggle('produtos', $p['id'], 'vendas', $p['status'] === 'disponivel', 'Ativo', 'Inativo'); ?>
                                            <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProdutoEditar<?php echo $p['id']; ?>">
                                                <i class="bi bi-pencil"></i>
                                            </button>
                                            <?php echo bo_botao_excluir('produtos', $p['id']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none">
                                <td colspan="7">Nenhum produto encontrado para os filtros selecionados.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php foreach ($vendedorProdutos as $p): ?>
                <?php bo_modal_produto($p, 'vendas', $categoriasAtivasOptions); ?>
                <?php bo_modal_confirmar_exclusao('produtos', $p['id'], $p['nome'], 'vendas'); ?>
            <?php endforeach; ?>
        </div>

        <!-- ===== Aba Vendas e logística ===== -->
        <div class="tab-pane fade" id="vendedorTabVendas" role="tabpanel">
            <div class="bo-filters mt-3">
                <select class="form-select" style="max-width:220px" data-bo-filter="status" data-bo-target="vendedorVendas">
                    <option value="">Todos os status</option>
                    <?php foreach ($vendasStatusLabel as $valor => $label): ?>
                        <option value="<?php echo $valor; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="vendedorVendas">
                        <thead>
                            <tr>
                                <th>Data</th>
                                <th>Produto</th>
                                <th>Comprador</th>
                                <th>Qtd.</th>
                                <th>Valor</th>
                                <th>Transportadora</th>
                                <th>Frete</th>
                                <th>Rastreio / Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($vendedorVendas as $v): ?>
                                <tr data-status="<?php echo $v['statusLogistica']; ?>">
                                    <td><?php echo $v['data']; ?></td>
                                    <td><?php echo htmlspecialchars($v['produto']); ?></td>
                                    <td><?php echo htmlspecialchars($v['comprador']); ?></td>
                                    <td><?php echo $v['quantidade']; ?></td>
                                    <td><?php echo bo_money($v['valor']); ?></td>
                                    <td><?php echo htmlspecialchars($v['transportadora']); ?></td>
                                    <td><?php echo bo_money($v['valorFrete']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo bo_form_action('vendas.php'); ?>" class="bo-inline-form">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'vendas'); ?>
                                            <?php echo bo_hidden('acao', 'update-status'); ?>
                                            <?php echo bo_hidden('id', $v['id']); ?>
                                            <select class="form-select form-select-sm" name="status_logistica" style="min-width:150px">
                                                <?php foreach ($vendasStatusLabel as $valor => $label): ?>
                                                    <option value="<?php echo $valor; ?>" <?php echo $v['statusLogistica'] === $valor ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1" name="codigo_rastreio" placeholder="Código de rastreio" value="<?php echo htmlspecialchars($v['codigoRastreio'] ?? ''); ?>">
                                            <button type="submit" class="btn-bo-outline btn-sm mt-1">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" <?php echo empty($vendedorVendas) ? '' : 'style="display:none"'; ?>>
                                <td colspan="8">Nenhuma venda registrada ainda.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>
