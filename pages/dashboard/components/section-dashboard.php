<?php
$boSections = [
    'admin' => [
        'dashboard' => ['Dashboard', 'Um resumo da sua operação, sempre baseado em dados reais.', 'bi-speedometer2'],
        'usuarios' => ['Usuários', 'Os usuários cadastrados aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-people'],
        'permissoes' => ['Permissões', 'As permissões cadastradas aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-shield-lock'],
        'funcoes' => ['Funções', 'As funções cadastradas aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-diagram-3'],
        'pagamentos' => ['Pagamentos', 'As movimentações reais aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-credit-card'],
        'cashbacks' => ['Cashbacks', 'O saldo e o extrato reais aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-wallet2'],
        'categorias' => ['Categorias', 'As categorias cadastradas aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-tags'],
        'produtos' => ['Produtos', 'Os produtos cadastrados aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-box-seam'],
        'planos' => ['Cadastro de planos', 'Os planos cadastrados aparecerão aqui quando a integração com o banco estiver disponível.', 'bi-clipboard-check'],
        'profissionais' => ['Profissionais', 'A equipe cadastrada aparecerá aqui quando a integração com o banco estiver disponível.', 'bi-person-badge'],
    ],
    'profissional' => [
        'dashboard' => ['Dashboard', 'Acompanhe sua rotina com informações que vêm diretamente do sistema.', 'bi-speedometer2'],
        'historico' => ['Histórico', 'Seu histórico real ficará disponível aqui.', 'bi-clock-history'],
        'alunos' => ['Alunos', 'Os alunos vinculados a você aparecerão aqui.', 'bi-people'],
        'agenda' => ['Agenda', 'Seus horários e agendamentos reais aparecerão aqui.', 'bi-calendar3'],
        'cashback' => ['Meu cashback', 'Seu saldo e extrato reais aparecerão aqui.', 'bi-wallet2'],
        'compras' => ['Minhas compras', 'Seus pedidos reais aparecerão aqui.', 'bi-bag-check'],
    ],
    'aluno' => [
        'perfil' => ['Meu perfil', 'Suas informações cadastrais reais aparecerão aqui.', 'bi-person-circle'],
        'historico' => ['Histórico', 'Seu histórico de pagamentos e movimentações aparecerá aqui.', 'bi-clock-history'],
        'cashback' => ['Cashback', 'Seu saldo e extrato reais aparecerão aqui.', 'bi-wallet2'],
        'compras' => ['Minhas compras', 'Seus pedidos reais aparecerão aqui.', 'bi-bag-check'],
        'treino' => ['Treino', 'Seu treino atual aparecerá aqui quando estiver vinculado ao seu cadastro.', 'bi-lightning-charge'],
        'agenda' => ['Minha agenda', 'Seus horários reais aparecerão aqui.', 'bi-calendar3'],
    ],
];

foreach ($boSections[$perfilLogado] as $key => [$title, $description, $icon]):
?>
<section class="bo-content-section" data-perfil="<?php echo $perfilLogado; ?>" data-section="<?php echo $key; ?>">
    <?php if ($key === 'dashboard'): ?>
        <div class="bo-page-title bo-page-title-modern"><div><span class="bo-eyebrow"><i class="bi bi-sun"></i> Visão geral</span><h1><?php echo $title; ?></h1><p><?php echo $description; ?></p></div></div>
        <div class="bo-notice"><i class="bi bi-database"></i><div><strong>Conectando seus dados</strong><span>Os indicadores serão preenchidos assim que a leitura do banco de dados for integrada.</span></div></div>
        <div class="bo-metric-grid">
            <article class="bo-metric-card"><span class="bo-metric-icon"><i class="bi bi-graph-up-arrow"></i></span><div><span class="bo-card-label">Receita</span><strong>—</strong><small>Aguardando dados reais</small></div></article>
            <article class="bo-metric-card"><span class="bo-metric-icon"><i class="bi bi-people"></i></span><div><span class="bo-card-label">Pessoas ativas</span><strong>—</strong><small>Aguardando dados reais</small></div></article>
            <article class="bo-metric-card"><span class="bo-metric-icon"><i class="bi bi-calendar3"></i></span><div><span class="bo-card-label">Agenda</span><strong>—</strong><small>Aguardando dados reais</small></div></article>
            <article class="bo-metric-card"><span class="bo-metric-icon"><i class="bi bi-wallet2"></i></span><div><span class="bo-card-label">Cashback</span><strong>—</strong><small>Aguardando dados reais</small></div></article>
        </div>
        <div class="bo-data-panel bo-empty-panel"><i class="bi bi-bar-chart-line"></i><h2>Sem dados para exibir</h2><p>Não mostramos números de exemplo. Quando o banco estiver conectado, os indicadores e gráficos aparecerão aqui.</p></div>
    <?php else: ?>
        <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-sun"></i> ONE FIT</span><h1><?php echo $title; ?></h1><p><?php echo $description; ?></p></div></div>
        <div class="bo-data-panel bo-empty-panel"><i class="bi <?php echo $icon; ?>"></i><h2>Nenhum dado carregado</h2><p>Esta área está pronta para receber os dados reais do banco.</p></div>
    <?php endif; ?>
</section>
<?php endforeach; ?>
