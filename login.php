<?php
// login.php - Front Controller for Admin Login

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/AuthController.php';

$pdo = getConexion();
$controller = new AuthController($pdo);
$controller->login();
