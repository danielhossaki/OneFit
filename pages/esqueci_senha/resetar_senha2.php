<?php
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

if (!isset($_GET['token'])) {
    die("Token inválido.");
}

$token = $_GET['token'];

// Verifica se o token existe e está válido
$stmt = $conn->prepare("SELECT usuario_id, expira_em, usado FROM recuperacao_senha WHERE token = ?");
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Token inválido ou inexistente.");
}

$dados = $result->fetch_assoc();

// Verifica validade
if ($dados['usado'] == 1 || strtotime($dados['expira_em']) < time()) {
    die("Token expirado ou já utilizado.");
}

$usuario_id = $dados['usuario_id'];

// Se formulário enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'];
    $confirmar = $_POST['confirmar'];

    if ($senha === $confirmar) {
        $hash = password_hash($senha, PASSWORD_DEFAULT);

        // Atualiza senha do usuário
        $stmt = $conn->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
        $stmt->bind_param("si", $hash, $usuario_id);
        $stmt->execute();

        // Marca token como usado
        $stmt = $conn->prepare("UPDATE recuperacao_senha SET usado = 1 WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();

        echo "Senha redefinida com sucesso! <a href='" . BASE_URL . "pages/login/login.php'>Entrar</a>";
        exit;
    } else {
        echo "As senhas não coincidem.";
    }
}
?>

<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <title>Redefinir senha — ONEFIT</title>
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
</head>
<body class="login-body">
  <main class="login-page">
    <section class="login-form-panel">
      <div class="login-form-wrap">
        <h1>Redefinir senha</h1>
        <p>Digite sua nova senha abaixo.</p>
        <form method="POST">
          <div class="field">
            <label for="senha">Nova senha</label>
            <input type="password" id="senha" name="senha" required>
          </div>
          <div class="field">
            <label for="confirmar">Confirmar senha</label>
            <input type="password" id="confirmar" name="confirmar" required>
          </div>
          <button type="submit" class="btn btn-gold btn-block">Redefinir senha</button>
        </form>
      </div>
    </section>
  </main>
</body>
</html>
