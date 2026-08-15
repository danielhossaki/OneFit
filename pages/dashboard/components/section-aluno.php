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
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Usar cashback", {})'>
            <i class="bi bi-wallet2"></i> Usar Cashback
        </button>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo de cashback</div>
                <div class="bo-card-value">R$ 125,40</div>
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
<section class="bo-content-section" data-perfil="aluno" data-section="compras">
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
                <div class="bo-card-value"><?php echo count($alunoPedidos) + count($alunoPedidosHistorico); ?></div>
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
                    <?php foreach ($alunoPedidos as $ped): ?>
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
                    <?php foreach ($alunoPedidosHistorico as $ped): ?>
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

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="treino">
    <div class="bo-page-title">
        <div>
            <h1>Treino</h1>
            <p>Monte e acompanhe sua ficha de treino.</p>
        </div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bo-action="clear-table" data-bo-target-table="alunoTreino">
                <i class="bi bi-eraser"></i> Limpar Treino
            </button>
            <button type="button" class="btn-bo-gold" onclick='boOpenForm("treinoExercicio","Adicionar exercício", {})'>
                <i class="bi bi-plus-lg"></i> Adicionar Treino
            </button>
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="alunoTreino">
                <thead>
                    <tr>
                        <th>Exercício</th>
                        <th>Séries</th>
                        <th>Repetições</th>
                        <th>Carga</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($alunoTreino as $t): ?>
                        <tr>
                            <td><?php echo $t['nome']; ?></td>
                            <td><?php echo $t['series']; ?></td>
                            <td><?php echo $t['repeticoes']; ?></td>
                            <td><?php echo $t['carga']; ?> kg</td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar"
                                        onclick='boOpenForm("treinoExercicio","Editar exercício", <?php echo bo_json($t); ?>)'>
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button" class="btn-bo-icon danger" title="Remover"
                                        data-bo-action="delete" data-bo-name="<?php echo htmlspecialchars($t['nome']); ?>">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="5">Nenhum exercício cadastrado.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== ALUNO · Minha agenda (horários de avaliação física disponíveis) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1>Minha agenda</h1>
            <p>Datas disponíveis e agendadas com seus profissionais.</p>
        </div>
    </div>

    <div class="bo-section-heading">Agenda de avaliação física</div>
    <?php foreach ($alunoAgendaDisponiveis as $d): ?>
        <div class="bo-agenda-card disponivel">
            <div>
                <div class="bo-agenda-title"><?php echo $d['tipo']; ?></div>
                <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
            </div>
            <button type="button" class="btn-bo-gold" style="padding:8px 16px;"
                onclick='boOpenForm("agendaAgendar","Confirmar agendamento", {data: "<?php echo $d['data']; ?>", modalidade: "<?php echo $d['tipo']; ?>"})'>
                Agendar
            </button>
        </div>
    <?php endforeach; ?>
</section>
