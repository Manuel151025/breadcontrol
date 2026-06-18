<?php
require_once __DIR__ . '/../includes/sesion.php';
session_unset();
session_destroy();
header('Location: index.php');
exit;
