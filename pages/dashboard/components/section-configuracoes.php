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


    <div class="bo-page-title">
        <div>
            <span class="bo-eyebrow">
                <i class="bi bi-person-circle"></i> Conta
            </span>

            <h1>Meu perfil</h1>
            <p>Consulte e atualize os dados da sua conta.</p>
        </div>
    </div>

    <div class="bo-settings-card bo-profile-settings">

        <div class="bo-profile-layout">

            <!-- ESQUERDA: FOTO -->
            <div class="bo-profile-photo-area">

                <div class="bo-profile-photo">

                    <?php if (!empty($usuarioDashboard['foto'])): ?>

                        <img
                            id="boProfilePhoto"
                            src="<?php echo htmlspecialchars(
                                        $usuarioDashboard['foto'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ); ?>"
                            alt="Foto de perfil">

                    <?php else: ?>

                        <div
                            class="bo-profile-photo-placeholder"
                            id="boProfilePhotoPlaceholder">
                            <i class="bi bi-person"></i>
                        </div>

                    <?php endif; ?>

                </div>

                <button
                    type="button"
                    class="btn-bo-outline bo-profile-photo-button"
                    onclick="boOpenProfileEdit()">
                    <i class="bi bi-camera"></i>
                    Alterar foto
                </button>

            </div>


            <!-- DIREITA: INFORMAÇÕES -->
            <div class="bo-profile-details">

                <div class="bo-settings-heading">

                    <span class="bo-metric-icon">
                        <i class="bi bi-person"></i>
                    </span>

                    <div>

                        <h2 id="boProfileName">
                            <?php echo htmlspecialchars(
                                $usuarioDashboard['nome'],
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </h2>

                        <p id="boProfileEmail">
                            <?php echo htmlspecialchars(
                                $usuarioDashboard['email'] ?: 'Dados da conta ONE FIT',
                                ENT_QUOTES,
                                'UTF-8'
                            ); ?>
                        </p>

                    </div>

                </div>

                <div class="bo-profile-info">

                    <!-- GÊNERO -->
                    <div class="bo-profile-info-item">
                        <i class="bi bi-gender-ambiguous"></i>

                        <div>
                            <span>Gênero</span>

                            <strong id="boProfileGender">
                                <?php
                                echo !empty($usuarioDashboard['genero'])
                                    ? htmlspecialchars(
                                        ucfirst($usuarioDashboard['genero']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : 'Não informado';
                                ?>
                            </strong>
                        </div>
                    </div>


                    <!-- ALTURA -->
                    <div class="bo-profile-info-item">
                        <i class="bi bi-rulers"></i>

                        <div>
                            <span>Altura</span>

                            <strong id="boProfileHeight">
                                <?php
                                if (!empty($usuarioDashboard['altura'])) {
                                    echo number_format(
                                        (float)$usuarioDashboard['altura'],
                                        2,
                                        ',',
                                        '.'
                                    ) . ' m';
                                } else {
                                    echo 'Não informada';
                                }
                                ?>
                            </strong>
                        </div>
                    </div>


                    <!-- PESO -->
                    <div class="bo-profile-info-item">
                        <i class="bi bi-speedometer2"></i>

                        <div>
                            <span>Peso</span>

                            <strong id="boProfileWeight">
                                <?php
                                if (!empty($usuarioDashboard['peso'])) {
                                    echo number_format(
                                        (float)$usuarioDashboard['peso'],
                                        1,
                                        ',',
                                        '.'
                                    ) . ' kg';
                                } else {
                                    echo 'Não informado';
                                }
                                ?>
                            </strong>
                        </div>
                    </div>


                    <!-- IMC -->
                    <div class="bo-profile-info-item">
                        <i class="bi bi-heart-pulse"></i>

                        <div>
                            <span>IMC</span>

                            <strong id="boProfileImc">
                                <?php

                                $altura = (float)($usuarioDashboard['altura'] ?? 0);
                                $peso = (float)($usuarioDashboard['peso'] ?? 0);

                                if ($altura > 0 && $peso > 0) {

                                    $imc = $peso / ($altura * $altura);

                                    if ($imc < 18.5) {
                                        $classificacao = 'Abaixo do peso';
                                    } elseif ($imc < 25) {
                                        $classificacao = 'Peso adequado';
                                    } elseif ($imc < 30) {
                                        $classificacao = 'Sobrepeso';
                                    } elseif ($imc < 35) {
                                        $classificacao = 'Obesidade grau I';
                                    } elseif ($imc < 40) {
                                        $classificacao = 'Obesidade grau II';
                                    } else {
                                        $classificacao = 'Obesidade grau III';
                                    }

                                    echo number_format($imc, 1, ',', '.')
                                        . ' · '
                                        . $classificacao;
                                } else {

                                    echo 'Não disponível';
                                }

                                ?>
                            </strong>
                        </div>
                    </div>

                </div>

            </div>

        </div>


        <div class="bo-profile-actions">

            <button
                class="btn-bo-gold"
                type="button"
                onclick="boOpenProfileEdit()">
                <i class="bi bi-pencil-square"></i>
                Editar perfil
            </button>

        </div>

    </div>

    <section class="bo-content-section" id="boSettingsSection">
        <div class="bo-page-title">
            <div><span class="bo-eyebrow"><i class="bi bi-gear"></i> Preferências</span>
                <h1>Configurações</h1>
                <p>Personalize a aparência do painel para a sua navegação.</p>
            </div>
        </div>
        <div class="bo-settings-card">
            <div class="bo-settings-heading"><span class="bo-metric-icon"><i class="bi bi-circle-half"></i></span>
                <div>
                    <h2>Tema da interface</h2>
                    <p>Escolha como o ONE FIT será exibido neste dispositivo.</p>
                </div>
            </div>
            <div class="bo-theme-choices" role="group" aria-label="Escolher tema">
                <button type="button" class="bo-theme-choice" data-bo-theme="light"><i class="bi bi-sun"></i><span><strong>Tema claro</strong><small>Interface iluminada</small></span><i class="bi bi-check2 bo-theme-check"></i></button>
                <button type="button" class="bo-theme-choice" data-bo-theme="dark"><i class="bi bi-moon-stars"></i><span><strong>Tema escuro</strong><small>Interface com menos brilho</small></span><i class="bi bi-check2 bo-theme-check"></i></button>
            </div>
            <div class="bo-settings-logout">
                <div><strong>Sair da conta</strong><span>Encerre sua sessão neste dispositivo.</span></div>
                <a class="logout-button btn-bo-outline bo-logout-button" href="<?php echo BASE_URL; ?>config/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
    </section>