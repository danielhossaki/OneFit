<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/email-auth.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$tipo = 'erro';
$mensagem = 'O link de confirmação é inválido, expirou ou já foi utilizado.';
$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
  $mensagem = 'Informe o link completo de confirmação recebido por e-mail.';
} elseif (onefitTokenFormatoValido($token)) {
  try {
    $hash = hash('sha256', $token);
    $conn->begin_transaction();

    $pendenteStmt = $conn->prepare(
      'SELECT * FROM matricula_cadastros_pendentes
       WHERE token_hash = ? AND usado_em IS NULL LIMIT 1 FOR UPDATE'
    );
    $pendenteStmt->bind_param('s', $hash);
    $pendenteStmt->execute();
    $pendente = $pendenteStmt->get_result()->fetch_assoc();
    $pendenteStmt->close();

    if ($pendente) {
      if (strtotime($pendente['expira_em']) < time()) {
        $marca = $conn->prepare('UPDATE matricula_cadastros_pendentes SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
        $marca->bind_param('i', $pendente['id']);
        $marca->execute();
        $marca->close();
        $conn->commit();
        $mensagem = 'Este link de confirmação expirou. Inicie o cadastro novamente.';
      } else {
        $stmt = $conn->prepare(
          "INSERT INTO usuarios
            (nome, data_nascimento, genero, cpf, endereco, cidade_estado, email, email_verificado, celular, senha, tipo_usuario, status)
           VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 'aluno', 'pendente_pagamento')"
        );
        $stmt->bind_param(
          'ssssssssss',
          $pendente['nome'], $pendente['data_nascimento'], $pendente['genero'], $pendente['cpf'],
          $pendente['endereco'], $pendente['cidade_estado'], $pendente['email'], $pendente['celular'], $pendente['senha']
        );
        $stmt->execute();
        $usuarioId = (int)$conn->insert_id;
        $stmt->close();
        $marca = $conn->prepare('UPDATE matricula_cadastros_pendentes SET usado_em = NOW() WHERE id = ? AND usado_em IS NULL');
        $marca->bind_param('i', $pendente['id']);
        $marca->execute();
        $marca->close();
        $conn->commit();
        $_SESSION['id_usuario'] = $usuarioId;
        $_SESSION['matricula_wizard']['usuario'] = $usuarioId;
        $_SESSION['pagamento_matricula_sem_login'] = true;
        $_SESSION['matricula_retornar'] = true;
        header('Location: ' . BASE_URL . 'pages/matricula/matricula.php?fluxo=' . rawurlencode($_SESSION['matricula_wizard']['id']), true, 303);
        exit;
      }
    } else {

    $stmt = $conn->prepare(
      'SELECT t.id, t.usuario_id, t.expira_em, u.email FROM verificacao_email_tokens t
             JOIN usuarios u ON u.id_usuario = t.usuario_id
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
      $wizard = $_SESSION['matricula_wizard'] ?? null;
      $retomarPagamento = !empty($_SESSION['matricula_retornar'])
        && is_array($wizard)
        && (int)($wizard['usuario'] ?? -1) === 0
        && ($wizard['dados']['email'] ?? '') === $registro['email'];
      if ($retomarPagamento) {
        unset($_SESSION['id_usuario'], $_SESSION['nome'], $_SESSION['email'], $_SESSION['tipo_usuario'], $_SESSION['genero']);
        $_SESSION['id_usuario'] = (int)$registro['usuario_id'];
        $_SESSION['matricula_wizard']['usuario'] = (int)$registro['usuario_id'];
        $_SESSION['pagamento_matricula_sem_login'] = true;
        header('Location: ' . BASE_URL . 'pages/matricula/matricula.php?fluxo=' . rawurlencode($wizard['id']), true, 303);
        exit;
      }
      $mensagem = 'E-mail confirmado com sucesso. Agora você já pode acessar sua conta.';
    }
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
  <link rel="icon" href="<?php echo BASE_URL; ?>assets/img/logo/logo.webp" type="image/webp">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@500;700;900&family=Manrope:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/home.css">
  <link rel="stylesheet" href="<?php echo BASE_URL; ?>assets/css/login.css?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/css/login.css'); ?>">
</head>

<body class="login-body"
  data-form-message="<?php echo htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'); ?>"
  data-form-message-type="<?php echo htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8'); ?>">
  <main class="login-page login-page-centered">
    <section class="login-form-panel login-form-panel-centered">
      <div class="login-form-wrap">
        <a href="<?php echo BASE_URL; ?>index.php" class="login-logo login-logo-mobile">ONE<span>FIT</span></a>
        <span class="tag">Segurança da conta</span>
        <h1>Confirmação de e-mail</h1>
        <a class="btn btn-gold btn-block" href="<?php echo BASE_URL; ?>pages/login/login.php">Entrar na minha conta</a>
      </div>
    </section>
  </main>
  <script src="<?php echo BASE_URL; ?>assets/js/login.js?v=<?php echo filemtime($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/assets/js/login.js'); ?>"></script>
</body>

</html>