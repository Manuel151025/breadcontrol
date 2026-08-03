<?php
// portal/recuperar_pass.php - Password recovery delegate

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalAuthController.php';

$pdo = getConexion();
$controller = new PortalAuthController($pdo);
$controller->recuperarPass();
