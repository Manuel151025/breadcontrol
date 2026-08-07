<?php
// helpers/FinanzasHelper.php

class FinanzasHelper {
    /**
     * Costo real de producción (consumo_lote, costeo FIFO) en un rango de fechas.
     * Fuente única de esta consulta: antes vivía duplicada en AuthModel y CierreModel,
     * y no existía en absoluto para FinanzasModel/TableroModel/GastoModel, que usaban
     * "compras" (dinero gastado) en vez de "costo de producción" (dinero consumido).
     */
    public static function costoProduccionEnRango(PDO $pdo, string $desde, string $hasta): float {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(cl.costo_consumo),0)
            FROM consumo_lote cl
            INNER JOIN produccion pr ON pr.id_produccion = cl.id_produccion
            WHERE DATE(pr.fecha_produccion) BETWEEN ? AND ?
        ");
        $stmt->execute([$desde, $hasta]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Ingresos de los pedidos del Portal de Clientes ya cobrados, en un rango.
     *
     * Los pedidos del portal viven en `pedido_cliente` y NUNCA generan una fila
     * en `venta`, así que los reportes —que leen `venta`— dejaban fuera todo lo
     * despachado por el portal. El costo de esos panes sí se contaba (se
     * descuenta al registrar la producción del día), de modo que la utilidad
     * quedaba sesgada a la baja.
     *
     * Se suma el estado actual del pedido en vez de crear una venta espejo: es
     * idempotente por diseño (leer un estado no puede contar doble) y no toca
     * inventario ni el POS. La contrapartida, asumida y documentada, es que
     * siguen existiendo dos fuentes de ingreso que hay que sumar en cada reporte
     * —por eso esta consulta vive aquí y no copiada en cada modelo.
     *
     * La fecha del ingreso es `fecha_entrega`, no la del cobro: así el ingreso
     * cae el mismo día que la producción que lo costeó y el cierre diario cuadra.
     */
    public static function ingresosPortalEnRango(PDO $pdo, string $desde, string $hasta): float {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(total_estimado),0)
            FROM pedido_cliente
            WHERE estado_pago = 'aprobado'
              AND DATE(fecha_entrega) BETWEEN ? AND ?
        ");
        $stmt->execute([$desde, $hasta]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Utilidad bruta y neta con el criterio correcto: ventas menos costo real de
     * producción (no compras), menos gastos operativos.
     *
     * @return array{bruta: float, neta: float}
     */
    public static function calcularUtilidad(float $ventas, float $costoProduccionReal, float $gastos): array {
        $bruta = $ventas - $costoProduccionReal;
        return [
            'bruta' => $bruta,
            'neta'  => $bruta - $gastos,
        ];
    }
}
