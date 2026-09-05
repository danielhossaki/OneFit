<?php
require_once __DIR__ . '/../includes/aluno-profile.php';
require_once __DIR__ . '/modal-student-profile.php';
$imcAluno = bo_aluno_imc($usuarioDashboard['altura'], $usuarioDashboard['peso']);
$fotoAluno = bo_aluno_foto_url($usuarioDashboard['foto']);
$resumoAluno = [
    ['Gênero', ucfirst($usuarioDashboard['genero'] ?: 'Não informado'), 'bi-person'],
    ['Altura', bo_aluno_medida($usuarioDashboard['altura'], 3) ? number_format((float) $usuarioDashboard['altura'], 2, ',', '.') . ' m' : 'Não informado', 'bi-rulers'],
    ['Peso', bo_aluno_medida($usuarioDashboard['peso'], 500) ? number_format((float) $usuarioDashboard['peso'], 1, ',', '.') . ' kg' : 'Não informado', 'bi-speedometer2'],
    ['IMC', $imcAluno['valor'] === null ? 'Não informado' : number_format($imcAluno['valor'], 1, ',', '.') . ' · ' . $imcAluno['classe'], 'bi-heart-pulse'],
];
?>
<link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/student-profile.css">
<script defer src="<?php echo BASE_URL; ?>assets/js/student-profile.js"></script>
<section class="bo-content-section bo-student-profile" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> Conta</span><h1>Meu perfil</h1><p>Consulte e atualize os dados da sua conta.</p></div></div>
    <div class="bo-settings-card bo-student-summary">
        <div class="bo-student-photo-column">
            <div class="bo-student-photo">
                <i class="bi bi-person" aria-hidden="true"></i>
                <?php if ($fotoAluno): ?><img src="<?php echo bo_val($fotoAluno); ?>" alt="Foto de <?php echo bo_val($usuarioDashboard['nome']); ?>" data-student-photo><?php endif; ?>
            </div>
            <form action="<?php echo bo_action_url('update-profile.php'); ?>" method="post" enctype="multipart/form-data" id="studentPhotoForm">
                <?php echo bo_csrf_field(); ?>
                <input type="hidden" name="acao" value="foto">
                <input type="file" name="foto_arquivo" id="studentQuickPhoto" accept="image/jpeg,image/png,image/webp" hidden>
                <button type="button" class="btn-bo-outline" id="studentChoosePhoto"><i class="bi bi-camera"></i> Alterar foto</button>
                <small>JPG, PNG ou WEBP · Até 3 MB</small>
                <span id="studentPhotoStatus" role="status"></span>
            </form>
        </div>
        <div class="bo-student-details">
            <h2 id="boProfileName"><?php echo bo_val($usuarioDashboard['nome']); ?></h2>
            <p id="boProfileEmail"><?php echo bo_val($usuarioDashboard['email']); ?></p>
            <p id="boProfileId">ID: #<?php echo str_pad((string) $_SESSION['id_usuario'], 4, '0', STR_PAD_LEFT); ?></p>
            <div class="bo-student-metrics">
                <?php foreach ($resumoAluno as [$label, $value, $icon]): ?>
                    <div class="bo-student-metric"><span><i class="bi <?php echo $icon; ?>"></i> <?php echo $label; ?></span><strong><?php echo bo_val($value); ?></strong></div>
                <?php endforeach; ?>
            </div>
            <div class="bo-actions bo-student-actions">
                <button class="btn-bo-gold" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfilEditar"><i class="bi bi-pencil-square"></i> EDITAR PERFIL</button>
            </div>
        </div>
    </div>
    <?php bo_modal_aluno_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>
