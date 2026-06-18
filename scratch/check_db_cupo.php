<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();
try {
    $stmt = $pdo->query("SELECT id_cliente, nombre, cupo_semanal FROM cliente WHERE cupo_semanal > 100000");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Clientes con cupo mayor a 100,000:\n";
    print_r($rows);

    $stmt2 = $pdo->query("SELECT id_cliente, nombre, cupo_semanal FROM cliente");
    $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    echo "\nTodos los clientes:\n";
    print_r($rows2);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
