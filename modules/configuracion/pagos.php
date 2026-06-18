<?php
// ============================================================
//  MÓDULO: CONFIGURACIÓN - PAGOS DIGITALES
//  Arquitectura: MVC (Controlador de Entrada)
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../controllers/ConfiguracionController.php';

$pdo = getConexion();
$controller = new ConfiguracionController($pdo);
$controller->pagos();