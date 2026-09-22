<?php

/**
 * components/section-aluno.php
 * Telas do perfil Aluno (exceto "Perfil" em si, que é a tela genérica
 * compartilhada por todos os papéis em components/student-profile.php).
 * Depende de includes/db-data.php:
 *   $alunoHistorico, $alunoCashbackHistorico, $alunoPedidos,
 *   $alunoPedidosHistorico, $alunoTreino, $alunoAgenda*
 */
?>

<!-- ===== ALUNO · Histórico (pagamentos de mensalidade) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="historico">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Histórico'); ?></h1>
            <p><?php echo of_t('Histórico de pagamentos e movimentações.'); ?></p>
        </div>
        <!-- Abre o modal fixo de pagamento (components/modal-pagar-plano.php) -->
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagarPlano">
            <i class="bi bi-credit-card"></i> <?php echo of_t('Pagar Plano'); ?>
        </button>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th><?php echo of_t('Data/hora'); ?></th>
                        <th><?php echo of_t('Descrição'); ?></th>
                        <th><?php echo of_t('Tipo'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('Cashback'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['descricao']; ?></td>
                            <td><?php echo $h['tipo']; ?></td>
                            <td><?php echo $h['status']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                            <td><?php echo bo_money($h['cashback']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Cashback (saldo e extrato de crédito/débito) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="cashback">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Cashback'); ?></h1>
            <p><?php echo of_t('Saldo disponível e histórico de movimentações.'); ?></p>
        </div>
        <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/marketplace/marketplace.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-bo-gold">
            <i class="bi bi-wallet2"></i> <?php echo of_t('Usar Cashback'); ?>
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Saldo de cashback'); ?></div>
                <div class="bo-card-value"><?php echo bo_money($alunoCashbackSaldo); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th><?php echo of_t('Data'); ?></th>
                        <th><?php echo of_t('Tipo'); ?></th>
                        <th><?php echo of_t('Descrição'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoCashbackHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['tipo'] === 'credito' ? onefitTraduzir('Crédito') : onefitTraduzir('Débito'); ?></td>
                            <td><?php echo $h['descricao']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Minhas compras (pedidos em andamento e histórico) ===== -->
<?php $perfilCompras = 'aluno';
$comprasPedidos = $alunoPedidos ?? [];
$comprasHistorico = $alunoPedidosHistorico ?? [];
require __DIR__ . '/section-compras.php'; ?>

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<?php require __DIR__ . '/section-treino.php'; ?>

<!-- ===== ALUNO · Minha agenda (calendário + horários disponíveis com profissionais) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Minha agenda'); ?></h1>
            <p><?php echo of_t('Clique num dia com horários disponíveis para agendar com um profissional.'); ?></p>
        </div>
    </div>

    <?php
    $boAgendaMesTs = strtotime($alunoAgendaMes . '-01');
    $boAgendaMesAnterior = date('Y-m', strtotime('-1 month', $boAgendaMesTs));
    $boAgendaMesSeguinte = date('Y-m', strtotime('+1 month', $boAgendaMesTs));
    $boAgendaLinkBase = BASE_URL . 'pages/dashboard/dashboard.php?section=agenda';
    $boAgendaPrimeiroDiaSemana = (int) date('w', $boAgendaMesTs); // 0 (domingo) .. 6 (sábado)
    $boAgendaTotalDias = (int) date('t', $boAgendaMesTs);
    $boMesesPtBr = [
        1 => 'janeiro',
        2 => 'fevereiro',
        3 => 'março',
        4 => 'abril',
        5 => 'maio',
        6 => 'junho',
        7 => 'julho',
        8 => 'agosto',
        9 => 'setembro',
        10 => 'outubro',
        11 => 'novembro',
        12 => 'dezembro'
    ];
    $boAgendaMesLabel = onefitTraduzir('{mes} de {ano}', ['{mes}' => onefitTraduzir($boMesesPtBr[(int) date('n', $boAgendaMesTs)]), '{ano}' => date('Y', $boAgendaMesTs)]);
    $boTiposAgendamento = ['aula' => 'Aula', 'personal' => 'Personal', 'avaliacao' => 'Avaliação física', 'consulta' => 'Consulta', 'reuniao' => 'Reunião', 'outro' => 'Agendamento'];
    ?>
    <div class="bo-calendar">
        <div class="bo-calendar-nav">
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesAnterior, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="<?php echo of_t('Mês anterior'); ?>"><i class="bi bi-chevron-left"></i></a>
            <span class="bo-calendar-mes-atual"><?php echo htmlspecialchars($boAgendaMesLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesSeguinte, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="<?php echo of_t('Próximo mês'); ?>"><i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="bo-calendar-grid">
            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $boDiaSemanaLabel): ?>
                <div class="bo-calendar-weekday"><?php echo of_t($boDiaSemanaLabel); ?></div>
            <?php endforeach; ?>

            <?php for ($boEspaco = 0; $boEspaco < $boAgendaPrimeiroDiaSemana; $boEspaco++): ?>
                <div class="bo-calendar-day is-outro-mes"></div>
            <?php endfor; ?>

            <?php for ($boDiaNum = 1; $boDiaNum <= $boAgendaTotalDias; $boDiaNum++): ?>
                <?php
                $boDataStr = sprintf('%s-%02d', $alunoAgendaMes, $boDiaNum);
                $boTemVaga = isset($alunoAgendaDiasDisponiveis[$boDataStr]);
                $boEventosDoDia = $alunoAgendaEventosPorDia[$boDataStr] ?? [];
                $boSelecionado = $alunoAgendaDiaSelecionado === $boDataStr;
                $boClasses = 'bo-calendar-day' . ($boDataStr < bo_agenda_now()->format('Y-m-d') ? ' is-passado' : '') . ($boTemVaga ? ' is-disponivel' : '') . ($boSelecionado ? ' is-selecionado' : '');
                $boHref = $boAgendaLinkBase . '&mes=' . $alunoAgendaMes . ($boTemVaga ? '&dia=' . $boDataStr : '');
                $boTag = $boTemVaga ? 'a' : 'div';
                ?>
                <<?php echo $boTag; ?><?php if ($boTemVaga): ?> href="<?php echo htmlspecialchars($boHref, ENT_QUOTES, 'UTF-8'); ?>" <?php endif; ?> class="<?php echo $boClasses; ?>">
                    <div class="bo-calendar-day-topo">
                        <span class="bo-calendar-day-num"><?php echo str_pad((string) $boDiaNum, 2, '0', STR_PAD_LEFT); ?></span>
                        <?php if ($boTemVaga): ?><span class="bo-calendar-badge-livre"><?php echo of_t('Livre'); ?></span><?php endif; ?>
                        <?php if ($boEventosDoDia): ?><span class="bo-calendar-badge-evento"><?php echo of_t(count($boEventosDoDia) === 1 ? '{n} evento' : '{n} eventos', ['{n}' => count($boEventosDoDia)]); ?></span><?php endif; ?>
                    </div>
                    <?php foreach (array_slice($boEventosDoDia, 0, 2) as $boEvento): ?>
                        <div class="bo-calendar-evento-preview">
                            <strong><?php echo htmlspecialchars($boEvento['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo $boEvento['hora']; ?> · <?php echo htmlspecialchars(mb_strtoupper(onefitTraduzir($boTiposAgendamento[$boEvento['tipo']] ?? $boEvento['tipo']), 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </<?php echo $boTag; ?>>
            <?php endfor; ?>
        </div>
        <p class="bo-calendar-legenda"><?php echo of_t('Eventos incluídos automaticamente quando você agenda aulas ou avaliações.'); ?></p>
    </div>

    <?php if ($alunoAgendaDiaSelecionado): ?>
        <div class="bo-section-heading"><?php echo of_t('Horários disponíveis em'); ?> <?php echo onefitData('d/m/Y', strtotime($alunoAgendaDiaSelecionado)); ?></div>
        <?php if (!$alunoAgendaSlots): ?>
            <p><?php echo of_t('Nenhum horário disponível nesse dia.'); ?></p>
        <?php else: ?>
            <form class="bo-calendar bo-agenda-booking bo-filters" method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/agenda.php">
                <p><?php echo of_t('Horários de demonstração com profissionais cadastrados. A confirmação será salva na sua agenda.'); ?></p>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="secao" value="agenda">
                <input type="hidden" name="data_evento" value="<?php echo htmlspecialchars($alunoAgendaDiaSelecionado, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="bo-agenda-fields">
                    <label><?php echo of_t('Tipo de agendamento'); ?><select class="form-select" data-agenda-type required></select></label>
                    <label><?php echo of_t('Profissional'); ?><select class="form-select" data-agenda-professional required></select></label>
                </div>
                <fieldset>
                    <legend><?php echo of_t('Horários disponíveis'); ?></legend>
                    <div data-agenda-times class="bo-agenda-times"></div>
                </fieldset>
                <p data-agenda-empty hidden><?php echo of_t('Nenhum horário disponível para esta seleção.'); ?></p>
                <a class="btn-bo-outline" href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $alunoAgendaMes, ENT_QUOTES, 'UTF-8'); ?>"><?php echo of_t('Cancelar'); ?></a>
                <button class="btn-bo-gold" type="submit" disabled><?php echo of_t('Confirmar agendamento'); ?></button>
                <script type="application/json" data-agenda-slots>
                    <?php echo json_encode($alunoAgendaSlots, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
                </script>
                <noscript><?php echo of_t('Ative o JavaScript para selecionar o atendimento e o horário.'); ?></noscript>
            </form>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bo-section-heading"><?php echo of_t('Meus agendamentos'); ?></div>
    <?php if (!$alunoAgendaMeusAgendamentos): ?>
        <p><?php echo of_t('Você ainda não tem agendamentos confirmados.'); ?></p>
    <?php endif; ?>
    <?php foreach ($alunoAgendaMeusAgendamentos as $boAg): ?>
        <div class="bo-agenda-card">
            <div>
                <div class="bo-agenda-title"><?php $tituloAgendamento = $boAg['titulo'] ?: onefitTraduzir($boTiposAgendamento[$boAg['tipo']] ?? ucfirst($boAg['tipo'])); echo $boAg['profissional'] ? of_t('{titulo} com {profissional}', ['{titulo}' => $tituloAgendamento, '{profissional}' => $boAg['profissional']]) : htmlspecialchars($tituloAgendamento, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="bo-agenda-sub"><?php echo of_t('{data} às {hora}', ['{data}' => onefitData('d/m/Y', strtotime($boAg['data_evento'])), '{hora}' => substr($boAg['hora_inicio'], 0, 5)]); ?></div>
            </div>
            <span><?php echo of_t('Status:'); ?> <?php echo of_t(['agendado' => 'Confirmado', 'confirmado' => 'Confirmado', 'cancelado' => 'Cancelado', 'concluido' => 'Concluído', 'faltou' => 'Não compareceu'][$boAg['status']] ?? $boAg['status']); ?></span>
            <?php if (in_array($boAg['status'], ['agendado', 'confirmado'], true)): ?>
                <form data-agenda-cancel method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/agenda.php', ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                    <input type="hidden" name="secao" value="agenda">
                    <input type="hidden" name="acao" value="cancelar">
                    <input type="hidden" name="id_agendamento" value="<?php echo (int) $boAg['id_agendamento']; ?>">
                    <button type="button" data-cancel-open class="btn-bo-outline" style="padding:8px 16px;"><?php echo of_t('Cancelar'); ?></button>
                    <div data-cancel-confirm hidden>
                        <p><?php echo of_t('Tem certeza que deseja cancelar este agendamento?'); ?></p>
                        <button type="button" data-cancel-back class="btn-bo-outline"><?php echo of_t('Voltar'); ?></button>
                        <button type="submit" class="btn-bo-gold"><?php echo of_t('Confirmar cancelamento'); ?></button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</section>

<script src="<?php echo BASE_URL; ?>assets/js/agenda.js" defer></script>