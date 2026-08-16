<?php
declare(strict_types=1);

/**
 * scripts/sembrar_datos_demo.php — genera un mes de actividad de la panadería.
 *
 * Uso:
 *   php scripts/sembrar_datos_demo.php --confirmar
 *   php scripts/sembrar_datos_demo.php --confirmar --dias=30 --semilla=2026
 *
 * POR QUÉ NO SON INSERTS SUELTOS
 * ------------------------------
 * Las tablas están encadenadas: una compra no es una fila en `compra`, sino
 * también un lote con su consecutivo, la suma al stock del insumo y, si el
 * precio cambió, una fila en el historial. Una producción consume lotes en
 * orden FIFO y de ahí calcula su costo. Insertar filas a mano produciría justo
 * los descuadres que el proyecto ya tiene documentados como abiertos: el
 * `stock_actual` que no cuadra con la suma de sus lotes (punto 7) y las ventas
 * sin producto (punto 10).
 *
 * Por eso este script llama a los MISMOS modelos que usa la aplicación cuando
 * alguien hace clic. Los efectos secundarios ocurren solos, y los datos salen
 * consistentes por construcción y no porque yo me acordara de replicarlos.
 *
 * DOS CONCESIONES, DELIBERADAS Y ACOTADAS
 * ---------------------------------------
 * 1. Ventas y gastos se insertan con la fecha de hoy porque sus modelos usan
 *    NOW() sin parámetro, y justo después se corrige la fecha con un UPDATE de
 *    esa única columna. Todo lo demás lo hizo el modelo.
 * 2. Se salta el límite de «producción de máximo 7 días atrás», que vive en el
 *    controlador y es una barrera contra erratas al teclear, no una regla de
 *    integridad. Rellenar un histórico es precisamente el caso que no cubre.
 *
 * DESHACER
 * --------
 * Cada id creado se anota en scripts/datos_demo_manifiesto.json.
 * Para borrarlo todo: php scripts/borrar_datos_demo.php --confirmar
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/funciones.php';
require_once __DIR__ . '/../models/CompraModel.php';
require_once __DIR__ . '/../models/ProduccionModel.php';
require_once __DIR__ . '/../models/VentaModel.php';
require_once __DIR__ . '/../models/GastoModel.php';

const MANIFIESTO = __DIR__ . '/datos_demo_manifiesto.json';

// ── Salvaguardas ────────────────────────────────────────────────────────────

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$opciones = getopt('', ['confirmar', 'dias::', 'semilla::']);

if (!isset($opciones['confirmar'])) {
    fwrite(STDERR, "Faltó --confirmar. Este script escribe datos de verdad en la base.\n");
    exit(1);
}

$entorno = defined('APP_ENV') ? (string) constant('APP_ENV') : 'production';
if ($entorno === 'production') {
    fwrite(STDERR, "APP_ENV=production. Este generador es para desarrollo, no para el servidor real.\n");
    exit(1);
}

$pdo = getConexion();
$base = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();

$dias    = max(1, (int) ($opciones['dias'] ?? 30));
$semilla = (int) ($opciones['semilla'] ?? 20260816);

// Aleatoriedad reproducible: dos ejecuciones con la misma semilla generan la
// misma historia, lo que hace que un problema se pueda volver a provocar.
mt_srand($semilla);

echo "Base de datos : {$base}\n";
echo "Entorno       : {$entorno}\n";
echo "Periodo       : últimos {$dias} días\n";
echo "Semilla       : {$semilla}\n\n";

// ── Catálogo existente ──────────────────────────────────────────────────────

/** @return list<array<string, mixed>> */
function filas(PDO $pdo, string $sql): array {
    $salida = [];
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        if (is_array($fila)) {
            $salida[] = $fila;
        }
    }
    return $salida;
}

$id_usuario = (int) $pdo->query("SELECT id_usuario FROM usuario WHERE nombre_usuario = 'propietario' LIMIT 1")->fetchColumn();
if ($id_usuario <= 0) {
    fwrite(STDERR, "No existe el usuario 'propietario'.\n");
    exit(1);
}

$insumos     = filas($pdo, "SELECT id_insumo, nombre, unidad_medida, es_harina FROM insumo WHERE activo = 1");
$proveedores = filas($pdo, "SELECT id_proveedor FROM proveedor WHERE activo = 1");
$productos   = filas($pdo, "SELECT id_producto, nombre FROM producto WHERE activo = 1");
$categorias  = filas($pdo, "SELECT id_categoria, precio_unitario FROM categoria_precio ORDER BY precio_unitario");
$tiendas     = filas($pdo, "SELECT id_cliente FROM cliente WHERE tipo = 'tienda' AND activo = 1 AND es_aprendiz = 0 LIMIT 12");

foreach ([['insumos', $insumos], ['proveedores', $proveedores], ['productos', $productos], ['categorías de precio', $categorias]] as [$que, $lista]) {
    if ($lista === []) {
        fwrite(STDERR, "No hay {$que} en la base. Crea el catálogo antes de sembrar.\n");
        exit(1);
    }
}

$modelo_compra    = new CompraModel($pdo);
$modelo_produccion = new ProduccionModel($pdo);
$modelo_venta     = new VentaModel($pdo);
$modelo_gasto     = new GastoModel($pdo);

// Solo los productos con receta vigente pueden producirse.
$producibles = [];
foreach ($productos as $p) {
    $id_producto = (int) $p['id_producto'];
    $id_receta   = $modelo_produccion->getRecetaVigente($id_producto);
    if ($id_receta) {
        $producibles[] = [
            'id_producto'    => $id_producto,
            'nombre'         => (string) $p['nombre'],
            'id_receta'      => (int) $id_receta,
            'ingredientes'   => $modelo_produccion->getIngredientesReceta((int) $id_receta),
            'por_tanda'      => (float) $modelo_produccion->getCantidadPorTanda($id_producto),
        ];
    }
}
if ($producibles === []) {
    fwrite(STDERR, "Ningún producto activo tiene receta vigente: no se puede producir nada.\n");
    exit(1);
}

echo "Catálogo: " . count($insumos) . " insumos, " . count($producibles) . " productos con receta, "
   . count($categorias) . " precios, " . count($tiendas) . " tiendas.\n\n";

// ── Utilidades ──────────────────────────────────────────────────────────────

$creados = ['compra' => [], 'lote' => [], 'produccion' => [], 'venta' => [], 'gasto' => []];

// Foto del stock antes de tocar nada. Al deshacer se restaura este valor exacto
// en vez de recalcularlo desde los lotes: la base ya trae insumos cuyo
// stock_actual no cuadra con sus lotes (punto 7 de las limitaciones), y
// «arreglárselos» de rebote sería modificar datos que este script no creó.
$stock_previo = [];
foreach (filas($pdo, "SELECT id_insumo, stock_actual FROM insumo") as $fila) {
    $stock_previo[(string) $fila['id_insumo']] = (float) $fila['stock_actual'];
}

// Y foto de los lotes que ya existían. Las producciones de la demo consumen
// lotes ANTERIORES por FIFO, y borrar luego sus registros de consumo no les
// devuelve la cantidad: sin esta foto, cada ciclo de sembrar y deshacer vaciaba
// un poco más el inventario real y no había forma de volver atrás.
$lotes_previos = [];
foreach (filas($pdo, "SELECT id_lote, cantidad_disponible, estado FROM lote") as $fila) {
    $lotes_previos[(string) $fila['id_lote']] = [
        'cantidad' => (float) $fila['cantidad_disponible'],
        'estado'   => (string) $fila['estado'],
    ];
}

function alAzar(array $lista): mixed {
    return $lista[mt_rand(0, count($lista) - 1)];
}

/**
 * Precio por unidad de cada insumo, tomado del histórico real de compras.
 *
 * Inventar precios producía una panadería en quiebra: los huevos salían a
 * $1.364 cuando en los datos reales estaban a $433, y el costo de producción
 * quedaba 24 veces por encima del ingreso por ventas. El informe de finanzas
 * mostraba números imposibles, que es exactamente lo que no queremos enseñar.
 *
 * Si un insumo nunca se ha comprado, se estima por su unidad de medida.
 *
 * @return array<int, float> Precio por id de insumo.
 */
function preciosHistoricos(PDO $pdo): array {
    $precios = [];
    $sql = "SELECT c.id_insumo, c.precio_unitario
            FROM compra c
            WHERE c.fecha_compra = (
                SELECT MAX(c2.fecha_compra) FROM compra c2 WHERE c2.id_insumo = c.id_insumo
            )";
    foreach ($pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) as $fila) {
        if (is_array($fila) && is_numeric($fila['precio_unitario']) && (float) $fila['precio_unitario'] > 0) {
            $precios[(int) $fila['id_insumo']] = (float) $fila['precio_unitario'];
        }
    }

    return $precios;
}

/** Última red: un insumo que nunca se ha comprado no tiene precio de referencia. */
function precioBase(string $unidad): float {
    return match (strtolower($unidad)) {
        'kg'    => (float) mt_rand(1800, 4000),
        'g'     => (float) mt_rand(15, 40),
        'l'     => (float) mt_rand(2500, 6000),
        'ml'    => (float) mt_rand(8, 25),
        default => (float) mt_rand(300, 900),
    };
}

// ── Plan de producción ──────────────────────────────────────────────────────
//
// Se planifica ANTES de comprar. Es el orden que evita el problema de fondo: si
// se compra a ojo, las recetas piden más de lo que hay, el FIFO se queda sin
// lotes y el modelo fabrica lotes sintéticos con el último precio conocido.
// Funciona —está pensado para eso— pero entonces el histórico no enseña un FIFO
// de verdad, que es justo lo que se quiere mostrar.

$hoy    = new DateTimeImmutable('today');
$inicio = $hoy->modify('-' . ($dias - 1) . ' days');

$plan = [];
for ($i = 0; $i < $dias; $i++) {
    $dia = $inicio->modify("+{$i} days");
    if ((int) $dia->format('w') === 0) {
        continue; // domingo: la panadería no opera
    }
    $delDia = [];
    $cuantos = mt_rand(1, 2);
    for ($p = 0; $p < $cuantos; $p++) {
        $prod = alAzar($producibles);
        // Una tanda casi siempre, dos de vez en cuando. Con 2-5 salían 726
        // unidades por hornada frente a las ~208 del histórico real, y el costo
        // se disparaba hasta volver absurdo el informe de finanzas.
        $tandas = mt_rand(1, 100) <= 70 ? 1 : 2;
        if ((int) round($tandas * $prod['por_tanda']) > 0) {
            $delDia[] = ['producto' => $prod, 'tandas' => $tandas];
        }
    }
    $plan[$dia->format('Y-m-d')] = $delDia;
}

// Cuánto va a pedir cada insumo en todo el periodo, según las recetas.
$demanda = [];
foreach ($plan as $delDia) {
    foreach ($delDia as $item) {
        foreach ($item['producto']['ingredientes'] as $ing) {
            $id_ins = (int) $ing['id_insumo'];
            $demanda[$id_ins] = ($demanda[$id_ins] ?? 0.0)
                + ((float) $ing['cant_por_unidad'] * $item['tandas']);
        }
    }
}

// ── Compras ─────────────────────────────────────────────────────────────────
//
// Se compra el 115% de la demanda prevista: sobra despensa, como en una
// panadería real, y el FIFO nunca se queda sin lotes. El 55% entra el primer
// día y el resto en reposiciones semanales, para que el inventario se mueva.

// Se parte de los precios reales del histórico; los que falten se estiman.
$precios_insumo = preciosHistoricos($pdo);
$dia_cero = $inicio;
while ((int) $dia_cero->format('w') === 0) {
    $dia_cero = $dia_cero->modify('+1 day');
}

/** Registra una compra que cubra `$cantidad` del insumo y devuelve el id. */
$comprar = function (array $ins, float $cantidad, string $fecha_hora) use (
    $modelo_compra, $proveedores, $id_usuario, &$precios_insumo
): int {
    $id_ins = (int) $ins['id_insumo'];
    $unidad = (string) $ins['unidad_medida'];

    if (!isset($precios_insumo[$id_ins])) {
        $precios_insumo[$id_ins] = precioBase($unidad);
    }

    // Se reparte en bultos de tamaño verosímil según la unidad de medida.
    $tam_bulto = in_array(strtolower($unidad), ['g', 'ml'], true) ? 1000.0 : 25.0;
    $bultos    = max(1, (int) ceil($cantidad / $tam_bulto));
    $cantidad_real = $tam_bulto * $bultos;

    $r = $modelo_compra->registrarCompra(
        $id_ins,
        (int) alAzar($proveedores)['id_proveedor'],
        $fecha_hora,
        $cantidad_real,
        $bultos,
        round($precios_insumo[$id_ins] * $tam_bulto, 2),
        $id_usuario
    );

    return (int) $r['id_compra'];
};

echo "Despensa inicial (" . $dia_cero->format('d/m/Y') . ")...\n";
$por_id = [];
foreach ($insumos as $ins) {
    $por_id[(int) $ins['id_insumo']] = $ins;
}
foreach ($demanda as $id_ins => $total) {
    if (!isset($por_id[$id_ins])) {
        continue;
    }
    $creados['compra'][] = $comprar($por_id[$id_ins], $total * 1.15 * 0.55, $dia_cero->format('Y-m-d H:i:s'));
}
echo "  " . count($creados['compra']) . " compras iniciales.\n\n";

$resumen = ['compras' => count($creados['compra']), 'producciones' => 0, 'ventas' => 0, 'gastos' => 0];

// El 45% restante se reparte en cuatro reposiciones semanales.
$reposiciones = [];
foreach ([7, 13, 19, 25] as $offset) {
    $dia_rep = $inicio->modify("+{$offset} days");
    while ((int) $dia_rep->format('w') === 0) {
        $dia_rep = $dia_rep->modify('+1 day');
    }
    $reposiciones[$dia_rep->format('Y-m-d')] = true;
}

foreach ($plan as $fecha => $produccion_del_dia) {
    $dia = new DateTimeImmutable($fecha);

    // ── Reposición semanal, con el precio moviéndose para que el historial de
    //    variaciones y las alertas del tablero tengan algo que contar.
    if (isset($reposiciones[$fecha])) {
        foreach ($demanda as $id_ins => $total) {
            if (!isset($por_id[$id_ins])) {
                continue;
            }
            $variacion = mt_rand(-8, 12) / 100;
            $precios_insumo[$id_ins] = round(max(1.0, ($precios_insumo[$id_ins] ?? 1.0) * (1 + $variacion)), 2);

            $creados['compra'][] = $comprar(
                $por_id[$id_ins],
                $total * 1.15 * 0.45 / 4,
                $fecha . ' 07:' . str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00'
            );
            $resumen['compras']++;
        }
    }

    // ── Producción del día, según el plan. Se reparten las unidades entre las
    //    categorías de precio, que es lo que luego permite vender.
    $disponible_por_categoria = [];
    foreach ($produccion_del_dia as $item) {
        $prod     = $item['producto'];
        $tandas   = $item['tandas'];
        $unidades = (int) round($tandas * $prod['por_tanda']);

        // Reparto entre 2 categorías: la mayoría al precio bajo, el resto arriba.
        $cat_baja = $categorias[0];
        $cat_alta = $categorias[min(2, count($categorias) - 1)];
        $en_alta  = (int) floor($unidades * (mt_rand(15, 35) / 100));
        $dist = [
            (int) $cat_baja['id_categoria'] => $unidades - $en_alta,
            (int) $cat_alta['id_categoria'] => $en_alta,
        ];

        $r = $modelo_produccion->registrarProduccionConConsumos(
            $prod['id_producto'],
            $prod['id_receta'],
            $id_usuario,
            $tandas,
            $unidades,
            $fecha . ' 05:' . str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00',
            '',
            $prod['ingredientes'],
            $dist,
            true // no abortar si un insumo se quedó corto; el modelo lo deja anotado
        );
        $creados['produccion'][] = (int) $r['id_produccion'];
        $resumen['producciones']++;

        foreach ($dist as $id_cat => $und) {
            $disponible_por_categoria[$id_cat] = ($disponible_por_categoria[$id_cat] ?? 0) + $und;
        }
    }

    // ── Ventas: nunca más de lo producido ese día en esa categoría, para que el
    //    stock diario (producción menos ventas) no se vaya a negativo.
    foreach ($disponible_por_categoria as $id_cat => $disponible) {
        $precio = 0.0;
        foreach ($categorias as $c) {
            if ((int) $c['id_categoria'] === (int) $id_cat) {
                $precio = (float) $c['precio_unitario'];
            }
        }

        // Se vende hasta agotar el objetivo del día, no un número fijo de
        // tickets: con un tope de 7 ventas de 25 unidades nunca se alcanzaba lo
        // producido, y el informe mostraba una panadería que compra y no vende.
        $por_vender = (int) floor($disponible * (mt_rand(75, 95) / 100));
        $guarda = 0;

        while ($por_vender > 0 && $guarda++ < 40) {
            $cantidad = min($por_vender, mt_rand(4, 30));
            $por_vender -= $cantidad;

            // Una de cada diez salidas es consumo interno, no venta.
            $es_consumo = mt_rand(1, 10) === 1;
            // Las tiendas reciben 20% en producto: paga 10, recibe 12.
            $a_tienda   = !$es_consumo && $tiendas !== [] && mt_rand(1, 4) === 1;
            $id_cliente = $a_tienda ? (int) alAzar($tiendas)['id_cliente'] : null;
            $bonif      = $a_tienda ? (int) floor($cantidad * 0.2) : 0;

            $ok = $modelo_venta->registrarVentaRapida(
                (int) $id_cat,
                $es_consumo ? 'consumo_interno' : 'venta',
                $id_cliente,
                $id_usuario,
                $cantidad,
                $precio,
                $es_consumo ? 0.0 : round($cantidad * $precio, 2),
                $bonif
            );
            if (!$ok) {
                continue;
            }

            // El modelo escribe NOW(); se corrige solo la fecha, nada más.
            $id_venta = (int) $pdo->lastInsertId();
            $pdo->prepare("UPDATE venta SET fecha_hora = ? WHERE id_venta = ?")
                ->execute([$fecha . ' ' . str_pad((string) mt_rand(8, 18), 2, '0', STR_PAD_LEFT) . ':' . str_pad((string) mt_rand(0, 59), 2, '0', STR_PAD_LEFT) . ':00', $id_venta]);

            $creados['venta'][] = $id_venta;
            $resumen['ventas']++;
        }
    }

    // ── Gastos: con calendario, no al azar. Un negocio real paga el arriendo el
    //    día 1 y los servicios cada semana; dejarlo a la suerte producía meses
    //    con dos gastos sueltos y un informe de finanzas sin nada que decir.
    $gastos_del_dia = [];
    if ((int) $dia->format('d') === 1) {
        $gastos_del_dia[] = ['otro', 'Arriendo del local', 900000.0];
        $gastos_del_dia[] = ['servicio', 'Energía eléctrica', (float) mt_rand(90000, 180000)];
        $gastos_del_dia[] = ['servicio', 'Gas del horno', (float) mt_rand(120000, 260000)];
    }
    if ((int) $dia->format('N') === 5) { // viernes: cierre de semana
        $gastos_del_dia[] = alAzar([
            ['otro', 'Bolsas y empaques', (float) mt_rand(30000, 80000)],
            ['otro', 'Aseo y desinfección', (float) mt_rand(25000, 60000)],
            ['servicio', 'Acueducto y alcantarillado', (float) mt_rand(40000, 90000)],
            ['otro', 'Mantenimiento de equipos', (float) mt_rand(60000, 200000)],
        ]);
    }

    foreach ($gastos_del_dia as [$categoria, $descripcion, $valor]) {
        if (!$modelo_gasto->registrarGasto($id_usuario, $categoria, $descripcion, $valor)) {
            continue;
        }
        // El modelo escribe NOW(); se corrige solo la fecha.
        $id_gasto = (int) $pdo->lastInsertId();
        $pdo->prepare("UPDATE gasto SET fecha_gasto = ? WHERE id_gasto = ?")
            ->execute([$fecha . ' 18:00:00', $id_gasto]);
        $creados['gasto'][] = $id_gasto;
        $resumen['gastos']++;
    }
}

// ── Manifiesto para poder deshacerlo ────────────────────────────────────────

// Los lotes no se crean directamente: los genera registrarCompra a partir de la
// compra. Se anotan aquí para que el borrado sepa qué tocar.
if ($creados['compra'] !== []) {
    $marcas = implode(',', array_fill(0, count($creados['compra']), '?'));
    $stmt = $pdo->prepare("SELECT id_lote FROM lote WHERE id_compra IN ($marcas)");
    $stmt->execute($creados['compra']);
    $creados['lote'] = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

file_put_contents(MANIFIESTO, json_encode([
    'generado'     => date('c'),
    'base'         => $base,
    'semilla'      => $semilla,
    'ids'           => $creados,
    'stock_previo'  => $stock_previo,
    'lotes_previos' => $lotes_previos,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo "\nListo.\n";
echo "  Compras      : {$resumen['compras']}\n";
echo "  Producciones : {$resumen['producciones']}\n";
echo "  Ventas       : {$resumen['ventas']}\n";
echo "  Gastos       : {$resumen['gastos']}\n";
echo "  Lotes creados: " . count($creados['lote']) . "\n\n";
echo "Manifiesto: " . MANIFIESTO . "\n";
echo "Para deshacerlo: php scripts/borrar_datos_demo.php --confirmar\n";
