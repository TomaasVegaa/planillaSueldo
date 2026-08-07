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
        <p class="page-subtitle">Período Activo: <strong style="color: var(--accent-navy); font-weight: 700; font-size: 1.1rem;"><?= FechaHelper::formatPeriodo($periodoActivo) ?></strong></p>
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
            <i class="fa-solid fa-calendar-plus" style="color: var(--accent-gold-dark);"></i> + Abrir Nuevo Mes
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
        <div class="stat-value" style="color: var(--accent-navy);">$<?= number_format($totalDevengado, 2, ',', '.') ?></div>
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
    <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
        <div>
            <h2 class="card-title">
                <i class="fa-solid fa-list-check" style="color: var(--accent-navy);"></i> 
                Detalle de Sueldos - <?= FechaHelper::formatPeriodo($periodoActivo) ?>
            </h2>
            <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.25rem;" id="searchCount">
                Mostrando <?= count($liquidaciones) ?> de <?= count($liquidaciones) ?> empleados
            </div>
        </div>
        
        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <!-- Buscador en Tiempo Real -->
            <div style="position: relative; width: 250px; max-width: 100%;">
                <input type="text" 
                       id="searchEmpleado" 
                       class="form-control" 
                       placeholder="Buscar por nombre..." 
                       style="padding-left: 2.3rem; padding-right: 2rem; font-size: 0.875rem;"
                       onkeyup="filtrarEmpleadosDashboard()">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.8rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                <button type="button" 
                        id="btnClearSearch" 
                        onclick="limpiarBuscadorDashboard()" 
                        style="display: none; position: absolute; right: 0.6rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); cursor: pointer; font-size: 0.85rem;">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            
            <span class="badge badge-purple">Sueldo Básico General: $<?= number_format($basicoGeneral, 2, ',', '.') ?></span>
        </div>
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
                
                <tr id="noResultsRow" style="display: none;">
                    <td colspan="9" style="text-align: center; padding: 2.5rem 1rem; color: var(--text-muted);">
                        <i class="fa-solid fa-magnifying-glass" style="font-size: 1.75rem; margin-bottom: 0.5rem; display: block; color: var(--accent-navy);"></i>
                        No se encontraron empleados que coincidan con la búsqueda.
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<script>
function filtrarEmpleadosDashboard() {
    var input = document.getElementById('searchEmpleado');
    var filter = input.value.toLowerCase().trim();
    var table = document.querySelector('.table tbody');
    var rows = table.getElementsByTagName('tr');
    var countVisible = 0;
    var totalRows = 0;
    var btnClear = document.getElementById('btnClearSearch');

    if (filter.length > 0) {
        if (btnClear) btnClear.style.display = 'block';
    } else {
        if (btnClear) btnClear.style.display = 'none';
    }

    for (var i = 0; i < rows.length; i++) {
        if (rows[i].id === 'noResultsRow') continue;
        totalRows++;
        
        var tdNombre = rows[i].getElementsByTagName('td')[0];
        if (tdNombre) {
            var textValue = tdNombre.textContent || tdNombre.innerText;
            if (textValue.toLowerCase().indexOf(filter) > -1) {
                rows[i].style.display = '';
                countVisible++;
            } else {
                rows[i].style.display = 'none';
            }
        }
    }

    var noResultsRow = document.getElementById('noResultsRow');
    if (noResultsRow) {
        if (countVisible === 0) {
            noResultsRow.style.display = '';
        } else {
            noResultsRow.style.display = 'none';
        }
    }

    var searchCount = document.getElementById('searchCount');
    if (searchCount) {
        if (filter.length > 0) {
            searchCount.innerHTML = 'Mostrando <strong>' + countVisible + '</strong> de ' + totalRows + ' empleados';
        } else {
            searchCount.innerHTML = 'Mostrando ' + totalRows + ' de ' + totalRows + ' empleados';
        }
    }
}

function limpiarBuscadorDashboard() {
    var input = document.getElementById('searchEmpleado');
    if (input) {
        input.value = '';
        filtrarEmpleadosDashboard();
        input.focus();
    }
}
</script>

<!-- Modal para Abrir un Nuevo Mes Futuro -->
<div id="modalNuevoMes" class="modal-overlay" style="display: none;">
    <div class="modal-content card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-calendar-plus" style="color: var(--accent-gold-dark);"></i> Abrir Nuevo Mes de Liquidación</h3>
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
