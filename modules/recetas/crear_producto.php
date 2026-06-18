<?php
// ============================================================
//  MÓDULO: CREAR PRODUCTO
//  Arquitectura: MVC (Controlador de Entrada)
// ============================================================

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../controllers/RecetaController.php';

$pdo = getConexion();
$controller = new RecetaController($pdo);
$controller->crearProducto();