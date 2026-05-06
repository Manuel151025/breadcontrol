<?php
// ============================================================
//  CONEXIÓN A LA BASE DE DATOS
//  Archivo: config/db.php
// ============================================================

$host = $_SERVER['HTTP_HOST'] ?? 'localhost';

if ($host === 'localhost' || $host === '127.0.0.1') {
    // LOCAL (XAMPP)
    define('DB_HOST',   'localhost');
    define('DB_USER',   'root');
    define('DB_PASS',   '');
    define('DB_NAME',   'panaderia_bd');
} else {
    // HOSTING (Hostinger)
    define('DB_HOST',   '193.203.175.84');
    define('DB_USER',   'u631215701_breadcontrol');
    define('DB_PASS',   'Breadcontrol2026');
    define('DB_NAME',   'u631215701_breadcontrol');
}

define('DB_CHARSET','utf8mb4');

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
            die(json_encode([
                'error' => true,
                'mensaje' => 'Error de conexión a la base de datos: ' . $e->getMessage()
            ]));
        }
    }

    $pdo->exec("SET time_zone = '-05:00'");
    return $pdo;
}
