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
            <h1><?php echo of_t('Perfil'); ?></h1>
            <p><?php echo of_t('Seus dados cadastrais na ONE FIT.'); ?></p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("perfilEdit","Editar perfil", <?php echo bo_json($alunoPerfil); ?>)'>
            <i class="bi bi-pencil"></i> <?php echo of_t('Editar'); ?>
        </button>
    </div>

    <!-- Bloco 1: dados cadastrais -->
    <div class="bo-profile-block">
        <div class="bo-thumb mb-3" style="width:72px;height:72px;font-size:28px;">
            <i class="bi bi-person"></i>
        </div>
        <div class="bo-profile-row"><span><?php echo of_t('E-mail'); ?></span><span><?php echo $alunoPerfil['email']; ?></span></div>
        <div class="bo-profile-row">
            <span><?php echo of_t('Plano'); ?></span>
            <span>
                <?php echo $alunoPerfil['plano']; ?>
                <button type="button" class="btn-bo-outline ms-2" style="padding:4px 10px;font-size:12px;"
                    onclick='boOpenForm("planoAlterar","Alterar plano", {plano: "<?php echo $alunoPerfil['plano']; ?>"})'><?php echo of_t('Alterar'); ?></button>
            </span>
        </div>
        <div class="bo-profile-row"><span><?php echo of_t('Status'); ?></span><span><?php echo bo_badge($alunoPerfil['status'] === onefitTraduzir('Ativo')); ?></span></div>
        <div class="bo-profile-row"><span><?php echo of_t('Documento'); ?></span><span><?php echo $alunoPerfil['documento']; ?></span></div>
        <div class="bo-profile-row"><span><?php echo of_t('Telefone'); ?></span><span><?php echo $alunoPerfil['telefone']; ?></span></div>
        <div class="bo-profile-row"><span><?php echo of_t('Data de cadastro'); ?></span><span><?php echo $alunoPerfil['dataCadastro']; ?></span></div>
        <div class="bo-profile-row"><span><?php echo of_t('Data de nascimento'); ?></span><span><?php echo $alunoPerfil['nascimento']; ?></span></div>
    </div>

    <!-- Bloco 2: avaliação física + cálculo de IMC (ver backoffice.js > boCalcularIMC) -->
    <div class="bo-profile-block">
        <div class="bo-section-heading"><?php echo of_t('Avaliação física'); ?></div>
        <div class="row g-3 mb-3">
            <div class="col-6 col-md-3">
                <label class="form-label"><?php echo of_t('Altura (m)'); ?></label>
                <input type="number" step="0.01" class="form-control" id="imcAltura" value="<?php echo $alunoPerfil['altura']; ?>">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label"><?php echo of_t('Peso (kg)'); ?></label>
                <input type="number" step="0.1" class="form-control" id="imcPeso" value="<?php echo $alunoPerfil['peso']; ?>">
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label"><?php echo of_t('Objetivo'); ?></label>
                <input type="text" class="form-control" id="imcObjetivo" value="<?php echo $alunoPerfil['objetivo']; ?>">
            </div>
        </div>
        <div class="bo-imc-box mb-3">
            <button type="button" class="btn-bo-outline" onclick="boCalcularIMC()">
                <i class="bi bi-calculator"></i> <?php echo of_t('Calcular IMC'); ?>
            </button>
            <div>
                <div class="bo-card-label" style="margin-bottom:2px;"><?php echo of_t('Status de IMC'); ?></div>
                <div class="bo-card-value" id="imcResultado" style="font-size:18px;">—</div>
            </div>
        </div>
        <div class="bo-table-actions">
            <button type="button" class="btn-bo-outline"><?php echo of_t('Cancelar'); ?></button>
            <button type="button" class="btn-bo-gold" onclick="boToast('Alterações salvas.')"><?php echo of_t('Salvar'); ?></button>
        </div>
    </div>
</section>

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
            <p><?php echo of_t('Saldo disponível e histórico de movimentações.'); ?></p>
        </div>
        <button type="button" class="btn-bo-gold" onclick='boOpenForm("utilizarCashback","Usar cashback", {})'>
            <i class="bi bi-wallet2"></i> <?php echo of_t('Usar Cashback'); ?>
        </button>
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
<?php $perfilCompras = 'aluno'; $comprasPedidos = $alunoPedidos ?? []; $comprasHistorico = $alunoPedidosHistorico ?? []; require __DIR__ . '/section-compras.php'; ?>

<!-- ===== ALUNO · Treino (ficha de exercícios) ===== -->
<?php require __DIR__ . '/section-treino.php'; ?>

<!-- ===== ALUNO · Minha agenda (horários de avaliação física disponíveis) ===== -->
<section class="bo-content-section" data-perfil="aluno" data-section="agenda">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Minha agenda'); ?></h1>
            <p><?php echo of_t('Datas disponíveis e agendadas com seus profissionais.'); ?></p>
        </div>
    </div>

    <div class="bo-section-heading"><?php echo of_t('Agenda de avaliação física'); ?></div>
    <?php foreach ($alunoAgendaDisponiveis as $d): ?>
        <div class="bo-agenda-card disponivel">
            <div>
                <div class="bo-agenda-title"><?php echo $d['tipo']; ?></div>
                <div class="bo-agenda-sub"><?php echo $d['data']; ?></div>
            </div>
            <button type="button" class="btn-bo-gold" style="padding:8px 16px;"
                onclick='boOpenForm("agendaAgendar","Confirmar agendamento", {data: "<?php echo $d['data']; ?>", modalidade: "<?php echo $d['tipo']; ?>"})'>
                <?php echo of_t('Agendar'); ?>
            </button>
        </div>
    <?php endforeach; ?>
</section>
