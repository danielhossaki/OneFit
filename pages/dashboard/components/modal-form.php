<?php
/**
 * components/modal-form.php
 * Modal ÚNICO e genérico usado para TODOS os formulários de
 * cadastro/edição do backoffice (usuário, plano, produto, profissional,
 * cashback, agenda, etc). O conteúdo do <form id="boFormModalForm"> é
 * montado dinamicamente pelo JS (função boOpenForm(), em backoffice.js)
 * de acordo com o "schema" da tela que chamou o modal — por isso ele
 * começa vazio aqui e não tem nenhum campo fixo.
 *
 * O <div id="boToast"> é o aviso flutuante ("Alterações salvas.", etc.)
 * mostrado depois de qualquer ação (salvar, excluir, exportar...).
 */
?>
<div class="modal fade bo-modal" id="boFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="boFormModalTitle">Formulário</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body">
                <!-- Campos inseridos aqui via JS (boBuildField / boOpenForm) -->
                <form id="boFormModalForm" class="row g-3" onsubmit="return false;"></form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-bo-outline" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn-bo-gold" id="boFormModalSave">Salvar</button>
            </div>
        </div>
    </div>
</div>

<div class="bo-toast" id="boToast"></div>
