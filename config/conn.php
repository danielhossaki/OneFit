<?php
$host = "onefit.mysql.dbaas.com.br";
$user = "onefit";
$dbname = "onefit";
$password = "Academi@321";
$conn = new mysqli($host, $user, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

