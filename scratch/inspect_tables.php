<?php
require_once __DIR__ . '/../config/db.php';
$pdo = getConexion();
foreach (['cliente', 'pedido_cliente'] as $tbl) {
    echo "\n--- $tbl ---\n";
    try {
        $q = $pdo->query("DESCRIBE $tbl");
        while ($row = $q->fetch()) {
            printf("%-20s %-15s %s\n", $row['Field'], $row['Type'], var_export($row['Default'], true));
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
