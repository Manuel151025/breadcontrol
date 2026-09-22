<?php
// models/RecetaModel.php

class RecetaModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener lista de productos activos con su id_receta (vigente) e ingredientes
     * @return array<int, array<string, mixed>>
     */
    public function getProductos(?string $busca = null): array {
        $sql = "
            SELECT p.*, r.id_receta, COUNT(DISTINCT ri.id_insumo) AS num_ingredientes
            FROM producto p
            LEFT JOIN receta r ON r.id_producto = p.id_producto AND r.es_vigente = 1
            LEFT JOIN receta_ingrediente ri ON ri.id_receta = r.id_receta
            WHERE p.activo = 1
        ";
        if ($busca) {
            $sql .= " AND p.nombre LIKE ? ";
        }
        $sql .= " GROUP BY p.id_producto, r.id_receta ORDER BY p.nombre ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($busca ? ["%$busca%"] : []);
        return $stmt->fetchAll();
    }

    /**
     * Desactivar un producto (activo = 0)
     */
    public function desactivarProducto(int $id_producto): bool {
        $stmt = $this->pdo->prepare("UPDATE producto SET activo = 0 WHERE id_producto = ?");
        return $stmt->execute([$id_producto]);
    }

    /**
     * Obtener los datos de un producto activo
     * @return array<string, mixed>|false
     */
    public function getProducto(int $id_producto) {
        $stmt = $this->pdo->prepare("SELECT * FROM producto WHERE id_producto = ? AND activo = 1");
        $stmt->execute([$id_producto]);
        return $stmt->fetch();
    }

    /**
     * Obtener producto por nombre (activo o inactivo)
     * @return array<string, mixed>|false
     */
    public function getProductoByName(string $nombre) {
        $stmt = $this->pdo->prepare("SELECT id_producto, activo FROM producto WHERE nombre = ?");
        $stmt->execute([$nombre]);
        return $stmt->fetch();
    }

    /**
     * Crear un producto nuevo
     */
    public function crearProducto(string $nombre, string $categoria, string $unidad, int $cantidad_tanda, float $precio_venta): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO producto (nombre, categoria, unidad_produccion, cantidad_por_tanda, precio_venta, activo, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Reactivar y actualizar un producto
     */
    public function reactivarProducto(int $id_producto, string $categoria, string $unidad, int $cantidad_tanda, float $precio_venta): bool {
        $stmt = $this->pdo->prepare("
            UPDATE producto 
            SET activo = 1, categoria = ?, unidad_produccion = ?, cantidad_por_tanda = ?, precio_venta = ? 
            WHERE id_producto = ?
        ");
        return $stmt->execute([$categoria, $unidad, $cantidad_tanda, $precio_venta, $id_producto]);
    }

    /**
     * Actualizar datos del producto
     */
    public function updateProducto(int $id_producto, string $nombre, string $categoria, string $unidad, int $cantidad_tanda, float $precio_venta): bool {
        $stmt = $this->pdo->prepare("
            UPDATE producto 
            SET nombre = ?, categoria = ?, unidad_produccion = ?, cantidad_por_tanda = ?, precio_venta = ? 
            WHERE id_producto = ?
        ");
        return $stmt->execute([$nombre, $categoria, $unidad, $cantidad_tanda, $precio_venta, $id_producto]);
    }

    /**
     * Obtener el id de la receta vigente para un producto
     * @return mixed
     */
    public function getRecetaVigenteId(int $id_producto) {
        $stmt = $this->pdo->prepare("SELECT id_receta FROM receta WHERE id_producto = ? AND es_vigente = 1 LIMIT 1");
        $stmt->execute([$id_producto]);
        return $stmt->fetchColumn();
    }

    /**
     * Obtener unidad de medida de un insumo
     * @return mixed
     */
    public function getIngredienteUnidadMedida(int $id_insumo) {
        $stmt = $this->pdo->prepare("SELECT unidad_medida FROM insumo WHERE id_insumo = ?");
        $stmt->execute([$id_insumo]);
        return $stmt->fetchColumn();
    }

    /**
     * Crear una receta nueva para un producto
     */
    public function crearReceta(int $id_producto, int $id_usuario): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO receta (id_producto, id_usuario, version, es_vigente, es_ajuste_temporal, fecha_creacion)
            VALUES (?, ?, 1, 1, 0, NOW())
        ");
        $stmt->execute([$id_producto, $id_usuario]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Reemplaza los ingredientes de una receta: borra los que tenía y escribe los nuevos.
     *
     * Antes eran dos métodos sueltos que el controlador llamaba en fila, y eso tenía
     * dos fallos que se tapaban entre sí:
     *
     *   - El INSERT no nombraba `unidad`, que es NOT NULL y no tiene valor por
     *     defecto. En producción (modo estricto) MySQL rechazaba la fila con el
     *     error 1364. En un XAMPP sin modo estricto pasaba sin quejarse, así que
     *     el fallo solo se veía en el sitio publicado.
     *   - El borrado no estaba en una transacción con las inserciones. Al fallar
     *     el INSERT, el DELETE ya estaba hecho: **la receta se quedaba sin
     *     ingredientes**. Quien editaba una receta la perdía entera.
     *
     * Si ya hay una transacción abierta se usa un SAVEPOINT, para que sea atómico
     * tanto si lo llama el controlador (sin transacción) como si lo envuelve otra
     * operación o una prueba.
     *
     * @param array<int, array{id_insumo: int, cantidad: float, unidad: string, aplica_merma: int, notas: string|null}> $ingredientes
     */
    public function guardarIngredientesReceta(int $id_receta, array $ingredientes): void {
        $anidada = $this->pdo->inTransaction();
        if ($anidada) {
            $this->pdo->exec('SAVEPOINT ingredientes_receta');
        } else {
            $this->pdo->beginTransaction();
        }

        try {
            $this->pdo->prepare("DELETE FROM receta_ingrediente WHERE id_receta = ?")->execute([$id_receta]);

            $stmt = $this->pdo->prepare("
                INSERT INTO receta_ingrediente (id_receta, id_insumo, cantidad, unidad, aplica_merma, notas)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            foreach ($ingredientes as $ing) {
                $stmt->execute([
                    $id_receta,
                    $ing['id_insumo'],
                    $ing['cantidad'],
                    $ing['unidad'],
                    $ing['aplica_merma'],
                    $ing['notas'],
                ]);
            }

            if ($anidada) {
                $this->pdo->exec('RELEASE SAVEPOINT ingredientes_receta');
            } else {
                $this->pdo->commit();
            }
        } catch (Throwable $e) {
            if ($anidada) {
                $this->pdo->exec('ROLLBACK TO SAVEPOINT ingredientes_receta');
            } else {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Obtener ingredientes de una receta específica con cálculo de cant_mostrar
     * @return array<int, array<string, mixed>>
     */
    public function getIngredientesReceta(int $id_receta): array {
        $stmt = $this->pdo->prepare("
            SELECT ri.*, i.nombre AS nombre_insumo, i.unidad_medida, i.es_harina,
                   CASE WHEN i.unidad_medida IN ('kg','L') THEN ri.cantidad*1000 ELSE ri.cantidad END AS cant_mostrar
            FROM receta_ingrediente ri
            INNER JOIN insumo i ON i.id_insumo = ri.id_insumo
            WHERE ri.id_receta = ? ORDER BY i.nombre
        ");
        $stmt->execute([$id_receta]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener todos los insumos activos para el selector
     * @return array<int, array<string, mixed>>
     */
    public function getInsumosActivos(): array {
        return $this->pdo->query("SELECT id_insumo, nombre, unidad_medida, es_harina FROM insumo WHERE activo = 1 ORDER BY nombre")->fetchAll();
    }

    /**
     * Obtener categorías de precios activas
     * @return array<int, array<string, mixed>>
     */
    public function getCategoriasPrecio(): array {
        return $this->pdo->query("SELECT * FROM categoria_precio WHERE activo = 1 ORDER BY precio_unitario")->fetchAll();
    }

    /**
     * Verificar si ya existe variedad con el mismo nombre y categoría
     * @return array<string, mixed>|false
     */
    public function getVariedadPanExistente(string $nombre, int $id_cat) {
        $stmt = $this->pdo->prepare("SELECT id_variedad FROM variedad_pan WHERE nombre = ? AND id_categoria_precio = ? AND activo = 1");
        $stmt->execute([$nombre, $id_cat]);
        return $stmt->fetch();
    }

    /**
     * Crear variedad de pan
     */
    public function crearVariedadPan(int $id_cat, string $nombre, ?string $img_path): bool {
        $stmt = $this->pdo->prepare("INSERT INTO variedad_pan (id_categoria_precio, nombre, imagen) VALUES (?, ?, ?)");
        return $stmt->execute([$id_cat, $nombre, $img_path]);
    }

    /**
     * Actualizar variedad de pan
     */
    public function updateVariedadPan(int $id_var, string $nombre, ?string $img_path, bool $update_img = false): bool {
        if ($update_img) {
            $stmt = $this->pdo->prepare("UPDATE variedad_pan SET nombre = ?, imagen = ? WHERE id_variedad = ?");
            return $stmt->execute([$nombre, $img_path, $id_var]);
        } else {
            $stmt = $this->pdo->prepare("UPDATE variedad_pan SET nombre = ? WHERE id_variedad = ?");
            return $stmt->execute([$nombre, $id_var]);
        }
    }

    /**
     * Desactivar variedad de pan (activo = 0)
     */
    public function desactivarVariedadPan(int $id_var): bool {
        $stmt = $this->pdo->prepare("UPDATE variedad_pan SET activo = 0 WHERE id_variedad = ?");
        return $stmt->execute([$id_var]);
    }

    /**
     * Obtener todas las variedades de pan vigentes
     * @return array<int, array<string, mixed>>
     */
    public function getVariedadesPan(): array {
        return $this->pdo->query("
            SELECT v.*, cp.nombre AS cat_nombre, cp.precio_unitario 
            FROM variedad_pan v 
            INNER JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio 
            WHERE v.activo = 1 
            ORDER BY cp.precio_unitario, v.nombre
        ")->fetchAll();
    }
}
