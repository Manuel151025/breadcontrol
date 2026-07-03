<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("Este script solo puede ejecutarse desde la linea de comandos.\n");
}

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/logger.php';

try {
    $pdo = getConexion();
    echo "Conexión a la base de datos establecida correctamente.\n";

    // 1. Crear tabla pago_abono
    $ddl = "
    CREATE TABLE IF NOT EXISTS `pago_abono` (
      `id_abono` INT AUTO_INCREMENT PRIMARY KEY,
      `id_pago` INT NOT NULL,
      `monto` DECIMAL(10,2) NOT NULL,
      `metodo_pago` VARCHAR(50) NOT NULL,
      `nota` TEXT NULL,
      `fecha_abono` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      FOREIGN KEY (`id_pago`) REFERENCES `pago_pedido`(`id_pago`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    $pdo->exec($ddl);
    echo "Tabla 'pago_abono' creada o ya existente.\n";

    // 2. Comprobar si ya existen registros en pago_abono
    $count = (int)$pdo->query("SELECT COUNT(*) FROM pago_abono")->fetchColumn();
    if ($count === 0) {
        echo "No hay abonos registrados. Iniciando migración de abonos históricos...\n";
        
        // Insertar registros basados en pago_pedido que estén APPROVED o PARTIAL
        $migrate_sql = "
            INSERT INTO `pago_abono` (id_pago, monto, metodo_pago, nota, fecha_abono)
            SELECT id_pago, monto, COALESCE(metodo_pago, 'OTRO'), COALESCE(nota, 'Migración de pago histórico'), COALESCE(fecha_pago, fecha_creacion)
            FROM pago_pedido
            WHERE estado IN ('APPROVED', 'PARTIAL') OR (estado = 'PENDING' AND monto > 0 AND metodo_pago IS NOT NULL)
        ";
        $inserted = $pdo->exec($migrate_sql);
        echo "Migración completada con éxito. Se insertaron $inserted abonos históricos.\n";
    } else {
        echo "La tabla 'pago_abono' ya contiene registros. Se omite la migración de datos históricos.\n";
    }

} catch (Exception $e) {
    log_error($e);
    echo "ERROR: la migracion fallo. Revisa el log en /logs para el detalle.\n";
    exit(1);
}
