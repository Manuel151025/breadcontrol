<?php
// controllers/VentaController.php

require_once __DIR__ . '/../models/VentaModel.php';

class VentaController {
    private $model;
    private $pdo; // for quick internal queries if needed, though we route through Model

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new VentaModel($pdo);
    }

    /**
     * Dashboard del Punto de Venta POS (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        // ── 1. Manejo de peticiones AJAX (retornan JSON y terminan flujo) ─────────
        if (isset($_GET['ajax_detalle_venta'])) {
            header('Content-Type: application/json');
            try {
                $id_v = (int)$_GET['id_venta'];
                $res = $this->model->getDetalleVentaAjax($id_v);
                echo json_encode($res);
            } catch (Exception $e) {
                echo json_encode(['items' => [], 'id_cliente' => 0]);
            }
            exit;
        }

        if (isset($_GET['ajax_all_variedades'])) {
            header('Content-Type: application/json');
            try {
                $all = $this->model->getAllVariedadesAjax();
                echo json_encode($all);
            } catch (Exception $e) {
                echo json_encode([]);
            }
            exit;
        }

        if (isset($_GET['ajax_variedades'])) {
            header('Content-Type: application/json');
            try {
                $id_cat = (int)$_GET['id_cat'];
                $vars = $this->model->getVariedadesPorCategoriaAjax($id_cat);
                echo json_encode($vars);
            } catch (Exception $e) {
                echo json_encode([]);
            }
            exit;
        }

        // ── 2. Mensajes ──────────────────────────────────────────────────────────
        $msg_ok  = $_GET['msg_ok']  ?? '';
        $msg_err = $_GET['msg_err'] ?? '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        // ── 3. POST — Registrar Venta Rápida (guardar_venta) ─────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_venta'])) {
            $id_cat        = (int)($_POST['id_categoria'] ?? 0);
            $cantidad      = (int)($_POST['cantidad'] ?? 0);
            $tipo_salida   = $_POST['tipo_salida'] ?? 'venta';
            $id_cliente    = (int)($_POST['id_cliente'] ?? 0);
            $dar_napa      = isset($_POST['dar_napa']) && $_POST['dar_napa'] === '1';
            $napa_cant     = max(0, (int)($_POST['napa_cantidad'] ?? 0));
            $precio_custom = (float)($_POST['precio_custom'] ?? 0);

            if (!in_array($tipo_salida, ['venta', 'consumo_interno'])) {
                $tipo_salida = 'venta';
            }

            if (!$id_cat && $precio_custom <= 0) {
                $msg_err = 'Selecciona una categoría de precio.';
            } elseif (!$id_cat && $precio_custom > 20000) {
                $msg_err = 'El precio personalizado no puede superar los $20.000.';
            } elseif ($cantidad <= 0) {
                $msg_err = 'La cantidad debe ser mayor a 0.';
            } elseif ($cantidad > 999) {
                $msg_err = 'La cantidad máxima permitida es 999.';
            } else {
                // Obtener datos de categoría
                $cat_data = null;
                if ($id_cat) {
                    $stmt_cat = $this->pdo->prepare("SELECT nombre, precio_unitario FROM categoria_precio WHERE id_categoria = ?");
                    $stmt_cat->execute([$id_cat]);
                    $cat_data = $stmt_cat->fetch();
                } else if ($precio_custom > 0) {
                    $cat_data = [
                        'nombre'          => 'Precio personalizado $' . number_format($precio_custom, 0, ',', '.'),
                        'precio_unitario' => $precio_custom
                    ];
                }

                if (!$cat_data) {
                    $msg_err = 'Categoría no encontrada.';
                } else {
                    $precio = (float)$cat_data['precio_unitario'];
                    $total  = ($tipo_salida === 'venta') ? round($precio * $cantidad, 2) : 0;

                    // Validar stock disponible (stock del día: producido hoy - vendido hoy,
                    // considerando también lo vendido vía pedido detallado)
                    $stock_disponible = 9999;
                    if ($id_cat) {
                        $stock_disponible = $this->model->getStockDisponibleHoy($id_cat);
                    }

                    // Calcular Bonificación / Ñapa
                    $bonificacion = 0;
                    $napa = 0;
                    $und_fisicas = $cantidad;
                    $total_venta_tmp = $precio * $cantidad;

                    if ($tipo_salida === 'venta' && $id_cliente > 0) {
                        $tc = $this->pdo->prepare("SELECT tipo FROM cliente WHERE id_cliente = ?");
                        $tc->execute([$id_cliente]);
                        if ($tc->fetchColumn() === 'tienda') {
                            // TIENDA: $1.000 de crédito por cada $5.000
                            $credito = floor($total_venta_tmp / 5000) * 1000;
                            $bonificacion = ($precio > 0) ? (int)floor($credito / $precio) : 0;
                            $und_fisicas = $cantidad + $bonificacion;
                        }
                    } elseif ($tipo_salida === 'venta' && $id_cliente == 0) {
                        // MOSTRADOR: $500 de crédito por cada $5.000
                        $credito = floor($total_venta_tmp / 5000) * 500;
                        $napa = ($precio > 0) ? (int)floor($credito / $precio) : 0;
                        $und_fisicas = $cantidad + $napa;
                    }

                    if ($und_fisicas > $stock_disponible && $tipo_salida === 'venta' && $id_cat) {
                        $msg_err = "Stock insuficiente.<br>Disponible: <strong>$stock_disponible</strong>.<br>Intentas sacar: <strong>$und_fisicas</strong>.";
                    } else {
                        try {
                            $id_cli_param = ($tipo_salida === 'venta' && $id_cliente > 0) ? $id_cliente : null;
                            $this->model->registrarVentaRapida($id_cat, $tipo_salida, $id_cli_param, $user['id_usuario'], $und_fisicas, $precio, $total, $bonificacion + $napa);

                            $tipo_labels = ['venta' => 'Venta', 'bonificacion' => 'Bonificación', 'consumo_interno' => 'Consumo interno'];
                            $tipo_icons  = ['venta' => '💰', 'bonificacion' => '🎁', 'consumo_interno' => '🍞'];

                            $msg_ok = $tipo_icons[$tipo_salida] . " <strong>" . $tipo_labels[$tipo_salida] . " registrada</strong><br>$cantidad unidades de " . htmlspecialchars($cat_data['nombre']);
                            if ($bonificacion > 0) {
                                $msg_ok .= "<br>+ <strong>$bonificacion bonificadas 🏪</strong> = <strong>$und_fisicas entregadas</strong>";
                            }
                            if ($napa > 0) {
                                $msg_ok .= "<br>+ <strong>$napa de ñapa 🎁</strong> = <strong>$und_fisicas entregadas</strong>";
                            }
                            if ($tipo_salida === 'venta') {
                                $msg_ok .= "<br>Total: <strong>$" . number_format($total, 0, ',', '.') . "</strong>";
                            }

                            header('Location: index.php?msg_ok=' . urlencode($msg_ok));
                            exit;
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error al registrar la venta rápida: ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        // ── 4. POST — Registrar Pedido Detallado (guardar_pedido) ────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pedido'])) {
            $cart_json   = $_POST['carrito_json'] ?? '[]';
            $id_cliente  = (int)($_POST['ped_cliente'] ?? 0);
            $bonif_json  = $_POST['bonif_json'] ?? '[]';
            $cart        = json_decode($cart_json, true) ?: [];
            $bonif_items = json_decode($bonif_json, true) ?: [];

            if (empty($cart)) {
                $msg_err = 'El carrito está vacío.';
            } else {
                try {
                    // La validación de cantidad, precio y stock ocurre dentro del modelo
                    // (nunca se confía en los valores del carrito enviados por el cliente),
                    // que devuelve los totales realmente guardados para armar el mensaje.
                    $resultado = $this->model->registrarPedidoDetallado($id_cliente, $user['id_usuario'], $cart, $bonif_items);

                    $msg_ok = "📋 <strong>Pedido detallado registrado</strong>"
                        . "<br>" . $resultado['total_variedades'] . " variedades · " . $resultado['total_unidades'] . " unidades cobradas";
                    if ($resultado['bonus_units'] > 0) {
                        $msg_ok .= "<br>🎁 Bonificación/Ñapa: <strong>" . $resultado['bonus_units'] . "</strong> unidades de regalo";
                    }
                    $msg_ok .= "<br>Total cobrado: <strong>$" . number_format($resultado['total_dinero'], 0, ',', '.') . "</strong>";

                    header('Location: index.php?msg_ok=' . urlencode($msg_ok));
                    exit;
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al guardar el pedido: ' . $e->getMessage();
                }
            }
        }

        // ── 5. POST — Editar Pedido Detallado (editar_pedido) ────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_pedido'])) {
            $id_v         = (int)($_POST['edit_id_venta'] ?? 0);
            $cart_json    = $_POST['edit_carrito_json'] ?? '[]';
            $id_cliente   = (int)($_POST['edit_ped_cliente'] ?? 0);
            $bonif_json_e = $_POST['edit_bonif_json'] ?? '[]';

            $cart          = json_decode($cart_json, true) ?: [];
            $bonif_items_e = json_decode($bonif_json_e, true) ?: [];

            if ($id_v && !empty($cart)) {
                try {
                    // Misma revalidación de cantidad, precio y stock que al registrar.
                    $resultado = $this->model->editarPedidoDetallado($id_v, $id_cliente, $cart, $bonif_items_e);

                    $msg_ok = "📋 <strong>Pedido #$id_v actualizado</strong><br>" . $resultado['total_variedades'] . " variedades · " . $resultado['total_unidades'] . " unidades · $" . number_format($resultado['total_dinero'], 0, ',', '.');
                    if ($resultado['bonus_units'] > 0) {
                        $etiq = $id_cliente > 0 ? 'bonificación 🏪' : 'ñapa 🎁';
                        $msg_ok .= "<br>+ <strong>" . $resultado['bonus_units'] . "</strong> de $etiq";
                    }

                    header('Location: index.php?msg_ok=' . urlencode($msg_ok));
                    exit;
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al editar el pedido: ' . $e->getMessage();
                }
            }
        }

        // ── 6. POST — Editar Venta Rápida (editar_venta) ─────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_venta'])) {
            $id_v   = (int)($_POST['id_venta'] ?? 0);
            $id_cat = (int)($_POST['ev_categoria'] ?? 0);
            $cant   = (int)($_POST['ev_cantidad'] ?? 0);
            $tipo   = $_POST['ev_tipo'] ?? 'venta';
            $id_cli = (int)($_POST['ev_cliente'] ?? 0);
            $extra  = max(0, (int)($_POST['ev_extra_bonif'] ?? 0));

            if (!in_array($tipo, ['venta', 'bonificacion', 'consumo_interno'])) {
                $tipo = 'venta';
            }

            if ($id_v && $id_cat && $cant > 0) {
                try {
                    $stmt_cat = $this->pdo->prepare("SELECT precio_unitario FROM categoria_precio WHERE id_categoria = ?");
                    $stmt_cat->execute([$id_cat]);
                    $precio = (float)$stmt_cat->fetchColumn();
                    $total  = ($tipo === 'venta') ? round($precio * $cant, 2) : 0;

                    $bonif_edit = 0;
                    $und_edit   = $cant;

                    if ($tipo === 'venta') {
                        if ($id_cli > 0) {
                            $tc = $this->pdo->prepare("SELECT tipo FROM cliente WHERE id_cliente = ?");
                            $tc->execute([$id_cli]);
                            if ($tc->fetchColumn() === 'tienda') {
                                $credito = floor(($precio * $cant) / 5000) * 1000;
                                $bonif_calc = ($precio > 0) ? (int)floor($credito / $precio) : 0;
                                $bonif_edit = $bonif_calc + $extra;
                                $und_edit   = $cant + $bonif_edit;
                            }
                        } else {
                            $credito = floor(($precio * $cant) / 5000) * 500;
                            $bonif_edit = ($precio > 0) ? (int)floor($credito / $precio) : 0;
                            $und_edit   = $cant + $bonif_edit;
                        }
                    }

                    $this->model->editarVentaRapida($id_v, $id_cat, $tipo, ($tipo === 'venta' && $id_cli > 0) ? $id_cli : null, $und_edit, $precio, $total, $bonif_edit);

                    $msg_ok = "✏️ Registro <strong>#$id_v</strong> actualizado. <strong>$cant</strong> cobradas";
                    if ($bonif_edit > 0) {
                        $msg_ok .= ($id_cli > 0) ? " + <strong>$bonif_edit</strong> bonif. 🏪" : " + <strong>$bonif_edit</strong> ñapa 🎁";
                        $msg_ok .= " = <strong>$und_edit</strong> entregadas";
                    }
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al actualizar registro de venta.';
                }
            }
        }

        // ── 7. POST — Eliminar Venta Rápida (del_venta) ──────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del_venta'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                header('Location: index.php?err=csrf');
                exit;
            }
            $id_del = (int)$_POST['del_venta'];
            try {
                $this->model->eliminarVenta($id_del);
            } catch (Exception $e) {
                log_error($e);
            }
            header('Location: index.php');
            exit;
        }

        // ── 8. Cargar Datos para POS ─────────────────────────────────────────────
        $categorias         = $this->model->getCategoriasPrecio();
        $clientes           = $this->model->getClientesTienda();
        $registros_hoy      = $this->model->getVentasHoy();
        $ventas_con_detalle = $this->model->getVentasConDetalleIds();

        // ── 9. Estadísticas Rápidas (KPIs) del día ───────────────────────────────
        $total_ventas = 0;
        $und_ventas   = 0;
        $n_ventas     = 0;
        $und_bonif    = 0;
        $und_consumo  = 0;
        foreach ($registros_hoy as $r) {
            if ($r['tipo_salida'] === 'venta') {
                $total_ventas += $r['total_venta'];
                $und_ventas   += $r['unidades_vendidas'];
                $n_ventas++;
            } elseif ($r['tipo_salida'] === 'bonificacion') {
                $und_bonif++;
            } else {
                $und_consumo++;
            }
        }

        $ventas_ayer = $this->model->getVentasAyerTotal();
        $diff_pct = $ventas_ayer > 0 ? round((($total_ventas - $ventas_ayer) / $ventas_ayer) * 100, 1) : null;

        // ── 10. Renderizar layout y vista ────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/ventas/index.php';
    }

    /**
     * Gestión de clientes de tipo tienda (clientes.php)
     */
    public function clientes() {
        requerirPropietario();

        $msg_ok  = '';
        $msg_err = '';
        $editando = null;
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        // ── 1. POST — Guardar Cliente/Tienda ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_cliente'])) {
            $nombre   = trim($_POST['nombre'] ?? '');
            $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
            if (strlen($telefono) > 15) {
                $telefono = substr($telefono, 0, 15);
            }
            $notes   = trim($_POST['notas'] ?? '');
            $id_edit = (int)($_POST['id_cliente'] ?? 0);

            if (!$nombre) {
                $msg_err = 'El nombre de la tienda es obligatorio.';
            } elseif (mb_strlen($nombre) > 100) {
                $msg_err = 'El nombre de la tienda no puede superar los 100 caracteres.';
            } elseif ($id_edit) {
                try {
                    $this->model->guardarCliente($id_edit, $nombre, $telefono, $notes);
                    redirigir(APP_URL.'/modules/ventas/clientes.php', 'exito', "Tienda <strong>" . htmlspecialchars($nombre) . "</strong> actualizada.");
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al actualizar la tienda.';
                }
            } else {
                try {
                    $chk = $this->pdo->prepare("SELECT id_cliente FROM cliente WHERE nombre = ? AND activo = 1");
                    $chk->execute([$nombre]);
                    if ($chk->fetch()) {
                        $msg_err = "Ya existe una tienda con ese nombre.";
                    } else {
                        $this->model->guardarCliente(0, $nombre, $telefono, $notes);
                        $msg_ok = "Tienda <strong>" . htmlspecialchars($nombre) . "</strong> registrada correctamente.";
                    }
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al registrar la tienda.';
                }
            }
        }

        // ── 2. POST — Desactivar (soft delete) Cliente ───────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                header('Location: clientes.php?err=csrf');
                exit;
            }
            $id_del = (int)$_POST['del'];
            try {
                $this->model->desactivarCliente($id_del);
            } catch (Exception $e) {
                log_error($e);
            }
            header('Location: clientes.php');
            exit;
        }

        // ── 3. GET — Cargar datos para edición ────────────────────────────────────
        if (!empty($_GET['edit'])) {
            $id_edit = (int)$_GET['edit'];
            $editando = $this->model->getClienteById($id_edit);
        }

        // ── 4. Filtros e historial ───────────────────────────────────────────────
        $busca  = trim($_GET['q'] ?? '');
        $tiendas = $this->model->getClientesConEstadisticas($busca);

        $total_tiendas = count($tiendas);
        $total_ventas_tiendas = array_sum(array_column($tiendas, 'total_comprado'));

        // ── 5. Renderizar layouts y vistas ───────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/ventas/clientes.php';
    }

    /**
     * Módulo secundario clásica Nueva Venta por producto (nueva_venta.php)
     */
    public function nuevaVenta() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = $_SESSION['msg_ok'] ?? '';
        $msg_err = '';
        unset($_SESSION['msg_ok']);

        // ── 1. POST — Registrar Venta clásica por producto ───────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_venta'])) {
            $id_prod   = (int)($_POST['id_producto'] ?? 0);
            $id_cli    = (int)($_POST['id_cliente'] ?? 0) ?: null;
            $unidades  = (int)($_POST['unidades_vendidas'] ?? 0);
            $precio    = (float)($_POST['precio_unitario'] ?? 0);
            $sobrantes = (int)($_POST['unidades_sobrantes'] ?? 0);

            if (!$id_prod || $unidades <= 0 || $precio <= 0) {
                $msg_err = 'Completa todos los campos correctamente.';
            } elseif ($sobrantes < 0) {
                $msg_err = 'Los sobrantes no pueden ser negativos.';
            } elseif ($sobrantes > $unidades) {
                $msg_err = 'Los sobrantes no pueden ser mayores que las unidades vendidas.';
            } else {
                // Validar stock disponible
                $stock = validarStockVenta($id_prod, $unidades); // global
                if (!$stock['ok']) {
                    $msg_err = $stock['mensaje'];
                } else {
                    try {
                        $this->model->registrarVentaNueva($id_prod, $id_cli, $user['id_usuario'], $unidades, $precio, $sobrantes);
                        $total = $unidades * $precio;
                        $_SESSION['msg_ok'] = 'Venta registrada: $' . number_format($total, 0, ',', '.');
                        header('Location: nueva_venta.php');
                        exit;
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al registrar la venta. Intenta de nuevo.';
                    }
                }
            }
        }

        // ── 2. Cargar listados locales ───────────────────────────────────────────
        $productos_list = $this->model->getProductosActivosConStock();
        $clientes_list  = $this->pdo->query("SELECT id_cliente, nombre, tipo FROM cliente WHERE activo = 1 ORDER BY nombre")->fetchAll();
        $ventas_hoy     = $this->model->getVentasHoyNueva();

        $total_hoy = array_sum(array_column($ventas_hoy, 'total_venta'));
        $num_hoy   = count($ventas_hoy);

        // ── 3. Renderizar layouts y vistas ───────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/ventas/nueva_venta.php';
    }

    /**
     * Exportación de registros a Excel (.xls)
     */
    public function exportarExcel() {
        requerirPropietario();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['exportar_ids'])) {
            throw new Exception("No se seleccionaron registros para exportar.");
        }

        $ids = $_POST['exportar_ids'];

        try {
            $res = $this->model->getVentasPorIds($ids);
            $ventas             = $res['ventas'];
            $detalles_por_venta = $res['detalles_por_venta'];
        } catch (Exception $e) {
            log_error($e);
            $ventas             = [];
            $detalles_por_venta = [];
        }

        // Cabeceras HTTP para descarga directa del archivo de Excel
        header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
        header("Content-Disposition: attachment; filename=ventas_" . date('Ymd_His') . ".xls");
        header("Pragma: no-cache");
        header("Expires: 0");

        echo "\xEF\xBB\xBF"; // UTF-8 BOM

        // Carga la plantilla de Excel
        require_once __DIR__ . '/../views/ventas/exportar_excel.php';
    }
}
