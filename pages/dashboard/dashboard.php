<?php
/**
 * dashboard.php
 * Página principal do backoffice. Esse arquivo só faz a "montagem":
 * carrega os dados (mock-data.php), as funções auxiliares (helpers.php)
 * e inclui, em ordem, cada pedaço de HTML (components/*.php). Toda a
 * regra de exibição/estilo/interação vive nos outros arquivos:
 *
 *   assets/css/backoffice.css   -> estilos
 *   assets/js/backoffice.js     -> perfis, menu, modal, filtros, IMC, pagamento
 *   includes/helpers.php        -> bo_badge(), bo_money(), bo_json()
 *   includes/mock-data.php      -> dados de exemplo (trocar por consultas ao banco)
 *   components/header.php       -> barra do topo
 *   components/sidebar.php      -> menu lateral
 *   components/section-*.php    -> telas de cada perfil (admin/profissional/aluno)
 *   components/modal-*.php      -> modais (formulário genérico e pagamento)
 */

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');


require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');

require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/mock-data.php';
?>


<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONE FIT - Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/backoffice.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body>

    <?php require __DIR__ . '/components/header.php'; ?>
    <?php require __DIR__ . '/components/sidebar.php'; ?>

    <main class="bo-main">
        <?php require __DIR__ . '/components/section-admin.php'; ?>
        <?php require __DIR__ . '/components/section-profissional.php'; ?>
        <?php require __DIR__ . '/components/section-aluno.php'; ?>
        <?php require __DIR__ . '/components/section-stub.php'; ?>
    </main>

    <?php require __DIR__ . '/components/modal-form.php'; ?>
    <?php require __DIR__ . '/components/modal-pagar-plano.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Essas duas listas vêm do PHP (categorias e planos cadastrados) e
        // precisam existir ANTES de backoffice.js carregar, porque os
        // schemas de formulário (BO_FORM_SCHEMAS.produtoForm e .planoAlterar)
        // usam elas direto ao montar os <select> de categoria/plano.
        const BO_CATEGORIAS_OPTIONS = [<?php echo implode(', ', array_map(fn($c) => '"' . $c['nome'] . '"', $categorias)); ?>];
        const BO_PLANOS_OPTIONS = [<?php echo implode(', ', array_map(fn($p) => '"' . $p['nome'] . '"', $planos)); ?>];
    </script>

    <script src="<?php echo BASE_URL; ?>assets/js/backoffice.js"></script>

</body>

</html>
