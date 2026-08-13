<?php
/**
 * components/header.php
 * Barra fixa no topo do backoffice. Contém:
 *  - botão hambúrguer (só aparece no mobile, abre/fecha a sidebar)
 *  - logo da ONE FIT
 *  - dropdown para alternar entre os perfis Administrador/Profissional/Aluno
 *    (usado para visualizar/testar as 3 telas sem precisar logar com 3
 *    contas diferentes; o item ativo é preenchido pelo JS em boRenderPerfilMenu())
 *  - avatar com a inicial do perfil atual
 */
?>
<header class="bo-header">
    <div class="d-flex align-items-center gap-2">
        <button class="bo-sidebar-toggle d-lg-none" id="boSidebarToggle" aria-label="Abrir menu">
            <i class="bi bi-list"></i>
        </button>
        <div class="bo-logo">
            <img src="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" alt="Logo One Fit">
            <span>One Fit</span>
        </div>
    </div>

    <div class="bo-user">
        <div class="dropdown bo-perfil-switch">
            <button class="btn-bo-outline dropdown-toggle" type="button" id="boPerfilBtn"
                data-bs-toggle="dropdown" aria-expanded="false">
                <i class="bi bi-person-badge"></i>
                <span id="boPerfilLabel">Administrador</span>
            </button>
            <!-- Itens do dropdown (Administrador/Profissional/Aluno) são gerados
                 dinamicamente pelo JS em boRenderPerfilMenu() -->
            <ul class="dropdown-menu dropdown-menu-end bo-perfil-menu" id="boPerfilMenu" aria-labelledby="boPerfilBtn"></ul>
        </div>
        <div class="bo-avatar" id="boAvatar">A</div>
    </div>
</header>
