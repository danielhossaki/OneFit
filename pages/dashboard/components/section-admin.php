<?php
/**
 * components/section-admin.php
 * Todas as telas (<section data-perfil="admin" data-section="...">) do
 * perfil Administrador. Cada <section> corresponde a um item do menu
 * BO_PERFIS.admin.menus em assets/js/backoffice.js (a chave "data-section"
 * precisa bater com a "key" do menu para o JS conseguir exibir a tela certa).
 *
 * Depende das variáveis vindas de includes/db-data.php:
 *   $usuarios (só alunos), $funcionarios, $cadastrosProfissionaisLivres,
 *   $funcionariosMigracao, $funcionariosErro, $pagamentos, $cashbackResumo,
 *   $cashbackTransacoes, $categorias, $produtosResumo, $produtos, $planos,
 *   $professores, $professoresMigracao, $professoresPendencias, $professoresErro
 * e das funções de includes/helpers.php: bo_badge(), bo_money(), bo_json()
 */
require __DIR__ . '/section-fotos-faciais.php';
// Alunos, Pagamentos, Cashbacks, Categorias e Modalidades são componentes
// compartilhados com a recepção e o vendedor, que só consultam.
$boPerfilSecao = 'admin';
$boSomenteLeitura = false;
?>

<!-- ===== ADMIN · Visão Geral (dashboard com indicadores gerais) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="dashboard">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Dashboard'); ?></h1>
            <p><?php echo of_t('Resumo geral da operação {marca}.'); ?></p>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Alunos ativos'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['alunosAtivos']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} de {total} contas de aluno', ['{n}' => (string) $admDashboard['alunosAtivos'], '{total}' => (string) $admDashboard['alunosTotal']]); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Alunos novos este mês'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['alunosNovosMes']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} contas de aluno inativas', ['{n}' => (string) $admDashboard['alunosInativos']]); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Matrículas vigentes'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['matriculasAtivas']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} pendentes', ['{n}' => (string) $admDashboard['matriculasPendentes']]); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Matrículas vencidas'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['matriculasVencidas']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('Situação atual das matrículas'); ?></div>
        </div></div>
    </div>
    <div class="row g-3 mb-3">
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Pagamentos aprovados no mês'); ?></div>
            <div class="bo-card-value"><?php echo bo_money($admDashboard['saldoMes']); ?></div>
            <div class="bo-card-sub"><?php echo of_t('Ano'); ?> <?php echo bo_money($admDashboard['saldoAno']); ?> · <?php echo of_t('Semana'); ?> <?php echo bo_money($admDashboard['saldoSemana']); ?> · <?php echo of_t('Dia'); ?> <?php echo bo_money($admDashboard['saldoDia']); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Pagamentos pendentes ou atrasados'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['pagamentosPendentes']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('Contagem de cobranças, sem somar ao recebido'); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Cashback concedido no mês'); ?></div>
            <div class="bo-card-value"><?php echo bo_money($admDashboard['cashbackMes']); ?></div>
            <div class="bo-card-sub"><?php echo of_t('Ano'); ?> <?php echo bo_money($admDashboard['cashbackAno']); ?> · <?php echo of_t('Semana'); ?> <?php echo bo_money($admDashboard['cashbackSemana']); ?> · <?php echo of_t('Dia'); ?> <?php echo bo_money($admDashboard['cashbackDia']); ?> · <?php echo of_t('Sem devoluções ou lançamentos cancelados'); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Funcionários ativos'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['funcionariosAtivos']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} contas inativas ou bloqueadas', ['{n}' => (string) $admDashboard['funcionariosInativos']]); ?></div>
        </div></div>
    </div>
    <div class="row g-3">
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Contas de professor ativas'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['profissionaisAtivos']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} recebem novos agendamentos', ['{n}' => (string) $admDashboard['profissionaisAgendaveis']]); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Professores não agendáveis'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['profissionaisIndisponiveis']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('Conta, cadastro ou disponibilidade impedem novos agendamentos'); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Agendamentos nos próximos 7 dias'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['agendamentosProximos']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('Apenas horários futuros agendados ou confirmados'); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Agendamentos concluídos nos últimos 30 dias'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['agendamentosConcluidos']; ?></div>
            <div class="bo-card-sub"><?php echo of_t('{n} cancelados', ['{n}' => (string) $admDashboard['agendamentosCancelados']]); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Acessos liberados'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['acessosLiberados']; ?></div>
            <div class="bo-card-sub"><?php echo $admDashboard['totalUsuarios'] ? round($admDashboard['acessosLiberados'] / $admDashboard['totalUsuarios'] * 100) : 0; ?><?php echo of_t('% da base total'); ?></div>
        </div></div>
        <div class="col-12 col-sm-6 col-xl-3"><div class="bo-card">
            <div class="bo-card-label"><?php echo of_t('Acessos bloqueados'); ?></div>
            <div class="bo-card-value"><?php echo $admDashboard['acessosBloqueados']; ?></div>
            <div class="bo-card-sub"><?php echo $admDashboard['totalUsuarios'] ? round($admDashboard['acessosBloqueados'] / $admDashboard['totalUsuarios'] * 100) : 0; ?><?php echo of_t('% da base total'); ?></div>
        </div></div>
    </div>
</section>

<?php require __DIR__ . '/section-alunos.php'; ?>

<?php require __DIR__ . '/section-funcionarios.php'; ?>

<!-- Permissões e Funções foram desativadas: o cargo vem apenas de
     usuarios.tipo_usuario (config/papeis.php). As tabelas `permissoes` e
     `funcao` continuam no banco, sem alteração. -->

<?php require __DIR__ . '/section-pagamentos.php'; ?>

<?php require __DIR__ . '/section-cashbacks.php'; ?>

<!-- ===== ADMIN · Comentários (moderação dos depoimentos enviados pelos alunos) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="comentarios">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Comentários'); ?></h1>
            <p><?php echo of_t('Aprove ou reprove os depoimentos enviados pelos alunos e controle quais aparecem na home.'); ?></p>
        </div>
    </div>

    <?php $boTestemunhoStatusLabel = ['pendente' => 'Pendente', 'aprovado' => 'Aprovado', 'reprovado' => 'Reprovado']; ?>
    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table">
                <thead>
                    <tr>
                        <th><?php echo of_t('Aluno'); ?></th>
                        <th><?php echo of_t('Comentário'); ?></th>
                        <th><?php echo of_t('Data'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Visível na home'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($testemunhosAdmin as $t): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td style="max-width:320px;"><?php echo htmlspecialchars($t['texto'], ENT_QUOTES, 'UTF-8'); ?></td>
                            <td><?php echo onefitData('d/m/Y', strtotime($t['data_criacao'])); ?></td>
                            <td><?php echo bo_badge($t['aprovacao'] === 'aprovado', $boTestemunhoStatusLabel[$t['aprovacao']] ?? ucfirst($t['aprovacao']), $boTestemunhoStatusLabel[$t['aprovacao']] ?? ucfirst($t['aprovacao'])); ?></td>
                            <td>
                                <?php if ($t['aprovacao'] === 'aprovado'): ?>
                                    <?php echo bo_badge($t['visibilidade'] === 'ativo', 'Visível', 'Oculto'); ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php if ($t['aprovacao'] !== 'aprovado'): ?>
                                        <form method="POST" action="<?php echo bo_form_action('testemunhos.php'); ?>" style="display:inline;">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'comentarios'); ?>
                                            <?php echo bo_hidden('acao', 'aprovar'); ?>
                                            <?php echo bo_hidden('id', $t['id_testemunho']); ?>
                                            <button type="submit" class="btn-bo-icon" title="<?php echo of_t('Aprovar'); ?>"><i class="bi bi-check-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($t['aprovacao'] !== 'reprovado'): ?>
                                        <form method="POST" action="<?php echo bo_form_action('testemunhos.php'); ?>" style="display:inline;">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'comentarios'); ?>
                                            <?php echo bo_hidden('acao', 'reprovar'); ?>
                                            <?php echo bo_hidden('id', $t['id_testemunho']); ?>
                                            <button type="submit" class="btn-bo-icon danger" title="<?php echo of_t('Reprovar'); ?>"><i class="bi bi-x-circle"></i></button>
                                        </form>
                                    <?php endif; ?>
                                    <?php if ($t['aprovacao'] === 'aprovado'): ?>
                                        <?php bo_form_toggle('testemunhos', $t['id_testemunho'], 'comentarios', $t['visibilidade'] === 'ativo', 'Visível', 'Oculto'); ?>
                                    <?php endif; ?>
                                    <?php echo bo_botao_excluir('testemunhos', $t['id_testemunho']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$testemunhosAdmin): ?><tr><td colspan="6"><?php echo of_t('Nenhum comentário enviado ainda.'); ?></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($testemunhosAdmin as $t): ?>
        <?php bo_modal_confirmar_exclusao('testemunhos', $t['id_testemunho'], 'o comentário de ' . $t['nome'], 'comentarios'); ?>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/section-categorias.php'; ?>

<!-- ===== ADMIN · Produtos (catálogo da loja) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="produtos">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Produtos'); ?></h1>
            <p><?php echo of_t('Gerencie o catálogo de produtos da loja.'); ?></p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalProdutoNovo">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Cadastro de Produto'); ?>
        </button>
    </div>
    <?php bo_modal_produto(null, 'produtos', $categoriasAtivasOptions); ?>

    <!-- Cards de resumo do catálogo -->
    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Total cadastrados'); ?></div>
                <div class="bo-card-value"><?php echo $produtosResumo['total']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Disponíveis'); ?></div>
                <div class="bo-card-value"><?php echo $produtosResumo['disponiveis']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo of_t('Indisponíveis'); ?></div>
                <div class="bo-card-value"><?php echo $produtosResumo['indisponiveis']; ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="<?php echo of_t('Buscar por nome ou ID'); ?>"
            data-bo-filter="search" data-bo-target="produtos">
        <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="produtos">
            <option value=""><?php echo of_t('Todos'); ?></option>
            <option value="disponivel"><?php echo of_t('Disponível'); ?></option>
            <option value="indisponivel"><?php echo of_t('Indisponível'); ?></option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="produtos">
                <thead>
                    <tr>
                        <th><?php echo of_t('Foto'); ?></th>
                        <th>ID</th>
                        <th><?php echo of_t('Nome'); ?></th>
                        <th><?php echo of_t('Preço'); ?></th>
                        <th><?php echo of_t('Desconto'); ?></th>
                        <th><?php echo of_t('Valor final'); ?></th>
                        <th><?php echo of_t('Cashback'); ?></th>
                        <th><?php echo of_t('Estoque'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $p): ?>
                        <tr data-status="<?php echo $p['status']; ?>"
                            data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome']); ?>">
                            <td>
                                <div class="bo-thumb">
                                    <?php if ($p['imagem']): ?>
                                        <img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="bi bi-image"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo bo_money($p['preco']); ?></td>
                            <td><?php echo $p['desconto']; ?>%</td>
                            <td><?php echo bo_money($p['valorFinal']); ?></td>
                            <td><?php echo bo_money($p['cashback']); ?></td>
                            <td><?php echo $p['estoque']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'disponivel', onefitTraduzir('Disponível'), onefitTraduzir('Indisponível')); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('produtos', $p['id'], 'produtos', $p['status'] === 'disponivel', onefitTraduzir('Disponível'), onefitTraduzir('Indisponível')); ?>
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalProdutoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('produtos', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="10"><?php echo of_t('Nenhum produto encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($produtos as $p): ?>
        <?php bo_modal_produto($p, 'produtos', $categoriasAtivasOptions); ?>
        <?php bo_modal_confirmar_exclusao('produtos', $p['id'], $p['nome'], 'produtos'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Vendas Marketplace (visão agregada de todos os vendedores) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="vendas">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Vendas Marketplace'); ?></h1>
            <p><?php echo of_t('Vendas e logística de todos os vendedores do marketplace.'); ?></p>
        </div>
    </div>

    <?php $admVendasStatusLabel = ['aguardando' => onefitTraduzir('Aguardando'), 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => onefitTraduzir('Devolvido'), 'extraviado' => onefitTraduzir('Extraviado')]; ?>

    <ul class="nav nav-tabs bo-nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#admVendasTabVendas" type="button" role="tab"><?php echo of_t('Vendas e logística'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#admVendasTabTransportadoras" type="button" role="tab"><?php echo of_t('Transportadoras'); ?></button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#admVendasTabDevolucoes" type="button" role="tab"><?php echo of_t('Devoluções'); ?></button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ===== Vendas de todos os vendedores ===== -->
        <div class="tab-pane fade show active" id="admVendasTabVendas" role="tabpanel">
            <div class="bo-filters mt-3">
                <input type="text" class="form-control" style="max-width:280px" placeholder="<?php echo of_t('Buscar por produto, vendedor, comprador ou rastreio'); ?>"
                    data-bo-filter="search" data-bo-target="admVendasVendas">
                <select class="form-select" style="max-width:220px" data-bo-filter="status" data-bo-target="admVendasVendas">
                    <option value=""><?php echo of_t('Todos os status'); ?></option>
                    <?php foreach ($admVendasStatusLabel as $valor => $label): ?>
                        <option value="<?php echo $valor; ?>"><?php echo of_t($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="admVendasVendas">
                        <thead>
                            <tr><th><?php echo of_t('Data'); ?></th><th><?php echo of_t('Produto'); ?></th><th><?php echo of_t('Vendedor'); ?></th><th><?php echo of_t('Comprador'); ?></th><th><?php echo of_t('Qtd.'); ?></th><th><?php echo of_t('Valor'); ?></th><th><?php echo of_t('Transportadora'); ?></th><th><?php echo of_t('Frete'); ?></th><th><?php echo of_t('Rastreio / Status'); ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admVendas as $v): ?>
                                <tr data-status="<?php echo $v['statusLogistica']; ?>"
                                    data-search="<?php echo strtolower($v['produto'] . ' ' . $v['vendedor'] . ' ' . $v['comprador'] . ' ' . $v['transportadora'] . ' ' . ($v['codigoRastreio'] ?? '')); ?>">
                                    <td><?php echo $v['data']; ?></td>
                                    <td><?php echo htmlspecialchars($v['produto']); ?></td>
                                    <td><?php echo htmlspecialchars($v['vendedor']); ?></td>
                                    <td><?php echo htmlspecialchars($v['comprador']); ?></td>
                                    <td><?php echo $v['quantidade']; ?></td>
                                    <td><?php echo bo_money($v['valor']); ?></td>
                                    <td><?php echo htmlspecialchars($v['transportadora']); ?></td>
                                    <td><?php echo bo_money($v['valorFrete']); ?></td>
                                    <td>
                                        <form method="POST" action="<?php echo bo_form_action('vendas.php'); ?>" class="bo-inline-form">
                                            <?php echo bo_csrf_field(); ?>
                                            <?php echo bo_hidden('secao', 'vendas'); ?>
                                            <?php echo bo_hidden('acao', 'update-status'); ?>
                                            <?php echo bo_hidden('id', $v['id']); ?>
                                            <select class="form-select form-select-sm" name="status_logistica" style="min-width:150px">
                                                <?php foreach ($admVendasStatusLabel as $valor => $label): ?>
                                                    <option value="<?php echo $valor; ?>" <?php echo $v['statusLogistica'] === $valor ? 'selected' : ''; ?>><?php echo of_t($label); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1" name="codigo_rastreio" placeholder="<?php echo of_t('Código de rastreio'); ?>" value="<?php echo htmlspecialchars($v['codigoRastreio'] ?? ''); ?>">
                                            <button type="submit" class="btn-bo-outline btn-sm mt-1"><?php echo of_t('Salvar'); ?></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" <?php echo empty($admVendas) ? '' : 'style="display:none"'; ?>><td colspan="9"><?php echo of_t('Nenhuma venda registrada ainda.'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== Transportadoras (globais) ===== -->
        <div class="tab-pane fade" id="admVendasTabTransportadoras" role="tabpanel">
            <div class="bo-page-title mt-3">
                <div><p class="mb-0"><?php echo of_t('Cadastradas pelo admin, disponíveis para todos os vendedores.'); ?></p></div>
                <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalTransportadoraNova">
                    <i class="bi bi-plus-lg"></i> <?php echo of_t('Adicionar transportador'); ?>
                </button>
            </div>
            <?php bo_modal_transportadora(null, 'vendas'); ?>

            <?php foreach ($transportadoras as $t): ?>
                <div class="bo-card mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong><?php echo htmlspecialchars($t['nome']); ?></strong>
                            <span class="bo-card-sub"><?php echo of_t(ucfirst($t['tipo'])); ?></span>
                            <?php echo bo_badge($t['status'] === 'ativo', onefitTraduzir('Ativo'), onefitTraduzir('Inativo')); ?>
                        </div>
                        <div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalTransportadoraEditar<?php echo $t['id']; ?>"><i class="bi bi-pencil"></i></button>
                            <?php bo_form_toggle('transportadoras', $t['id'], 'vendas', $t['status'] === 'ativo', onefitTraduzir('Ativo'), onefitTraduzir('Inativo')); ?>
                            <?php echo bo_botao_excluir('transportadoras', $t['id']); ?>
                            <button type="button" class="btn-bo-outline btn-sm" data-bs-toggle="modal" data-bs-target="#modalFaixaCep<?php echo $t['id']; ?>"><i class="bi bi-plus-lg"></i> <?php echo of_t('Faixa de CEP'); ?></button>
                        </div>
                    </div>
                    <?php if (!empty($t['faixas'])): ?>
                        <div class="table-responsive mt-2">
                            <table class="bo-table">
                                <thead><tr><th><?php echo of_t('CEP inicial'); ?></th><th><?php echo of_t('CEP final'); ?></th><th><?php echo of_t('Frete'); ?></th><th><?php echo of_t('Prazo'); ?></th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($t['faixas'] as $f): ?>
                                        <tr>
                                            <td><?php echo $f['cepInicial']; ?></td>
                                            <td><?php echo $f['cepFinal']; ?></td>
                                            <td><?php echo bo_money($f['valorFrete']); ?></td>
                                            <td><?php echo of_t((int) $f['prazoDias'] === 1 ? '{n} dia' : '{n} dias', ['{n}' => (int) $f['prazoDias']]); ?></td>
                                            <td>
                                                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                                                    <?php echo bo_csrf_field(); ?>
                                                    <?php echo bo_hidden('secao', 'vendas'); ?>
                                                    <?php echo bo_hidden('acao', 'delete-faixa'); ?>
                                                    <?php echo bo_hidden('id_faixa', $f['id']); ?>
                                                    <button type="submit" class="btn-bo-icon danger" title="<?php echo of_t('Remover faixa'); ?>"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="bo-card-sub mt-2 mb-0"><?php echo of_t('Nenhuma faixa de CEP cadastrada ainda.'); ?></p>
                    <?php endif; ?>
                </div>
                <?php bo_modal_transportadora($t, 'vendas'); ?>
                <?php bo_modal_faixa_cep($t['id'], 'vendas'); ?>
                <?php bo_modal_confirmar_exclusao('transportadoras', $t['id'], $t['nome'], 'vendas'); ?>
            <?php endforeach; ?>
        </div>

        <!-- ===== Devoluções solicitadas pelos compradores ===== -->
        <div class="tab-pane fade" id="admVendasTabDevolucoes" role="tabpanel">
            <?php $admDevolucaoStatusLabel = bo_status_devolucao_labels(); ?>
            <div class="bo-table-wrap mt-3">
                <div class="table-responsive">
                    <table class="bo-table">
                        <thead>
                            <tr><th><?php echo of_t('Solicitado em'); ?></th><th><?php echo of_t('Pedido'); ?></th><th><?php echo of_t('Comprador'); ?></th><th><?php echo of_t('Valor'); ?></th><th><?php echo of_t('Motivo'); ?></th><th><?php echo of_t('Status'); ?></th><th><?php echo of_t('Ação'); ?></th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admDevolucoes as $d): ?>
                                <tr>
                                    <td><?php echo $d['dataSolicitacao']; ?></td>
                                    <td><?php echo $d['transacao']; ?></td>
                                    <td><?php echo htmlspecialchars($d['comprador']); ?></td>
                                    <td><?php echo bo_money($d['valor']); ?></td>
                                    <td><?php echo htmlspecialchars($d['motivo']); ?></td>
                                    <td><span class="bo-badge"><?php echo of_t($admDevolucaoStatusLabel[$d['status']] ?? ucfirst($d['status'])); ?></span></td>
                                    <td>
                                        <?php if ($d['status'] === 'pendente'): ?>
                                            <form method="POST" action="<?php echo bo_form_action('devolucoes.php'); ?>" class="bo-inline-form">
                                                <?php echo bo_csrf_field(); ?>
                                                <?php echo bo_hidden('secao', 'vendas'); ?>
                                                <?php echo bo_hidden('acao', 'aprovar'); ?>
                                                <?php echo bo_hidden('id', $d['id']); ?>
                                                <button type="submit" class="btn-bo-outline btn-sm"><?php echo of_t('Aprovar'); ?></button>
                                            </form>
                                            <details>
                                                <summary><?php echo of_t('Recusar'); ?></summary>
                                                <form method="POST" action="<?php echo bo_form_action('devolucoes.php'); ?>" class="bo-inline-form">
                                                    <?php echo bo_csrf_field(); ?>
                                                    <?php echo bo_hidden('secao', 'vendas'); ?>
                                                    <?php echo bo_hidden('acao', 'recusar'); ?>
                                                    <?php echo bo_hidden('id', $d['id']); ?>
                                                    <textarea name="resposta_admin" class="form-control form-control-sm" rows="2" maxlength="500" placeholder="<?php echo of_t('Motivo da recusa'); ?>" required></textarea>
                                                    <button type="submit" class="btn-bo-outline btn-sm mt-1"><?php echo of_t('Confirmar recusa'); ?></button>
                                                </form>
                                            </details>
                                        <?php elseif ($d['status'] === 'aceita'): ?>
                                            <form method="POST" action="<?php echo bo_form_action('devolucoes.php'); ?>" class="bo-inline-form">
                                                <?php echo bo_csrf_field(); ?>
                                                <?php echo bo_hidden('secao', 'vendas'); ?>
                                                <?php echo bo_hidden('acao', 'concluir'); ?>
                                                <?php echo bo_hidden('id', $d['id']); ?>
                                                <button type="submit" class="btn-bo-gold btn-sm"><?php echo of_t('Concluir devolução'); ?></button>
                                            </form>
                                        <?php else: ?>
                                            <small>—</small>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" <?php echo empty($admDevolucoes) ? '' : 'style="display:none"'; ?>><td colspan="7"><?php echo of_t('Nenhuma devolução solicitada até o momento.'); ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ===== ADMIN · Cadastro de Planos (planos de assinatura oferecidos) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="planos">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Cadastro de Planos'); ?></h1>
            <p><?php echo of_t('Configure os planos de assinatura disponíveis.'); ?></p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPlanoNovo">
            <i class="bi bi-plus-lg"></i> <?php echo of_t('Novo Plano'); ?>
        </button>
    </div>
    <?php bo_modal_plano(null, 'planos'); ?>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="<?php echo of_t('Buscar por plano ou ID'); ?>"
            data-bo-filter="search" data-bo-target="planos">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="planos">
            <option value=""><?php echo of_t('Todos'); ?></option>
            <option value="ativo"><?php echo of_t('Ativo'); ?></option>
            <option value="inativo"><?php echo of_t('Inativo'); ?></option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="planos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th><?php echo of_t('Plano'); ?></th>
                        <th><?php echo of_t('Valor'); ?></th>
                        <th><?php echo of_t('Ciclo'); ?></th>
                        <th><?php echo of_t('Descrição'); ?></th>
                        <th><?php echo of_t('Status'); ?></th>
                        <th><?php echo of_t('Ações'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planos as $p): ?>
                        <tr data-status="<?php echo $p['status']; ?>"
                            data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome']); ?>">
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo bo_money($p['valor']); ?></td>
                            <td><?php echo of_t($p['ciclo']); ?></td>
                            <td><?php echo $p['descricao']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('planos', $p['id'], 'planos', $p['status'] === 'ativo'); ?>
                                    <button type="button" class="btn-bo-icon" title="<?php echo of_t('Editar'); ?>" data-bs-toggle="modal" data-bs-target="#modalPlanoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7"><?php echo of_t('Nenhum plano encontrado para os filtros selecionados.'); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($planos as $p): ?>
        <?php bo_modal_plano($p, 'planos'); ?>
    <?php endforeach; ?>
</section>

<?php require __DIR__ . '/section-professores.php'; ?>

<?php require __DIR__ . '/section-modalidades.php'; ?>
