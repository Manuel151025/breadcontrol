<?php
// models/VentaModel.php

class VentaModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene el detalle de un pedido (variedades) y cliente asociado en formato JSON/Array para AJAX
     */
    public function getDetalleVentaAjax(int $id_v): array {
        $det = $this->pdo->prepare("
            SELECT vd.id_variedad, vd.cantidad, vd.napa,
                   COALESCE(vd.bonificacion,0) AS bonificacion,
                   vd.precio_unitario,
                   vp.nombre, vp.imagen, vp.id_categoria_precio
            FROM venta_detalle vd
            INNER JOIN variedad_pan vp ON vp.id_variedad = vd.id_variedad
            WHERE vd.id_venta = ?
        ");
        $det->execute([$id_v]);
        $items = $det->fetchAll(PDO::FETCH_ASSOC);

        $venta = $this->pdo->prepare("SELECT id_cliente FROM venta WHERE id_venta = ?");
        $venta->execute([$id_v]);
        $v_info = $venta->fetch();

        return [
            'items'      => $items,
            'id_cliente' => $v_info['id_cliente'] ?? 0
        ];
    }

    /**
     * Obtiene todas las variedades activas (para bonificaciones en AJAX)
     */
    public function getAllVariedadesAjax(): array {
        return $this->pdo->query("
            SELECT vp.id_variedad, vp.nombre, vp.imagen, vp.id_categoria_precio,
                   cp.nombre AS cat_nombre, cp.precio_unitario
            FROM variedad_pan vp
            INNER JOIN categoria_precio cp ON cp.id_categoria = vp.id_categoria_precio
            WHERE vp.activo = 1
            ORDER BY cp.precio_unitario, vp.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene variedades activas por categoría para AJAX
     */
    public function getVariedadesPorCategoriaAjax(int $id_cat): array {
        $vars = $this->pdo->prepare("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE id_categoria_precio = ? AND activo = 1 ORDER BY nombre");
        $vars->execute([$id_cat]);
        return $vars->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calcula el stock disponible HOY para una categoría de precio:
     *   producido hoy (produccion_precio/produccion, fecha_produccion = hoy)
     *   - vendido hoy vía venta rápida (venta.unidades_vendidas, fecha_hora = hoy)
     *   - vendido hoy vía pedido detallado (venta_detalle: cantidad+napa+bonificacion,
     *     unido a venta para filtrar por fecha_hora = hoy, porque venta_detalle no
     *     tiene columna propia de fecha).
     *
     * Es el único lugar donde vive esta fórmula; getCategoriasPrecio(),
     * registrarPedidoDetallado(), editarPedidoDetallado() y la validación de
     * "venta rápida" en VentaController la reutilizan para no tener copias.
     *
     * @param int      $idCategoria  id_categoria de categoria_precio a evaluar
     * @param int|null $excluirVenta id_venta a excluir del cálculo de "ya vendido"
     *                                (usar al editar una venta/pedido existente,
     *                                para no restar su propia reserva previa)
     */
    public function getStockDisponibleHoy(int $idCategoria, ?int $excluirVenta = null): int {
        $exclVenta   = $excluirVenta !== null ? " AND v.id_venta != ?"  : "";
        $exclDetalle = $excluirVenta !== null ? " AND vd.id_venta != ?" : "";

        $stmt = $this->pdo->prepare("
            SELECT
              COALESCE((SELECT SUM(pp.unidades)
                        FROM produccion_precio pp
                        INNER JOIN produccion p ON p.id_produccion = pp.id_produccion
                        WHERE pp.id_categoria_precio = ? AND DATE(p.fecha_produccion) = CURDATE()), 0)
              - COALESCE((SELECT SUM(v.unidades_vendidas)
                          FROM venta v
                          WHERE v.id_categoria_precio = ? AND DATE(v.fecha_hora) = CURDATE(){$exclVenta}), 0)
              - COALESCE((SELECT SUM(vd.cantidad + vd.napa + vd.bonificacion)
                          FROM venta_detalle vd
                          INNER JOIN venta v2 ON v2.id_venta = vd.id_venta
                          INNER JOIN variedad_pan vp ON vp.id_variedad = vd.id_variedad
                          WHERE vp.id_categoria_precio = ? AND DATE(v2.fecha_hora) = CURDATE(){$exclDetalle}), 0)
              AS disponible
        ");

        $params = [$idCategoria, $idCategoria];
        if ($excluirVenta !== null) $params[] = $excluirVenta;
        $params[] = $idCategoria;
        if ($excluirVenta !== null) $params[] = $excluirVenta;

        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtiene las categorías de precio activas y su stock disponible hoy
     */
    public function getCategoriasPrecio(): array {
        $categorias = $this->pdo->query("
            SELECT * FROM categoria_precio WHERE activo = 1 ORDER BY precio_unitario
        ")->fetchAll(PDO::FETCH_ASSOC);

        foreach ($categorias as &$cat) {
            $cat['stock_hoy'] = $this->getStockDisponibleHoy((int)$cat['id_categoria']);
        }
        unset($cat);

        return $categorias;
    }

    /**
     * Obtiene los clientes de tipo tienda activos
     */
    public function getClientesTienda(): array {
        return $this->pdo->query("SELECT id_cliente, nombre FROM cliente WHERE activo = 1 AND tipo = 'tienda' ORDER BY nombre")->fetchAll();
    }

    /**
     * Obtiene las ventas registradas el día de hoy
     */
    public function getVentasHoy(): array {
        return $this->pdo->query("
            SELECT v.id_venta, v.unidades_vendidas, v.precio_unitario, v.total_venta,
                   v.tipo_salida, v.fecha_hora, v.id_categoria_precio, v.id_cliente,
                   COALESCE(v.unidades_bonificacion,0) AS bonificacion,
                   COALESCE(cp.nombre, CONCAT('Producto #', v.id_producto)) AS categoria,
                   COALESCE(c.nombre, 'Mostrador') AS cliente
            FROM venta v
            LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio
            LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
            WHERE DATE(v.fecha_hora) = CURDATE()
            ORDER BY v.fecha_hora DESC
        ")->fetchAll();
    }

    /**
     * Obtiene los IDs de las ventas que poseen detalles estructurados (venta_detalle)
     */
    public function getVentasConDetalleIds(): array {
        try {
            $vcd = $this->pdo->query("SELECT DISTINCT id_venta FROM venta_detalle");
            return $vcd ? $vcd->fetchAll(PDO::FETCH_COLUMN) : [];
        } catch (Exception $e) {
            return []; // Retorna vacío si la tabla no existiera
        }
    }

    /**
     * Obtiene el monto facturado total el día anterior
     */
    public function getVentasAyerTotal(): float {
        return (float)$this->pdo->query("
            SELECT COALESCE(SUM(total_venta), 0)
            FROM venta
            WHERE tipo_salida = 'venta' AND DATE(fecha_hora) = DATE_SUB(CURDATE(), INTERVAL 1 DAY)
        ")->fetchColumn();
    }

    /**
     * Registra una venta rápida de mostrador/tienda
     */
    public function registrarVentaRapida(int $id_cat, string $tipo_salida, ?int $id_cliente, int $id_usuario, int $cantidad, float $precio, float $total, int $bonificacion): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO venta (id_categoria_precio, tipo_salida, id_cliente, id_usuario, fecha_hora, unidades_vendidas, precio_unitario, total_venta, unidades_bonificacion)
            VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, ?)
        ");
        return $stmt->execute([
            $id_cat ?: null,
            $tipo_salida,
            $id_cliente,
            $id_usuario,
            $cantidad,
            $precio,
            $total,
            $bonificacion
        ]);
    }

    /**
     * Registra un pedido detallado en el carrito (maestro y detalle).
     *
     * Revalida contra la base de datos, dentro de una única transacción con rollback:
     *  - cantidad de cada ítem (entero, 1-999) del carrito y de las bonificaciones/ñapa,
     *  - precio real de cada variedad (nunca se confía en el precio enviado por el cliente),
     *  - stock disponible HOY por categoría de precio (ver getStockDisponibleHoy()).
     * Si cualquier ítem no pasa la validación, se aborta todo el pedido (no se guarda nada parcial).
     *
     * @return array ['id_venta'=>int,'total_variedades'=>int,'total_unidades'=>int,'total_dinero'=>float,'bonus_units'=>int]
     */
    public function registrarPedidoDetallado(?int $id_cliente, int $id_usuario, array $cart, array $bonif_items): array {
        $this->pdo->beginTransaction();
        try {
            // Consulta reutilizada para revalidar precio real y categoría de cada variedad
            $stmt_info = $this->pdo->prepare("
                SELECT cp.precio_unitario, vp.id_categoria_precio
                FROM variedad_pan vp
                JOIN categoria_precio cp ON vp.id_categoria_precio = cp.id_categoria
                WHERE vp.id_variedad = ? AND vp.activo = 1
            ");

            // 1. Validar y recalcular carrito principal (productos cobrados)
            $total_dinero = 0.0;
            $cart_validado = [];
            $demanda_por_categoria = [];

            foreach ($cart as $item) {
                $id_var = (int)($item['id_variedad'] ?? 0);
                $cant   = (int)($item['cantidad'] ?? 0);

                if ($id_var <= 0 || $cant <= 0 || $cant > 999) {
                    throw new Exception("Cantidad inválida en el carrito: debe ser un número entero entre 1 y 999.");
                }

                $stmt_info->execute([$id_var]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                if (!$info) {
                    throw new Exception("Uno de los productos del carrito no es válido o está inactivo.");
                }

                $precio_real = (float)$info['precio_unitario'];
                $id_cat      = (int)$info['id_categoria_precio'];

                $cart_validado[] = [
                    'id_variedad' => $id_var,
                    'cantidad'    => $cant,
                    'precio'      => $precio_real,
                ];
                $total_dinero += $cant * $precio_real;
                $demanda_por_categoria[$id_cat] = ($demanda_por_categoria[$id_cat] ?? 0) + $cant;
            }

            if (empty($cart_validado)) {
                throw new Exception("El carrito no contiene productos válidos.");
            }

            // 2. Validar y recalcular bonificaciones/ñapas (mismo origen de datos que el carrito,
            //    así que se validan igual: cantidad y precio nunca se toman del cliente sin revisar)
            $bonif_validado = [];
            $bonus_units = 0;

            foreach ($bonif_items as $bi) {
                $cant = (int)($bi['cantidad'] ?? 0);
                if ($cant <= 0) continue; // fila vacía del formulario, se ignora

                $id_var = (int)($bi['id_variedad'] ?? 0);
                if ($id_var <= 0 || $cant > 999) {
                    throw new Exception("Cantidad inválida en la bonificación/ñapa: debe ser un número entero entre 1 y 999.");
                }

                $stmt_info->execute([$id_var]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                if (!$info) {
                    throw new Exception("Uno de los productos de bonificación/ñapa no es válido o está inactivo.");
                }

                $precio_real = (float)$info['precio_unitario'];
                $id_cat      = (int)$info['id_categoria_precio'];

                $bonif_validado[] = [
                    'id_variedad' => $id_var,
                    'cantidad'    => $cant,
                    'precio'      => $precio_real,
                ];
                $bonus_units += $cant;
                $demanda_por_categoria[$id_cat] = ($demanda_por_categoria[$id_cat] ?? 0) + $cant;
            }

            $total_und   = array_sum(array_column($cart_validado, 'cantidad'));
            $und_totales = $total_und + $bonus_units;

            // 3. Validar stock disponible HOY por categoría de precio
            $stmt_cat_nombre = $this->pdo->prepare("SELECT nombre FROM categoria_precio WHERE id_categoria = ?");

            foreach ($demanda_por_categoria as $id_cat => $unidades_pedidas) {
                $disponible = $this->getStockDisponibleHoy($id_cat);

                if ($unidades_pedidas > $disponible) {
                    $stmt_cat_nombre->execute([$id_cat]);
                    $nombre_cat = $stmt_cat_nombre->fetchColumn() ?: "categoría #$id_cat";
                    throw new Exception("Stock insuficiente para {$nombre_cat}: disponible {$disponible}, solicitado {$unidades_pedidas}.");
                }
            }

            // 4. Insertar maestro venta
            $stmt_v = $this->pdo->prepare("
                INSERT INTO venta (tipo_salida, id_cliente, id_usuario, fecha_hora, unidades_vendidas, precio_unitario, total_venta, unidades_bonificacion)
                VALUES ('venta', ?, ?, NOW(), ?, 0, ?, ?)
            ");
            $stmt_v->execute([
                $id_cliente > 0 ? $id_cliente : null,
                $id_usuario,
                $und_totales,
                $total_dinero,
                $bonus_units
            ]);
            $id_venta = (int)$this->pdo->lastInsertId();

            // 5. Insertar venta_detalle para productos cobrados
            $stmt_vd = $this->pdo->prepare("
                INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario)
                VALUES (?, ?, ?, 0, 0, ?)
            ");
            foreach ($cart_validado as $item) {
                $stmt_vd->execute([$id_venta, $item['id_variedad'], $item['cantidad'], $item['precio']]);
            }

            // 6. Insertar venta_detalle para bonificaciones
            if (!empty($bonif_validado)) {
                $stmt_vdb = $this->pdo->prepare("
                    INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario)
                    VALUES (?, ?, 0, 0, ?, ?)
                ");
                foreach ($bonif_validado as $bi) {
                    $stmt_vdb->execute([$id_venta, $bi['id_variedad'], $bi['cantidad'], $bi['precio']]);
                }
            }

            $this->pdo->commit();

            return [
                'id_venta'         => $id_venta,
                'total_variedades' => count($cart_validado),
                'total_unidades'   => $und_totales,
                'total_dinero'     => $total_dinero,
                'bonus_units'      => $bonus_units,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Edita un pedido detallado borrando sus detalles y reinsertando.
     *
     * Aplica la misma revalidación de cantidad, precio y stock que registrarPedidoDetallado(),
     * dentro de una única transacción con rollback. El stock disponible se calcula excluyendo
     * la propia reserva previa de este pedido (id_v), para no bloquear una edición válida que
     * no aumenta el consumo real frente a lo que ya tenía reservado.
     *
     * @return array ['id_venta'=>int,'total_variedades'=>int,'total_unidades'=>int,'total_dinero'=>float,'bonus_units'=>int]
     */
    public function editarPedidoDetallado(int $id_v, ?int $id_cliente, array $cart, array $bonif_items): array {
        $this->pdo->beginTransaction();
        try {
            $stmt_info = $this->pdo->prepare("
                SELECT cp.precio_unitario, vp.id_categoria_precio
                FROM variedad_pan vp
                JOIN categoria_precio cp ON vp.id_categoria_precio = cp.id_categoria
                WHERE vp.id_variedad = ? AND vp.activo = 1
            ");

            // 1. Validar y recalcular carrito principal (productos cobrados)
            $total_dinero = 0.0;
            $cart_validado = [];
            $demanda_por_categoria = [];

            foreach ($cart as $item) {
                $id_var = (int)($item['id_variedad'] ?? 0);
                $cant   = (int)($item['cantidad'] ?? 0);

                if ($id_var <= 0 || $cant <= 0 || $cant > 999) {
                    throw new Exception("Cantidad inválida en el carrito: debe ser un número entero entre 1 y 999.");
                }

                $stmt_info->execute([$id_var]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                if (!$info) {
                    throw new Exception("Uno de los productos del carrito no es válido o está inactivo.");
                }

                $precio_real = (float)$info['precio_unitario'];
                $id_cat      = (int)$info['id_categoria_precio'];

                $cart_validado[] = [
                    'id_variedad' => $id_var,
                    'cantidad'    => $cant,
                    'napa'        => (int)($item['napa'] ?? 0),
                    'precio'      => $precio_real,
                ];
                $total_dinero += $cant * $precio_real;
                $demanda_por_categoria[$id_cat] = ($demanda_por_categoria[$id_cat] ?? 0) + $cant;
            }

            if (empty($cart_validado)) {
                throw new Exception("El carrito no contiene productos válidos.");
            }

            // 2. Validar y recalcular bonificaciones/ñapas
            $bonif_validado = [];
            $bonus_units = 0;

            foreach ($bonif_items as $bi) {
                $cant = (int)($bi['cantidad'] ?? 0);
                if ($cant <= 0) continue;

                $id_var = (int)($bi['id_variedad'] ?? 0);
                if ($id_var <= 0 || $cant > 999) {
                    throw new Exception("Cantidad inválida en la bonificación/ñapa: debe ser un número entero entre 1 y 999.");
                }

                $stmt_info->execute([$id_var]);
                $info = $stmt_info->fetch(PDO::FETCH_ASSOC);
                if (!$info) {
                    throw new Exception("Uno de los productos de bonificación/ñapa no es válido o está inactivo.");
                }

                $precio_real = (float)$info['precio_unitario'];
                $id_cat      = (int)$info['id_categoria_precio'];

                $bonif_validado[] = [
                    'id_variedad' => $id_var,
                    'cantidad'    => $cant,
                    'precio'      => $precio_real,
                ];
                $bonus_units += $cant;
                $demanda_por_categoria[$id_cat] = ($demanda_por_categoria[$id_cat] ?? 0) + $cant;
            }

            $total_und   = array_sum(array_column($cart_validado, 'cantidad'));
            $und_totales = $total_und + $bonus_units;

            // 3. Validar stock disponible HOY por categoría de precio, excluyendo la propia
            //    reserva previa de este pedido (id_v).
            $stmt_cat_nombre = $this->pdo->prepare("SELECT nombre FROM categoria_precio WHERE id_categoria = ?");

            foreach ($demanda_por_categoria as $id_cat => $unidades_pedidas) {
                $disponible = $this->getStockDisponibleHoy($id_cat, $id_v);

                if ($unidades_pedidas > $disponible) {
                    $stmt_cat_nombre->execute([$id_cat]);
                    $nombre_cat = $stmt_cat_nombre->fetchColumn() ?: "categoría #$id_cat";
                    throw new Exception("Stock insuficiente para {$nombre_cat}: disponible {$disponible}, solicitado {$unidades_pedidas}.");
                }
            }

            // 4. Actualizar maestro venta (restringido a hoy)
            $stmt_up = $this->pdo->prepare("
                UPDATE venta
                SET id_cliente = ?, unidades_vendidas = ?, total_venta = ?, unidades_bonificacion = ?
                WHERE id_venta = ? AND DATE(fecha_hora) = CURDATE()
            ");
            $stmt_up->execute([
                $id_cliente > 0 ? $id_cliente : null,
                $und_totales,
                $total_dinero,
                $bonus_units,
                $id_v
            ]);

            // 5. Eliminar detalle previo
            $stmt_del = $this->pdo->prepare("DELETE FROM venta_detalle WHERE id_venta = ?");
            $stmt_del->execute([$id_v]);

            // 6. Reinsertar productos cobrados
            $stmt_vd = $this->pdo->prepare("
                INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario)
                VALUES (?, ?, ?, ?, 0, ?)
            ");
            foreach ($cart_validado as $item) {
                $stmt_vd->execute([
                    $id_v,
                    $item['id_variedad'],
                    $item['cantidad'],
                    $item['napa'],
                    $item['precio']
                ]);
            }

            // 7. Reinsertar bonificaciones
            if (!empty($bonif_validado)) {
                $stmt_vdb = $this->pdo->prepare("
                    INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario)
                    VALUES (?, ?, 0, 0, ?, ?)
                ");
                foreach ($bonif_validado as $bi) {
                    $stmt_vdb->execute([$id_v, $bi['id_variedad'], $bi['cantidad'], $bi['precio']]);
                }
            }

            $this->pdo->commit();

            return [
                'id_venta'         => $id_v,
                'total_variedades' => count($cart_validado),
                'total_unidades'   => $und_totales,
                'total_dinero'     => $total_dinero,
                'bonus_units'      => $bonus_units,
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Edita una venta rápida
     */
    public function editarVentaRapida(int $id_v, int $id_cat, string $tipo, ?int $id_cli, int $und_edit, float $precio, float $total, int $bonif_edit): bool {
        $stmt = $this->pdo->prepare("
            UPDATE venta
            SET id_categoria_precio = ?, tipo_salida = ?, id_cliente = ?, unidades_vendidas = ?, precio_unitario = ?, total_venta = ?, unidades_bonificacion = ?
            WHERE id_venta = ? AND DATE(fecha_hora) = CURDATE()
        ");
        return $stmt->execute([
            $id_cat,
            $tipo,
            $id_cli,
            $und_edit,
            $precio,
            $total,
            $bonif_edit,
            $id_v
        ]);
    }

    /**
     * Elimina una venta (solo si fue hoy)
     */
    public function eliminarVenta(int $id_v): bool {
        $stmt = $this->pdo->prepare("DELETE FROM venta WHERE id_venta = ? AND DATE(fecha_hora) = CURDATE()");
        return $stmt->execute([$id_v]);
    }

    /**
     * Obtiene el listado de tiendas (clientes) con estadísticas de compra
     */
    public function getClientesConEstadisticas(string $busca = ''): array {
        $where  = "";
        $params = [];
        if ($busca !== '') {
            $where = "AND c.nombre LIKE ?";
            $params[] = "%$busca%";
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*,
                   COUNT(v.id_venta) AS num_compras,
                   COALESCE(SUM(v.total_venta),0) AS total_comprado,
                   COALESCE(SUM(v.unidades_vendidas),0) AS total_unidades
            FROM cliente c
            LEFT JOIN venta v ON v.id_cliente = c.id_cliente
            WHERE c.activo = 1 AND c.tipo = 'tienda' $where
            GROUP BY c.id_cliente
            ORDER BY total_comprado DESC, c.nombre
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una tienda por ID
     */
    public function getClienteById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE id_cliente = ? AND tipo = 'tienda'");
        $stmt->execute([$id]);
        $cli = $stmt->fetch();
        return $cli ?: null;
    }

    /**
     * Guarda (crea/actualiza) un cliente tienda
     */
    public function guardarCliente(int $id_edit, string $nombre, string $telefono, string $notas): bool {
        if ($id_edit > 0) {
            $stmt = $this->pdo->prepare("UPDATE cliente SET nombre = ?, tipo = 'tienda', telefono = ?, notas = ? WHERE id_cliente = ?");
            return $stmt->execute([$nombre, $telefono, $notas, $id_edit]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO cliente (nombre, tipo, telefono, notas, activo) VALUES (?, 'tienda', ?, ?, 1)");
            return $stmt->execute([$nombre, $telefono, $notas]);
        }
    }

    /**
     * Desactiva (soft-delete) un cliente
     */
    public function desactivarCliente(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET activo = 0 WHERE id_cliente = ?");
        return $stmt->execute([$id]);
    }

    /**
     * MÓDULO NUEVA VENTA CLÁSICA: Obtiene los productos activos con sus cantidades hoy producidas menos vendidas
     */
    public function getProductosActivosConStock(): array {
        return $this->pdo->query("
            SELECT p.id_producto, p.nombre, p.precio_venta,
                   GREATEST(0,
                       COALESCE((SELECT SUM(pr.unidades_producidas) FROM produccion pr
                                 WHERE pr.id_producto = p.id_producto AND DATE(pr.fecha_produccion) = CURDATE()), 0)
                     - COALESCE((SELECT SUM(v2.unidades_vendidas)   FROM venta v2
                                 WHERE v2.id_producto = p.id_producto AND DATE(v2.fecha_hora) = CURDATE()), 0)
                   ) AS stock_disponible
            FROM producto p
            WHERE p.activo = 1
            ORDER BY p.nombre
        ")->fetchAll();
    }

    /**
     * MÓDULO NUEVA VENTA CLÁSICA: Registra una venta por producto
     */
    public function registrarVentaNueva(int $id_prod, ?int $id_cli, int $id_usuario, int $unidades, float $precio, int $sobrantes): bool {
        $total = $unidades * $precio;
        $stmt = $this->pdo->prepare("
            INSERT INTO venta (id_producto, id_cliente, id_usuario, unidades_vendidas, precio_unitario, total_venta, unidades_sobrantes, fecha_hora)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$id_prod, $id_cli, $id_usuario, $unidades, $precio, $total, $sobrantes]);
    }

    /**
     * MÓDULO NUEVA VENTA CLÁSICA: Obtiene la lista de las últimas 20 ventas de hoy
     */
    public function getVentasHoyNueva(): array {
        return $this->pdo->query("
            SELECT v.*, p.nombre AS producto, c.nombre AS cliente, c.tipo AS tipo_cliente
            FROM venta v
            INNER JOIN producto p ON p.id_producto = v.id_producto
            LEFT  JOIN cliente  c ON c.id_cliente = v.id_cliente
            WHERE DATE(v.fecha_hora) = CURDATE()
            ORDER BY v.fecha_hora DESC
            LIMIT 20
        ")->fetchAll();
    }

    /**
     * EXPORTAR EXCEL: Obtiene listado de ventas maestras y detalles para exportación a Excel (.xls)
     */
    public function getVentasPorIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $inQuery = implode(',', array_fill(0, count($ids), '?'));

        // 1. Obtener ventas maestras
        $stmt = $this->pdo->prepare("
            SELECT v.id_venta, v.fecha_hora, v.tipo_salida, v.unidades_vendidas,
                   v.precio_unitario, v.total_venta, COALESCE(v.unidades_bonificacion, 0) as bonificacion,
                   COALESCE(cp.nombre, 'Pedido detallado') as categoria,
                   COALESCE(c.nombre, 'Mostrador') as cliente
            FROM venta v
            LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio
            LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
            WHERE v.id_venta IN ($inQuery)
            ORDER BY v.fecha_hora DESC
        ");
        $stmt->execute($ids);
        $ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. Obtener detalles
        $detalles_brutos = [];
        try {
            $det_stmt = $this->pdo->prepare("
                SELECT vd.id_venta, vd.cantidad, vd.napa, vd.bonificacion, vd.precio_unitario, vp.nombre
                FROM venta_detalle vd
                INNER JOIN variedad_pan vp ON vp.id_variedad = vd.id_variedad
                WHERE vd.id_venta IN ($inQuery)
            ");
            $det_stmt->execute($ids);
            $detalles_brutos = $det_stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // Manejar silenciosamente si no hay detalles o la tabla no existiera
        }

        // Agrupar detalles por id_venta
        $detalles_por_venta = [];
        foreach ($detalles_brutos as $d) {
            if (!isset($detalles_por_venta[$d['id_venta']])) {
                $detalles_por_venta[$d['id_venta']] = [];
            }
            $detalles_por_venta[$d['id_venta']][] = $d;
        }

        return [
            'ventas'             => $ventas,
            'detalles_por_venta' => $detalles_por_venta
        ];
    }
}
