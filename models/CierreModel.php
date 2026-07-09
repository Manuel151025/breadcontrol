<?php
// models/CierreModel.php

class CierreModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ══════════════════════════════════════════════════════════════
    //  KPIs FINANCIEROS DEL DÍA
    // ══════════════════════════════════════════════════════════════

    /**
     * Total facturado en ventas del día
     */
    public function getTotalVentasHoy(string $fecha): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=?");
        $stmt->execute([$fecha]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Número de ventas del día
     */
    public function getNumVentasHoy(string $fecha): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=?");
        $stmt->execute([$fecha]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Total ventas del día anterior (para comparativo porcentual)
     */
    public function getVentasAyer(string $fecha): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=DATE_SUB(?,INTERVAL 1 DAY)");
        $stmt->execute([$fecha]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Total pagado en compras del día
     */
    public function getTotalComprasHoy(string $fecha): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(total_pagado),0) FROM compra WHERE DATE(fecha_compra)=?");
        $stmt->execute([$fecha]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Número de compras del día
     */
    public function getNumComprasHoy(string $fecha): int {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM compra WHERE DATE(fecha_compra)=?");
        $stmt->execute([$fecha]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Costo total de insumos consumidos en producción del día (consumo_lote)
     */
    public function getCostoProduccionHoy(string $fecha): float {
        $stmt = $this->pdo->prepare(
            "SELECT COALESCE(SUM(cl.costo_consumo),0) FROM consumo_lote cl INNER JOIN produccion pr ON pr.id_produccion=cl.id_produccion WHERE DATE(pr.fecha_produccion)=?"
        );
        $stmt->execute([$fecha]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Total de gastos operativos del día
     */
    public function getTotalGastosHoy(string $fecha): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(valor),0) FROM gasto WHERE DATE(fecha_gasto)=?");
        $stmt->execute([$fecha]);
        return (float)$stmt->fetchColumn();
    }

    // ══════════════════════════════════════════════════════════════
    //  DESGLOSES DEL DÍA
    // ══════════════════════════════════════════════════════════════

    /**
     * Ventas agrupadas por producto/categoría
     */
    public function getVentasPorProducto(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(cp.nombre, p.nombre) AS nombre, SUM(v.unidades_vendidas) AS u, SUM(v.total_venta) AS t
            FROM venta v LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio LEFT JOIN producto p ON p.id_producto = v.id_producto
            WHERE v.tipo_salida='venta' AND DATE(v.fecha_hora)=?
            GROUP BY nombre ORDER BY t DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Ventas agrupadas por cliente
     */
    public function getVentasPorCliente(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(c.nombre,'Mostrador') AS cliente, COALESCE(c.tipo,'mostrador') AS tipo,
                   SUM(v.total_venta) AS t, COUNT(*) AS n
            FROM venta v LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
            WHERE v.tipo_salida='venta' AND DATE(v.fecha_hora)=?
            GROUP BY v.id_cliente ORDER BY t DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Producciones del día con nombre de producto
     */
    public function getProduccionesHoy(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT pr.cantidad_tandas, pr.fecha_produccion, p.nombre, p.unidad_produccion
            FROM produccion pr INNER JOIN producto p ON p.id_producto = pr.id_producto
            WHERE DATE(pr.fecha_produccion)=?
            ORDER BY pr.fecha_produccion DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Compras del día con datos de insumo
     */
    public function getComprasHoy(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT c.cantidad, c.total_pagado, i.nombre AS insumo, i.unidad_medida
            FROM compra c INNER JOIN insumo i ON i.id_insumo = c.id_insumo
            WHERE DATE(c.fecha_compra)=? ORDER BY c.id_compra DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Insumos con stock actual por debajo del punto de reposición
     */
    public function getAlertasStockBajo(): array {
        return $this->pdo->query("
            SELECT nombre, stock_actual, punto_reposicion, unidad_medida
            FROM insumo WHERE stock_actual <= punto_reposicion AND activo=1
            ORDER BY (stock_actual / NULLIF(punto_reposicion,0)) ASC
        ")->fetchAll();
    }

    /**
     * Pan sobrante del día: producido – vendido por producto.
     *
     * LIMITACIÓN CONOCIDA (no corregida aquí a propósito, ver decisión en
     * AUDITORIA.md C6): esta consulta solo reconcilia producto vs. venta
     * cuando la venta trae id_producto directo (módulo clásico "venta
     * nueva"). Las ventas del POS moderno (venta rápida y pedido detallado)
     * no registran id_producto, solo id_categoria_precio — y una misma
     * categoría de precio agrupa varias variedades de pan distintas (ej. la
     * categoría de $500 recibe producción de Pan de Sal, Pan Grande,
     * Croissant, Pan Dulce y Pan Coco a la vez). No hay forma de saber con el
     * esquema actual a cuál de esos productos pertenece una venta por
     * categoría, así que esas unidades no pueden restarse aquí sin adivinar.
     * Para que "sobrante" no las ignore en silencio, se exponen aparte en
     * getVentasSinProductoHoy() y el panel las muestra como nota separada.
     * Arreglo de fondo (trabajo futuro): que el POS moderno registre también
     * el id_producto vendido, no solo la categoría de precio.
     */
    public function getSobrantesHoy(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT p.nombre,
                   COALESCE(SUM(pr.unidades_producidas), 0) AS producidas,
                   COALESCE(SUM(v.unidades_vendidas),    0) AS vendidas,
                   COALESCE(SUM(pr.unidades_producidas), 0)
                 - COALESCE(SUM(v.unidades_vendidas),    0) AS sobrante
            FROM producto p
            LEFT JOIN produccion pr ON pr.id_producto = p.id_producto
                                    AND DATE(pr.fecha_produccion) = ?
            LEFT JOIN venta v       ON v.id_producto  = p.id_producto
                                    AND DATE(v.fecha_hora)        = ?
            WHERE p.activo = 1
            GROUP BY nombre
            HAVING producidas > 0
            ORDER BY sobrante DESC
        ");
        $stmt->execute([$fecha, $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Unidades salidas hoy (venta rápida o pedido detallado) que NO se pueden
     * atribuir a un producto específico, porque el POS moderno solo registra
     * id_categoria_precio y deja id_producto en NULL — ver el comentario de
     * getSobrantesHoy() para el porqué. Se reporta aparte, como nota, para
     * que el panel "Sin vender hoy" no subestime en silencio lo que falta por
     * vender.
     *
     * Para pedido detallado basta sumar venta.unidades_vendidas: el registro
     * maestro ya guarda ahí el total agregado del pedido (cantidad + ñapa +
     * bonificación, ver VentaModel::registrarPedidoDetallado()), así que
     * sumar también venta_detalle duplicaría el conteo.
     */
    public function getVentasSinProductoHoy(string $fecha): int {
        $stmt = $this->pdo->prepare("
            SELECT COALESCE(SUM(unidades_vendidas), 0)
            FROM venta
            WHERE id_producto IS NULL AND DATE(fecha_hora) = ?
        ");
        $stmt->execute([$fecha]);
        return (int)$stmt->fetchColumn();
    }

    // ══════════════════════════════════════════════════════════════
    //  CIERRE E HISTORIAL
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene el cierre guardado de una fecha específica (o null si no existe)
     */
    public function getCierreGuardado(string $fecha): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cierre_dia WHERE fecha=?");
        $stmt->execute([$fecha]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Últimos N cierres con nombre de usuario
     */
    public function getHistorialCierres(int $limite = 7): array {
        return $this->pdo->query("
            SELECT cd.*, u.nombre_completo AS usuario
            FROM cierre_dia cd
            LEFT JOIN usuario u ON u.id_usuario = cd.id_usuario
            ORDER BY cd.fecha DESC LIMIT $limite
        ")->fetchAll();
    }

    /**
     * Guarda o actualiza el cierre del día (ON DUPLICATE KEY UPDATE)
     */
    public function guardarCierre(
        int $id_usuario,
        string $fecha,
        float $total_ingresos,
        float $total_gastos,
        float $costo_produccion,
        float $utilidad_bruta,
        float $utilidad_neta,
        ?string $sugerencia
    ): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO cierre_dia
                (id_usuario, fecha, total_ingresos, total_gastos, costo_produccion,
                 utilidad_bruta, utilidad_neta, sugerencia_produccion)
            VALUES (?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE
                id_usuario=VALUES(id_usuario),
                total_ingresos=VALUES(total_ingresos),
                total_gastos=VALUES(total_gastos),
                costo_produccion=VALUES(costo_produccion),
                utilidad_bruta=VALUES(utilidad_bruta),
                utilidad_neta=VALUES(utilidad_neta),
                sugerencia_produccion=VALUES(sugerencia_produccion)
        ");
        return $stmt->execute([
            $id_usuario, $fecha,
            $total_ingresos, $total_gastos, $costo_produccion,
            $utilidad_bruta, $utilidad_neta, $sugerencia ?: null
        ]);
    }
}
