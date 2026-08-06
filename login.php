<?php
// login.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/auth.php';

// Si ya inició sesión, redirigir al Dashboard
if (!empty($_SESSION['usuario_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($usuario) && !empty($password)) {
        $pdo = Database::getConnection();
        
        // Asegurar que exista la tabla usuarios y el usuario admin por defecto si es primera vez
        try {
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
    <title>Iniciar Sesión | SueldosPro</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: radial-gradient(circle at top right, #1e1b4b 0%, #0f172a 60%);
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            padding: 2.5rem 2rem;
            background: rgba(30, 41, 59, 0.85);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: var(--radius-lg);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
        }
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .login-logo {
            width: 56px;
            height: 56px;
            background: var(--gradient-primary);
            border-radius: var(--radius-md);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            color: #ffffff;
            margin-bottom: 1rem;
            box-shadow: 0 8px 20px rgba(99, 102, 241, 0.4);
        }
        .login-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>

<div class="login-card">
    <div class="login-header">
        <div class="login-logo">
            <i class="fa-solid fa-lock"></i>
        </div>
        <h1 class="login-title">SueldosPro</h1>
        <p class="login-subtitle">Ingrese sus credenciales para acceder al sistema</p>
    </div>

    <?php if ($error): ?>
        <div style="padding: 0.85rem; border-radius: var(--radius-md); background: rgba(244, 63, 94, 0.15); border: 1px solid var(--accent-rose); color: var(--accent-rose); font-size: 0.85rem; margin-bottom: 1.5rem; text-align: center;">
            <i class="fa-solid fa-circle-exclamation" style="margin-right: 0.35rem;"></i> <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php">
        <div class="form-group">
            <label class="form-label" for="usuario">Usuario</label>
            <div style="position: relative;">
                <input type="text" name="usuario" id="usuario" class="form-control" style="padding-left: 2.5rem;" required autofocus placeholder="Usuario">
                <i class="fa-solid fa-user" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 1.75rem;">
            <label class="form-label" for="password">Contraseña</label>
            <div style="position: relative;">
                <input type="password" name="password" id="password" class="form-control" style="padding-left: 2.5rem;" required placeholder="Contraseña">
                <i class="fa-solid fa-key" style="position: absolute; left: 0.9rem; top: 50%; transform: translateY(-50%); color: var(--text-muted);"></i>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.8rem; font-size: 1rem;">
            <i class="fa-solid fa-right-to-bracket"></i> Iniciar Sesión
        </button>
    </form>

    <div style="margin-top: 1.75rem; text-align: center; font-size: 0.75rem; color: var(--text-muted); border-top: 1px solid var(--border-color); padding-top: 1rem;">
        <i class="fa-solid fa-shield-halved" style="color: var(--accent-emerald);"></i> Acceso protegido por contraseña
    </div>
</div>

</body>
</html>
