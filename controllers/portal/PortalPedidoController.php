<?php
// controllers/portal/PortalPedidoController.php

require_once __DIR__ . '/PortalControllerBase.php';

/**
 * Ciclo de vida de los pedidos del portal: dashboard, detalle,
 * creación/edición y cancelación.
 */
class PortalPedidoController extends PortalControllerBase {

    /**
     * Carga el Dashboard del portal.
     */
    public function dashboard(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        $es_tienda = ($cliente_info['tipo'] === 'tienda');

        // La capacidad de instructor se determina por id (configuracion.id_cliente_adso),
        // NUNCA por tipo: solo la cuenta ADSO es instructor.
        $es_instructor = $this->model->esInstructorCapaz($cliente_info);
        $resumen_fin   = [];
        $aprendices    = [];
        $total_reg     = 0;

        if ($es_instructor) {
            $resumen_fin = $this->model->getInstructorStats($cliente_id);
            $aprendices  = $this->model->getAprendicesResumen($cliente_id);
            $total_reg   = $this->model->contarAprendices($cliente_id, true);
        }

        // Mensajes provenientes de un redirect (p. ej. el canje en completar_perfil.php).
        $success_msg = $_SESSION['flash_ok'] ?? '';
        $error_msg   = $_SESSION['flash_err'] ?? '';
        unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error_msg = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } elseif (isset($_POST['canjear_codigo'])) {
                // Canje desde el aviso del tablero (mismo helper unificado que registro/perfil).
                $r = $this->intentarCanjeCodigo($cliente_id, $_POST['codigo_aprendiz'] ?? '');
                if ($r['ok']) {
                    $success_msg = '¡Listo! Quedaste vinculado como aprendiz de ' . $r['instructor'] . '.';
                    $cliente_info = $this->model->getClienteById($cliente_id); // refrescar es_aprendiz
                } else {
                    $error_msg = $r['error'];
                }
            } elseif ($es_instructor) {
                if (isset($_POST['aprobar_aprendiz_id']) || isset($_POST['aprobar_lote_ids'])) {
                    $ids = [];
                    if (isset($_POST['aprobar_lote_ids'])) {
                        $ids = array_values(array_filter(array_map('intval', $_POST['aprobar_lote_ids']), fn($v) => $v > 0));
                    } elseif (isset($_POST['aprobar_aprendiz_id'])) {
                        $ids = [(int)$_POST['aprobar_aprendiz_id']];
                    }

                    $fecha_entrega = $_POST['fecha_entrega'] ?? '';
                    $hora_entrega = $_POST['hora_entrega'] ?? '';
                    
                    $datetime_entrega = $fecha_entrega . ' ' . $hora_entrega . ':00';
                    $ahora_str = date('Y-m-d H:i:s');
                    
                    if (empty($ids)) {
                        $error_msg = 'Debes seleccionar al menos un pedido para aprobar.';
                    } elseif (empty($fecha_entrega) || empty($hora_entrega)) {
                        $error_msg = 'Debes seleccionar una fecha y hora de entrega.';
                    } elseif (!ReglasPortal::horarioEntregaValido($hora_entrega)) {
                        $error_msg = 'El horario de entrega de la panadería es de 7:00 AM a 8:00 PM.';
                    } elseif ($fecha_entrega < date('Y-m-d')) {
                        $error_msg = 'La fecha de entrega no puede ser en el pasado.';
                    } elseif ($datetime_entrega <= $ahora_str) {
                        $error_msg = 'La fecha y hora de entrega no pueden ser en el pasado.';
                    } else {
                        try {
                            $n = $this->model->aprobarPedidosInstructorLote($ids, $cliente_id, $datetime_entrega);
                            $success_msg = $n > 1 
                                ? "$n pedidos aprobados y programados con éxito." 
                                : "Pedido aprobado y programado con éxito.";
                            // Recargar datos (esta rama solo corre para instructores)
                            $resumen_fin = $this->model->getInstructorStats($cliente_id);
                            $aprendices = $this->model->getAprendicesResumen($cliente_id);
                        } catch (Exception $e) {
                            $error_msg = $e->getMessage();
                        }
                    }
                } elseif (isset($_POST['rechazar_aprendiz_id']) || isset($_POST['rechazar_lote_ids'])) {
                    $ids = [];
                    if (isset($_POST['rechazar_lote_ids'])) {
                        $ids = array_values(array_filter(array_map('intval', $_POST['rechazar_lote_ids']), fn($v) => $v > 0));
                    } elseif (isset($_POST['rechazar_aprendiz_id'])) {
                        $ids = [(int)$_POST['rechazar_aprendiz_id']];
                    }
                    
                    if (empty($ids)) {
                        $error_msg = 'Debes seleccionar al menos un pedido para rechazar.';
                    } else {
                        try {
                            $n = $this->model->rechazarPedidosInstructorLote($ids, $cliente_id);
                            $success_msg = $n > 1
                                ? "$n pedidos rechazados con éxito."
                                : "Pedido rechazado con éxito.";
                            // Recargar datos (esta rama solo corre para instructores)
                            $resumen_fin = $this->model->getInstructorStats($cliente_id);
                            $aprendices = $this->model->getAprendicesResumen($cliente_id);
                        } catch (Exception $e) {
                            $error_msg = $e->getMessage();
                        }
                    }
                } elseif (isset($_POST['actualizar_cupo_aprendiz_id'])) {
                    $id_apr = (int)$_POST['actualizar_cupo_aprendiz_id'];
                    $nuevo_cupo = (float)($_POST['cupo_semanal'] ?? 0);
                    try {
                        // Validar que el aprendiz pertenezca a este instructor
                        $stmt_v = $this->pdo->prepare("SELECT COUNT(*) FROM cliente WHERE id_cliente = ? AND id_instructor = ? AND es_aprendiz = 1");
                    $stmt_v->execute([$id_apr, $cliente_id]);
                    if ((int)$stmt_v->fetchColumn() === 0) {
                        throw new Exception("El aprendiz no pertenece a tu grupo.");
                    }
                    
                    $error_cupo = ReglasPortal::validarCupoSemanal($nuevo_cupo);
                    if ($error_cupo !== null) {
                        throw new Exception($error_cupo);
                    }
                    
                    $stmt_u = $this->pdo->prepare("UPDATE cliente SET cupo_semanal = ? WHERE id_cliente = ?");
                    $stmt_u->execute([$nuevo_cupo, $id_apr]);
                    $success_msg = "Cupo semanal del aprendiz actualizado con éxito.";

                    // Recargar datos (esta rama solo corre para instructores)
                    $resumen_fin = $this->model->getInstructorStats($cliente_id);
                    $aprendices = $this->model->getAprendicesResumen($cliente_id);
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                }
            }
        }
    }

        // Datos Nequi / Wompi para el instructor
        $nequi_config = [];
        $pedidos_pago_instructor = [];
        $pedidos_por_aprobar = [];
        if ($es_instructor) {
            $nequi_config = $this->model->getConfiguracionPago();
            if ($resumen_fin['pendiente_total'] > 0) {
                $pedidos_pago_instructor = $this->model->getPedidosPagoInstructor($cliente_id);
            }
            $pedidos_por_aprobar = $this->model->getPedidosPendientesAprobacionInstructor($cliente_id);
        }

        $variedades = $this->model->getVariedadesPanActivas();

        // Filtros
        $f_estado   = trim($_GET['estado'] ?? '');
        $f_orden    = trim($_GET['orden'] ?? 'recientes');
        $f_aprendiz = (int)($_GET['aprendiz_id'] ?? 0);
        $f_variedad = (int)($_GET['variedad_id'] ?? 0);

        $nombre_variedad = '';
        if ($f_variedad) {
            foreach ($variedades as $v) {
                if ($v['id_variedad'] === $f_variedad) { 
                    $nombre_variedad = $v['nombre']; 
                    break; 
                }
            }
        }

        $filtros = [
            'estado' => $f_estado,
            'orden' => $f_orden,
            'aprendiz_id' => $f_aprendiz,
            'variedad_id' => $f_variedad
        ];

        $nombre_filtro = '';
        if ($f_aprendiz && $es_instructor) {
            $aprendiz_fil = $this->model->getClienteById($f_aprendiz);
            $nombre_filtro = $aprendiz_fil ? $aprendiz_fil['nombre'] : 'Aprendiz';
        }

        $mis_pedidos = $this->model->getPedidosFiltrados($cliente_id, $es_instructor, $filtros);
        $saldo_pendiente = $this->model->getSaldoPendiente($cliente_id);

        // Aviso de canje en el tablero: solo para clientes que NO son aprendices ni la
        // cuenta instructor, y SOLO si hay algún código activo y no vencido (temporada de
        // inscripción). Se calcula al final para reflejar un canje recién hecho.
        $mostrar_canje = (
            (int)($cliente_info['es_aprendiz'] ?? 0) !== 1
            && !$es_instructor
            && $this->model->hayCodigoAprendizActivo()
        );

        require_once __DIR__ . '/../../views/portal/dashboard.php';
    }

    /**
     * Muestra la vista detallada de un pedido específico.
     */
    public function detallePedido(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        $id_pedido  = (int)($_GET['id'] ?? 0);

        $cliente_info = $this->model->getClienteById($cliente_id);
        $es_aprendiz = $cliente_info ? ((int)$cliente_info['es_aprendiz'] === 1) : false;

        // Instructor por id (configuracion.id_cliente_adso), nunca por tipo.
        $es_instructor = $this->model->esInstructorCapaz($cliente_info);

        $pedido = $this->model->getPedido($id_pedido, $cliente_id);
        if (!$pedido) {
            header('Location: dashboard.php');
            exit;
        }

        // Regla unica de pago (D2): solo puede pagar quien figura como id_cliente del
        // pedido (destinatario/facturado). El id_creador puede VER el pedido pero nunca pagarlo.
        $puede_pagar = ((int)$pedido['id_cliente'] === $cliente_id);

        $detalles = $this->model->getDetallesPedido($id_pedido);

        $dentro_limite   = ReglasPortal::dentroDeLimite48h($pedido['fecha_entrega']);
        // Editar y cancelar dejaron de compartir regla: un pedido vencido ya no
        // se puede editar —no tiene sentido— pero si retirar.
        $puede_editar   = ReglasPortal::puedeGestionarPedido($pedido['estado'], $pedido['fecha_entrega']);
        $puede_cancelar = ReglasPortal::puedeCancelarPedido($pedido['estado'], $pedido['fecha_entrega']);

        // Pago digital
        $estado_pago = $pedido['estado_pago'] ?? 'no_aplica';
        $pago_activo = null;
        $abonos = [];
        $total_pagado = 0.0;
        if (!empty($pedido['id_pago_activo'])) {
            $pago_activo = $this->model->getPagoPendientePorId($pedido['id_pago_activo']);
            if ($pago_activo) {
                $abonos = $this->model->getAbonos($pedido['id_pago_activo']);
                foreach ($abonos as $ab) {
                    $total_pagado += (float)$ab['monto'];
                }
            }
        }

        $metodos_legibles = [
            'NEQUI' => 'Nequi', 'BANCOLOMBIA' => 'Bancolombia',
            'PSE' => 'PSE', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro medio',
        ];

        // Configuración de la tienda
        $cfg = $this->model->getConfiguracionPago();
        $titular_negocio = $cfg['nequi_titular'] ?? '';
        $nequi_link_pago = $cfg['nequi_link_pago'] ?? '';

        // Detalle por tienda
        $row_tipo = $this->model->getClienteTipoAsociadoPedido($id_pedido);
        $orden_es_de_tienda = ($row_tipo && $row_tipo['tipo'] === 'tienda');
        $es_tienda          = $orden_es_de_tienda && ($pedido['id_cliente'] === $cliente_id);
        $nombre_tienda      = $row_tipo['nombre'] ?? '';

        $todos_confirmados  = false;
        $pendientes_count   = 0;
        if ($es_tienda) {
            $pendientes_count  = $this->model->getCountPedidosPendientesTiendaFecha($pedido['id_cliente'], $pedido['fecha_entrega']);
            $todos_confirmados = ($pendientes_count === 0);
        }

        // Reporte por aprendiz
        $reporte_por_aprendiz = [];
        $total_general_reporte = 0.0;
        if ($es_tienda) {
            $reporte_por_aprendiz = $this->model->getReporteAgrupadoTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
            $total_general_reporte = $this->model->getTotalGeneralReporteTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
        }

        require_once __DIR__ . '/../../views/portal/detalle_pedido.php';
    }

    /**
     * Creación y edición de pedidos.
     */
    public function nuevoPedido(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        // Fecha y hora que el formulario propone por defecto. La hora NO puede ser
        // una constante: con '08:00' fijo, cualquier cliente que entrara después de
        // las 8 de la mañana y enviara el pedido sin tocar el campo recibía "la fecha
        // y hora no pueden ser en el pasado" sin haber hecho nada mal. Se propone la
        // próxima hora en punto dentro del horario de atención, y si ya no queda
        // margen hoy, mañana a la hora de apertura.
        $min_fecha     = date('Y-m-d');
        $hora_sugerida = ReglasPortal::HORA_APERTURA;
        $hora_actual   = (int) date('H');

        if ($hora_actual >= (int) substr(ReglasPortal::HORA_CIERRE, 0, 2)) {
            $min_fecha = date('Y-m-d', strtotime('+1 day'));
        } elseif ($hora_actual >= (int) substr(ReglasPortal::HORA_APERTURA, 0, 2)) {
            $hora_sugerida = date('H:00', (int) strtotime('+1 hour'));
            if ($hora_sugerida > ReglasPortal::HORA_CIERRE) {
                $min_fecha     = date('Y-m-d', strtotime('+1 day'));
                $hora_sugerida = ReglasPortal::HORA_APERTURA;
            }
        }

        // Obtener info del cliente (saber si es tienda, mostrador o aprendiz)
        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        // $es_tienda se conserva SOLO para la tarifa de bonificación/ñapa (tienda vs
        // mostrador), que es lógica de precios, no de instructor.
        $es_tienda = ($cliente_info['tipo'] === 'tienda');
        $es_aprendiz = (int)$cliente_info['es_aprendiz'] === 1;

        // Instructor por id (configuracion.id_cliente_adso), nunca por tipo.
        $es_instructor = $this->model->esInstructorCapaz($cliente_info);

        // ══ AJAX: TODAS las variedades (para bonificación) ══
        if (isset($_GET['ajax_all_variedades'])) {
            header('Content-Type: application/json');
            try {
                $all = $this->model->getProductosActivos();
                echo json_encode($all);
            } catch (Exception $e) { 
                echo json_encode([]); 
            }
            exit;
        }

        // ══ AJAX: variedades por categoría ══
        if (isset($_GET['ajax_variedades'])) {
            header('Content-Type: application/json');
            try {
                $id_cat = (int)$_GET['id_cat'];
                $vars = $this->model->getVariedadesPorCategoria($id_cat);
                echo json_encode($vars);
            } catch (Exception $e) {
                echo json_encode([]);
            }
            exit;
        }

        $error = '';
        $success = '';

        // ══ POST — Guardar o Editar Pedido ══
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pedido'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } else {
                $fecha_entrega = $_POST['fecha_entrega'] ?? '';
                $hora_entrega = $_POST['hora_entrega'] ?? '';
                $cart_json = $_POST['carrito_json'] ?? '[]';
                $bonif_json = $_POST['bonif_json'] ?? '[]';
                $edit_id = (int)($_POST['edit_id'] ?? 0);
                
                $cart = json_decode($cart_json, true);
                $bonif_items = json_decode($bonif_json, true);

                $pedido_para = $_POST['pedido_para'] ?? 'adso';
                $es_adso = ($es_aprendiz && $pedido_para === 'adso');

                if ($es_adso) {
                    $datetime_entrega = '1000-01-01 00:00:00';
                } else {
                    $datetime_entrega = $fecha_entrega . ' ' . $hora_entrega . ':00';
                }
                $ahora_str = date('Y-m-d H:i:s');

                if (!$es_adso && (empty($fecha_entrega) || empty($hora_entrega))) {
                    $error = 'Debes seleccionar una fecha y hora de entrega.';
                } elseif (!$es_adso && !ReglasPortal::horarioEntregaValido($hora_entrega)) {
                    $error = 'El horario de entrega de la panadería es de 7:00 AM a 8:00 PM.';
                } elseif (!$es_adso && $fecha_entrega < $min_fecha && $edit_id == 0) {
                    if ($min_fecha > date('Y-m-d')) {
                        $error = 'Por la hora actual, la fecha de entrega debe ser a partir de mañana (' . date('d/m/Y', (int) strtotime($min_fecha)) . ').';
                    } else {
                        $error = 'La fecha de entrega no puede ser en el pasado.';
                    }
                } elseif (!$es_adso && $datetime_entrega <= $ahora_str && $edit_id == 0) {
                    $error = 'La fecha y hora de entrega no pueden ser en el pasado.';
                } elseif (!$es_adso && $fecha_entrega > date('Y-m-d', strtotime('+3 months'))) {
                    $error = 'La fecha de entrega no puede ser mayor a 3 meses.';
                } elseif (empty($cart)) {
                    $error = 'El carrito está vacío. Debes pedir al menos un producto.';
                } else {
                    // Determinar el cliente destino (facturado) del pedido.
                    $id_cli_destino = $cliente_id;
                    $pedido_para = $_POST['pedido_para'] ?? 'adso';
                    if ($es_aprendiz && $pedido_para === 'adso') {
                        if (!empty($cliente_info['id_instructor'])) {
                            $id_cli_destino = (int)$cliente_info['id_instructor'];
                        } else {
                            // Enrutamiento a ADSO por id (configuracion.id_cliente_adso).
                            // Nunca por nombre: si la clave falta o apunta a una cuenta
                            // inexistente o inactiva, se falla con mensaje claro y visible.
                            $adso = $this->model->getClienteAdso();
                            if ($adso['id'] > 0) {
                                $id_cli_destino = $adso['id'];
                            } else {
                                $error = 'No se pudo enviar tu pedido a la cuenta ADSO: ' . $adso['error'];
                            }
                        }
                    }

                    if (empty($error)) {
                        try {
                            $id_ped_creado = $this->model->crearPedido($id_cli_destino, $cliente_id, $datetime_entrega, $cart, $bonif_items, $edit_id > 0 ? $edit_id : null);
                            $success = $edit_id > 0 ? "Pedido actualizado exitosamente." : "Pedido enviado exitosamente a la panadería.";
                        } catch (Exception $e) {
                            $error = 'Hubo un error al procesar tu pedido: ' . $e->getMessage();
                        }
                    }
                }
            }
        }

        // Preload para edición
        $edit_id = (int)($_GET['edit_id'] ?? 0);
        $ped_edit = null;
        $edit_fecha = '';
        $edit_hora = '';
        $cart_preload = [];
        $bonif_preload = [];

        if ($edit_id > 0) {
            $ped_edit = $this->model->getPedido($edit_id, $cliente_id);
            if ($ped_edit && $ped_edit['estado'] === 'pendiente') {
                $dt = new DateTime($ped_edit['fecha_entrega']);
                $yr = (int)$dt->format('Y');
                if ($yr <= 1970) {
                    $edit_fecha = date('Y-m-d');
                    $edit_hora = '08:00';
                } else {
                    $edit_fecha = $dt->format('Y-m-d');
                    $edit_hora = $dt->format('H:i');
                }
                // Bloqueo por pago en proceso si es aprendiz
                if (!empty($ped_edit['id_pago_activo'])) {
                    $stmt_pay_check = $this->pdo->prepare("SELECT estado FROM pago_pedido WHERE id_pago = ?");
                    $stmt_pay_check->execute([(int)$ped_edit['id_pago_activo']]);
                    $pay_status = $stmt_pay_check->fetchColumn();
                    if (ReglasPortal::bloqueoPorPagoInstructor($es_aprendiz, $pay_status !== false ? (string)$pay_status : null)) {
                        header('Location: detalle_pedido.php?id=' . $edit_id . '&error=pago_proceso');
                        exit;
                    }
                }

                // Validar restricción de 48 horas en la carga
                if (ReglasPortal::fueraDeLimiteGestion($ped_edit['fecha_entrega'])) {
                    header('Location: detalle_pedido.php?id=' . $edit_id . '&error=limite_tiempo');
                    exit;
                }

                $rows = $this->model->getDetallesPedido($edit_id);
                // Necesitamos la estructura pregrabada que requiere la vista (incluyendo catId)
                // Para esto volvemos a consultar con categorias
                $stmt_det_edit = $this->pdo->prepare("
                    SELECT d.*, vp.nombre, vp.imagen, cp.precio_unitario, cp.id_categoria 
                    FROM pedido_cliente_detalle d 
                    JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
                    JOIN categoria_precio cp ON vp.id_categoria_precio = cp.id_categoria
                    WHERE d.id_pedido = ?
                ");
                $stmt_det_edit->execute([$edit_id]);
                $det_rows = $stmt_det_edit->fetchAll(PDO::FETCH_ASSOC);

                foreach ($det_rows as $r) {
                    if ($r['cantidad'] > 0) {
                        $cart_preload[] = [
                            'id_variedad' => (int)$r['id_variedad'],
                            'nombre' => $r['nombre'],
                            'imagen' => $r['imagen'],
                            'precio' => (float)$r['precio_unitario'],
                            'cantidad' => (int)$r['cantidad'],
                            'catId' => (int)$r['id_categoria']
                        ];
                    } else if ($r['napa'] > 0 || $r['bonificacion'] > 0) {
                        $bonif_preload[(int)$r['id_variedad']] = $r['napa'] > 0 ? (int)$r['napa'] : (int)$r['bonificacion'];
                    }
                }
            }
        }

        $pedido_para_actual = 'adso';
        if ($ped_edit && $es_aprendiz) {
            $pedido_para_actual = ((int)$ped_edit['id_cliente'] === $cliente_id) ? 'personal' : 'adso';
        }

        $categorias = $this->model->getCategoriasActivas();

        require_once __DIR__ . '/../../views/portal/nuevo_pedido.php';
    }

    /**
     * Cancela un pedido.
     */
    public function cancelarPedido(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        // Cancelar destruye un pedido, así que exige POST con token. Antes se
        // hacía por GET y sin token: bastaba con que el cliente, ya autenticado,
        // abriera un enlace preparado por un tercero para perder su pedido sin
        // haber decidido nada. Es el mismo fallo que se corrigió en el
        // back-office; este punto del portal se había quedado fuera.
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: dashboard.php');
            exit;
        }
        if (!validar_token_csrf(post_texto('csrf_token'))) {
            header('Location: dashboard.php?error=csrf');
            exit;
        }

        $id_pedido = (int) post_texto('id');

        try {
            $this->model->cancelarPedido($id_pedido, $cliente_id);
            header('Location: dashboard.php?msg=cancelado');
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'pago') !== false) {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=pago_proceso');
            } elseif (strpos($msg, '48 horas') !== false) {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=limite_tiempo');
            } else {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=' . urlencode($msg));
            }
        }
        exit;
    }

}
