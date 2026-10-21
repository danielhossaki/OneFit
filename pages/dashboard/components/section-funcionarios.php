<?php
/**
 * components/section-funcionarios.php
 * Tela "Funcionários" do admin. Depende de includes/db-data.php:
 *   $funcionarios, $cadastrosProfissionaisLivres, $funcionariosMigracao, $funcionariosErro
 * Os botões só aparecem quando a operação é permitida, mas a permissão real
 * é sempre conferida no servidor (funcionalidades/funcionarios.php).
 */
$rotulosCargo = bo_funcionarios_rotulos_cargo();
$motivosCatraca = [
    'ok' => 'Liberada',
    'conta_inativa' => 'Bloqueada: conta inativa',
    'cadastro_profissional_inativo' => 'Bloqueada: cadastro profissional inativo ou ausente',
    'cargo_desconhecido' => 'Bloqueada: cargo desconhecido',
    'requer_matricula' => 'Depende da matrícula',
];
$rotulosStatus = ['ativo' => 'Ativo', 'inativo' => 'Inativo', 'bloqueado' => 'Bloqueado'];
$podeGravar = $funcionariosMigracao['ok'] && !$funcionariosErro;
$operadorDiretor = onefitPapelDiretor($_SESSION['tipo_usuario'] ?? null);
$idOperadorTela = (int) ($_SESSION['id_usuario'] ?? 0);
?>
<!-- ===== ADMIN · Funcionários (contas da equipe em usuarios.tipo_usuario) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="funcionarios">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Funcionários'); ?></h1>
            <p><?php echo of_t('Cadastre e gerencie professores, recepcionistas, vendedores e faxineiras.'); ?></p>
        </div>
        <?php if ($podeGravar): ?>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalFuncionarioNovo">
                <i class="bi bi-plus-lg"></i> <?php echo of_t('Novo funcionário'); ?>
            </button>
        <?php endif; ?>
    </div>

    <?php if ($funcionariosErro): ?>
        <div class="bo-notice" style="border-color:#dc3545;">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong><?php echo of_t('Não foi possível carregar os funcionários.'); ?></strong><span><?php echo of_t('Nenhuma alteração pode ser feita até o problema ser resolvido.'); ?></span></div>
        </div>
    <?php elseif (!$funcionariosMigracao['ok']): ?>
        <div class="bo-notice" style="border-color:#dc3545;">
            <i class="bi bi-database-exclamation"></i>
            <div>
                <strong><?php echo of_t('Migração de funcionários pendente'); ?></strong>
                <span><?php echo of_t('Este banco ainda não tem os cargos novos e a tabela de auditoria. Cadastro e edição ficam bloqueados até a migração ser aplicada (database/migrate-funcionarios.php).'); ?></span>
            </div>
        </div>
    <?php endif; ?>
    <div class="bo-notice">
        <i class="bi bi-shield-lock"></i>
        <div>
            <strong><?php echo of_t('Contas de administrador e de diretor'); ?></strong>
            <span><?php echo of_t($operadorDiretor
                ? 'Como Diretor, você também cria e altera contas de administrador e de diretor. O sistema mantém sempre pelo menos um Diretor ativo, e a sua própria conta não é alterada nesta tela.'
                : 'Aparecem aqui só para consulta. Criar, promover ou alterar administradores e diretores é exclusivo de um Diretor.'); ?></span>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:320px" placeholder="<?php echo of_t('Buscar por nome ou e-mail'); ?>"
            data-bo-filter="search" data-bo-target="funcionarios" aria-label="<?php echo of_t('Buscar por nome ou e-mail'); ?>">
        <select class="form-select" style="max-width:200px" data-bo-filter="type" data-bo-target="funcionarios" aria-label="<?php echo of_t('Cargo'); ?>">
            <option value=""><?php echo of_t('Todos os cargos'); ?></option>
            <?php foreach (array_diff(onefitPapeisFuncionarios(), ['profissional']) as $cargo): ?>
                <option value="<?php echo bo_val($cargo); ?>"><?php echo of_t($rotulosCargo[$cargo]); ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="funcionarios" aria-label="<?php echo of_t('Situação'); ?>">
            <option value=""><?php echo of_t('Todas as situações'); ?></option>
            <?php foreach ($rotulosStatus as $valor => $rotulo): ?>
                <option value="<?php echo $valor; ?>"><?php echo of_t($rotulo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="funcionarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo of_t('Nome'); ?></th>
                        <th><?php echo of_t('E-mail'); ?></th>
                        <th><?php echo of_t('Cargo'); ?></th>
                        <th><?php echo of_t('Situação'); ?></th>
                        <th><?php echo of_t('Painel'); ?></th>
                        <th title="<?php echo of_t('Regra que a catraca aplicará quando a integração estiver ativa.'); ?>"><?php echo of_t('Catraca (prevista)'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($funcionarios as $f): ?>
                        <tr data-status="<?php echo bo_val($f['status']); ?>" data-type="<?php echo bo_val($f['cargo']); ?>"
                            data-search="<?php echo bo_val(mb_strtolower($f['id'] . ' ' . $f['nome'] . ' ' . $f['email'], 'UTF-8')); ?>">
                            <td>#<?php echo str_pad((string) $f['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo bo_val($f['nome']); ?></td>
                            <td><?php echo bo_val($f['email']); ?></td>
                            <td><?php echo of_t($rotulosCargo[$f['tipo']] ?? 'Desconhecido'); ?></td>
                            <td><?php echo bo_badge($f['status'] === 'ativo', 'Ativo', $rotulosStatus[$f['status']] ?? 'Inativo'); ?></td>
                            <td>
                                <?php echo of_t($f['painel'] ? 'Sim' : 'Não'); ?>
                                <?php if ($f['painel'] && !$f['emailVerificado']): ?>
                                    <br><small class="text-muted"><?php echo of_t('E-mail não confirmado'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?php echo bo_badge($f['catraca']['liberado'], $motivosCatraca['ok'], $motivosCatraca[$f['catraca']['motivo']] ?? 'Bloqueada'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php if (!$f['gerenciavel']): ?>
                                        <span class="btn-bo-icon" title="<?php echo of_t($f['id'] === $idOperadorTela ? 'Sua própria conta não é alterada nesta tela.' : 'Somente um Diretor pode alterar esta conta.'); ?>"><i class="bi bi-lock"></i></span>
                                    <?php elseif ($podeGravar): ?>
                                        <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalFuncionarioEditar<?php echo (int) $f['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <button type="button" class="btn-bo-icon" title="<?php echo of_t($f['status'] === 'ativo' ? 'Inativar' : 'Ativar'); ?>" data-bs-toggle="modal" data-bs-target="#modalFuncionarioStatus<?php echo (int) $f['id']; ?>">
                                            <i class="bi <?php echo $f['status'] === 'ativo' ? 'bi-pause-circle' : 'bi-play-circle'; ?>"></i>
                                        </button>
                                        <?php if ($f['painel'] && $f['status'] === 'ativo'): ?>
                                            <button type="button" class="btn-bo-icon" title="<?php echo of_t('Enviar link de acesso'); ?>" data-bs-toggle="modal" data-bs-target="#modalFuncionarioAcesso<?php echo (int) $f['id']; ?>">
                                                <i class="bi bi-envelope"></i>
                                            </button>
                                        <?php endif; ?>
                                        <?php if ($f['cargo'] === 'professor' && $f['cadastros'] === 0): ?>
                                            <button type="button" class="btn-bo-icon" title="<?php echo of_t('Vincular cadastro profissional'); ?>" data-bs-toggle="modal" data-bs-target="#modalFuncionarioVincular<?php echo (int) $f['id']; ?>">
                                                <i class="bi bi-link-45deg"></i>
                                            </button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="<?php echo $funcionarios ? 'display:none' : ''; ?>">
                        <td colspan="8"><?php echo of_t('Nenhum funcionário encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <?php if ($podeGravar): ?>
        <?php bo_modal_funcionario(null, $cadastrosProfissionaisLivres, 'funcionarios', $funcionariosCargos); ?>
        <?php foreach ($funcionarios as $f): ?>
            <?php if (!$f['gerenciavel']) continue; ?>
            <?php bo_modal_funcionario($f, $cadastrosProfissionaisLivres, 'funcionarios', $funcionariosCargos); ?>
            <?php bo_modal_funcionario_status($f, 'funcionarios'); ?>
            <?php if ($f['painel'] && $f['status'] === 'ativo'): ?>
                <?php bo_modal_funcionario_acesso($f, 'funcionarios'); ?>
            <?php endif; ?>
            <?php if ($f['cargo'] === 'professor' && $f['cadastros'] === 0): ?>
                <?php bo_modal_funcionario_vincular($f, $cadastrosProfissionaisLivres, 'funcionarios'); ?>
            <?php endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
