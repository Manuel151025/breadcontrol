<?php
// controllers/GastoController.php

require_once __DIR__ . '/../models/GastoModel.php';
require_once __DIR__ . '/../helpers/FinanzasHelper.php';
require_once __DIR__ . '/../includes/funciones.php';

class GastoController {
    /** Tope de un gasto: frena una tecla de más sin estorbar a ningún gasto real. */
    private const VALOR_MAXIMO = 100000000;

    private GastoModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new GastoModel($pdo);
    }

    /**
     * Valor de un gasto. El campo es type="number" (el punto es decimal); se
     * toleran «$» y espacios por si se pega un valor copiado. Null si no es un
     * valor válido.
     */
    private static function valorGasto(string $texto): ?float {
        return numero_en_rango(str_replace(['$', ' '], '', $texto), 1, self::VALOR_MAXIMO);
    }
    
    public function index(): void {
        // Asegurar que el usuario sea propietario
        requerirPropietario();
        $user = usuarioActual();
        $hoy  = date('Y-m-d');
        
        // Resultado de un guardado recién hecho (Post-Redirect-Get, más abajo).
        $msg_ok  = is_string($_SESSION['gastos_ok'] ?? null) ? $_SESSION['gastos_ok'] : '';
        unset($_SESSION['gastos_ok']);
        $msg_err = '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }
        
        // Guarda CSRF única para TODAS las acciones POST de esta pantalla.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $fecha_post = fecha_valida(post_texto('fecha')) ? post_texto('fecha') : $hoy;
            requerir_csrf('index.php?fecha=' . $fecha_post . '&err=csrf');
        }

        // ── 1. Eliminar gasto (POST ?del=ID) ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del'])) {
            $redir_fecha = fecha_valida(post_texto('fecha')) ? post_texto('fecha') : $hoy;
            $id_g = (int)$_POST['del'];
            try {
                $this->model->eliminarGasto($id_g);
            } catch (Exception $e) {
                // Silencioso o log_error según se prefiera
                log_error($e);
            }
            header('Location: index.php?fecha=' . $redir_fecha);
            exit;
        }
        
        // ── 2. Guardar nuevo gasto (POST) ────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_gasto'])) {
            $cat  = post_texto('categoria');
            $desc = trim(post_texto('descripcion'));
            $val  = self::valorGasto(post_texto('valor'));

            if (!in_array($cat, ['compra', 'servicio', 'otro'])) {
                $cat = '';
            }

            if (!$cat || !$desc) {
                $msg_err = 'Completa todos los campos correctamente.';
            } elseif (!texto_cabe($desc, 255)) {
                $msg_err = 'La descripción no puede superar los 255 caracteres.';
            } elseif ($val === null) {
                $msg_err = 'El valor debe ser un número mayor que 0 y de máximo $' . number_format(self::VALOR_MAXIMO, 0, ',', '.') . '.';
            } else {
                try {
                    $result = $this->model->registrarGasto($user['id_usuario'], $cat, $desc, $val);
                    if ($result) {
                        // Post-Redirect-Get: F5 sobre el aviso registraba el gasto
                        // otra vez y descuadraba el cierre del día.
                        $_SESSION['gastos_ok'] = 'Gasto registrado correctamente.';
                        header('Location: index.php');
                        exit;
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
            $id_g   = (int)($_POST['id_gasto'] ?? 0);
            $cat_e  = post_texto('cat_edit');
            $desc_e = trim(post_texto('desc_edit'));
            $val_e  = self::valorGasto(post_texto('val_edit'));

            if (!in_array($cat_e, ['compra', 'servicio', 'otro'])) {
                $cat_e = '';
            }

            if ($id_g <= 0) {
                $msg_err = 'No se encontró el gasto a editar. Recarga la página.';
            } elseif (!texto_cabe($desc_e, 255)) {
                $msg_err = 'La descripción no puede superar los 255 caracteres.';
            } elseif ($cat_e && $desc_e && $val_e !== null) {
                try {
                    $result = $this->model->actualizarGasto($id_g, $cat_e, $desc_e, $val_e);
                    if ($result) {
                        $_SESSION['gastos_ok'] = 'Gasto actualizado correctamente.';
                        header('Location: index.php');
                        exit;
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
        $fecha_fil = fecha_valida(get_texto('fecha')) ? get_texto('fecha') : $hoy;
        
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
                'lbl' => date('d/m', (int) strtotime($f)),
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
