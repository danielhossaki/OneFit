<?php

require($_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit/config/parametros.php');

$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$destino = BASE_URL . 'redefinir-senha.php';
if ($token !== '') {
    $destino .= '?token=' . rawurlencode($token);
}

header('Location: ' . $destino, true, 302);
exit;
