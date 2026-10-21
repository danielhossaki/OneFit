<?php
/**
 * components/section-cashbacks.php
 * Tela "Cashbacks" (saldo geral e lançamentos). O admin gerencia; a recepção
 * e o vendedor só consultam.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem lançamento, distribuição em massa, exclusão nem
 *                      modais (padrão). A rota funcionalidades/cashbacks.php
 *                      continua só do admin.
 * Depende de $cashbackResumo e $cashbackTransacoes (includes/db-data.php).
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
?>

<!-- ===== Cashbacks (saldo geral + lançamentos manuais) ===== -->
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8'); ?>" data-section="cashbacks">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo of_t('Cashbacks'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Consulte o saldo, a distribuição e os lançamentos de cashback.') : of_t('Acompanhe saldo, distribuição e lançamentos de cashback.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#modalCashbackMassa">
                <i class="bi bi-people"></i> <?php echo of_t('Distribuição em Massa'); ?>
            </button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalCashbackLancar">
                <i class="bi bi-plus-lg"></i> <?php echo of_t('Lançar Cashback'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php bo_modal_cashback_massa('cashbacks'); ?>
        <?php bo_modal_cashback_lancar('cashbacks'); ?>
    <?php endif; ?>

    <!-- Cards de resumo: saldo total / distribuídos / debitado / creditado -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Saldo total'); ?></div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['saldoTotal']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Distribuídos'); ?></div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['distribuidos']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Debitado'); ?></div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['debitado']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Creditado'); ?></div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['creditado']); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:200px" placeholder="<?php echo of_t('Buscar por ID'); ?>"
            data-bo-filter="search" data-bo-target="cashbacks">
        <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="cashbacks">
            <option value=""><?php echo of_t('Todos os tipos'); ?></option>
            <option value="credito"><?php echo of_t('Crédito'); ?></option>
            <option value="debito"><?php echo of_t('Débito'); ?></option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="cashbacks">
                <thead>
                    <tr>
                        <th><?php echo of_t('ID transação'); ?></th>
                        <th><?php echo of_t('Data'); ?></th>
                        <th><?php echo of_t('Tipo'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('ID usuário'); ?></th>
                        <th><?php echo of_t('Motivo'); ?></th>
                        <?php if (!$boSomenteLeitura): ?><th><?php echo of_t('Ações'); ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cashbackTransacoes as $c): ?>
                        <tr data-type="<?php echo $c['tipo']; ?>" data-search="<?php echo strtolower('#' . str_pad($c['id'], 4, '0', STR_PAD_LEFT) . ' ' . $c['id']); ?>">
                            <td>#<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo onefitData('d/m/Y', strtotime($c['data'])); ?></td>
                            <td><?php echo $c['tipo'] === 'credito' ? onefitTraduzir('Crédito') : onefitTraduzir('Débito'); ?></td>
                            <td><?php echo bo_money($c['valor']); ?></td>
                            <td>#<?php echo str_pad($c['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $c['motivo']; ?></td>
                            <?php if (!$boSomenteLeitura): ?>
                            <td>
                                <div class="bo-table-actions">
                                    <?php echo bo_botao_excluir('cashbacks', $c['id']); ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="<?php echo $boSomenteLeitura ? 6 : 7; ?>"><?php echo of_t('Nenhuma transação encontrada para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php foreach ($cashbackTransacoes as $c): ?>
            <?php bo_modal_confirmar_exclusao('cashbacks', $c['id'], 'a transação #' . $c['id'], 'cashbacks'); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
