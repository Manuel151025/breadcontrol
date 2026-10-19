<?php
// controllers/CompraController.php

require_once __DIR__ . '/../models/CompraModel.php';
require_once __DIR__ . '/../helpers/FormularioCompra.php';

class CompraController {
    private CompraModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new CompraModel($pdo);
    }

    /**
     * Dashboard principal de compras (index.php)
     */
    public function index(): void {
        requerirPropietario();
        $user = usuarioActual();

        // Resultado de la última compra, si se acaba de registrar (ver más abajo).
        $flash   = is_array($_SESSION['compras_ok'] ?? null) ? $_SESSION['compras_ok'] : [];
        unset($_SESSION['compras_ok']);
        $msg_ok  = is_string($flash['msg'] ?? null) ? $flash['msg'] : '';
        $msg_err = '';
        $last_id = is_int($flash['id'] ?? null) ? $flash['id'] : 0;
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('index.php?err=csrf');
        }

        // ── 1. Registrar nueva compra (POST) ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_compra'])) {
            $c = FormularioCompra::compra($_POST, date('Y-m-d'));

            if ($c['error'] !== '') {
                $msg_err = $c['error'];
            } else {
                try {
                    $res = $this->model->registrarCompra($c['id_insumo'], $c['id_proveedor'], $c['fecha'], $c['cantidad'], $c['num_bultos'], $c['precio_bulto'], $user['id_usuario']);
                    $last_id = $res['id_compra'];

                    $merma_aviso = $res['es_harina'] 
                        ? " (merma aplicada: disponible <strong>" . number_format($res['cantidad_disponible'], 3) . " kg</strong> de {$res['cantidad']} kg comprados)" 
                        : '';
                    $alerta_precio = abs($res['variacion']) >= 5 
                        ? " ⚠️ Variación de precio: {$res['variacion']}%" 
                        : '';

                    $msg_ok = "Compra registrada.<br>Lote <strong>{$res['numero_lote']}</strong> creado.";
                    if ($merma_aviso) {
                        $msg_ok .= "<br>" . ltrim($merma_aviso, " ");
                    }
                    if ($alerta_precio) {
                        $msg_ok .= "<br>" . ltrim($alerta_precio, " ");
                    }

                    // Post-Redirect-Get: con la página de resultado servida por
                    // POST, un F5 registraba la compra otra vez —otro lote, el
                    // stock y el gasto del mes duplicados—.
                    $_SESSION['compras_ok'] = ['msg' => $msg_ok, 'id' => is_numeric($last_id) ? (int) $last_id : 0];
                    header('Location: index.php');
                    exit;
                } catch (Exception $e) {
                    $msg_err = 'Error al registrar la compra: ' . mensaje_para_usuario($e);
                }
            }
        }

        // ── 2. Filtros y Búsqueda ────────────────────────────────────────────────
        $busca         = trim($_GET['q'] ?? '');
        $filtro_alerta = !empty($_GET['alerta']);
        $mes_filtro    = fecha_valida(get_texto('mes') . '-01') ? get_texto('mes') : date('Y-m');

        // ── 3. Consultar datos ───────────────────────────────────────────────────
        $compras     = $this->model->getComprasMesActual($mes_filtro, $busca, $filtro_alerta);
        $kpis        = $this->model->getKPIs();
        $insumos     = $this->model->getInsumosActivos();
        $proveedores = $this->model->getProveedoresActivos();

        // ── 4. KPIs locales para la vista ────────────────────────────────────────
        $compras_mes    = $kpis['compras_mes'];
        $total_mes      = $kpis['total_mes'];
        $alertas_precio = $kpis['alertas_precio'];
        $proveedores_n  = $kpis['proveedores_n'];

        $page_title = 'Compras';

        // ── 5. Cargar plantilla y vista ──────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/compras/index.php';
    }

    /**
     * Gestión de proveedores (proveedores.php)
     */
    public function proveedores(): void {
        requerirLogin();
        
        $errores = [];
        $editando = null;
        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        // ── 1. Guardar/Editar Proveedor (POST) ───────────────────────────────────
        // El formulario de desactivar también llega por POST: no es un guardado.
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['desactivar'])) {
            requerirPropietario();
            requerir_csrf('proveedores.php?err=csrf');
            $id_edit = (int)($_POST['id_proveedor'] ?? 0);
            $p       = FormularioCompra::proveedor($_POST);
            $errores = array_merge($errores, $p['errores']);
            $nombre  = $p['nombre'];

            // Nombre único, también entre los desactivados (índice de la tabla).
            // Registrar de nuevo uno desactivado lo reactiva con los datos nuevos,
            // igual que hace inventario con los insumos; antes la base de datos
            // lo rechazaba y solo se veía «Error al guardar el proveedor».
            if (empty($errores)) {
                $mismo_nombre = $this->model->getProveedorPorNombre($nombre, $id_edit);
                if ($mismo_nombre && ($mismo_nombre['activo'] === 1 || $id_edit > 0)) {
                    $errores[] = 'Ya existe un proveedor con ese nombre.';
                } elseif ($mismo_nombre) {
                    $id_edit = $mismo_nombre['id_proveedor'];
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->guardarProveedor($id_edit, $nombre, $p['telefono'], $p['entrega'], $p['dias'], $p['dias_visita']);
                    redirigir(APP_URL . '/modules/compras/proveedores.php', 'exito', "Proveedor <strong>" . htmlspecialchars($nombre) . "</strong> " . ($id_edit > 0 ? "actualizado." : "creado."));
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al guardar el proveedor.';
                }
            }
        }

        // ── 2. Desactivar Proveedor (POST desactivar) ────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['desactivar'])) {
            requerirPropietario();
            requerir_csrf('proveedores.php?err=csrf');
            $id_des = (int)$_POST['desactivar'];
            try {
                $this->model->desactivarProveedor($id_des);
                redirigir(APP_URL . '/modules/compras/proveedores.php', 'alerta', 'Proveedor desactivado.');
            } catch (Exception $e) {
                log_error($e);
                redirigir(APP_URL . '/modules/compras/proveedores.php', 'error', 'Error al desactivar proveedor.');
            }
        }

        // ── 3. Cargar datos para edición (GET editar) ────────────────────────────
        if (isset($_GET['editar'])) {
            $id_edit = (int)$_GET['editar'];
            $editando = $this->model->getProveedorById($id_edit);
        }

        // ── 4. Cargar listado y estadísticas ─────────────────────────────────────
        $proveedores   = $this->model->getProveedores();
        $total_provs   = count($proveedores);
        $con_visita    = count(array_filter($proveedores, fn($p) => $p['tipo_entrega'] === 'visita'));
        $con_domicilio = count(array_filter($proveedores, fn($p) => $p['tipo_entrega'] === 'domicilio'));

        $page_title = 'Proveedores';

        // ── 5. Cargar plantilla y vista ──────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/compras/proveedores.php';
    }

    /**
     * Imprimir etiquetas de lote (etiqueta_lote.php)
     */
    public function etiquetaLote(): void {
        requerirPropietario();

        // Obtener la lista de IDs a procesar
        if (!empty($_GET['ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $_GET['ids'])))));
            $ids = array_slice($ids, 0, 6); // Límite máximo de 6 etiquetas por hoja A4
        } elseif (!empty($_GET['id_compra'])) {
            $ids = [(int)$_GET['id_compra']];
        } else {
            header('Location: index.php');
            exit;
        }

        if (empty($ids)) {
            header('Location: index.php');
            exit;
        }

        try {
            $filas = $this->model->getComprasPorIds($ids);
        } catch (Exception $e) {
            log_error($e);
            $filas = [];
        }

        if (empty($filas)) {
            header('Location: index.php');
            exit;
        }

        $etiquetas = [];
        foreach ($filas as $d) {
            $stock = (float)$d['stock_actual'];
            $punto = (float)$d['punto_reposicion'];
            if ($stock <= $punto) {
                $sem = 'crit';
                $sem_lbl = 'Stock critico';
            } elseif ($stock <= $punto * 1.5) {
                $sem = 'mid';
                $sem_lbl = 'Stock bajo';
            } else {
                $sem = 'ok';
                $sem_lbl = 'Stock normal';
            }
            $var = (float)$d['variacion_precio_pct'];

            $etiquetas[] = [
                'insumo'    => htmlspecialchars($d['insumo']),
                'proveedor' => htmlspecialchars($d['proveedor']),
                'lote_num'  => htmlspecialchars($d['numero_lote'] ?? 'LOT-' . str_pad($d['id_compra'], 4, '0', STR_PAD_LEFT)),
                'fecha_fmt' => date('d/m/Y', strtotime($d['fecha_compra'])),
                'cantidad'  => formatoInteligente($d['cantidad']) . ' ' . $d['unidad_medida'],
                'unidad'    => $d['unidad_medida'],
                'precio'    => '$ ' . number_format($d['precio_unitario'], 0, ',', '.'),
                'total'     => '$ ' . number_format($d['total_pagado'], 0, ',', '.'),
                'sem'       => $sem,
                'sem_lbl'   => $sem_lbl,
                'var'       => $var,
                'var_lbl'   => $var == 0 ? '' : ($var > 0 ? "&#9650; {$var}%" : '&#9660; ' . abs($var) . '%'),
                'var_color' => $var > 0 ? '#c62828' : '#2e7d32',
                'seed'      => ($d['id_compra'] * 7) % 22,
            ];
        }

        $total_etq = count($etiquetas);
        $titulo_sub = $total_etq === 1
            ? $etiquetas[0]['insumo'] . ' &middot; Lote ' . $etiquetas[0]['lote_num']
            : $total_etq . ' compras seleccionadas';

        // Renderizar vista directa (posee su propia estructura HTML y estilos de impresión)
        require_once __DIR__ . '/../views/compras/etiqueta_lote.php';
    }
}
