<?php
// models/PedidoClienteModel.php

require_once __DIR__ . '/../includes/estados_pago.php';

class PedidoClienteModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener los cobros pendientes por tienda
     */
    public function getCobrosPendientesTiendas() {
        return $this->pdo->query("
            SELECT p.id_pedido, p.id_cliente, p.total_estimado, p.fecha_entrega, p.fecha_solicitud,
                   c.id_cliente AS cli_id, c.nombre AS nombre_tienda
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            WHERE c.tipo = 'tienda'
              AND p.estado != 'rechazado'
              AND p.aprobado_instructor = 1
              AND p.estado_pago IN ('pendiente','no_aplica')
            ORDER BY p.id_cliente, p.fecha_entrega ASC
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener configuración de pagos
     */
    public function getConfigPago() {
        return $this->pdo->query("SELECT nequi_link_pago, nequi_titular, wompi_habilitado, wompi_confirmar_auto FROM configuracion LIMIT 1")->fetch();
    }

    /**
     * Obtener pedidos filtrados
     */
    public function getPedidos(array $where = [], array $params = []) {
        $where[] = "p.aprobado_instructor = 1";
        $sql_where = "WHERE " . implode(" AND ", $where);
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre as cliente, c.tipo as tipo_cliente, 
                   c2.nombre as nombre_creador, c2.es_aprendiz as creador_es_aprendiz
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
            $sql_where
            ORDER BY p.fecha_solicitud DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtener pedidos por un listado de IDs (para exportar)
     */
    public function getPedidosPorIds(array $ids) {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre as cliente, c.tipo as tipo_cliente, c.telefono
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            WHERE p.id_pedido IN ($inQuery)
            ORDER BY p.fecha_solicitud DESC
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener detalles de productos de un listado de IDs de pedidos
     */
    public function getDetallesPedidosPorIds(array $ids) {
        $inQuery = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT d.id_pedido, d.cantidad, d.napa, d.bonificacion, vp.nombre 
            FROM pedido_cliente_detalle d 
            JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad 
            WHERE d.id_pedido IN ($inQuery)
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener un pedido por ID
     */
    public function getPedido(int $id_pedido) {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre as cliente, c.tipo as tipo_cliente, c.telefono, 
                   c2.nombre as nombre_creador, c2.es_aprendiz as creador_es_aprendiz
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
            WHERE p.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        return $stmt->fetch();
    }

    /**
     * Obtener detalles de productos de un pedido
     */
    public function getDetallesPedido(int $id_pedido) {
        $stmt = $this->pdo->prepare("
            SELECT d.*, vp.nombre as producto 
            FROM pedido_cliente_detalle d 
            JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad 
            WHERE d.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener registro de pago activo por ID
     */
    public function getPagoPedido(int $id_pago) {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ?");
        $stmt->execute([$id_pago]);
        return $stmt->fetch();
    }

    /**
     * Obtener abonos asociados a un pago
     */
    public function getAbonosPago(int $id_pago) {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_abono WHERE id_pago = ? ORDER BY fecha_abono ASC");
        $stmt->execute([$id_pago]);
        return $stmt->fetchAll();
    }

    /**
     * Obtener pedidos consolidados bajo un pago
     */
    public function getPedidosConsolidadosPago(int $id_pago) {
        $stmt = $this->pdo->prepare("SELECT id_pedido, total_estimado FROM pedido_cliente WHERE id_pago_activo = ?");
        $stmt->execute([$id_pago]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cambiar estado de pedidos en lote
     */
    public function cambiarEstadoLote(array $ids, string $nuevo_estado): int {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE pedido_cliente SET estado = ? WHERE id_pedido IN ($placeholders)");
        $stmt->execute(array_merge([$nuevo_estado], $ids));
        return $stmt->rowCount();
    }

    /**
     * Confirmar cobro masivo de tienda
     */
    public function confirmarCobroTienda(array $ids_pedidos, bool $auto_confirmar): int {
        $this->pdo->beginTransaction();
        try {
            $ph = implode(',', array_fill(0, count($ids_pedidos), '?'));

            $sql_upd = "UPDATE pedido_cliente SET estado_pago = 'aprobado'"
                     . ($auto_confirmar ? ", estado = CASE WHEN estado = 'pendiente' THEN 'confirmado' ELSE estado END" : "")
                     . " WHERE id_pedido IN ($ph)";
            $upd = $this->pdo->prepare($sql_upd);
            $upd->execute($ids_pedidos);

            // Actualizar pago_pedido vinculados al enum canonico APPROVED. El WHERE es
            // tolerante a datos historicos en minuscula ('pendiente') ademas de 'PENDING'
            // (B3): antes filtraba solo 'pendiente' y nunca casaba con las filas 'PENDING'.
            $this->pdo->prepare("
                UPDATE pago_pedido pp
                INNER JOIN pedido_cliente pc ON pc.id_pago_activo = pp.id_pago
                SET pp.estado = '" . EstadoPagoPedido::APPROVED . "'
                WHERE pc.id_pedido IN ($ph) AND pp.estado IN (" . EstadoPagoPedido::pendientesSql() . ")
            ")->execute($ids_pedidos);

            $this->pdo->commit();
            return $upd->rowCount();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Actualizar estado del pedido individualmente
     */
    public function updatePedidoEstado(int $id_pedido, string $estado, string $mensaje): bool {
        $stmt = $this->pdo->prepare("UPDATE pedido_cliente SET estado = ?, mensaje_propietario = ? WHERE id_pedido = ?");
        return $stmt->execute([$estado, $mensaje, $id_pedido]);
    }

    /**
     * Habilitar pago digital para un pedido
     */
    public function habilitarPagoDigital(int $id_pedido, string $referencia, ?string $link_id, string $link_url, float $monto): bool {
        $this->pdo->beginTransaction();
        try {
            $monto_centavos = (int) round($monto * 100);
            $stmt = $this->pdo->prepare("
                INSERT INTO pago_pedido
                  (id_pedido, referencia, wompi_link_id, wompi_link_url, monto, monto_centavos, estado, fecha_expiracion)
                VALUES (?, ?, ?, ?, ?, ?, '" . EstadoPagoPedido::PENDING . "', DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute([$id_pedido, $referencia, $link_id, $link_url, $monto, $monto_centavos]);
            $id_pago_nuevo = (int) $this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare("UPDATE pedido_cliente SET estado_pago = 'pendiente', id_pago_activo = ? WHERE id_pedido = ?");
            $stmt->execute([$id_pago_nuevo, $id_pedido]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Registrar un abono individual a un pago activo
     */
    public function registrarAbonoPago(int $id_pago_activo, float $monto_recibido, string $metodo, ?string $nota): bool {
        $this->pdo->beginTransaction();
        try {
            // 1. Insertar el abono
            $stmt_abono = $this->pdo->prepare("
                INSERT INTO pago_abono (id_pago, monto, metodo_pago, nota, fecha_abono)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt_abono->execute([$id_pago_activo, $monto_recibido, $metodo, $nota ?: null]);

            // 2. Suma acumulada de todos los abonos
            $stmt_sum_ab = $this->pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
            $stmt_sum_ab->execute([$id_pago_activo]);
            $total_abonado = (float)$stmt_sum_ab->fetchColumn();

            // 3. Total esperado consolidado de los pedidos
            $stmt_sum = $this->pdo->prepare("SELECT SUM(total_estimado) FROM pedido_cliente WHERE id_pago_activo = ?");
            $stmt_sum->execute([$id_pago_activo]);
            $total_esperado = (float)$stmt_sum->fetchColumn();

            // 4. Determinar estado
            $es_pago_parcial = ($total_abonado < ($total_esperado - 1));
            $nuevo_estado_pago = $es_pago_parcial ? 'parcial' : 'aprobado';
            $nuevo_estado_pago_pedido = $es_pago_parcial ? EstadoPagoPedido::PARTIAL : EstadoPagoPedido::APPROVED;

            $total_abonado_centavos = (int) round($total_abonado * 100);

            // 5. Actualizar pago_pedido
            $stmt = $this->pdo->prepare("
                UPDATE pago_pedido
                SET estado = ?, fecha_pago = NOW(), metodo_pago = ?, nota = ?, monto = ?, monto_centavos = ?
                WHERE id_pago = ?
            ");
            $stmt->execute([$nuevo_estado_pago_pedido, $metodo, $nota ?: null, $total_abonado, $total_abonado_centavos, $id_pago_activo]);

            // 6. Actualizar pedidos
            $stmt = $this->pdo->prepare("UPDATE pedido_cliente SET estado_pago = ? WHERE id_pago_activo = ?");
            $stmt->execute([$nuevo_estado_pago, $id_pago_activo]);

            // Forzar estado confirmado
            $stmt = $this->pdo->prepare("UPDATE pedido_cliente SET estado = 'confirmado' WHERE id_pago_activo = ? AND estado = 'pendiente'");
            $stmt->execute([$id_pago_activo]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Deshabilitar pago digital de un pedido
     */
    public function deshabilitarPagoDigital(int $id_pago): bool {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE pago_pedido SET estado = '" . EstadoPagoPedido::EXPIRED . "' WHERE id_pago = ?")->execute([$id_pago]);
            $this->pdo->prepare("UPDATE pedido_cliente SET estado_pago = 'no_aplica', id_pago_activo = NULL WHERE id_pago_activo = ?")->execute([$id_pago]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Revertir pago aprobado/parcial
     */
    public function revertirPagoDigital(int $id_pago): bool {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE pago_pedido SET estado = '" . EstadoPagoPedido::VOIDED . "' WHERE id_pago = ?")->execute([$id_pago]);
            $this->pdo->prepare("UPDATE pedido_cliente SET estado_pago = 'no_aplica', id_pago_activo = NULL WHERE id_pago_activo = ?")->execute([$id_pago]);
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
