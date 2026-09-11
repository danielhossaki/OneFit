<?php

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/interface.php';
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

$appUrl = onefitEnv('APP_URL', 'http://localhost/AN25/OneFit/');
define('BASE_URL', rtrim($appUrl ?: '', '/') . '/');
define('Dir', $_SERVER['DOCUMENT_ROOT'] . '/AN25/OneFit');
