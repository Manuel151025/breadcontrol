<?php
// helpers/ReglasPortal.php

/**
 * Reglas de negocio del Portal de Clientes y ventas.
 *
 * Fuente única de estas reglas: antes vivían duplicadas (y a veces con
 * copias ligeramente distintas) en los controladores del portal (hoy
 * divididos en controllers/portal/), VentaController y PortalClienteModel.
 * Son funciones puras (sin base de datos ni sesión), por lo que se
 * prueban directamente en tests/Unit/ReglasPortalTest.php.
 *
 * Las vistas JS (nuevo_pedido.php, ventas/index.php) replican el cálculo de
 * crédito solo como previsualización; el valor definitivo siempre sale de aquí.
 */
class ReglasPortal {

    /** Horas mínimas antes de la entrega para poder editar/cancelar un pedido. */
    public const HORAS_LIMITE_GESTION = 48;

    /**
     * Los pedidos que un aprendiz dirige a la cuenta ADSO se guardan con una
     * fecha imposible (`1000-01-01`) porque quien fija la entrega es el
     * instructor al aprobarlos, no el aprendiz al pedirlos. Cualquier año
     * anterior a este se interpreta como «entrega sin definir» — el mismo
     * criterio que usa formatearFechaEntrega() para mostrar «Por definir».
     */
    public const ANIO_SIN_DEFINIR = 1970;

    /** Cupo semanal máximo de un aprendiz (COP). */
    public const CUPO_MAXIMO = 100000;

    /** El cupo semanal debe ser múltiplo de este valor (COP). */
    public const CUPO_MULTIPLO = 500;

    /** Horario de entrega de la panadería. */
    public const HORA_APERTURA = '07:00';
    public const HORA_CIERRE   = '20:00';

    /** Dinero base por el que se otorga crédito de bonificación/ñapa (COP). */
    public const BASE_CREDITO = 5000;

    /** Crédito otorgado por cada BASE_CREDITO según el tipo de cliente (COP). */
    public const CREDITO_TIENDA    = 1000;
    public const CREDITO_MOSTRADOR = 500;

    /**
     * Crédito (bonificación para tiendas, ñapa para mostrador) según el dinero
     * de la venta/pedido: $1.000 por cada $5.000 para tiendas, $500 para mostrador.
     */
    public static function calcularCredito(bool $es_tienda, float $total_dinero): float {
        $tarifa = $es_tienda ? self::CREDITO_TIENDA : self::CREDITO_MOSTRADOR;
        return floor($total_dinero / self::BASE_CREDITO) * $tarifa;
    }

    /**
     * Estado temporal de un pedido frente a su fecha de entrega.
     *
     * @return array{horas_restantes:int, vencido:bool}
     */
    private static function estadoTiempo(string $fecha_entrega, ?DateTimeInterface $ahora = null): array {
        $entrega = new DateTimeImmutable($fecha_entrega);
        $ahora   = $ahora !== null
            ? DateTimeImmutable::createFromInterface($ahora)
            : new DateTimeImmutable();
        $diff = $ahora->diff($entrega);

        return [
            'horas_restantes' => ($diff->days * 24) + $diff->h,
            'vencido'         => $diff->invert === 1,
        ];
    }

    /**
     * True si el pedido está "cerca" de entregarse: no vencido y a menos de
     * 48 horas. En ese lapso el cliente ya no puede editarlo ni cancelarlo
     * (la vista lo usa para explicar el bloqueo).
     */
    public static function dentroDeLimite48h(string $fecha_entrega, ?DateTimeInterface $ahora = null): bool {
        if (self::entregaSinDefinir($fecha_entrega)) {
            return false;
        }
        $t = self::estadoTiempo($fecha_entrega, $ahora);
        return !$t['vencido'] && $t['horas_restantes'] < self::HORAS_LIMITE_GESTION;
    }

    /**
     * True si el pedido todavía no tiene fecha de entrega asignada.
     *
     * Distinguir este caso importa: sin él, la fecha centinela del año 1000 se
     * lee como «entrega vencida» y el sistema bloquea la edición justo en la
     * ventana en la que el aprendiz querría cambiar su pedido — mientras espera
     * que el instructor lo apruebe.
     */
    public static function entregaSinDefinir(string $fecha_entrega): bool {
        try {
            $anio = (int) (new DateTimeImmutable($fecha_entrega))->format('Y');
        } catch (Exception $e) {
            return false;
        }
        return $anio <= self::ANIO_SIN_DEFINIR;
    }

    /**
     * True si el pedido ya NO se puede gestionar por tiempo: vencido o a menos
     * de 48 horas de la entrega.
     */
    public static function fueraDeLimiteGestion(string $fecha_entrega, ?DateTimeInterface $ahora = null): bool {
        // Sin fecha asignada no hay entrega que proteger: el pedido sigue en
        // manos del aprendiz hasta que el instructor le ponga fecha.
        if (self::entregaSinDefinir($fecha_entrega)) {
            return false;
        }
        $t = self::estadoTiempo($fecha_entrega, $ahora);
        return $t['vencido'] || $t['horas_restantes'] < self::HORAS_LIMITE_GESTION;
    }

    /**
     * Un pedido solo se puede editar/cancelar si sigue pendiente y aún faltan
     * al menos 48 horas para su entrega.
     */
    public static function puedeGestionarPedido(string $estado, string $fecha_entrega, ?DateTimeInterface $ahora = null): bool {
        return $estado === 'pendiente' && !self::fueraDeLimiteGestion($fecha_entrega, $ahora);
    }

    /**
     * Un aprendiz no puede editar/cancelar un pedido mientras su instructor
     * tenga un pago en proceso (PENDING/PENDIENTE) asociado a él.
     */
    public static function bloqueoPorPagoInstructor(bool $es_aprendiz, ?string $estado_pago): bool {
        if (!$es_aprendiz) {
            return false;
        }
        return $estado_pago !== null && $estado_pago !== ''
            && in_array(strtoupper($estado_pago), ['PENDING', 'PENDIENTE'], true);
    }

    /**
     * Valida un cupo semanal propuesto para un aprendiz.
     *
     * @return string|null Mensaje de error, o null si el cupo es válido.
     */
    public static function validarCupoSemanal(float $cupo): ?string {
        if ($cupo < 0 || $cupo > self::CUPO_MAXIMO) {
            return 'El cupo semanal debe estar entre $0 y $100.000 COP.';
        }
        $cupo_int = (int) $cupo;
        if ($cupo_int % self::CUPO_MULTIPLO !== 0 || $cupo != $cupo_int) {
            return 'El cupo semanal debe ser múltiplo de $500 COP.';
        }
        return null;
    }

    /**
     * True si el consumo acumulado de la semana más el nuevo pedido supera
     * el cupo semanal del aprendiz.
     */
    public static function excedeCupoSemanal(float $consumido_semana, float $monto_nuevo, float $cupo_semanal): bool {
        return ($consumido_semana + $monto_nuevo) > $cupo_semanal;
    }

    /**
     * El horario de entrega de la panadería es de 7:00 AM a 8:00 PM.
     */
    public static function horarioEntregaValido(string $hora): bool {
        return $hora >= self::HORA_APERTURA && $hora <= self::HORA_CIERRE;
    }
}
