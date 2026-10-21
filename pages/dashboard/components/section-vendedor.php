<?php
/**
 * components/section-vendedor.php
 * Painel do perfil Vendedor:
 *  - Marketplace ("vendas"): logística das próprias vendas (status de envio,
 *    transportadora, código de rastreio) e o link para a loja;
 *  - Produtos: espelha a tela "Produtos" do admin, filtrada pelos produtos do
 *    próprio vendedor (funcionalidades/produtos.php confere o dono de cada um);
 *  - Categorias e Cashback: só consulta (a gestão continua exclusiva do admin).
 * Depende de $vendedorProdutos, $vendedorProdutosResumo, $vendedorVendas,
 * $transportadorasAtivas, $categoriasAtivasOptions, $categorias,
 * $cashbackResumo e $cashbackTransacoes (includes/db-data.php).
 */

$vendasStatusLabel = [
    'aguardando' => onefitTraduzir('Aguardando'),
    'preparando' => 'Preparando',
    'despachado' => 'Despachado',
    'entregue' => 'Entregue',
    'devolvido' => onefitTraduzir('Devolvido'),
    'extraviado' => onefitTraduzir('Extraviado'),
];
?>

<!-- ===== VENDEDOR · Marketplace (vendas e logística) ===== -->
<section class="bo-content-section" data-perfil="vendedor" data-section="vendas">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Marketplace'); ?></h1>
            <p><?php echo of_t('Suas vendas no marketplace e a logística de envio.'); ?></p>
        </div>
        <a class="btn-bo-outline" href="<?php echo BASE_URL; ?>pages/marketplace/marketplace.php">
            <i class="bi bi-shop"></i> <?php echo of_t('Abrir Marketplace'); ?>
        </a>
    </div>

    <div class="bo-filters">
        <select class="form-select" style="max-width:220px" data-bo-filter="status" data-bo-target="vendedorVendas">
            <option value=""><?php echo of_t('Todos os status'); ?></option>
            <?php foreach ($vendasStatusLabel as $valor => $label): ?>
                <option value="<?php echo $valor; ?>"><?php echo of_t($label); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="vendedorVendas">
                <thead>
                    <tr>
                        <th><?php echo of_t('Data'); ?></th>
                        <th><?php echo of_t('Produto'); ?></th>
                        <th><?php echo of_t('Comprador'); ?></th>
                        <th><?php echo of_t('Qtd.'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('Transportadora'); ?></th>
                        <th><?php echo of_t('Frete'); ?></th>
                        <th><?php echo of_t('Rastreio / Status'); ?></th>
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
                                            <option value="<?php echo $valor; ?>" <?php echo $v['statusLogistica'] === $valor ? 'selected' : ''; ?>><?php echo of_t($label); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="text" class="form-control form-control-sm mt-1" name="codigo_rastreio" placeholder="<?php echo of_t('Código de rastreio'); ?>" value="<?php echo htmlspecialchars($v['codigoRastreio'] ?? ''); ?>">
                                    <button type="submit" class="btn-bo-outline btn-sm mt-1"><?php echo of_t('Salvar'); ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" <?php echo empty($vendedorVendas) ? '' : 'style="display:none"'; ?>>
                        <td colspan="8"><?php echo of_t('Nenhuma venda registrada ainda.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== VENDEDOR · Produtos (só os do próprio vendedor; ativos/inativos) ===== -->
<section class="bo-content-section" data-perfil="vendedor" data-section="produtos">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Produtos'); ?></h1>
            <p><?php echo of_t('Cadastre e mantenha os seus produtos do marketplace.'); ?></p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalProdutoNovo">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Cadastro de Produto'); ?>
        </button>
    </div>
    <?php bo_modal_produto(null, 'produtos', $categoriasAtivasOptions); ?>

    <div class="row g-3 mb-3 mt-1">
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Total cadastrados'); ?></div>
                <div class="bo-card-value"><?php echo $vendedorProdutosResumo['total']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Ativos'); ?></div>
                <div class="bo-card-value"><?php echo $vendedorProdutosResumo['disponiveis']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Inativos / pausados'); ?></div>
                <div class="bo-card-value"><?php echo $vendedorProdutosResumo['indisponiveis']; ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="<?php echo of_t('Buscar por nome ou ID'); ?>"
            data-bo-filter="search" data-bo-target="vendedorProdutos">
        <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="vendedorProdutos">
            <option value=""><?php echo of_t('Todos'); ?></option>
            <option value="disponivel"><?php echo of_t('Ativo'); ?></option>
            <option value="indisponivel"><?php echo of_t('Inativo / pausado'); ?></option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="vendedorProdutos">
                <thead>
                    <tr>
                        <th><?php echo of_t('Foto'); ?></th>
                        <th>ID</th>
                        <th><?php echo of_t('Nome'); ?></th>
                        <th><?php echo of_t('Preço'); ?></th>
                        <th><?php echo of_t('Estoque'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
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
                            <td><?php echo bo_badge($p['status'] === 'disponivel', onefitTraduzir('Ativo'), onefitTraduzir('Inativo')); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('produtos', $p['id'], 'produtos', $p['status'] === 'disponivel', onefitTraduzir('Ativo'), onefitTraduzir('Inativo')); ?>
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalProdutoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('produtos', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7"><?php echo of_t('Nenhum produto encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($vendedorProdutos as $p): ?>
        <?php bo_modal_produto($p, 'produtos', $categoriasAtivasOptions); ?>
        <?php bo_modal_confirmar_exclusao('produtos', $p['id'], $p['nome'], 'produtos'); ?>
    <?php endforeach; ?>
</section>

<?php
// Categorias e Cashback: componentes compartilhados com o admin, só para consulta.
$boPerfilSecao = 'vendedor';
$boSomenteLeitura = true;
require __DIR__ . '/section-categorias.php';
require __DIR__ . '/section-cashbacks.php';
