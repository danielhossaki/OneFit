<?php
require_once __DIR__ . '/../includes/agenda-service.php';

$boRecepcaoResultados = $_SESSION['bo_recepcao_agenda_resultados'] ?? [];
$boRecepcaoAluno = $_SESSION['bo_recepcao_agenda_aluno'] ?? null;
$boRecepcaoData = (string) ($_SESSION['bo_recepcao_agenda_data'] ?? '');
$boRecepcaoProfessores = bo_professores_listar($conn);
$boRecepcaoSlots = [];
$boRecepcaoIdAluno = is_array($boRecepcaoAluno) ? (int) ($boRecepcaoAluno['id'] ?? 0) : 0;

if ($boRecepcaoIdAluno > 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', $boRecepcaoData)) {
    $boRecepcaoSlots = bo_agenda_slots($conn, $boRecepcaoData, $boRecepcaoData, $boRecepcaoIdAluno);
}
$boRecepcaoSlotsPorProfessor = [];
foreach ($boRecepcaoSlots as $slot) {
    $boRecepcaoSlotsPorProfessor[(int) $slot['id_profissional']][] = $slot;
}
$boRecepcaoMotivos = [
    'ok' => 'Disponível para agendamento',
    'conta_inativa' => 'Conta inativa',
    'cadastro_inativo' => 'Cadastro profissional inativo',
    'indisponivel' => 'Agenda pausada',
];
?>
<section class="bo-content-section" data-perfil="recepcionista" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Agendamentos'); ?></h1>
            <p><?php echo of_t('Encontre um aluno, confira a disponibilidade da equipe e confirme um horário.'); ?></p>
        </div>
    </div>

    <div class="bo-bloco">
        <div class="bo-bloco-topo">
            <div>
                <h2><?php echo of_t('Buscar aluno'); ?></h2>
                <p><?php echo of_t('A busca considera somente contas de aluno ativas.'); ?></p>
            </div>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda-recepcao.php" class="bo-cashback-cpf-form">
            <?php echo bo_csrf_field(); ?>
            <?php echo bo_hidden('secao', 'agenda'); ?>
            <input type="hidden" name="acao" value="buscar_aluno">
            <label class="visually-hidden" for="boRecepcaoBuscaAluno"><?php echo of_t('Nome, CPF ou ID do aluno'); ?></label>
            <input type="search" class="form-control" id="boRecepcaoBuscaAluno" name="aluno_busca" autocomplete="off"
                placeholder="<?php echo of_t('Nome, CPF ou ID do aluno'); ?>" required>
            <button type="submit" class="btn-bo-outline"><i class="bi bi-search" aria-hidden="true"></i> <?php echo of_t('Buscar'); ?></button>
        </form>

        <?php if ($boRecepcaoResultados): ?>
            <div class="bo-table-wrap mt-3">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead><tr><th><?php echo of_t('Aluno'); ?></th><th><?php echo of_t('Ação'); ?></th></tr></thead>
                        <tbody>
                            <?php foreach ($boRecepcaoResultados as $resultado): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars((string) $resultado['nome'], ENT_QUOTES, 'UTF-8'); ?> <small class="text-secondary">#<?php echo (int) $resultado['id']; ?></small></td>
                                    <td>
                                        <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda-recepcao.php">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'agenda'); ?>
                                            <?php echo bo_hidden('acao', 'selecionar_aluno'); ?>
                                            <?php echo bo_hidden('id_aluno', $resultado['id']); ?>
                                            <button type="submit" class="btn-bo-outline btn-sm"><?php echo of_t('Selecionar'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($boRecepcaoIdAluno > 0): ?>
        <div class="bo-bloco">
            <div class="bo-bloco-topo">
                <div>
                    <h2><?php echo of_t('Aluno selecionado'); ?></h2>
                    <p><strong><?php echo htmlspecialchars((string) ($boRecepcaoAluno['nome'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></strong> <small>#<?php echo $boRecepcaoIdAluno; ?></small></p>
                </div>
                <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda-recepcao.php">
                    <?php echo bo_csrf_field(); ?>
                    <?php echo bo_hidden('secao', 'agenda'); ?>
                    <?php echo bo_hidden('acao', 'limpar_aluno'); ?>
                    <button type="submit" class="btn-bo-outline btn-sm"><?php echo of_t('Trocar aluno'); ?></button>
                </form>
            </div>
            <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda-recepcao.php" class="bo-cashback-cpf-form">
                <?php echo bo_csrf_field(); ?>
                <?php echo bo_hidden('secao', 'agenda'); ?>
                <?php echo bo_hidden('acao', 'horarios'); ?>
                <label for="boRecepcaoData"><?php echo of_t('Data do atendimento'); ?></label>
                <input type="date" class="form-control" id="boRecepcaoData" name="data_evento" value="<?php echo htmlspecialchars($boRecepcaoData, ENT_QUOTES, 'UTF-8'); ?>"
                    min="<?php echo bo_agenda_now()->format('Y-m-d'); ?>" max="<?php echo bo_agenda_now()->modify('+31 days')->format('Y-m-d'); ?>" required>
                <button type="submit" class="btn-bo-outline"><?php echo of_t('Consultar horários'); ?></button>
            </form>
        </div>

        <?php if ($boRecepcaoData !== ''): ?>
            <div class="bo-bloco">
                <div class="bo-bloco-topo">
                    <div>
                        <h2><?php echo of_t('Equipe e disponibilidade'); ?></h2>
                        <p><?php echo of_t('Os horários são revalidados pelo servidor antes da confirmação.'); ?></p>
                    </div>
                </div>
                <div class="bo-recepcao-professores">
                    <?php foreach ($boRecepcaoProfessores as $professor): ?>
                        <?php $boRecepcaoRecebe = !empty($professor['agenda']['recebe']); ?>
                        <div class="bo-recepcao-professor">
                            <strong><?php echo htmlspecialchars((string) $professor['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span class="<?php echo $boRecepcaoRecebe ? 'is-available' : 'is-unavailable'; ?>">
                                <?php echo of_t($boRecepcaoMotivos[$professor['agenda']['motivo']] ?? 'Indisponível'); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($boRecepcaoSlots): ?>
                    <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda-recepcao.php" class="bo-recepcao-booking">
                        <?php echo bo_csrf_field(); ?>
                        <?php echo bo_hidden('secao', 'agenda'); ?>
                        <?php echo bo_hidden('acao', 'agendar'); ?>
                        <label class="form-label" for="boRecepcaoSlot"><?php echo of_t('Horário disponível'); ?></label>
                        <select class="form-select" id="boRecepcaoSlot" name="slot" required>
                            <option value=""><?php echo of_t('Selecione professor e horário'); ?></option>
                            <?php foreach ($boRecepcaoSlotsPorProfessor as $idProfissional => $slots): ?>
                                <optgroup label="<?php echo htmlspecialchars((string) ($slots[0]['profissional'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php foreach ($slots as $slot): ?>
                                        <option value="<?php echo htmlspecialchars((string) $slot['key'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <?php echo htmlspecialchars(substr((string) $slot['hora_inicio'], 0, 5) . ' · ' . (string) ($slot['modalidade_label'] ?? $slot['modalidade']), ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t('Confirmar agendamento'); ?></button>
                    </form>
                <?php else: ?>
                    <p class="bo-empty mt-3"><?php echo of_t('Não há horários disponíveis nessa data para esse aluno. Escolha outra data.'); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</section>
