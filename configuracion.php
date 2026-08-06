<?php
// configuracion.php
require_once __DIR__ . '/config/db.php';

$pdo = Database::getConnection();
$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
        <h1 class="page-title">Configuración de Escala Salarial</h1>
        <p class="page-subtitle">Parámetros globales de la actividad para el cálculo de sueldos</p>
    </div>
</div>

<?php if ($mensaje): ?>
    <div style="padding: 1rem; border-radius: var(--radius-md); background: rgba(16, 185, 129, 0.15); border: 1px solid var(--accent-emerald); color: var(--accent-emerald); margin-bottom: 1.5rem;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="card" style="max-width: 650px;">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-sliders" style="color: var(--accent-purple);"></i>
            Parámetros del Sueldo Básico de Convenio
        </h2>
    </div>
    
    <form method="POST" action="configuracion.php">
        <div class="form-group">
            <label class="form-label">Sueldo Básico General (Referencia 8 Horas)</label>
            <input type="number" step="0.01" name="basico_8hs" class="form-control" value="<?= $basicoVal ?>" required>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Monto base de convenio para jornada completa de 8 horas diarias.</small>
        </div>

        <div class="form-group">
            <label class="form-label">Asignación No Remunerativa General ($)</label>
            <input type="number" step="0.01" name="no_remunerativo" class="form-control" value="<?= $noRemunVal ?>" required>
            <small style="color: var(--text-muted); font-size: 0.8rem;">Suma no remunerativa fija acordada en la escala.</small>
        </div>

        <div class="form-group">
            <label class="form-label">Adicional / Incremento Gremial Fijo ($)</label>
            <input type="number" step="0.01" name="inc_gremio" class="form-control" value="<?= $incGremioVal ?>">
            <small style="color: var(--text-muted); font-size: 0.8rem;">Incremento adicional por día/gremio si corresponde.</small>
        </div>

        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-primary">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Cambios de Escala
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
