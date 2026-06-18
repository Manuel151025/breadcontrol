<?php
// sql/migrar_cupo_semanal.php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

try {
    $pdo = getConexion();
    echo "Conexión a la base de datos establecida.\n";

    // Agregar cupo_semanal a cliente
    $cols = $pdo->query("SHOW COLUMNS FROM cliente LIKE 'cupo_semanal'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE cliente ADD COLUMN cupo_semanal DECIMAL(10,2) NOT NULL DEFAULT 20000.00");
        echo "Columna 'cupo_semanal' agregada a la tabla 'cliente'.\n";
    } else {
        echo "La columna 'cupo_semanal' ya existe en 'cliente'.\n";
    }

    echo "MIGRACIÓN DE CUPO COMPLETADA CON ÉXITO.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
