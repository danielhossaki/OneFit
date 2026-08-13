<?php
/**
 * components/section-profissional.php
 * Telas do perfil Profissional (personal trainer, instrutor, nutricionista
 * etc). Depende de includes/mock-data.php:
 *   $profContrato, $profHistorico, $profAlunos, $profAgendados,
 *   $profDisponiveis, $profCashbackHistorico, $profPedidos, $profPedidosHistorico
 */
?>

<!-- ===== PROFISSIONAL · Dashboard (status de contrato e saldo) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="dashboard">
    <div class="bo-page-title">
        <div>
            <h1>Dashboard</h1>
            <p>Resumo do seu contrato e saldo com a ONE FIT.</p>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Status de contrato</div>
                <div class="bo-card-value"><?php echo $profContrato['status']; ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Validade de contrato</div>
                <div class="bo-card-value"><?php echo $profContrato['validade']; ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo de cashback</div>
                <div class="bo-card-value"><?php echo bo_money($profContrato['saldoCashback']); ?></div>
            </div>
        </div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Histórico (valores recebidos por competência) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="historico">
    <div class="bo-page-title">
        <div>
            <h1>Histórico</h1>
            <p>Histórico de competências e valores recebidos.</p>
        </div>
        <button type="button" class="btn-bo-outline" data-bo-export="profHistorico">
            <i class="bi bi-download"></i> Exportar
        </button>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="profHistorico">
                <thead>
                    <tr>
                        <th>Competência</th>
                        <th>Valor</th>
                        <th>Tipo</th>
                        <th>Cashback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['competencia']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                            <td><?php echo ucfirst($h['tipo']); ?></td>
                            <td><?php echo bo_money($h['cashback']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="4">Nenhum registro encontrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Alunos (alunos vinculados a este profissional) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="alunos">
    <div class="bo-page-title">
        <div>
            <h1>Alunos</h1>
            <p>Alunos vinculados aos seus atendimentos.</p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("alunoDoProfissionalForm","Adicionar Aluno", {})'>
            <i class="bi bi-plus-lg"></i> Adicionar Aluno
        </button>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="profAlunos">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Plano</th>
                        <th>Status</th>
                        <th>Valor</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profAlunos as $a): ?>
                        <tr>
                            <td><?php echo $a['nome']; ?></td>
                            <td><?php echo $a['plano']; ?></td>
                            <td><?php echo bo_badge($a['status'] === 'ativo'); ?></td>
                            <td><?php echo bo_money($a['valor']); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar"
                                        onclick='boOpenForm("alunoDoProfissionalForm","Editar aluno", <?php echo bo_json($a); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-bo-icon danger" title="Excluir"
                                        data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($a['nome']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="5">Nenhum aluno vinculado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Agenda (horários agendados e disponíveis) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1>Agenda</h1>
            <p>Horários agendados e disponíveis.</p>
        </div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" onclick='boOpenForm("agendaDisponivel","Novo horário disponível", {})'>
                <i class="bi bi-calendar-plus"></i> Horário Disponível
            </button>
            <button type="button" class="btn-bo-gold" onclick='boOpenForm("agendaAgendar","Agendar horário", {})'>
                <i class="bi bi-plus-lg"></i> Agenda
            </button>
        </div>
    </div>

    <div class="bo-filters">
        <div class="bo-daterange">
            De <input type="date" class="form-control">
            até <input type="date" class="form-control">
        </div>
    </div>

    <div class="bo-section-heading">Agendados</div>
    <?php foreach ($profAgendados as $ag): ?>
        <div class="bo-agenda-card">
            <div>
                <div class="bo-agenda-title"><?php echo $ag['aluno']; ?> · <?php echo $ag['modalidade']; ?></div>
                <div class="bo-agenda-sub"><?php echo $ag['contato']; ?> · <?php echo $ag['data']; ?></div>
            </div>
            <button type="button" class="btn-bo-icon danger" title="Cancelar" data-bo-remove-card>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    <?php endforeach; ?>

    <div class="bo-section-heading">Disponíveis</div>
    <?php foreach ($profDisponiveis as $d): ?>
        <div class="bo-agenda-card disponivel">
            <div>
                <div class="bo-agenda-title"><?php echo $d['modalidade']; ?></div>
                <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
            </div>
            <button type="button" class="btn-bo-icon danger" title="Remover" data-bo-remove-card>
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
    <?php endforeach; ?>
</section>

<!-- ===== PROFISSIONAL · Meu cashback (saldo e extrato de créditos) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="cashback">
    <div class="bo-page-title">
        <div>
            <h1>Meu cashback</h1>
            <p>Saldo disponível e histórico de créditos.</p>
        </div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bo-export="profCashback">
                <i class="bi bi-download"></i> Exportar
            </button>
            <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Utilizar cashback", {})'>
                <i class="bi bi-wallet2"></i> Utilizar Cashback
            </button>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo total</div>
                <div class="bo-card-value"><?php echo bo_money($profContrato['saldoCashback']); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="profCashback">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Descrição</th>
                        <th>Valor</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profCashbackHistorico as $h): ?>
                        <tr>
                            <td><?php echo $h['data']; ?></td>
                            <td><?php echo $h['descricao']; ?></td>
                            <td><?php echo bo_money($h['valor']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="3">Nenhum registro encontrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== PROFISSIONAL · Minhas compras (pedidos em andamento e histórico) ===== -->
<section class="bo-content-section" data-perfil="profissional" data-section="compras">
    <div class="bo-page-title">
        <div>
            <h1>Minhas compras</h1>
            <p>Acompanhamento e histórico dos seus pedidos.</p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Total de pedidos</div>
                <div class="bo-card-value"><?php echo count($profPedidos) + count($profPedidosHistorico); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por produto, ID da transação ou pedido">
        <select class="form-select" style="max-width:200px">
            <option value="">Todos</option>
            <option value="aguardando">Aguardando</option>
            <option value="entregue">Entregue</option>
            <option value="cancelado">Cancelado</option>
            <option value="devolvido">Devolvido</option>
        </select>
    </div>

    <div class="bo-section-heading">Acompanhamento de pedido</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Produto</th>
                        <th>Quantidade</th>
                        <th>Valor</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profPedidos as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td><?php echo $ped['produto']; ?></td>
                            <td><?php echo $ped['quantidade']; ?></td>
                            <td><?php echo bo_money($ped['valor']); ?></td>
                            <td><?php echo $ped['status']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bo-section-heading">Histórico de compra</div>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Data/hora</th>
                        <th>Produto</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profPedidosHistorico as $ped): ?>
                        <tr>
                            <td><?php echo $ped['transacao']; ?></td>
                            <td><?php echo $ped['data']; ?></td>
                            <td><?php echo $ped['produto']; ?></td>
                            <td><?php echo $ped['status']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
