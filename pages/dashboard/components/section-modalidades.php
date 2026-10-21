<?php
/**
 * components/section-modalidades.php
 * Tela "Modalidades" (tabela `modalidades`, usada no cadastro de
 * profissionais e na home). O admin gerencia; a recepção só consulta.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem botões, formulários nem modais de gravação (padrão).
 *                      A rota funcionalidades/modalidades.php continua só do admin.
 * Depende de $modalidadesAdm (includes/db-data.php).
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
?>

<!-- ===== Modalidades (tabela `modalidades`, usada no cadastro de profissionais) ===== -->
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8'); ?>" data-section="modalidades">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo of_t('Modalidades'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Consulte as modalidades oferecidas pela academia.') : of_t('Gerencie as modalidades oferecidas pela academia.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalModalidadeNova">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Nova Modalidade'); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura) bo_modal_modalidade(null, 'modalidades'); ?>

    <div class="bo-list">
        <?php foreach ($modalidadesAdm as $m): ?>
            <div class="bo-list-item">
                <div>
                    <div class="bo-list-title"><?php echo $m['nome']; ?> <?php echo bo_badge($m['status'] === 'ativo', onefitTraduzir('Ativo'), onefitTraduzir('Inativo')); ?></div>
                    <?php if ($m['descricao']): ?><div class="bo-list-sub"><?php echo htmlspecialchars($m['descricao']); ?></div><?php endif; ?>
                </div>
                <?php if (!$boSomenteLeitura): ?>
                <div class="bo-table-actions">
                    <?php bo_form_toggle('modalidades', $m['id'], 'modalidades', $m['status'] === 'ativo'); ?>
                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalModalidadeEditar<?php echo $m['id']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php echo bo_botao_excluir('modalidades', $m['id']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$boSomenteLeitura): ?>
                <?php bo_modal_modalidade($m, 'modalidades'); ?>
                <?php bo_modal_confirmar_exclusao('modalidades', $m['id'], 'a modalidade ' . $m['nome'], 'modalidades'); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
