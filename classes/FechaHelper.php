<?php
// classes/FechaHelper.php

class FechaHelper {
    private static $meses = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre'
    ];

    /**
     * Formatea un período 'YYYY-MM' a 'Mes Año' en español (Ej: '2026-04' -> 'Abril 2026')
     */
    public static function formatPeriodo($periodo) {
        $parts = explode('-', $periodo);
        if (count($parts) === 2) {
            $anio = $parts[0];
            $mesNum = $parts[1];
            $nombreMes = self::$meses[$mesNum] ?? $mesNum;
            return "$nombreMes $anio";
        }
        return $periodo;
    }

    /**
     * Devuelve únicamente los meses existentes en DB más el rango base (Abril 2026 a Julio 2026)
     */
    public static function getPeriodosDisponibles($pdo = null) {
        $periodosSet = [
            '2026-04' => 'Abril 2026',
            '2026-05' => 'Mayo 2026',
            '2026-06' => 'Junio 2026',
            '2026-07' => 'Julio 2026'
        ];

        if ($pdo) {
            try {
                $stmt = $pdo->query("SELECT DISTINCT periodo FROM liquidaciones ORDER BY periodo ASC");
                $dbPeriodos = $stmt->fetchAll(PDO::FETCH_COLUMN);
                foreach ($dbPeriodos as $p) {
                    if (!isset($periodosSet[$p])) {
                        $periodosSet[$p] = self::formatPeriodo($p);
                    }
                }
            } catch (Exception $e) {
                // Ignore fallback
            }
        }

        ksort($periodosSet);
        return $periodosSet;
    }
}
