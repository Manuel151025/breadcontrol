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
     * Obtiene las categorías de precio activas y su stock hoy disponible
     */
    public function getCategoriasPrecio(): array {
        return $this->pdo->query("
            SELECT cp.*,
                COALESCE((SELECT SUM(pp.unidades) FROM produccion_precio pp WHERE pp.id_categoria_precio = cp.id_categoria), 0) -
                COALESCE((SELECT SUM(v.unidades_vendidas) FROM venta v WHERE v.id_categoria_precio = cp.id_categoria), 0) AS stock_hoy
            FROM categoria_precio cp 
            WHERE cp.activo = 1 
            ORDER BY cp.precio_unitario
        ")->fetchAll();
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
     * Registra un pedido detallado en el carrito (maestro y detalle)
     */
    public function registrarPedidoDetallado(?int $id_cliente, int $id_usuario, array $cart, array $bonif_items): bool {
        $this->pdo->beginTransaction();
        try {
            // Calcular totales cobrados
            $total_und = 0;
            $total_dinero = 0;
            foreach ($cart as $item) {
                $total_und += (int)$item['cantidad'];
                $total_dinero += (int)$item['cantidad'] * (float)$item['precio'];
            }

            // Calcular bonificaciones
            $bonus_units = 0;
            if (!empty($bonif_items)) {
                foreach ($bonif_items as $bi) {
                    $bonus_units += (int)($bi['cantidad'] ?? 0);
                }
            }
            $und_totales = $total_und + $bonus_units;

            // 1. Insertar maestro venta
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

            // 2. Insertar venta_detalle para productos cobrados
            $stmt_vd = $this->pdo->prepare("
                INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) 
                VALUES (?, ?, ?, 0, 0, ?)
            ");
            foreach ($cart as $item) {
                $stmt_vd->execute([$id_venta, (int)$item['id_variedad'], (int)$item['cantidad'], (float)$item['precio']]);
            }

            // 3. Insertar venta_detalle para bonificaciones
            if (!empty($bonif_items)) {
                $stmt_vdb = $this->pdo->prepare("
                    INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) 
                    VALUES (?, ?, 0, 0, ?, ?)
                ");
                foreach ($bonif_items as $bi) {
                    if ((int)($bi['cantidad'] ?? 0) > 0) {
                        $stmt_vdb->execute([$id_venta, (int)$bi['id_variedad'], (int)$bi['cantidad'], (float)($bi['precio'] ?? 0)]);
                    }
                }
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Edita un pedido detallado borrando sus detalles y reinsertando
     */
    public function editarPedidoDetallado(int $id_v, ?int $id_cliente, array $cart, array $bonif_items): bool {
        $this->pdo->beginTransaction();
        try {
            $total_und = 0;
            $total_dinero = 0;
            foreach ($cart as $item) {
                $total_und += (int)$item['cantidad'];
                $total_dinero += (int)$item['cantidad'] * (float)$item['precio'];
            }
            $bonus_units = 0;
            if (!empty($bonif_items)) {
                foreach ($bonif_items as $bi) {
                    $bonus_units += (int)($bi['cantidad'] ?? 0);
                }
            }
            $und_totales = $total_und + $bonus_units;

            // 1. Actualizar maestro venta (restringido a hoy)
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

            // 2. Eliminar detalle previo
            $stmt_del = $this->pdo->prepare("DELETE FROM venta_detalle WHERE id_venta = ?");
            $stmt_del->execute([$id_v]);

            // 3. Reinsertar productos cobrados
            $stmt_vd = $this->pdo->prepare("
                INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) 
                VALUES (?, ?, ?, ?, 0, ?)
            ");
            foreach ($cart as $item) {
                $stmt_vd->execute([
                    $id_v, 
                    (int)$item['id_variedad'], 
                    (int)$item['cantidad'], 
                    (int)($item['napa'] ?? 0), 
                    (float)$item['precio']
                ]);
            }

            // 4. Reinsertar bonificaciones
            if (!empty($bonif_items)) {
                $stmt_vdb = $this->pdo->prepare("
                    INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) 
                    VALUES (?, ?, 0, 0, ?, ?)
                ");
                foreach ($bonif_items as $bi) {
                    if ((int)($bi['cantidad'] ?? 0) > 0) {
                        $stmt_vdb->execute([$id_v, (int)$bi['id_variedad'], (int)$bi['cantidad'], (float)($bi['precio'] ?? 0)]);
                    }
                }
            }

            $this->pdo->commit();
            return true;
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
