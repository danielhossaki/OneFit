<?php
$faceProfessionalMode = ($perfilLogado ?? '') === 'profissional';
?>
<section class="bo-content-section"
    data-perfil="<?php echo $faceProfessionalMode ? 'profissional' : 'admin'; ?>"
    data-section="cadastro-facial">
    <div class="bo-page-title">
        <div>
            <h1><?php echo of_t('Cadastro facial de usuários'); ?></h1>
            <p><?php echo of_t('Selecione quem será identificado pela catraca e capture sua foto facial.'); ?></p>
        </div>
    </div>

    <div class="bo-notice">
        <i class="bi bi-shield-lock"></i>
        <div>
            <strong><?php echo of_t('Dado biométrico sensível'); ?></strong>
            <span><?php echo of_t('As imagens ficam armazenadas de forma privada no banco de dados e não são disponibilizadas por URL.'); ?></span>
        </div>
    </div>

    <ol class="bo-face-steps" aria-label="<?php echo of_t('Etapas do cadastro facial'); ?>">
        <li><span>1</span><?php echo of_t('Selecionar pessoa'); ?></li>
        <li><span>2</span><?php echo of_t('Capturar rosto'); ?></li>
        <li><span>3</span><?php echo of_t('Confirmar cadastro'); ?></li>
    </ol>

    <div class="bo-face-layout"
        data-face-capture
        data-face-api="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/actions/fotos-faciais.php', ENT_QUOTES, 'UTF-8'); ?>"
        data-face-csrf="<?php echo htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="bo-face-panel" data-face-directory>
            <h2><?php echo of_t('Etapa 1 — Selecionar pessoa'); ?></h2>
            <label class="visually-hidden" for="boFaceSearch"><?php echo of_t('Pesquisar por nome, CPF ou identificador'); ?></label>
            <input class="form-control bo-face-search" id="boFaceSearch" type="search"
                placeholder="<?php echo of_t('Pesquisar por nome, CPF ou ID'); ?>" autocomplete="off">
            <div class="bo-face-list" id="boFacePeople" role="group" aria-label="<?php echo of_t('Usuários cadastrados'); ?>" aria-live="polite">
                <p class="bo-face-muted"><?php echo of_t('Carregando usuários…'); ?></p>
            </div>
        </div>

        <div class="bo-face-panel">
            <h2><?php echo of_t('Etapa 2 — Capturar rosto'); ?></h2>
            <div class="bo-face-selected">
                <div>
                    <h3 id="boFaceSelectedName"><?php echo of_t('Ninguém selecionado'); ?></h3>
                    <div class="bo-face-muted" id="boFaceSelectedId"></div>
                </div>
                <span class="badge text-bg-secondary" id="boFaceStatus"><?php echo of_t('Pendente'); ?></span>
            </div>

            <div class="bo-face-camera" id="boFaceCamera">
                <div class="bo-face-placeholder" id="boFacePlaceholder">
                    <i class="bi bi-camera-video" aria-hidden="true"></i>
                    <span><?php echo of_t('Selecione uma pessoa para habilitar a câmera.'); ?></span>
                </div>
                <video id="boFaceVideo" autoplay playsinline muted hidden></video>
                <img id="boFacePreview" alt="<?php echo of_t('Pré-visualização da foto facial'); ?>" hidden>
                <canvas id="boFaceCanvas" hidden></canvas>
            </div>

            <p class="bo-face-confirmation" id="boFaceConfirmation" role="status" hidden></p>
            <p class="bo-face-muted bo-face-feedback" id="boFaceFeedback" role="status" aria-live="polite">
                <?php echo of_t('Câmera desligada.'); ?>
            </p>

            <div class="bo-face-actions">
                <button type="button" class="btn-bo-gold" id="boFaceStart" disabled>
                    <i class="bi bi-camera-video" aria-hidden="true"></i> <?php echo of_t('Ativar câmera'); ?>
                </button>
                <button type="button" class="btn-bo-outline" id="boFaceCapture" disabled>
                    <i class="bi bi-camera" aria-hidden="true"></i> <?php echo of_t('Capturar foto'); ?>
                </button>
                <button type="button" class="btn-bo-outline" id="boFaceStop" hidden>
                    <i class="bi bi-camera-video-off" aria-hidden="true"></i> <?php echo of_t('Desativar câmera'); ?>
                </button>
                <button type="button" class="btn-bo-outline" id="boFaceRetake" hidden>
                    <i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?php echo of_t('Tirar outra foto'); ?>
                </button>
                <button type="button" class="btn-bo-outline" id="boFaceCancel" hidden>
                    <?php echo of_t('Cancelar'); ?>
                </button>
                <button type="button" class="btn-bo-gold" id="boFaceSave" hidden disabled>
                    <i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> <?php echo of_t('Salvar cadastro facial'); ?>
                </button>
            </div>
        </div>
    </div>
</section>
