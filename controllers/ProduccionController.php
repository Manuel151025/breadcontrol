<?php
// controllers/ProduccionController.php

require_once __DIR__ . '/../models/ProduccionModel.php';

class ProduccionController {
    private $model;
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo   = $pdo;
        $this->model = new ProduccionModel($pdo);
    }

    /**
     * Historial de producción (index.php)
     */
    public function index() {
        requerirPropietario();

        // ── Filtro por fecha ────────────────────────────────────────
        $fecha_fil = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['fecha'] ?? '') ? $_GET['fecha'] : date('Y-m-d');

        $producciones  = $this->model->getProduccionesPorFecha($fecha_fil);
        $total_tandas  = array_sum(array_column($producciones, 'cantidad_tandas'));

        // ── KPIs ────────────────────────────────────────────────────
        $kpis = $this->model->getKPIs();
        $prod_hoy          = $kpis['prod_hoy'];
        $prod_ayer         = $kpis['prod_ayer'];
        $prod_mes          = $kpis['prod_mes'];
        $productos_activos = $kpis['productos_activos'];
        $total_tandas_mes  = $kpis['total_tandas_mes'];

        $meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
        $nombre_mes = $meses[date('n') - 1];

        // ── Top productos del mes ───────────────────────────────────
        $top_productos = $this->model->getTopProductosMes();
        $max_tandas    = max(array_column($top_productos, 'tandas') ?: [1]);

        // ── Renderizar ──────────────────────────────────────────────
        $page_title = 'Producción';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/produccion/index.php';
    }

    /**
     * Registro de nueva producción con recetas y consumo FIFO (nueva_produccion.php)
     */
    public function nuevaProduccion() {
        requerirPropietario();
        $user = usuarioActual();

        // ══════════════════════════════════════════════════════════
        //  ENDPOINT AJAX — devuelve ingredientes + lotes FIFO en JSON
        // ══════════════════════════════════════════════════════════
        if (isset($_GET['ajax_lotes'])) {
            header('Content-Type: application/json');
            $id_prod  = (int)($_GET['id_producto'] ?? 0);
            $unidades = max(1, (int)($_GET['unidades'] ?? 1));

            if (!$id_prod) { echo json_encode(['error' => 'Sin producto']); exit; }

            $id_receta = $this->model->getRecetaVigente($id_prod);
            if (!$id_receta) { echo json_encode(['error' => 'sin_receta']); exit; }

            $ingredientes = $this->model->getIngredientesReceta($id_receta);

            $resultado    = [];
            $hay_faltante = false;

            foreach ($ingredientes as $ing) {
                $info = $this->model->calcularLotesFIFO($ing, $unidades);
                if (!$info['alcanza']) $hay_faltante = true;
                $resultado[] = $info;
            }

            echo json_encode([
                'ok'           => true,
                'hay_faltante' => $hay_faltante,
                'ingredientes' => $resultado,
                'id_receta'    => $id_receta,
            ]);
            exit;
        }

        // ══════════════════════════════════════════════════════════
        //  POST — Registrar producción + consumo FIFO de lotes
        // ══════════════════════════════════════════════════════════
        $msg_ok  = '';
        $msg_err = '';
        $msg_err_class = '';

        if (!empty($_SESSION['msg_ok_prod'])) {
            $msg_ok = $_SESSION['msg_ok_prod'];
            unset($_SESSION['msg_ok_prod']);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
            $id_prod  = (int)($_POST['id_producto']         ?? 0);
            $tandas   = max(1, (int)($_POST['num_tandas']    ?? 1));
            $fecha    =       $_POST['fecha_produccion']     ?? date('Y-m-d');
            $obs      = trim($_POST['observaciones']         ?? '');

            // Calcular unidades desde tandas × cantidad_por_tanda
            $cant_por_tanda = $this->model->getCantidadPorTanda($id_prod);
            $unidades = (int)round($tandas * $cant_por_tanda);

            if (!$id_prod)        $msg_err = 'Selecciona un producto.';
            elseif ($unidades<=0) $msg_err = 'Las unidades producidas deben ser mayor a 0.';
            elseif (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $msg_err = 'La fecha de producción no es válida.';
            elseif ($fecha > date('Y-m-d')) $msg_err = 'No se puede registrar producción con fecha futura.';
            elseif ($fecha < date('Y-m-d', strtotime('-7 days'))) $msg_err = 'La fecha de producción no puede ser de hace más de 7 días.';
            else {
                $id_receta = $this->model->getRecetaVigente($id_prod);

                if (!$id_receta) {
                    $msg_err = 'Este producto no tiene receta vigente. Créala primero en <a href="../recetas/index.php">Recetas</a>.';
                } else {
                    $ingredientes = $this->model->getIngredientesReceta($id_receta);

                    // Verificar stock suficiente
                    $errores_stock = $this->model->verificarStockIngredientes($ingredientes, $tandas);

                    $forzar = !empty($_POST['forzar_produccion']);
                    if (!empty($errores_stock) && !$forzar) {
                        $msg_err = '<div style="text-align:left;width:100%;">'
                                 . '<div style="margin-bottom:.5rem;font-weight:700;">Stock insuficiente para producir:</div>'
                                 . '<ul style="margin:0;padding-left:1.5rem;line-height:1.6;font-size:.82rem;">';
                        foreach ($errores_stock as $es) {
                            $msg_err .= '<li>Falta <strong>' . $es['nombre'] . '</strong>: necesitas '
                                . formatoInteligente($es['cant_necesaria']) . ' ' . $es['unidad_medida']
                                . ', solo hay ' . formatoInteligente($es['disponible']) . ' ' . $es['unidad_medida'] . '.</li>';
                        }
                        $msg_err .= '</ul>'
                                 . '<form method="post" style="margin-top:.8rem" id="form-forzar">'
                                 . '<input type="hidden" name="id_producto" value="' . $id_prod . '">'
                                 . '<input type="hidden" name="num_tandas" value="' . $tandas . '">'
                                 . '<input type="hidden" name="fecha_produccion" value="' . htmlspecialchars($fecha) . '">'
                                 . '<input type="hidden" name="observaciones" value="' . htmlspecialchars($obs) . '">'
                                 . '<input type="hidden" name="forzar_produccion" value="1">'
                                 . '<input type="hidden" name="guardar" value="1">'
                                 . '<button type="submit" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;border:none;border-radius:9px;padding:.55rem 1.1rem;font-size:.83rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;">'
                                 . '<i class="bi bi-exclamation-triangle-fill"></i> Ya saqué los ingredientes — registrar con lo que hay'
                                 . '</button></form></div>';
                        $msg_err_class = "msg-ok";
                    } else {
                        try {
                            $fecha_hora = $fecha . ' ' . date('H:i:s');
                            $dist_precios = $_POST['dist'] ?? [];

                            $resultado = $this->model->registrarProduccionConConsumos(
                                $id_prod, $id_receta, $user['id_usuario'],
                                $tandas, $unidades, $fecha_hora, $obs,
                                $ingredientes, $dist_precios, $forzar
                            );

                            $nombre_prod = $this->model->getNombreProducto($id_prod);

                            $_SESSION['msg_ok_prod'] = "Producción registrada: <strong>{$tandas} tanda(s)</strong> de <strong>" . htmlspecialchars($nombre_prod) . "</strong>"
                                    . "<br><strong>{$resultado['unidades']} unidades</strong> producidas"
                                    . "<br>Costo total: <strong>$" . number_format($resultado['costo_total'],0,',','.') . "</strong>"
                                    . "<br>Costo por unidad: <strong>$" . number_format($resultado['costo_unitario'],0,',','.') . "</strong>"
                                    . "<br>Lotes descontados correctamente.";
                            header('Location: nueva_produccion.php');
                            exit;

                        } catch (Exception $e) {
                            $msg_err = 'Error al registrar. Intenta de nuevo. (' . $e->getMessage() . ')';
                        }
                    }
                }
            }
        }

        // ── Datos para la vista ─────────────────────────────────────
        $categorias_precio = $this->model->getCategoriasPrecio();
        $productos         = $this->model->getProductosActivosConReceta();
        $prod_hoy          = $this->model->getProduccionesHoy();
        $total_hoy         = array_sum(array_column($prod_hoy, 'unidades_producidas'));
        $costo_hoy         = array_sum(array_column($prod_hoy, 'costo_total'));
        $obs_cierre        = $this->model->getUltimaSugerenciaCierre();

        // ── Renderizar ──────────────────────────────────────────────
        $page_title = 'Nueva Producción';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/produccion/nueva_produccion.php';
    }

    /**
     * Detalle de una producción específica (detalle.php)
     */
    public function detalle() {
        requerirLogin();
        $id_produccion = (int)($_GET['id'] ?? 0);

        if (!$id_produccion) {
            redirigir(APP_URL . '/modules/produccion/index.php', 'error', 'Producción no encontrada.');
        }

        $produccion = $this->model->getProduccionDetalle($id_produccion);

        if (!$produccion) {
            redirigir(APP_URL . '/modules/produccion/index.php', 'error', 'Producción no encontrada.');
        }

        $consumos    = $this->model->getConsumosLote($id_produccion);
        $costo_total = array_sum(array_column($consumos, 'costo_consumo'));
        $num_insumos = count(array_unique(array_column($consumos, 'insumo')));

        // ── Renderizar ──────────────────────────────────────────────
        $page_title = 'Detalle Producción';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/produccion/detalle.php';
    }
}
