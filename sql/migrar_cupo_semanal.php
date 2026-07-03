<?php
// sql/migrar_cupo_semanal.php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la linea de comandos.\n");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';

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
    log_error($e);
    echo "ERROR: la migracion fallo. Revisa el log en /logs para el detalle.\n";
    exit(1);
}
