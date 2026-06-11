<?php
// controllers/CompraController.php

require_once __DIR__ . '/../models/CompraModel.php';

class CompraController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new CompraModel($pdo);
    }

    /**
     * Dashboard principal de compras (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        $last_id = 0;

        // ── 1. Registrar nueva compra (POST) ─────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_compra'])) {
            if (esHoyDomingo()) {
                $msg_err = 'No se pueden registrar compras los domingos.';
            } else {
                $id_insumo    = (int)($_POST['id_insumo'] ?? 0);
                $id_proveedor = (int)($_POST['id_proveedor'] ?? 0);
                $fecha        = $_POST['fecha_compra'] ?? date('Y-m-d');
                $cantidad     = (float)($_POST['cantidad'] ?? 0);
                $num_bultos   = max(1, (int)($_POST['num_bultos'] ?? 1));
                $precio_bulto = (float)($_POST['precio_bulto'] ?? 0);

                if (!$id_insumo || !$id_proveedor || $cantidad <= 0 || $precio_bulto <= 0) {
                    $msg_err = 'Todos los campos son obligatorios y deben ser mayores a 0.';
                } else {
                    try {
                        $res = $this->model->registrarCompra($id_insumo, $id_proveedor, $fecha, $cantidad, $num_bultos, $precio_bulto, $user['id_usuario']);
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
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al registrar la compra: ' . $e->getMessage();
                    }
                }
            }
        }

        // ── 2. Filtros y Búsqueda ────────────────────────────────────────────────
        $busca         = trim($_GET['q'] ?? '');
        $filtro_alerta = !empty($_GET['alerta']);
        $mes_filtro    = preg_match('/^\d{4}-\d{2}$/', $_GET['mes'] ?? '') ? $_GET['mes'] : date('Y-m');

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
    public function proveedores() {
        requerirLogin();
        
        $errores = [];
        $editando = null;

        // ── 1. Guardar/Editar Proveedor (POST) ───────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerirPropietario();
            $id_edit   = (int)($_POST['id_proveedor'] ?? 0);
            $nombre    = limpiar($_POST['nombre'] ?? '');
            $telefono  = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
            if (strlen($telefono) > 15) {
                $telefono = substr($telefono, 0, 15);
            }
            $entrega     = limpiar($_POST['tipo_entrega'] ?? 'domicilio');
            $dias        = (float)($_POST['dias_entrega_promedio'] ?? 0);
            $dias_visita = $entrega === 'visita'
                ? implode(',', array_map('trim', $_POST['dias_visita'] ?? []))
                : null;

            if (empty($nombre)) {
                $errores[] = 'El nombre es obligatorio.';
            }
            if ($entrega === 'visita' && empty($dias_visita)) {
                $errores[] = 'Selecciona al menos un día de visita.';
            }

            if (empty($errores)) {
                try {
                    $this->model->guardarProveedor($id_edit, $nombre, $telefono, $entrega, $dias, $dias_visita);
                    redirigir(APP_URL . '/modules/compras/proveedores.php', 'exito', "Proveedor <strong>" . htmlspecialchars($nombre) . "</strong> " . ($id_edit > 0 ? "actualizado." : "creado."));
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al guardar el proveedor.';
                }
            }
        }

        // ── 2. Desactivar Proveedor (GET desactivar) ─────────────────────────────
        if (isset($_GET['desactivar'])) {
            requerirPropietario();
            $id_des = (int)$_GET['desactivar'];
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
    public function etiquetaLote() {
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
