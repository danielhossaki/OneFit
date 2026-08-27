<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/email-auth.php');

function onefitBuscaTokenRedefinicao(mysqli $conn, string $hash, bool $bloquear = false): ?array
{
    $sql = 'SELECT id, usuario_id, expira_em FROM recuperacao_senha_tokens
            WHERE token_hash = ? AND usado_em IS NULL LIMIT 1';
    if ($bloquear) {
        $sql .= ' FOR UPDATE';
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $hash);
    $stmt->execute();
    $registro = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $registro ?: null;
}

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$erro = '';
$sucesso = '';
$tokenValido = false;
$hash = onefitTokenFormatoValido($token) ? hash('sha256', $token) : '';

if ($hash === '') {
    $erro = 'Este link é inválido, expirou ou já foi utilizado.';
} else {
    try {
        $registro = onefitBuscaTokenRedefinicao($conn, $hash);
        $tokenValido = $registro && strtotime($registro['expira_em']) >= time();
        if (!$tokenValido) {
            $erro = 'Este link é inválido, expirou ou já foi utilizado.';
        }
    } catch (Throwable $erroBanco) {
        error_log('Falha segura ao consultar token de redefinição da OneFit.');
        $erro = 'Não foi possível validar este link agora. Tente novamente mais tarde.';
    }
}

if ($tokenValido && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $novaSenha = (string) ($_POST['nova_senha'] ?? '');
    $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

    if (strlen($novaSenha) < 8) {
        $erro = 'A nova senha deve ter pelo menos 8 caracteres.';
    } elseif ($novaSenha !== $confirmarSenha) {
        $erro = 'As senhas não coincidem.';
    } else {
        try {
            $conn->begin_transaction();
            $registro = onefitBuscaTokenRedefinicao($conn, $hash, true);

            if (!$registro || strtotime($registro['expira_em']) < time()) {
                $conn->rollback();
                $tokenValido = false;
                $erro = 'Este link é inválido, expirou ou já foi utilizado.';
            } else {
                $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                $atualizaSenha = $conn->prepare('UPDATE usuarios SET senha = ? WHERE id_usuario = ?');
                $atualizaSenha->bind_param('si', $senhaHash, $registro['usuario_id']);
                $atualizaSenha->execute();
                $atualizaSenha->close();

                $marcaUsado = $conn->prepare('UPDATE recuperacao_senha_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
                $marcaUsado->bind_param('i', $registro['id']);
                $marcaUsado->execute();
                $marcaUsado->close();

                $invalidaOutros = $conn->prepare('UPDATE recuperacao_senha_tokens SET usado_em = NOW() WHERE usuario_id = ? AND usado_em IS NULL');
                $invalidaOutros->bind_param('i', $registro['usuario_id']);
                $invalidaOutros->execute();
                $invalidaOutros->close();

                $conn->commit();
                $tokenValido = false;
                $sucesso = 'Senha alterada com sucesso.';
            }
        } catch (Throwable $erroBanco) {
            $conn->rollback();
            error_log('Falha segura ao redefinir senha da OneFit.');
            $erro = 'Não foi possível alterar a senha neste momento. Tente novamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redefinir senha · ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
</head>
<body class="login-body">
  <main class="login-page">
    <section class="login-form-panel" style="grid-column: 1 / -1;">
      <div class="login-form-wrap">
        <span class="tag">Recuperar acesso</span>
        <h1>Redefinir senha</h1>
        <?php if ($erro): ?><p class="form-msg form-msg-erro" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($erro, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
        <?php if ($sucesso): ?>
          <p class="form-msg form-msg-sucesso" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8'); ?></p>
          <a class="btn btn-gold btn-block" href="<?php echo BASE_URL; ?>pages/login/login.php">Entrar</a>
        <?php elseif ($tokenValido): ?>
          <p class="login-subtitle">Defina uma nova senha para sua conta.</p>
          <form class="login-form" method="POST" action="<?php echo BASE_URL; ?>redefinir-senha.php">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
            <div class="field"><label for="nova_senha">Nova senha</label><input type="password" id="nova_senha" name="nova_senha" minlength="8" required></div>
            <div class="field"><label for="confirmar_senha">Confirmar nova senha</label><input type="password" id="confirmar_senha" name="confirmar_senha" minlength="8" required></div>
            <button type="submit" class="btn btn-gold btn-block">Salvar nova senha</button>
          </form>
        <?php else: ?>
          <a class="btn btn-gold btn-block" href="<?php echo BASE_URL; ?>pages/esqueci_senha/esqueci_senha.php">Solicitar novo link</a>
        <?php endif; ?>
      </div>
    </section>
  </main>
</body>
</html>
