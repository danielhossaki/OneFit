<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');

$mensagem = $_SESSION['esqueci_senha_msg'] ?? '';
$tipo = $_SESSION['esqueci_senha_tipo'] ?? 'sucesso';
unset($_SESSION['esqueci_senha_msg'], $_SESSION['esqueci_senha_tipo']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Esqueci minha senha · ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
</head>
<body class="login-body">
  <main class="login-page">
    <section class="login-visual">
      <video autoplay muted loop playsinline>
        <source src="<?php echo BASE_URL; ?>assets/img/videos/video-esqueci-senha.mp4" type="video/mp4">
      </video>
      <div class="login-visual-overlay"></div>
      <div class="login-visual-content">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo">ONE<span>FIT</span></a>
        <div class="login-visual-text"><span class="eyebrow">Recuperar acesso</span><h2>SUA CONTA<br>EM SEGURANÇA</h2></div>
      </div>
    </section>
    <section class="login-form-panel">
      <div class="login-form-wrap">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>
        <span class="tag">Recuperar acesso</span>
        <h1>Esqueci minha senha</h1>
        <p class="login-subtitle">Digite seu e-mail para receber instruções de redefinição de senha.</p>
        <?php if ($mensagem): ?><p class="form-msg form-msg-<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form class="login-form" action="<?php echo BASE_URL; ?>pages/esqueci_senha/processa_esqueci_senha.php" method="POST">
          <div class="field"><label for="email">E-mail</label><input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required></div>
          <button type="submit" class="btn btn-gold btn-block">Enviar instruções</button>
        </form>
        <p class="login-footer-text">Lembrou sua senha? <a href="<?php echo BASE_URL; ?>pages/login/login.php">Entrar</a></p>
      </div>
    </section>
  </main>
</body>
</html>
