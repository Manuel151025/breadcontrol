<?php
// modules/produccion/exportar_pdf.php - Export production daily report delegate

require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../controllers/ProduccionController.php';

$pdo = getConexion();
$controller = new ProduccionController($pdo);
$controller->exportarPDF();
