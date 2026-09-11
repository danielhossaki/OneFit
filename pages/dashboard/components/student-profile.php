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
<script defer src="<?php echo BASE_URL; ?>assets/js/student-profile.js?v=<?php echo filemtime(__DIR__ . '/../../../assets/js/student-profile.js'); ?>"></script>
<section class="bo-content-section bo-student-profile" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> Conta</span><h1>Meu perfil</h1><p>Consulte e atualize os dados da sua conta.</p></div></div>
    <div class="row g-3">
        <div class="<?php echo $perfilLogado === 'aluno' ? 'col-lg-8' : 'col-12'; ?>">
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
        </div>

        <?php if ($perfilLogado === 'aluno'): ?>
            <!-- Comentário do aluno, moderado pelo admin (aba Comentários) -->
            <div class="col-lg-4">
                <div class="bo-settings-card bo-testemunho-card">
                    <div class="bo-testemunho-titulo"><i class="bi bi-star-fill"></i> Comente aqui</div>
                    <p class="bo-testemunho-sub">Compartilhe sua experiência e ajude outras pessoas a também darem o próximo passo!</p>
                    <?php if ($alunoTestemunho ?? null): ?>
                        <?php $boStatusLabel = ['pendente' => 'Em análise', 'aprovado' => 'Aprovado', 'reprovado' => 'Não aprovado']; ?>
                        <p class="bo-testemunho-status">Status atual: <strong><?php echo $boStatusLabel[$alunoTestemunho['aprovacao']] ?? ucfirst($alunoTestemunho['aprovacao']); ?></strong></p>
                    <?php endif; ?>
                    <form method="POST" action="<?php echo htmlspecialchars(BASE_URL . 'pages/dashboard/funcionalidades/testemunho.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
                        <textarea class="form-control" name="texto" rows="5" maxlength="500" placeholder="Escreva aqui seu testemunho... Conte como foi sua experiência, o que mudou na sua vida e o que você mais gostou." data-testemunho-texto><?php echo htmlspecialchars($alunoTestemunho['texto'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                        <p class="bo-testemunho-contador"><span data-testemunho-contador><?php echo mb_strlen($alunoTestemunho['texto'] ?? ''); ?></span>/500 caracteres</p>
                        <button type="submit" class="btn-bo-gold w-100"><i class="bi bi-send"></i> Enviar comentário</button>
                    </form>
                    <p class="bo-testemunho-aviso"><i class="bi bi-info-circle"></i> Seu depoimento poderá ser exibido na nossa página inicial, para inspirar outras pessoas.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php bo_modal_aluno_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>
