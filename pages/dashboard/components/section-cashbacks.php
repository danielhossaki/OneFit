<?php
/**
 * components/section-cashbacks.php
 * Tela "Cashbacks": o cashback de todos os clientes (não o saldo de quem está
 * logado). O admin gerencia; a recepção e o vendedor só consultam.
 *   $boPerfilSecao     painel que exibe a tela (data-perfil)
 *   $boSomenteLeitura  sem lançamento, distribuição em massa, exclusão nem
 *                      modais (padrão). A rota funcionalidades/cashbacks.php
 *                      continua só do admin.
 * Indicadores calculados em bo_carregar_cashbacks() (includes/db-data.php),
 * sempre sem os lançamentos cancelados:
 *   concedido              créditos de compras, mensalidades e lançamentos do admin;
 *   devoluções creditadas  valor de pedidos devolvidos lançado como saldo;
 *   utilizado              débitos do checkout ("Uso de cashback no pedido #N");
 *   saldo disponível       soma dos saldos positivos de cada cliente.
 * O nome do cliente e o saldo por cliente aparecem só para admin e recepção.
 * Depende de $cashbackResumo, $cashbackTransacoes e $cashbackClientes.
 */
$boSomenteLeitura = $boSomenteLeitura ?? true;
$boCashbackComClientes = in_array($boPerfilSecao, ['admin', 'recepcionista'], true);
$boCashbackOrigens = bo_cashback_origem_labels();
$boCashbackColunas = $boSomenteLeitura ? 8 : 9;
$boCashbackPerfil = htmlspecialchars($boPerfilSecao, ENT_QUOTES, 'UTF-8');
$boIdUsuarioFmt = static fn(int $id): string => '#' . str_pad((string) $id, 4, '0', STR_PAD_LEFT);
$boCashbackConsultaCpf = $boCashbackComClientes ? ($_SESSION['bo_cashback_cpf_resultado'] ?? null) : null;
$boCashbackConsultaCliente = null;
if (is_array($boCashbackConsultaCpf) && !empty($boCashbackConsultaCpf['encontrado'])) {
    foreach ($cashbackClientes as $cliente) {
        if ((int) $cliente['usuarioId'] === (int) $boCashbackConsultaCpf['id']) {
            $boCashbackConsultaCliente = $cliente;
            break;
        }
    }
}
?>

<!-- ===== Cashbacks dos clientes (indicadores, saldo por cliente e movimentações) ===== -->
<section class="bo-content-section" data-perfil="<?php echo $boCashbackPerfil; ?>" data-section="cashbacks">
    <div class="bo-page-title">
        <div>
            <?php if ($boSomenteLeitura): ?><?php echo bo_selo_somente_consulta(); ?><?php endif; ?>
            <h1><?php echo $boSomenteLeitura ? of_t('Cashback dos clientes') : of_t('Cashbacks'); ?></h1>
            <p><?php echo $boSomenteLeitura ? of_t('Consulte o cashback concedido, utilizado e disponível dos clientes. Estes valores não são o seu saldo.') : of_t('Acompanhe concessões, devoluções, utilizações e saldos de cashback dos clientes.'); ?></p>
        </div>
        <?php if (!$boSomenteLeitura): ?>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#modalCashbackMassa">
                <i class="bi bi-people"></i> <?php echo of_t('Distribuição em Massa'); ?>
            </button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalCashbackLancar">
                <i class="bi bi-plus-lg"></i> <?php echo of_t('Lançar Cashback'); ?>
            </button>
        </div>
        <?php endif; ?>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php bo_modal_cashback_massa('cashbacks'); ?>
        <?php bo_modal_cashback_lancar('cashbacks'); ?>
    <?php endif; ?>

    <!-- Indicadores de todos os clientes: concedido + devoluções - utilizado = saldo -->
    <p class="bo-cashback-escopo"><i class="bi bi-globe2" aria-hidden="true"></i> <?php echo of_t('Indicadores de todos os clientes · lançamentos cancelados ficam fora dos totais'); ?></p>
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bo-card bo-cashback-card">
                <div class="bo-card-label"><?php echo of_t('Cashback concedido'); ?> <i class="bi bi-gift" aria-hidden="true"></i></div>
                <div class="bo-card-value" data-bo-cashback="concedido"><?php echo bo_money($cashbackResumo['concedido']); ?></div>
                <p class="bo-cashback-desc"><?php echo of_t('Compras, mensalidades e lançamentos do admin'); ?></p>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bo-card bo-cashback-card">
                <div class="bo-card-label"><?php echo of_t('Devoluções creditadas'); ?> <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i></div>
                <div class="bo-card-value" data-bo-cashback="devolucoes"><?php echo bo_money($cashbackResumo['devolucoes']); ?></div>
                <p class="bo-cashback-desc"><?php echo of_t('Pedidos devolvidos lançados como saldo'); ?></p>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bo-card bo-cashback-card">
                <div class="bo-card-label"><?php echo of_t('Cashback utilizado'); ?> <i class="bi bi-bag-check" aria-hidden="true"></i></div>
                <div class="bo-card-value" data-bo-cashback="utilizado"><?php echo bo_money($cashbackResumo['utilizado']); ?></div>
                <p class="bo-cashback-desc">
                    <?php echo of_t('Abatido em compras no Marketplace'); ?>
                    <?php if ($cashbackResumo['debitosManuais'] > 0): ?>
                        <span data-bo-cashback="debitos-manuais"><?php echo of_t('· Débitos manuais do admin: {valor}', ['{valor}' => bo_money($cashbackResumo['debitosManuais'])]); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="bo-card bo-cashback-card">
                <div class="bo-card-label"><?php echo of_t('Saldo disponível dos clientes'); ?> <i class="bi bi-wallet2" aria-hidden="true"></i></div>
                <div class="bo-card-value" data-bo-cashback="saldo"><?php echo bo_money($cashbackResumo['saldoDisponivel']); ?></div>
                <p class="bo-cashback-desc">
                    <?php echo of_t('{n} cliente(s) com saldo positivo', ['{n}' => (string) $cashbackResumo['clientesComSaldo']]); ?>
                    <?php if ($cashbackResumo['clientesSaldoNegativo'] > 0): ?>
                        <span data-bo-cashback="saldo-negativo"><?php echo of_t('· {n} cliente(s) com saldo negativo ({valor}), fora da soma', ['{n}' => (string) $cashbackResumo['clientesSaldoNegativo'], '{valor}' => bo_money($cashbackResumo['saldoNegativo'])]); ?></span>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>

    <?php if ($boCashbackComClientes): ?>
    <div class="bo-bloco bo-cashback-cpf">
        <div class="bo-bloco-topo">
            <div>
                <h2><?php echo of_t('Consultar cashback por CPF'); ?></h2>
                <p><?php echo of_t('O CPF é consultado no servidor e não é incluído na página nem na URL.'); ?></p>
            </div>
        </div>
        <form method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/funcionalidades/cashbacks-consulta.php" class="bo-cashback-cpf-form">
            <?php echo bo_csrf_field(); ?>
            <?php echo bo_hidden('secao', 'cashbacks'); ?>
            <label class="visually-hidden" for="boCashbackCpf"><?php echo of_t('CPF do aluno'); ?></label>
            <input type="text" class="form-control" id="boCashbackCpf" name="cpf" inputmode="numeric" autocomplete="off" maxlength="14"
                placeholder="<?php echo of_t('CPF do aluno'); ?>" aria-label="<?php echo of_t('CPF do aluno'); ?>" required>
            <button type="submit" class="btn-bo-outline"><i class="bi bi-search" aria-hidden="true"></i> <?php echo of_t('Consultar'); ?></button>
            <?php if (is_array($boCashbackConsultaCpf)): ?>
                <button type="submit" name="acao" value="limpar" class="btn-bo-outline" formnovalidate><?php echo of_t('Limpar consulta'); ?></button>
            <?php endif; ?>
        </form>
        <?php if (is_array($boCashbackConsultaCpf)): ?>
            <?php if (!empty($boCashbackConsultaCpf['encontrado'])): ?>
                <?php
                $boCashbackConsultaId = (int) $boCashbackConsultaCpf['id'];
                $boCashbackConsultaCreditos = $boCashbackConsultaCliente['creditos'] ?? 0;
                $boCashbackConsultaDebitos = $boCashbackConsultaCliente['debitos'] ?? 0;
                $boCashbackConsultaSaldo = $boCashbackConsultaCliente['saldo'] ?? 0;
                ?>
                <div class="bo-cashback-cpf-result" role="status">
                    <strong><?php echo htmlspecialchars((string) $boCashbackConsultaCpf['nome'], ENT_QUOTES, 'UTF-8'); ?></strong>
                    <span><?php echo $boIdUsuarioFmt($boCashbackConsultaId); ?></span>
                    <span><?php echo of_t('Créditos: {valor}', ['{valor}' => bo_money($boCashbackConsultaCreditos)]); ?></span>
                    <span><?php echo of_t('Débitos: {valor}', ['{valor}' => bo_money($boCashbackConsultaDebitos)]); ?></span>
                    <span><?php echo of_t('Saldo: {valor}', ['{valor}' => bo_money($boCashbackConsultaSaldo)]); ?></span>
                </div>
            <?php else: ?>
                <p class="bo-cashback-cpf-result" role="status"><?php echo of_t('Nenhum aluno encontrado com esse CPF.'); ?></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <!-- Consulta individual: saldo de cada cliente; "Ver histórico" filtra as movimentações abaixo -->
    <div class="bo-bloco" id="boCashbackClientes-<?php echo $boCashbackPerfil; ?>">
        <div class="bo-bloco-topo">
            <div>
                <h2><?php echo of_t('Saldo por cliente'); ?> <span class="bo-contagem" data-bo-contagem="cashbacksClientes"><?php echo count($cashbackClientes); ?></span></h2>
                <p><?php echo of_t('Créditos, débitos e saldo de cada cliente com movimentação.'); ?></p>
            </div>
            <div class="bo-filters">
                <input type="search" class="form-control" placeholder="<?php echo of_t('Buscar cliente por nome ou ID'); ?>"
                    aria-label="<?php echo of_t('Buscar cliente por nome ou ID'); ?>" data-bo-filter="search" data-bo-target="cashbacksClientes">
            </div>
        </div>
        <div class="bo-table-wrap">
            <div class="table-responsive">
                <table class="bo-table" data-bo-table="cashbacksClientes">
                    <thead>
                        <tr>
                            <th><?php echo of_t('Cliente'); ?></th>
                            <th><?php echo of_t('Créditos'); ?></th>
                            <th><?php echo of_t('Débitos'); ?></th>
                            <th><?php echo of_t('Saldo disponível'); ?></th>
                            <th><?php echo of_t('Movimentações'); ?></th>
                            <th><?php echo of_t('Última movimentação'); ?></th>
                            <th><?php echo of_t('Histórico'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashbackClientes as $cl): ?>
                            <tr data-user="<?php echo (int) $cl['usuarioId']; ?>" data-bo-cashback-cliente="<?php echo (int) $cl['usuarioId']; ?>"
                                data-search="<?php echo htmlspecialchars(mb_strtolower($boIdUsuarioFmt($cl['usuarioId']) . ' ' . $cl['usuarioId'] . ' ' . $cl['cliente']), ENT_QUOTES, 'UTF-8'); ?>">
                                <td><?php echo htmlspecialchars((string) $cl['cliente']); ?> <small class="text-secondary"><?php echo $boIdUsuarioFmt($cl['usuarioId']); ?></small></td>
                                <td class="bo-num"><?php echo bo_money($cl['creditos']); ?></td>
                                <td class="bo-num"><?php echo bo_money($cl['debitos']); ?></td>
                                <td class="bo-num" data-bo-cashback-saldo>
                                    <?php if ($cl['saldo'] > 0): ?><strong><?php echo bo_money($cl['saldo']); ?></strong>
                                    <?php else: ?><span class="bo-saldo-zero"><?php echo bo_money(0); ?></span>
                                        <?php if ($cl['saldo'] < 0): ?><span class="bo-badge bo-badge-inactive"><?php echo of_t('Negativo: {valor}', ['{valor}' => bo_money($cl['saldo'])]); ?></span><?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="bo-num"><?php echo (int) $cl['movimentos']; ?></td>
                                <td class="bo-num"><?php echo onefitData('d/m/Y', strtotime($cl['ultima'])); ?></td>
                                <td>
                                    <button type="button" class="btn-bo-outline btn-sm" data-bo-filtrar-usuario="<?php echo (int) $cl['usuarioId']; ?>" data-bo-target="cashbacks"
                                        data-bo-rolar-para="boCashbackMovimentacoes-<?php echo $boCashbackPerfil; ?>">
                                        <i class="bi bi-clock-history"></i> <?php echo of_t('Ver histórico'); ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bo-empty-row" <?php echo empty($cashbackClientes) ? '' : 'style="display:none"'; ?>>
                            <td colspan="7"><?php echo of_t('Nenhum cliente encontrado.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Movimentações: créditos e débitos com filtros agrupados -->
    <div class="bo-bloco" id="boCashbackMovimentacoes-<?php echo $boCashbackPerfil; ?>">
        <div class="bo-bloco-topo">
            <div>
                <h2><?php echo of_t('Movimentações'); ?> <span class="bo-contagem" data-bo-contagem="cashbacks"><?php echo (int) $cashbackResumo['movimentos']; ?></span></h2>
                <p><?php echo of_t('Créditos e débitos de cashback, do mais recente ao mais antigo.'); ?></p>
            </div>
        </div>
        <div class="bo-filters bo-filters-painel" role="search" aria-label="<?php echo of_t('Filtros das movimentações'); ?>">
            <input type="search" class="form-control bo-filters-busca" placeholder="<?php echo $boCashbackComClientes ? of_t('Buscar por transação, cliente ou pedido') : of_t('Buscar por transação ou pedido'); ?>"
                aria-label="<?php echo $boCashbackComClientes ? of_t('Buscar por transação, cliente ou pedido') : of_t('Buscar por transação ou pedido'); ?>" data-bo-filter="search" data-bo-target="cashbacks">
            <input type="text" inputmode="numeric" class="form-control" style="max-width:200px" placeholder="<?php echo of_t('Buscar por ID do usuário'); ?>"
                aria-label="<?php echo of_t('Buscar por ID do usuário'); ?>" data-bo-filter="user" data-bo-target="cashbacks">
            <select class="form-select" style="max-width:170px" aria-label="<?php echo of_t('Tipo'); ?>" data-bo-filter="type" data-bo-target="cashbacks">
                <option value=""><?php echo of_t('Todos os tipos'); ?></option>
                <option value="credito"><?php echo of_t('Crédito'); ?></option>
                <option value="debito"><?php echo of_t('Débito'); ?></option>
            </select>
            <select class="form-select" style="max-width:220px" aria-label="<?php echo of_t('Origem'); ?>" data-bo-filter="status" data-bo-target="cashbacks">
                <option value=""><?php echo of_t('Todas as origens'); ?></option>
                <?php foreach ($boCashbackOrigens as $valor => $label): ?>
                    <option value="<?php echo $valor; ?>"><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <div class="bo-daterange">
                <?php echo of_t('De'); ?> <input type="date" class="form-control" aria-label="<?php echo of_t('Data inicial'); ?>" data-bo-filter="date-from" data-bo-target="cashbacks">
                <?php echo of_t('até'); ?> <input type="date" class="form-control" aria-label="<?php echo of_t('Data final'); ?>" data-bo-filter="date-to" data-bo-target="cashbacks">
            </div>
            <button type="button" class="btn-bo-outline btn-sm bo-filters-limpar" data-bo-limpar-filtros="cashbacks"><i class="bi bi-x-lg"></i> <?php echo of_t('Limpar filtros'); ?></button>
        </div>

        <div class="bo-table-wrap">
            <div class="table-responsive">
                <table class="bo-table" data-bo-table="cashbacks">
                    <thead>
                        <tr>
                            <th><?php echo of_t('ID transação'); ?></th>
                            <th><?php echo of_t('Data'); ?></th>
                            <th><?php echo $boCashbackComClientes ? of_t('Cliente') : of_t('ID usuário'); ?></th>
                            <th><?php echo of_t('Tipo'); ?></th>
                            <th><?php echo of_t('Origem'); ?></th>
                            <th><?php echo of_t('Valor'); ?></th>
                            <th><?php echo of_t('Pedido'); ?></th>
                            <th><?php echo of_t('Motivo'); ?></th>
                            <?php if (!$boSomenteLeitura): ?><th><?php echo of_t('Ações'); ?></th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cashbackTransacoes as $c): ?>
                            <?php
                            $boPedido = $c['pedido'] !== null ? 'TRX-' . str_pad((string) $c['pedido'], 4, '0', STR_PAD_LEFT) : '';
                            $boBusca = $boIdUsuarioFmt($c['id']) . ' ' . $c['id'] . ' ' . $boPedido . ' ' . ($boCashbackComClientes ? $c['cliente'] : '') . ' ' . $c['motivo'];
                            $boValorClasse = $c['cancelado'] ? 'bo-valor-cancelado' : ($c['tipo'] === 'credito' ? 'bo-valor-credito' : '');
                            ?>
                            <tr data-type="<?php echo $c['tipo']; ?>" data-user="<?php echo (int) $c['usuarioId']; ?>" data-status="<?php echo $c['origem']; ?>"
                                data-date="<?php echo substr((string) $c['data'], 0, 10); ?>"
                                data-search="<?php echo htmlspecialchars(mb_strtolower($boBusca), ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="bo-num"><?php echo $boIdUsuarioFmt($c['id']); ?></td>
                                <td class="bo-num"><?php echo onefitData('d/m/Y H:i', strtotime($c['data'])); ?></td>
                                <td>
                                    <?php if ($boCashbackComClientes): ?><?php echo htmlspecialchars((string) $c['cliente']); ?> <small class="text-secondary"><?php echo $boIdUsuarioFmt($c['usuarioId']); ?></small>
                                    <?php else: ?><?php echo $boIdUsuarioFmt($c['usuarioId']); ?><?php endif; ?>
                                </td>
                                <td><?php echo $c['tipo'] === 'credito' ? onefitTraduzir('Crédito') : onefitTraduzir('Débito'); ?></td>
                                <td><?php echo $boCashbackOrigens[$c['origem']]; ?></td>
                                <td class="bo-num">
                                    <span class="<?php echo $boValorClasse; ?>"><?php echo ($c['tipo'] === 'debito' ? '−' : '+') . bo_money($c['valor']); ?></span>
                                    <?php if ($c['cancelado']): ?><span class="bo-badge bo-badge-inactive"><?php echo of_t('Cancelado'); ?></span><?php endif; ?>
                                </td>
                                <td class="bo-num"><?php echo $boPedido !== '' ? $boPedido : '—'; ?></td>
                                <td><?php echo htmlspecialchars((string) $c['motivo']); ?></td>
                                <?php if (!$boSomenteLeitura): ?>
                                <td>
                                    <div class="bo-table-actions">
                                        <?php echo bo_botao_excluir('cashbacks', $c['id']); ?>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="bo-empty-row" <?php echo empty($cashbackTransacoes) ? '' : 'style="display:none"'; ?>>
                            <td colspan="<?php echo $boCashbackColunas; ?>"><?php echo of_t('Nenhuma transação encontrada para os filtros selecionados.'); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php if (!$boSomenteLeitura): ?>
        <?php foreach ($cashbackTransacoes as $c): ?>
            <?php bo_modal_confirmar_exclusao('cashbacks', $c['id'], 'a transação #' . $c['id'], 'cashbacks'); ?>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
