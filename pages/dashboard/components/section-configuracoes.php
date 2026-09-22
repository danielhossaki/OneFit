<?php require_once __DIR__ . '/../../../config/interface.php'; ?>
<?php if ($perfilLogado === 'aluno'): ?>
<?php require __DIR__ . '/student-profile.php'; ?>
<?php else: ?>
<section class="bo-content-section" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> <?php echo of_t('Conta'); ?></span><h1><?php echo of_t('Meu perfil'); ?></h1><p><?php echo of_t('Consulte e atualize os dados da sua conta.'); ?></p></div></div>
    <div class="bo-settings-card bo-profile-settings">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person"></i></span><div><h2 id="boProfileName"><?php echo htmlspecialchars($usuarioDashboard['nome'], ENT_QUOTES, 'UTF-8'); ?></h2><p id="boProfileId"><?php echo of_t('ID: #'); ?><?php echo str_pad((string) $_SESSION['id_usuario'], 4, '0', STR_PAD_LEFT); ?></p><p id="boProfileEmail"><?php echo htmlspecialchars($usuarioDashboard['email'] ?: 'Dados da conta ONE FIT', ENT_QUOTES, 'UTF-8'); ?></p><?php if ($usuarioDashboard['genero']): ?><p id="boProfileGender"><?php echo of_t('Gênero:'); ?> <?php echo of_t(ucfirst($usuarioDashboard['genero'])); ?></p><?php endif; ?></div></div>
        <div class="bo-actions">
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> <?php echo of_t('Alterar senha'); ?></button>
            <button class="btn-bo-gold" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfilEditar"><i class="bi bi-pencil-square"></i> <?php echo of_t('Editar perfil'); ?></button>
        </div>
    </div>
    <?php bo_modal_perfil_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>

<?php endif; ?>

<section class="bo-content-section" id="boSettingsSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-gear"></i> <?php echo of_t('Preferências'); ?></span><h1><?php echo of_t('Configurações'); ?></h1><p><?php echo of_t('Personalize sua experiência e gerencie a segurança da conta.'); ?></p></div></div>
    <div class="bo-settings-stack">
    
     <!-- Configurações de tema -->
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-circle-half"></i></span><div><h2><?php echo of_t('Tema da interface'); ?></h2><p><?php echo of_t('Escolha como o {marca} será exibido neste dispositivo.'); ?></p></div></div>
        <div class="bo-theme-choices" role="group" aria-label="<?php echo of_t('Escolher tema'); ?>">
            <button type="button" class="bo-theme-choice" data-bo-theme="light" aria-pressed="false"><i class="bi bi-sun"></i><span><strong><?php echo of_t('Tema claro'); ?></strong><small><?php echo of_t('Interface iluminada'); ?></small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="dark" aria-pressed="false"><i class="bi bi-moon-stars"></i><span><strong><?php echo of_t('Tema escuro'); ?></strong><small><?php echo of_t('Interface com menos brilho'); ?></small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="system" aria-pressed="false"><i class="bi bi-display"></i><span><strong><?php echo of_t('Usar tema do sistema'); ?></strong><small><?php echo of_t('Acompanha este dispositivo'); ?></small></span></button>
        </div>
    </div>

    <!-- Configurações de idioma -->
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-translate"></i></span><div><h2><?php echo of_t('Idioma'); ?></h2><p><?php echo of_t('Escolha o idioma da interface.'); ?></p></div></div>
        <form class="of-settings-form" action="<?php echo bo_action_url('interface.php'); ?>" method="post">
            <?php echo bo_csrf_field(); ?>
            <input type="hidden" name="configuracao" value="idioma">
            <label for="of-language"><?php echo of_t('Idioma'); ?></label>
            <select class="form-select" id="of-language" name="valor">
                <?php foreach (['pt-BR'=>'Português do Brasil','en'=>'English','es'=>'Español'] as $code=>$label): ?>
                <option value="<?php echo $code; ?>" <?php echo onefitIdioma() === $code ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-bo-gold" type="submit"><?php echo of_t('Salvar'); ?></button>
        </form>
    </div>

    <!-- Configurações de notificações -->
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-bell"></i></span><div><h2><?php echo of_t('Notificações'); ?></h2><p><?php echo of_t('Escolha individualmente quais comunicações deseja receber.'); ?></p></div></div>
        <div class="bo-preference-list">
            <?php
            $opcoesNotificacao = [
                'lembretes_treino' => ['Lembretes de treino', 'bi-activity'],
                'avisos_agendamentos' => ['Avisos de agendamentos', 'bi-calendar-check'],
                'atualizacoes_compras' => ['Atualizações de compras', 'bi-bag-check'],
                'ofertas_novidades' => ['Ofertas e novidades', 'bi-stars'],
                'notificacoes_email' => ['Notificações por e-mail', 'bi-envelope'],
            ];
            foreach ($opcoesNotificacao as $chave => [$rotulo, $icone]):
                $idControle = 'preferencia-' . str_replace('_', '-', $chave);
            ?>
                <div class="bo-preference-row">
                    <label for="<?php echo $idControle; ?>"><i class="bi <?php echo $icone; ?>"></i><span><?php echo of_t($rotulo); ?></span></label>
                    <input class="bo-toggle-input" type="checkbox" role="switch"
                        id="<?php echo $idControle; ?>" data-bo-preference="<?php echo $chave; ?>"
                        <?php echo !$preferenciasDisponiveis ? 'disabled' : ''; ?>
                        <?php echo !empty($preferenciasDashboard[$chave]) ? 'checked' : ''; ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$preferenciasDisponiveis): ?>
            <p class="bo-settings-hint" role="alert"><i class="bi bi-info-circle"></i> <?php echo of_t('Não foi possível carregar suas preferências. Atualize a página e tente novamente.'); ?></p>
        <?php endif; ?>
    </div>

    <!-- Configurações de privacidade e segurança -->
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-shield-lock"></i></span><div><h2><?php echo of_t('Privacidade e segurança'); ?></h2><p><?php echo of_t('Proteja suas credenciais de acesso ao {marca}.'); ?></p></div></div>
        <div class="bo-settings-action-row">
            <div><strong><?php echo of_t('Senha da conta'); ?></strong><span><?php echo of_t('Use uma senha exclusiva com pelo menos 8 caracteres.'); ?></span></div>
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> <?php echo of_t('Alterar senha'); ?></button>
        </div>
    </div>

    <!-- Configurações de tema de cores do site (apenas para administradores) -->
    <?php if ($perfilLogado === 'admin' && isset($conn) && onefitAdminAutorizado($conn, (int) $_SESSION['id_usuario'])): ?>
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-palette"></i></span><div><h2><?php echo of_t('Tema de cores do site'); ?></h2><p><?php echo of_t('A cor escolhida será aplicada para todos após salvar.'); ?></p></div></div>
        <form class="of-settings-form" action="<?php echo bo_action_url('interface.php'); ?>" method="post">
            <?php echo bo_csrf_field(); ?>
            <input type="hidden" name="configuracao" value="tema_cores">
            <div class="of-theme-options" role="group" aria-label="<?php echo of_t('Tema de cores do site'); ?>">

            <?php foreach (onefitTemas() as $code): ?>
                <label><input type="radio" name="valor" value="<?php echo $code; ?>" <?php echo ($GLOBALS['onefitTemaGlobal'] ?? 'dourado') === $code ? 'checked' : ''; ?>><span class="of-swatch" data-color="<?php echo $code; ?>" aria-hidden="true"></span><?php echo of_t(ucfirst($code)); ?> — <?php echo htmlspecialchars(onefitMarca($code)['name'], ENT_QUOTES, 'UTF-8'); ?></label>
                <?php endforeach; ?>
            </div>
            <button class="btn-bo-gold" type="submit"><?php echo of_t('Salvar'); ?></button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Configurações de conta -->
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-gear"></i></span><div><h2><?php echo of_t('Conta'); ?></h2><p><?php echo of_t('Gerencie sua sessão atual.'); ?></p></div></div>
        <div class="bo-settings-action-row">
            <div><strong><?php echo of_t('Sair da conta'); ?></strong><span><?php echo of_t('Encerre sua sessão neste dispositivo.'); ?></span></div>
            <a class="logout-button btn-bo-outline bo-logout-button" href="<?php echo BASE_URL; ?>config/logout.php"><i class="bi bi-box-arrow-right"></i> <?php echo of_t('Logout'); ?></a>
        </div>
    </div>

    </div>
</section>
