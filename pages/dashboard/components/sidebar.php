<?php
/**
 * components/sidebar.php
 * Menu lateral fixo. Os itens (<button class="bo-nav-item">) NÃO ficam
 * fixos no HTML — são montados dinamicamente pelo JS (boRenderSidebar())
 * de acordo com o perfil ativo (admin/profissional/aluno), usando a lista
 * BO_PERFIS definida em assets/js/backoffice.js. Aqui só existe o
 * container vazio (<nav id="boNav">) onde o JS injeta os botões.
 *
 * O "backdrop" é a camada escura que aparece atrás da sidebar quando ela
 * é aberta no celular (clicar nela fecha o menu — ver backoffice.js).
 */
?>
<div class="bo-sidebar-backdrop" id="boSidebarBackdrop"></div>

<aside class="bo-sidebar" id="boSidebar">
    <nav class="bo-nav" id="boNav"></nav>
</aside>
