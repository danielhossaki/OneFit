<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/email-auth.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/rate-limit.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/login/login.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$mensagem = 'Se existir uma conta pendente para este e-mail, enviaremos uma nova confirmação em instantes.';

if (filter_var($email, FILTER_VALIDATE_EMAIL) && onefitPodeEnviarEmail('verificacao', $email)) {
    $stmt = $conn->prepare('SELECT id_usuario, nome, email_verificado FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuario && (int) $usuario['email_verificado'] !== 1) {
        try {
            $conn->begin_transaction();
            $token = onefitCriarToken($conn, 'verificacao_email_tokens', (int) $usuario['id_usuario'], '+24 hours');
            $conn->commit();
            onefitEnviarVerificacaoEmail($email, $usuario['nome'], $token);
        } catch (Throwable $erro) {
            $conn->rollback();
            error_log('Falha segura ao reenviar confirmação de e-mail da OneFit.');
        }
    }
}

$_SESSION['login_tipo'] = 'sucesso';
$_SESSION['login_msg'] = $mensagem;
header('Location: ' . BASE_URL . 'pages/login/login.php');
exit;
