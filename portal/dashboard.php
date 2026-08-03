<?php
// portal/dashboard.php - Dashboard delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalPedidoController.php';

$pdo = getConexion();
$controller = new PortalPedidoController($pdo);
$controller->dashboard();
