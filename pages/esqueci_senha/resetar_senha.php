<?php
session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

$erro = '';
$tokenValido = false;

// O token pode chegar via GET (quando o usuário clica no link do "e-mail")
// ou via POST (quando o próprio formulário desta página é enviado).
$token = $_GET['token'] ?? ($_POST['token'] ?? '');

// ── 1. Sem token, não tem o que fazer aqui ─────────────────────────────────
if (empty($token)) {
    header('Location: ' . BASE_URL . 'pages/esqueci_senha/esqueci_senha.php');
    exit;
}

// ── 2. Busca o token no banco ──────────────────────────────────────────────
// A condição "usado = 0" já garante, na própria query, que um token usado
// não seja aceito de novo — não depende de mais nenhuma checagem depois.
$sql = "SELECT usuario_id, expira_em FROM recuperacao_senha WHERE token = ? AND usado = 0 LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param('s', $token);
$stmt->execute();
$resultado = $stmt->get_result();
$registro = $resultado->fetch_assoc();
$stmt->close();

// ── 3. Verifica também se ainda está dentro da validade (1 hora) ──────────
if ($registro && strtotime($registro['expira_em']) >= time()) {
    $tokenValido = true;
    $usuario_id = $registro['usuario_id'];
} else {
    $erro = 'Este link de redefinição é inválido ou expirou. Solicite um novo.';
}

// ── 4. Se o form foi enviado (POST) e o token é válido, tenta trocar a senha
if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha = $_POST['nova_senha'] ?? '';
    $confirmarSenha = $_POST['confirmar_senha'] ?? '';

    if (strlen($novaSenha) < 8) {
        $erro = 'A senha precisa ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        // password_hash cria um hash seguro (usa bcrypt por padrão) e já
        // inclui um "salt" aleatório dentro do próprio hash. NUNCA salve
        // a senha em texto puro nem use md5()/sha1() pra isso — ambos são
        // rápidos demais e fáceis de quebrar por força bruta hoje em dia.
        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);

        // Ajuste o nome da coluna "senha" abaixo se na sua tabela
        // `usuarios` o campo de senha tiver outro nome (ex: "password").
        $sqlUpdate = "UPDATE usuarios SET senha = ? WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        $stmtUpdate->bind_param('si', $hash, $usuario_id);
        $stmtUpdate->execute();
        $stmtUpdate->close();

        // ── 5. Invalida o token DEPOIS de trocar a senha com sucesso ──────
        // Assim, se o UPDATE acima falhar por algum motivo, o token continua
        // válido e o usuário pode tentar de novo em vez de ficar travado.
        $sqlUsado = "UPDATE recuperacao_senha SET usado = 1 WHERE token = ?";
        $stmtUsado = $conn->prepare($sqlUsado);
        $stmtUsado->bind_param('s', $token);
        $stmtUsado->execute();
        $stmtUsado->close();

        $_SESSION['esqueci_senha_msg'] = 'Senha redefinida com sucesso! Faça login com sua nova senha.';
        $_SESSION['esqueci_senha_tipo'] = 'sucesso';
        header('Location: ' . BASE_URL . 'pages/login/login.php');
        exit;
    }
}
?>

<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir senha — ONE FIT</title>
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
        <h1>Redefinir senha</h1>

        <?php if ($erro): ?>
          <p class="form-msg form-msg-erro"><?php echo htmlspecialchars($erro); ?></p>
        <?php endif; ?>

        <?php if ($tokenValido): ?>
        <p class="login-subtitle">Escolha uma nova senha para sua conta.</p>
        <form class="login-form" action="resetar_senha.php?token=<?php echo urlencode($token); ?>" method="POST">
          <div class="field">
            <label for="nova_senha">Nova senha</label>
            <input type="password" id="nova_senha" name="nova_senha" placeholder="Mínimo 8 caracteres" required minlength="8">
          </div>
          <div class="field">
            <label for="confirmar_senha">Confirmar nova senha</label>
            <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" required minlength="8">
          </div>
          <button type="submit" class="btn btn-gold btn-block">Redefinir senha</button>
        </form>
        <?php else: ?>
        <p class="login-footer-text">
          <a href="<?php echo BASE_URL; ?>pages/esqueci_senha/esqueci_senha.php">Solicitar novo link</a>
        </p>
        <?php endif; ?>

      </div>
    </section>

  </main>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script>
    AOS.init({ duration: 800, once: true, offset: 300 });
  </script>
</body>
</html>
