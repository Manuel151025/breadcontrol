<?php
// helpers/PedidoHelper.php

class PedidoHelper {
    /**
     * Calcula el monto esperado total de un pedido, considerando si está agrupado en un pago consolidado.
     *
     * @param array $pedido
     * @param array $pedidosConsolidados
     * @return float
     */
    public static function calcularTotalEsperado(array $pedido, array $pedidosConsolidados): float {
        if (!empty($pedidosConsolidados)) {
            $total = 0.0;
            foreach ($pedidosConsolidados as $pc) {
                $total += (float)$pc['total_estimado'];
            }
            return $total;
        }
        return (float)$pedido['total_estimado'];
    }

    /**
     * Calcula la deuda restante.
     *
     * @param float $totalEsperado
     * @param float $totalPagado
     * @return float
     */
    public static function calcularDeudaRestante(float $totalEsperado, float $totalPagado): float {
        return max(0.0, $totalEsperado - $totalPagado);
    }
}
