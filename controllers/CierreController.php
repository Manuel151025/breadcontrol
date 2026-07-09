<?php
// controllers/CierreController.php

require_once __DIR__ . '/../models/CierreModel.php';

class CierreController {
    private $model;
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo   = $pdo;
        $this->model = new CierreModel($pdo);
    }

    /**
     * Pantalla principal de cierre del día (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();
        $hoy  = date('Y-m-d');

        $msg_ok  = '';
        $msg_err = '';

        // ── POST: Confirmar cierre ──────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_cierre'])) {
            $sugerencia = trim($_POST['sugerencia_produccion'] ?? '');

            $total_ingresos   = $this->model->getTotalVentasHoy($hoy);
            $costo_produccion = $this->model->getCostoProduccionHoy($hoy);
            $total_gastos_c   = $this->model->getTotalGastosHoy($hoy);
            $utilidad_bruta   = $total_ingresos - $costo_produccion;
            $utilidad_neta    = $utilidad_bruta - $total_gastos_c;

            try {
                $this->model->guardarCierre(
                    $user['id_usuario'], $hoy,
                    $total_ingresos, $total_gastos_c, $costo_produccion,
                    $utilidad_bruta, $utilidad_neta, $sugerencia
                );
                $msg_ok = 'Cierre del día guardado correctamente.';
            } catch (Exception $e) {
                $msg_err = 'Error al guardar: ' . $e->getMessage();
            }
        }

        // ── Datos del día actual ────────────────────────────────────
        $total_ventas         = $this->model->getTotalVentasHoy($hoy);
        $num_ventas           = $this->model->getNumVentasHoy($hoy);
        $ventas_ayer          = $this->model->getVentasAyer($hoy);
        $diff_ventas          = $ventas_ayer > 0 ? round((($total_ventas - $ventas_ayer) / $ventas_ayer) * 100, 1) : null;

        $total_compras        = $this->model->getTotalComprasHoy($hoy);
        $num_compras          = $this->model->getNumComprasHoy($hoy);
        $costo_produccion_hoy = $this->model->getCostoProduccionHoy($hoy);
        $total_gastos         = $this->model->getTotalGastosHoy($hoy);

        $utilidad_bruta       = $total_ventas - $costo_produccion_hoy;
        $utilidad_neta        = $utilidad_bruta - $total_gastos;

        // ── Desgloses ───────────────────────────────────────────────
        $ventas_prod   = $this->model->getVentasPorProducto($hoy);
        $ventas_cli    = $this->model->getVentasPorCliente($hoy);
        $producciones  = $this->model->getProduccionesHoy($hoy);
        $total_tandas  = array_sum(array_column($producciones, 'cantidad_tandas'));
        $compras_hoy   = $this->model->getComprasHoy($hoy);

        // ── Alertas y sobrantes ─────────────────────────────────────
        $alertas             = $this->model->getAlertasStockBajo();
        $num_alertas         = count($alertas);
        $sobrantes           = $this->model->getSobrantesHoy($hoy);
        $total_sobrante      = array_sum(array_column($sobrantes, 'sobrante'));
        $ventas_sin_producto = $this->model->getVentasSinProductoHoy($hoy);

        // ── Cierre e historial ──────────────────────────────────────
        $cierre_guardado = $this->model->getCierreGuardado($hoy);
        $historial       = $this->model->getHistorialCierres(7);

        // ── Renderizar ──────────────────────────────────────────────
        $page_title = 'Cierre del día';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/cierre/index.php';
    }
}
