<?php
/**
 * components/section-admin.php
 * Todas as telas (<section data-perfil="admin" data-section="...">) do
 * perfil Administrador. Cada <section> corresponde a um item do menu
 * BO_PERFIS.admin.menus em assets/js/backoffice.js (a chave "data-section"
 * precisa bater com a "key" do menu para o JS conseguir exibir a tela certa).
 *
 * Depende das variáveis vindas de includes/mock-data.php:
 *   $usuarios, $permissoes, $funcoes, $pagamentos, $cashbackResumo,
 *   $cashbackTransacoes, $categorias, $produtosResumo, $produtos, $planos,
 *   $profissionaisAdm
 * e das funções de includes/helpers.php: bo_badge(), bo_money(), bo_json()
 */
?>

<!-- ===== ADMIN · Visão Geral (dashboard com indicadores gerais) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="dashboard">
    <div class="bo-page-title">
        <div>
            <h1>Dashboard</h1>
            <p>Resumo geral da operação ONE FIT.</p>
        </div>
    </div>

    <!-- Linha 1: indicadores principais (usuários, saldo do mês, cashback distribuído) -->
    <div class="row g-3 mb-3">
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Usuários ativos</div>
                <div class="bo-card-value"><?php echo $admDashboard['usuariosAtivos']; ?></div>
                <div class="bo-card-sub">+<?php echo $admDashboard['usuariosNovosMes']; ?> este mês</div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Saldo operacional (mês)</div>
                <div class="bo-card-value"><?php echo bo_money($admDashboard['saldoMes']); ?></div>
                <div class="bo-card-sub">Ano <?php echo bo_money($admDashboard['saldoAno']); ?> · Semana <?php echo bo_money($admDashboard['saldoSemana']); ?> · Dia <?php echo bo_money($admDashboard['saldoDia']); ?></div>
            </div>
        </div>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label">Cashback distribuído</div>
                <div class="bo-card-value"><?php echo bo_money($admDashboard['cashbackMes']); ?></div>
                <div class="bo-card-sub">Ano <?php echo bo_money($admDashboard['cashbackAno']); ?> · Semana <?php echo bo_money($admDashboard['cashbackSemana']); ?> · Dia <?php echo bo_money($admDashboard['cashbackDia']); ?></div>
            </div>
        </div>
    </div>

    <!-- Linha 2: indicadores de acesso e equipe -->
    <div class="row g-3">
        <div class="col-12 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Acessos liberados</div>
                <div class="bo-card-value"><?php echo $admDashboard['acessosLiberados']; ?></div>
                <div class="bo-card-sub"><?php echo $admDashboard['totalUsuarios'] ? round($admDashboard['acessosLiberados'] / $admDashboard['totalUsuarios'] * 100) : 0; ?>% da base</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Acessos bloqueados</div>
                <div class="bo-card-value"><?php echo $admDashboard['acessosBloqueados']; ?></div>
                <div class="bo-card-sub"><?php echo $admDashboard['totalUsuarios'] ? round($admDashboard['acessosBloqueados'] / $admDashboard['totalUsuarios'] * 100) : 0; ?>% da base</div>
            </div>
        </div>
        <div class="col-12 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Profissionais ativos</div>
                <div class="bo-card-value"><?php echo $admDashboard['profissionaisAtivos']; ?></div>
                <div class="bo-card-sub"><?php echo $admDashboard['profissionaisPendentes']; ?> pendentes de contrato</div>
            </div>
        </div>
    </div>
</section>

<!-- ===== ADMIN · Usuários (lista + editar/ativar-inativar/excluir) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="usuarios">
    <div class="bo-page-title">
        <div>
            <h1>Usuários</h1>
            <p>Gerencie os usuários cadastrados na plataforma.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalUsuarioNovo">
            <i class="bi bi-plus-lg"></i> Novo Usuário
        </button>
    </div>
    <?php bo_modal_usuario(null, 'usuarios'); ?>

    <!-- Filtros: busca por texto + status (ligados à tabela pelo data-bo-target="usuarios") -->
    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:320px" placeholder="Buscar por ID, nome e email"
            data-bo-filter="search" data-bo-target="usuarios">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="usuarios">
            <option value="">Todos os status</option>
            <option value="ativo">Ativo</option>
            <option value="inativo">Inativo</option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="usuarios">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Status</th>
                        <th>Nº matrícula</th>
                        <th>Data inicial</th>
                        <th>Final de contrato</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr data-status="<?php echo $u['status']; ?>"
                            data-search="<?php echo strtolower($u['id'] . ' ' . $u['nome'] . ' ' . $u['email'] . ' ' . $u['cpf']); ?>">
                            <td>#<?php echo str_pad($u['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $u['nome']; ?></td>
                            <td><?php echo $u['email']; ?></td>
                            <td><?php echo bo_badge($u['status'] === 'ativo'); ?></td>
                            <td><?php echo $u['matricula']; ?></td>
                            <td><?php echo date('d/m/Y', strtotime($u['dataInicial'])); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($u['dataFinal'])); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalUsuarioEditar<?php echo $u['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php bo_form_toggle('usuarios', $u['id'], 'usuarios', $u['status'] === 'ativo'); ?>
                                    <?php echo bo_botao_excluir('usuarios', $u['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <!-- Linha exibida pelo JS quando o filtro não encontra nada -->
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="8">Nenhum usuário encontrado para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($usuarios as $u): ?>
        <?php bo_modal_usuario($u, 'usuarios'); ?>
        <?php bo_modal_confirmar_exclusao('usuarios', $u['id'], $u['nome'], 'usuarios'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Permissões (usuários com função administrativa) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="permissoes">
    <div class="bo-page-title">
        <div>
            <h1>Permissões</h1>
            <p>Controle os níveis de acesso concedidos aos usuários.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPermissaoNova">
            <i class="bi bi-plus-lg"></i> Cadastrar Permissão
        </button>
    </div>
    <?php bo_modal_permissao_nova('permissoes', $funcoes); ?>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="permissoes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Tipo de função</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissoes as $p): ?>
                        <tr data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome'] . ' ' . $p['email']); ?>">
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo $p['email']; ?></td>
                            <td><?php echo $p['funcaoLabel']; ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalPermissaoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('permissoes', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="5">Nenhuma permissão encontrada.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($permissoes as $p): ?>
        <?php bo_modal_permissao_editar($p, 'permissoes', $funcoes); ?>
        <?php bo_modal_confirmar_exclusao('permissoes', $p['id'], 'a permissão de ' . $p['nome'], 'permissoes'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Funções (tabela `funcao`, usada pela tela "Permissões") ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="funcoes">
    <div class="bo-page-title">
        <div>
            <h1>Funções</h1>
            <p>Gerencie as funções disponíveis para conceder permissões aos usuários.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalFuncaoNova">
            <i class="bi bi-plus-lg"></i> Nova Função
        </button>
    </div>
    <?php bo_modal_funcao(null, 'funcoes'); ?>

    <div class="bo-list">
        <?php foreach ($funcoes as $f): ?>
            <div class="bo-list-item">
                <div class="bo-list-title"><?php echo $f['nome']; ?></div>
                <div class="bo-table-actions">
                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalFuncaoEditar<?php echo $f['id']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php echo bo_botao_excluir('funcoes', $f['id']); ?>
                </div>
            </div>
            <?php bo_modal_funcao($f, 'funcoes'); ?>
            <?php bo_modal_confirmar_exclusao('funcoes', $f['id'], 'a função ' . $f['nome'], 'funcoes'); ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===== ADMIN · Pagamentos (recebimentos de mensalidade/produtos/taxas) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="pagamentos">
    <div class="bo-page-title">
        <div>
            <h1>Pagamentos</h1>
            <p>Acompanhe e registre os pagamentos recebidos.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPagamentoNovo">
            <i class="bi bi-plus-lg"></i> Registrar Pagamento
        </button>
    </div>
    <?php bo_modal_pagamento(null, 'pagamentos'); ?>

    <!-- Filtros: busca por ID, tipo de pagamento e intervalo de datas -->
    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:200px" placeholder="Buscar por ID"
            data-bo-filter="search" data-bo-target="pagamentos">
        <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="pagamentos">
            <option value="">Todos os tipos</option>
            <option value="PIX">PIX</option>
            <option value="Cartão">Cartão</option>
        </select>
        <div class="bo-daterange">
            De <input type="date" class="form-control" data-bo-filter="date-from" data-bo-target="pagamentos">
            até <input type="date" class="form-control" data-bo-filter="date-to" data-bo-target="pagamentos">
        </div>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="pagamentos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>ID usuário</th>
                        <th>Observação</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pagamentos as $p): ?>
                        <tr data-type="<?php echo $p['tipo']; ?>" data-date="<?php echo substr($p['data'], 0, 10); ?>"
                            data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id']); ?>">
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($p['data'])); ?></td>
                            <td><?php echo $p['tipo']; ?></td>
                            <td><?php echo bo_money($p['valor']); ?></td>
                            <td>#<?php echo str_pad($p['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['observacao']; ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalPagamentoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('pagamentos', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7">Nenhum pagamento encontrado para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($pagamentos as $p): ?>
        <?php bo_modal_pagamento($p, 'pagamentos'); ?>
        <?php bo_modal_confirmar_exclusao('pagamentos', $p['id'], 'o pagamento #' . $p['id'], 'pagamentos'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Cashbacks (saldo geral + lançamentos manuais) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="cashbacks">
    <div class="bo-page-title">
        <div>
            <h1>Cashbacks</h1>
            <p>Acompanhe saldo, distribuição e lançamentos de cashback.</p>
        </div>
        <div class="bo-actions">
            <button type="button" class="btn-bo-outline" data-bs-toggle="modal" data-bs-target="#modalCashbackMassa">
                <i class="bi bi-people"></i> Distribuição em Massa
            </button>
            <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalCashbackLancar">
                <i class="bi bi-plus-lg"></i> Lançar Cashback
            </button>
        </div>
    </div>
    <?php bo_modal_cashback_massa('cashbacks'); ?>
    <?php bo_modal_cashback_lancar('cashbacks'); ?>

    <!-- Cards de resumo: saldo total / distribuídos / debitado / creditado -->
    <div class="row g-3 mb-3">
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Saldo total</div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['saldoTotal']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Distribuídos</div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['distribuidos']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Debitado</div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['debitado']); ?></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="bo-card">
                <div class="bo-card-label">Creditado</div>
                <div class="bo-card-value"><?php echo bo_money($cashbackResumo['creditado']); ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:200px" placeholder="Buscar por ID"
            data-bo-filter="search" data-bo-target="cashbacks">
        <select class="form-select" style="max-width:180px" data-bo-filter="type" data-bo-target="cashbacks">
            <option value="">Todos os tipos</option>
            <option value="credito">Crédito</option>
            <option value="debito">Débito</option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="cashbacks">
                <thead>
                    <tr>
                        <th>ID transação</th>
                        <th>Data</th>
                        <th>Tipo</th>
                        <th>Valor</th>
                        <th>ID usuário</th>
                        <th>Motivo</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cashbackTransacoes as $c): ?>
                        <tr data-type="<?php echo $c['tipo']; ?>" data-search="<?php echo strtolower('#' . str_pad($c['id'], 4, '0', STR_PAD_LEFT) . ' ' . $c['id']); ?>">
                            <td>#<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($c['data'])); ?></td>
                            <td><?php echo $c['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
                            <td><?php echo bo_money($c['valor']); ?></td>
                            <td>#<?php echo str_pad($c['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $c['motivo']; ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php echo bo_botao_excluir('cashbacks', $c['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7">Nenhuma transação encontrada para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($cashbackTransacoes as $c): ?>
        <?php bo_modal_confirmar_exclusao('cashbacks', $c['id'], 'a transação #' . $c['id'], 'cashbacks'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Categorias (organização dos produtos da loja) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="categorias">
    <div class="bo-page-title">
        <div>
            <h1>Categorias</h1>
            <p>Organize as categorias de produtos da loja.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalCategoriaNova">
            <i class="bi bi-plus-lg"></i> Nova Categoria
        </button>
    </div>
    <?php bo_modal_categoria(null, 'categorias'); ?>

    <div class="bo-list">
        <?php foreach ($categorias as $c): ?>
            <div class="bo-list-item">
                <div class="bo-list-title"><?php echo $c['nome']; ?></div>
                <div class="bo-table-actions">
                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalCategoriaEditar<?php echo $c['id']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php echo bo_botao_excluir('categorias', $c['id']); ?>
                </div>
            </div>
            <?php bo_modal_categoria($c, 'categorias'); ?>
            <?php bo_modal_confirmar_exclusao('categorias', $c['id'], 'a categoria ' . $c['nome'], 'categorias'); ?>
        <?php endforeach; ?>
    </div>
</section>

<!-- ===== ADMIN · Produtos (catálogo da loja) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="produtos">
    <div class="bo-page-title">
        <div>
            <h1>Produtos</h1>
            <p>Gerencie o catálogo de produtos da loja.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalProdutoNovo">
            <i class="bi bi-plus-lg"></i> Cadastro de Produto
        </button>
    </div>
    <?php bo_modal_produto(null, 'produtos', $categoriasAtivasOptions); ?>

    <!-- Cards de resumo do catálogo -->
    <div class="row g-3 mb-3">
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label">Total cadastrados</div>
                <div class="bo-card-value"><?php echo $produtosResumo['total']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label">Disponíveis</div>
                <div class="bo-card-value"><?php echo $produtosResumo['disponiveis']; ?></div>
            </div>
        </div>
        <div class="col-4">
            <div class="bo-card">
                <div class="bo-card-label">Indisponíveis</div>
                <div class="bo-card-value"><?php echo $produtosResumo['indisponiveis']; ?></div>
            </div>
        </div>
    </div>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome ou ID"
            data-bo-filter="search" data-bo-target="produtos">
        <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="produtos">
            <option value="">Todos</option>
            <option value="disponivel">Disponível</option>
            <option value="indisponivel">Indisponível</option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="produtos">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Preço</th>
                        <th>Desconto</th>
                        <th>Valor final</th>
                        <th>Cashback</th>
                        <th>Estoque</th>
                        <th>Status</th>
                        <th>Ações</th>
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
                            <td><?php echo bo_badge($p['status'] === 'disponivel', 'Disponível', 'Indisponível'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('produtos', $p['id'], 'produtos', $p['status'] === 'disponivel', 'Disponível', 'Indisponível'); ?>
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProdutoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('produtos', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="10">Nenhum produto encontrado para os filtros selecionados.</td>
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
            <h1>Vendas Marketplace</h1>
            <p>Produtos, vendas e logística de todos os vendedores do marketplace.</p>
        </div>
    </div>

    <?php $admVendasStatusLabel = ['aguardando' => 'Aguardando', 'preparando' => 'Preparando', 'despachado' => 'Despachado', 'entregue' => 'Entregue', 'devolvido' => 'Devolvido', 'extraviado' => 'Extraviado']; ?>

    <ul class="nav nav-tabs bo-nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#admVendasTabProdutos" type="button" role="tab">Produtos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#admVendasTabVendas" type="button" role="tab">Vendas e logística</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#admVendasTabTransportadoras" type="button" role="tab">Transportadoras</button>
        </li>
    </ul>

    <div class="tab-content">
        <!-- ===== Produtos de todos os vendedores ===== -->
        <div class="tab-pane fade show active" id="admVendasTabProdutos" role="tabpanel">
            <div class="bo-filters mt-3">
                <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome, ID ou vendedor"
                    data-bo-filter="search" data-bo-target="admVendasProdutos">
                <select class="form-select" style="max-width:200px" data-bo-filter="status" data-bo-target="admVendasProdutos">
                    <option value="">Todos</option>
                    <option value="disponivel">Ativo</option>
                    <option value="indisponivel">Inativo / pausado</option>
                </select>
            </div>
            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="admVendasProdutos">
                        <thead>
                            <tr>
                                <th>Foto</th><th>ID</th><th>Nome</th><th>Vendedor</th><th>Preço</th><th>Estoque</th><th>Status</th><th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admVendasProdutos as $p): ?>
                                <tr data-status="<?php echo $p['status']; ?>"
                                    data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome'] . ' ' . $p['vendedor']); ?>">
                                    <td>
                                        <div class="bo-thumb">
                                            <?php if ($p['imagem']): ?><img src="<?php echo htmlspecialchars($p['imagem']); ?>" alt=""><?php else: ?><i class="bi bi-image"></i><?php endif; ?>
                                        </div>
                                    </td>
                                    <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                                    <td><?php echo $p['nome']; ?></td>
                                    <td><?php echo htmlspecialchars($p['vendedor']); ?></td>
                                    <td><?php echo bo_money($p['valorFinal']); ?></td>
                                    <td><?php echo $p['estoque']; ?></td>
                                    <td><?php echo bo_badge($p['status'] === 'disponivel', 'Ativo', 'Inativo'); ?></td>
                                    <td>
                                        <div class="bo-table-actions">
                                            <?php bo_form_toggle('produtos', $p['id'], 'vendas', $p['status'] === 'disponivel', 'Ativo', 'Inativo'); ?>
                                            <?php echo bo_botao_excluir('produtos', $p['id']); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" style="display:none"><td colspan="8">Nenhum produto encontrado para os filtros selecionados.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php foreach ($admVendasProdutos as $p): ?>
                <?php bo_modal_confirmar_exclusao('produtos', $p['id'], $p['nome'], 'vendas'); ?>
            <?php endforeach; ?>
        </div>

        <!-- ===== Vendas de todos os vendedores ===== -->
        <div class="tab-pane fade" id="admVendasTabVendas" role="tabpanel">
            <div class="bo-filters mt-3">
                <select class="form-select" style="max-width:220px" data-bo-filter="status" data-bo-target="admVendasVendas">
                    <option value="">Todos os status</option>
                    <?php foreach ($admVendasStatusLabel as $valor => $label): ?>
                        <option value="<?php echo $valor; ?>"><?php echo $label; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="bo-table-wrap">
                <div class="table-responsive">
                    <table class="bo-table" data-bo-table="admVendasVendas">
                        <thead>
                            <tr><th>Data</th><th>Produto</th><th>Vendedor</th><th>Comprador</th><th>Qtd.</th><th>Valor</th><th>Transportadora</th><th>Frete</th><th>Rastreio / Status</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach ($admVendas as $v): ?>
                                <tr data-status="<?php echo $v['statusLogistica']; ?>">
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
                                                    <option value="<?php echo $valor; ?>" <?php echo $v['statusLogistica'] === $valor ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <input type="text" class="form-control form-control-sm mt-1" name="codigo_rastreio" placeholder="Código de rastreio" value="<?php echo htmlspecialchars($v['codigoRastreio'] ?? ''); ?>">
                                            <button type="submit" class="btn-bo-outline btn-sm mt-1">Salvar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <tr class="bo-empty-row" <?php echo empty($admVendas) ? '' : 'style="display:none"'; ?>><td colspan="9">Nenhuma venda registrada ainda.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ===== Transportadoras (globais) ===== -->
        <div class="tab-pane fade" id="admVendasTabTransportadoras" role="tabpanel">
            <div class="bo-page-title mt-3">
                <div><p class="mb-0">Cadastradas pelo admin, disponíveis para todos os vendedores.</p></div>
                <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalTransportadoraNova">
                    <i class="bi bi-plus-lg"></i> Adicionar transportador
                </button>
            </div>
            <?php bo_modal_transportadora(null, 'vendas'); ?>

            <?php foreach ($transportadoras as $t): ?>
                <div class="bo-card mb-3">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong><?php echo htmlspecialchars($t['nome']); ?></strong>
                            <span class="bo-card-sub"><?php echo ucfirst($t['tipo']); ?></span>
                            <?php echo bo_badge($t['status'] === 'ativo', 'Ativo', 'Inativo'); ?>
                        </div>
                        <div class="bo-table-actions">
                            <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalTransportadoraEditar<?php echo $t['id']; ?>"><i class="bi bi-pencil"></i></button>
                            <?php bo_form_toggle('transportadoras', $t['id'], 'vendas', $t['status'] === 'ativo', 'Ativo', 'Inativo'); ?>
                            <?php echo bo_botao_excluir('transportadoras', $t['id']); ?>
                            <button type="button" class="btn-bo-outline btn-sm" data-bs-toggle="modal" data-bs-target="#modalFaixaCep<?php echo $t['id']; ?>"><i class="bi bi-plus-lg"></i> Faixa de CEP</button>
                        </div>
                    </div>
                    <?php if (!empty($t['faixas'])): ?>
                        <div class="table-responsive mt-2">
                            <table class="bo-table">
                                <thead><tr><th>CEP inicial</th><th>CEP final</th><th>Frete</th><th>Prazo</th><th></th></tr></thead>
                                <tbody>
                                    <?php foreach ($t['faixas'] as $f): ?>
                                        <tr>
                                            <td><?php echo $f['cepInicial']; ?></td>
                                            <td><?php echo $f['cepFinal']; ?></td>
                                            <td><?php echo bo_money($f['valorFrete']); ?></td>
                                            <td><?php echo $f['prazoDias']; ?> dia(s)</td>
                                            <td>
                                                <form method="POST" action="<?php echo bo_form_action('transportadoras.php'); ?>">
                                                    <?php echo bo_csrf_field(); ?>
                                                    <?php echo bo_hidden('secao', 'vendas'); ?>
                                                    <?php echo bo_hidden('acao', 'delete-faixa'); ?>
                                                    <?php echo bo_hidden('id_faixa', $f['id']); ?>
                                                    <button type="submit" class="btn-bo-icon danger" title="Remover faixa"><i class="bi bi-trash"></i></button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="bo-card-sub mt-2 mb-0">Nenhuma faixa de CEP cadastrada ainda.</p>
                    <?php endif; ?>
                </div>
                <?php bo_modal_transportadora($t, 'vendas'); ?>
                <?php bo_modal_faixa_cep($t['id'], 'vendas'); ?>
                <?php bo_modal_confirmar_exclusao('transportadoras', $t['id'], $t['nome'], 'vendas'); ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ===== ADMIN · Cadastro de Planos (planos de assinatura oferecidos) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="planos">
    <div class="bo-page-title">
        <div>
            <h1>Cadastro de Planos</h1>
            <p>Configure os planos de assinatura disponíveis.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalPlanoNovo">
            <i class="bi bi-plus-lg"></i> Novo Plano
        </button>
    </div>
    <?php bo_modal_plano(null, 'planos'); ?>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por plano ou ID"
            data-bo-filter="search" data-bo-target="planos">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="planos">
            <option value="">Todos</option>
            <option value="ativo">Ativo</option>
            <option value="inativo">Inativo</option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="planos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Plano</th>
                        <th>Valor</th>
                        <th>Ciclo</th>
                        <th>Descrição</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($planos as $p): ?>
                        <tr data-status="<?php echo $p['status']; ?>"
                            data-search="<?php echo strtolower('#' . str_pad($p['id'], 4, '0', STR_PAD_LEFT) . ' ' . $p['id'] . ' ' . $p['nome']); ?>">
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo bo_money($p['valor']); ?></td>
                            <td><?php echo $p['ciclo']; ?></td>
                            <td><?php echo $p['descricao']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('planos', $p['id'], 'planos', $p['status'] === 'ativo'); ?>
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalPlanoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7">Nenhum plano encontrado para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($planos as $p): ?>
        <?php bo_modal_plano($p, 'planos'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Profissionais (equipe cadastrada na plataforma) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="profissionais">
    <div class="bo-page-title">
        <div>
            <h1>Profissionais</h1>
            <p>Gerencie os profissionais cadastrados na plataforma.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalProfissionalNovo">
            <i class="bi bi-plus-lg"></i> Novo Profissional
        </button>
    </div>
    <?php bo_modal_profissional(null, 'profissionais', $modalidadesOptions); ?>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por ID, nome, função ou doc"
            data-bo-filter="search" data-bo-target="profissionais">
        <select class="form-select" style="max-width:180px" data-bo-filter="status" data-bo-target="profissionais">
            <option value="">Todos</option>
            <option value="ativo">Ativo</option>
            <option value="inativo">Inativo</option>
        </select>
    </div>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="profissionais">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>ID</th>
                        <th>Nome</th>
                        <th>Função</th>
                        <th>Modalidades</th>
                        <th>Documento</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($profissionaisAdm as $p): ?>
                        <tr data-status="<?php echo $p['status']; ?>"
                            data-search="<?php echo strtolower($p['nome'] . ' ' . $p['funcao'] . ' ' . $p['documento']); ?>">
                            <td>
                                <div class="bo-thumb">
                                    <?php if ($p['foto']): ?>
                                        <img src="<?php echo htmlspecialchars($p['foto']); ?>" alt="">
                                    <?php else: ?>
                                        <i class="bi bi-person"></i>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>#<?php echo str_pad($p['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo $p['funcao']; ?></td>
                            <td><?php echo $p['modalidades'] !== '' ? $p['modalidades'] : '—'; ?></td>
                            <td><?php echo $p['documento']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProfissionalEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_botao_excluir('profissionais', $p['id']); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="8">Nenhum profissional encontrado para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($profissionaisAdm as $p): ?>
        <?php bo_modal_profissional($p, 'profissionais', $modalidadesOptions); ?>
        <?php bo_modal_confirmar_exclusao('profissionais', $p['id'], $p['nome'], 'profissionais'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Modalidades (tabela `modalidades`, usada no cadastro de profissionais) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="modalidades">
    <div class="bo-page-title">
        <div>
            <h1>Modalidades</h1>
            <p>Gerencie as modalidades oferecidas pela academia.</p>
        </div>
        <button type="button" class="btn-bo-gold" data-bs-toggle="modal" data-bs-target="#modalModalidadeNova">
            <i class="bi bi-plus-lg"></i> Nova Modalidade
        </button>
    </div>
    <?php bo_modal_modalidade(null, 'modalidades'); ?>

    <div class="bo-list">
        <?php foreach ($modalidadesAdm as $m): ?>
            <div class="bo-list-item">
                <div class="bo-list-title"><?php echo $m['nome']; ?></div>
                <div class="bo-table-actions">
                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalModalidadeEditar<?php echo $m['id']; ?>">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <?php echo bo_botao_excluir('modalidades', $m['id']); ?>
                </div>
            </div>
            <?php bo_modal_modalidade($m, 'modalidades'); ?>
            <?php bo_modal_confirmar_exclusao('modalidades', $m['id'], 'a modalidade ' . $m['nome'], 'modalidades'); ?>
        <?php endforeach; ?>
    </div>
</section>
