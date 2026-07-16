<?php
// controllers/PedidoClienteController.php

require_once __DIR__ . '/../models/PedidoClienteModel.php';

class PedidoClienteController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new PedidoClienteModel($pdo);
    }

    /**
     * Dashboard principal de pedidos (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok_bulk  = '';
        $msg_err_bulk = '';
        $msg_cobro_ok  = '';
        $msg_cobro_err = '';

        // ── 1. Acción Masiva: Cambiar Estado ─────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado_lote') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_err_bulk = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                $ids        = array_values(array_filter(array_map('intval', $_POST['exportar_ids'] ?? []), fn($v) => $v > 0));
                $nuevo_est  = $_POST['nuevo_estado'] ?? '';
                $estados_ok = ['pendiente', 'confirmado', 'rechazado'];
                if (empty($ids)) {
                    $msg_err_bulk = 'Selecciona al menos un pedido.';
                } elseif (!in_array($nuevo_est, $estados_ok, true)) {
                    $msg_err_bulk = 'Estado no válido.';
                } else {
                    try {
                        $n = $this->model->cambiarEstadoLote($ids, $nuevo_est);
                        $labels = ['pendiente' => 'pendientes', 'confirmado' => 'confirmados', 'rechazado' => 'rechazados'];
                        $msg_ok_bulk = $n > 0 ? "$n pedido(s) marcados como <strong>{$labels[$nuevo_est]}</strong>." : "Sin cambios (ya tenían ese estado).";
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err_bulk = 'Error al cambiar de estado.';
                    }
                }
            }
        }

        // ── 2. Acción Masiva: Confirmar Cobro Tienda (Nequi manual) ──────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar_cobro_tienda') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_cobro_err = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                $ids_pedidos = array_values(array_filter(array_map('intval', $_POST['ids_pedidos'] ?? []), fn($v) => $v > 0));
                if (empty($ids_pedidos)) {
                    $msg_cobro_err = 'Selecciona al menos un pedido.';
                } else {
                    try {
                        $cfg  = $this->model->getConfigPago();
                        $auto = !empty($cfg['wompi_confirmar_auto']);
                        $n = $this->model->confirmarCobroTienda($ids_pedidos, $auto);
                        $msg_cobro_ok = "$n pedido(s) marcados como pagados." . ($auto ? ' Los pedidos pendientes pasaron a confirmados.' : '');
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_cobro_err = 'Error al confirmar cobros: ' . $e->getMessage();
                    }
                }
            }
        }

        // ── 3. Cargar Cobros Pendientes ──────────────────────────────────────
        $rows_cobros = $this->model->getCobrosPendientesTiendas();
        $cobros_pendientes = [];
        foreach ($rows_cobros as $r) {
            $cid = $r['id_cliente'];
            if (!isset($cobros_pendientes[$cid])) {
                $cobros_pendientes[$cid] = [
                    'id_cliente'      => $cid,
                    'nombre'          => $r['nombre_tienda'],
                    'num_pedidos'     => 0,
                    'total_pendiente' => 0,
                    'pedidos'         => [],
                ];
            }
            $cobros_pendientes[$cid]['num_pedidos']++;
            $cobros_pendientes[$cid]['total_pendiente'] += (float)$r['total_estimado'];
            $cobros_pendientes[$cid]['pedidos'][] = $r;
        }
        $cobros_pendientes = array_values($cobros_pendientes);

        // ── 4. Filtros ───────────────────────────────────────────────────────
        $f_cliente = trim($_GET['cliente'] ?? '');
        $f_estado  = trim($_GET['estado'] ?? '');
        $f_pago    = trim($_GET['pago'] ?? '');
        $f_desde   = $_GET['desde'] ?? '';
        $f_hasta   = $_GET['hasta'] ?? '';
        $f_entrega = $_GET['entrega'] ?? '';
        $f_tipo    = trim($_GET['tipo'] ?? '');

        $params = [];
        $where = [];

        if ($f_cliente) {
            $where[] = "c.nombre LIKE ?";
            $params[] = "%$f_cliente%";
        }
        if ($f_estado) {
            $where[] = "p.estado = ?";
            $params[] = $f_estado;
        }
        if ($f_pago) {
            $where[] = "p.estado_pago = ?";
            $params[] = $f_pago;
        }
        if ($f_desde) {
            $where[] = "DATE(p.fecha_solicitud) >= ?";
            $params[] = $f_desde;
        }
        if ($f_hasta) {
            $where[] = "DATE(p.fecha_solicitud) <= ?";
            $params[] = $f_hasta;
        }
        if ($f_entrega) {
            $where[] = "p.fecha_entrega = ?";
            $params[] = $f_entrega;
        }
        if ($f_tipo) {
            $where[] = "c.tipo = ?";
            $params[] = $f_tipo;
        }

        // ── 5. Cargar Pedidos y Calcular Estadísticas ────────────────────────
        $pedidos = $this->model->getPedidos($where, $params);

        $pendientes = 0;
        $confirmados = 0;
        $hoy = 0;
        $total_estimado_hoy = 0;
        $fecha_hoy = date('Y-m-d');
        foreach ($pedidos as $p) {
            if ($p['estado'] === 'pendiente') $pendientes++;
            if ($p['estado'] === 'confirmado') $confirmados++;
            if (strpos($p['fecha_solicitud'], $fecha_hoy) === 0) {
                $hoy++;
                $total_estimado_hoy += $p['total_estimado'];
            }
        }

        $page_title = 'Pedidos de Clientes';

        // ── 6. Cargar Vista ──────────────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/pedidos_clientes/index.php';
    }

    /**
     * Detalle de pedido (ver_pedido.php)
     */
    public function verPedido() {
        requerirPropietario();
        $user = usuarioActual();
        $id_pedido = (int)($_GET['id'] ?? 0);
        $msg_ok = '';
        $msg_err = '';

        // Cargar pedido actual
        $pedido = $this->model->getPedido($id_pedido);
        if (!$pedido) {
            redirigir(APP_URL . '/modules/pedidos_clientes/index.php');
        }

        $config_pago = $this->model->getConfigPago();

        $metodos_legibles = [
            'NEQUI' => 'Nequi',
            'BANCOLOMBIA' => 'Bancolombia',
            'PSE' => 'PSE',
            'TARJETA' => 'Tarjeta',
            'OTRO' => 'Otro medio',
        ];

        // ── 1. Procesar Solicitudes POST ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_err = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                // (A) Actualizar estado del pedido
                if (isset($_POST['actualizar'])) {
                    $estado = $_POST['estado'] ?? 'pendiente';
                    $mensaje = trim($_POST['mensaje_propietario'] ?? '');
                    if (in_array($estado, ['pendiente', 'confirmado', 'rechazado'])) {
                        try {
                            $this->model->updatePedidoEstado($id_pedido, $estado, $mensaje);
                            $msg_ok = 'Pedido actualizado correctamente.';
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error al actualizar pedido.';
                        }
                    }
                }

                // (B) Habilitar pago digital
                elseif (isset($_POST['habilitar_pago'])) {
                    if (empty($config_pago['nequi_link_pago'])) {
                        $msg_err = 'Primero configura tu link de Nequi Negocios en Configuracion > Pagos.';
                    } else {
                        try {
                            $referencia = sprintf('PED-%d-%d', $id_pedido, (int) (microtime(true) * 1000));
                            $link_id = null;
                            if (preg_match('#/l/([A-Za-z0-9_-]+)#', $config_pago['nequi_link_pago'], $m)) {
                                $link_id = $m[1];
                            }
                            $this->model->habilitarPagoDigital($id_pedido, $referencia, $link_id, $config_pago['nequi_link_pago'], (float)$pedido['total_estimado']);
                            $msg_ok = 'Pago habilitado. El cliente ya puede pagar desde su portal.';
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error: ' . $e->getMessage();
                        }
                    }
                }

                // (C) Marcar como pagado (Confirmar abono)
                elseif (isset($_POST['marcar_pagado'])) {
                    $metodo = $_POST['metodo_pago'] ?? 'NEQUI';
                    $monto_recibido = (float) ($_POST['monto_recibido'] ?? 0);
                    $nota = trim($_POST['nota_pago'] ?? '');

                    $metodos_validos = ['NEQUI', 'BANCOLOMBIA', 'PSE', 'TARJETA', 'OTRO'];
                    if (!in_array($metodo, $metodos_validos, true)) {
                        $metodo = 'OTRO';
                    }

                    if (empty($pedido['id_pago_activo'])) {
                        $msg_err = 'No hay un pago activo para este pedido.';
                    } elseif ($monto_recibido <= 0) {
                        $msg_err = 'Debes ingresar el monto recibido.';
                    } else {
                        try {
                            $this->model->registrarAbonoPago((int)$pedido['id_pago_activo'], $monto_recibido, $metodo, $nota);
                            $msg_ok = 'Abono confirmado y registrado con éxito.';
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error: ' . $e->getMessage();
                        }
                    }
                }

                // (D) Deshabilitar pago
                elseif (isset($_POST['deshabilitar_pago'])) {
                    if (!empty($pedido['id_pago_activo'])) {
                        try {
                            $this->model->deshabilitarPagoDigital((int)$pedido['id_pago_activo']);
                            $msg_ok = 'Pago deshabilitado.';
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error: ' . $e->getMessage();
                        }
                    }
                }

                // (E) Revertir pago aprobado
                elseif (isset($_POST['revertir_pago'])) {
                    if (!empty($pedido['id_pago_activo'])) {
                        try {
                            $this->model->revertirPagoDigital((int)$pedido['id_pago_activo']);
                            $msg_ok = 'Pago revertido.';
                        } catch (Exception $e) {
                            log_error($e);
                            $msg_err = 'Error: ' . $e->getMessage();
                        }
                    }
                }

                // Recargar pedido tras mutaciones
                $pedido = $this->model->getPedido($id_pedido);
            }
        }

        // ── 2. Cargar Relaciones y Consolidados ──────────────────────────────
        $detalles = $this->model->getDetallesPedido($id_pedido);

        $pago_activo = null;
        $pedidos_consolidados = [];
        $es_consolidado = false;
        $abonos = [];
        $total_pagado = 0.0;
        if (!empty($pedido['id_pago_activo'])) {
            $pago_activo = $this->model->getPagoPedido((int)$pedido['id_pago_activo']);
            if ($pago_activo) {
                $pedidos_consolidados = $this->model->getPedidosConsolidadosPago((int)$pedido['id_pago_activo']);
                $es_consolidado = count($pedidos_consolidados) > 1;

                // Cargar abonos
                $abonos = $this->model->getAbonosPago((int)$pedido['id_pago_activo']);
                foreach ($abonos as $ab) {
                    $total_pagado += (float)$ab['monto'];
                }
            }
        }

        $hace_pago = '';
        if ($pago_activo && !empty($pago_activo['fecha_creacion'])) {
            $diff = (new DateTime())->diff(new DateTime($pago_activo['fecha_creacion']));
            if ($diff->days >= 1) {
                $hace_pago = 'hace ' . $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
            } else {
                $h = $diff->h;
                $hace_pago = $h > 0 ? "hace {$h}h" : 'hace menos de 1h';
            }
        }

        $estado_pago      = $pedido['estado_pago'] ?? 'no_aplica';
        $pago_configurado = !empty($config_pago['nequi_link_pago']);

        $page_title = 'Detalle de Pedido';

        // ── 3. Cargar Vista ──────────────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/pedidos_clientes/ver_pedido.php';
    }

    /**
     * Exportación de Pedidos (exportar.php)
     */
    public function exportar() {
        requerirPropietario();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['exportar_ids'])) {
            throw new Exception("No se seleccionaron pedidos para exportar.");
        }

        $formato = $_POST['formato'] ?? 'excel';
        $ids = $_POST['exportar_ids'];

        // Cargar información de la base de datos
        $pedidos = $this->model->getPedidosPorIds($ids);
        $detalles_brutos = $this->model->getDetallesPedidosPorIds($ids);

        $det_por_pedido = [];
        foreach ($detalles_brutos as $d) {
            $cant = $d['cantidad'] > 0 ? $d['cantidad'] : ($d['napa'] > 0 ? $d['napa'] : $d['bonificacion']);
            $extra = '';
            if ($d['napa'] > 0) $extra = ' (Ñapa)';
            elseif ($d['bonificacion'] > 0) $extra = ' (Bonif)';
            
            $det_por_pedido[$d['id_pedido']][] = htmlspecialchars($d['nombre']) . ' x' . $cant . $extra;
        }

        // Renderizar archivo de exportación directamente
        require_once __DIR__ . '/../views/pedidos_clientes/exportar.php';
    }
}
