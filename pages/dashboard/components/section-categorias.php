<?php
/**
 * components/section-categorias.php
 * Tela "Categorias" (organização dos produtos da loja). O admin gerencia; o
 * vendedor só consulta as categorias ativas que usa no cadastro de produtos.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem botões, formulários nem modais de gravação (padrão).
 *                      A rota funcionalidades/categorias.php continua só do admin.
 * Depende de $categorias (includes/db-data.php).
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
?>

<!-- ===== Categorias (organização dos produtos da loja) ===== -->
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8'); ?>" data-section="categorias">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo of_t('Categorias'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Categorias disponíveis para cadastrar e organizar os seus produtos.') : of_t('Organize as categorias de produtos da loja.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalCategoriaNova">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Nova Categoria'); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura) bo_modal_categoria(null, 'categorias'); ?>

    <div class="bo-list">
        <?php foreach ($categorias as $c): ?>
            <div class="bo-list-item">
                <div class="bo-list-title"><?php echo $c['nome']; ?></div>
                <?php if (!$boSomenteLeitura): ?>
                <div class="bo-table-actions">
                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalCategoriaEditar<?php echo $c['id']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php echo bo_botao_excluir('categorias', $c['id']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if (!$boSomenteLeitura): ?>
                <?php bo_modal_categoria($c, 'categorias'); ?>
                <?php bo_modal_confirmar_exclusao('categorias', $c['id'], $c['nome'], 'categorias'); ?>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$categorias): ?><p class="text-secondary"><?php echo of_t('Nenhuma categoria cadastrada.'); ?></p><?php endif; ?>
    </div>
</section>
