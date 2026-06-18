<?php
require_once __DIR__ . '/../config/db.php';
try {
    $pdo = getConexion();
    $pdo->exec("ALTER TABLE pedido_cliente MODIFY COLUMN fecha_entrega DATETIME NOT NULL");
    echo "Columna 'fecha_entrega' alterada exitosamente a DATETIME.\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
