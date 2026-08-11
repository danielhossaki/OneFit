<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/session.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/components/backoffice/menu.php');

$perfil = $_SESSION['perfil'];
$menuAtual = $boMenus[$perfil];
$iniciais = mb_strtoupper(mb_substr($_SESSION['nome'], 0, 1));
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> — ONE FIT</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/backoffice.css">
    <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body class="bo-body">

    <header class="bo-header">
        <div class="d-flex align-items-center gap-2">
            <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
                <i class="bi bi-list"></i>
            </button>
            <div class="bo-logo">
                <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit">
                <span>One Fit · Backoffice</span>
            </div>
        </div>

        <div class="bo-user">
            <span class="bo-user-role"><?php echo ucfirst($perfil); ?></span>
            <div class="bo-avatar"><?php echo $iniciais; ?></div>
        </div>
    </header>

    <div class="bo-sidebar-backdrop" id="boSidebarBackdrop"></div>

    <aside class="bo-sidebar" id="boSidebar">
        <nav class="bo-nav">
            <?php foreach ($menuAtual as $item): ?>
                <a href="<?php echo $item['href']; ?>" class="bo-nav-item<?php echo ($activeMenu ?? '') === $item['key'] ? ' active' : ''; ?>">
                    <i class="bi <?php echo $item['icon']; ?>"></i>
                    <span><?php echo $item['label']; ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <a href="<?php echo BASE_URL; ?>index.php" class="bo-nav-item bo-nav-exit">
            <i class="bi bi-box-arrow-left"></i>
            <span>Sair</span>
        </a>
    </aside>

    <main class="bo-main">
