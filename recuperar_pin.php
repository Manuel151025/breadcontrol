<?php
// recuperar_pin.php - Front Controller for Admin PIN/Password Recovery

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/AuthController.php';

$pdo = getConexion();
$controller = new AuthController($pdo);
$controller->recuperarPin();
