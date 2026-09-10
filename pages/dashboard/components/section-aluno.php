<?php
/**
 * components/section-aluno.php
 * Telas do perfil Aluno. Depende de includes/mock-data.php:
 *   $alunoPerfil, $alunoHistorico, $alunoCashbackHistorico, $alunoPedidos,
 *   $alunoPedidosHistorico, $alunoTreino, $alunoAgendaDisponiveis
 */
?>

<!-- ===== ALUNO · Perfil (dados cadastrais + avaliação física/IMC) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="perfil">
    <div class="bo-page-title">
        <div>
            <h1>Perfil</h1>
            <p>Seus dados cadastrais na ONE FIT.</p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("perfilEdit","Editar perfil", <?php echo bo_json($alunoPerfil); ?>)'>
            <i class="bi bi-pencil"></i> Editar
        </button>
    </div>

    <!-- Bloco 1: dados cadastrais -->
    <div class="bo-profile-block">
        <div class="bo-thumb mb-3" style="width:72px;height:72px;font-size:28px;">
            <i class="bi bi-person"></i>
        </div>
        <div class="bo-profile-row"><span>E-mail</span><span><?php echo $alunoPerfil['email']; ?></span></div>
        <div class="bo-profile-row">
            <span>Plano</span>
            <span>
                <?php echo $alunoPerfil['plano']; ?>
                <button type="button" class="btn-bo-outline ms-2" style="padding:4px 10px;font-size:12px;"
                    onclick='boOpenForm("planoAlterar","Alterar plano", {plano: "<?php echo $alunoPerfil['plano']; ?>"})'>Alterar</button>
            </span>
        </div>
        <div class="bo-profile-row"><span>Status</span><span><?php echo bo_badge($alunoPerfil['status'] === 'Ativo'); ?></span></div>
        <div class="bo-profile-row"><span>Documento</span><span><?php echo $alunoPerfil['documento']; ?></span></div>
        <div class="bo-profile-row"><span>Telefone</span><span><?php echo $alunoPerfil['telefone']; ?></span></div>
        <div class="bo-profile-row"><span>Data de cadastro</span><span><?php echo $alunoPerfil['dataCadastro']; ?></span></div>
        <div class="bo-profile-row"><span>Data de nascimento</span><span><?php echo $alunoPerfil['nascimento']; ?></span></div>
    </div>

    <!-- Bloco 2: avaliação física + cálculo de IMC (ver backoffice.js > boCalcularIMC) -->
    <div class="bo-profile-block">
        <div class="bo-section-heading">Avaliação física</div>
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label">Altura (m)</label>
                <input type="number" step="0.01" class="form-control" id="imcAltura" value="<?php echo $alunoPerfil['altura']; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label">Peso (kg)</label>
                <input type="number" step="0.1" class="form-control" id="imcPeso" value="<?php echo $alunoPerfil['peso']; ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label">Objetivo</label>
                <input type="text" class="form-control" id="imcObjetivo" value="<?php echo $alunoPerfil['objetivo']; ?>">
            </div>
        </div>
        <div class="bo-imc-box mb-3">
            <button type="button" class="btn-bo-outline" onclick="boCalcularIMC()">
                <i class="bi bi-calculator"></i> Calcular IMC
            </button>
            <div>
                <div class="bo-card-label" style="margin-bottom:2px;">Status de IMC</div>
                <div class="bo-card-value" id="imcResultado" style="font-size:18px;">—</div>
            </div>
        </div>
        <div class="bo-table-actions">
            <button type="button" class="btn-bo-outline">Cancelar</button>
            <button type="button" class="btn-bo-gold" onclick="boToast('Alterações salvas.')">Salvar</button>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Histórico (pagamentos de mensalidade) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="historico">
    <div class="bo-page-title">
        <div>
            <h1>Histórico</h1>
            <p>Histórico de pagamentos e movimentações.</p>
        </div>
        <!-- Abre o modal fixo de pagamento (components/modal-pagar-plano.php) -->
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagarPlano">
            <i class="bi bi-credit-card"></i> Pagar Plano
        </button>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>Data/hora</th>
                        <th>Descrição</th>
                        <th>Tipo</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Cashback</th>
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
            <h1>Cashback</h1>
            <p>Saldo disponível e histórico de movimentações.</p>
        </div>
        <a href="<?php echo htmlspecialchars(BASE_URL . 'pages/marketplace/marketplace.php', ENT_QUOTES, 'UTF-8'); ?>" class="btn-bo-gold">
            <i class="bi bi-wallet2"></i> Usar Cashback
        </a>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo de cashback</div>
                <div class="bo-card-value"><?php echo bo_money($alunoCashbackSaldo); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoCashbackHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
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
<?php $perfilCompras = 'aluno'; $comprasPedidos = $alunoPedidos ?? []; $comprasHistorico = $alunoPedidosHistorico ?? []; require __DIR__ . '/section-compras.php'; ?>

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<?php require __DIR__ . '/section-treino.php'; ?>

<!-- ===== ALUNO · Minha agenda (calendário + horários disponíveis com profissionais) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1>Minha agenda</h1>
            <p>Clique num dia com horários disponíveis para agendar com um profissional.</p>
        </div>
    </div>

    <?php
    $boAgendaMesTs = strtotime($alunoAgendaMes . '-01');
    $boAgendaMesAnterior = date('Y-m', strtotime('-1 month', $boAgendaMesTs));
    $boAgendaMesSeguinte = date('Y-m', strtotime('+1 month', $boAgendaMesTs));
    $boAgendaLinkBase = BASE_URL . 'pages/dashboard/dashboard.php?section=agenda';
    $boAgendaPrimeiroDiaSemana = (int) date('w', $boAgendaMesTs); // 0 (domingo) .. 6 (sábado)
    $boAgendaTotalDias = (int) date('t', $boAgendaMesTs);
    $boMesesPtBr = [1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril', 5 => 'maio', 6 => 'junho',
        7 => 'julho', 8 => 'agosto', 9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'];
    $boAgendaMesLabel = $boMesesPtBr[(int) date('n', $boAgendaMesTs)] . ' de ' . date('Y', $boAgendaMesTs);
    $boTiposAgendamento = ['aula' => 'Aula', 'personal' => 'Personal', 'avaliacao' => 'Avaliação física', 'consulta' => 'Consulta', 'reuniao' => 'Reunião', 'outro' => 'Agendamento'];
    ?>
    <div class="bo-calendar">
        <div class="bo-calendar-nav">
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesAnterior, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="Mês anterior"><i class="bi bi-chevron-left"></i></a>
            <span class="bo-calendar-mes-atual"><?php echo htmlspecialchars($boAgendaMesLabel, ENT_QUOTES, 'UTF-8'); ?></span>
            <a href="<?php echo htmlspecialchars($boAgendaLinkBase . '&mes=' . $boAgendaMesSeguinte, ENT_QUOTES, 'UTF-8'); ?>" class="bo-calendar-nav-btn" aria-label="Próximo mês"><i class="bi bi-chevron-right"></i></a>
        </div>
        <div class="bo-calendar-grid">
            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $boDiaSemanaLabel): ?>
                <div class="bo-calendar-weekday"><?php echo $boDiaSemanaLabel; ?></div>
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
                $boClasses = 'bo-calendar-day' . ($boTemVaga ? ' is-disponivel' : '') . ($boSelecionado ? ' is-selecionado' : '');
                $boHref = $boAgendaLinkBase . '&mes=' . $alunoAgendaMes . ($boTemVaga ? '&dia=' . $boDataStr : '');
                $boTag = $boTemVaga ? 'a' : 'div';
                ?>
                <<?php echo $boTag; ?><?php if ($boTemVaga): ?> href="<?php echo htmlspecialchars($boHref, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?> class="<?php echo $boClasses; ?>">
                    <div class="bo-calendar-day-topo">
                        <span class="bo-calendar-day-num"><?php echo str_pad((string) $boDiaNum, 2, '0', STR_PAD_LEFT); ?></span>
                        <?php if ($boTemVaga): ?><span class="bo-calendar-badge-livre">Livre</span><?php endif; ?>
                        <?php if ($boEventosDoDia): ?><span class="bo-calendar-badge-evento"><?php echo count($boEventosDoDia); ?> evento(s)</span><?php endif; ?>
                    </div>
                    <?php foreach (array_slice($boEventosDoDia, 0, 2) as $boEvento): ?>
                        <div class="bo-calendar-evento-preview">
                            <strong><?php echo htmlspecialchars($boEvento['titulo'], ENT_QUOTES, 'UTF-8'); ?></strong>
                            <span><?php echo $boEvento['hora']; ?> · <?php echo htmlspecialchars(mb_strtoupper($boTiposAgendamento[$boEvento['tipo']] ?? $boEvento['tipo'], 'UTF-8'), ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endforeach; ?>
                </<?php echo $boTag; ?>>
            <?php endfor; ?>
        </div>
        <p class="bo-calendar-legenda">Eventos incluídos automaticamente quando você agenda aulas ou avaliações.</p>
    </div>

    <?php if ($alunoAgendaDiaSelecionado): ?>
        <div class="bo-section-heading">Horários disponíveis em <?php echo date('d/m/Y', strtotime($alunoAgendaDiaSelecionado)); ?></div>
        <?php if (!$alunoAgendaSlots): ?>
            <p>Nenhum horário disponível nesse dia.</p>
        <?php else: ?>
            <div class="bo-slot-list">
                <?php foreach ($alunoAgendaSlots as $boSlot): ?>
                    <div class="bo-slot-card">
                        <div>
                            <div class="bo-agenda-title"><?php echo htmlspecialchars($boSlot['modalidade'], ENT_QUOTES, 'UTF-8'); ?> com <?php echo htmlspecialchars($boSlot['profissional'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="bo-agenda-sub">
                                <?php echo substr($boSlot['hora_inicio'], 0, 5) . ' às ' . substr($boSlot['hora_fim'], 0, 5); ?>
                                <?php if (!empty($boSlot['local'])): ?> · <?php echo htmlspecialchars($boSlot['local'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                                <?php if (!empty($boSlot['especialidade'])): ?> · <?php echo htmlspecialchars($boSlot['especialidade'], ENT_QUOTES, 'UTF-8'); ?><?php endif; ?>
                            </div>
                        </div>
                        <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/agenda.php', ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="secao" value="agenda">
                            <input type="hidden" name="id_disponibilidade" value="<?php echo (int) $boSlot['id_disponibilidade']; ?>">
                            <button type="submit" class="btn-bo-gold" style="padding:8px 16px;">Agendar</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="bo-section-heading">Meus agendamentos</div>
    <?php if (!$alunoAgendaMeusAgendamentos): ?>
        <p>Você ainda não tem agendamentos confirmados.</p>
    <?php endif; ?>
    <?php foreach ($alunoAgendaMeusAgendamentos as $boAg): ?>
        <div class="bo-agenda-card">
            <div>
                <div class="bo-agenda-title"><?php echo htmlspecialchars($boTiposAgendamento[$boAg['tipo']] ?? ucfirst($boAg['tipo']), ENT_QUOTES, 'UTF-8'); ?><?php echo $boAg['profissional'] ? ' com ' . htmlspecialchars($boAg['profissional'], ENT_QUOTES, 'UTF-8') : ''; ?></div>
                <div class="bo-agenda-sub"><?php echo date('d/m/Y', strtotime($boAg['data_evento'])) . ' às ' . substr($boAg['hora_inicio'], 0, 5); ?></div>
            </div>
            <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/agenda.php', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="secao" value="agenda">
                <input type="hidden" name="acao" value="cancelar">
                <input type="hidden" name="id_agendamento" value="<?php echo (int) $boAg['id_agendamento']; ?>">
                <button type="submit" class="btn-bo-outline" style="padding:8px 16px;">Cancelar</button>
            </form>
        </div>
    <?php endforeach; ?>
</section>
