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
