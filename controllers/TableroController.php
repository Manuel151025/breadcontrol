<?php
require_once __DIR__ . '/../models/TableroModel.php';

class TableroController {
    private $model;
    
    public function __construct($pdo) {
        $this->model = new TableroModel($pdo);
    }
    
    public function index() {
        // Preparar entorno
        requerirPropietario();
        $user = usuarioActual();
        
        // Datos generales
        $meses_es = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $dias_es = ['Sun'=>'Dom','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb'];
        $mes_actual = $meses_es[(int)date('n')];
        
        // Obtener datos del modelo
        $ventas_stats = $this->model->getEstadisticasVentas();
        $ventas_hoy = $ventas_stats['hoy'];
        $num_ventas = $ventas_stats['num_ventas'];
        $diff_v = $ventas_stats['diff_v'];
        
        $finanzas = $this->model->getFinanzasMes();
        $ingresos_mes = $finanzas['ingresos'];
        $compras_mes = $finanzas['compras'];
        $utilidad_mes = $finanzas['utilidad'];
        
        $inventario = $this->model->getResumenInventario();
        $total_insumos = $inventario['total_insumos'];
        $prod_hoy = $inventario['prod_hoy'];
        $prods_act = $inventario['prods_act'];
        
        $alertas = $this->model->getAlertas();
        $num_alertas = count($alertas);
        $prods_recientes = $this->model->getProduccionesRecientes();
        $top_ventas = $this->model->getTopVentas();
        
        // Procesar gráfico
        $dias_raw = $this->model->getVentasUltimos7Dias();
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $f = date('Y-m-d', strtotime("-{$i} days"));
            $chart[] = [
                'lbl' => $dias_es[date('D', strtotime($f))],
                'v'   => (float)($dias_raw[$f] ?? 0),
                'hoy' => $i === 0
            ];
        }
        $chartMax = max(array_column($chart, 'v') ?: [1]);
        
        // Consumo
        $consumo_hoy = $this->model->getConsumoHoy();
        $max_consumo_hoy = !empty($consumo_hoy) ? max(array_column($consumo_hoy, 'total')) : 1;
        
        // Cierre
        $obs_cierre = $this->model->getObservacionCierre();
        
        // Renderizar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/tablero/index.php';
    }
}
