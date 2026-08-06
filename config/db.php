<?php
// config/db.php

class Database {
    private static $pdo = null;

    public static function getConnection() {
        if (self::$pdo === null) {
            // Datos de conexión MySQL de InfinityFree
            $host = 'sql300.infinityfree.com';
            $port = '3306';
            $user = 'if0_42505125';
            $pass = 'tomas140203';
            $dbname = 'if0_42505125_planillasueldos';

            try {
                // 1. Intentar conectar a MySQL (para InfinityFree o servidor remoto)
                $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
                self::$pdo = new PDO($dsn, $user, $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 3 // timeout rápido si se ejecuta localmente sin internet
                ]);
            } catch (PDOException $e) {
                // 2. Fallback a SQLite local (para desarrollo sin conexión o XAMPP local)
                $dbPath = __DIR__ . '/../database/sueldos.db';
                $dir = dirname($dbPath);
                if (!file_exists($dir)) {
                    mkdir($dir, 0777, true);
                }
                self::$pdo = new PDO("sqlite:" . $dbPath);
                self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            }
        }
        return self::$pdo;
    }
}
