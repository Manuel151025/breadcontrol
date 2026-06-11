<?php
// ============================================================
//  MÓDULO: INVENTARIO
//  Arquitectura: MVC (Controlador de Entrada)
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../controllers/InventarioController.php';

// Inicializar conexión y controlador
$pdo = getConexion();
$controller = new InventarioController($pdo);

// Ejecutar acción principal
$controller->index();