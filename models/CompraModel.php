<?php
// models/CompraModel.php

class CompraModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene una compra por ID
     */
    public function getCompraById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM compra WHERE id_compra = ?");
        $stmt->execute([$id]);
        $compra = $stmt->fetch();
        return $compra ?: null;
    }

    /**
     * Obtiene información detallada de compras y lotes por lista de IDs (para etiquetas)
     */
    public function getComprasPorIds(array $ids): array {
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT c.id_compra, c.cantidad, c.precio_unitario, c.total_pagado,
                   c.fecha_compra, c.variacion_precio_pct,
                   i.nombre AS insumo, i.unidad_medida, i.stock_actual, i.punto_reposicion,
                   p.nombre AS proveedor,
                   l.numero_lote, l.fecha_ingreso
            FROM compra c
            INNER JOIN insumo    i ON i.id_insumo    = c.id_insumo
            INNER JOIN proveedor p ON p.id_proveedor = c.id_proveedor
            LEFT  JOIN lote      l ON l.id_compra    = c.id_compra
            WHERE c.id_compra IN ($ph)
            ORDER BY FIELD(c.id_compra, $ph)
        ");
        // We pass the IDs array twice: one for the IN clause, and one for the FIELD function
        $stmt->execute(array_merge($ids, $ids));
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el listado de compras con filtros de mes, búsqueda y alertas
     */
    public function getComprasMesActual(string $mesFiltro, string $busca = '', bool $filtroAlerta = false): array {
        $where  = "WHERE 1=1";
        $params = [];

        if ($busca !== '') {
            $where .= " AND (i.nombre LIKE ? OR p.nombre LIKE ?)";
            $params[] = "%$busca%";
            $params[] = "%$busca%";
        }
        if ($filtroAlerta) {
            $where .= " AND ABS(c.variacion_precio_pct) >= 5";
        }
        if ($mesFiltro !== '') {
            $where .= " AND DATE_FORMAT(c.fecha_compra,'%Y-%m') = ?";
            $params[] = $mesFiltro;
        }

        $stmt = $this->pdo->prepare("
            SELECT c.*, i.nombre AS insumo, i.unidad_medida, p.nombre AS proveedor
            FROM compra c
            INNER JOIN insumo i    ON i.id_insumo    = c.id_insumo
            INNER JOIN proveedor p ON p.id_proveedor = c.id_proveedor
            $where 
            ORDER BY c.fecha_compra DESC 
            LIMIT 50
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los KPIs de compras del mes actual
     */
    public function getKPIs(): array {
        $mes_actual = date('Y-m');
        
        $stmt_total = $this->pdo->prepare("SELECT COALESCE(SUM(total_pagado), 0) FROM compra WHERE DATE_FORMAT(fecha_compra,'%Y-%m') = ?");
        $stmt_total->execute([$mes_actual]);
        $total_mes = (float)$stmt_total->fetchColumn();

        $stmt_count = $this->pdo->prepare("SELECT COUNT(*) FROM compra WHERE DATE_FORMAT(fecha_compra,'%Y-%m') = ?");
        $stmt_count->execute([$mes_actual]);
        $compras_mes = (int)$stmt_count->fetchColumn();

        $stmt_alerts = $this->pdo->prepare("SELECT COUNT(*) FROM compra WHERE ABS(variacion_precio_pct) >= 5 AND DATE_FORMAT(fecha_compra,'%Y-%m') = ?");
        $stmt_alerts->execute([$mes_actual]);
        $alertas_precio = (int)$stmt_alerts->fetchColumn();

        $proveedores_n = (int)$this->pdo->query("SELECT COUNT(*) FROM proveedor WHERE activo = 1")->fetchColumn();

        return [
            'total_mes'      => $total_mes,
            'compras_mes'    => $compras_mes,
            'alertas_precio' => $alertas_precio,
            'proveedores_n'  => $proveedores_n
        ];
    }

    /**
     * Obtiene los insumos activos para el formulario de compras
     */
    public function getInsumosActivos(): array {
        return $this->pdo->query("
            SELECT id_insumo, nombre, unidad_medida, stock_actual, punto_reposicion, es_harina
            FROM insumo WHERE activo = 1 ORDER BY nombre
        ")->fetchAll();
    }

    /**
     * Obtiene los proveedores activos para el formulario de compras
     */
    public function getProveedoresActivos(): array {
        return $this->pdo->query("
            SELECT id_proveedor, nombre, telefono, tipo_entrega, dias_visita, dias_entrega_promedio
            FROM proveedor WHERE activo = 1 ORDER BY nombre
        ")->fetchAll();
    }

    /**
     * Registra transaccionalmente una compra y genera el lote correspondiente
     */
    public function registrarCompra(int $id_insumo, int $id_proveedor, string $fecha, float $cantidad, int $num_bultos, float $precio_bulto, int $id_usuario): array {
        if (esHoyDomingo()) {
            throw new Exception('No se pueden registrar compras los domingos.');
        }

        // 1. Obtener precio unitario anterior
        $stmt_prev = $this->pdo->prepare("SELECT precio_unitario FROM compra WHERE id_insumo = ? ORDER BY fecha_compra DESC LIMIT 1");
        $stmt_prev->execute([$id_insumo]);
        $precio_anterior = (float)($stmt_prev->fetchColumn() ?: 0);

        // 2. Calcular precio unitario y total
        $precio_unit = $cantidad > 0 ? round($precio_bulto / ($cantidad / $num_bultos), 4) : 0;
        $total        = round($precio_bulto * $num_bultos, 2);
        $variacion    = calcularVariacion($precio_anterior, $precio_unit);

        // 3. Obtener datos del insumo
        $stmt_ins = $this->pdo->prepare("SELECT nombre, unidad_medida, es_harina FROM insumo WHERE id_insumo = ?");
        $stmt_ins->execute([$id_insumo]);
        $insumo_data = $stmt_ins->fetch();
        if (!$insumo_data) {
            throw new Exception('El insumo seleccionado no existe.');
        }

        // 4. Generar lote
        $prefijo_lote = strtoupper(substr($insumo_data['nombre'], 0, 3));
        $numero_lote  = generarNumeroLote($prefijo_lote);

        // 5. Merma 6% en harina
        $es_harina = (bool)$insumo_data['es_harina'];
        $cantidad_disponible = $es_harina ? round($cantidad * 0.94, 3) : $cantidad;

        $this->pdo->beginTransaction();
        try {
            // A. Registrar compra
            $stmt_c = $this->pdo->prepare("
                INSERT INTO compra (id_insumo, id_proveedor, fecha_compra, cantidad,
                    precio_unitario, total_pagado, variacion_precio_pct, id_usuario)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_c->execute([$id_insumo, $id_proveedor, $fecha, $cantidad, $precio_unit, $total, $variacion, $id_usuario]);
            $id_compra_nueva = (int)$this->pdo->lastInsertId();

            // B. Registrar lote
            $stmt_l = $this->pdo->prepare("
                INSERT INTO lote (id_insumo, id_compra, numero_lote, cantidad_inicial, cantidad_disponible,
                    precio_unitario, fecha_ingreso, estado)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'activo')
            ");
            $stmt_l->execute([$id_insumo, $id_compra_nueva, $numero_lote, $cantidad, $cantidad_disponible, $precio_unit, $fecha]);

            // C. Incrementar stock de insumo
            $stmt_up_stock = $this->pdo->prepare("UPDATE insumo SET stock_actual = stock_actual + ? WHERE id_insumo = ?");
            $stmt_up_stock->execute([$cantidad_disponible, $id_insumo]);

            // D. Registrar en historial de precios si varió
            if ($variacion != 0) {
                $stmt_hp = $this->pdo->prepare("
                    INSERT INTO historial_precio (id_insumo, id_proveedor, id_compra, precio, variacion_pct)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt_hp->execute([$id_insumo, $id_proveedor, $id_compra_nueva, $precio_unit, $variacion]);
            }

            $this->pdo->commit();
            return [
                'id_compra'           => $id_compra_nueva,
                'numero_lote'         => $numero_lote,
                'es_harina'           => $es_harina,
                'cantidad'            => $cantidad,
                'cantidad_disponible' => $cantidad_disponible,
                'variacion'           => $variacion
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene el listado completo de proveedores activos
     */
    public function getProveedores(): array {
        return $this->pdo->query("SELECT * FROM proveedor WHERE activo = 1 ORDER BY nombre")->fetchAll();
    }

    /**
     * Obtiene un proveedor por ID
     */
    public function getProveedorById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM proveedor WHERE id_proveedor = ?");
        $stmt->execute([$id]);
        $prov = $stmt->fetch();
        return $prov ?: null;
    }

    /**
     * Crea o actualiza un proveedor
     */
    public function guardarProveedor(int $id_edit, string $nombre, string $telefono, string $entrega, float $dias, ?string $dias_visita): bool {
        if ($id_edit > 0) {
            $stmt = $this->pdo->prepare("
                UPDATE proveedor
                SET nombre = ?, telefono = ?, tipo_entrega = ?, dias_entrega_promedio = ?, dias_visita = ?
                WHERE id_proveedor = ?
            ");
            return $stmt->execute([$nombre, $telefono, $entrega, $dias, $dias_visita, $id_edit]);
        } else {
            $stmt = $this->pdo->prepare("
                INSERT INTO proveedor (nombre, telefono, tipo_entrega, dias_entrega_promedio, dias_visita, activo)
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            return $stmt->execute([$nombre, $telefono, $entrega, $dias, $dias_visita]);
        }
    }

    /**
     * Desactivación lógica de un proveedor
     */
    public function desactivarProveedor(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE proveedor SET activo = 0 WHERE id_proveedor = ?");
        return $stmt->execute([$id]);
    }
}
