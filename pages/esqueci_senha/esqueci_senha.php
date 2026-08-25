<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

?>

<html lang="pt-BR">

<head>

  <meta charset="UTF-8">

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  >

  <title>Esqueci minha senha · ONE FIT</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">

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


  <section
    class="login-visual"
    data-aos="fade-right"
  >

    <video autoplay muted loop playsinline>

      <source
        src="<?php echo BASE_URL; ?>assets/img/videos/video-esqueci-senha.mp4"
        type="video/mp4"
      >

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



  <section class="login-form-panel">


    <div
      class="login-form-wrap"
      data-aos="fade-right"
      data-aos-delay="250"
    >


      <a
        href="<?php echo BASE_URL; ?>index.php"
        class="login-logo login-logo-mobile"
      >
        ONE<span>FIT</span>
      </a>


      <span class="tag">
        Recuperar acesso
      </span>


      <h1>
        Esqueci minha senha
      </h1>


      <p class="login-subtitle">

        Digite seu e-mail para receber instruções
        de redefinição de senha.

      </p>



      <?php if (!empty($_SESSION['esqueci_senha_msg'])): ?>

        <p
          class="form-msg form-msg-<?php
            echo htmlspecialchars(
              $_SESSION['esqueci_senha_tipo'] ?? 'sucesso'
            );
          ?>"
        >

          <?php
            echo htmlspecialchars(
              $_SESSION['esqueci_senha_msg']
            );
          ?>

        </p>

      <?php endif; ?>



      <?php if (!empty($_SESSION['reset_token'])): ?>


        <form
          class="login-form"
          action="<?php echo BASE_URL; ?>pages/esqueci_senha/resetar_senha.php"
          method="GET"
        >

          <input
            type="hidden"
            name="token"
            value="<?php
              echo htmlspecialchars(
                $_SESSION['reset_token']
              );
            ?>"
          >


          <button
            type="submit"
            class="btn btn-gold btn-block"
          >
            Definir nova senha
          </button>

        </form>


      <?php else: ?>


        <form
          class="login-form"
          action="<?php echo BASE_URL; ?>pages/esqueci_senha/processa_esqueci_senha.php"
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


      <?php endif; ?>



      <p class="login-footer-text">

        Lembrou sua senha?

        <a
          href="<?php
            echo BASE_URL;
          ?>pages/login/login.php"
        >
          Entrar
        </a>

      </p>


    </div>


  </section>


</main>



<script
  src="https://unpkg.com/aos@2.3.4/dist/aos.js"
></script>


<script>

  AOS.init({
    duration: 800,
    once: true,
    offset: 300
  });

</script>


</body>

</html>


<?php

unset(
    $_SESSION['esqueci_senha_msg'],
    $_SESSION['esqueci_senha_tipo']
);

?>