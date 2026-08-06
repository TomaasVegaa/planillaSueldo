<?php
// liquidaciones.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/CalculationEngine.php';
require_once __DIR__ . '/classes/FechaHelper.php';

$pdo = Database::getConnection();

$periodo = $_GET['periodo'] ?? '2026-07';

// Asegurar que exista el período en la base de datos y solo con empleados activos
CalculationEngine::asegurarLiquidacionesPeriodo($pdo, $periodo);

$mensaje = '';
$tipoMensaje = '';

// Obtener básico de referencia general
$stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
$config = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
$basico8hs = floatval($config['basico_8hs'] ?? 889390);
$noRemunerativo = floatval($config['no_remunerativo'] ?? 97797.89);
$incGremio = floatval($config['inc_gremio'] ?? 0);

// Guardar liquidaciones
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $periodoPost = $_POST['periodo'];
    $liquidacionesPost = $_POST['liq'] ?? [];
    
    $pdo->beginTransaction();
    try {
        foreach ($liquidacionesPost as $empId => $data) {
            $faltas = intval($data['faltas_dias'] ?? 0);
            $faltaInjustificada = !empty($data['falta_injustificada']);
            $adelantoQ1 = floatval($data['adelanto_q1'] ?? 0);
            $adelantoQ2 = floatval($data['adelanto_q2'] ?? 0);
            $adelantoQ3 = floatval($data['adelanto_q3'] ?? 0);
            $fechaIngreso = $data['fecha_ingreso'];
            $horasDiarias = intval($data['horas_diarias']);
            $adicionalTitulo = floatval($data['adicional_titulo'] ?? 0);
            
            // Ejecutar motor de cálculo
            $res = CalculationEngine::calcularLiquidacion([
                'basico_referencia'  => $basico8hs,
                'no_remunerativo'    => $noRemunerativo,
                'inc_gremio'         => $incGremio,
                'adicional_titulo'   => $adicionalTitulo,
                'fecha_ingreso'      => $fechaIngreso,
                'periodo'            => $periodoPost,
                'horas_diarias'      => $horasDiarias,
                'faltas_dias'        => $faltas,
                'falta_justificada'  => !$faltaInjustificada,
                'pierde_presentismo' => $faltaInjustificada,
                'adelanto_q1'        => $adelantoQ1,
                'adelanto_q2'        => $adelantoQ2,
                'adelanto_q3'        => $adelantoQ3
            ]);
            
            // Guardar o actualizar registro en liquidaciones
            $stmtCheck = $pdo->prepare("SELECT id FROM liquidaciones WHERE empleado_id = ? AND periodo = ?");
            $stmtCheck->execute([$empId, $periodoPost]);
            $existingId = $stmtCheck->fetchColumn();
            
            if ($existingId) {
                $stmtUpd = $pdo->prepare("
                    UPDATE liquidaciones SET
                        basico_referencia = ?, horas_diarias = ?, antiguedad_anios = ?,
                        antiguedad_monto = ?, presentismo_monto = ?, no_remunerativo = ?,
                        retenciones_teoricas = ?, neto_convenio_8hs = ?, neto_real_horas = ?,
                        sac_prorrateado = ?, faltas_dias = ?, neto_devengado = ?,
                        adelanto_q1 = ?, adelanto_q2 = ?, adelanto_q3 = ?, total_adelantos = ?, saldo_a_cobrar = ?,
                        adicional_titulo = ?
                    WHERE id = ?
                ");
                $stmtUpd->execute([
                    $res['basico_referencia'], $res['horas_diarias'], $res['antiguedad_anios'],
                    $res['antiguedad_monto'], $res['presentismo_monto'], $res['no_remunerativo'],
                    $res['retenciones_teoricas'], $res['neto_convenio_8hs'], $res['neto_real_horas'],
                    $res['sac_prorrateado'], $res['faltas_dias'], $res['neto_devengado'],
                    $res['adelanto_q1'], $res['adelanto_q2'], $res['adelanto_q3'], $res['total_adelantos'], $res['saldo_a_cobrar'],
                    $res['adicional_titulo'], $existingId
                ]);
            } else {
                $stmtIns = $pdo->prepare("
                    INSERT INTO liquidaciones (
                        empleado_id, periodo, basico_referencia, horas_diarias, antiguedad_anios,
                        antiguedad_monto, presentismo_monto, no_remunerativo, retenciones_teoricas,
                        neto_convenio_8hs, neto_real_horas, sac_prorrateado, faltas_dias, neto_devengado,
                        adelanto_q1, adelanto_q2, adelanto_q3, total_adelantos, saldo_a_cobrar, adicional_titulo
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtIns->execute([
                    $empId, $periodoPost, $res['basico_referencia'], $res['horas_diarias'], $res['antiguedad_anios'],
                    $res['antiguedad_monto'], $res['presentismo_monto'], $res['no_remunerativo'],
                    $res['retenciones_teoricas'], $res['neto_convenio_8hs'], $res['neto_real_horas'],
                    $res['sac_prorrateado'], $res['faltas_dias'], $res['neto_devengado'],
                    $res['adelanto_q1'], $res['adelanto_q2'], $res['adelanto_q3'], $res['total_adelantos'], $res['saldo_a_cobrar'],
                    $res['adicional_titulo']
                ]);
            }
        }
        $pdo->commit();
        $nombreMesEsp = FechaHelper::formatPeriodo($periodoPost);
        $mensaje = "Liquidación correspondiente a $nombreMesEsp guardada y calculada exitosamente.";
        $tipoMensaje = "success";
        $periodo = $periodoPost;
    } catch (Exception $e) {
        $pdo->rollBack();
        $mensaje = "Error al guardar la liquidación: " . $e->getMessage();
        $tipoMensaje = "error";
    }
}

// Obtener empleados ACTIVOS exclusivamente y sus liquidaciones del período
$stmtEmp = $pdo->prepare("
    SELECT e.*, 
           l.faltas_dias, l.presentismo_monto, l.adelanto_q1, l.adelanto_q2, l.adelanto_q3,
           l.neto_devengado, l.saldo_a_cobrar
    FROM empleados e
    LEFT JOIN liquidaciones l ON e.id = l.empleado_id AND l.periodo = ?
    WHERE e.activo = 1
    ORDER BY e.nombre ASC
");
$stmtEmp->execute([$periodo]);
$empleadosList = $stmtEmp->fetchAll();

$periodosDisponibles = FechaHelper::getPeriodosDisponibles($pdo);

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Carga de Liquidación Mensual</h1>
        <p class="page-subtitle">Ingreso de faltas, inasistencias y entregas a cuenta de adelantos</p>
    </div>
    
    <div style="display: flex; gap: 0.75rem; align-items: center; flex-wrap: wrap;">
        <form method="GET" action="liquidaciones.php" style="display: flex; gap: 0.5rem; align-items: center;">
            <label style="font-weight: 600; color: var(--text-secondary); font-size: 0.9rem;">Mes a Liquidar:</label>
            <select name="periodo" class="form-control" style="width: auto; font-weight: 600;" onchange="this.form.submit()">
                <?php foreach ($periodosDisponibles as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $periodo == $key ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>

        <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('modalNuevoMes').style.display='flex'">
            <i class="fa-solid fa-calendar-plus" style="color: var(--accent-gold-dark);"></i> + Abrir Nuevo Mes
        </button>
    </div>
</div>

<?php if ($mensaje): ?>
    <div style="padding: 1rem; border-radius: var(--radius-md); background: <?= $tipoMensaje == 'success' ? '#f0fdf4' : '#fef2f2' ?>; border: 1px solid <?= $tipoMensaje == 'success' ? '#bbf7d0' : '#fecaca' ?>; color: <?= $tipoMensaje == 'success' ? 'var(--accent-emerald)' : 'var(--accent-rose)' ?>; margin-bottom: 1.5rem;">
        <i class="fa-solid <?= $tipoMensaje == 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<form method="POST" action="liquidaciones.php?periodo=<?= $periodo ?>">
    <input type="hidden" name="periodo" value="<?= $periodo ?>">
    
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fa-solid fa-file-pen" style="color: var(--accent-navy);"></i> 
                Planilla de Novedades - <?= FechaHelper::formatPeriodo($periodo) ?>
            </h2>
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Toda la Liquidación
            </button>
        </div>
        
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Empleado</th>
                        <th>Jornada</th>
                        <th style="width: 100px;">Faltas (Días)</th>
                        <th>¿Falta Injustificada?</th>
                        <th style="width: 130px;">ADELANTO ($)</th>
                        <th style="width: 130px;">ADELANTO ($)</th>
                        <th style="width: 130px;">ADELANTO ($)</th>
                        <th>Devengado Aprox</th>
                        <th>Saldo Aprox</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($empleadosList as $emp): 
                        $empId = $emp['id'];
                        $faltasVal = $emp['faltas_dias'] ?? 0;
                        $q1Val = $emp['adelanto_q1'] ?? 0;
                        $q2Val = $emp['adelanto_q2'] ?? 0;
                        $q3Val = $emp['adelanto_q3'] ?? 0;
                        $pierdePres = ($emp['presentismo_monto'] === 0.0 || $emp['presentismo_monto'] === '0.00');
                    ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($emp['nombre']) ?></strong>
                                <?php if (!empty($emp['adicional_titulo']) && $emp['adicional_titulo'] > 0): ?>
                                    <span class="badge badge-purple" style="font-size: 0.65rem; margin-left: 0.25rem;">
                                        <i class="fa-solid fa-graduation-cap"></i> Extra Título
                                    </span>
                                <?php endif; ?>
                                <div style="font-size: 0.75rem; color: var(--text-muted);">
                                    Ingreso: <?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?>
                                </div>
                                <input type="hidden" name="liq[<?= $empId ?>][fecha_ingreso]" value="<?= $emp['fecha_ingreso'] ?>">
                                <input type="hidden" name="liq[<?= $empId ?>][horas_diarias]" value="<?= $emp['horas_diarias'] ?>">
                                <input type="hidden" name="liq[<?= $empId ?>][adicional_titulo]" value="<?= $emp['adicional_titulo'] ?? 0 ?>">
                            </td>
                            <td>
                                <span class="badge badge-blue"><?= $emp['horas_diarias'] ?> hs/día</span>
                            </td>
                            <td>
                                <input type="number" 
                                       name="liq[<?= $empId ?>][faltas_dias]" 
                                       class="form-control" 
                                       value="<?= $faltasVal ?>" 
                                       min="0" max="30" 
                                       style="padding: 0.35rem 0.5rem; text-align: center;">
                            </td>
                            <td>
                                <label class="checkbox-label" style="font-size: 0.8rem;">
                                    <input type="checkbox" 
                                           name="liq[<?= $empId ?>][falta_injustificada]" 
                                           value="1" 
                                           <?= $pierdePres ? 'checked' : '' ?>>
                                    Quitar Presentismo
                                </label>
                            </td>
                            <td>
                                <input type="number" 
                                       step="1000" 
                                       name="liq[<?= $empId ?>][adelanto_q1]" 
                                       class="form-control" 
                                       value="<?= $q1Val ?>" 
                                       placeholder="0"
                                       style="padding: 0.35rem 0.5rem;">
                            </td>
                            <td>
                                <input type="number" 
                                       step="1000" 
                                       name="liq[<?= $empId ?>][adelanto_q2]" 
                                       class="form-control" 
                                       value="<?= $q2Val ?>" 
                                       placeholder="0"
                                       style="padding: 0.35rem 0.5rem;">
                            </td>
                            <td>
                                <input type="number" 
                                       step="1000" 
                                       name="liq[<?= $empId ?>][adelanto_q3]" 
                                       class="form-control" 
                                       value="<?= $q3Val ?>" 
                                       placeholder="0"
                                       style="padding: 0.35rem 0.5rem;">
                            </td>
                            <td>
                                <?php if (isset($emp['neto_devengado'])): ?>
                                    $<?= number_format($emp['neto_devengado'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-weight: 700; color: var(--accent-emerald);">
                                <?php if (isset($emp['saldo_a_cobrar'])): ?>
                                    $<?= number_format($emp['saldo_a_cobrar'], 2, ',', '.') ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="display: flex; justify-content: flex-end; margin-top: 1.5rem;">
            <button type="submit" class="btn btn-success">
                <i class="fa-solid fa-floppy-disk"></i> Guardar Toda la Liquidación
            </button>
        </div>
    </div>
</form>

<!-- Modal para Abrir un Nuevo Mes Futuro -->
<div id="modalNuevoMes" class="modal-overlay" style="display: none;">
    <div class="modal-content card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-calendar-plus" style="color: var(--accent-gold-dark);"></i> Abrir Nuevo Mes de Liquidación</h3>
            <button onclick="document.getElementById('modalNuevoMes').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;">&times;</button>
        </div>
        <form method="GET" action="liquidaciones.php">
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
