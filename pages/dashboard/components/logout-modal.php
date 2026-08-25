<div class="modal fade bo-modal bo-logout-modal" id="boLogoutModal" tabindex="-1" aria-labelledby="boLogoutModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="btn-close bo-logout-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            <div class="bo-logout-body">
                <span class="bo-logout-icon" aria-hidden="true"><i class="bi bi-box-arrow-right"></i></span>
                <h5 id="boLogoutModalTitle">Encerrar sessão?</h5>
                <p>Você precisará entrar novamente para acessar o painel da ONE FIT neste dispositivo.</p>
            </div>
            <div class="bo-logout-actions">
                <button type="button" class="bo-logout-cancel" data-bs-dismiss="modal">Ficar no painel</button>
                <a class="bo-logout-confirm" href="<?php echo BASE_URL; ?>config/logout.php">Sim, sair <i class="bi bi-arrow-right"></i></a>
            </div>
        </div>
    </div>
</div>
