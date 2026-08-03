<?php
// models/portal/PagosPortalTrait.php

/**
 * Pagos consolidados y abonos: consulta de saldos y creación del pago
 * con link de Nequi.
 * Parte de PortalClienteModel (dividido por responsabilidad).
 */
trait PagosPortalTrait {

    /**
     * Obtiene abonos relacionados a un pago.
     * @return array<int, array<string, mixed>>
     */
    public function getAbonos(int $id_pago): array {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_abono WHERE id_pago = ? ORDER BY fecha_abono ASC");
        $stmt->execute([$id_pago]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los pedidos pendientes de pago de un cliente.
     *
     * Regla unica de pago (D2): SOLO paga quien figura como id_cliente del pedido
     * (destinatario/facturado). Nunca se habilita el pago por id_creador — un aprendiz
     * que crea un pedido dirigido a la cuenta del instructor (id_cliente = instructor)
     * no puede pagarlo; lo paga el instructor.
     *
     * Regla de aprobacion (D5): un pedido dirigido a OTRA cuenta (id_cliente != id_creador)
     * solo es pagable si el instructor ya lo aprobo (aprobado_instructor = 1). Un pedido
     * personal (id_cliente = id_creador) es pagable sin aprobacion previa.
     * @return array<int, array<string, mixed>>
     */
    public function getPedidosPendientesPago(int $cliente_id, int $id_pedido_spec = 0): array {
        $cond = "id_cliente = ?
                 AND estado != 'rechazado'
                 AND (aprobado_instructor = 1 OR id_cliente = id_creador)
                 AND (estado_pago IS NULL OR estado_pago IN ('no_aplica','pendiente','expirado','parcial','rechazado'))";

        if ($id_pedido_spec > 0) {
            $stmt = $this->pdo->prepare("SELECT * FROM pedido_cliente WHERE id_pedido = ? AND $cond");
            $stmt->execute([$id_pedido_spec, $cliente_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $stmt = $this->pdo->prepare("SELECT * FROM pedido_cliente WHERE $cond ORDER BY fecha_solicitud ASC");
        $stmt->execute([$cliente_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene abono acumulado para un pago de pedido.
     */
    public function getMontoAbonado(int $id_pago): float {
        $stmt = $this->pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
        $stmt->execute([$id_pago]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Obtiene un pago pendiente por su ID.
     * @return array<mixed>
     */
    public function getPagoPendientePorId(int $id_pago): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ? AND estado IN (" . EstadoPagoPedido::pendientesSql() . ")");
        $stmt->execute([$id_pago]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Registra el inicio de un pago consolidado.
     * @param array<mixed> $pedidos
     * @param array<mixed> $ids_pedidos
     */
    public function iniciarPagoConsolidado(int $cliente_id, array $pedidos, array $ids_pedidos, float $total_saldo, string $referencia, ?string $link_id, string $link_pago_url, string $nota_consolidado): int {
        try {
            $this->pdo->beginTransaction();

            // Expirar pagos pendientes previos de estos pedidos
            $pagos_pendientes_previos = array_unique(array_filter(array_column($pedidos, 'id_pago_activo')));
            if (!empty($pagos_pendientes_previos)) {
                $ph_prev = implode(',', array_fill(0, count($pagos_pendientes_previos), '?'));
                $stmt_exp = $this->pdo->prepare("UPDATE pago_pedido SET estado = '" . EstadoPagoPedido::EXPIRED . "' WHERE id_pago IN ($ph_prev) AND estado IN (" . EstadoPagoPedido::pendientesSql() . ")");
                $stmt_exp->execute($pagos_pendientes_previos);
            }

            // Crear registro de pago en pago_pedido (enlazado al pedido mas antiguo)
            $id_pedido_referencia = $ids_pedidos[0];
            $monto_centavos = (int) round($total_saldo * 100);

            $stmt_pago = $this->pdo->prepare("
                INSERT INTO pago_pedido
                  (id_pedido, referencia, wompi_link_id, wompi_link_url, monto, monto_centavos, estado, fecha_expiracion, nota)
                VALUES
                  (?, ?, ?, ?, ?, ?, '" . EstadoPagoPedido::PENDING . "', DATE_ADD(NOW(), INTERVAL 7 DAY), ?)
            ");
            $stmt_pago->execute([
                $id_pedido_referencia,
                $referencia,
                $link_id,
                $link_pago_url,
                $total_saldo,
                $monto_centavos,
                $nota_consolidado
            ]);
            $id_pago = (int) $this->pdo->lastInsertId();

            // Vincular todos los pedidos del consolidado
            $placeholders = implode(',', array_fill(0, count($ids_pedidos), '?'));
            $stmt_upd = $this->pdo->prepare("
                UPDATE pedido_cliente
                SET id_pago_activo = ?, estado_pago = 'pendiente'
                WHERE id_pedido IN ($placeholders)
            ");
            $stmt_upd->execute(array_merge([$id_pago], $ids_pedidos));

            $this->pdo->commit();
            return $id_pago;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
