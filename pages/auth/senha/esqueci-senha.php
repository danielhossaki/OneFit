<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

$mensagem = $_SESSION['esqueci_senha_msg'] ?? '';
$tipo = $_SESSION['esqueci_senha_tipo'] ?? 'sucesso';
unset($_SESSION['esqueci_senha_msg'], $_SESSION['esqueci_senha_tipo']);
?>
<!DOCTYPE html>
<html lang="<?php echo onefitIdioma(); ?>" data-site-theme="<?php echo htmlspecialchars($GLOBALS['onefitTemaGlobal'] ?? 'dourado', ENT_QUOTES, 'UTF-8'); ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo of_t('Esqueci minha senha · {marca}', ['{marca}' => mb_strtoupper(onefitMarca()['name'])]); ?></title>
  <link rel="icon" data-brand-logo href="<?php echo onefitLogo(); ?>" type="image/webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/css/login.css'); ?>">
<?php onefitInterfaceHead(); ?>
</head>
<body class="login-body"
  <?php if ($mensagem): ?>
    data-form-message="<?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>"
    data-form-message-type="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>"
  <?php endif; ?>>
  <main class="login-page">
    <section class="login-visual">
      <video autoplay muted loop playsinline>
        <source src="<?php echo BASE_URL; ?>assets/img/videos/video-esqueci-senha.mp4" type="video/mp4">
      </video>
      <div class="login-visual-overlay"></div>
      <div class="login-visual-content">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo"><?php echo onefitWordmarkHtml(); ?></a>
        <div class="login-visual-text"><span class="eyebrow"><?php echo of_t('Recuperar acesso'); ?></span><h2><?php echo of_t('SUA CONTA'); ?><br><?php echo of_t('EM SEGURANÇA'); ?></h2></div>
      </div>
    </section>
    <section class="login-form-panel">
      <div class="login-form-wrap">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo login-logo-mobile"><?php echo onefitWordmarkHtml(); ?></a>
        <span class="tag"><?php echo of_t('Recuperar acesso'); ?></span>
        <h1><?php echo of_t('Esqueci minha senha'); ?></h1>
        <p class="login-subtitle"><?php echo of_t('Digite seu e-mail para receber instruções de redefinição de senha.'); ?></p>
        <form class="login-form" action="<?php echo BASE_URL; ?>pages/auth/senha/processar-recuperacao.php" method="POST">
          <div class="field"><label for="email"><?php echo of_t('E-mail'); ?></label><input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required></div>
          <button type="submit" class="btn btn-gold btn-block"><?php echo of_t('Enviar instruções'); ?></button>
        </form>
        <p class="login-footer-text"><?php echo of_t('Lembrou sua senha?'); ?> <a href="<?php echo BASE_URL; ?>pages/login/login.php"><?php echo of_t('Entrar'); ?></a></p>
      </div>
    </section>
  </main>
  <script src="<?php echo BASE_URL; ?>assets/js/login.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/js/login.js'); ?>"></script>
</body>
</html>
