<?php
// configuracion.php
require_once __DIR__ . '/config/db.php';

$pdo = Database::getConnection();
$mensaje = '';
$tipoMensaje = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'escala';

    if ($action === 'escala') {
        $basico8hs = floatval($_POST['basico_8hs']);
        $noRemunerativo = floatval($_POST['no_remunerativo']);
        $incGremio = floatval($_POST['inc_gremio']);

        $stmt = $pdo->prepare("REPLACE INTO configuracion (clave, valor) VALUES ('basico_8hs', ?)");
        $stmt->execute([$basico8hs]);

        $stmt = $pdo->prepare("REPLACE INTO configuracion (clave, valor) VALUES ('no_remunerativo', ?)");
        $stmt->execute([$noRemunerativo]);

        $stmt = $pdo->prepare("REPLACE INTO configuracion (clave, valor) VALUES ('inc_gremio', ?)");
        $stmt->execute([$incGremio]);

        $mensaje = "Valores de escala general actualizados correctamente.";
        $tipoMensaje = "success";
    } elseif ($action === 'password') {
        $usuarioId = $_SESSION['usuario_id'] ?? 1;
        $passActual = $_POST['pass_actual'] ?? '';
        $passNueva = $_POST['pass_nueva'] ?? '';
        $passConfirm = $_POST['pass_confirm'] ?? '';

        if (!empty($passNueva) && $passNueva === $passConfirm) {
            $stmtUser = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
            $stmtUser->execute([$usuarioId]);
            $user = $stmtUser->fetch();

            if ($user && password_verify($passActual, $user['password_hash'])) {
                $newHash = password_hash($passNueva, PASSWORD_DEFAULT);
                $stmtUpd = $pdo->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?");
                $stmtUpd->execute([$newHash, $usuarioId]);
                $mensaje = "Contraseña cambiada con éxito.";
                $tipoMensaje = "success";
            } else {
                $mensaje = "La contraseña actual es incorrecta.";
                $tipoMensaje = "error";
            }
        } else {
            $mensaje = "La nueva contraseña y su confirmación no coinciden.";
            $tipoMensaje = "error";
        }
    }
}

$stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
$config = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);

$basicoVal = floatval($config['basico_8hs'] ?? 889390);
$noRemunVal = floatval($config['no_remunerativo'] ?? 97797.89);
$incGremioVal = floatval($config['inc_gremio'] ?? 0);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Configuración del Sistema</h1>
        <p class="page-subtitle">Parámetros globales de la escala salarial y seguridad de acceso</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div style="padding: 1rem; border-radius: var(--radius-md); background: <?= $tipoMensaje == 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(244, 63, 94, 0.15)' ?>; border: 1px solid <?= $tipoMensaje == 'success' ? 'var(--accent-emerald)' : 'var(--accent-rose)' ?>; color: <?= $tipoMensaje == 'success' ? 'var(--accent-emerald)' : 'var(--accent-rose)' ?>; margin-bottom: 1.5rem;">
        <i class="fa-solid <?= $tipoMensaje == 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
    <!-- Formulario 1: Escala General -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-sliders" style="color: var(--accent-purple);"></i>
                Parámetros del Sueldo Básico
            </h2>
        </div>
        
        <form method="POST" action="configuracion.php">
            <input type="hidden" name="action" value="escala">
            <div class="form-group">
                <label class="form-label">Sueldo Básico General (Referencia 8 Horas)</label>
                <input type="number" step="0.01" name="basico_8hs" class="form-control" value="<?= $basicoVal ?>" required>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Monto base de convenio para jornada completa.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Asignación No Remunerativa General ($)</label>
                <input type="number" step="0.01" name="no_remunerativo" class="form-control" value="<?= $noRemunVal ?>" required>
                <small style="color: var(--text-muted); font-size: 0.8rem;">Suma no remunerativa fija acordada.</small>
            </div>

            <div class="form-group">
                <label class="form-label">Adicional / Incremento Gremial Fijo ($)</label>
                <input type="number" step="0.01" name="inc_gremio" class="form-control" value="<?= $incGremioVal ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Incremento adicional si corresponde.</small>
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Guardar Escala
                </button>
            </div>
        </form>
    </div>

    <!-- Formulario 2: Cambiar Contraseña -->
    <div class="card" style="margin-bottom: 0;">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-key" style="color: var(--accent-blue);"></i>
                Seguridad y Contraseña de Acceso
            </h2>
        </div>
        
        <form method="POST" action="configuracion.php">
            <input type="hidden" name="action" value="password">
            <div class="form-group">
                <label class="form-label">Contraseña Actual</label>
                <input type="password" name="pass_actual" class="form-control" required placeholder="Ingrese contraseña actual">
            </div>

            <div class="form-group">
                <label class="form-label">Nueva Contraseña</label>
                <input type="password" name="pass_nueva" class="form-control" required placeholder="Mínimo 6 caracteres">
            </div>

            <div class="form-group">
                <label class="form-label">Confirmar Nueva Contraseña</label>
                <input type="password" name="pass_confirm" class="form-control" required placeholder="Repita la nueva contraseña">
            </div>

            <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-secondary">
                    <i class="fa-solid fa-shield-halved"></i> Cambiar Contraseña
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
