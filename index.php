<?php
// index.php - Front Controller for Public Landing Page

require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/controllers/AuthController.php';

$pdo = getConexion();
$controller = new AuthController($pdo);
$controller->landing();