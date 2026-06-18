<?php
// controllers/FinanzasController.php

require_once __DIR__ . '/../models/FinanzasModel.php';

class FinanzasController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new FinanzasModel($pdo);
    }

    /**
     * Dashboard principal de finanzas (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        // ── Filtros ────────────────────────────────────────────────────────────────
        $modo   = in_array($_GET['modo'] ?? 'mes', ['mes','semana','rango']) ? ($_GET['modo'] ?? 'mes') : 'mes';
        $anio   = (int)($_GET['anio']   ?? date('Y'));
        $mes    = max(1, min(12, (int)($_GET['mes'] ?? date('m'))));
        $semana = max(1, min(53, (int)($_GET['semana'] ?? date('W'))));
        $desde  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta  = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');

        if ($modo === 'mes') {
            $desde          = "$anio-" . str_pad($mes, 2, '0', STR_PAD_LEFT) . "-01";
            $hasta          = date('Y-m-t', strtotime($desde));
            $titulo_periodo = date('F Y', strtotime($desde));
        } elseif ($modo === 'semana') {
            $dto = new DateTime();
            $dto->setISODate($anio, $semana, 1);
            $desde = $dto->format('Y-m-d');
            $dto->modify('+6 days');
            $hasta          = $dto->format('Y-m-d');
            $titulo_periodo = "Sem. $semana · " . date('d/m', strtotime($desde)) . " – " . date('d/m/Y', strtotime($hasta));
        } else {
            $titulo_periodo = date('d/m/Y', strtotime($desde)) . " – " . date('d/m/Y', strtotime($hasta));
        }

        // Fetch totals
        $ingresos = $this->model->getIngresos($desde, $hasta);
        $compras = $this->model->getComprasTotal($desde, $hasta);
        $gastos_op = $this->model->getGastosOp($desde, $hasta);
        $utilidad_bruta = $ingresos - $compras;
        $utilidad_neta  = $ingresos - $compras - $gastos_op;
        $margen_bruto   = $ingresos > 0 ? round(($utilidad_bruta / $ingresos) * 100, 1) : 0;
        $num_ventas = $this->model->getNumVentas($desde, $hasta);
        $num_compras = $this->model->getNumCompras($desde, $hasta);

        // Ventas y compras por día (gráfico)
        $ventas_dia = $this->model->getVentasPorDia($desde, $hasta);
        $compras_dia = $this->model->getComprasPorDia($desde, $hasta);

        $dias_chart = [];
        $cur = strtotime($desde);
        $fin = strtotime($hasta);
        while ($cur <= $fin) {
            $f = date('Y-m-d', $cur);
            $dias_chart[] = [
                'f'   => $f,
                'lbl' => date('d/m', $cur),
                'v'   => (float)($ventas_dia[$f]  ?? 0),
                'c'   => (float)($compras_dia[$f] ?? 0),
            ];
            $cur = strtotime('+1 day', $cur);
        }
        $chart_max = max(array_merge(array_column($dias_chart, 'v'), array_column($dias_chart, 'c'), [1]));

        // Top productos vendidos
        $top_productos = $this->model->getTopProductos($desde, $hasta, 6);

        // Top clientes
        $top_clientes = $this->model->getTopClientes($desde, $hasta, 5);

        // Top insumos comprados
        $top_insumos = $this->model->getTopInsumos($desde, $hasta, 5);

        // Gastos por categoría
        $gastos_cat = $this->model->getGastosPorCategoria($desde, $hasta);

        // Comparativo período anterior
        $dias_periodo = max(1, (strtotime($hasta) - strtotime($desde)) / 86400 + 1);
        $desde_ant    = date('Y-m-d', strtotime($desde) - $dias_periodo * 86400);
        $hasta_ant    = date('Y-m-d', strtotime($desde) - 86400);

        $ingresos_ant = $this->model->getIngresos($desde_ant, $hasta_ant);
        $compras_ant  = $this->model->getComprasTotal($desde_ant, $hasta_ant);

        $utilidad_ant  = $ingresos_ant - $compras_ant;
        $diff_ingresos = $ingresos_ant  > 0 ? round((($ingresos       - $ingresos_ant)  / $ingresos_ant)  * 100, 1) : null;
        $diff_compras  = $compras_ant   > 0 ? round((($compras        - $compras_ant)   / $compras_ant)   * 100, 1) : null;
        $diff_utilidad = $utilidad_ant != 0  ? round((($utilidad_bruta - $utilidad_ant) / abs($utilidad_ant)) * 100, 1) : null;

        // Años disponibles
        $anios = $this->model->getAniosDisponibles();

        // Consumo de ingredientes en el período
        $consumo_ingredientes = $this->model->getConsumoIngredientes($desde, $hasta, 6);
        $es_estimado_consumo = false;
        if (empty($consumo_ingredientes)) {
            $consumo_ingredientes = $this->model->getConsumoIngredientesEstimado($desde, $hasta, 6);
            $es_estimado_consumo = !empty($consumo_ingredientes);
        }

        $costo_prod_total = array_sum(array_column($consumo_ingredientes, 'total_costo'));
        $max_ing_costo    = !empty($consumo_ingredientes) ? max(array_column($consumo_ingredientes, 'total_costo') ?: [0]) : 1;
        $max_ing_cant     = !empty($consumo_ingredientes) ? max(array_column($consumo_ingredientes, 'total_cant'))  : 1;

        $page_title = 'Finanzas';

        // Cargar vistas
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/finanzas/index.php';
    }

    /**
     * Exportación de reporte a PDF (exportar_pdf.php)
     */
    public function exportarPdf() {
        requerirPropietario();
        $user = usuarioActual();

        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['desde'] ?? '') ? $_GET['desde'] : date('Y-m-01');
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['hasta'] ?? '') ? $_GET['hasta'] : date('Y-m-d');

        // Totales
        $ingresos = $this->model->getIngresos($desde, $hasta);
        $compras_total = $this->model->getComprasTotal($desde, $hasta);
        $gastos_op = $this->model->getGastosOp($desde, $hasta);
        $utilidad_bruta = $ingresos - $compras_total;
        $utilidad_neta  = $ingresos - $compras_total - $gastos_op;
        $margen_bruto   = $ingresos > 0 ? round(($utilidad_bruta/$ingresos)*100,1) : 0;
        $num_ventas = $this->model->getNumVentas($desde, $hasta);
        $num_compras = $this->model->getNumCompras($desde, $hasta);

        // Ventas por día para gráfico
        $ventas_dia = $this->model->getVentasPorDia($desde, $hasta);

        // Top productos
        $top_prod = $this->model->getTopProductos($desde, $hasta, 6);

        // Detalle compras
        $detalle_compras = $this->model->getDetalleCompras($desde, $hasta);

        // Detalle ventas
        $detalle_ventas = $this->model->getDetalleVentas($desde, $hasta);

        // Días del período para gráfico
        $dias_chart = [];
        $cur = strtotime($desde);
        while ($cur <= strtotime($hasta)) {
            $f = date('Y-m-d',$cur);
            $dias_chart[] = ['lbl'=>date('d/m',$cur),'v'=>(float)($ventas_dia[$f]??0),'f'=>$f];
            $cur = strtotime('+1 day',$cur);
        }
        $chart_max = max(array_column($dias_chart,'v')?:[1]);

        $logo_path = APP_URL . '/assets/img/logo.png';
        $titulo_periodo = date('d \d\e F Y', strtotime($desde)) . ' — ' . date('d \d\e F Y', strtotime($hasta));

        // Cargar vista
        require_once __DIR__ . '/../views/finanzas/exportar_pdf.php';
    }
}
