<?php
declare(strict_types=1);

/**
 * scripts/migraciones.php — ¿qué migraciones le faltan a esta base de datos?
 *
 * Uso:
 *   php scripts/migraciones.php                        estado (por defecto)
 *   php scripts/migraciones.php --marcar=ARCHIVO.sql   registrar una como aplicada
 *
 * POR QUÉ NO LAS APLICA
 * ---------------------
 * Podría, pero mentiría. En MySQL las sentencias DDL hacen commit implícito, así
 * que una migración a medias no se puede deshacer: si falla en el tercer ALTER,
 * los dos primeros ya están hechos y no hay vuelta atrás automática. Y la imagen
 * de la aplicación no lleva cliente `mysql`, de modo que en el servidor este
 * script ni siquiera podría ejecutarlas.
 *
 * Aplicarlas sigue siendo manual y consciente, como hasta ahora. Lo que faltaba
 * —y es lo que resuelve esto— era poder responder «¿está producción al día?» sin
 * exportar la estructura de los dos lados y compararla a mano.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

const DIR_MIGRACIONES = __DIR__ . '/../sql/migraciones';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$opciones = getopt('', ['marcar::', 'nota::']);
$pdo = getConexion();
$base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

// ── ¿Existe el control? ─────────────────────────────────────────────────────

$tiene_tabla = $pdo->query("SHOW TABLES LIKE 'migracion'")->fetch() !== false;
if (!$tiene_tabla) {
    fwrite(STDERR, "La base '{$base}' no tiene tabla de control de migraciones.\n\n");
    fwrite(STDERR, "Ejecuta primero:\n");
    fwrite(STDERR, "  sql/migraciones/2026-08-20_01_control_migraciones.sql\n\n");
    fwrite(STDERR, "Da por aplicadas las nueve anteriores, que lo están en todas las bases\n");
    fwrite(STDERR, "existentes y también en un despliegue nuevo desde 01_esquema_base.sql.\n");
    exit(1);
}

// ── Archivos en disco frente a filas en la base ─────────────────────────────

/** @return array<string, string> archivo => md5 */
function migracionesEnDisco(): array {
    $archivos = glob(DIR_MIGRACIONES . '/*.sql') ?: [];
    sort($archivos); // el nombre empieza por fecha: orden alfabético = cronológico

    $salida = [];
    foreach ($archivos as $ruta) {
        $contenido = file_get_contents($ruta);
        $salida[basename($ruta)] = is_string($contenido) ? md5($contenido) : '';
    }

    return $salida;
}

/** @return array<string, array{checksum: ?string, aplicada_en: string, nota: ?string}> */
function migracionesAplicadas(PDO $pdo): array {
    $salida = [];
    $filas = $pdo->query("SELECT archivo, checksum, aplicada_en, nota FROM migracion")
                 ->fetchAll(PDO::FETCH_ASSOC);
    foreach ($filas as $fila) {
        if (!is_array($fila) || !is_string($fila['archivo'])) {
            continue;
        }
        $salida[$fila['archivo']] = [
            'checksum'    => is_string($fila['checksum']) ? $fila['checksum'] : null,
            'aplicada_en' => is_string($fila['aplicada_en']) ? $fila['aplicada_en'] : '',
            'nota'        => is_string($fila['nota']) ? $fila['nota'] : null,
        ];
    }

    return $salida;
}

$en_disco   = migracionesEnDisco();
$aplicadas  = migracionesAplicadas($pdo);

// ── Modo: registrar una como aplicada ───────────────────────────────────────

if (isset($opciones['marcar']) && $opciones['marcar'] !== false) {
    $archivo = is_string($opciones['marcar']) ? basename(trim($opciones['marcar'])) : '';

    if ($archivo === '' || !isset($en_disco[$archivo])) {
        fwrite(STDERR, "No existe sql/migraciones/{$archivo}\n");
        exit(1);
    }
    if (isset($aplicadas[$archivo])) {
        fwrite(STDERR, "Ya estaba registrada como aplicada ({$aplicadas[$archivo]['aplicada_en']}).\n");
        exit(1);
    }

    $nota = isset($opciones['nota']) && is_string($opciones['nota']) ? $opciones['nota'] : null;
    $stmt = $pdo->prepare("INSERT INTO migracion (archivo, checksum, nota) VALUES (?, ?, ?)");
    $stmt->execute([$archivo, $en_disco[$archivo], $nota]);

    echo "Registrada como aplicada en '{$base}': {$archivo}\n";
    exit(0);
}

// ── Modo por defecto: estado ────────────────────────────────────────────────

echo "Base de datos: {$base}\n";
echo str_repeat('-', 64) . "\n";

$pendientes = [];
$alteradas  = [];

foreach ($en_disco as $archivo => $md5) {
    if (!isset($aplicadas[$archivo])) {
        $pendientes[] = $archivo;
        printf("  PENDIENTE  %s\n", $archivo);
        continue;
    }

    $registrado = $aplicadas[$archivo]['checksum'];
    if ($registrado === null) {
        // Heredada: se dio por aplicada sin saber con qué contenido.
        printf("  aplicada   %s  (heredada)\n", $archivo);
    } elseif ($registrado !== $md5) {
        $alteradas[] = $archivo;
        printf("  ALTERADA   %s  (el archivo cambió después de aplicarse)\n", $archivo);
    } else {
        printf("  aplicada   %s  %s\n", $archivo, substr($aplicadas[$archivo]['aplicada_en'], 0, 10));
    }
}

// Filas en la base sin archivo: alguien borró o renombró una migración.
$huerfanas = array_diff(array_keys($aplicadas), array_keys($en_disco));
foreach ($huerfanas as $archivo) {
    printf("  HUÉRFANA   %s  (registrada, pero el archivo ya no está)\n", $archivo);
}

echo str_repeat('-', 64) . "\n";

if ($pendientes === [] && $alteradas === [] && $huerfanas === []) {
    echo "Al día: " . count($en_disco) . " migraciones, todas aplicadas.\n";
    exit(0);
}

if ($pendientes !== []) {
    echo "\nFaltan " . count($pendientes) . ". Para aplicarlas, en orden:\n\n";
    foreach ($pendientes as $archivo) {
        echo "  # 1) ejecutar el archivo contra la base\n";
        echo "  #    local:      mysql -u root {$base} < sql/migraciones/{$archivo}\n";
        echo "  #    en el VPS:  docker exec -i \$DB sh -c 'mysql -u root -p\"\$MYSQL_ROOT_PASSWORD\" {$base}' < sql/migraciones/{$archivo}\n";
        echo "  # 2) registrarla\n";
        echo "  php scripts/migraciones.php --marcar={$archivo}\n\n";
    }
    echo "Se aplican de una en una y se registra cada una solo si terminó bien:\n";
    echo "en MySQL el DDL hace commit implícito, así que una migración a medias\n";
    echo "no se deshace sola y conviene saber exactamente dónde se quedó.\n";
}

if ($alteradas !== []) {
    echo "\nATENCIÓN: " . count($alteradas) . " migración(es) cambiaron después de aplicarse.\n";
    echo "Lo que hay en la base se generó con otro contenido del archivo. Editar una\n";
    echo "migración ya aplicada no vuelve a ejecutarla: si el cambio importa, hace\n";
    echo "falta una migración nueva.\n";
}

if ($huerfanas !== []) {
    echo "\nATENCIÓN: hay migraciones registradas cuyo archivo ya no existe.\n";
    echo "Si se renombró, la base seguirá creyendo que falta la nueva y sobra la vieja.\n";
}

exit(1);
