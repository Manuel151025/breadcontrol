<?php
// ============================================================
//  CONEXIÓN A LA BASE DE DATOS
//  Archivo: config/db.php
// ============================================================

require_once __DIR__ . '/env.php';

define('DB_HOST',   get_env('DB_HOST', '127.0.0.1'));
define('DB_USER',   get_env('DB_USER', 'root'));
define('DB_PASS',   get_env('DB_PASS', ''));
define('DB_NAME',   get_env('DB_NAME', 'panaderia_bd'));
define('DB_CHARSET',get_env('DB_CHARSET', 'utf8mb4'));

function getConexion(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
        $opciones = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $opciones);
            $pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");
        } catch (PDOException $e) {
            log_error("Fallo la conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos.");
        }
    }

    $pdo->exec("SET time_zone = '-05:00'");
    return $pdo;
}
