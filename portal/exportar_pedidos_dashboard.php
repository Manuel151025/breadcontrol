<?php
// portal/exportar_pedidos_dashboard.php - Export delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalExportController.php';

$pdo = getConexion();
$controller = new PortalExportController($pdo);
$controller->exportarPedidosDashboard();
