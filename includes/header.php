<?php
// includes/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estudio Jerez | Sistema de Liquidación y Recibos PDF</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-wrapper">
            <a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <i class="fa-solid fa-calculator"></i>
                </div>
                <div>
                    <span>Estudio Jerez</span>
                    <span style="font-size: 0.725rem; display: block; color: var(--accent-gold); font-weight: 500; margin-top: -2px; letter-spacing: 0.02em;">Estudio Contable & Liquidaciones</span>
                </div>
            </a>
            <ul class="nav-links">
                <li>
                    <a href="index.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-chart-pie"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="liquidaciones.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'liquidaciones.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-calculator"></i> Liquidación Mensual
                    </a>
                </li>
                <li>
                    <a href="empleados.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'empleados.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-users"></i> Empleados
                    </a>
                </li>
                <li>
                    <a href="configuracion.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) == 'configuracion.php' ? 'active' : '' ?>">
                        <i class="fa-solid fa-sliders"></i> Básico General
                    </a>
                </li>
            </ul>
        </div>
    </nav>
    <main class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
