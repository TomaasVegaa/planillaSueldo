<?php
// generar_pdf.php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/classes/CalculationEngine.php';
require_once __DIR__ . '/classes/FechaHelper.php';
require_once __DIR__ . '/lib/fpdf/fpdf.php';

$empleadoId = intval($_GET['empleado_id'] ?? 0);
$periodo = $_GET['periodo'] ?? date('Y-m');

if ($empleadoId <= 0) {
    die("Empleado no especificado.");
}

$pdo = Database::getConnection();

// Asegurar que exista liquidación para este período
CalculationEngine::asegurarLiquidacionesPeriodo($pdo, $periodo);

// Buscar liquidación en DB
$stmt = $pdo->prepare("
    SELECT l.*, e.nombre, e.fecha_ingreso, e.horas_diarias, e.adicional_titulo AS emp_titulo
    FROM liquidaciones l 
    JOIN empleados e ON l.empleado_id = e.id 
    WHERE l.empleado_id = ? AND l.periodo = ?
");
$stmt->execute([$empleadoId, $periodo]);
$liq = $stmt->fetch();

if (!$liq) {
    die("Liquidación no encontrada.");
}

// Asegurar valores calculados
$faltasDias = intval($liq['faltas_dias'] ?? 0);
$diasTrabajados = max(0, 30 - $faltasDias);
$adicionalTitulo = floatval($liq['adicional_titulo'] ?? ($liq['emp_titulo'] ?? 0));
$q1 = floatval($liq['adelanto_q1'] ?? 0);
$q2 = floatval($liq['adelanto_q2'] ?? 0);
$q3 = floatval($liq['adelanto_q3'] ?? 0);

// Convertir encoding para FPDF (Latin1 / ISO-8859-1)
if (!function_exists('txt')) {
    function txt($str) {
        return mb_convert_encoding($str, 'ISO-8859-1', 'UTF-8');
    }
}

if (!class_exists('PDFReceipt')) {
    class PDFReceipt extends FPDF {
        function Header() {
            // Franja decorativa superior
            $this->SetFillColor(30, 41, 59); // Slate Dark
            $this->Rect(0, 0, 210, 15, 'F');
            
            $this->SetY(20);
            $this->SetFont('Arial', 'B', 16);
            $this->SetTextColor(30, 41, 59);
            $this->Cell(0, 8, txt("RECIBO DE PAGO DE HABERES Y LIQUIDACIÓN"), 0, 1, 'C');
            
            $this->SetFont('Arial', 'I', 9);
            $this->SetTextColor(100, 116, 139);
            $this->Cell(0, 4, txt("Comprobante Mensual de Pago de Remuneraciones"), 0, 1, 'C');
            $this->Ln(6);
        }

        function Footer() {
            $this->SetY(-25);
            $this->SetFont('Arial', 'I', 8);
            $this->SetTextColor(148, 163, 184);
            $this->Cell(0, 4, txt("Este documento sirve como comprobante de pago de liquidación final de mes."), 0, 1, 'C');
            $this->Cell(0, 4, txt("Página ") . $this->PageNo() . ' / {nb}', 0, 0, 'C');
        }
    }
}

$pdf = new PDFReceipt('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->AddPage();

// Marco principal
$pdf->SetDrawColor(226, 232, 240);
$pdf->SetLineWidth(0.4);
$pdf->Rect(10, 38, 190, 235);

// Bloque 1: Datos de Cabecera y Empleado
$nombreEmp = txt(strtoupper($liq['nombre']));
$fechaIngreso = date('d/m/Y', strtotime($liq['fecha_ingreso']));
$periodoStr = txt(strtoupper(FechaHelper::formatPeriodo($periodo)));
$horasStr = $liq['horas_diarias'] . " Horas / " . txt("día");
$diasTrabStr = $diasTrabajados . " " . txt("Días") . " (Base 30 " . txt("días") . ")";

$pdf->SetY(42);
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 245, 249);
$pdf->SetTextColor(15, 23, 42);
$pdf->Cell(190, 7, txt("  DATOS DE LA LIQUIDACIÓN Y DEL TRABAJADOR"), 0, 1, 'L', true);

$pdf->Ln(2);
$pdf->SetFont('Arial', '', 9.5);
$pdf->SetTextColor(51, 65, 85);

// Fila 1
$pdf->Cell(30, 6, txt("  Empleado:"), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(80, 6, $nombreEmp, 0, 0, 'L');
$pdf->SetFont('Arial', '', 9.5);
$pdf->Cell(35, 6, txt("Período Abonado:"), 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(45, 6, $periodoStr, 0, 1, 'L');

// Fila 2
$pdf->SetFont('Arial', '', 9.5);
$pdf->Cell(30, 6, txt("  Legajo ID:"), 0, 0, 'L');
$pdf->Cell(80, 6, "#" . sprintf("%04d", $liq['empleado_id']), 0, 0, 'L');
$pdf->Cell(35, 6, txt("Jornada Horaria:"), 0, 0, 'L');
$pdf->Cell(45, 6, $horasStr, 0, 1, 'L');

// Fila 3
$pdf->Cell(30, 6, txt("  F. Ingreso:"), 0, 0, 'L');
$pdf->Cell(80, 6, $fechaIngreso . " (" . $liq['antiguedad_anios'] . txt(" años de antigüedad)"), 0, 0, 'L');
$pdf->Cell(35, 6, txt("Días Liquidados:"), 0, 0, 'L');
$pdf->Cell(45, 6, $diasTrabStr, 0, 1, 'L');

$pdf->Ln(4);

// Bloque 2: Tabla de Conceptos Devengados
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(190, 7, txt("  DESGLOSE DE CONCEPTOS REMUNERATIVOS Y ADICIONALES (DEVENGADO)"), 0, 1, 'L', true);

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetFillColor(226, 232, 240);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(120, 7, txt("  Concepto"), 1, 0, 'L', true);
$pdf->Cell(35, 7, txt("Base / Unid."), 1, 0, 'C', true);
$pdf->Cell(35, 7, txt("Importe Devengado"), 1, 1, 'R', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(15, 23, 42);

// Item 1: Básico Proporcional
$basicoProp = $liq['basico_referencia'] * ($liq['horas_diarias'] / 8.0);
$pdf->Cell(120, 6, txt("  Sueldo Básico (Jornada " . $liq['horas_diarias'] . " hs)"), 'LR', 0, 'L');
$pdf->Cell(35, 6, $liq['horas_diarias'] . " hs/dia", 'LR', 0, 'C');
$pdf->Cell(35, 6, "$ " . number_format($basicoProp, 2, ',', '.'), 'LR', 1, 'R');

// Item 2: Antigüedad
if ($liq['antiguedad_monto'] > 0) {
    $pdf->Cell(120, 6, txt("  Adicional por Antigüedad (" . $liq['antiguedad_anios'] . " % sobre básico)"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, $liq['antiguedad_anios'] . " %", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($liq['antiguedad_monto'], 2, ',', '.'), 'LR', 1, 'R');
}

// Item 3: Presentismo
if ($liq['presentismo_monto'] > 0) {
    $pdf->Cell(120, 6, txt("  Adicional Asistencia Perfecta (Presentismo 8.33%)"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "8.33 %", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($liq['presentismo_monto'], 2, ',', '.'), 'LR', 1, 'R');
} else {
    $pdf->Cell(120, 6, txt("  Presentismo (Perdido por inasistencia no justificada)"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "0 %", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ 0,00", 'LR', 1, 'R');
}

// Item 4: Adicional por Título Contador / Profesional del Legajo
if ($adicionalTitulo > 0) {
    $pdf->Cell(120, 6, txt("  Adicional por Título Contador / Profesional"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "Fijo", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($adicionalTitulo, 2, ',', '.'), 'LR', 1, 'R');
}

// Item 5: Asignación No Remunerativa
if ($liq['no_remunerativo'] > 0) {
    $pdf->Cell(120, 6, txt("  Asignación Suma No Remunerativa"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "Fijo", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($liq['no_remunerativo'], 2, ',', '.'), 'LR', 1, 'R');
}

// Item 6: Prorrateo SAC Aguinaldo Mensual (8.33%)
$sacMonto = $liq['sac_prorrateado'] ?? round(($liq['neto_real_horas'] + $adicionalTitulo) * 0.0833, 2);
$pdf->Cell(120, 6, txt("  Prorrateo Mensual S.A.C. (Aguinaldo 8.33% incorporado)"), 'LR', 0, 'L');
$pdf->Cell(35, 6, "8.33 %", 'LR', 0, 'C');
$pdf->Cell(35, 6, "$ " . number_format($sacMonto, 2, ',', '.'), 'LR', 1, 'R');

// Item 7: Ajuste por Faltas en el mes
if ($faltasDias > 0) {
    $pdf->Cell(120, 6, txt("  Descuento por Faltas No Trabajadas (" . $faltasDias . " día/s)"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "-" . $faltasDias . " dias", 'LR', 0, 'C');
    $pdf->Cell(35, 6, txt("Aplicado"), 'LR', 1, 'R');
}

// Subtotal Devengado
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetFillColor(241, 245, 249);
$pdf->Cell(155, 7, txt("  TOTAL SUELDO DEVENGADO EN EL MES"), 1, 0, 'L', true);
$pdf->Cell(35, 7, "$ " . number_format($liq['neto_devengado'], 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(6);

// Bloque 3: Tabla de Adelantos y Pagos Entregados a Cuenta
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(254, 243, 199); // Amber Light
$pdf->SetTextColor(146, 64, 14);
$pdf->Cell(190, 7, txt("  ADELANTOS Y PAGOS A CUENTA ENTREGADOS DURANTE EL MES"), 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(15, 23, 42);

// Fila Q1
if ($q1 > 0 || ($q2 == 0 && $q3 == 0)) {
    $pdf->Cell(120, 6, txt("  Adelanto / Entrega a Cuenta N° 1"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "Adelanto 1", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($q1, 2, ',', '.'), 'LR', 1, 'R');
}

// Fila Q2
if ($q2 > 0) {
    $pdf->Cell(120, 6, txt("  Adelanto / Entrega a Cuenta N° 2"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "Adelanto 2", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($q2, 2, ',', '.'), 'LR', 1, 'R');
}

// Fila Q3
if ($q3 > 0) {
    $pdf->Cell(120, 6, txt("  Adelanto / Entrega a Cuenta N° 3"), 'LR', 0, 'L');
    $pdf->Cell(35, 6, "Adelanto 3", 'LR', 0, 'C');
    $pdf->Cell(35, 6, "$ " . number_format($q3, 2, ',', '.'), 'LR', 1, 'R');
}

// Total Adelantos
$totalAdelantosPdf = $q1 + $q2 + $q3;
$pdf->SetFont('Arial', 'B', 9.5);
$pdf->SetFillColor(254, 243, 199);
$pdf->Cell(155, 7, txt("  TOTAL ENTREGADO A CUENTA EN EL MES"), 1, 0, 'L', true);
$pdf->Cell(35, 7, "$ " . number_format($totalAdelantosPdf, 2, ',', '.'), 1, 1, 'R', true);

$pdf->Ln(8);

// Bloque 4: SALDO LÍQUIDO FINAL A COBRAR (Destacado)
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(16, 185, 129); // Emerald
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(140, 10, txt("  SALDO FINAL NETO A COBRAR A FIN DE MES"), 1, 0, 'L', true);
$pdf->Cell(50, 10, "$ " . number_format($liq['saldo_a_cobrar'], 2, ',', '.'), 1, 1, 'C', true);

$pdf->Ln(20);

// Firma de Conformidad
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(100, 116, 139);

$pdf->Cell(90, 4, "_________________________________________", 0, 0, 'C');
$pdf->Cell(10, 4, "", 0, 0, 'C');
$pdf->Cell(90, 4, "_________________________________________", 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 9);
$pdf->SetTextColor(30, 41, 59);
$pdf->Cell(90, 5, txt("Firma de Conformidad del Empleado"), 0, 0, 'C');
$pdf->Cell(10, 5, "", 0, 0, 'C');
$pdf->Cell(90, 5, txt("Firma y Sello de la Empresa / Contadora"), 0, 1, 'C');

$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(148, 163, 184);
$pdf->Cell(90, 4, "Recibí la suma de pesos indicada a mi entera satisfacción", 0, 0, 'C');
$pdf->Cell(10, 4, "", 0, 0, 'C');
$pdf->Cell(90, 4, "Comprobante de pago de liquidación mensual", 0, 1, 'C');

// Salida directa del PDF en el navegador
$pdf->Output('I', "Recibo_" . preg_replace('/[^a-zA-Z0-9]/', '_', $liq['nombre']) . "_" . $periodo . ".pdf");
