<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/papeis.php';

$host = onefitEnv('DB_HOST');
$user = onefitEnv('DB_USER');
$dbname = onefitEnv('DB_NAME');
$password = onefitEnv('DB_PASSWORD');

if (!$host || !$user || !$dbname || $password === null) {
    throw new RuntimeException('A configuração do banco de dados está incompleta.');
}

// A exceção original traz usuário e host; registra só o código e falha com mensagem genérica.
try {
    $conn = new mysqli($host, $user, $password, $dbname);
} catch (mysqli_sql_exception $erro) {
    error_log('OneFit: falha ao conectar ao banco de dados (código ' . $erro->getCode() . ').');
    throw new RuntimeException('Não foi possível conectar ao banco de dados.');
}
if ($conn->connect_error) {
    throw new RuntimeException('Não foi possível conectar ao banco de dados.');
}

$conn->set_charset('utf8mb4');
if (function_exists('onefitCarregarInterface')) onefitCarregarInterface($conn);
// Cargo e status vêm do banco a cada requisição, não só do login.
onefitSessaoRevalidar($conn);
