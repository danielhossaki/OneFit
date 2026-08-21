<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: esqueci_senha.php');
    exit;
}

$email = trim($_POST['email'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $_SESSION['esqueci_senha_msg'] = 'Informe um e-mail válido.';
    $_SESSION['esqueci_senha_tipo'] = 'erro';

    header('Location: esqueci_senha.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| VERIFICA SE O E-MAIL EXISTE
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT id_usuario, email
    FROM usuarios
    WHERE email = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param('s', $email);

$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| SALVA O E-MAIL NA SESSÃO
|--------------------------------------------------------------------------
*/

if ($usuario) {

    $_SESSION['recuperacao_email'] = $usuario['email'];
    $_SESSION['recuperacao_usuario_id'] = $usuario['id_usuario'];

    $_SESSION['esqueci_senha_msg'] =
        'Se este e-mail estiver cadastrado, você poderá definir uma nova senha.';

    $_SESSION['esqueci_senha_tipo'] = 'sucesso';

} else {

    /*
    | Mantém uma mensagem neutra.
    | Não informa diretamente se o e-mail existe ou não.
    */

    $_SESSION['esqueci_senha_msg'] =
        'Se este e-mail estiver cadastrado, você poderá continuar a recuperação.';

    $_SESSION['esqueci_senha_tipo'] = 'sucesso';
}


header('Location: esqueci_senha.php');
exit;