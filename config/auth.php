<?php
// config/auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentScript = basename($_SERVER['PHP_SELF']);

// Si el usuario no ha iniciado sesión y no está en la página de login, redirigir a login.php
if (empty($_SESSION['usuario_id']) && $currentScript !== 'login.php') {
    header("Location: login.php");
    exit();
}
