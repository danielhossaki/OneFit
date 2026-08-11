<?php
session_start();

$perfisValidos = ['admin', 'profissional', 'aluno'];

if (isset($_GET['perfil']) && in_array($_GET['perfil'], $perfisValidos, true)) {
    $_SESSION['perfil'] = $_GET['perfil'];
}

if (!isset($_SESSION['perfil']) || !in_array($_SESSION['perfil'], $perfisValidos, true)) {
    $_SESSION['perfil'] = 'admin';
}

if (!isset($_SESSION['nome'])) {
    $nomesPorPerfil = [
        'admin' => 'Administrador',
        'profissional' => 'Profissional',
        'aluno' => 'Aluno',
    ];
    $_SESSION['nome'] = $nomesPorPerfil[$_SESSION['perfil']];
}
