<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');


/*
|--------------------------------------------------------------------------
| TOKEN
|--------------------------------------------------------------------------
*/

$token = $_GET['token'] ?? $_POST['token'] ?? '';

$token = trim($token);


if (empty($token)) {

  $_SESSION['esqueci_senha_msg'] =
    'Link de recuperação inválido.';

  $_SESSION['esqueci_senha_tipo'] =
    'erro';

  header(
    'Location: ' .
      BASE_URL .
      'pages/esqueci_senha/esqueci_senha.php'
  );

  exit;
}


/*
|--------------------------------------------------------------------------
| PROCURA TOKEN
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        id,
        usuario_id,
        expira_em,
        usado
    FROM recuperacao_senha
    WHERE token = ?
    LIMIT 1
";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
  's',
  $token
);

$stmt->execute();

$resultado = $stmt->get_result();

$recuperacao = $resultado->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| TOKEN NÃO EXISTE
|--------------------------------------------------------------------------
*/

if (!$recuperacao) {

  $erro =
    'Este link de recuperação é inválido.';

  $tokenValido = false;
} else {

  /*
    |--------------------------------------------------------------------------
    | VERIFICA SE JÁ FOI USADO
    |--------------------------------------------------------------------------
    */

  if ((int)$recuperacao['usado'] === 1) {

    $erro =
      'Este link já foi utilizado.';

    $tokenValido = false;
  }

  /*
    |--------------------------------------------------------------------------
    | VERIFICA EXPIRAÇÃO
    |--------------------------------------------------------------------------
    */ elseif (
    strtotime($recuperacao['expira_em']) < time()
  ) {

    $erro =
      'Este link de recuperação expirou.';

    $tokenValido = false;
  } else {

    $erro = '';

    $tokenValido = true;
  }
}


/*
|--------------------------------------------------------------------------
| PROCESSA NOVA SENHA
|--------------------------------------------------------------------------
*/

if (
  $tokenValido &&
  $_SERVER['REQUEST_METHOD'] === 'POST'
) {

  $novaSenha =
    $_POST['nova_senha'] ?? '';

  $confirmarSenha =
    $_POST['confirmar_senha'] ?? '';


  /*
    |--------------------------------------------------------------------------
    | TAMANHO
    |--------------------------------------------------------------------------
    */

  if (strlen($novaSenha) < 8) {

    $erro =
      'A senha precisa ter pelo menos 8 caracteres.';
  }

  /*
    |--------------------------------------------------------------------------
    | CONFIRMAÇÃO
    |--------------------------------------------------------------------------
    */ elseif ($novaSenha !== $confirmarSenha) {

    $erro =
      'As senhas não coincidem.';
  } else {

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
        | ATUALIZA USUÁRIO
        |--------------------------------------------------------------------------
        |
        | IMPORTANTE:
        | Sua tabela usa id_usuario.
        |
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
      $recuperacao['usuario_id']
    );


    if ($stmtUpdate->execute()) {

      $stmtUpdate->close();


      /*
            |--------------------------------------------------------------------------
            | MARCA TOKEN COMO USADO
            |--------------------------------------------------------------------------
            */

      $sqlUsado = "
                UPDATE recuperacao_senha
                SET usado = 1
                WHERE id = ?
            ";


      $stmtUsado =
        $conn->prepare($sqlUsado);


      $stmtUsado->bind_param(
        'i',
        $recuperacao['id']
      );


      $stmtUsado->execute();

      $stmtUsado->close();


      /*
            |--------------------------------------------------------------------------
            | SUCESSO
            |--------------------------------------------------------------------------
            */

      $_SESSION['login_msg'] =
        'Senha redefinida com sucesso! Faça login com sua nova senha.';

      $_SESSION['login_tipo'] =
        'sucesso';


      header(
        'Location: ' .
          BASE_URL .
          'pages/login/login.php'
      );

      exit;
    } else {

      $erro =
        'Não foi possível atualizar sua senha.';

      $stmtUpdate->close();
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
    content="width=device-width, initial-scale=1.0">

  <title>
    Redefinir senha · ONE FIT
  </title>


  <link
    rel="preconnect"
    href="https://fonts.googleapis.com">


  <link
    href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap"
    rel="stylesheet">


  <link
    rel="stylesheet"
    href="<?php echo BASE_URL; ?>assets/css/home.css">


  <link
    rel="stylesheet"
    href="<?php echo BASE_URL; ?>assets/css/login.css">


  <link
    rel="icon"
    href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp"
    type="image/webp">

</head>


<body class="login-body">


  <main class="login-page">


    <section
      class="login-visual">

      <video
        autoplay
        muted
        loop
        playsinline>

        <source
          src="<?php echo BASE_URL; ?>assets/img/videos/video-login.mp4"
          type="video/mp4">

        Seu navegador não suporta vídeos.

      </video>


      <div class="login-visual-overlay"></div>


      <div class="login-visual-content">

        <a
          href="<?php echo BASE_URL; ?>index.php"
          class="login-logo">
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


    <section class="login-form-panel">


      <div class="login-form-wrap">


        <a
          href="<?php echo BASE_URL; ?>index.php"
          class="login-logo login-logo-mobile">
          ONE<span>FIT</span>
        </a>


        <span class="tag">
          Recuperar acesso
        </span>


        <h1>
          Redefinir senha
        </h1>


        <?php if ($erro): ?>

          <p class="form-msg form-msg-erro">
            <?php echo htmlspecialchars($erro); ?>
          </p>

        <?php endif; ?>


        <?php if ($tokenValido): ?>


          <p class="login-subtitle">
            Escolha uma nova senha para sua conta.
          </p>


          <form
            class="login-form"
            action="resetar_senha.php"
            method="POST">


            <input
              type="hidden"
              name="token"
              value="<?php echo htmlspecialchars($token); ?>">


            <div class="field">

              <label for="nova_senha">
                Nova senha
              </label>


              <input
                type="password"
                id="nova_senha"
                name="nova_senha"
                placeholder="Mínimo 8 caracteres"
                minlength="8"
                required>

            </div>


            <div class="field">

              <label for="confirmar_senha">
                Confirmar nova senha
              </label>


              <input
                type="password"
                id="confirmar_senha"
                name="confirmar_senha"
                placeholder="Repita a nova senha"
                minlength="8"
                required>

            </div>


            <button
              type="submit"
              class="btn btn-gold btn-block">
              Redefinir senha
            </button>


          </form>


        <?php else: ?>


          <p class="login-subtitle">

            Solicite um novo link de recuperação
            para continuar.

          </p>


          <p class="login-footer-text">

            <a
              href="<?php echo BASE_URL; ?>pages/esqueci_senha/esqueci_senha.php">
              Solicitar novo link
            </a>

          </p>


        <?php endif; ?>


      </div>


    </section>


  </main>


</body>

</html>