<?php
// includes/header.php
require_once __DIR__ . '/../config/auth.php';

$usuarioActual = $_SESSION['usuario_nombre'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
                    <span class="brand-sub">Estudio Contable & Liquidaciones</span>
                </div>
            </a>

            <!-- Botón Hamburguesa para Celulares -->
            <button class="mobile-nav-toggle" id="mobileNavToggle" aria-label="Abrir Menú">
                <i class="fa-solid fa-bars"></i>
            </button>

            <!-- Menú Desplegable Responsivo -->
            <div class="nav-menu" id="navMenu">
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

                <div class="user-menu">
                    <span class="badge badge-purple user-badge">
                        <i class="fa-solid fa-user-circle"></i> <?= htmlspecialchars($usuarioActual) ?>
                    </span>
                    <a href="logout.php" class="btn btn-secondary btn-sm btn-logout" title="Cerrar Sesión">
                        <i class="fa-solid fa-power-off"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var toggleBtn = document.getElementById('mobileNavToggle');
        var navMenu = document.getElementById('navMenu');
        if (toggleBtn && navMenu) {
            toggleBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                navMenu.classList.toggle('active');
                var icon = toggleBtn.querySelector('i');
                if (icon) {
                    if (navMenu.classList.contains('active')) {
                        icon.className = 'fa-solid fa-xmark';
                    } else {
                        icon.className = 'fa-solid fa-bars';
                    }
                }
            });

            // Cerrar menú al hacer clic fuera
            document.addEventListener('click', function(e) {
                if (!navMenu.contains(e.target) && !toggleBtn.contains(e.target)) {
                    navMenu.classList.remove('active');
                    var icon = toggleBtn.querySelector('i');
                    if (icon) icon.className = 'fa-solid fa-bars';
                }
            });
        }
    });
    </script>
    <main class="container" style="padding-top: 1.5rem; padding-bottom: 3rem;">
