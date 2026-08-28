<?php
/**
 * ============================================================
 *  Verificacion de cobertura de pruebas — BreadControl
 *
 *  Lee el informe clover que produce PHPUnit, publica el desglose por capa y
 *  falla si la cobertura global baja del minimo acordado.
 *
 *  Por que un script y no una opcion de PHPUnit: PHPUnit no trae un umbral
 *  minimo por linea de comandos. Y el numero global por si solo dice poco —lo
 *  util es ver que capa lo sostiene y cual no—, asi que ademas del corte
 *  imprime el reparto y los archivos peor cubiertos, que es por donde hay que
 *  empezar a escribir pruebas.
 *
 *  Uso:
 *    php scripts/verificar_cobertura.php RUTA_CLOVER.xml [MINIMO_GLOBAL]
 *
 *  Sin MINIMO_GLOBAL solo informa y sale con 0: util para medir por primera
 *  vez, cuando todavia no se sabe que numero es razonable exigir.
 * ============================================================
 */

declare(strict_types=1);

$ruta   = $argv[1] ?? '';
$minimo = isset($argv[2]) ? (float) $argv[2] : null;

if ($ruta === '' || !is_file($ruta)) {
    fwrite(STDERR, "Uso: php scripts/verificar_cobertura.php RUTA_CLOVER.xml [MINIMO_GLOBAL]\n");
    fwrite(STDERR, "No se encontro el informe: {$ruta}\n");
    exit(2);
}

$xml = @simplexml_load_file($ruta);
if ($xml === false) {
    fwrite(STDERR, "El informe clover no se pudo leer como XML: {$ruta}\n");
    exit(2);
}

/**
 * Cobertura por archivo. Se cuentan sentencias (statements), no lineas del
 * archivo: es lo que mide clover y lo que tiene sentido comparar.
 *
 * @var array<string, array{total: int, cubiertas: int}> $porArchivo
 */
$porArchivo = [];

foreach ($xml->xpath('//file') ?: [] as $archivo) {
    $nombre = (string) $archivo['name'];
    $m      = $archivo->metrics;
    if ($m === null) {
        continue;
    }
    $total     = (int) $m['statements'];
    $cubiertas = (int) $m['coveredstatements'];

    // Ruta relativa y con barras normales, para que el informe se lea igual
    // en Windows y en el runner de Linux.
    $nombre = str_replace('\\', '/', $nombre);
    $pos    = strpos($nombre, '/panaderia/');
    if ($pos !== false) {
        $nombre = substr($nombre, $pos + strlen('/panaderia/'));
    }

    $porArchivo[$nombre] = ['total' => $total, 'cubiertas' => $cubiertas];
}

if ($porArchivo === []) {
    fwrite(STDERR, "El informe no contiene ningun archivo: ¿se genero la cobertura de verdad?\n");
    exit(2);
}

// ── Reparto por capa ─────────────────────────────────────────
/** @var array<string, array{total: int, cubiertas: int, archivos: int}> $porCapa */
$porCapa = [];
$totalGlobal = 0;
$cubiertasGlobal = 0;

foreach ($porArchivo as $nombre => $datos) {
    $capa = explode('/', $nombre)[0];
    if (!isset($porCapa[$capa])) {
        $porCapa[$capa] = ['total' => 0, 'cubiertas' => 0, 'archivos' => 0];
    }
    $porCapa[$capa]['total']     += $datos['total'];
    $porCapa[$capa]['cubiertas'] += $datos['cubiertas'];
    $porCapa[$capa]['archivos']++;

    $totalGlobal     += $datos['total'];
    $cubiertasGlobal += $datos['cubiertas'];
}

$pct = static fn (int $cubiertas, int $total): float => $total === 0 ? 100.0 : ($cubiertas / $total) * 100;

$global = $pct($cubiertasGlobal, $totalGlobal);

echo "\n";
echo "COBERTURA POR CAPA\n";
echo str_repeat('-', 58) . "\n";
printf("%-16s %8s %10s %9s %8s\n", 'Capa', 'Archivos', 'Sentencias', 'Cubiertas', '%');
echo str_repeat('-', 58) . "\n";

ksort($porCapa);
foreach ($porCapa as $capa => $d) {
    printf(
        "%-16s %8d %10d %9d %7.1f%%\n",
        $capa,
        $d['archivos'],
        $d['total'],
        $d['cubiertas'],
        $pct($d['cubiertas'], $d['total'])
    );
}
echo str_repeat('-', 58) . "\n";
printf("%-16s %8d %10d %9d %7.1f%%\n", 'GLOBAL', count($porArchivo), $totalGlobal, $cubiertasGlobal, $global);
echo "\n";

// ── Los peor cubiertos, que es por donde se empieza ──────────
$candidatos = array_filter($porArchivo, static fn (array $d): bool => $d['total'] >= 20);
uasort(
    $candidatos,
    static function (array $a, array $b): int {
        $pa = $a['total'] === 0 ? 100 : $a['cubiertas'] / $a['total'];
        $pb = $b['total'] === 0 ? 100 : $b['cubiertas'] / $b['total'];
        if ($pa === $pb) {
            return $b['total'] <=> $a['total'];   // a igual %, primero el mas grande
        }
        return $pa <=> $pb;
    }
);

echo "DIEZ ARCHIVOS PEOR CUBIERTOS (de 20 sentencias o mas)\n";
echo str_repeat('-', 58) . "\n";
$i = 0;
foreach ($candidatos as $nombre => $d) {
    if ($i++ >= 10) {
        break;
    }
    printf("%6.1f%%  %5d sent.  %s\n", $pct($d['cubiertas'], $d['total']), $d['total'], $nombre);
}
echo "\n";

// ── Corte ────────────────────────────────────────────────────
if ($minimo === null) {
    printf("Cobertura global: %.2f%% (solo informativo: no se paso un minimo)\n", $global);
    exit(0);
}

if ($global + 0.005 < $minimo) {
    printf("FALLO: la cobertura global es %.2f%% y el minimo exigido es %.2f%%\n", $global, $minimo);
    echo "Si la bajada es intencionada, ajusta el minimo en el flujo de CI y di por que.\n";
    exit(1);
}

printf("Cobertura global: %.2f%% (minimo exigido: %.2f%%)\n", $global, $minimo);
exit(0);
