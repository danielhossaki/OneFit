<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/email-auth.php');
require_once($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/rate-limit.php');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . 'pages/esqueci_senha/esqueci_senha.php');
    exit;
}

$email = trim((string) ($_POST['email'] ?? ''));
$mensagem = 'Se existir uma conta associada a este e-mail, enviaremos as instruções para recuperá-la.';

if (filter_var($email, FILTER_VALIDATE_EMAIL) && onefitPodeEnviarEmail('recuperacao-senha', $email)) {
    $stmt = $conn->prepare('SELECT id_usuario, nome, email FROM usuarios WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $usuario = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($usuario) {
        try {
            $conn->begin_transaction();
            $token = onefitCriarToken($conn, 'recuperacao_senha_tokens', (int) $usuario['id_usuario'], '+30 minutes');
            $conn->commit();
            onefitEnviarRedefinicaoSenha($usuario['email'], $usuario['nome'], $token);
        } catch (Throwable $erro) {
            $conn->rollback();
            error_log('Falha segura ao solicitar redefinição de senha da OneFit.');
        }
    }
}

$_SESSION['esqueci_senha_tipo'] = 'sucesso';
$_SESSION['esqueci_senha_msg'] = $mensagem;
header('Location: ' . BASE_URL . 'pages/esqueci_senha/esqueci_senha.php');
exit;
