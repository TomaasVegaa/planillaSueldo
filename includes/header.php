<?php
// includes/header.php
require_once __DIR__ . '/../config/auth.php';

$usuarioActual = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SueldosPro | Sistema de Liquidación y Recibos PDF</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar">
        <div class="container nav-wrapper">
            <a href="index.php" class="brand-logo">
                <div class="logo-icon">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </div>
                <div>
                    <span>SueldosPro</span>
                    <span style="font-size: 0.7rem; display: block; color: var(--accent-blue); font-weight: 400; margin-top: -3px;">Gestión de Liquidaciones</span>
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

            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <span class="badge badge-purple" style="font-size: 0.8rem; padding: 0.4rem 0.75rem;">
                    <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($usuarioActual) ?>
                </span>
                <a href="logout.php" class="btn btn-secondary btn-sm" title="Cerrar Sesión" style="color: var(--accent-rose);">
                    <i class="fa-solid fa-power-off"></i> Salir
                </a>
            </div>
        </div>
    </nav>
    <main class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
