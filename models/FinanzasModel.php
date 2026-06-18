<?php
// models/FinanzasModel.php

class FinanzasModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getIngresos(string $desde, string $hasta): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora) BETWEEN :d AND :h");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return (float)$stmt->fetchColumn();
    }

    public function getComprasTotal(string $desde, string $hasta): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_pagado),0) FROM compra WHERE DATE(fecha_compra) BETWEEN :d AND :h");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return (float)$stmt->fetchColumn();
    }

    public function getGastosOp(string $desde, string $hasta): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM gasto WHERE DATE(fecha_gasto) BETWEEN :d AND :h");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return (float)$stmt->fetchColumn();
    }

    public function getNumVentas(string $desde, string $hasta): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora) BETWEEN :d AND :h");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return (int)$stmt->fetchColumn();
    }

    public function getNumCompras(string $desde, string $hasta): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM compra WHERE DATE(fecha_compra) BETWEEN :d AND :h");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return (int)$stmt->fetchColumn();
    }

    public function getVentasPorDia(string $desde, string $hasta): array {
        $stmt = $this->pdo->prepare("SELECT DATE(fecha_hora) AS dia, SUM(total_venta) AS total FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora) BETWEEN :d AND :h GROUP BY DATE(fecha_hora) ORDER BY dia ASC");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getComprasPorDia(string $desde, string $hasta): array {
        $stmt = $this->pdo->prepare("SELECT DATE(fecha_compra) AS dia, SUM(total_pagado) AS total FROM compra WHERE DATE(fecha_compra) BETWEEN :d AND :h GROUP BY DATE(fecha_compra) ORDER BY dia ASC");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getTopProductos(string $desde, string $hasta, int $limit = 6): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(cp.nombre, p.nombre) AS nombre, 
                   SUM(v.unidades_vendidas) AS unidades, 
                   SUM(v.unidades_vendidas) AS u, 
                   SUM(v.total_venta) AS total,
                   SUM(v.total_venta) AS t
            FROM venta v 
            LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio 
            LEFT JOIN producto p ON p.id_producto = v.id_producto
            WHERE DATE(v.fecha_hora) BETWEEN :d AND :h
            GROUP BY nombre 
            ORDER BY total DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':d', $desde);
        $stmt->bindValue(':h', $hasta);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTopClientes(string $desde, string $hasta, int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(c.nombre,'Mostrador') AS cliente, c.tipo,
                   SUM(v.total_venta) AS total, COUNT(*) AS transacciones
            FROM venta v LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
            WHERE v.tipo_salida='venta' AND DATE(v.fecha_hora) BETWEEN :d AND :h
            GROUP BY v.id_cliente 
            ORDER BY total DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':d', $desde);
        $stmt->bindValue(':h', $hasta);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getTopInsumos(string $desde, string $hasta, int $limit = 5): array {
        $stmt = $this->pdo->prepare("
            SELECT i.nombre, SUM(c.cantidad) AS cantidad, SUM(c.total_pagado) AS total, i.unidad_medida
            FROM compra c INNER JOIN insumo i ON i.id_insumo = c.id_insumo
            WHERE DATE(c.fecha_compra) BETWEEN :d AND :h
            GROUP BY c.id_insumo 
            ORDER BY total DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':d', $desde);
        $stmt->bindValue(':h', $hasta);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getGastosPorCategoria(string $desde, string $hasta): array {
        $stmt = $this->pdo->prepare("
            SELECT categoria, SUM(valor) AS total, COUNT(*) AS cantidad
            FROM gasto WHERE DATE(fecha_gasto) BETWEEN :d AND :h
            GROUP BY categoria ORDER BY total DESC
        ");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return $stmt->fetchAll();
    }

    public function getAniosDisponibles(): array {
        $anios = $this->pdo->query("SELECT DISTINCT YEAR(fecha_hora) AS y FROM venta WHERE tipo_salida='venta' ORDER BY y DESC")->fetchAll(PDO::FETCH_COLUMN);
        if (empty($anios)) {
            $anios = [date('Y')];
        }
        return $anios;
    }

    public function getConsumoIngredientes(string $desde, string $hasta, int $limit = 6): array {
        $stmt = $this->pdo->prepare("
            SELECT i.nombre, i.unidad_medida,
                   SUM(cl.cantidad_consumida) AS total_cant,
                   SUM(cl.costo_consumo)      AS total_costo,
                   GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ' · ') AS productos
            FROM consumo_lote cl
            INNER JOIN lote l        ON l.id_lote       = cl.id_lote
            INNER JOIN insumo i      ON i.id_insumo      = l.id_insumo
            INNER JOIN produccion pr ON pr.id_produccion = cl.id_produccion
            INNER JOIN producto p    ON p.id_producto    = pr.id_producto
            WHERE DATE(pr.fecha_produccion) BETWEEN :d AND :h
            GROUP BY i.id_insumo 
            ORDER BY total_costo DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':d', $desde);
        $stmt->bindValue(':h', $hasta);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getConsumoIngredientesEstimado(string $desde, string $hasta, int $limit = 6): array {
        $stmt = $this->pdo->prepare("
            SELECT i.nombre, i.unidad_medida,
                   SUM(ri.cantidad * pr.cantidad_tandas) AS total_cant,
                   0 AS total_costo,
                   GROUP_CONCAT(DISTINCT p.nombre ORDER BY p.nombre SEPARATOR ' · ') AS productos
            FROM produccion pr
            INNER JOIN receta_ingrediente ri ON ri.id_receta = pr.id_receta
            INNER JOIN insumo i  ON i.id_insumo  = ri.id_insumo
            INNER JOIN producto p ON p.id_producto = pr.id_producto
            WHERE DATE(pr.fecha_produccion) BETWEEN :d AND :h
            GROUP BY i.id_insumo 
            ORDER BY total_cant DESC 
            LIMIT :limit
        ");
        $stmt->bindValue(':d', $desde);
        $stmt->bindValue(':h', $hasta);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getDetalleCompras(string $desde, string $hasta): array {
        $stmt = $this->pdo->prepare("
            SELECT c.fecha_compra, i.nombre AS insumo, c.cantidad, i.unidad_medida,
                   c.precio_unitario, c.total_pagado
            FROM compra c INNER JOIN insumo i ON i.id_insumo=c.id_insumo
            WHERE DATE(c.fecha_compra) BETWEEN :d AND :h
            ORDER BY c.fecha_compra ASC
        ");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return $stmt->fetchAll();
    }

    public function getDetalleVentas(string $desde, string $hasta): array {
        $stmt = $this->pdo->prepare("
            SELECT DATE(v.fecha_hora) AS fecha, TIME(v.fecha_hora) AS hora,
                   COALESCE(c.nombre,'Mostrador') AS cliente, c.tipo,
                   p.nombre AS producto, v.unidades_vendidas,
                   COALESCE(v.unidades_bonificacion,0) AS bonificacion,
                   v.precio_unitario, v.total_venta
            FROM venta v
            INNER JOIN producto p ON p.id_producto = v.id_producto
            LEFT  JOIN cliente  c ON c.id_cliente  = v.id_cliente
            WHERE DATE(v.fecha_hora) BETWEEN :d AND :h
            ORDER BY v.fecha_hora ASC
        ");
        $stmt->execute([':d' => $desde, ':h' => $hasta]);
        return $stmt->fetchAll();
    }
}
