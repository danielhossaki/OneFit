<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

session_start();

$mensagensLogin = [
  '1' => ['tipo' => 'erro', 'texto' => 'E-mail ou senha incorretos. Confira os dados e tente novamente.'],
  '2' => ['tipo' => 'erro', 'texto' => 'Sua conta está inativa ou bloqueada. Entre em contato com a ONE FIT caso deseja reativar sua conta.'],
  '3' => ['tipo' => 'erro', 'texto' => 'Preencha o e-mail e a senha para entrar.'],
  '4' => ['tipo' => 'sucesso', 'texto' => 'Cadastro realizado com sucesso! Agora você já pode entrar.'],
  '5' => ['tipo' => 'erro', 'texto' => 'Digite um endereço de e-mail válido.'],
];

$mensagemLogin = $mensagensLogin[(string) ($_GET['msg'] ?? '')] ?? null;

$emailPendenteVerificacao = $_SESSION['email_verificacao_pendente'] ?? '';
unset($_SESSION['email_verificacao_pendente']);

if (!$mensagemLogin && !empty($_SESSION['login_msg'])) {
  $mensagemLogin = [
    'tipo' => $_SESSION['login_tipo'] ?? 'sucesso',
    'texto' => $_SESSION['login_msg'],
  ];
}

unset($_SESSION['login_msg'], $_SESSION['login_tipo']);

if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $email = trim($_POST['email'] ?? '');
  $senha = $_POST['password'] ?? null;
  $lembrar = isset($_POST['remember']);

  if ($email && $senha) {

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      header("Location: login.php?msg=5");
      exit;
    }

    $stmt = $conn->prepare(
      "SELECT id_usuario, nome, senha, tipo_usuario, status, genero, email_verificado FROM usuarios WHERE email = ? LIMIT 1"
    );
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();


    // Usa uma mensagem única para não revelar se o e-mail está cadastrado.
    if (!$usuario || !password_verify($senha, $usuario['senha'])) {
      header("Location: login.php?msg=1");
      exit;
    }

    if ((int) $usuario['email_verificado'] !== 1) {
      $_SESSION['email_verificacao_pendente'] = $email;
      $_SESSION['login_tipo'] = 'erro';
      $_SESSION['login_msg'] = 'Você precisa confirmar seu e-mail antes de acessar sua conta.';
      header("Location: login.php");
      exit;
    }

    // Impede o acesso de contas que não estão ativas.
    if ($usuario['status'] !== 'ativo') {
      header("Location: login.php?msg=2");
      exit;
    }

    // Renova a sessão antes de armazenar os dados do usuário autenticado.
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = $usuario['id_usuario'];
    $_SESSION['nome'] = $usuario['nome'];
    $_SESSION['email'] = $email;
    $_SESSION['tipo_usuario'] = $usuario['tipo_usuario'];
    $_SESSION['genero'] = $usuario['genero'];

    // Mantém a autenticação por 30 dias quando o usuário solicita.
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
    header("Location: login.php?msg=3"); // Informa que e-mail ou senha não foram enviados.
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
  <!-- Fontes usadas pela identidade visual da página. -->
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
  <!-- Estilos globais e específicos do login. -->
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/css/login.css'); ?>">
  <!-- Biblioteca de animações de entrada. -->
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <!-- Ícone exibido na aba do navegador. -->
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/x-icon">
</head>

<body class="login-body"
  <?php if ($mensagemLogin): ?>
    data-form-message="<?php echo htmlspecialchars($mensagemLogin['texto'], ENT_QUOTES, 'UTF-8'); ?>"
    data-form-message-type="<?php echo htmlspecialchars($mensagemLogin['tipo'], ENT_QUOTES, 'UTF-8'); ?>"
  <?php endif; ?>>

  <main class="login-page">

    <!-- Painel institucional ocultado em telas pequenas. -->
    <section class="login-visual" data-aos="fade-right">
      <video autoplay muted loop playsinline>
        <source src="<?php echo BASE_URL; ?>assets/img/videos/video-login.mp4" type="video/mp4">
        Seu navegador não suporta vídeos
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

    <!-- Formulário responsável pela autenticação. -->
    <section class="login-form-panel">
      <div class="login-form-wrap" data-aos="fade-right" data-aos-delay="250">

        <a href="<?php echo BASE_URL; ?>pages/index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>

        <span class="tag">Bem-vindo de volta</span>
        <h1>Entrar</h1>


        <p class="login-subtitle">
          Acesse sua conta para acompanhar treinos, planos e agendamentos.
        </p>

        <form class="login-form" action="#" method="POST" novalidate>
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

        <?php if ($emailPendenteVerificacao): ?>
          <form class="login-form" action="<?php echo BASE_URL; ?>pages/reenviar-verificacao.php" method="POST">
            <input type="hidden" name="email" value="<?php echo htmlspecialchars($emailPendenteVerificacao, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="forgot-link">Reenviar e-mail de confirmação</button>
          </form>
        <?php endif; ?>

        <p class="login-footer-text">Ainda não treina com a gente? <a href="<?php echo BASE_URL; ?>pages/matricula/matricula.php">Criar conta</a></p>

      </div>
    </section>

  </main>

  <!-- Controla senha visível e mensagens retornadas pelo PHP. -->
  <script src="<?php echo BASE_URL; ?>assets/js/login.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/js/login.js'); ?>"></script>

  <!-- Carrega e inicializa as animações de entrada. -->
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>

  <!-- Configuração das animações desta página. -->
  <script>
    AOS.init({
      duration: 800,
      once: true,
      offset: 300
    });
  </script>

</body>

</html>
