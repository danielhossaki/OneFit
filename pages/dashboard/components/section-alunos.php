<?php
/**
 * components/section-alunos.php
 * Tela "Alunos" (data-section "usuarios"; só contas tipo_usuario = 'aluno').
 * O admin gerencia; a recepção só consulta.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem botões, formulários nem modais de gravação (padrão).
 *                      A rota funcionalidades/usuarios.php continua só do admin.
 * Depende de $usuarios e, no modo de gestão, de $planos (includes/db-data.php).
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
?>

<!-- ===== Alunos (data-section "usuarios"; só contas tipo_usuario = 'aluno') ===== -->
<section class="bo-content-section" data-perfil="<?php echo htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8'); ?>" data-section="usuarios">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo of_t('Alunos'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Consulte os alunos cadastrados e a situação da matrícula.') : of_t('Gerencie os alunos cadastrados. Funcionários ficam na tela Funcionários.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalUsuarioNovo">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Novo aluno'); ?>
        </button>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura) bo_modal_usuario(null, 'usuarios', $planos); ?>

    <!-- Filtros: busca por texto + status (ligados à tabela pelo data-bo-target="usuarios") -->
    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:320px" placeholder="<?php echo of_t('Buscar por ID, nome e email'); ?>"
            data-bo-filter="search" data-bo-target="usuarios">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="usuarios">
            <option value=""><?php echo of_t('Todos os status'); ?></option>
            <option value="ativo"><?php echo of_t('Ativo'); ?></option>
            <option value="inativo"><?php echo of_t('Inativo'); ?></option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="usuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo of_t('Nome'); ?></th>
                        <th><?php echo of_t('E-mail'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Plano'); ?></th>
                        <th><?php echo of_t('Nº matrícula'); ?></th>
                        <th><?php echo of_t('Data inicial'); ?></th>
                        <th><?php echo of_t('Final de contrato'); ?></th>
                        <?php if (!$boSomenteLeitura): ?><th><?php echo of_t('Ações'); ?></th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr data-status="<?php echo $u['status']; ?>"
                            data-search="<?php echo strtolower($u['id'] . ' ' . $u['nome'] . ' ' . $u['email'] . ' ' . $u['cpf']); ?>">
                            <td>#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $u['nome']; ?></td>
                            <td><?php echo $u['email']; ?></td>
                            <td><?php echo bo_badge($u['status'] === 'ativo'); ?></td>
                            <td><?php echo htmlspecialchars($u['plano']); ?></td>
                            <td><?php echo $u['matricula']; ?></td>
                            <td><?php echo onefitData('d/m/Y', strtotime($u['dataInicial'])); ?></td>
                            <td><?php echo onefitData('d/m/Y', strtotime($u['dataFinal'])); ?></td>
                            <?php if (!$boSomenteLeitura): ?>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalUsuarioEditar<?php echo $u['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php bo_form_toggle('usuarios', $u['id'], 'usuarios', $u['status'] === 'ativo'); ?>
                                    <?php echo bo_botao_excluir('usuarios', $u['id']); ?>
                                </div>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Linha exibida pelo JS quando o filtro não encontra nada -->
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="<?php echo $boSomenteLeitura ? 8 : 9; ?>"><?php echo of_t('Nenhum usuário encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php foreach ($usuarios as $u): ?>
            <?php bo_modal_usuario($u, 'usuarios', $planos); ?>
            <?php bo_modal_confirmar_exclusao('usuarios', $u['id'], $u['nome'], 'usuarios'); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
