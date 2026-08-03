<?php
// controllers/portal/PortalPagoController.php

require_once __DIR__ . '/PortalControllerBase.php';

/**
 * Pago consolidado de pedidos (link de Nequi) desde el portal.
 */
class PortalPagoController extends PortalControllerBase {

    /**
     * Generación del link de pago consolidado y redirección.
     */
    public function pagarConsolidado() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $config_pago = $this->model->getConfiguracionPago();
        $pago_configurado = !empty($config_pago['nequi_link_pago']) && !empty($config_pago['wompi_habilitado']);

        $id_pedido_spec = (int)($_GET['id_pedido'] ?? 0);

        // getPedidosPendientesPago aplica la regla unica de pago por id_cliente (D2/D5).
        $pedidos = $this->model->getPedidosPendientesPago($cliente_id, $id_pedido_spec);
        if (empty($pedidos)) {
            header('Location: dashboard.php');
            exit;
        }

        // Defensa en profundidad (D2): ningun pedido del lote puede pertenecer a otra
        // cuenta. Si alguno no factura a este usuario, se aborta el lote completo — nunca
        // se filtra silenciosamente ni se expone el link de pago de otro.
        foreach ($pedidos as $p) {
            if ((int)$p['id_cliente'] !== $cliente_id) {
                header('Location: dashboard.php?error=pago_no_autorizado');
                exit;
            }
        }

        $total_saldo = 0;
        $ids_pedidos = [];
        $pedidos_por_pago = [];

        foreach ($pedidos as $p) {
            $pago_id = !empty($p['id_pago_activo']) ? (int)$p['id_pago_activo'] : 0;
            $pedidos_por_pago[$pago_id][] = $p;
            $ids_pedidos[] = (int)$p['id_pedido'];
        }

        foreach ($pedidos_por_pago as $pago_id => $grupo_pedidos) {
            if ($pago_id === 0) {
                foreach ($grupo_pedidos as $p) {
                    $total_saldo += (float)$p['total_estimado'];
                }
            } else {
                $pago_rec = $this->model->getPagoPendientePorId($pago_id);
                $suma_grupo = 0;
                foreach ($grupo_pedidos as $p) {
                    $suma_grupo += (float)$p['total_estimado'];
                }
                
                // Si está aprobado pero es un abono parcial, se debe sumar la diferencia restante
                $stmt_pay_actual = $this->pdo->prepare("SELECT estado, monto FROM pago_pedido WHERE id_pago = ?");
                $stmt_pay_actual->execute([$pago_id]);
                $pago_rec_db = $stmt_pay_actual->fetch(PDO::FETCH_ASSOC);

                if ($pago_rec_db && in_array(strtoupper($pago_rec_db['estado']), ['APPROVED', 'APROBADO'])) {
                    $deficit = $suma_grupo - (float)$pago_rec_db['monto'];
                    if ($deficit > 0) {
                        $total_saldo += $deficit;
                    }
                } else {
                    $total_saldo += $suma_grupo;
                }
            }
        }

        $error = '';
        $success = '';
        $link_pago_url = '';
        $pago_existente = null;

        // ¿Todos los pedidos ya comparten un unico pago PENDING? (idempotencia D3/C5)
        $pagos_activos = array_unique(array_filter(array_column($pedidos, 'id_pago_activo')));
        $id_pago_compartido = 0;
        if (count($pagos_activos) === 1) {
            $candidato = (int)reset($pagos_activos);
            $todos = true;
            foreach ($pedidos as $p) {
                if (empty($p['id_pago_activo']) || (int)$p['id_pago_activo'] !== $candidato) {
                    $todos = false;
                    break;
                }
            }
            if ($todos && $this->model->getPagoPendientePorId($candidato)) {
                $id_pago_compartido = $candidato;
            }
        }

        // URL de redireccion (POST-Redirect-GET). Preserva el pedido especifico si aplica.
        $redir = 'pagar_consolidado.php' . ($id_pedido_spec > 0 ? '?id_pedido=' . $id_pedido_spec . '&' : '?') . 'pago=ok';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pago'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } elseif (!$pago_configurado) {
                $error = 'La panadería aún no ha configurado el pago digital. Por favor contacta al propietario.';
            } elseif ($id_pago_compartido > 0) {
                // Idempotencia: ya existe un pago PENDING que cubre estos pedidos. No se
                // crea otro; se redirige a mostrar el enlace (POST-Redirect-GET).
                header('Location: ' . $redir);
                exit;
            } else {
                try {
                    $referencia = sprintf('CON-%d-%d', $cliente_id, (int)(microtime(true) * 1000));

                    // Extraer link_id del link estatico de Nequi (alojado en checkout.wompi.co)
                    $link_id = null;
                    if (preg_match('#/l/([A-Za-z0-9_-]+)#', $config_pago['nequi_link_pago'], $m)) {
                        $link_id = $m[1];
                    }

                    // Auditoria (D3): dejar constancia de quien inicio el pago.
                    $pagador = trim($_SESSION['cliente_nombre'] ?? '');
                    $nota_consolidado = sprintf('Pago de %d pedido(s) [%s] iniciado por %s (cliente #%d)',
                        count($ids_pedidos),
                        implode(', ', array_map(fn($id) => '#' . str_pad($id, 4, '0', STR_PAD_LEFT), $ids_pedidos)),
                        $pagador !== '' ? $pagador : 'cliente',
                        $cliente_id
                    );

                    $this->model->iniciarPagoConsolidado(
                        $cliente_id,
                        $pedidos,
                        $ids_pedidos,
                        $total_saldo,
                        $referencia,
                        $link_id,
                        $config_pago['nequi_link_pago'],
                        $nota_consolidado
                    );

                    // POST-Redirect-GET: evita re-registrar el pago con F5/doble submit (C5).
                    header('Location: ' . $redir);
                    exit;
                } catch (Exception $e) {
                    log_error($e);
                    $error = 'Error al habilitar el pago. Intenta de nuevo.';
                }
            }
        } else {
            // GET: si ya existe el pago consolidado, mostrar el enlace de Nequi.
            // El enlace SIEMPRE proviene de configuracion.nequi_link_pago (nunca hardcodeado).
            if ($id_pago_compartido > 0) {
                $pago_existente = $this->model->getPagoPendientePorId($id_pago_compartido);
                if ($pago_existente) {
                    $link_pago_url = $config_pago['nequi_link_pago'];
                }
            }
            if (isset($_GET['pago']) && $_GET['pago'] === 'ok' && $link_pago_url) {
                $success = 'Pago registrado. Toca el botón verde de abajo para pagar por Nequi.';
            }
        }

        $titular_negocio = $config_pago['nequi_titular'] ?? '';

        require_once __DIR__ . '/../../views/portal/pagar_consolidado.php';
    }

}
