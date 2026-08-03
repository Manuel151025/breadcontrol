<?php
// portal/exportar_cartera_instructor.php - Export cartera delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalExportController.php';

$pdo = getConexion();
$controller = new PortalExportController($pdo);
$controller->exportarCarteraInstructor();
