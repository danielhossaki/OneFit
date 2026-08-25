<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');


$erro = '';

$sucesso = '';

$tokenValido = false;


/*
|--------------------------------------------------------------------------
| PEGA TOKEN
|--------------------------------------------------------------------------
*/

$token =
    $_GET['token']
    ??
    ($_POST['token'] ?? '');


/*
|--------------------------------------------------------------------------
| VERIFICA SE EXISTE TOKEN
|--------------------------------------------------------------------------
*/

if (empty($token)) {

    header(
        'Location: ' .
        BASE_URL .
        'pages/esqueci_senha/esqueci_senha.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICA O TOKEN
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT usuario_id, expira_em
    FROM recuperacao_senha
    WHERE token = ?
    AND usado = 0
    LIMIT 1
";


$stmt = $conn->prepare($sql);


$stmt->bind_param(
    's',
    $token
);


$stmt->execute();


$resultado = $stmt->get_result();


$registro = $resultado->fetch_assoc();


$stmt->close();



/*
|--------------------------------------------------------------------------
| TOKEN VÁLIDO
|--------------------------------------------------------------------------
*/

if (
    $registro
    &&
    strtotime($registro['expira_em']) >= time()
) {

    $tokenValido = true;

    $usuario_id =
        $registro['usuario_id'];

} else {

    $erro =
        'Este link é inválido, expirou ou já foi utilizado.';
}


/*
|--------------------------------------------------------------------------
| SALVAR NOVA SENHA
|--------------------------------------------------------------------------
*/

if (
    $tokenValido
    &&
    $_SERVER['REQUEST_METHOD'] === 'POST'
    &&
    isset($_POST['salvar_senha'])
) {


    $novaSenha =
        $_POST['nova_senha'] ?? '';


    $confirmarSenha =
        $_POST['confirmar_senha'] ?? '';



    /*
    |--------------------------------------------------------------------------
    | VALIDA CAMPOS
    |--------------------------------------------------------------------------
    */

    if (
        empty($novaSenha)
        ||
        empty($confirmarSenha)
    ) {

        $erro =
            'Preencha todos os campos.';

    }


    /*
    |--------------------------------------------------------------------------
    | VALIDA TAMANHO
    |--------------------------------------------------------------------------
    */

    elseif (
        strlen($novaSenha) < 8
    ) {

        $erro =
            'A senha deve ter pelo menos 8 caracteres.';

    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRMA SENHAS
    |--------------------------------------------------------------------------
    */

    elseif (
        $novaSenha !== $confirmarSenha
    ) {

        $erro =
            'As senhas não coincidem.';

    }


    /*
    |--------------------------------------------------------------------------
    | SALVA SENHA
    |--------------------------------------------------------------------------
    */

    else {


        /*
        |--------------------------------------------------------------------------
        | CRIA HASH
        |--------------------------------------------------------------------------
        */

        $hash =
            password_hash(
                $novaSenha,
                PASSWORD_DEFAULT
            );


        /*
        |--------------------------------------------------------------------------
        | ATUALIZA SENHA DO USUÁRIO
        |--------------------------------------------------------------------------
        */

        $sqlUpdate = "
            UPDATE usuarios
            SET senha = ?
            WHERE id_usuario = ?
        ";


        $stmtUpdate =
            $conn->prepare($sqlUpdate);


        $stmtUpdate->bind_param(
            'si',
            $hash,
            $usuario_id
        );


        $senhaAlterada =
            $stmtUpdate->execute();


        $stmtUpdate->close();



        /*
        |--------------------------------------------------------------------------
        | SE SENHA FOI ALTERADA
        |--------------------------------------------------------------------------
        */

        if ($senhaAlterada) {


            /*
            |--------------------------------------------------------------------------
            | MARCA TOKEN COMO USADO
            |--------------------------------------------------------------------------
            */

            $sqlUsado = "
                UPDATE recuperacao_senha
                SET usado = 1
                WHERE token = ?
            ";


            $stmtUsado =
                $conn->prepare($sqlUsado);


            $stmtUsado->bind_param(
                's',
                $token
            );


            $stmtUsado->execute();


            $stmtUsado->close();


            /*
            |--------------------------------------------------------------------------
            | REMOVE TOKEN DA SESSÃO
            |--------------------------------------------------------------------------
            */

            unset(
                $_SESSION['reset_token']
            );


            /*
            |--------------------------------------------------------------------------
            | MENSAGEM DE SUCESSO
            |--------------------------------------------------------------------------
            */

            $sucesso =
                'Senha salva com sucesso!';


            $tokenValido = false;


        } else {


            $erro =
                'Não foi possível salvar a nova senha.';
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

    <title>
        Redefinir senha · ONE FIT
    </title>


    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >


    <link
        href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap"
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

</head>


<body class="login-body">


<main class="login-page">


    <section class="login-form-panel">


        <div class="login-form-wrap">


            <span class="tag">
                Recuperar acesso
            </span>


            <h1>
                Redefinir senha
            </h1>



            <?php if ($erro): ?>

                <p class="form-msg form-msg-erro">

                    <?php
                    echo htmlspecialchars($erro);
                    ?>

                </p>

            <?php endif; ?>



            <?php if ($sucesso): ?>


                <p class="form-msg form-msg-sucesso">

                    <?php
                    echo htmlspecialchars($sucesso);
                    ?>

                </p>


                <p class="login-subtitle">

                    Sua senha foi alterada com sucesso.

                    Agora você já pode entrar na sua conta.

                </p>


                <a
                    href="<?php echo BASE_URL; ?>pages/login/login.php"
                    class="btn btn-gold btn-block"
                >

                    Ir para o login

                </a>



            <?php elseif ($tokenValido): ?>


                <p class="login-subtitle">

                    Clique abaixo para definir uma nova senha.

                </p>



                <!--
                ============================================================
                BOTÃO DEFINIR NOVA SENHA
                ============================================================
                -->

                <form
                    method="POST"
                    action="resetar_senha.php"
                >

                    <input
                        type="hidden"
                        name="token"
                        value="<?php echo htmlspecialchars($token); ?>"
                    >


                    <button
                        type="submit"
                        name="definir_senha"
                        class="btn btn-gold btn-block"
                    >

                        Definir nova senha

                    </button>

                </form>



                <?php if (
                    (
                        $_SERVER['REQUEST_METHOD'] === 'POST'
                        &&
                        isset($_POST['definir_senha'])
                    )
                    ||
                    (
                        $_SERVER['REQUEST_METHOD'] === 'POST'
                        &&
                        isset($_POST['salvar_senha'])
                    )
                ): ?>


                    <br>


                    <form
                        class="login-form"
                        method="POST"
                        action="resetar_senha.php"
                    >


                        <input
                            type="hidden"
                            name="token"
                            value="<?php echo htmlspecialchars($token); ?>"
                        >



                        <div class="field">

                            <label for="nova_senha">

                                Nova senha

                            </label>


                            <input
                                type="password"
                                id="nova_senha"
                                name="nova_senha"
                                placeholder="Digite sua nova senha"
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
                                placeholder="Confirme sua nova senha"
                                required
                                minlength="8"
                            >

                        </div>



                        <button
                            type="submit"
                            name="salvar_senha"
                            class="btn btn-gold btn-block"
                        >

                            Salvar nova senha

                        </button>


                    </form>


                <?php endif; ?>



            <?php else: ?>


                <p class="login-subtitle">

                    Solicite um novo link para redefinir sua senha.

                </p>


                <a
                    href="<?php echo BASE_URL; ?>pages/esqueci_senha/esqueci_senha.php"
                    class="btn btn-gold btn-block"
                >

                    Solicitar novo link

                </a>


            <?php endif; ?>


        </div>


    </section>


</main>


</body>

</html>