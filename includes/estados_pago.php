<?php
// ============================================================
//  includes/estados_pago.php
//  Enum canonico de la columna pago_pedido.estado (SIEMPRE en MAYUSCULAS).
//
//  Fuente unica de verdad. Antes coexistian dos vocabularios para la MISMA
//  columna: el back-office escribia MAYUSCULAS (PENDING/APPROVED/...) y el
//  webhook de Wompi escribia minusculas (aprobado/rechazado/...), lo que dejaba
//  confirmarCobroTienda comparando 'pendiente' contra filas 'PENDING' que nunca
//  coincidian. El webhook se retiro (D1) y este enum queda como referencia unica.
//
//  OJO: NO confundir con pedido_cliente.estado_pago, que es OTRA columna con su
//  propio vocabulario en minusculas (no_aplica/pendiente/parcial/aprobado/...).
// ============================================================

final class EstadoPagoPedido {
    const PENDING  = 'PENDING';   // pago habilitado, esperando que el propietario confirme el recibo
    const APPROVED = 'APPROVED';  // pagado en su totalidad
    const PARTIAL  = 'PARTIAL';   // abonos parciales, aun queda saldo
    const EXPIRED  = 'EXPIRED';   // link retirado o pedido cancelado
    const VOIDED   = 'VOIDED';    // pago aprobado que se revirtio manualmente

    /**
     * Valores historicos (incluye minusculas previas al retiro de Wompi) que deben
     * tratarse como "PENDING" al filtrar pagos aun cobrables. Se mantiene por
     * tolerancia a datos antiguos que la migracion no haya normalizado todavia.
     */
    public static function pendientesSql(): string {
        return "'PENDING','PENDIENTE','pendiente'";
    }
}
