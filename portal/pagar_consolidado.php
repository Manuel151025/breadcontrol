<?php
// portal/pagar_consolidado.php - Consolidated payment delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalPagoController.php';

$pdo = getConexion();
$controller = new PortalPagoController($pdo);
$controller->pagarConsolidado();