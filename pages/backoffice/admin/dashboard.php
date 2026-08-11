<?php
$pageTitle = 'Visão Geral';
$activeMenu = 'dashboard';
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/components/backoffice/layout_top.php');

$cards = [
    ['label' => 'Usuários ativos', 'value' => '482', 'sub' => '+18 este mês'],
    ['label' => 'Saldo operacional (mês)', 'value' => 'R$ 38.240,00', 'sub' => 'R$ 1.940 no dia'],
    ['label' => 'Cashback distribuído', 'value' => 'R$ 6.512,90', 'sub' => 'R$ 22.140 no ano'],
];

$cards2 = [
    ['label' => 'Acessos liberados', 'value' => '463', 'sub' => '19 bloqueados'],
    ['label' => 'Profissionais ativos', 'value' => '27', 'sub' => '3 pendentes de contrato'],
];
?>

<div class="bo-page-title">
    <div>
        <h1>Dashboard</h1>
        <p>Resumo geral da operação ONE FIT.</p>
    </div>
</div>

<div class="row g-3 mb-3">
    <?php foreach ($cards as $card): ?>
        <div class="col-12 col-md-4">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo $card['label']; ?></div>
                <div class="bo-card-value"><?php echo $card['value']; ?></div>
                <div class="bo-card-sub"><?php echo $card['sub']; ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <?php foreach ($cards2 as $card): ?>
        <div class="col-12 col-md-6">
            <div class="bo-card">
                <div class="bo-card-label"><?php echo $card['label']; ?></div>
                <div class="bo-card-value"><?php echo $card['value']; ?></div>
                <div class="bo-card-sub"><?php echo $card['sub']; ?></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/components/backoffice/layout_bottom.php'); ?>
