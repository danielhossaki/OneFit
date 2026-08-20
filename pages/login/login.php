<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? null;
    $senha = $_POST['password'] ?? null;
    $lembrar = isset($_POST['remember']);

    if ($email && $senha) {

        $stmt = $conn->prepare(
            "SELECT id_usuario, nome, senha, tipo_usuario, status, genero FROM usuarios WHERE email = ? LIMIT 1"
        );
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $usuario = $result->fetch_assoc();
        $stmt->close();


        // e-mail ou senha incorretos
        if (!$usuario || !password_verify($senha, $usuario['senha'])) {
            header("Location: login.php?msg=1"); 
            exit;
        }

        // conta inativa/bloqueada
        if ($usuario['status'] !== 'ativo') {
            header("Location: login.php?msg=2");
            exit;
        }

        // login ok - inicia sessão
        session_regenerate_id(true);
        $_SESSION['id_usuario'] = $usuario['id_usuario'];
        $_SESSION['nome'] = $usuario['nome'];
        $_SESSION['email'] = $email;
        $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
        $_SESSION['genero'] = $usuario['genero'];

        // "lembrar de mim" - cookie com token válido por 30 dias
        if ($lembrar) {
            $token = bin2hex(random_bytes(32));

            $stmtToken = $conn->prepare("UPDATE usuarios SET remember_token = ? WHERE id_usuario = ?");
            $stmtToken->bind_param("si", $token, $usuario['id_usuario']);
            $stmtToken->execute();
            $stmtToken->close();

            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
        }

        header("Location: " . BASE_URL . "pages/dashboard/dashboard.php");
        exit;

    } else {
        header("Location: login.php?msg=3"); // campos vazios
        exit;
    }
}
?>

<html lang="pt-BR">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Entrar · ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <!-- link da fonte -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- link do css -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
  <!-- link das animações -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- link do favicon -->
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body class="login-body">

  <main class="login-page">

    <!-- Painel visual (some em telas pequenas) -->
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

    <!-- Painel de preenchimento -->
    <section class="login-form-panel">
      <div class="login-form-wrap" data-aos="fade-right" data-aos-delay="250" >

        <a href="<?php echo BASE_URL; ?>pages/index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>

        <span class="tag">Bem-vindo de volta</span>
        <h1>Entrar</h1>
        <p class="login-subtitle">Acesse sua conta para acompanhar treinos, planos e agendamentos.</p>

        <form class="login-form" action="#" method="POST" >

          <div class="field">
            <label for="email">E-mail</label>
            <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" required>
          </div>

          <div class="field">
            <label for="password">Senha</label>
            <div class="password-wrap">
              <input type="password" id="password" name="password" placeholder="••••••••" required>
              <button type="button" class="toggle-password" aria-label="Mostrar senha" aria-pressed="false" data-target="password">
                <svg class="icon-eye" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M1.5 12S5 5 12 5s10.5 7 10.5 7-3.5 7-10.5 7S1.5 12 1.5 12z" />
                  <circle cx="12" cy="12" r="3.2" />
                </svg>
                <svg class="icon-eye-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
                  <path d="M3 3l18 18" />
                  <path d="M10.6 5.2A10.7 10.7 0 0112 5c7 0 10.5 7 10.5 7a15.6 15.6 0 01-3.4 4.3M6.5 6.6C3.4 8.5 1.5 12 1.5 12s3.5 7 10.5 7c1.4 0 2.7-.28 3.85-.75" />
                  <path d="M9.5 9.6a3.2 3.2 0 004.4 4.5" />
                </svg>
              </button>
            </div>
          </div>

          <div class="login-row">
            <label class="checkbox">
              <input type="checkbox" name="remember">
              <span>Lembrar de mim</span>
            </label>
            <a href="<?php echo BASE_URL; ?>pages/esqueci_senha/esqueci_senha.php" class="forgot-link">Esqueci minha senha</a>
          </div>

          <button type="submit" class="btn btn-gold btn-block">Entrar</button>

        </form>

        <p class="login-footer-text">Ainda não treina com a gente? <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php">Criar conta</a></p>

      </div>
    </section>

  </main>

  <!-- Link para JavaScript -->
  <script src="<?php echo BASE_URL; ?>assets/js/login.js"></script>

    <!-- Link para animações AOS JS -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Animacão do AOS JS -->
  <script>
    AOS.init({
      duration: 800,
      once: true,
      offset: 300
    });
  </script>

</body>

</html>
