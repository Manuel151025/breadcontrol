<?php
date_default_timezone_set('America/Bogota');
// ============================================================
//  CONFIGURACIÓN GENERAL DE LA APLICACIÓN
//  Archivo: config/app.php
// ============================================================

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';

define('APP_NOMBRE',   'Sistema Inventario Panadería');
define('APP_VERSION',  '1.0');

// APP_URL viene del .env (fuente de verdad). Si no esta configurado,
// se detecta automaticamente a partir del Host de la peticion como respaldo
// para desarrollo local (no usar el Host de la peticion en produccion:
// es controlado por el cliente y no debe confiarse para construir URLs).
$app_url_env = get_env('APP_URL');
if ($app_url_env) {
    define('APP_URL', rtrim($app_url_env, '/'));
} else {
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    if ($host === 'localhost' || $host === '127.0.0.1') {
        define('APP_URL', 'http://localhost/panaderia');
    } else {
        define('APP_URL', 'https://' . $host);
    }
}

// Zona horaria (Colombia)

// Sesión
define('SESSION_NOMBRE',   'panaderia_session');
define('SESSION_DURACION', 28800); // 8 horas en segundos

// Rutas de módulos
define('MOD_INVENTARIO', APP_URL . '/modules/inventario');
define('MOD_RECETAS',    APP_URL . '/modules/recetas');
define('MOD_COMPRAS',    APP_URL . '/modules/compras');
define('MOD_FINANZAS',   APP_URL . '/modules/finanzas');
define('MOD_TABLERO',    APP_URL . '/modules/tablero');

// Correo — leer desde .env con get_env('SENDGRID_API_KEY')