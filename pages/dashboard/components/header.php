<header class="bo-header">
    <div class="d-flex align-items-center gap-2">
        <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="bo-header-search-wrap">
            <label class="bo-header-search" for="boHeaderSearch">
                <i class="bi bi-search"></i>
                <input id="boHeaderSearch" type="search" placeholder="Pesquisar no painel" aria-label="Pesquisar no painel" autocomplete="off">
                <kbd>Ctrl K</kbd>
            </label>
        </div>
    </div>

    <div class="bo-user">
        <div class="bo-user-menu-wrap" id="boUserMenuWrap">
            <button class="bo-avatar" id="boAvatar" type="button" aria-label="Abrir menu do usuário" aria-expanded="false">
                <?php echo strtoupper(substr($_SESSION['nome'] ?? $perfilLogado, 0, 1)); ?>
            </button>
            <div class="bo-user-menu" id="boUserMenu" role="menu" aria-hidden="true">
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php" role="menuitem"><i class="bi bi-person"></i> Meu painel</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=alterar-senha" role="menuitem"><i class="bi bi-key"></i> Alterar senha</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=configuracoes" role="menuitem"><i class="bi bi-gear"></i> Configurações</a>
                <a href="<?php echo BASE_URL; ?>config/logout.php" id="boLogoutLink" role="menuitem" class="bo-user-menu-logout"><i class="bi bi-box-arrow-right"></i> Sair</a>
            </div>
        </div>
    </div>
</header>
