<?php if ($perfilLogado === 'aluno'): ?>
<?php require __DIR__ . '/student-profile.php'; ?>
<?php else: ?>
<section class="bo-content-section" id="boProfileSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-person-circle"></i> Conta</span><h1>Meu perfil</h1><p>Consulte e atualize os dados da sua conta.</p></div></div>
    <div class="bo-settings-card bo-profile-settings">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person"></i></span><div><h2 id="boProfileName"><?php echo htmlspecialchars($usuarioDashboard['nome'], ENT_QUOTES, 'UTF-8'); ?></h2><p id="boProfileId">ID: #<?php echo str_pad((string) $_SESSION['id_usuario'], 4, '0', STR_PAD_LEFT); ?></p><p id="boProfileEmail"><?php echo htmlspecialchars($usuarioDashboard['email'] ?: 'Dados da conta ONE FIT', ENT_QUOTES, 'UTF-8'); ?></p><?php if ($usuarioDashboard['genero']): ?><p id="boProfileGender">Gênero: <?php echo htmlspecialchars(ucfirst($usuarioDashboard['genero']), ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?></div></div>
        <div class="bo-actions">
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> Alterar senha</button>
            <button class="btn-bo-gold" type="button" data-bs-toggle="modal" data-bs-target="#modalPerfilEditar"><i class="bi bi-pencil-square"></i> Editar perfil</button>
        </div>
    </div>
    <?php bo_modal_perfil_editar($usuarioDashboard, (int) $_SESSION['id_usuario']); ?>
    <?php bo_modal_senha_alterar(); ?>
</section>

<?php endif; ?>

<section class="bo-content-section" id="boSettingsSection">
    <div class="bo-page-title"><div><span class="bo-eyebrow"><i class="bi bi-gear"></i> Preferências</span><h1>Configurações</h1><p>Personalize sua experiência e gerencie a segurança da conta.</p></div></div>
    <div class="bo-settings-stack">
    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-circle-half"></i></span><div><h2>Tema da interface</h2><p>Escolha como o ONE FIT será exibido neste dispositivo.</p></div></div>
        <div class="bo-theme-choices" role="group" aria-label="Escolher tema">
            <button type="button" class="bo-theme-choice" data-bo-theme="light" aria-pressed="false"><i class="bi bi-sun"></i><span><strong>Tema claro</strong><small>Interface iluminada</small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="dark" aria-pressed="false"><i class="bi bi-moon-stars"></i><span><strong>Tema escuro</strong><small>Interface com menos brilho</small></span></button>
            <button type="button" class="bo-theme-choice" data-bo-theme="system" aria-pressed="false"><i class="bi bi-display"></i><span><strong>Usar tema do sistema</strong><small>Acompanha este dispositivo</small></span></button>
        </div>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-bell"></i></span><div><h2>Notificações</h2><p>Escolha individualmente quais comunicações deseja receber.</p></div></div>
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
                    <label for="<?php echo $idControle; ?>"><i class="bi <?php echo $icone; ?>"></i><span><?php echo $rotulo; ?></span></label>
                    <input class="bo-toggle-input" type="checkbox" role="switch"
                        id="<?php echo $idControle; ?>" data-bo-preference="<?php echo $chave; ?>"
                        <?php echo !empty($preferenciasDashboard[$chave]) ? 'checked' : ''; ?>>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$preferenciasDisponiveis): ?>
            <p class="bo-settings-hint"><i class="bi bi-info-circle"></i> A migração de preferências deve ser aplicada para sincronizar estes dados com a conta.</p>
        <?php endif; ?>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-shield-lock"></i></span><div><h2>Privacidade e segurança</h2><p>Proteja suas credenciais de acesso ao ONE FIT.</p></div></div>
        <div class="bo-settings-action-row">
            <div><strong>Senha da conta</strong><span>Use uma senha exclusiva com pelo menos 8 caracteres.</span></div>
            <button class="btn-bo-outline" type="button" data-bs-toggle="modal" data-bs-target="#modalSenhaAlterar"><i class="bi bi-key"></i> Alterar senha</button>
        </div>
    </div>

    <div class="bo-settings-card">
        <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-person-gear"></i></span><div><h2>Conta</h2><p>Gerencie sua sessão atual.</p></div></div>
        <div class="bo-settings-action-row">
            <div><strong>Sair da conta</strong><span>Encerre sua sessão neste dispositivo.</span></div>
            <a class="logout-button btn-bo-outline bo-logout-button" href="<?php echo BASE_URL; ?>config/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
    </div>

    </div>
</section>
