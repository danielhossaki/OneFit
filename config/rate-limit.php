<?php

function onefitPodeEnviarEmail(string $acao, string $identificador, int $intervaloSegundos = 60): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $chave = hash('sha256', $acao . '|' . strtolower($identificador) . '|' . $ip);
    $agora = time();
    $ultimoEnvio = (int) ($_SESSION['onefit_email_rate_limit'][$chave] ?? 0);

    if ($ultimoEnvio > 0 && ($agora - $ultimoEnvio) < $intervaloSegundos) {
        return false;
    }

    $_SESSION['onefit_email_rate_limit'][$chave] = $agora;
    return true;
}
