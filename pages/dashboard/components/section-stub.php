<?php
/**
 * components/section-stub.php
 * Seção "coringa" — não pertence a nenhum perfil específico (por isso não
 * tem data-perfil/data-section). O JS (boGoToSection, em backoffice.js)
 * mostra ela sempre que o item de menu clicado não tem uma <section>
 * própria montada para o perfil atual, preenchendo título/descrição/ícone
 * dinamicamente antes de exibir.
 */
?>
<section class="bo-content-section" id="boStubSection">
    <div class="bo-page-title">
        <div>
            <h1 id="boStubTitle"></h1>
            <p id="boStubDesc"></p>
        </div>
    </div>
    <div class="bo-stub">
        <i class="bi" id="boStubIcon"></i>
        <h2>Em construção</h2>
        <p>Esta seção está no roteiro do backoffice e será implementada em uma próxima etapa.</p>
    </div>
</section>
