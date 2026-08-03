<?php
// ============================================================
//  BOOTSTRAP DE PRUEBAS (PHPUnit)
//  Archivo: tests/bootstrap.php
//
//  Carga el autoloader de Composer y el entorno mínimo de la
//  aplicación ANTES de que PHPUnit produzca salida, para que
//  session_start() (includes/sesion.php) no colisione con headers.
// ============================================================

require_once __DIR__ . '/../vendor/autoload.php';

// Entorno base de la aplicación (config, funciones y sesión/CSRF).
// includes/sesion.php carga a su vez config/app.php y config/db.php.
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/funciones.php';
