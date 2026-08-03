<?php
// portal/completar_email.php - Pantalla intermedia para pedir el correo (delegate)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalAuthController.php';

$pdo = getConexion();
$controller = new PortalAuthController($pdo);
$controller->completarEmail();
