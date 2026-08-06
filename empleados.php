<?php
// empleados.php
require_once __DIR__ . '/config/db.php';

$pdo = Database::getConnection();

$mensaje = '';
$tipoMensaje = '';

// Procesar formulario de alta / edición
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'crear') {
        $nombre = trim($_POST['nombre']);
        $fechaIngreso = $_POST['fecha_ingreso'];
        $horasDiarias = intval($_POST['horas_diarias']);
        $adicionalTitulo = floatval($_POST['adicional_titulo'] ?? 0);
        
        if (!empty($nombre) && !empty($fechaIngreso)) {
            $stmt = $pdo->prepare("INSERT INTO empleados (nombre, fecha_ingreso, horas_diarias, adicional_titulo, activo) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$nombre, $fechaIngreso, $horasDiarias, $adicionalTitulo]);
            $mensaje = "Empleado '$nombre' agregado exitosamente.";
            $tipoMensaje = "success";
        }
    } elseif ($action === 'editar') {
        $id = intval($_POST['id']);
        $nombre = trim($_POST['nombre']);
        $fechaIngreso = $_POST['fecha_ingreso'];
        $horasDiarias = intval($_POST['horas_diarias']);
        $adicionalTitulo = floatval($_POST['adicional_titulo'] ?? 0);
        $activo = isset($_POST['activo']) ? 1 : 0;
        
        if ($id > 0 && !empty($nombre)) {
            $stmt = $pdo->prepare("UPDATE empleados SET nombre = ?, fecha_ingreso = ?, horas_diarias = ?, adicional_titulo = ?, activo = ? WHERE id = ?");
            $stmt->execute([$nombre, $fechaIngreso, $horasDiarias, $adicionalTitulo, $activo, $id]);
            
            // Si el empleado fue desactivado, eliminar de liquidaciones de períodos actuales/futuros
            if ($activo == 0) {
                $stmtDel = $pdo->prepare("DELETE FROM liquidaciones WHERE empleado_id = ? AND periodo >= '2026-07'");
                $stmtDel->execute([$id]);
            }
            
            $mensaje = "Empleado '$nombre' actualizado correctamente.";
            $tipoMensaje = "success";
        }
    }
}

// Listar empleados
$stmt = $pdo->query("SELECT * FROM empleados ORDER BY activo DESC, nombre ASC");
$empleados = $stmt->fetchAll();

include __DIR__ . '/includes/header.php';
?>

<div class="page-header">
    <div>
        <h1 class="page-title">Gestión de Empleados</h1>
        <p class="page-subtitle">Administración de la nómina de personal y adicionales de legajo</p>
    </div>
    
    <button class="btn btn-primary" onclick="document.getElementById('modalCrear').style.display='flex'">
        <i class="fa-solid fa-user-plus"></i> Nuevo Empleado
    </button>
</div>

<?php if ($mensaje): ?>
    <div style="padding: 1rem; border-radius: var(--radius-md); background: #f0fdf4; border: 1px solid #bbf7d0; color: var(--accent-emerald); margin-bottom: 1.5rem;">
        <i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fa-solid fa-users-gear" style="color: var(--accent-navy);"></i> 
            Nómina de Empleados
        </h2>
        <span class="badge badge-blue">Total: <?= count($empleados) ?> Empleados</span>
    </div>
    
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th># ID</th>
                    <th>Nombre y Apellido</th>
                    <th>Fecha de Ingreso</th>
                    <th>Jornada Horaria</th>
                    <th>Adicional Título ($)</th>
                    <th>Estado</th>
                    <th style="text-align: right;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($empleados as $emp): ?>
                    <tr>
                        <td><strong>#<?= $emp['id'] ?></strong></td>
                        <td>
                            <strong style="font-size: 1rem;"><?= htmlspecialchars($emp['nombre']) ?></strong>
                        </td>
                        <td>
                            <i class="fa-regular fa-calendar" style="color: var(--text-muted); margin-right: 0.25rem;"></i>
                            <?= date('d/m/Y', strtotime($emp['fecha_ingreso'])) ?>
                        </td>
                        <td>
                            <span class="badge <?= $emp['horas_diarias'] == 8 ? 'badge-blue' : 'badge-purple' ?>">
                                <?= $emp['horas_diarias'] ?> Horas / día
                            </span>
                        </td>
                        <td>
                            <?php if (!empty($emp['adicional_titulo']) && $emp['adicional_titulo'] > 0): ?>
                                <span class="badge badge-purple">
                                    <i class="fa-solid fa-graduation-cap"></i> $<?= number_format($emp['adicional_titulo'], 2, ',', '.') ?>
                                </span>
                            <?php else: ?>
                                <span style="color: var(--text-muted);">$0,00</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($emp['activo']): ?>
                                <span class="badge badge-emerald"><i class="fa-solid fa-circle" style="font-size: 0.5rem; margin-right: 0.25rem;"></i> Activo</span>
                            <?php else: ?>
                                <span class="badge badge-rose"><i class="fa-solid fa-user-slash" style="font-size: 0.75rem; margin-right: 0.25rem;"></i> Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align: right;">
                            <button class="btn btn-secondary btn-sm" onclick='editarEmpleado(<?= json_encode($emp) ?>)'>
                                <i class="fa-solid fa-user-pen"></i> Editar
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Crear Empleado -->
<div id="modalCrear" class="modal-overlay" style="display: none;">
    <div class="modal-content card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-user-plus" style="color: var(--accent-navy);"></i> Nuevo Empleado</h3>
            <button onclick="document.getElementById('modalCrear').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="empleados.php">
            <input type="hidden" name="action" value="crear">
            <div class="form-group">
                <label class="form-label">Nombre del Empleado</label>
                <input type="text" name="nombre" class="form-control" required placeholder="Ej: CARLOS GOMEZ">
            </div>
            <div class="form-group">
                <label class="form-label">Fecha de Ingreso</label>
                <input type="date" name="fecha_ingreso" class="form-control" required value="<?= date('Y-m-d') ?>">
            </div>
            <div class="form-group">
                <label class="form-label">Horas Diarias de Trabajo</label>
                <select name="horas_diarias" class="form-control" required>
                    <option value="8">8 Horas (Jornada Completa)</option>
                    <option value="6">6 Horas</option>
                    <option value="4">4 Horas (Media Jornada)</option>
                    <option value="3">3 Horas</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Adicional por Título Contador / Profesional ($)</label>
                <input type="number" step="1000" name="adicional_titulo" class="form-control" placeholder="0" value="0">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Monto extra mensual si posee título profesional.</small>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalCrear').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Empleado</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Editar Empleado -->
<div id="modalEditar" class="modal-overlay" style="display: none;">
    <div class="modal-content card" style="margin: 0;">
        <div class="card-header">
            <h3 class="card-title"><i class="fa-solid fa-user-pen" style="color: var(--accent-navy);"></i> Editar Empleado</h3>
            <button onclick="document.getElementById('modalEditar').style.display='none'" style="background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST" action="empleados.php">
            <input type="hidden" name="action" value="editar">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-group">
                <label class="form-label">Nombre del Empleado</label>
                <input type="text" name="nombre" id="edit_nombre" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha de Ingreso</label>
                <input type="date" name="fecha_ingreso" id="edit_fecha_ingreso" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label">Horas Diarias de Trabajo</label>
                <select name="horas_diarias" id="edit_horas_diarias" class="form-control" required>
                    <option value="8">8 Horas (Jornada Completa)</option>
                    <option value="6">6 Horas</option>
                    <option value="4">4 Horas (Media Jornada)</option>
                    <option value="3">3 Horas</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Adicional por Título Contador / Profesional ($)</label>
                <input type="number" step="1000" name="adicional_titulo" id="edit_adicional_titulo" class="form-control" placeholder="0">
                <small style="color: var(--text-muted); font-size: 0.8rem;">Monto extra mensual si posee título profesional.</small>
            </div>
            <div class="form-group">
                <label class="checkbox-label">
                    <input type="checkbox" name="activo" id="edit_activo" value="1">
                    Empleado Activo en Nómina
                </label>
            </div>
            <div style="display: flex; justify-content: flex-end; gap: 0.75rem; margin-top: 1.5rem;">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('modalEditar').style.display='none'">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar Cambios</button>
            </div>
        </form>
    </div>
</div>

<script>
function editarEmpleado(emp) {
    document.getElementById('edit_id').value = emp.id;
    document.getElementById('edit_nombre').value = emp.nombre;
    document.getElementById('edit_fecha_ingreso').value = emp.fecha_ingreso;
    document.getElementById('edit_horas_diarias').value = emp.horas_diarias;
    document.getElementById('edit_adicional_titulo').value = emp.adicional_titulo || 0;
    document.getElementById('edit_activo').checked = (emp.activo == 1);
    document.getElementById('modalEditar').style.display = 'flex';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
