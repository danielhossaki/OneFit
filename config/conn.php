<?php

require_once __DIR__ . '/env.php';

$host = onefitEnv('DB_HOST');
$user = onefitEnv('DB_USER');
$dbname = onefitEnv('DB_NAME');
$password = onefitEnv('DB_PASSWORD');

if (!$host || !$user || !$dbname || $password === null) {
    throw new RuntimeException('A configuração do banco de dados está incompleta.');
}

$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    throw new RuntimeException('Não foi possível conectar ao banco de dados.');
}

$conn->set_charset('utf8mb4');
