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
        <input type="text" class="form-control" style="max-width:320px" placeholder="Buscar por nome, email, CPF ou ID"
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
                                    <?php echo bo_link_excluir('usuarios', $u['id'], $u['nome'], 'usuarios'); ?>
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
    <?php bo_modal_permissao_nova('permissoes'); ?>

    <div class="bo-table-wrap">
        <div class="table-responsive">
            <table class="bo-table" data-bo-table="permissoes">
                <thead>
                    <tr>
                        <th>ID usuário</th>
                        <th>Nome</th>
                        <th>E-mail</th>
                        <th>Tipo de função</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($permissoes as $p): ?>
                        <tr data-search="<?php echo strtolower($p['usuarioId'] . ' ' . $p['nome'] . ' ' . $p['email']); ?>">
                            <td>#<?php echo str_pad($p['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $p['nome']; ?></td>
                            <td><?php echo $p['email']; ?></td>
                            <td><?php echo $p['funcaoLabel']; ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalPermissaoEditar<?php echo $p['usuarioId']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_link_excluir('permissoes', $p['usuarioId'], 'a permissão de ' . $p['nome'], 'permissoes'); ?>
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
        <?php bo_modal_permissao_editar($p, 'permissoes'); ?>
    <?php endforeach; ?>
</section>

<!-- ===== ADMIN · Funções (legenda fixa: são os tipos de acesso do banco, não uma tabela editável) ===== -->
<section class="bo-content-section" data-perfil="admin" data-section="funcoes">
    <div class="bo-page-title">
        <div>
            <h1>Funções</h1>
            <p>Referência dos níveis de acesso do sistema. Para elevar ou revogar o acesso de um usuário, use a tela "Permissões".</p>
        </div>
    </div>

    <div class="bo-list">
        <?php foreach ($funcoes as $f): ?>
            <div class="bo-list-item">
                <div>
                    <div class="bo-list-title"><?php echo $f['nome']; ?></div>
                    <div class="bo-list-sub"><?php echo $f['permissoes']; ?></div>
                </div>
            </div>
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
                        <tr data-type="<?php echo $p['tipo']; ?>" data-date="<?php echo $p['data']; ?>"
                            data-search="<?php echo strtolower($p['id']); ?>">
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
                                    <?php echo bo_link_excluir('pagamentos', $p['id'], 'o pagamento #' . $p['id'], 'pagamentos'); ?>
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
                        <tr data-type="<?php echo $c['tipo']; ?>" data-search="<?php echo strtolower($c['id']); ?>">
                            <td>#<?php echo str_pad($c['id'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($c['data'])); ?></td>
                            <td><?php echo $c['tipo'] === 'credito' ? 'Crédito' : 'Débito'; ?></td>
                            <td><?php echo bo_money($c['valor']); ?></td>
                            <td>#<?php echo str_pad($c['usuarioId'], 4, '0', STR_PAD_LEFT); ?></td>
                            <td><?php echo $c['motivo']; ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php echo bo_link_excluir('cashbacks', $c['id'], 'a transação #' . $c['id'], 'cashbacks'); ?>
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
                    <?php echo bo_link_excluir('categorias', $c['id'], 'a categoria ' . $c['nome'], 'categorias'); ?>
                </div>
            </div>
            <?php bo_modal_categoria($c, 'categorias'); ?>
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
                            data-search="<?php echo strtolower($p['id'] . ' ' . $p['nome']); ?>">
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
                            <td><?php echo $p['cashback']; ?>%</td>
                            <td><?php echo $p['estoque']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'disponivel', 'Disponível', 'Indisponível'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <?php bo_form_toggle('produtos', $p['id'], 'produtos', $p['status'] === 'disponivel', 'Disponível', 'Indisponível'); ?>
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProdutoEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_link_excluir('produtos', $p['id'], $p['nome'], 'produtos'); ?>
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
    <?php endforeach; ?>
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
                            data-search="<?php echo strtolower($p['id'] . ' ' . $p['nome']); ?>">
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
    <?php bo_modal_profissional(null, 'profissionais'); ?>

    <div class="bo-filters">
        <input type="text" class="form-control" style="max-width:280px" placeholder="Buscar por nome, função ou documento"
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
                            <td><?php echo $p['documento']; ?></td>
                            <td><?php echo bo_badge($p['status'] === 'ativo'); ?></td>
                            <td>
                                <div class="bo-table-actions">
                                    <button type="button" class="btn-bo-icon" title="Editar" data-bs-toggle="modal" data-bs-target="#modalProfissionalEditar<?php echo $p['id']; ?>">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <?php echo bo_link_excluir('profissionais', $p['id'], $p['nome'], 'profissionais'); ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="bo-empty-row" style="display:none">
                        <td colspan="7">Nenhum profissional encontrado para os filtros selecionados.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php foreach ($profissionaisAdm as $p): ?>
        <?php bo_modal_profissional($p, 'profissionais'); ?>
    <?php endforeach; ?>
</section>
