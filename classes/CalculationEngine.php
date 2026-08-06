<?php
// classes/CalculationEngine.php

class CalculationEngine {

    /**
     * Calcula los años de antigüedad entre la fecha de ingreso y el período (YYYY-MM)
     */
    public static function calcularAntiguedadAnios($fechaIngreso, $periodoStr) {
        if (empty($fechaIngreso)) return 0;
        $ingreso = new DateTime($fechaIngreso);
        $periodo = new DateTime($periodoStr . '-01');
        $diff = $ingreso->diff($periodo);
        return max(0, $diff->y);
    }

    /**
     * Motor de cálculo completo de la liquidación de sueldo
     */
    public static function calcularLiquidacion($datos) {
        $basico8hs = floatval($datos['basico_referencia'] ?? 889390);
        $noRemunerativo = floatval($datos['no_remunerativo'] ?? 97797.89);
        $incGremio = floatval($datos['inc_gremio'] ?? 0);
        $adicionalTitulo = floatval($datos['adicional_titulo'] ?? 0);
        
        $fechaIngreso = $datos['fecha_ingreso'] ?? date('Y-m-d');
        $periodo = $datos['periodo'] ?? date('Y-m'); // YYYY-MM
        $horasDiarias = intval($datos['horas_diarias'] ?? 8);
        $faltasDias = intval($datos['faltas_dias'] ?? 0);
        
        $pierdePresentismo = !empty($datos['pierde_presentismo']);
        
        $adelantoQ1 = floatval($datos['adelanto_q1'] ?? 0);
        $adelantoQ2 = floatval($datos['adelanto_q2'] ?? 0);
        $adelantoQ3 = floatval($datos['adelanto_q3'] ?? 0);

        // 1. Antigüedad (1% por año sobre el básico 8hs)
        $antiguedadAnios = self::calcularAntiguedadAnios($fechaIngreso, $periodo);
        $antiguedadMonto = $basico8hs * $antiguedadAnios * 0.01;

        // 2. Presentismo (8.33% sobre Básico + Antigüedad)
        if ($pierdePresentismo) {
            $presentismoMonto = 0.0;
        } else {
            $presentismoMonto = ($basico8hs + $antiguedadMonto) * 0.0833;
        }

        // 3. Total Bruto Convenio 8hs
        $totalBruto8hs = $basico8hs + $antiguedadMonto + $presentismoMonto + $incGremio;

        // 4. Retenciones Teóricas CCT (19.5% + 5.5%)
        $retencionesTeoricas = ($totalBruto8hs * 0.195) + ($noRemunerativo * 0.055);

        // 5. Neto Convenio 8hs
        $netoConvenio8hs = $totalBruto8hs - $retencionesTeoricas + $noRemunerativo;

        // 6. Proporcional por Horas Realmente Trabajadas + Adicional por Título de Legajo
        $netoRealHoras = $netoConvenio8hs * ($horasDiarias / 8.0);
        
        // Base mensual con adicional por título del perfil de empleado
        $baseConTitulo = $netoRealHoras + $adicionalTitulo;

        // 7. SAC Prorrateado Mensual (+8.33%) y Descuento por Faltas (Base 30 días)
        $diasTrabajados = max(0, 30 - $faltasDias);
        $sacProrrateado = $baseConTitulo * 0.0833;
        
        $netoDevengado = ($baseConTitulo * 1.0833 / 30.0) * $diasTrabajados;

        // 8. Total Adelantos (hasta 3 entregas a cuenta) y Saldo Líquido a Cobrar
        $totalAdelantos = $adelantoQ1 + $adelantoQ2 + $adelantoQ3;
        $saldoACobrar = $netoDevengado - $totalAdelantos;

        return [
            'basico_referencia'    => round($basico8hs, 2),
            'horas_diarias'        => $horasDiarias,
            'antiguedad_anios'     => $antiguedadAnios,
            'antiguedad_monto'     => round($antiguedadMonto, 2),
            'presentismo_monto'    => round($presentismoMonto, 2),
            'pierde_presentismo'   => $pierdePresentismo ? 1 : 0,
            'adicional_titulo'     => round($adicionalTitulo, 2),
            'no_remunerativo'      => round($noRemunerativo, 2),
            'inc_gremio'           => round($incGremio, 2),
            'total_bruto_8hs'      => round($totalBruto8hs, 2),
            'retenciones_teoricas' => round($retencionesTeoricas, 2),
            'neto_convenio_8hs'    => round($netoConvenio8hs, 2),
            'neto_real_horas'      => round($netoRealHoras, 2),
            'sac_prorrateado'      => round($sacProrrateado, 2),
            'faltas_dias'          => $faltasDias,
            'dias_trabajados'      => $diasTrabajados,
            'neto_devengado'       => round($netoDevengado, 2),
            'adelanto_q1'          => round($adelantoQ1, 2),
            'adelanto_q2'          => round($adelantoQ2, 2),
            'adelanto_q3'          => round($adelantoQ3, 2),
            'total_adelantos'      => round($totalAdelantos, 2),
            'saldo_a_cobrar'       => round($saldoACobrar, 2),
        ];
    }

    /**
     * Garantiza que exista la liquidación en DB para el período seleccionado.
     * Solo incluye empleados ACTIVOS (activo = 1).
     */
    public static function asegurarLiquidacionesPeriodo($pdo, $periodo) {
        $stmtClean = $pdo->prepare("
            DELETE FROM liquidaciones 
            WHERE periodo = ? AND empleado_id IN (SELECT id FROM empleados WHERE activo = 0)
        ");
        $stmtClean->execute([$periodo]);

        $stmtCheck = $pdo->prepare("
            SELECT COUNT(*) FROM liquidaciones l 
            JOIN empleados e ON l.empleado_id = e.id 
            WHERE l.periodo = ? AND e.activo = 1
        ");
        $stmtCheck->execute([$periodo]);
        $count = $stmtCheck->fetchColumn();

        $stmtEmp = $pdo->query("SELECT * FROM empleados WHERE activo = 1 ORDER BY nombre ASC");
        $empleadosActivos = $stmtEmp->fetchAll();

        if ($count < count($empleadosActivos)) {
            $stmtConfig = $pdo->query("SELECT clave, valor FROM configuracion");
            $config = $stmtConfig->fetchAll(PDO::FETCH_KEY_PAIR);
            $basico8hs = floatval($config['basico_8hs'] ?? 889390);
            $noRemunerativo = floatval($config['no_remunerativo'] ?? 97797.89);
            $incGremio = floatval($config['inc_gremio'] ?? 0);

            $pdo->beginTransaction();
            try {
                foreach ($empleadosActivos as $emp) {
                    $stmtCheckEmp = $pdo->prepare("SELECT COUNT(*) FROM liquidaciones WHERE empleado_id = ? AND periodo = ?");
                    $stmtCheckEmp->execute([$emp['id'], $periodo]);
                    if ($stmtCheckEmp->fetchColumn() == 0) {
                        $res = self::calcularLiquidacion([
                            'basico_referencia' => $basico8hs,
                            'no_remunerativo'   => $noRemunerativo,
                            'inc_gremio'        => $incGremio,
                            'adicional_titulo'  => floatval($emp['adicional_titulo'] ?? 0),
                            'fecha_ingreso'     => $emp['fecha_ingreso'],
                            'periodo'           => $periodo,
                            'horas_diarias'     => $emp['horas_diarias'],
                            'faltas_dias'       => 0,
                            'adelanto_q1'       => 0,
                            'adelanto_q2'       => 0,
                            'adelanto_q3'       => 0
                        ]);

                        $stmtIns = $pdo->prepare("
                            INSERT INTO liquidaciones (
                                empleado_id, periodo, basico_referencia, horas_diarias, antiguedad_anios,
                                antiguedad_monto, presentismo_monto, no_remunerativo, retenciones_teoricas,
                                neto_convenio_8hs, neto_real_horas, sac_prorrateado, faltas_dias, neto_devengado,
                                adelanto_q1, adelanto_q2, adelanto_q3, total_adelantos, saldo_a_cobrar, adicional_titulo, observaciones
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $stmtIns->execute([
                            $emp['id'], $periodo, $res['basico_referencia'], $res['horas_diarias'], $res['antiguedad_anios'],
                            $res['antiguedad_monto'], $res['presentismo_monto'], $res['no_remunerativo'],
                            $res['retenciones_teoricas'], $res['neto_convenio_8hs'], $res['neto_real_horas'],
                            $res['sac_prorrateado'], 0, $res['neto_devengado'],
                            0, 0, 0, 0, $res['saldo_a_cobrar'], $res['adicional_titulo'], 'Generación automática de período'
                        ]);
                    }
                }
                $pdo->commit();
            } catch (Exception $e) {
                $pdo->rollBack();
            }
        }
    }
}
