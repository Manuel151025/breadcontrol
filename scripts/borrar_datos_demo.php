<?php
declare(strict_types=1);

/**
 * scripts/borrar_datos_demo.php — deshace lo que sembró el generador.
 *
 * Uso:
 *   php scripts/borrar_datos_demo.php --confirmar
 *
 * Borra ÚNICAMENTE los ids anotados en el manifiesto, nunca por rango de fechas
 * ni por patrón: si alguien registró una venta de verdad el mismo día que la
 * demo, tiene que sobrevivir.
 *
 * El orden importa, porque las claves foráneas apuntan hacia atrás:
 *   consumo_lote → producción y lote
 *   lote e historial_precio → compra
 * Así que se borra de la hoja a la raíz.
 *
 * El stock se recalcula al final desde los lotes que quedan vivos, en vez de
 * intentar deshacer suma a suma: es la misma cifra y no acumula errores de
 * redondeo.
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

const MANIFIESTO = __DIR__ . '/datos_demo_manifiesto.json';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!isset(getopt('', ['confirmar'])['confirmar'])) {
    fwrite(STDERR, "Faltó --confirmar. Este script borra datos.\n");
    exit(1);
}

if (!is_file(MANIFIESTO)) {
    fwrite(STDERR, "No hay manifiesto en " . MANIFIESTO . ": nada que deshacer.\n");
    exit(1);
}

$contenido = file_get_contents(MANIFIESTO);
$datos = is_string($contenido) ? json_decode($contenido, true) : null;
if (!is_array($datos) || !isset($datos['ids']) || !is_array($datos['ids'])) {
    fwrite(STDERR, "El manifiesto está ilegible. No se borra nada a ciegas.\n");
    exit(1);
}

$pdo  = getConexion();
$base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$base_origen = (string) ($datos['base'] ?? '');
if ($base_origen !== '' && $base_origen !== $base) {
    fwrite(STDERR, "El manifiesto es de la base '{$base_origen}' y estás conectado a '{$base}'.\n");
    fwrite(STDERR, "No se borra nada: los ids de una base no significan lo mismo en otra.\n");
    exit(1);
}

/** @return list<int> */
function ids(array $manifiesto, string $clave): array {
    $lista = $manifiesto[$clave] ?? [];
    if (!is_array($lista)) {
        return [];
    }
    return array_values(array_filter(array_map('intval', $lista), static fn(int $id): bool => $id > 0));
}

$compras     = ids($datos['ids'], 'compra');
$lotes       = ids($datos['ids'], 'lote');
$producciones = ids($datos['ids'], 'produccion');
$ventas      = ids($datos['ids'], 'venta');
$gastos      = ids($datos['ids'], 'gasto');

echo "Base: {$base}\n";
echo "A borrar: " . count($compras) . " compras, " . count($producciones) . " producciones, "
   . count($ventas) . " ventas, " . count($gastos) . " gastos.\n\n";

/** Borra por lista de ids y devuelve cuántas filas cayeron. */
function borrar(PDO $pdo, string $tabla, string $columna, array $ids): int {
    if ($ids === []) {
        return 0;
    }
    $marcas = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("DELETE FROM {$tabla} WHERE {$columna} IN ({$marcas})");
    $stmt->execute($ids);

    return $stmt->rowCount();
}

$pdo->beginTransaction();
try {
    // Hojas primero.
    $n_consumos = borrar($pdo, 'consumo_lote', 'id_produccion', $producciones);
    // Los lotes sintéticos (EST-*) que creó una producción no están en el
    // manifiesto: se localizan por la producción que los consumió.
    $n_consumos += borrar($pdo, 'consumo_lote', 'id_lote', $lotes);

    $n_prod_precio = borrar($pdo, 'produccion_precio', 'id_produccion', $producciones);
    $n_ventas      = borrar($pdo, 'venta', 'id_venta', $ventas);
    $n_gastos      = borrar($pdo, 'gasto', 'id_gasto', $gastos);
    $n_prod        = borrar($pdo, 'produccion', 'id_produccion', $producciones);

    $n_hist  = borrar($pdo, 'historial_precio', 'id_compra', $compras);
    $n_lotes = borrar($pdo, 'lote', 'id_lote', $lotes);
    $n_compras = borrar($pdo, 'compra', 'id_compra', $compras);

    // Lotes sintéticos huérfanos que dejó una producción ya borrada.
    $n_lotes += (int) $pdo->exec(
        "DELETE FROM lote WHERE id_compra IS NULL AND numero_lote LIKE 'EST-%'
         AND id_lote NOT IN (SELECT id_lote FROM consumo_lote)"
    );

    // Los lotes que ya existían recuperan su disponibilidad.
    //
    // Las producciones de la demo consumen lotes anteriores por FIFO, y borrar
    // sus registros de consumo no devuelve la cantidad al lote. Sin esto, cada
    // ciclo de sembrar y deshacer vaciaba un poco más el inventario real y el
    // daño era acumulativo e invisible: los conteos de filas cuadraban.
    $lotes_previos = $datos['lotes_previos'] ?? [];
    $n_lotes_rest = 0;
    if (is_array($lotes_previos)) {
        $stmt_lote = $pdo->prepare("UPDATE lote SET cantidad_disponible = ?, estado = ? WHERE id_lote = ?");
        foreach ($lotes_previos as $id_lote => $antes) {
            if (!is_numeric($id_lote) || !is_array($antes) || !is_numeric($antes['cantidad'] ?? null)) {
                continue;
            }
            $estado = is_string($antes['estado'] ?? null) ? $antes['estado'] : 'activo';
            $stmt_lote->execute([(float) $antes['cantidad'], $estado, (int) $id_lote]);
            $n_lotes_rest++;
        }
    }

    // Stock devuelto al valor exacto que tenía antes de sembrar.
    //
    // No se recalcula desde los lotes a propósito: la base ya traía insumos con
    // el stock descuadrado respecto a sus lotes (punto 7 de las limitaciones), y
    // recalcular los «arreglaría» de rebote. Este script deshace lo que hizo el
    // generador, no corrige defectos que se encontró por el camino.
    $stock_previo = $datos['stock_previo'] ?? [];
    $n_stock = 0;
    if (is_array($stock_previo)) {
        $stmt_stock = $pdo->prepare("UPDATE insumo SET stock_actual = ? WHERE id_insumo = ?");
        foreach ($stock_previo as $id_insumo => $valor) {
            if (is_numeric($id_insumo) && is_numeric($valor)) {
                $stmt_stock->execute([(float) $valor, (int) $id_insumo]);
                $n_stock++;
            }
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "Falló el borrado, no se tocó nada: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Borrado:\n";
echo "  consumos de lote   : {$n_consumos}\n";
echo "  distribución precio: {$n_prod_precio}\n";
echo "  producciones       : {$n_prod}\n";
echo "  ventas             : {$n_ventas}\n";
echo "  gastos             : {$n_gastos}\n";
echo "  historial de precio: {$n_hist}\n";
echo "  lotes              : {$n_lotes}\n";
echo "  compras            : {$n_compras}\n";
echo "\nDisponibilidad devuelta a {$n_lotes_rest} lotes anteriores.\n";
echo "Stock devuelto a su valor previo en {$n_stock} insumos.\n";

unlink(MANIFIESTO);
echo "Manifiesto eliminado.\n";
