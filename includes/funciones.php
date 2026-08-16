<?php
// ============================================================
//  FUNCIONES AUXILIARES GENERALES
//  Archivo: includes/funciones.php
// ============================================================

// Formatear número como moneda colombiana
function formatoPeso(float $valor): string {
    return '$ ' . number_format($valor, 0, ',', '.');
}

// Formatear número con decimales
function formatoDecimal(float $valor, int $decimales = 2): string {
    return number_format($valor, $decimales, ',', '.');
}

// Formatear número eliminando ceros innecesarios (12.000 → 12, 2.500 → 2,5)
function formatoInteligente(float $valor): string {
    if ($valor == floor($valor)) {
        return number_format($valor, 0, ',', '.');
    }
    $texto = rtrim(number_format($valor, 3, ',', '.'), '0');
    return rtrim($texto, ',');
}

// Sanitizar entrada del usuario
function limpiar(string $dato): string {
    return htmlspecialchars(strip_tags(trim($dato)), ENT_QUOTES, 'UTF-8');
}

/**
 * Valor de texto de un campo del formulario.
 *
 * Un formulario manipulado puede enviar `campo[]` y convertir el valor en un
 * array: leer `$_POST['campo'] ?? ''` y tratarlo como texto revienta o se
 * comporta de forma imprevista. Esta función devuelve siempre una cadena, y
 * cadena vacía si llegó cualquier otra cosa.
 */
function post_texto(string $clave): string {
    $valor = $_POST[$clave] ?? '';
    return is_string($valor) ? $valor : '';
}

// Redirigir con mensaje en sesión (nunca retorna: termina con exit)
function redirigir(string $url, string $tipo = 'exito', string $mensaje = ''): never {
    if ($mensaje) {
        $_SESSION['mensaje_tipo']  = $tipo;   // 'exito', 'error', 'alerta'
        $_SESSION['mensaje_texto'] = $mensaje;
    }
    header("Location: $url");
    exit;
}

/**
 * Devuelve el mensaje flash guardado por redirigir(), y lo consume.
 *
 * Se invoca una sola vez, en views/layouts/header.php, de modo que cualquier
 * pantalla del back-office lo muestre sin tener que acordarse de nada.
 *
 * Durante mucho tiempo esta función existió pero **ninguna vista la llamaba**:
 * los 11 avisos que el sistema guardaba al redirigir ("Insumo creado",
 * "Proveedor desactivado", "Producción registrada") se escribían en la sesión
 * y se descartaban sin llegar nunca a la pantalla. La acción sí ocurría, pero
 * el usuario no recibía confirmación de nada.
 *
 * El marcado lleva sus estilos incrustados a propósito: cada vista del
 * back-office define sus propias clases de mensaje, así que depender de ellas
 * haría que el aviso se viera bien en unas pantallas y roto en otras.
 */
function mostrarMensaje(): string {
    if (!isset($_SESSION['mensaje_texto'])) {
        return '';
    }

    $tipo    = $_SESSION['mensaje_tipo']  ?? 'exito';
    $mensaje = $_SESSION['mensaje_texto'] ?? '';
    unset($_SESSION['mensaje_tipo'], $_SESSION['mensaje_texto']);

    if (!is_string($mensaje) || trim($mensaje) === '') {
        return '';
    }

    $estilos = [
        'exito'  => ['#e8f5e9', '#a5d6a7', '#1b5e20', 'bi-check-circle-fill'],
        'error'  => ['#ffebee', '#ef9a9a', '#b71c1c', 'bi-exclamation-triangle-fill'],
        'alerta' => ['#fff8e1', '#ffe082', '#8d6e00', 'bi-info-circle-fill'],
    ];
    [$fondo, $borde, $texto, $icono] = $estilos[is_string($tipo) ? $tipo : 'exito']
        ?? $estilos['exito'];

    // El mensaje puede traer <strong> con el nombre del insumo o la tienda, así
    // que no se escapa aquí: lo componen los controladores, no el usuario.
    return '<div role="status" style="max-width:1000px;margin:1rem auto 0;padding:.75rem 1rem;'
         . 'border-radius:10px;font-size:.88rem;font-weight:600;display:flex;align-items:center;'
         . 'gap:.5rem;background:' . $fondo . ';border:1px solid ' . $borde . ';'
         . 'border-left:3px solid ' . $texto . ';color:' . $texto . ';">'
         . '<i class="bi ' . $icono . '"></i><span>' . $mensaje . '</span></div>';
}

// Verificar si hoy es domingo (no se generan órdenes de compra)
function esHoyDomingo(): bool {
    return date('w') === '0';
}

// Verificar si hoy es sábado
function esHoySabado(): bool {
    return date('w') === '6';
}

// Obtener configuración del sistema
/** @return array<mixed> */
function getConfiguracion(): array {
    static $config = null;
    if ($config === null) {
        $pdo    = getConexion();
        $stmt   = $pdo->query("SELECT * FROM configuracion LIMIT 1");
        // fetch() devuelve false (no null) si la tabla esta vacia; ?? no lo capturaba (F3).
        $fila   = $stmt->fetch();
        $config = is_array($fila) ? $fila : [];
    }
    return $config;
}

// Generar número de lote único
// Formato: INS-2026-02-25-001
function generarNumeroLote(string $prefijo): string {
    $pre3   = strtoupper(substr($prefijo, 0, 3));
    $fecha  = date('Y-m-d');
    $patron = $pre3 . '-' . $fecha . '-%';
    $pdo    = getConexion();
    $stmt   = $pdo->prepare(
        "SELECT numero_lote FROM lote WHERE numero_lote LIKE ? ORDER BY numero_lote DESC LIMIT 1"
    );
    $stmt->execute([$patron]);
    $ultimo = $stmt->fetchColumn();
    if ($ultimo) {
        $partes = explode('-', (string) $ultimo);
        $seq = (int)end($partes) + 1;
    } else {
        $seq = 1;
    }
    return $pre3 . '-' . $fecha . '-' . str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
}

// Calcular porcentaje de variación entre dos precios
function calcularVariacion(float $precioAnterior, float $precioNuevo): float {
    if ($precioAnterior == 0) return 0;
    return round((($precioNuevo - $precioAnterior) / $precioAnterior) * 100, 2);
}

// ============================================================
//  STOCK DE PRODUCTO TERMINADO
//  El stock se calcula como:
//  total producido (todas las producciones) - total vendido (todas las ventas)
//  No existe columna stock_actual en la tabla producto; se computa en tiempo real.
// ============================================================

/**
 * Retorna las unidades disponibles HOY de un producto terminado.
 * Disponible = SUM(unidades_producidas hoy) - SUM(unidades_vendidas hoy)
 * El stock es diario: cada día se produce y se vende desde cero.
 */
function getStockProducto(int $id_producto): float {
    $pdo  = getConexion();
    $stmt = $pdo->prepare("SELECT stock_actual FROM v_stock_productos_hoy WHERE id_producto = ?");
    $stmt->execute([$id_producto]);
    return (float) ($stmt->fetchColumn() ?: 0);
}

/**
 * Valida si hay stock suficiente para registrar una venta.
 *
 * Retorna un array:
 *   ['ok' => true]  → hay suficiente stock
 *   ['ok' => false, 'mensaje' => '...', 'disponible' => N]  → no hay stock
 * @return array<mixed>
 */
function validarStockVenta(int $id_producto, int $cantidad): array {
    $disponible = getStockProducto($id_producto);

    if ($cantidad <= 0) {
        return [
            'ok'         => false,
            'mensaje'    => 'La cantidad a vender debe ser mayor a 0.',
            'disponible' => $disponible,
        ];
    }

    if ($cantidad > $disponible) {
        $disp_fmt = number_format($disponible, 0, ',', '.');
        return [
            'ok'         => false,
            'mensaje'    => "No hay suficiente stock. Solicitaste {$cantidad} unidad(es), pero solo hay <strong>{$disp_fmt}</strong> disponible(s).",
            'disponible' => $disponible,
        ];
    }

    return ['ok' => true, 'disponible' => $disponible];
}

/**
 * Formatea la fecha de entrega de un pedido de forma amigable.
 * Si el año es menor o igual a 1970 (por ejemplo, 1000-01-01), significa "Por definir".
 */
function formatearFechaEntrega(?string $fecha_entrega, bool $html = true): string {
    if ($fecha_entrega === null || trim($fecha_entrega) === '') {
        $fecha_entrega = '1000-01-01 00:00:00';
    }
    $yr = (int)date('Y', (int) strtotime($fecha_entrega));
    if ($yr <= 1970) {
        return $html 
            ? '<span style="color:#c62828; font-weight:700;"><i class="bi bi-clock-history"></i> Por definir (Tienda ADSO)</span>'
            : 'Por definir (Tienda ADSO)';
    }
    return date('H:i', (int) strtotime($fecha_entrega)) !== '00:00' 
        ? date('d/m/Y h:i A', (int) strtotime($fecha_entrega)) 
        : date('d/m/Y', (int) strtotime($fecha_entrega));
}