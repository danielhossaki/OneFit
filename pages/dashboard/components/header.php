<?php
/**
 * components/header.php
 * Barra fixa no topo do backoffice. Contém:
 *  - botão hambúrguer (só aparece no mobile, abre/fecha a sidebar)
 *  - logo da ONE FIT
 *  - dropdown para alternar entre os perfis Administrador/Profissional/Aluno
 *    (usado para visualizar/testar as 3 telas sem precisar logar com 3
 *    contas diferentes; o item ativo é preenchido pelo JS em boRenderPerfilMenu())
 *  - avatar com a foto salva do aluno ou a inicial do perfil atual
 */
$fotoAvatarAluno = '';
if ($perfilLogado === 'aluno') {
    require_once __DIR__ . '/../includes/aluno-profile.php';
    $fotoAvatarAluno = bo_aluno_foto_url($usuarioDashboard['foto'] ?? null);
}
?>
<header class="bo-header">
    <div class="d-flex align-items-center gap-2">
        <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="bo-header-search-wrap" id="boHeaderSearchWrap">
            <label class="bo-header-search" for="boHeaderSearch">
                <i class="bi bi-search"></i>
                <input id="boHeaderSearch" type="search" placeholder="Pesquisar no painel" aria-label="Pesquisar no painel" autocomplete="off" aria-autocomplete="list" aria-controls="boSearchResults" aria-expanded="false">
                <kbd>Ctrl K</kbd>
            </label>
            <div class="bo-search-results" id="boSearchResults" role="listbox" aria-label="Resultados da pesquisa" hidden></div>
        </div>
    </div>

    <div class="bo-user">
        <div class="bo-notifications-wrap" id="boNotificationsWrap" data-url="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/actions/notificacoes.php', ENT_QUOTES, 'UTF-8'); ?>">
            <button class="bo-avatar bo-notifications-toggle" id="boNotificationsToggle" type="button" aria-label="Abrir notificações" aria-expanded="false" aria-controls="boNotificationsPanel">
                <i class="bi bi-bell" aria-hidden="true"></i>
                <span class="bo-notifications-count" id="boNotificationsCount" aria-hidden="true" hidden></span>
            </button>
            <section class="bo-notifications-panel" id="boNotificationsPanel" aria-labelledby="boNotificationsTitle" tabindex="-1" hidden>
                <div class="bo-notifications-heading">
                    <h2 id="boNotificationsTitle">Notificações</h2>
                    <button type="button" id="boNotificationsReadAll" disabled>Marcar todas como lidas</button>
                </div>
                <ul class="bo-notifications-list" id="boNotificationsList"></ul>
                <p class="bo-notifications-empty" id="boNotificationsEmpty" hidden>Nenhuma notificação por enquanto</p>
                <p class="bo-notifications-feedback" id="boNotificationsFeedback" role="status">Carregando notificações…</p>
                <span class="visually-hidden" id="boNotificationsStatus" role="status"></span>
            </section>
        </div>
        <div class="bo-user-menu-wrap" id="boUserMenuWrap">
            <button class="bo-avatar<?php echo $perfilLogado === 'aluno' ? ' bo-student-avatar' : ''; ?>" id="boAvatar" type="button" aria-label="Abrir menu do usuário" aria-expanded="false" aria-controls="boUserMenu">
                <?php echo htmlspecialchars(strtoupper(substr($_SESSION['nome'] ?? $perfilLogado, 0, 1)), ENT_QUOTES, 'UTF-8'); ?>
                <?php if ($fotoAvatarAluno): ?>
                    <img src="<?php echo htmlspecialchars($fotoAvatarAluno, ENT_QUOTES, 'UTF-8'); ?>" alt="" data-student-photo>
                <?php endif; ?>
            </button>
            <div class="bo-user-menu" id="boUserMenu" role="menu" aria-hidden="true">
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=perfil" role="menuitem"><i class="bi bi-person"></i> Editar perfil</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/alterar-senha.php" role="menuitem"><i class="bi bi-key"></i> Alterar senha</a>
                <a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php?section=configuracoes" role="menuitem"><i class="bi bi-gear"></i> Configurações</a>
                <a href="<?php echo BASE_URL; ?>config/logout.php" role="menuitem" class="bo-user-menu-logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </div>
</header>
