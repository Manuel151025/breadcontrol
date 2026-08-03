<?php
// portal/exportar_recibo_pago.php - Export payment receipt delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalExportController.php';

$pdo = getConexion();
$controller = new PortalExportController($pdo);
$controller->exportarReciboPago();
