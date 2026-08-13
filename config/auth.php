<?php

session_start();

if (!isset($_SESSION['id_usuario'])) {
    header("Location: " . BASE_URL . "pages/login/login.php");
    exit;
}