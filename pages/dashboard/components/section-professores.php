<?php
/**
 * components/section-professores.php
 * Tela "Professores" do admin: quais professores recebem agendamentos dos
 * alunos. A chave da seção continua "profissionais" (rotas e menu antigos).
 * Depende de includes/db-data.php:
 *   $professores, $professoresMigracao, $professoresPendencias, $professoresErro,
 *   $funcionariosMigracao, $modalidadesOptions
 * Os botões só aparecem quando a operação é permitida, mas a permissão real
 * é sempre conferida no servidor (funcionalidades/profissionais.php).
 */
$motivosAgenda = [
    'ok' => 'Aparece na agenda dos alunos',
    'conta_inativa' => 'Fora da agenda: conta inativa',
    'cadastro_inativo' => 'Fora da agenda: cadastro profissional inativo',
    'indisponivel' => 'Fora da agenda: indisponível',
];
$rotulosConta = ['ativo' => 'Ativa', 'inativo' => 'Inativa', 'bloqueado' => 'Bloqueada'];
$podeEditarProfessores = !$professoresErro && $funcionariosMigracao['ok'];
$podeAlterarDisponibilidade = $podeEditarProfessores && $professoresMigracao;
$professoresNaAgenda = count(array_filter($professores, static fn(array $p): bool => $p['agenda']['recebe']));
$professoresIndisponiveis = count(array_filter($professores, static fn(array $p): bool => !$p['disponivel']));
?>
<!-- ===== ADMIN · Professores (disponibilidade para agendamentos; cadastro em Funcionários) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="profissionais">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Professores'); ?></h1>
            <p><?php echo of_t('Defina quais professores podem receber agendamentos dos alunos. O cadastro e os dados pessoais ficam na tela Funcionários.'); ?></p>
        </div>
        <a class="btn-bo-outline" href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=funcionarios">
            <i class="bi bi-person-vcard"></i> <?php echo of_t('Cadastrar em Funcionários'); ?>
        </a>
    </div>

    <?php if ($professoresErro): ?>
        <div class="bo-notice is-fixo" style="border-color:#dc3545;">
            <i class="bi bi-exclamation-triangle"></i>
            <div><strong><?php echo of_t('Não foi possível carregar os professores.'); ?></strong><span><?php echo of_t('Nenhuma alteração pode ser feita até o problema ser resolvido.'); ?></span></div>
        </div>
    <?php elseif (!$professoresMigracao): ?>
        <div class="bo-notice is-fixo" style="border-color:#dc3545;">
            <i class="bi bi-database-exclamation"></i>
            <div>
                <strong><?php echo of_t('Migração de disponibilidade pendente'); ?></strong>
                <span><?php echo of_t('Este banco ainda não tem a coluna de disponibilidade. Até a migração ser aplicada (database/migrate-professores.php), todo professor com conta e cadastro profissional ativos continua na agenda, como antes, e a disponibilidade não pode ser alterada.'); ?></span>
            </div>
        </div>
    <?php endif; ?>
    <div class="bo-notice is-fixo">
        <i class="bi bi-calendar2-check"></i>
        <div>
            <strong><?php echo of_t('Disponibilidade não é a situação da conta'); ?></strong>
            <span><?php echo of_t('Indisponível tira o professor só dos novos agendamentos dos alunos: a conta, o cargo, a catraca e os agendamentos já marcados continuam como estão. Para aparecer na agenda, o professor também precisa de conta ativa e cadastro profissional ativo.'); ?></span>
        </div>
    </div>
    <?php if (!$professoresErro && ($professoresPendencias['semCadastro'] > 0 || $professoresPendencias['semConta'] > 0)): ?>
        <div class="bo-notice is-fixo">
            <i class="bi bi-link-45deg"></i>
            <div>
                <strong><?php echo of_t('Cadastros fora desta lista'); ?></strong>
                <?php if ($professoresPendencias['semCadastro'] > 0): ?>
                    <span><?php echo of_t('Professores sem cadastro profissional: {n}. Vincule o cadastro na tela Funcionários para que apareçam aqui.', ['{n}' => (string) $professoresPendencias['semCadastro']]); ?></span>
                <?php endif; ?>
                <?php if ($professoresPendencias['semConta'] > 0): ?>
                    <span><?php echo of_t('Cadastros profissionais antigos sem conta: {n}. Eles não aparecem aqui nem na agenda; ligue cada um a um professor em Funcionários ("Vincular cadastro existente").', ['{n}' => (string) $professoresPendencias['semConta']]); ?></span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Professores'); ?></div>
                <div class="bo-card-value"><?php echo count($professores); ?></div>
                <div class="bo-card-sub"><?php echo of_t('Com cadastro profissional vinculado'); ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Recebem agendamentos'); ?></div>
                <div class="bo-card-value"><?php echo $professoresNaAgenda; ?></div>
                <div class="bo-card-sub"><?php echo of_t('Disponíveis, com conta e cadastro ativos'); ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Indisponíveis'); ?></div>
                <div class="bo-card-value"><?php echo $professoresIndisponiveis; ?></div>
                <div class="bo-card-sub"><?php echo of_t('Fora de novos agendamentos'); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:300px" placeholder="<?php echo of_t('Buscar por ID, nome, especialidade ou modalidade'); ?>"
            data-bo-filter="search" data-bo-target="profissionais" aria-label="<?php echo of_t('Buscar por ID, nome, especialidade ou modalidade'); ?>">
        <!-- "type" do filtro genérico guarda a disponibilidade; "status", a situação da conta. -->
        <select class="form-select" style="max-width:220px" data-bo-filter="type" data-bo-target="profissionais" aria-label="<?php echo of_t('Disponibilidade'); ?>">
            <option value=""><?php echo of_t('Toda disponibilidade'); ?></option>
            <option value="disponivel"><?php echo of_t('Disponível'); ?></option>
            <option value="indisponivel"><?php echo of_t('Indisponível'); ?></option>
        </select>
        <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="profissionais" aria-label="<?php echo of_t('Conta'); ?>">
            <option value=""><?php echo of_t('Todas as contas'); ?></option>
            <?php foreach (['ativo' => 'Conta ativa', 'inativo' => 'Conta inativa', 'bloqueado' => 'Conta bloqueada'] as $valor => $rotulo): ?>
                <option value="<?php echo $valor; ?>"><?php echo of_t($rotulo); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="profissionais">
                <thead>
                    <tr>
                        <th><?php echo of_t('Foto'); ?></th>
                        <th>ID</th>
                        <th><?php echo of_t('Nome'); ?></th>
                        <th><?php echo of_t('Disponibilidade para agendamentos'); ?></th>
                        <th><?php echo of_t('Conta'); ?></th>
                        <th><?php echo of_t('Especialidade e modalidades'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($professores as $p): ?>
                        <tr data-status="<?php echo bo_val($p['conta']); ?>" data-type="<?php echo $p['disponivel'] ? 'disponivel' : 'indisponivel'; ?>"
                            data-search="<?php echo bo_val(mb_strtolower($p['idUsuario'] . ' #' . str_pad((string) $p['idUsuario'], 4, '0', STR_PAD_LEFT) . ' ' . $p['nome'] . ' ' . $p['especialidade'] . ' ' . $p['modalidades'], 'UTF-8')); ?>">
                            <td>
                                <div class="bo-thumb">
                                    <?php if ($p['foto'] !== ''): ?>
                                        <img src="<?php echo bo_val($p['foto']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="bi bi-person"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>#<?php echo str_pad((string) $p['idUsuario'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo bo_val($p['nome']); ?></td>
                            <td>
                                <div class="bo-disponibilidade">
                                    <?php if ($podeAlterarDisponibilidade): ?>
                                        <form method="POST" action="<?php echo bo_form_action('profissionais.php'); ?>">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'profissionais'); ?>
                                            <?php echo bo_hidden('acao', 'disponibilidade'); ?>
                                            <?php echo bo_hidden('id', $p['id']); ?>
                                            <?php echo bo_hidden('disponivel', $p['disponivel'] ? '0' : '1'); ?>
                                            <button type="submit" class="bo-toggle-input" role="switch" aria-checked="<?php echo $p['disponivel'] ? 'true' : 'false'; ?>"
                                                title="<?php echo of_t($p['disponivel'] ? 'Tornar indisponível para novos agendamentos' : 'Tornar disponível para novos agendamentos'); ?>"
                                                aria-label="<?php echo of_t('Disponível para agendamentos: {nome}', ['{nome}' => $p['nome']]); ?>"></button>
                                        </form>
                                    <?php endif; ?>
                                    <div>
                                        <strong><?php echo of_t($p['disponivel'] ? 'Disponível' : 'Indisponível'); ?></strong>
                                        <small class="<?php echo $p['agenda']['recebe'] ? 'text-success' : 'text-muted'; ?>"><?php echo of_t($motivosAgenda[$p['agenda']['motivo']]); ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php echo bo_badge($p['conta'] === 'ativo', 'Ativa', $rotulosConta[$p['conta']] ?? 'Inativa'); ?>
                                <?php if ($p['cadastro'] !== 'ativo'): ?>
                                    <br><small class="text-muted"><?php echo of_t('Cadastro profissional inativo'); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo $p['especialidade'] !== '' ? bo_val($p['especialidade']) : '—'; ?>
                                <?php if ($p['modalidades'] !== ''): ?>
                                    <br><small class="text-muted"><?php echo bo_val($p['modalidades']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php if ($podeEditarProfessores): ?>
                                        <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar dados profissionais'); ?>" data-bs-toggle="modal" data-bs-target="#modalProfissionalEditar<?php echo $p['id']; ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="<?php echo $professores ? 'display:none' : ''; ?>">
                        <td colspan="7"><?php echo of_t($professores
                            ? 'Nenhum professor encontrado para os filtros selecionados.'
                            : 'Nenhum professor cadastrado. Cadastre um funcionário com o cargo Professor na tela Funcionários.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php if ($professores && $professoresNaAgenda === 0): ?>
        <p class="bo-settings-hint"><i class="bi bi-info-circle"></i> <?php echo of_t('Nenhum professor recebe agendamentos no momento: os alunos não verão opções na agenda.'); ?></p>
    <?php endif; ?>

    <?php if ($podeEditarProfessores): ?>
        <?php foreach ($professores as $p): ?>
            <?php bo_modal_professor($p, 'profissionais', $modalidadesOptions); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
