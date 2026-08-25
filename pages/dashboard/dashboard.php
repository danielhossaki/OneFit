<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');

$perfilLogado = $_SESSION['tipo_usuario'] ?? 'aluno';
if (!in_array($perfilLogado, ['admin', 'profissional', 'aluno'], true)) {
    $perfilLogado = 'aluno';
}

?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo painel · ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/dashboard.css">
    <script>
        try { document.documentElement.dataset.theme = localStorage.getItem('onefit-theme') || 'dark'; } catch (error) {}
    </script>
</head>
<body>
    <?php require __DIR__ . '/components/header.php'; ?>
    <?php require __DIR__ . '/components/sidebar.php'; ?>

    <main class="bo-main" id="dashboardMain">
        <section class="bo-welcome" aria-labelledby="boWelcomeTitle">
            <span class="bo-welcome-eyebrow"><i class="bi bi-stars"></i> ONE FIT</span>
            <h1 id="boWelcomeTitle">Bem-vindo de volta, <span><?php echo htmlspecialchars(explode(' ', trim($_SESSION['nome'] ?? 'Atleta'))[0], ENT_QUOTES, 'UTF-8'); ?></span>!</h1>
            <p>Que bom ter você aqui. Vamos transformar mais um dia em progresso.</p>
        </section>
    </main>

    <?php require __DIR__ . '/components/logout-modal.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo BASE_URL; ?>assets/js/dashboard.js"></script>
</body>
</html>
