<?php
// controllers/GastoController.php

require_once __DIR__ . '/../models/GastoModel.php';
require_once __DIR__ . '/../helpers/FinanzasHelper.php';

class GastoController {
    private $model;
    
    public function __construct(PDO $pdo) {
        $this->model = new GastoModel($pdo);
    }
    
    public function index() {
        // Asegurar que el usuario sea propietario
        requerirPropietario();
        $user = usuarioActual();
        $hoy  = date('Y-m-d');
        
        $msg_ok  = '';
        $msg_err = '';
        
        // ── 1. Eliminar gasto (GET ?del=ID) ──────────────────────────────────────
        if (!empty($_GET['del'])) {
            $id_g = (int)$_GET['del'];
            try {
                $this->model->eliminarGasto($id_g);
            } catch (Exception $e) {
                // Silencioso o log_error según se prefiera
                log_error($e);
            }
            $redir_fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'] ?? '') ? $_GET['fecha'] : $hoy;
            header('Location: index.php?fecha=' . $redir_fecha);
            exit;
        }
        
        // ── 2. Guardar nuevo gasto (POST) ────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_gasto'])) {
            $cat  = $_POST['categoria']  ?? '';
            $desc = trim($_POST['descripcion'] ?? '');
            $val  = (float)str_replace(['.', '$', ' '], '', $_POST['valor'] ?? 0);
            
            if (!in_array($cat, ['compra', 'servicio', 'otro'])) {
                $cat = '';
            }
            
            if (!$cat || !$desc || $val <= 0) {
                $msg_err = 'Completa todos los campos correctamente.';
            } else {
                try {
                    $result = $this->model->registrarGasto($user['id_usuario'], $cat, $desc, $val);
                    if ($result) {
                        $msg_ok = 'Gasto registrado correctamente.';
                    } else {
                        $msg_err = 'Error al registrar el gasto.';
                    }
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error interno al registrar el gasto.';
                }
            }
        }
        
        // ── 3. Editar gasto existente (POST) ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_gasto'])) {
            $id_g   = (int)$_POST['id_gasto'];
            $cat_e  = $_POST['cat_edit'] ?? '';
            $desc_e = trim($_POST['desc_edit'] ?? '');
            $val_e  = (float)str_replace(['.', '$', ' '], '', $_POST['val_edit'] ?? 0);
            
            if (!in_array($cat_e, ['compra', 'servicio', 'otro'])) {
                $cat_e = '';
            }
            
            if ($cat_e && $desc_e && $val_e > 0) {
                try {
                    $result = $this->model->actualizarGasto($id_g, $cat_e, $desc_e, $val_e);
                    if ($result) {
                        $msg_ok = 'Gasto actualizado correctamente.';
                    } else {
                        $msg_err = 'Error al actualizar el gasto.';
                    }
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error interno al actualizar el gasto.';
                }
            } else {
                $msg_err = 'Completa todos los campos correctamente.';
            }
        }
        
        // ── 4. Cargar datos para la vista ────────────────────────────────────────
        $fecha_fil = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'] ?? '') ? $_GET['fecha'] : $hoy;
        
        // Obtener listado de gastos del día
        $gastos_dia = $this->model->getGastosPorFecha($fecha_fil);
        
        // Agrupar y totalizar por categoría
        $por_cat = [];
        foreach ($gastos_dia as $g) {
            $por_cat[$g['categoria']] = ($por_cat[$g['categoria']] ?? 0) + $g['valor'];
        }
        $total_dia = array_sum(array_column($gastos_dia, 'valor'));
        
        // Obtener resumen financiero (ingresos, compras y costo real de producción)
        $finanzas = $this->model->getResumenFinanzasDia($fecha_fil);
        $ingresos_dia = $finanzas['ingresos'];
        $compras_dia  = $finanzas['compras'];
        $costo_produccion_dia = $finanzas['costo_produccion'];
        $utilidad_neta = FinanzasHelper::calcularUtilidad($ingresos_dia, $costo_produccion_dia, $total_dia)['neta'];
        
        // Estadísticas mensuales
        $gastos_mes     = $this->model->getGastosMes();
        $num_gastos_mes = $this->model->getNumGastosMes();
        
        // Datos de los últimos 7 días para el mini gráfico
        $gastos_7d_raw = $this->model->getGastosUltimos7Dias();
        $gastos_7d = [];
        for ($i = 6; $i >= 0; $i--) {
            $f = date('Y-m-d', strtotime("-$i days"));
            $gastos_7d[] = [
                'lbl' => date('d/m', strtotime($f)),
                'v'   => (float)($gastos_7d_raw[$f] ?? 0)
            ];
        }
        $chart_max_7d = max(array_column($gastos_7d, 'v') ?: [1]);
        
        // Textos y configuraciones
        $cat_labels = [
            'compra'   => ['🛒', 'Compras',   '#1565c0', 'rgba(21,101,192,.1)'],
            'servicio' => ['💡', 'Servicios', '#e65100', 'rgba(230,81,0,.1)'],
            'otro'     => ['📝', 'Otros',     '#2e7d32', 'rgba(46,125,50,.1)'],
        ];
        
        $page_title = 'Gastos';
        
        // Renderizar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/gastos/index.php';
    }
}
