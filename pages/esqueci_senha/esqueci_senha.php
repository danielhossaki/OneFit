<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');


/*
|--------------------------------------------------------------------------
| MOSTRAR FORMULÁRIO DE NOVA SENHA
|--------------------------------------------------------------------------
*/

$mostrarNovaSenha = false;

if (
    isset($_POST['acao']) &&
    $_POST['acao'] === 'mostrar_nova_senha'
) {

    if (
        !empty($_SESSION['recuperacao_email']) &&
        !empty($_SESSION['recuperacao_usuario_id'])
    ) {

        $mostrarNovaSenha = true;

    } else {

        $_SESSION['esqueci_senha_msg'] =
            'Solicite a recuperação de senha novamente.';

        $_SESSION['esqueci_senha_tipo'] = 'erro';

        header('Location: esqueci_senha.php');
        exit;
    }
}


/*
|--------------------------------------------------------------------------
| SALVAR NOVA SENHA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['acao']) &&
    $_POST['acao'] === 'salvar_nova_senha'
) {

    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (empty($novaSenha) || empty($confirmarSenha)) {

        $erro = 'Preencha todos os campos.';
        $mostrarNovaSenha = true;

    } elseif (strlen($novaSenha) < 8) {

        $erro = 'A nova senha precisa ter pelo menos 8 caracteres.';
        $mostrarNovaSenha = true;

    } elseif ($novaSenha !== $confirmarSenha) {

        $erro = 'As senhas não coincidem.';
        $mostrarNovaSenha = true;

    } elseif (
        empty($_SESSION['recuperacao_usuario_id'])
    ) {

        $erro = 'Sessão de recuperação inválida. Solicite novamente.';
        $mostrarNovaSenha = false;

    } else {

        /*
        |--------------------------------------------------------------------------
        | GERA HASH DA NOVA SENHA
        |--------------------------------------------------------------------------
        */

        $hash = password_hash(
            $novaSenha,
            PASSWORD_DEFAULT
        );


        /*
        |--------------------------------------------------------------------------
        | ATUALIZA A SENHA
        |--------------------------------------------------------------------------
        */

        $usuarioId =
            $_SESSION['recuperacao_usuario_id'];

        $sql = "
            UPDATE usuarios
            SET senha = ?
            WHERE id_usuario = ?
        ";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            'si',
            $hash,
            $usuarioId
        );


        if ($stmt->execute()) {

            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | LIMPA A SESSÃO DE RECUPERAÇÃO
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['recuperacao_email'],
                $_SESSION['recuperacao_usuario_id']
            );


            /*
            |--------------------------------------------------------------------------
            | MENSAGEM DE SUCESSO
            |--------------------------------------------------------------------------
            */

            $_SESSION['esqueci_senha_msg'] =
                'Senha salva com sucesso! Agora você já pode entrar com sua nova senha.';

            $_SESSION['esqueci_senha_tipo'] =
                'sucesso';


            header(
                'Location: ' .
                BASE_URL .
                'pages/login/login.php'
            );

            exit;

        } else {

            $erro =
                'Não foi possível salvar a nova senha. Tente novamente.';

            $mostrarNovaSenha = true;

            $stmt->close();
        }
    }
}

?>

<!DOCTYPE html>

<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Esqueci minha senha · ONE FIT</title>

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?>assets/css/home.css"
    >

    <link
        rel="stylesheet"
        href="<?php echo BASE_URL; ?>assets/css/login.css"
    >

    <link
        rel="stylesheet"
        href="https://unpkg.com/aos@2.3.4/dist/aos.css"
    >

    <link
        rel="icon"
        href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp"
        type="image/x-icon"
    >

</head>


<body class="login-body">

<main class="login-page">


    <!-- PAINEL VISUAL -->

    <section
        class="login-visual"
        data-aos="fade-right"
    >

        <video
            autoplay
            muted
            loop
            playsinline
        >

            <source
                src="<?php echo BASE_URL; ?>assets/img/videos/video-esqueci-senha.mp4"
                type="video/mp4"
            >

            Seu navegador não suporta vídeos.

        </video>


        <div class="login-visual-overlay"></div>


        <div class="login-visual-content">

            <a
                href="<?php echo BASE_URL; ?>index.php"
                class="login-logo"
            >
                ONE<span>FIT</span>
            </a>


            <div class="login-visual-text">

                <span class="eyebrow">
                    Treino de alta performance
                </span>

                <h2>
                    NÃO EXISTE<br>
                    SEGUNDO<br>
                    LUGAR
                </h2>

            </div>

        </div>

    </section>



    <!-- FORMULÁRIO -->

    <section class="login-form-panel">

        <div
            class="login-form-wrap"
            data-aos="fade-right"
            data-aos-delay="250"
        >


            <a
                href="<?php echo BASE_URL; ?>pages/index.php"
                class="login-logo login-logo-mobile"
            >
                ONE<span>FIT</span>
            </a>


            <span class="tag">
                Recuperar acesso
            </span>


            <h1>
                <?php
                echo $mostrarNovaSenha
                    ? 'Definir nova senha'
                    : 'Esqueci minha senha';
                ?>
            </h1>


            <?php if (!$mostrarNovaSenha): ?>

                <p class="login-subtitle">
                    Digite seu e-mail para receber instruções de redefinição de senha.
                </p>

            <?php else: ?>

                <p class="login-subtitle">
                    Digite e confirme sua nova senha.
                </p>

            <?php endif; ?>



            <!-- MENSAGEM -->

            <?php

            if (!empty($_SESSION['esqueci_senha_msg'])):

                $tipo =
                    $_SESSION['esqueci_senha_tipo']
                    ?? 'info';

            ?>

                <p
                    class="form-msg form-msg-<?php echo htmlspecialchars($tipo); ?>"
                >

                    <?php
                    echo htmlspecialchars(
                        $_SESSION['esqueci_senha_msg']
                    );
                    ?>

                </p>

                <?php

                unset(
                    $_SESSION['esqueci_senha_msg'],
                    $_SESSION['esqueci_senha_tipo']
                );

                ?>

            <?php endif; ?>



            <!-- ERRO -->

            <?php if (!empty($erro)): ?>

                <p class="form-msg form-msg-erro">

                    <?php
                    echo htmlspecialchars($erro);
                    ?>

                </p>

            <?php endif; ?>



            <!-- ETAPA 1: INFORMAR E-MAIL -->

            <?php if (
                !$mostrarNovaSenha &&
                empty($_SESSION['recuperacao_email'])
            ): ?>

                <form
                    class="login-form"
                    action="processa_esqueci_senha.php"
                    method="POST"
                >

                    <div class="field">

                        <label for="email">
                            E-mail
                        </label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            placeholder="seuemail@exemplo.com"
                            required
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-gold btn-block"
                    >
                        Enviar instruções
                    </button>

                </form>



            <!-- ETAPA 2: BOTÃO DEFINIR NOVA SENHA -->

            <?php elseif (
                !$mostrarNovaSenha &&
                !empty($_SESSION['recuperacao_email'])
            ): ?>

                <form
                    class="login-form"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="acao"
                        value="mostrar_nova_senha"
                    >


                    <button
                        type="submit"
                        class="btn btn-gold btn-block"
                    >
                        Definir nova senha
                    </button>

                </form>



            <!-- ETAPA 3: NOVA SENHA -->

            <?php elseif ($mostrarNovaSenha): ?>

                <form
                    class="login-form"
                    method="POST"
                >

                    <input
                        type="hidden"
                        name="acao"
                        value="salvar_nova_senha"
                    >


                    <div class="field">

                        <label for="nova_senha">
                            Nova senha
                        </label>

                        <input
                            type="password"
                            id="nova_senha"
                            name="nova_senha"
                            placeholder="Mínimo 8 caracteres"
                            required
                            minlength="8"
                        >

                    </div>



                    <div class="field">

                        <label for="confirmar_senha">
                            Confirmar senha
                        </label>

                        <input
                            type="password"
                            id="confirmar_senha"
                            name="confirmar_senha"
                            placeholder="Repita sua nova senha"
                            required
                            minlength="8"
                        >

                    </div>


                    <button
                        type="submit"
                        class="btn btn-gold btn-block"
                    >
                        Salvar nova senha
                    </button>

                </form>

            <?php endif; ?>



            <p class="login-footer-text">

                Lembrou sua senha?

                <a
                    href="<?php echo BASE_URL; ?>pages/login/login.php"
                >
                    Entrar
                </a>

            </p>


        </div>

    </section>


</main>


<script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

<script>

AOS.init({
    duration: 800,
    once: true,
    offset: 300
});

</script>


</body>

</html>