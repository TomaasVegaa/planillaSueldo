<?php
// index.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/CalculationEngine.php';
require_once __DIR__ . '/classes/FechaHelper.php';

$pdo = Database::getConnection();

// Período activo por defecto: Julio 2026 ('2026-07')
$periodoActivo = $_GET['periodo'] ?? '2026-07';

// Garantizar que la liquidación para este período exista en la DB y esté sincronizada con los activos
CalculationEngine::asegurarLiquidacionesPeriodo($pdo, $periodoActivo);

// Obtener datos del básico general
$stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
$config = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
$basicoGeneral = floatval($config['basico_8hs'] ?? 889390);

// Obtener liquidaciones de empleados ACTIVOS únicamente para el período seleccionado
$stmtLiq = $pdo->prepare("
    SELECT l.*, e.nombre, e.fecha_ingreso, e.horas_diarias, e.adicional_titulo AS emp_titulo 
    FROM liquidaciones l 
    JOIN empleados e ON l.empleado_id = e.id 
    WHERE l.periodo = ? AND e.activo = 1
    ORDER BY e.nombre ASC
");
$stmtLiq->execute([$periodoActivo]);
$liquidaciones = $stmtLiq->fetchAll();

// Totales del período
$totalDevengado = 0;
$totalAdelantos = 0;
$totalSaldoCobrar = 0;
$totalEmpleados = count($liquidaciones);

foreach ($liquidaciones as $l) {
    $totalDevengado += $l['neto_devengado'];
    $totalAdelantos += $l['total_adelantos'];
    $totalSaldoCobrar += $l['saldo_a_cobrar'];
}

$periodosDisponibles = FechaHelper::getPeriodosDisponibles($pdo);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Dashboard de Liquidación</h1>
        <p class="page-subtitle">Período Activo: <strong style="color: var(--accent-blue); font-size: 1.1rem;"><?= FechaHelper::formatPeriodo($periodoActivo) ?></strong></p>
    </div>
    
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <form method="GET" action="index.php" style="display: flex; gap: 0.5rem; align-items: center;">
            <label for="periodo" style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem;">Mes:</label>
            <select name="periodo" id="periodo" class="form-control" style="width: auto; padding: 0.45rem 0.85rem; font-weight: 600;" onchange="this.form.submit()">
                <?php foreach ($periodosDisponibles as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $periodoActivo == $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modalNuevoMes').style.display='flex'">
            <i class="fa-solid fa-calendar-plus" style="color: var(--accent-emerald);"></i> + Abrir Nuevo Mes
        </button>

        <a href="liquidaciones.php?periodo=<?= $periodoActivo ?>" class="btn btn-primary btn-sm">
            <i class="fa-solid fa-pen-to-square"></i> Modificar Novedades
        </a>
    </div>
</div>

<!-- Grid de Métricas Principales -->
<div class="stat-grid">
    <div class="stat-card blue">
        <div class="stat-label">Empleados en Nómina Activa</div>
        <div class="stat-value"><?= $totalEmpleados ?></div>
        <div class="stat-desc">Legajos activos en <?= FechaHelper::formatPeriodo($periodoActivo) ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label">Total Devengado Mensual</div>
        <div class="stat-value" style="color: var(--accent-blue);">$<?= number_format($totalDevengado, 2, ',', '.') ?></div>
        <div class="stat-desc">Sueldos base + Antigüedad + Presentismo + Título + SAC</div>
    </div>
    <div class="stat-card amber">
        <div class="stat-label">Total Adelantos Entregados</div>
        <div class="stat-value" style="color: var(--accent-amber);">$<?= number_format($totalAdelantos, 2, ',', '.') ?></div>
        <div class="stat-desc">Anticipos totales del mes</div>
    </div>
    <div class="stat-card emerald">
        <div class="stat-label">Saldo Líquido a Pagar a Fin de Mes</div>
        <div class="stat-value" style="color: var(--accent-emerald);">$<?= number_format($totalSaldoCobrar, 2, ',', '.') ?></div>
        <div class="stat-desc">Monto final abonado a los empleados</div>
    </div>
</div>

<!-- Tabla Principal de Liquidaciones -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-list-check" style="color: var(--accent-indigo);"></i> 
            Detalle de Sueldos - <?= FechaHelper::formatPeriodo($periodoActivo) ?>
        </h2>
        <span class="badge badge-purple">Sueldo Básico General: $<?= number_format($basicoGeneral, 2, ',', '.') ?></span>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Empleado</th>
                    <th>Jornada</th>
                    <th>Antigüedad</th>
                    <th>Adicionales</th>
                    <th>Faltas</th>
                    <th>Devengado (+SAC)</th>
                    <th>Total Adelantos</th>
                    <th>Saldo a Cobrar</th>
                    <th style="text-align: center;">Recibo PDF</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($liquidaciones as $liq): 
                    $adicionalTitulo = floatval($liq['adicional_titulo'] ?? 0);
                ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($liq['nombre']) ?></strong>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">
                                Ingreso: <?= date('d/m/Y', strtotime($liq['fecha_ingreso'])) ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge <?= $liq['horas_diarias'] == 8 ? 'badge-blue' : 'badge-purple' ?>">
                                <?= $liq['horas_diarias'] ?> hs/día
                            </span>
                        </td>
                        <td>
                            <?= $liq['antiguedad_anios'] ?> años 
                            <div style="font-size: 0.75rem; color: var(--text-muted);">$<?= number_format($liq['antiguedad_monto'], 2, ',', '.') ?></div>
                        </td>
                        <td>
                            <?php if ($liq['presentismo_monto'] > 0): ?>
                                <span class="badge badge-emerald"><i class="fa-solid fa-check"></i> Presentismo: $<?= number_format($liq['presentismo_monto'], 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge badge-rose"><i class="fa-solid fa-xmark"></i> Sin Presentismo</span>
                            <?php endif; ?>
                            
                            <?php if ($adicionalTitulo > 0): ?>
                                <div style="margin-top: 0.25rem;">
                                    <span class="badge badge-purple" title="Adicional por Título Contador / Profesional">
                                        <i class="fa-solid fa-graduation-cap"></i> Título: $<?= number_format($adicionalTitulo, 0, ',', '.') ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($liq['faltas_dias'] > 0): ?>
                                <span class="badge badge-amber"><?= $liq['faltas_dias'] ?> día(s)</span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">0</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-weight: 600;">
                            $<?= number_format($liq['neto_devengado'], 2, ',', '.') ?>
                        </td>
                        <td style="color: var(--accent-amber); font-weight: 600;">
                            $<?= number_format($liq['total_adelantos'], 2, ',', '.') ?>
                        </td>
                        <td style="font-weight: 700; font-size: 1rem; color: var(--accent-emerald);">
                            $<?= number_format($liq['saldo_a_cobrar'], 2, ',', '.') ?>
                        </td>
                        <td style="text-align: center;">
                            <a href="generar_pdf.php?empleado_id=<?= $liq['empleado_id'] ?>&periodo=<?= $periodoActivo ?>" 
                               target="_blank" 
                               class="btn btn-secondary btn-sm" 
                               title="Generar Recibo PDF Individual">
                                <i class="fa-solid fa-file-pdf" style="color: var(--accent-rose);"></i> Recibo PDF
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal para Abrir un Nuevo Mes Futuro -->
<div id="modalNuevoMes" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(5px); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
    <div class="card" style="width: 100%; max-width: 450px; margin: 0; background: var(--bg-surface); border: 1px solid var(--border-color);">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-calendar-plus" style="color: var(--accent-emerald);"></i> Abrir Nuevo Mes de Liquidación</h3>
            <button onclick="document.getElementById('modalNuevoMes').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;">&times;</button>
        </div>
        <form method="GET" action="index.php">
            <div class="form-group">
                <label class="form-label">Seleccionar Año y Mes Futuro</label>
                <input type="month" name="periodo" class="form-control" required value="<?= date('Y-m') ?>">
                <small style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.4rem; display: block;">
                    Al seleccionar un nuevo mes, el sistema auto-generará la plantilla con todos los empleados activos listos para liquidar.
                </small>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalNuevoMes').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-success">Abrir Período</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
