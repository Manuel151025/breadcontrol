<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();
$res = $pdo->query("DESCRIBE pedido_cliente")->fetchAll(PDO::FETCH_ASSOC);
print_r($res);
