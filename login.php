<?php
// login.php - Iniciar Sesión en Estudio Jerez
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Si ya inició sesión, redirigir al Dashboard
if (!empty($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

// Autoinstalación preventiva de tabla de usuarios si no existe aún
try {
    $pdo = Database::getConnection();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `usuarios` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `usuario` varchar(50) NOT NULL UNIQUE,
          `password_hash` varchar(255) NOT NULL,
          `nombre` varchar(100) NOT NULL,
          `creado_en` datetime DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Si la tabla está vacía, insertar el usuario admin por defecto con contraseña lore2828
    $stmtCount = $pdo->query("SELECT COUNT(*) FROM usuarios");
    if ($stmtCount->fetchColumn() == 0) {
        $hashDefault = password_hash('lore2828', PASSWORD_BCRYPT);
        $stmtIns = $pdo->prepare("INSERT INTO usuarios (usuario, password_hash, nombre) VALUES ('admin', ?, 'Contadora')");
        $stmtIns->execute([$hashDefault]);
    }
} catch (Exception $e) {
    // Ignorar si ya existe
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($usuario) && !empty($password)) {
        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE usuario = ?");
            $stmt->execute([$usuario]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $_SESSION['usuario_id'] = $user['id'];
                $_SESSION['usuario_nombre'] = $user['nombre'];
                $_SESSION['usuario_username'] = $user['usuario'];

                header("Location: index.php");
                exit();
            } else {
                $error = "Usuario o contraseña incorrectos.";
            }
        } catch (Exception $e) {
            $error = "Error al intentar iniciar sesión: " . $e->getMessage();
        }
    } else {
        $error = "Por favor ingrese su usuario y contraseña.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión | Estudio Jerez</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background-color: var(--bg-primary);
            background-image: radial-gradient(circle at 50% 0%, #1b365d 0%, #0f2942 35%, #0b1f33 100%);
            padding: 1.5rem;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2.25rem;
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-top: 4px solid var(--accent-gold);
            border-radius: var(--radius-lg);
            box-shadow: 0 20px 40px rgba(11, 31, 51, 0.25);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            width: 58px;
            height: 58px;
            background: var(--accent-navy);
            border: 2px solid var(--accent-gold);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: var(--accent-gold);
            margin-bottom: 0.85rem;
            box-shadow: 0 4px 12px rgba(15, 41, 66, 0.2);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-navy);
            letter-spacing: -0.01em;
        }
        .login-subtitle {
            font-size: 0.825rem;
            color: var(--text-secondary);
            margin-top: 0.25rem;
            font-weight: 500;
        }
        /* Eliminar fondo azul de autocompletado de navegador */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus,
        input:-webkit-autofill:active {
            -webkit-box-shadow: 0 0 0 30px #ffffff inset !important;
            -webkit-text-fill-color: var(--text-primary) !important;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            <i class="fa-solid fa-calculator"></i>
        </div>
        <h1 class="login-title">Estudio Jerez</h1>
        <p class="login-subtitle">Sistema de Liquidación de Sueldos & Recibos PDF</p>
    </div>

    <?php if ($error): ?>
        <div style="padding: 0.85rem; border-radius: var(--radius-sm); background: #fef2f2; border: 1px solid #fecaca; color: var(--accent-rose); font-size: 0.85rem; margin-bottom: 1.5rem; text-align: center; font-weight: 500;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.35rem;"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" autocomplete="off" id="formLogin">
        <!-- Trampas invisibles para capturar y bloquear el gestor de contraseñas de Chrome/Edge -->
        <input type="text" style="display:none!important;" name="prevent_autofill_username" tabindex="-1">
        <input type="password" style="display:none!important;" name="prevent_autofill_password" tabindex="-1">

        <div class="form-group">
            <label class="form-label" for="usuario">Usuario</label>
            <div style="position: relative;">
                <input type="text" 
                       name="usuario" 
                       id="usuario" 
                       value="" 
                       class="form-control" 
                       style="padding-left: 2.5rem;" 
                       required 
                       placeholder="Ingresar usuario" 
                       autocomplete="off" 
                       readonly 
                       onfocus="this.removeAttribute('readonly');">
                <i class="fa-solid fa-user" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.75rem;">
            <label class="form-label" for="password">Contraseña</label>
            <div style="position: relative;">
                <input type="password" 
                       name="password" 
                       id="password" 
                       value="" 
                       class="form-control" 
                       style="padding-left: 2.5rem;" 
                       required 
                       placeholder="Ingresar contraseña" 
                       autocomplete="new-password" 
                       readonly 
                       onfocus="this.removeAttribute('readonly');">
                <i class="fa-solid fa-key" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-size: 0.95rem;">
            <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
        </button>
    </form>

    <div style="margin-top: 1.75rem; text-align: center; font-size: 0.775rem; color: var(--text-muted); border-top: 1px solid var(--border-color-light); padding-top: 1rem;">
        <i class="fa-solid fa-shield-halved" style="color: var(--accent-emerald); margin-right: 0.25rem;"></i> Acceso Protegido &bull; Estudio Jerez
    </div>
</div>

<script>
// Forzar vaciado de cualquier contraseña recordada por el navegador
document.addEventListener("DOMContentLoaded", function() {
    setTimeout(function() {
        var u = document.getElementById('usuario');
        var p = document.getElementById('password');
        if (u) { u.value = ''; }
        if (p) { p.value = ''; }
    }, 50);
});
</script>

</body>
</html>
