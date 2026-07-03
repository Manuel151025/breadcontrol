<?php
// sql/migrar_instructor_flow.php
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

    // 1. Agregar id_instructor a cliente
    $cols = $pdo->query("SHOW COLUMNS FROM cliente LIKE 'id_instructor'")->fetch();
    if (!$cols) {
        $pdo->exec("ALTER TABLE cliente ADD COLUMN id_instructor INT NULL DEFAULT NULL");
        $pdo->exec("ALTER TABLE cliente ADD CONSTRAINT fk_cliente_instructor FOREIGN KEY (id_instructor) REFERENCES cliente(id_cliente) ON DELETE SET NULL");
        echo "Columna 'id_instructor' agregada a la tabla 'cliente'.\n";
    } else {
        echo "La columna 'id_instructor' ya existe en 'cliente'.\n";
    }

    // 2. Agregar aprobado_instructor a pedido_cliente
    $cols2 = $pdo->query("SHOW COLUMNS FROM pedido_cliente LIKE 'aprobado_instructor'")->fetch();
    if (!$cols2) {
        $pdo->exec("ALTER TABLE pedido_cliente ADD COLUMN aprobado_instructor TINYINT(1) NOT NULL DEFAULT 1");
        echo "Columna 'aprobado_instructor' agregada a la tabla 'pedido_cliente'.\n";
    } else {
        echo "La columna 'aprobado_instructor' ya existe en 'pedido_cliente'.\n";
    }

    // 3. Vincular aprendices existentes a la Tienda ADSO por defecto
    $stmt_adso = $pdo->query("SELECT id_cliente FROM cliente WHERE nombre LIKE '%ADSO%' AND tipo='tienda' LIMIT 1");
    $id_adso = $stmt_adso->fetchColumn();
    if ($id_adso) {
        $stmt_upd = $pdo->prepare("UPDATE cliente SET id_instructor = ? WHERE es_aprendiz = 1 AND id_instructor IS NULL");
        $stmt_upd->execute([$id_adso]);
        $affected = $stmt_upd->rowCount();
        echo "Vinculados $affected aprendices existentes al instructor ID $id_adso (Tienda ADSO).\n";
    } else {
        echo "No se encontró una tienda 'ADSO' para vincular aprendices existentes por defecto.\n";
    }

    echo "MIGRACIÓN COMPLETADA CON ÉXITO.\n";

} catch (Exception $e) {
    log_error($e);
    echo "ERROR: la migracion fallo. Revisa el log en /logs para el detalle.\n";
    exit(1);
}
