<?php
// controllers/RecetaController.php

require_once __DIR__ . '/../models/RecetaModel.php';

class RecetaController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new RecetaModel($pdo);
    }

    /**
     * Catálogo principal de Recetas (index.php)
     */
    public function index() {
        requerirPropietario();
        $user = usuarioActual();

        // ── 1. Desactivar Producto ──────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                redirigir(APP_URL . '/modules/recetas/index.php?err=csrf');
            }
            try {
                $this->model->desactivarProducto((int)$_POST['del']);
            } catch (Exception $e) {
                log_error($e);
            }
            redirigir(APP_URL . '/modules/recetas/index.php');
        }

        // ── 2. Cargar Datos ──────────────────────────────────────────────────
        $busca = trim($_GET['q'] ?? '');
        $productos = $this->model->getProductos($busca);

        $total_productos = count($productos);
        $con_receta = count(array_filter($productos, fn($p) => $p['id_receta']));
        $sin_receta = $total_productos - $con_receta;
        $precio_prom = $total_productos > 0 ? array_sum(array_column($productos, 'precio_venta')) / $total_productos : 0;

        $msg_ok = '';
        if (!empty($_GET['ok'])) {
            $msg_ok = 'Receta guardada correctamente.';
        }

        $msg_err = '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
        }

        $page_title = 'Recetas';

        // ── 3. Cargar Vista ──────────────────────────────────────────────────
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/recetas/index.php';
    }

    /**
     * Crear nuevo producto (crear_producto.php)
     */
    public function crearProducto() {
        requerirPropietario();
        $errores = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre         = trim($_POST['nombre']              ?? '');
            $categoria      = in_array($_POST['categoria'] ?? '', ['sal','dulce','especial']) ? $_POST['categoria'] : 'sal';
            $unidad         = trim($_POST['unidad_produccion']   ?? '');
            $cantidad_tanda = (int)($_POST['cantidad_por_tanda'] ?? 0);
            $precio_venta   = (float)str_replace(['.','$',' '], '', $_POST['precio_venta'] ?? 0);

            if (empty($nombre))  $errores[] = 'El nombre es obligatorio.';
            if (mb_strlen($nombre) > 100) $errores[] = 'El nombre del producto no puede superar los 100 caracteres.';
            if (empty($unidad))  $errores[] = 'La unidad de producción es obligatoria.';
            if ($cantidad_tanda < 0) $errores[] = 'La cantidad por tanda no puede ser negativa.';
            if ($precio_venta < 0)   $errores[] = 'El precio de venta no puede ser negativo.';

            if (empty($errores)) {
                $existe = $this->model->getProductoByName($nombre);

                if ($existe && $existe['activo'] == 0) {
                    // Reactivar producto inactivo
                    try {
                        $this->model->reactivarProducto($existe['id_producto'], $categoria, $unidad, $cantidad_tanda, $precio_venta);
                        redirigir(APP_URL . '/modules/recetas/editar_receta.php?id=' . $existe['id_producto']);
                    } catch (Exception $e) {
                        log_error($e);
                        $errores[] = 'Error al reactivar el producto.';
                    }
                } elseif ($existe && $existe['activo'] == 1) {
                    $errores[] = 'Ya existe un producto activo con el nombre "' . htmlspecialchars($nombre) . '".';
                } else {
                    try {
                        $id_nuevo = $this->model->crearProducto($nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta);
                        redirigir(APP_URL . '/modules/recetas/editar_receta.php?id=' . $id_nuevo);
                    } catch (Exception $e) {
                        log_error($e);
                        $errores[] = 'Error al guardar el producto. Intenta de nuevo.';
                    }
                }
            }
        }

        $page_title = 'Nuevo Producto';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/recetas/crear_producto.php';
    }

    /**
     * Editar producto (editar_producto.php)
     */
    public function editarProducto() {
        requerirPropietario();
        $id_producto = (int)($_GET['id'] ?? 0);
        $errores    = [];
        $msg_ok     = '';

        // Cargar producto
        $producto = $this->model->getProducto($id_producto);
        if (!$producto) {
            redirigir(APP_URL . '/modules/recetas/index.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $nombre         = trim($_POST['nombre']              ?? '');
            $categoria      = in_array($_POST['categoria'] ?? '', ['sal','dulce','especial']) ? $_POST['categoria'] : 'sal';
            $unidad         = trim($_POST['unidad_produccion']   ?? '');
            $cantidad_tanda = (int)($_POST['cantidad_por_tanda'] ?? 0);
            $precio_venta   = (float)str_replace(['.','$',' '], '', $_POST['precio_venta'] ?? 0);

            if (empty($nombre)) $errores[] = 'El nombre es obligatorio.';
            if (mb_strlen($nombre) > 100) $errores[] = 'El nombre del producto no puede superar los 100 caracteres.';
            if (empty($unidad)) $errores[] = 'La unidad de producción es obligatoria.';
            if ($cantidad_tanda < 0) $errores[] = 'La cantidad por tanda no puede ser negativa.';
            if ($precio_venta < 0)   $errores[] = 'El precio de venta no puede ser negativo.';

            if (empty($errores)) {
                try {
                    $this->model->updateProducto($id_producto, $nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta);
                    $producto = $this->model->getProducto($id_producto);
                    $msg_ok   = 'Producto actualizado correctamente.';
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al actualizar el producto.';
                }
            }
        }

        // Verificar si tiene receta
        $tiene_receta = $this->model->getRecetaVigenteId($id_producto);

        $page_title = 'Editar — ' . $producto['nombre'];

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/recetas/editar_producto.php';
    }

    /**
     * Editar receta (editar_receta.php)
     */
    public function editarReceta() {
        requerirPropietario();
        $user = usuarioActual();
        $id_producto = (int)($_GET['id'] ?? 0);
        $errores     = [];

        // Cargar producto
        $producto = $this->model->getProducto($id_producto);
        if (!$producto) {
            redirigir(APP_URL . '/modules/recetas/index.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $ids_insumo   = $_POST['id_insumo']   ?? [];
            $cantidades   = $_POST['cantidad']    ?? [];
            $notas        = $_POST['notas']       ?? [];
            $aplica_merma = $_POST['aplica_merma'] ?? [];

            $validos = [];
            foreach ($ids_insumo as $i => $id_ins) {
                $id_ins  = (int)$id_ins;
                $cant_g  = (float)($cantidades[$i] ?? 0);
                $nota    = trim($notas[$i] ?? '');
                $merma   = in_array($i, array_keys($aplica_merma)) ? 1 : 0;
                if ($id_ins > 0 && $cant_g > 0) {
                    $unidad = $this->model->getIngredienteUnidadMedida($id_ins);
                    $cant_guardar = in_array($unidad, ['kg','L']) ? $cant_g / 1000 : $cant_g;
                    $validos[] = [
                        'id_insumo'   => $id_ins,
                        'cantidad'    => $cant_guardar,
                        'notas'       => $nota,
                        'aplica_merma' => $merma
                    ];
                }
            }

            if (empty($validos)) {
                $errores[] = 'Agrega al menos un ingrediente con cantidad.';
            }

            if (empty($errores)) {
                try {
                    $id_receta = $this->model->getRecetaVigenteId($id_producto);

                    if (!$id_receta) {
                        $id_receta = $this->model->crearReceta($id_producto, $user['id_usuario']);
                    }

                    $this->model->limpiarIngredientesReceta($id_receta);
                    foreach ($validos as $v) {
                        $this->model->agregarIngredienteReceta($id_receta, $v['id_insumo'], $v['cantidad'], $v['aplica_merma'], $v['notas']);
                    }
                    redirigir(APP_URL . '/modules/recetas/index.php?ok=1');
                } catch (Exception $e) {
                    log_error($e);
                    $errores[] = 'Error al guardar la receta. Intenta de nuevo.';
                }
            }
        }

        // Cargar ingredientes actuales
        $id_receta_actual = $this->model->getRecetaVigenteId($id_producto) ?: 0;
        $ingredientes = [];
        if ($id_receta_actual) {
            $ingredientes = $this->model->getIngredientesReceta($id_receta_actual);
        }

        // Todos los insumos activos
        $todos_insumos = $this->model->getInsumosActivos();

        $page_title = 'Receta — ' . $producto['nombre'];

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/recetas/editar_receta.php';
    }

    /**
     * Variedades de Pan (variedades.php)
     */
    public function variedades() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        $upload_dir = __DIR__ . '/../assets/img/variedades/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // ── 1. Eliminar variedad ─────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del_var'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                redirigir(APP_URL . '/modules/recetas/variedades.php?err=csrf');
            }
            try {
                $this->model->desactivarVariedadPan((int)$_POST['del_var']);
            } catch (Exception $e) {
                log_error($e);
            }
            redirigir(APP_URL . '/modules/recetas/variedades.php');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ── 2. Agregar variedad ──────────────────────────────────────────
            if (isset($_POST['agregar_variedad'])) {
                $nombre = trim($_POST['nombre_variedad'] ?? '');
                $id_cat = (int)($_POST['id_categoria'] ?? 0);

                if (empty($nombre) || !$id_cat) {
                    $msg_err = 'Escribe el nombre de la variedad.';
                } else {
                    $duplicado = $this->model->getVariedadPanExistente($nombre, $id_cat);
                    if ($duplicado) {
                        $msg_err = 'Ya existe esta variedad en esa categoría.';
                    } else {
                        $img_path = null;
                        if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === 0) {
                            $ext = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
                            if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                                $fname = 'var_' . time() . '_' . rand(100,999) . '.' . $ext;
                                if (move_uploaded_file($_FILES['imagen']['tmp_name'], $upload_dir . $fname)) {
                                    $img_path = 'assets/img/variedades/' . $fname;
                                }
                            } else {
                                $msg_err = 'Solo se permiten imágenes JPG, PNG o WebP.';
                            }
                        }
                        if (!$msg_err) {
                            try {
                                $this->model->crearVariedadPan($id_cat, $nombre, $img_path);
                                $msg_ok = "Variedad <strong>" . htmlspecialchars($nombre) . "</strong> agregada.";
                            } catch (Exception $e) {
                                log_error($e);
                                $msg_err = 'Error al agregar la variedad.';
                            }
                        }
                    }
                }
            }

            // ── 3. Editar variedad ───────────────────────────────────────────
            elseif (isset($_POST['editar_variedad'])) {
                $id_var = (int)($_POST['id_variedad'] ?? 0);
                $nombre = trim($_POST['nombre_edit'] ?? '');

                if ($id_var && $nombre) {
                    $img_path = null;
                    $update_img = false;
                    if (!empty($_FILES['imagen_edit']['name']) && $_FILES['imagen_edit']['error'] === 0) {
                        $ext = strtolower(pathinfo($_FILES['imagen_edit']['name'], PATHINFO_EXTENSION));
                        if (in_array($ext, ['jpg','jpeg','png','webp'])) {
                            $fname = 'var_' . time() . '_' . rand(100,999) . '.' . $ext;
                            if (move_uploaded_file($_FILES['imagen_edit']['tmp_name'], $upload_dir . $fname)) {
                                $img_path = 'assets/img/variedades/' . $fname;
                                $update_img = true;
                            }
                        }
                    }
                    try {
                        $this->model->updateVariedadPan($id_var, $nombre, $img_path, $update_img);
                        $msg_ok = "Variedad actualizada.";
                    } catch (Exception $e) {
                        log_error($e);
                        $msg_err = 'Error al actualizar la variedad.';
                    }
                }
            }
        }

        // Cargar categorías y variedades
        $categorias = $this->model->getCategoriasPrecio();
        $variedades = $this->model->getVariedadesPan();

        $var_por_cat = [];
        foreach ($variedades as $v) {
            $var_por_cat[$v['id_categoria_precio']][] = $v;
        }

        $page_title = 'Variedades de Pan';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/recetas/variedades.php';
    }
}
