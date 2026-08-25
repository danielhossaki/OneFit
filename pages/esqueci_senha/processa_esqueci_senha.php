<?php

session_start();

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');
require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/conn.php');


/*
|--------------------------------------------------------------------------
| VERIFICA MÉTODO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header(
        'Location: ' .
        BASE_URL .
        'pages/esqueci_senha/esqueci_senha.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| LIMPA TOKEN ANTERIOR
|--------------------------------------------------------------------------
*/

unset($_SESSION['reset_token']);


/*
|--------------------------------------------------------------------------
| PEGA E-MAIL
|--------------------------------------------------------------------------
*/

$email = trim(
    $_POST['email'] ?? ''
);


/*
|--------------------------------------------------------------------------
| VALIDA E-MAIL
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    $_SESSION['esqueci_senha_msg'] =
        'Informe um e-mail válido.';

    $_SESSION['esqueci_senha_tipo'] =
        'erro';


    header(
        'Location: ' .
        BASE_URL .
        'pages/esqueci_senha/esqueci_senha.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| BUSCA USUÁRIO
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("
    SELECT id_usuario
    FROM usuarios
    WHERE email = ?
    LIMIT 1
");

$stmt->bind_param(
    's',
    $email
);

$stmt->execute();

$resultado = $stmt->get_result();

$usuario = $resultado->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| SE USUÁRIO EXISTE
|--------------------------------------------------------------------------
*/

if ($usuario) {


    $usuario_id = $usuario['id_usuario'];


    /*
    |--------------------------------------------------------------------------
    | GERA TOKEN
    |--------------------------------------------------------------------------
    */

    $token = bin2hex(
        random_bytes(32)
    );


    /*
    |--------------------------------------------------------------------------
    | TOKEN EXPIRA EM 1 HORA
    |--------------------------------------------------------------------------
    */

    $expira_em = date(
        'Y-m-d H:i:s',
        strtotime('+1 hour')
    );


    /*
    |--------------------------------------------------------------------------
    | INVALIDA TOKENS ANTIGOS
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        UPDATE recuperacao_senha
        SET usado = 1
        WHERE usuario_id = ?
        AND usado = 0
    ");

    $stmt->bind_param(
        'i',
        $usuario_id
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | SALVA NOVO TOKEN
    |--------------------------------------------------------------------------
    */

    $stmt = $conn->prepare("
        INSERT INTO recuperacao_senha
        (
            usuario_id,
            token,
            expira_em,
            usado
        )
        VALUES (?, ?, ?, 0)
    ");

    $stmt->bind_param(
        'iss',
        $usuario_id,
        $token,
        $expira_em
    );

    $stmt->execute();

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | SALVA TOKEN NA SESSÃO
    |--------------------------------------------------------------------------
    */

    $_SESSION['reset_token'] = $token;


    /*
    |--------------------------------------------------------------------------
    | LINK DE RECUPERAÇÃO
    |--------------------------------------------------------------------------
    */

    $link =
        BASE_URL .
        'pages/esqueci_senha/resetar_senha.php?token=' .
        urlencode($token);


    /*
    |--------------------------------------------------------------------------
    | SALVA LINK NO LOG PARA TESTE
    |--------------------------------------------------------------------------
    */

    $logDir = __DIR__ . '/../../storage/logs';


    if (!is_dir($logDir)) {

        mkdir(
            $logDir,
            0775,
            true
        );
    }


    $linha =
        '[' .
        date('Y-m-d H:i:s') .
        '] E-mail: ' .
        $email .
        ' | Link: ' .
        $link .
        PHP_EOL;


    file_put_contents(
        $logDir . '/recuperacao_senha.log',
        $linha,
        FILE_APPEND
    );
}


/*
|--------------------------------------------------------------------------
| MENSAGEM
|--------------------------------------------------------------------------
*/

$_SESSION['esqueci_senha_msg'] =
    'Se este e-mail estiver cadastrado, você receberá as instruções de redefinição em instantes.';

$_SESSION['esqueci_senha_tipo'] =
    'sucesso';


/*
|--------------------------------------------------------------------------
| VOLTA PARA ESQUECI SENHA
|--------------------------------------------------------------------------
*/

header(
    'Location: ' .
    BASE_URL .
    'pages/esqueci_senha/esqueci_senha.php'
);

exit;