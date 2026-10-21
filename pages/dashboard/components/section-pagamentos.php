<?php
/**
 * components/section-pagamentos.php
 * Tela "Pagamentos" (cobranças das matrículas). O admin gerencia; a recepção
 * só consulta.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem botões, formulários nem modais de gravação (padrão).
 *                      A rota funcionalidades/pagamentos.php continua só do admin.
 * Depende de $pagamentos (includes/db-data.php).
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
?>

<!-- ===== Pagamentos (recebimentos de mensalidade/produtos/taxas) ===== -->
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8'); ?>" data-section="pagamentos">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo of_t('Pagamentos'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Consulte os pagamentos e a situação de cada cobrança.') : of_t('Acompanhe e registre os pagamentos recebidos.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagamentoNovo">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Registrar Pagamento'); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura) bo_modal_pagamento(null, 'pagamentos'); ?>

    <!-- Filtros: busca por ID, tipo de pagamento e intervalo de datas -->
    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:200px" placeholder="<?php echo of_t('Buscar por ID'); ?>"
            data-bo-filter="search" data-bo-target="pagamentos">
        <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="pagamentos">
            <option value=""><?php echo of_t('Todos os tipos'); ?></option>
            <option value="PIX">PIX</option>
            <option value="Cartão"><?php echo of_t('Cartão'); ?></option>
        </select>
        <div class="bo-daterange">
            <?php echo of_t('De'); ?> <input type="date" class="form-control" data-bo-filter="date-from" data-bo-target="pagamentos">
            <?php echo of_t('até'); ?> <input type="date" class="form-control" data-bo-filter="date-to" data-bo-target="pagamentos">
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="pagamentos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo of_t('Data'); ?></th>
                        <th><?php echo of_t('Tipo'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('ID usuário'); ?></th>
                        <th><?php echo of_t('Observação'); ?></th>
                        <?php if (!$boSomenteLeitura): ?><th><?php echo of_t('Ações'); ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagamentos as $p): ?>
                        <tr data-type="<?php echo $p['tipo']; ?>" data-date="<?php echo substr($p['data'], 0, 10); ?>"
                            data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id']); ?>">
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo onefitData('d/m/Y', strtotime($p['data'])); ?></td>
                            <td><?php echo $p['tipo']; ?></td>
                            <td><?php echo bo_money($p['valor']); ?></td>
                            <td>#<?php echo str_pad($p['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['observacao']; ?></td>
                            <?php if (!$boSomenteLeitura): ?>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalPagamentoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('pagamentos', $p['id']); ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="<?php echo $boSomenteLeitura ? 6 : 7; ?>"><?php echo of_t('Nenhum pagamento encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php foreach ($pagamentos as $p): ?>
            <?php bo_modal_pagamento($p, 'pagamentos'); ?>
            <?php bo_modal_confirmar_exclusao('pagamentos', $p['id'], 'o pagamento #' . $p['id'], 'pagamentos'); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
