<?php

require_once __DIR__ . '/parametros.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params['path'] ?: '/', $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
}

setcookie('remember_token', '', time() - 42000, '/', '', false, true);
session_destroy();

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
header("Location: " . BASE_URL . "pages/login/login.php");
exit;
