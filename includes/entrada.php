<?php
// ============================================================
//  NORMALIZACIÓN DE LA ENTRADA ($_GET / $_POST)
//  Archivo: includes/entrada.php
//
//  PHP convierte `campo[]=1` en un array sin preguntar. El código del proyecto
//  lee cientos de campos como `$_GET['estado'] ?? ''` y los trata como texto,
//  así que basta con escribir `?estado[]=1` en la barra de direcciones para que
//  trim(), htmlspecialchars() o json_decode() lancen un TypeError y la pantalla
//  termine en «Ocurrió un error inesperado». Es exactamente lo que pasa cuando
//  alguien prueba la aplicación tocando lo que no debe.
//
//  Corregirlo campo por campo dejaría fuera el siguiente que se escriba. Aquí se
//  corrige una sola vez, antes de que ningún controlador mire la petición:
//
//    - Un campo que NO es una lista y llega como array se descarta, como si no
//      se hubiera enviado. El controlador aplica su valor por defecto.
//    - Un campo que SÍ es una lista (los `name="ids[]"` de los formularios) y
//      llega como texto suelto se convierte en una lista de un elemento. Dentro
//      de la lista solo se conservan valores de texto: `ids[][]=1` no pasa.
//
//  Las listas se declaran por pantalla porque un mismo nombre cambia de forma
//  según el formulario: `cantidad` es una lista en la receta y un número en la
//  compra.
// ============================================================

/**
 * Campos que llegan como lista, por script de entrada (ruta desde la raíz).
 *
 * Al añadir a un formulario un campo con `name="algo[]"` hay que declararlo
 * aquí; si no, el campo se descartará y el formulario no hará nada.
 *
 * @var array<string, list<string>>
 */
const ENTRADA_LISTAS_POR_SCRIPT = [
    'modules/recetas/editar_receta.php'     => ['id_insumo', 'cantidad', 'notas', 'aplica_merma'],
    'modules/compras/proveedores.php'       => ['dias_visita'],
    'modules/produccion/nueva_produccion.php' => ['dist'],
    'modules/produccion/registrar.php'      => ['dist'],
    'modules/pedidos_clientes/index.php'    => ['exportar_ids', 'ids_pedidos'],
    'modules/pedidos_clientes/exportar.php' => ['exportar_ids'],
    'modules/ventas/exportar_excel.php'     => ['exportar_ids'],
    'modules/inventario/index.php'          => ['ids_eliminar'],
    'portal/dashboard.php'                  => ['aprobar_lote_ids', 'rechazar_lote_ids', 'ids_aprobar'],
    'portal/exportar_pedidos_dashboard.php' => ['ids'],
];

/**
 * Deja $_GET, $_POST y $_REQUEST con la forma que el código espera.
 *
 * @param list<string> $listas campos que esta pantalla recibe como lista
 */
function normalizar_entrada(array $listas): void {
    $_GET  = entrada_normalizada($_GET, $listas);
    $_POST = entrada_normalizada($_POST, $listas);
    $_REQUEST = array_merge($_GET, $_POST);
}

/**
 * @param array<mixed> $datos
 * @param list<string> $listas
 * @return array<string, string|array<string>>
 */
function entrada_normalizada(array $datos, array $listas): array {
    $limpio = [];
    foreach ($datos as $clave => $valor) {
        $clave = (string) $clave;
        if (in_array($clave, $listas, true)) {
            if (is_string($valor)) {
                $limpio[$clave] = [entrada_sin_nulos($valor)];
            } elseif (is_array($valor)) {
                // Tope de elementos: una lista de cien mil ids rebasaría el
                // máximo de parámetros de una consulta preparada.
                $valor = array_slice(array_filter($valor, 'is_string'), 0, 1000, true);
                $limpio[$clave] = array_map('entrada_sin_nulos', $valor);
            }
            continue;
        }
        if (is_string($valor)) {
            $limpio[$clave] = entrada_sin_nulos($valor);
        }
    }
    return $limpio;
}

/**
 * Quita los bytes nulos. Ningún formulario los envía, y varias funciones de
 * PHP (fechas, rutas de archivo) lanzan ValueError al encontrarlos.
 */
function entrada_sin_nulos(string $valor): string {
    return str_replace("\0", '', $valor);
}

/**
 * Script de entrada de la petición, como ruta relativa a la raíz del proyecto.
 */
function entrada_script_actual(): string {
    $archivo = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $real    = is_string($archivo) ? realpath($archivo) : false;
    $raiz    = realpath(__DIR__ . '/..');
    if ($real === false || $raiz === false || !str_starts_with($real, $raiz)) {
        return '';
    }
    return ltrim(str_replace('\\', '/', substr($real, strlen($raiz))), '/');
}

if (PHP_SAPI !== 'cli') {
    normalizar_entrada(ENTRADA_LISTAS_POR_SCRIPT[entrada_script_actual()] ?? []);
}
