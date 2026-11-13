<?php
// helpers/ReglasVenta.php

/**
 * Reglas de negocio del punto de venta.
 *
 * El crédito de bonificación (tiendas) y de ñapa (mostrador) vivía en
 * ReglasPortal porque lo compartían el portal de clientes y el punto de venta.
 * Al retirar el portal solo queda este uso.
 */
class ReglasVenta {

    /** Dinero base por el que se otorga crédito de bonificación/ñapa (COP). */
    public const BASE_CREDITO = 5000;

    /** Crédito otorgado por cada BASE_CREDITO según el tipo de cliente (COP). */
    public const CREDITO_TIENDA    = 1000;
    public const CREDITO_MOSTRADOR = 500;

    /**
     * Crédito (bonificación para tiendas, ñapa para mostrador) según el dinero
     * de la venta: $1.000 por cada $5.000 para tiendas, $500 para mostrador.
     */
    public static function calcularCredito(bool $es_tienda, float $total_dinero): float {
        $tarifa = $es_tienda ? self::CREDITO_TIENDA : self::CREDITO_MOSTRADOR;
        return floor($total_dinero / self::BASE_CREDITO) * $tarifa;
    }
}
