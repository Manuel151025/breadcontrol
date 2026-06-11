<?php
// modules/cierre/index.php — Enrutador delgado (MVC)
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../controllers/CierreController.php';

$pdo = getConexion();
$ctrl = new CierreController($pdo);
$ctrl->index();