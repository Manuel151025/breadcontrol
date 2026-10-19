<?php
// controllers/InventarioController.php

require_once __DIR__ . '/../models/InventarioModel.php';

class InventarioController {
    /** Mayor cantidad que admiten las columnas DECIMAL(12,3) de stock y lotes. */
    private const CANTIDAD_MAXIMA = 999999999;

    private InventarioModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new InventarioModel($pdo);
    }

    /**
     * Controlador principal para el listado de insumos (index.php)
     */
    public function index(): void {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        // Guarda CSRF única para TODAS las acciones POST de esta pantalla: se
        // valida antes de mirar qué acción se pidió, así ninguna rama nueva
        // puede quedar sin protección por olvido.
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('index.php?err=csrf');
        }

        // ── 1. Guardar/Editar insumo (POST guardar_insumo) ───────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_insumo'])) {
            $nombre  = trim(post_texto('nombre'));
            $unidad  = trim(post_texto('unidad_medida'));
            // Vacío cuenta como 0; letras, negativos o cifras que no caben, no.
            $stock   = numero_en_rango(post_texto('stock_actual') ?: '0', 0, self::CANTIDAD_MAXIMA);
            $reposi  = numero_en_rango(post_texto('punto_reposicion') ?: '0', 0, self::CANTIDAD_MAXIMA);
            $es_har  = isset($_POST['es_harina']) ? 1 : 0;
            $id_edit = (int)($_POST['id_insumo'] ?? 0);

            $unidades_validas = ['kg', 'g', 'L', 'ml', 'unidad'];

            if (!$nombre || !$unidad) {
                $msg_err = 'Nombre y unidad son obligatorios.';
            } elseif (!texto_cabe($nombre, 100)) {
                $msg_err = 'El nombre del insumo no puede superar los 100 caracteres.';
            } elseif (preg_match('/[0-9]/', $nombre)) {
                $msg_err = 'El nombre del insumo no puede contener números, solo letras.';
            } elseif (!in_array($unidad, $unidades_validas)) {
                $msg_err = 'Unidad de medida no válida.';
            } elseif ($stock === null) {
                $msg_err = 'El stock debe ser un número entre 0 y ' . number_format(self::CANTIDAD_MAXIMA, 0, ',', '.') . '.';
            } elseif ($reposi === null) {
                $msg_err = 'El punto de reposición debe ser un número entre 0 y ' . number_format(self::CANTIDAD_MAXIMA, 0, ',', '.') . '.';
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
    public function crearInsumo(): void {
        requerirPropietario();
        $errores = [];
        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('crear_insumo.php?err=csrf');
            $nombre      = limpiar(post_texto('nombre'));
            $unidad      = limpiar(post_texto('unidad_medida'));
            $es_harina   = isset($_POST['es_harina']) ? 1 : 0;
            $punto_repos = numero_en_rango(post_texto('punto_reposicion') ?: '0', 0, self::CANTIDAD_MAXIMA);

            if (empty($nombre))  $errores[] = 'El nombre es obligatorio.';
            if (!texto_cabe($nombre, 100)) $errores[] = 'El nombre no puede superar los 100 caracteres.';
            if (!in_array($unidad, ['kg', 'g', 'L', 'ml', 'unidad'], true)) $errores[] = 'Elige una unidad de medida de la lista.';
            if ($punto_repos === null) $errores[] = 'El punto de reposición debe ser un número entre 0 y ' . number_format(self::CANTIDAD_MAXIMA, 0, ',', '.') . '.';

            if (empty($errores)) {
                $check = $this->model->getInsumoPorNombre($nombre);
                if ($check) {
                    $errores[] = "Ya existe un insumo con el nombre \"$nombre\".";
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->registrarInsumo($nombre, $unidad, 0, (float) $punto_repos, $es_harina);
                    // El nombre lo escribe el usuario y el aviso se imprime como HTML:
                    // sin escapar, un insumo llamado <script>… se ejecutaría al mostrarlo.
                    redirigir(APP_URL . '/modules/inventario/index.php', 'exito', 'Insumo <strong>' . htmlspecialchars($nombre) . '</strong> creado correctamente.');
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
    public function editarInsumo(): void {
        requerirPropietario();
        $id = (int)($_GET['id'] ?? 0);
        $errores = [];

        $insumo = $this->model->getInsumoById($id);
        if (!$insumo) {
            redirigir(APP_URL . '/modules/inventario/index.php', 'error', 'Insumo no encontrado.');
        }
        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('editar_insumo.php?id=' . $id . '&err=csrf');
            $nombre      = limpiar(post_texto('nombre'));
            $unidad      = limpiar(post_texto('unidad_medida'));
            $es_harina   = isset($_POST['es_harina']) ? 1 : 0;
            $punto_repos = numero_en_rango(post_texto('punto_reposicion') ?: '0', 0, self::CANTIDAD_MAXIMA);
            $activo      = isset($_POST['activo']) ? 1 : 0;

            if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
            if (!texto_cabe($nombre, 100)) $errores[] = 'El nombre no puede superar los 100 caracteres.';
            if (!in_array($unidad, ['kg', 'g', 'L', 'ml', 'unidad'], true)) $errores[] = 'Elige una unidad de medida de la lista.';
            if ($punto_repos === null) $errores[] = 'El punto de reposición debe ser un número entre 0 y ' . number_format(self::CANTIDAD_MAXIMA, 0, ',', '.') . '.';

            if (empty($errores)) {
                $check = $this->model->getInsumoPorNombre($nombre, $id);
                if ($check) {
                    $errores[] = "Ya existe otro insumo con el nombre \"$nombre\".";
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->actualizarInsumo($id, $nombre, $unidad, (float)$insumo['stock_actual'], (float) $punto_repos, $es_harina, $activo);
                    redirigir(APP_URL . '/modules/inventario/index.php', 'exito', 'Insumo <strong>' . htmlspecialchars($nombre) . '</strong> actualizado.');
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al actualizar el insumo en el sistema.';
                }
            }

            // Si el flujo llega aquí siempre hay errores: el camino exitoso
            // termina en redirigir(), que nunca retorna.
            $insumo = array_merge($insumo, $_POST, ['es_harina' => $es_harina, 'activo' => $activo]);
        }

        $titulo = 'Editar insumo';
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/inventario/editar_insumo.php';
    }

    /**
     * Ajuste manual de inventario e historial (ajuste.php)
     */
    public function ajuste(): void {
        requerirLogin();
        $id = (int)($_GET['id'] ?? 0);
        $errores = [];

        $insumo = $this->model->getInsumoActivoById($id);
        if (!$insumo) {
            redirigir(APP_URL . '/modules/inventario/index.php', 'error', 'Insumo no encontrado.');
        }
        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('ajuste.php?id=' . $id . '&err=csrf');
            $cantidad_real = numero_en_rango(post_texto('cantidad_real'), 0, self::CANTIDAD_MAXIMA);
            $motivo        = limpiar(post_texto('motivo'));

            if ($cantidad_real === null) {
                $errores[] = 'Escribe la cantidad real que hay: un número entre 0 y ' . number_format(self::CANTIDAD_MAXIMA, 0, ',', '.') . '.';
            }
            if (empty($motivo)) {
                $errores[] = 'El motivo del ajuste es obligatorio.';
            } elseif (!texto_cabe($motivo, 255)) {
                $errores[] = 'El motivo no puede superar los 255 caracteres.';
            }

            if (empty($errores)) {
                $id_usuario = usuarioActual()['id_usuario'];
                try {
                    $res = $this->model->registrarAjusteInventario($id, $id_usuario, (float) $cantidad_real, $motivo);
                    // La unidad la escribió un usuario al crear el insumo y el aviso
                    // se imprime como HTML, así que se escapa antes de componerlo.
                    $unidad = is_string($res['unidad']) ? htmlspecialchars($res['unidad']) : '';
                    redirigir(
                        APP_URL . '/modules/inventario/index.php',
                        'exito',
                        'Ajuste registrado. Diferencia: ' . ($res['diferencia'] >= 0 ? '+' : '')
                        . formatoDecimal($res['diferencia'], 3) . ' ' . $unidad
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
