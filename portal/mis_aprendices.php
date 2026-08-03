<?php
// portal/mis_aprendices.php - Gestión de aprendices del instructor (delegate)

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../controllers/portal/PortalInstructorController.php';

$pdo = getConexion();
$controller = new PortalInstructorController($pdo);
$controller->misAprendices();
