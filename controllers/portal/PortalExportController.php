<?php
// controllers/portal/PortalExportController.php

require_once __DIR__ . '/PortalControllerBase.php';

/**
 * Exportaciones del portal: pedidos del dashboard, reporte por tienda,
 * cartera del instructor y recibos de pago (Excel/PDF/impresión).
 */
class PortalExportController extends PortalControllerBase {

    /**
     * Exporta pedidos seleccionados desde el dashboard en formato Excel o PDF.
     */
    public function exportarPedidosDashboard() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                die("Error de seguridad: Token CSRF inválido.");
            }
        }

        $formato    = in_array($_POST['formato'] ?? '', ['excel', 'pdf']) ? $_POST['formato'] : 'pdf';
        $ids        = array_values(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($v) => $v > 0));

        if (empty($ids)) {
            header('Location: dashboard.php');
            exit;
        }

        // Verificar pertenencia
        if (!$this->model->verificarPedidosPertenecenCliente($ids, $cliente_id)) {
            header('Location: dashboard.php');
            exit;
        }

        $cliente_info = $this->model->getClienteById($cliente_id);
        $nombre_tienda = $cliente_info ? $cliente_info['nombre'] : 'Tienda';

        $pedidos = $this->model->getPedidosDetalladosParaExportacion($ids);
        $todos_detalles = $this->model->getDetallesPedidosParaExportacion($ids);

        // Agrupar detalles por id_pedido
        $detalles_por_pedido = [];
        foreach ($todos_detalles as $d) {
            $detalles_por_pedido[$d['id_pedido']][] = $d;
        }

        $fecha_generado = date('d/m/Y H:i');

        require_once __DIR__ . '/../../views/portal/exportar_pedidos_dashboard.php';
    }

    /**
     * Exporta el reporte de panes agrupado por aprendiz para una tienda.
     */
    public function exportarReporteTienda() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $id_pedido  = (int)($_GET['id'] ?? 0);
        $formato    = in_array($_GET['formato'] ?? '', ['excel', 'pdf']) ? $_GET['formato'] : 'pdf';

        $pedido = $this->model->getPedidoTiendaParaExportacion($id_pedido, $cliente_id);
        if (!$pedido) {
            header('Location: dashboard.php');
            exit;
        }

        $reporte_por_aprendiz = $this->model->getReporteAgrupadoTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
        $total_general = $this->model->getTotalGeneralReporteTienda($pedido['id_cliente'], $pedido['fecha_entrega']);

        $fecha_entrega_fmt = date('H:i', strtotime($pedido['fecha_entrega'])) !== '00:00'
            ? date('d/m/Y H:i', strtotime($pedido['fecha_entrega']))
            : date('d/m/Y', strtotime($pedido['fecha_entrega']));
        $fecha_generado    = date('d/m/Y H:i');
        $nombre_tienda     = $pedido['nombre_tienda'];

        require_once __DIR__ . '/../../views/portal/exportar_reporte_tienda.php';
    }

    /**
     * Exporta la cartera de todos los aprendices vinculados al instructor en formato PDF/Impresión.
     */
    public function exportarCarteraInstructor() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        // Validar si realmente es instructor ADSO o tiene aprendices
        $total_reg = $this->model->contarAprendices($cliente_id, true);
        if ($total_reg === 0) {
            header('Location: dashboard.php');
            exit;
        }

        $aprendices = $this->model->getAprendicesResumen($cliente_id);
        $resumen_fin = $this->model->getInstructorStats($cliente_id);

        $nombre_instructor = $cliente_info['nombre'];
        $fecha_generado    = date('d/m/Y H:i');

        require_once __DIR__ . '/../../views/portal/exportar_cartera_instructor.php';
    }

    /**
     * Exporta el recibo de un pago aprobado o parcial en formato PDF/Impresión.
     */
    public function exportarReciboPago() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        $id_pedido  = (int)($_GET['id'] ?? 0);

        $pedido = $this->model->getPedido($id_pedido, $cliente_id);
        if (!$pedido || !in_array($pedido['estado_pago'], ['aprobado', 'parcial'])) { 
            header('Location: dashboard.php'); 
            exit; 
        }

        $detalles = $this->model->getDetallesPedido($id_pedido);

        $abonos = [];
        $total_pagado = 0.0;
        $pago_activo = null;
        if (!empty($pedido['id_pago_activo'])) {
            $pago_id = (int)$pedido['id_pago_activo'];
            
            // Buscar pago_pedido activo o inactivo (aprobado)
            $stmt_p = $this->pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ?");
            $stmt_p->execute([$pago_id]);
            $pago_activo = $stmt_p->fetch(PDO::FETCH_ASSOC);
            
            if ($pago_activo) {
                $abonos = $this->model->getAbonos($pago_id);
                foreach ($abonos as $ab) {
                    $total_pagado += (float)$ab['monto'];
                }
            }
        }

        // Consultar otros pedidos incluidos en esta misma transacción de pago
        $pedidos_consolidados = [];
        if ($pago_activo) {
            $stmt_pc = $this->pdo->prepare("
                SELECT p.id_pedido, p.total_estimado, p.fecha_entrega, c.nombre AS nombre_aprendiz
                FROM pedido_cliente p
                LEFT JOIN cliente c ON p.id_creador = c.id_cliente
                WHERE p.id_pago_activo = ? AND p.estado_pago IN ('aprobado', 'parcial')
            ");
            $stmt_pc->execute([$pago_activo['id_pago']]);
            $pedidos_consolidados = $stmt_pc->fetchAll(PDO::FETCH_ASSOC);
        }

        $metodos_legibles = [
            'NEQUI' => 'Nequi', 'BANCOLOMBIA' => 'Bancolombia',
            'PSE' => 'PSE', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro medio',
        ];

        $cfg = $this->model->getConfiguracionPago();
        $titular_negocio = $cfg['nequi_titular'] ?? 'BreadControl';
        
        $row_tipo = $this->model->getClienteTipoAsociadoPedido($id_pedido);
        $nombre_tienda = $row_tipo['nombre'] ?? '';

        $fecha_generado = date('d/m/Y H:i');

        require_once __DIR__ . '/../../views/portal/exportar_recibo_pago.php';
    }
}
