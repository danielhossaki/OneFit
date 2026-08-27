<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/email-auth.php');

$tipo = 'erro';
$mensagem = 'O link de confirmação é inválido, expirou ou já foi utilizado.';
$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    $mensagem = 'Informe o link completo de confirmação recebido por e-mail.';
} elseif (onefitTokenFormatoValido($token)) {
    try {
        $hash = hash('sha256', $token);
        $conn->begin_transaction();

        $stmt = $conn->prepare(
            'SELECT id, usuario_id, expira_em FROM verificacao_email_tokens
             WHERE token_hash = ? AND usado_em IS NULL LIMIT 1 FOR UPDATE'
        );
        $stmt->bind_param('s', $hash);
        $stmt->execute();
        $registro = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$registro) {
            $conn->rollback();
        } elseif (strtotime($registro['expira_em']) < time()) {
            $marca = $conn->prepare('UPDATE verificacao_email_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
            $marca->bind_param('i', $registro['id']);
            $marca->execute();
            $marca->close();
            $conn->commit();
            $mensagem = 'Este link de confirmação expirou. Solicite um novo envio pela tela de login.';
        } else {
            $usuario = $conn->prepare('UPDATE usuarios SET email_verificado = 1 WHERE id_usuario = ?');
            $usuario->bind_param('i', $registro['usuario_id']);
            $usuario->execute();
            $usuario->close();

            $marca = $conn->prepare('UPDATE verificacao_email_tokens SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
            $marca->bind_param('i', $registro['id']);
            $marca->execute();
            $marca->close();
            $conn->commit();
            $tipo = 'sucesso';
            $mensagem = 'E-mail confirmado com sucesso. Agora você já pode acessar sua conta.';
        }
    } catch (Throwable $erro) {
        $conn->rollback();
        error_log('Falha segura ao confirmar e-mail da OneFit.');
        $mensagem = 'Não foi possível confirmar o e-mail neste momento. Tente novamente mais tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirmar e-mail · ONE FIT</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css">
</head>
<body class="login-body">
  <main class="login-page">
    <section class="login-form-panel" style="grid-column: 1 / -1;">
      <div class="login-form-wrap">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo login-logo-mobile" style="display:block;">ONE<span>FIT</span></a>
        <span class="tag">Segurança da conta</span>
        <h1>Confirmação de e-mail</h1>
        <p class="form-msg form-msg-<?php echo $tipo; ?>" style="position:static;transform:none;width:auto;"> <?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?></p>
        <a class="btn btn-gold btn-block" href="<?php echo BASE_URL; ?>pages/login/login.php">Entrar na minha conta</a>
      </div>
    </section>
  </main>
</body>
</html>
