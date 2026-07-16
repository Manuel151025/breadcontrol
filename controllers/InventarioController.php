<?php
// controllers/InventarioController.php

require_once __DIR__ . '/../models/InventarioModel.php';

class InventarioController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new InventarioModel($pdo);
    }

    /**
     * Controlador principal para el listado de insumos (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        // ── 1. Guardar/Editar insumo (POST guardar_insumo) ───────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_insumo'])) {
            $nombre  = trim($_POST['nombre'] ?? '');
            $unidad  = trim($_POST['unidad_medida'] ?? '');
            $stock   = (float)($_POST['stock_actual'] ?? 0);
            $reposi  = (float)($_POST['punto_reposicion'] ?? 0);
            $es_har  = isset($_POST['es_harina']) ? 1 : 0;
            $id_edit = (int)($_POST['id_insumo'] ?? 0);

            $unidades_validas = ['kg', 'g', 'L', 'ml', 'unidad'];

            if (!$nombre || !$unidad) {
                $msg_err = 'Nombre y unidad son obligatorios.';
            } elseif (preg_match('/[0-9]/', $nombre)) {
                $msg_err = 'El nombre del insumo no puede contener números, solo letras.';
            } elseif (!in_array($unidad, $unidades_validas)) {
                $msg_err = 'Unidad de medida no válida.';
            } elseif ($id_edit) {
                // Modo Edición
                $chk = $this->model->getInsumoPorNombre($nombre, $id_edit);
                if ($chk) {
                    $msg_err = "Ya existe otro insumo con el nombre \"" . htmlspecialchars($nombre) . "\". Elige un nombre diferente.";
                } else {
                    try {
                        $this->model->actualizarInsumo($id_edit, $nombre, $unidad, $stock, $reposi, $es_har);
                        redirigir(APP_URL . '/modules/inventario/index.php', 'exito', "Insumo <strong>" . htmlspecialchars($nombre) . "</strong> actualizado correctamente.");
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al actualizar el insumo.';
                    }
                }
            } else {
                // Modo Registro Nuevo o Reactivación
                $existe = $this->model->getInsumoPorNombre($nombre);
                if ($existe) {
                    try {
                        $this->model->actualizarInsumo($existe['id_insumo'], $nombre, $unidad, $stock, $reposi, $es_har, 1);
                        $msg_ok = "Insumo reactivado.<br><strong>" . htmlspecialchars($nombre) . "</strong> actualizado correctamente.";
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al reactivar el insumo.';
                    }
                } else {
                    try {
                        $this->model->registrarInsumo($nombre, $unidad, $stock, $reposi, $es_har);
                        $msg_ok = 'Insumo registrado correctamente.';
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al registrar el insumo.';
                    }
                }
            }
        }

        // ── 2. Desactivar insumo individual (POST del) ─────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                header('Location: index.php?err=csrf');
                exit;
            }
            $id_del = (int)$_POST['del'];
            try {
                $this->model->desactivarInsumo($id_del);
            } catch (Exception $e) {
                log_error($e);
            }
            header('Location: index.php');
            exit;
        }

        // ── 3. Eliminar seleccionados en lote (POST eliminar_seleccionados) ───────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['eliminar_seleccionados'])) {
            $ids = $_POST['ids_eliminar'] ?? [];
            if (!empty($ids)) {
                try {
                    $this->model->desactivarMultiplesInsumos($ids);
                    $msg_ok = count($ids) . ' insumo(s) eliminado(s) correctamente.';
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'Error al eliminar los insumos seleccionados.';
                }
            }
        }

        // ── 4. Cargar datos para edición inline (GET edit) ────────────────────────
        $editando = null;
        if (!empty($_GET['edit'])) {
            $id_edit = (int)$_GET['edit'];
            $editando = $this->model->getInsumoById($id_edit);
        }

        // ── 5. Filtros y Búsqueda ────────────────────────────────────────────────
        $busca         = trim($_GET['q'] ?? '');
        $filtro_alerta = !empty($_GET['alerta']);

        // ── 6. Cargar listado y KPIs ─────────────────────────────────────────────
        $insumos = $this->model->getInsumosList($busca, $filtro_alerta);
        
        $kpis = $this->model->getKPIs();
        $total_insumos    = $kpis['total_insumos'];
        $alertas_count    = $kpis['alertas_count'];
        $lotes_activos    = $kpis['lotes_activos'];
        $valor_inventario = $kpis['valor_inventario'];

        $page_title = 'Inventario';

        // ── 7. Renderizar layouts y vistas ───────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/inventario/index.php';
    }

    /**
     * Creación de insumo clásica (crear_insumo.php)
     */
    public function crearInsumo() {
        requerirPropietario();
        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = limpiar($_POST['nombre'] ?? '');
            $unidad      = limpiar($_POST['unidad_medida'] ?? '');
            $es_harina   = isset($_POST['es_harina']) ? 1 : 0;
            $punto_repos = (float)($_POST['punto_reposicion'] ?? 0);

            if (empty($nombre))  $errores[] = 'El nombre es obligatorio.';
            if (empty($unidad))  $errores[] = 'La unidad de medida es obligatoria.';
            if ($punto_repos < 0) $errores[] = 'El punto de reposición no puede ser negativo.';

            if (empty($errores)) {
                $check = $this->model->getInsumoPorNombre($nombre);
                if ($check) {
                    $errores[] = "Ya existe un insumo con el nombre \"$nombre\".";
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->registrarInsumo($nombre, $unidad, 0, $punto_repos, $es_harina);
                    redirigir(APP_URL . '/modules/inventario/index.php', 'exito', "Insumo <strong>$nombre</strong> creado correctamente.");
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al registrar el insumo en el sistema.';
                }
            }
        }

        $titulo = 'Nuevo insumo';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/inventario/crear_insumo.php';
    }

    /**
     * Edición de insumo clásica (editar_insumo.php)
     */
    public function editarInsumo() {
        requerirPropietario();
        $id = (int)($_GET['id'] ?? 0);
        $errores = [];

        $insumo = $this->model->getInsumoById($id);
        if (!$insumo) {
            redirigir(APP_URL . '/modules/inventario/index.php', 'error', 'Insumo no encontrado.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre      = limpiar($_POST['nombre'] ?? '');
            $unidad      = limpiar($_POST['unidad_medida'] ?? '');
            $es_harina   = isset($_POST['es_harina']) ? 1 : 0;
            $punto_repos = (float)($_POST['punto_reposicion'] ?? 0);
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
            if (empty($unidad)) $errores[] = 'La unidad de medida es obligatoria.';

            if (empty($errores)) {
                $check = $this->model->getInsumoPorNombre($nombre, $id);
                if ($check) {
                    $errores[] = "Ya existe otro insumo con el nombre \"$nombre\".";
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->actualizarInsumo($id, $nombre, $unidad, (float)$insumo['stock_actual'], $punto_repos, $es_harina, $activo);
                    redirigir(APP_URL . '/modules/inventario/index.php', 'exito', "Insumo <strong>$nombre</strong> actualizado.");
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al actualizar el insumo en el sistema.';
                }
            }

            if (!empty($errores)) {
                $insumo = array_merge($insumo, $_POST, ['es_harina' => $es_harina, 'activo' => $activo]);
            }
        }

        $titulo = 'Editar insumo';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/inventario/editar_insumo.php';
    }

    /**
     * Ajuste manual de inventario e historial (ajuste.php)
     */
    public function ajuste() {
        requerirLogin();
        $id = (int)($_GET['id'] ?? 0);
        $errores = [];

        $insumo = $this->model->getInsumoActivoById($id);
        if (!$insumo) {
            redirigir(APP_URL . '/modules/inventario/index.php', 'error', 'Insumo no encontrado.');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $cantidad_real = (float)($_POST['cantidad_real'] ?? -1);
            $motivo        = limpiar($_POST['motivo'] ?? '');

            if ($cantidad_real < 0) {
                $errores[] = 'La cantidad real no puede ser negativa.';
            }
            if (empty($motivo)) {
                $errores[] = 'El motivo del ajuste es obligatorio.';
            }

            if (empty($errores)) {
                $id_usuario = usuarioActual()['id_usuario'];
                try {
                    $res = $this->model->registrarAjusteInventario($id, $id_usuario, $cantidad_real, $motivo);
                    redirigir(
                        APP_URL . '/modules/inventario/index.php',
                        'exito',
                        "Ajuste registrado. Diferencia: " . ($res['diferencia'] >= 0 ? '+' : '') . formatoDecimal($res['diferencia'], 3) . " {$res['unidad']}"
                    );
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al guardar el ajuste. Intenta de nuevo.';
                }
            }
        }

        $ajustes = $this->model->getHistorialAjustes($id);

        $titulo = 'Ajuste de inventario — ' . $insumo['nombre'];
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/inventario/ajuste.php';
    }
}
