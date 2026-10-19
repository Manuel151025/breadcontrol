<?php
// controllers/RecetaController.php

require_once __DIR__ . '/../models/RecetaModel.php';

class RecetaController {
    private RecetaModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new RecetaModel($pdo);
    }

    /**
     * Catálogo principal de Recetas (index.php)
     */
    public function index(): void {
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
     * Lee y valida el formulario de producto (crear y editar comparten campos).
     *
     * Ni la cantidad por tanda ni el precio tenían tope: una cifra que no cabía
     * en la columna hacía fallar el guardado con un «Error al guardar» sin más
     * explicación. La cantidad admite 0, que el formulario ofrece para «varía».
     * Los dos campos son type="number", así que el punto es decimal.
     *
     * @param list<string> $errores se le añaden los problemas encontrados
     * @return array{0: string, 1: string, 2: string, 3: int, 4: float}
     */
    private static function leerProducto(array &$errores): array {
        $nombre    = trim(post_texto('nombre'));
        $categoria = in_array(post_texto('categoria'), ['sal', 'dulce', 'especial'], true) ? post_texto('categoria') : 'sal';
        $unidad    = trim(post_texto('unidad_produccion'));
        $cantidad  = numero_en_rango(post_texto('cantidad_por_tanda') ?: '0', 0, 100000);
        $precio    = numero_en_rango(str_replace(['$', ' '], '', post_texto('precio_venta')) ?: '0', 0, 10000000);

        $reglas = [
            'El nombre es obligatorio.'                                          => $nombre === '',
            'El nombre del producto no puede superar los 100 caracteres.'        => !texto_cabe($nombre, 100),
            'La unidad de producción es obligatoria.'                            => $unidad === '',
            'La unidad de producción no puede superar los 20 caracteres.'        => !texto_cabe($unidad, 20),
            'La cantidad por tanda debe ser un número entero entre 0 y 100.000.' => $cantidad === null || $cantidad != (int) $cantidad,
            'El precio de venta debe ser un número entre $0 y $10.000.000.'      => $precio === null,
        ];
        foreach (array_keys(array_filter($reglas)) as $mensaje) {
            $errores[] = $mensaje;
        }

        return [$nombre, $categoria, $unidad, (int) $cantidad, (float) $precio];
    }

    /**
     * Crear nuevo producto (crear_producto.php)
     */
    public function crearProducto(): void {
        requerirPropietario();
        $errores = [];
        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('crear_producto.php?err=csrf');
            [$nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta] = self::leerProducto($errores);

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
    public function editarProducto(): void {
        requerirPropietario();
        $id_producto = (int)($_GET['id'] ?? 0);
        $errores    = [];
        $msg_ok     = '';

        // Cargar producto
        $producto = $this->model->getProducto($id_producto);
        if (!$producto) {
            redirigir(APP_URL . '/modules/recetas/index.php');
        }

        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('editar_producto.php?id=' . $id_producto . '&err=csrf');
            [$nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta] = self::leerProducto($errores);

            // El nombre es único en la tabla: renombrar a uno que ya existe
            // terminaba en «Error al actualizar el producto» sin decir por qué.
            if (empty($errores)) {
                $mismo_nombre = $this->model->getProductoByName($nombre);
                if ($mismo_nombre && (int) $mismo_nombre['id_producto'] !== $id_producto) {
                    $errores[] = 'Ya existe otro producto con el nombre "' . htmlspecialchars($nombre) . '"'
                        . ((int) $mismo_nombre['activo'] === 1 ? '.' : ' (desactivado).');
                }
            }

            if (empty($errores)) {
                try {
                    $this->model->updateProducto($id_producto, $nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta);
                    $producto = $this->model->getProducto($id_producto) ?: $producto;
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
    public function editarReceta(): void {
        requerirPropietario();
        $user = usuarioActual();
        $id_producto = (int)($_GET['id'] ?? 0);
        $errores     = [];

        // Cargar producto
        $producto = $this->model->getProducto($id_producto);
        if (!$producto) {
            redirigir(APP_URL . '/modules/recetas/index.php');
        }

        if (($_GET['err'] ?? '') === 'csrf') {
            $errores[] = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('editar_receta.php?id=' . $id_producto . '&err=csrf');
            $ids_insumo   = $_POST['id_insumo']   ?? [];
            $cantidades   = $_POST['cantidad']    ?? [];
            $notas        = $_POST['notas']       ?? [];
            $aplica_merma = $_POST['aplica_merma'] ?? [];

            $validos = [];
            foreach ($ids_insumo as $i => $id_ins) {
                $id_ins  = (int)$id_ins;
                // Letras, negativos, INF o cifras que no caben cuentan como vacío.
                $cant_g  = numero_en_rango(is_string($cantidades[$i] ?? null) ? $cantidades[$i] : '', 0, 10000000) ?? 0.0;
                $nota    = trim(is_string($notas[$i] ?? null) ? $notas[$i] : '');
                if (!texto_cabe($nota, 255)) {
                    $errores[] = 'Las notas de cada ingrediente no pueden superar los 255 caracteres.';
                    break;
                }
                $merma   = in_array($i, array_keys($aplica_merma)) ? 1 : 0;
                $unidad = $id_ins > 0 ? $this->model->getIngredienteUnidadMedida($id_ins) : null;
                // Sin unidad no hay insumo: la columna `unidad` es obligatoria y la
                // clave foránea rechazaría la fila de todas formas.
                if ($id_ins > 0 && $cant_g > 0 && is_string($unidad) && $unidad !== '') {
                    // El formulario pide gramos o mililitros; los insumos que se
                    // miden en kg o L se guardan en su propia unidad.
                    $cant_guardar = in_array($unidad, ['kg', 'L']) ? $cant_g / 1000 : $cant_g;
                    $validos[] = [
                        'id_insumo'    => $id_ins,
                        'cantidad'     => $cant_guardar,
                        'unidad'       => $unidad,
                        'notas'        => $nota !== '' ? $nota : null,
                        'aplica_merma' => $merma,
                    ];
                }
            }

            if (empty($validos)) {
                $errores[] = 'Agrega al menos un ingrediente con cantidad.';
            }

            if (empty($errores)) {
                try {
                    $vigente   = $this->model->getRecetaVigenteId($id_producto);
                    $id_receta = is_numeric($vigente) ? (int) $vigente : 0;

                    if ($id_receta === 0) {
                        $id_usuario = is_numeric($user['id_usuario'] ?? null) ? (int) $user['id_usuario'] : 0;
                        $id_receta  = $this->model->crearReceta($id_producto, $id_usuario);
                    }

                    // De una sola vez y en una transacción: si algo falla, la receta
                    // se queda como estaba en lugar de perder sus ingredientes.
                    $this->model->guardarIngredientesReceta($id_receta, $validos);
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
     * Sube la imagen de una variedad, si se eligió una.
     *
     * Devuelve la ruta relativa, o null si no se subió ninguna; si hubo un
     * problema deja el motivo en $error. Antes una imagen demasiado pesada o un
     * archivo que no era imagen se descartaban en silencio: la variedad se
     * guardaba sin foto y nadie sabía por qué. Además de la extensión se mira
     * el contenido, para que un archivo renombrado a .png no pase.
     */
    private static function subirImagenVariedad(string $campo, string $upload_dir, string &$error): ?string {
        $archivo = $_FILES[$campo] ?? null;
        if (!is_array($archivo) || ($archivo['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $error = self::motivoRechazoImagen($archivo);
        if ($error !== '') {
            return null;
        }

        // motivoRechazoImagen() ya comprobó que los dos son texto.
        $nombre = is_string($archivo['name'] ?? null) ? $archivo['name'] : '';
        $tmp    = is_string($archivo['tmp_name'] ?? null) ? $archivo['tmp_name'] : '';
        $ext    = strtolower(pathinfo($nombre, PATHINFO_EXTENSION));
        $fname  = 'var_' . time() . '_' . random_int(100, 999) . '.' . $ext;
        if (!move_uploaded_file($tmp, $upload_dir . $fname)) {
            $error = 'No se pudo guardar la imagen. Intenta de nuevo.';
            return null;
        }
        return 'assets/img/variedades/' . $fname;
    }

    /**
     * Por qué no se acepta un archivo subido como imagen de variedad, o '' si
     * se acepta.
     *
     * @param array<mixed> $archivo una entrada de $_FILES
     */
    private static function motivoRechazoImagen(array $archivo): string {
        $codigo = $archivo['error'] ?? null;
        if ($codigo === UPLOAD_ERR_INI_SIZE || $codigo === UPLOAD_ERR_FORM_SIZE) {
            return 'La imagen pesa demasiado. Usa una de máximo ' . ini_get('upload_max_filesize') . 'B.';
        }
        if ($codigo !== UPLOAD_ERR_OK || !is_string($archivo['tmp_name'] ?? null) || !is_string($archivo['name'] ?? null)) {
            return 'No se pudo subir la imagen. Intenta de nuevo.';
        }

        $ext  = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $info = @getimagesize($archivo['tmp_name']);
        $tipo_real = is_array($info) ? $info[2] : 0;
        $es_imagen = in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)
            && in_array($tipo_real, [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true);
        return $es_imagen ? '' : 'Solo se permiten imágenes JPG, PNG o WebP.';
    }

    /**
     * Variedades de Pan (variedades.php)
     */
    public function variedades(): void {
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

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('variedades.php?err=csrf');
        }

        // ── 1. Eliminar variedad ─────────────────────────────────────────────
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['del_var'])) {
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
                $nombre = trim(post_texto('nombre_variedad'));
                $id_cat = (int)($_POST['id_categoria'] ?? 0);

                $ids_categorias = array_column($this->model->getCategoriasPrecio(), 'id_categoria');

                if (empty($nombre) || !$id_cat) {
                    $msg_err = 'Escribe el nombre de la variedad.';
                } elseif (!texto_cabe($nombre, 100)) {
                    $msg_err = 'El nombre de la variedad no puede superar los 100 caracteres.';
                } elseif (!in_array($id_cat, $ids_categorias)) {
                    $msg_err = 'Esa categoría de precio no existe. Recarga la página.';
                } else {
                    $duplicado = $this->model->getVariedadPanExistente($nombre, $id_cat);
                    if ($duplicado) {
                        $msg_err = 'Ya existe esta variedad en esa categoría.';
                    } else {
                        $img_path = self::subirImagenVariedad('imagen', $upload_dir, $msg_err);
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
                $nombre = trim(post_texto('nombre_edit'));
                $img_path   = null;
                $update_img = false;

                if (!$id_var || $nombre === '') {
                    $msg_err = 'Escribe el nombre de la variedad.';
                } elseif (!texto_cabe($nombre, 100)) {
                    $msg_err = 'El nombre de la variedad no puede superar los 100 caracteres.';
                } else {
                    $img_path   = self::subirImagenVariedad('imagen_edit', $upload_dir, $msg_err);
                    $update_img = $img_path !== null;
                }
                if (!$msg_err) {
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
