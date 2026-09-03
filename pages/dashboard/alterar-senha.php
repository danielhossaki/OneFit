<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/auth.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

$erro = '';
$sucesso = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senhaAtual = (string) ($_POST['senha_atual'] ?? '');
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
    $idUsuario = (int) $_SESSION['id_usuario'];

    $csrfToken = (string) ($_POST['csrf_token'] ?? '');

    if ($csrfToken === '' || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } elseif (!$senhaAtual || !$novaSenha || !$confirmarSenha) {
        $erro = 'Preencha todos os campos.';
    } elseif (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        $stmt = $conn->prepare('SELECT senha FROM usuarios WHERE id_usuario = ? LIMIT 1');
        $stmt->bind_param('i', $idUsuario);
        $stmt->execute();
        $usuario = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$usuario || !password_verify($senhaAtual, $usuario['senha'])) {
            $erro = 'A senha atual está incorreta.';
        } else {
            try {
                $conn->begin_transaction();
                $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $atualiza = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id_usuario = ?');
                $atualiza->bind_param('si', $hash, $idUsuario);
                $atualiza->execute();
                $atualiza->close();

                $invalida = $conn->prepare('UPDATE recuperacao_senha_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL');
                $invalida->bind_param('i', $idUsuario);
                $invalida->execute();
                $invalida->close();
                $conn->commit();
                $sucesso = 'Senha alterada com sucesso.';
            } catch (Throwable $erroBanco) {
                $conn->rollback();
                error_log('Falha segura ao alterar senha autenticada da OneFit.');
                $erro = 'Não foi possível alterar a senha neste momento. Tente novamente.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Alterar senha · ONE FIT</title>
  <script>
    (() => { let p = 'dark'; try { p = localStorage.getItem('onefit-theme') || p; } catch (e) {} const t = p === 'system' ? (matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark') : p; document.documentElement.setAttribute('data-theme', t === 'light' ? 'light' : 'dark'); })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
</head>
<body class="login-body">
  <main class="login-page">
    <section class="login-form-panel" style="grid-column: 1 / -1;">
      <div class="login-form-wrap">
        <span class="tag">Segurança da conta</span>
        <h1>Alterar senha</h1>
        <p class="login-subtitle">Use sua senha atual para definir uma nova senha de acesso.</p>
        <?php if ($erro): ?><p class="form-msg form-msg-erro" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($sucesso): ?><p class="form-msg form-msg-sucesso" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <form class="login-form" method="POST" action="<?php echo BASE_URL; ?>pages/dashboard/alterar-senha.php">
          <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">
          <div class="field"><label for="senha_atual">Senha atual</label><input type="password" id="senha_atual" name="senha_atual" required></div>
          <div class="field"><label for="nova_senha">Nova senha</label><input type="password" id="nova_senha" name="nova_senha" minlength="8" required></div>
          <div class="field"><label for="confirmar_senha">Confirmar nova senha</label><input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required></div>
          <button type="submit" class="btn btn-gold btn-block">Alterar senha</button>
        </form>
        <p class="login-footer-text"><a href="<?php echo BASE_URL; ?>pages/dashboard/dashboard.php">Voltar ao painel</a></p>
      </div>
    </section>
  </main>
</body>
</html>
