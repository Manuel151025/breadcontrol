<?php
// logout.php - Front Controller for Admin Logout

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/AuthController.php';

$pdo = getConexion();
$controller = new AuthController($pdo);
$controller->logout();
