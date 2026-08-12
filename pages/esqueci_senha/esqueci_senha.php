<?php
// session_start() precisa vir ANTES de qualquer saída HTML, senão o PHP
// não consegue mais criar o cookie de sessão (erro "headers already sent").
session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
?>

<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esqueci minha senha — ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>
<body class="login-body">
  <main class="login-page">

    <section class="login-visual" data-aos="fade-right">
      <video autoplay muted loop playsinline>
        <source src="<?php echo BASE_URL; ?>assets/img/videos/video-login.mp4" type="video/mp4">
        Seu navegador não suporta vídeos.
      </video>
      <div class="login-visual-overlay"></div>
      <div class="login-visual-content">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo">ONE<span>FIT</span></a>
        <div class="login-visual-text">
          <span class="eyebrow">Treino de alta performance</span>
          <h2>NÃO EXISTE<br>SEGUNDO<br>LUGAR</h2>
        </div>
      </div>
    </section>

    <section class="login-form-panel">
      <div class="login-form-wrap" data-aos="fade-right" data-aos-delay="250">
        <a href="<?php echo BASE_URL; ?>pages/index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>

        <span class="tag">Recuperar acesso</span>
        <h1>Esqueci minha senha</h1>
        <p class="login-subtitle">Digite seu e-mail para receber instruções de redefinição de senha.</p>

        <?php
        // O processa_esqueci_senha.php guarda a mensagem na sessão e redireciona
        // de volta pra cá (padrão "Post/Redirect/Get"). Isso evita que, se o
        // usuário der F5 na página, o formulário seja reenviado sem querer.
        if (!empty($_SESSION['esqueci_senha_msg'])) {
            $tipo = $_SESSION['esqueci_senha_tipo'] ?? 'info';
            echo '<p class="form-msg form-msg-' . htmlspecialchars($tipo) . '">'
               . htmlspecialchars($_SESSION['esqueci_senha_msg'])
               . '</p>';

            // Limpa a mensagem depois de mostrar, senão ela reaparece
            // em qualquer página futura que reaproveite essa mesma sessão.
            unset($_SESSION['esqueci_senha_msg'], $_SESSION['esqueci_senha_tipo']);
        }
        ?>

        <form class="login-form" action="processa_esqueci_senha.php" method="POST">
          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
          </div>
          <button type="submit" class="btn btn-gold btn-block">Enviar instruções</button>
        </form>

        <p class="login-footer-text">Lembrou sua senha? <a href="<?php echo BASE_URL; ?>pages/login/login.php">Entrar</a></p>
      </div>
    </section>

  </main>

  <script src="<?php echo BASE_URL; ?>assets/js/login.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 800, once: true, offset: 300 });
  </script>
</body>
</html>
