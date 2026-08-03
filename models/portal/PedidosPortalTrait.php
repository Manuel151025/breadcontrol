<?php
// models/portal/PedidosPortalTrait.php

/**
 * Ciclo de vida de los pedidos del portal: consulta, creación/edición,
 * cancelación, reportes por tienda y datos para exportaciones.
 * Parte de PortalClienteModel (dividido por responsabilidad).
 */
trait PedidosPortalTrait {

    /**
     * Obtiene los pedidos asociados a un cliente, aplicando filtros de estado, orden, variedad y aprendiz.
     * @return array<int, array<string, mixed>>
     * @param array<mixed> $filtros
     */
    public function getPedidosFiltrados(int $cliente_id, bool $es_instructor, array $filtros): array {
        $f_estado   = $filtros['estado'] ?? '';
        $f_orden    = $filtros['orden'] ?? 'recientes';
        $f_aprendiz = (int)($filtros['aprendiz_id'] ?? 0);
        $f_variedad = (int)($filtros['variedad_id'] ?? 0);

        if ($f_aprendiz > 0 && $es_instructor) {
            $where_sql = "WHERE p.id_cliente = ? AND p.id_creador = ? AND p.aprobado_instructor = 1";
            $params    = [$cliente_id, $f_aprendiz];
        } else {
            $where_sql = "WHERE (p.id_cliente = ? OR p.id_creador = ?) AND (p.id_cliente != ? OR p.aprobado_instructor = 1)";
            $params    = [$cliente_id, $cliente_id, $cliente_id];
        }

        if ($f_estado) {
            $where_sql .= " AND p.estado = ?";
            $params[]   = $f_estado;
        }

        $order_sql = match($f_orden) {
            'antiguos' => "ORDER BY p.fecha_solicitud ASC",
            'entrega'  => "ORDER BY p.fecha_entrega ASC, p.fecha_solicitud DESC",
            default    => "ORDER BY p.fecha_solicitud DESC",
        };

        $join_variedad = '';
        if ($f_variedad > 0) {
            $join_variedad = "INNER JOIN pedido_cliente_detalle pcd ON pcd.id_pedido = p.id_pedido AND pcd.id_variedad = ?";
            $params[] = $f_variedad;
        }

        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre AS nombre_creador, c.es_aprendiz AS creador_es_aprendiz
            FROM pedido_cliente p
            LEFT JOIN cliente c ON p.id_creador = c.id_cliente
            $join_variedad
            $where_sql $order_sql
            LIMIT 50
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el saldo pendiente de un cliente.
     */
    public function getSaldoPendiente(int $cliente_id): float {
        $stmt = $this->pdo->prepare("
            SELECT SUM(total_estimado) 
            FROM pedido_cliente 
            WHERE id_cliente = ? 
              AND estado != 'rechazado' 
              AND aprobado_instructor = 1
              AND (estado_pago IN ('pendiente', 'no_aplica') OR estado_pago IS NULL)
        ");
        $stmt->execute([$cliente_id]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Obtiene un pedido específico del cliente o creador.
     * @return array<string, mixed>|null
     */
    public function getPedido(int $id_pedido, int $cliente_id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre AS nombre_cliente, c.tipo AS tipo_cliente, 
                   c2.nombre AS nombre_creador, c2.es_aprendiz AS creador_es_aprendiz
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
            WHERE p.id_pedido = ? AND (p.id_cliente = ? OR p.id_creador = ?)
        ");
        $stmt->execute([$id_pedido, $cliente_id, $cliente_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Obtiene los detalles (productos) de un pedido.
     * @return array<int, array<string, mixed>>
     */
    public function getDetallesPedido(int $id_pedido): array {
        $stmt = $this->pdo->prepare("
            SELECT d.*, vp.nombre AS producto
            FROM pedido_cliente_detalle d
            JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
            WHERE d.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información del tipo de cliente asociado a un pedido.
     * @return array<mixed>
     */
    public function getClienteTipoAsociadoPedido(int $id_pedido): ?array {
        $stmt = $this->pdo->prepare("
            SELECT c.tipo, c.nombre 
            FROM pedido_cliente p 
            JOIN cliente c ON p.id_cliente = c.id_cliente 
            WHERE p.id_pedido = ?
        ");
        $stmt->execute([$id_pedido]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Cuenta cuántos pedidos de una tienda para una fecha de entrega siguen pendientes.
     */
    public function getCountPedidosPendientesTiendaFecha(int $id_cliente, string $fecha_entrega): int {
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM pedido_cliente
            WHERE id_cliente = ? AND fecha_entrega = ? AND estado = 'pendiente'
        ");
        $stmt->execute([$id_cliente, $fecha_entrega]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtiene el reporte agrupado por aprendiz y producto para tiendas.
     * @return array<string, list<array<string, mixed>>>
     */
    public function getReporteAgrupadoTienda(int $id_cliente, string $fecha_entrega): array {
        $stmt = $this->pdo->prepare("
            SELECT
                COALESCE(c2.nombre, 'Tienda') AS aprendiz,
                vp.nombre AS producto,
                SUM(d.cantidad)    AS cantidad,
                SUM(d.napa)        AS napa,
                SUM(d.bonificacion) AS bonificacion,
                p.id_pedido,
                p.total_estimado
            FROM pedido_cliente p
            JOIN pedido_cliente_detalle d ON p.id_pedido = d.id_pedido
            JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
            LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
            WHERE p.id_cliente = ?
              AND p.fecha_entrega = ?
              AND p.estado IN ('confirmado', 'pendiente')
            GROUP BY p.id_creador, d.id_variedad
            ORDER BY aprendiz, vp.nombre
        ");
        $stmt->execute([$id_cliente, $fecha_entrega]);
        $reporte = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $reporte[$row['aprendiz']][] = $row;
        }
        return $reporte;
    }

    /**
     * Obtiene el total general de pedidos confirmados/pendientes para una tienda y fecha.
     */
    public function getTotalGeneralReporteTienda(int $id_cliente, string $fecha_entrega): float {
        $stmt = $this->pdo->prepare("
            SELECT SUM(total_estimado)
            FROM pedido_cliente
            WHERE id_cliente = ? AND fecha_entrega = ? AND estado IN ('confirmado','pendiente')
        ");
        $stmt->execute([$id_cliente, $fecha_entrega]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Cancela un pedido del cliente validando primero si pertenece al cliente, si está pendiente
     * y si cumple la regla de las 48 horas.
     */
    public function cancelarPedido(int $id_pedido, int $cliente_id): bool {
        // Validar que el pedido pertenezca al cliente y esté pendiente
        $stmt = $this->pdo->prepare("SELECT * FROM pedido_cliente WHERE id_pedido = ? AND (id_cliente = ? OR id_creador = ?)");
        $stmt->execute([$id_pedido, $cliente_id, $cliente_id]);
        $pedido = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pedido || $pedido['estado'] !== 'pendiente') {
            throw new Exception("El pedido no se puede cancelar porque no existe o no está pendiente.");
        }

        // Bloqueo por pago en proceso si el cancelador es aprendiz
        if (!empty($pedido['id_pago_activo'])) {
            $stmt_pay_check = $this->pdo->prepare("SELECT estado FROM pago_pedido WHERE id_pago = ?");
            $stmt_pay_check->execute([(int)$pedido['id_pago_activo']]);
            $pay_status = $stmt_pay_check->fetchColumn();
            if ($pay_status && in_array(strtoupper((string) $pay_status), ['PENDING', 'PENDIENTE'])) {
                $stmt_cli_check = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
                $stmt_cli_check->execute([$cliente_id]);
                if ((int)$stmt_cli_check->fetchColumn() === 1) {
                    throw new Exception("No puedes cancelar este pedido porque está vinculado a un pago en proceso de tu instructor.");
                }
            }
        }

        // Validar restricción de 48 horas
        if (ReglasPortal::fueraDeLimiteGestion($pedido['fecha_entrega'])) {
            throw new Exception("No es posible cancelar este pedido (menos de 48 horas para la entrega).");
        }

        try {
            $this->pdo->beginTransaction();

            // Cambiar estado a rechazado
            $stmt_upd = $this->pdo->prepare("UPDATE pedido_cliente SET estado = 'rechazado', mensaje_propietario = 'Cancelado por el cliente' WHERE id_pedido = ?");
            $stmt_upd->execute([$id_pedido]);

            // Expirar pago si formaba parte de un pago consolidado/individual
            if (!empty($pedido['id_pago_activo'])) {
                $id_pago = (int)$pedido['id_pago_activo'];
                
                $stmt_pay = $this->pdo->prepare("UPDATE pago_pedido SET estado = '" . EstadoPagoPedido::EXPIRED . "' WHERE id_pago = ? AND estado IN (" . EstadoPagoPedido::pendientesSql() . ")");
                $stmt_pay->execute([$id_pago]);
                
                $stmt_ped = $this->pdo->prepare("UPDATE pedido_cliente SET id_pago_activo = NULL, estado_pago = 'no_aplica' WHERE id_pago_activo = ?");
                $stmt_ped->execute([$id_pago]);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Transacción para crear o actualizar un pedido con su detalle y validaciones de bonificación/ñapa.
     * @param array<mixed> $cart
     * @param array<mixed> $bonif_items
     */
    public function crearPedido(int $cliente_id, int $id_creador, string $fecha_entrega, array $cart, array $bonif_items, ?int $edit_id = null): int {
        try {
            $this->pdo->beginTransaction();

            $total_dinero = 0;
            $cart_validado = [];
            
            $stmt_precio = $this->pdo->prepare("
                SELECT cp.precio_unitario 
                FROM variedad_pan vp 
                JOIN categoria_precio cp ON vp.id_categoria_precio = cp.id_categoria 
                WHERE vp.id_variedad = ? AND vp.activo = 1
            ");
            
            foreach ($cart as $item) {
                $id_var = (int)($item['id_variedad'] ?? 0);
                $cant = (int)($item['cantidad'] ?? 0);
                if ($cant <= 0) continue;
                if ($cant > 99) $cant = 99;
                
                $stmt_precio->execute([$id_var]);
                $precio_real = $stmt_precio->fetchColumn();
                
                if ($precio_real !== false) {
                    $total_dinero += $cant * (float)$precio_real;
                    $cart_validado[] = [
                        'id_variedad' => $id_var,
                        'cantidad' => $cant,
                        'precio' => (float)$precio_real
                    ];
                } else {
                    throw new Exception("Producto del carrito no válido o inactivo.");
                }
            }
            
            if (empty($cart_validado)) {
                throw new Exception("El carrito no contiene productos válidos.");
            }

            // Validar cupo semanal de aprendiz. Se bloquea la fila del creador (FOR UPDATE)
            // para serializar pedidos concurrentes del mismo aprendiz y que dos pedidos casi
            // simultaneos no lean el mismo consumo "antes" del commit y excedan el cupo (D1/L5).
            $stmt_creador = $this->pdo->prepare("SELECT es_aprendiz, cupo_semanal FROM cliente WHERE id_cliente = ? FOR UPDATE");
            $stmt_creador->execute([$id_creador]);
            $creador_info = $stmt_creador->fetch(PDO::FETCH_ASSOC);

            if ($creador_info && (int)$creador_info['es_aprendiz'] === 1 && $cliente_id !== $id_creador) {
                $cupo_semanal = (float)$creador_info['cupo_semanal'];
                
                // Calcular lo consumido esta semana (excluyendo el propio pedido si se está editando)
                $sql_consumido = "
                    SELECT COALESCE(SUM(total_estimado), 0) 
                    FROM pedido_cliente 
                    WHERE id_creador = ? 
                      AND id_cliente = ?
                      AND estado != 'rechazado'
                      AND fecha_solicitud >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), '%Y-%m-%d 00:00:00')
                ";
                $args_consumido = [$id_creador, $cliente_id];
                if ($edit_id > 0) {
                    $sql_consumido .= " AND id_pedido != ?";
                    $args_consumido[] = $edit_id;
                }
                
                $stmt_cons = $this->pdo->prepare($sql_consumido);
                $stmt_cons->execute($args_consumido);
                $consumido_semana = (float)$stmt_cons->fetchColumn();
                
                if (ReglasPortal::excedeCupoSemanal($consumido_semana, $total_dinero, $cupo_semanal)) {
                    $monto_exceso = ($consumido_semana + $total_dinero) - $cupo_semanal;
                    throw new Exception("Límite de cupo semanal excedido. Tu cupo semanal es de $" . number_format($cupo_semanal, 0, ',', '.') . " COP. Ya has consumido $" . number_format($consumido_semana, 0, ',', '.') . " COP esta semana y este pedido de $" . number_format($total_dinero, 0, ',', '.') . " COP excede el cupo en $" . number_format($monto_exceso, 0, ',', '.') . " COP.");
                }
            }

            if ($edit_id > 0) {
                // Validar que exista el pedido y esté pendiente
                $stmt_chk = $this->pdo->prepare("SELECT id_pedido, estado, fecha_entrega, id_cliente, id_pago_activo FROM pedido_cliente WHERE id_pedido = ? AND (id_cliente = ? OR id_creador = ?)");
                $stmt_chk->execute([$edit_id, $cliente_id, $cliente_id]);
                $ped_chk = $stmt_chk->fetch(PDO::FETCH_ASSOC);

                if (!$ped_chk || $ped_chk['estado'] !== 'pendiente') {
                    throw new Exception("No puedes editar este pedido.");
                }

                // Bloqueo por pago en proceso si el creador es aprendiz
                if (!empty($ped_chk['id_pago_activo'])) {
                    $stmt_pay_check = $this->pdo->prepare("SELECT estado FROM pago_pedido WHERE id_pago = ?");
                    $stmt_pay_check->execute([(int)$ped_chk['id_pago_activo']]);
                    $pay_status = $stmt_pay_check->fetchColumn();
                    if ($pay_status && in_array(strtoupper((string) $pay_status), ['PENDING', 'PENDIENTE'])) {
                        $stmt_cli_check = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
                        $stmt_cli_check->execute([$id_creador]);
                        if ((int)$stmt_cli_check->fetchColumn() === 1) {
                            throw new Exception("No puedes editar este pedido porque está vinculado a un pago en proceso de tu instructor.");
                        }
                    }
                }

                // Validar restricción de 48 horas en el guardado
                if (ReglasPortal::fueraDeLimiteGestion($ped_chk['fecha_entrega'])) {
                    throw new Exception("Ya no es posible editar este pedido (menos de 48 horas para la entrega).");
                }
                
                $id_cli_real = $ped_chk['id_cliente'];
                $aprobado_instructor = ($cliente_id === $id_creador) ? 1 : 0;
                $stmt_ped = $this->pdo->prepare("UPDATE pedido_cliente SET fecha_entrega = ?, total_estimado = ?, id_cliente = ?, aprobado_instructor = ? WHERE id_pedido = ?");
                $stmt_ped->execute([$fecha_entrega, $total_dinero, $cliente_id, $aprobado_instructor, $edit_id]);
                $id_pedido = $edit_id;
                
                $this->pdo->prepare("DELETE FROM pedido_cliente_detalle WHERE id_pedido = ?")->execute([$id_pedido]);
            } else {
                // estado_pago = 'no_aplica' explicito (E3): un pedido recien creado no tiene
                // pago asociado todavia; 'pendiente' se asigna solo cuando existe un pago_pedido.
                //
                // aprobado_instructor se fija SIEMPRE de forma explicita: un pedido dirigido a
                // OTRA cuenta (aprendiz -> instructor, id_cliente != id_creador) nace en 0 y
                // requiere la aprobacion del instructor; un pedido propio (id_cliente = id_creador,
                // cliente normal o pedido personal del aprendiz) nace en 1. El DEFAULT de la
                // columna (0) es solo una red de seguridad por si algun INSERT futuro lo omite.
                $aprobado_instructor = ($cliente_id === $id_creador) ? 1 : 0;
                $stmt_ped = $this->pdo->prepare("INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, aprobado_instructor, estado_pago) VALUES (?, ?, ?, ?, ?, 'no_aplica')");
                $stmt_ped->execute([$cliente_id, $id_creador, $fecha_entrega, $total_dinero, $aprobado_instructor]);
                $id_pedido = (int)$this->pdo->lastInsertId();
            }

            // Guardar detalles del carrito
            $stmt_det = $this->pdo->prepare("INSERT INTO pedido_cliente_detalle (id_pedido, id_variedad, cantidad, precio_unitario, napa, bonificacion) VALUES (?, ?, ?, ?, 0, 0)");
            foreach ($cart_validado as $item) {
                $stmt_det->execute([$id_pedido, $item['id_variedad'], $item['cantidad'], $item['precio']]);
            }

            // Calcular y validar bonificaciones/ñapas
            $stmt_cli_tipo = $this->pdo->prepare("SELECT tipo FROM cliente WHERE id_cliente = ?");
            $stmt_cli_tipo->execute([$cliente_id]);
            $cli_tipo = $stmt_cli_tipo->fetchColumn();
            $es_tienda_actual = ($cli_tipo === 'tienda');

            // Si el creador del pedido es un de tipo aprendiz, la tarifa de bonificación será de Mostrador ($500 por cada $5000)
            $stmt_creador_check = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
            $stmt_creador_check->execute([$id_creador]);
            $creador_es_aprendiz = ((int)$stmt_creador_check->fetchColumn() === 1);

            if ($creador_es_aprendiz) {
                $es_tienda_actual = false;
            }

            $max_bonif_credit = ReglasPortal::calcularCredito($es_tienda_actual, $total_dinero);
            $total_bonif_cost = 0;
            
            if (!empty($bonif_items)) {
                foreach ($bonif_items as $bi) {
                    $cant = (int)($bi['cantidad'] ?? 0);
                    $id_var = (int)($bi['id_variedad'] ?? 0);
                    if ($cant > 0 && $id_var > 0) {
                        if ($cant > 99) $cant = 99;
                        $stmt_precio->execute([$id_var]);
                        $precio_real = $stmt_precio->fetchColumn();
                        if ($precio_real !== false) {
                            $total_bonif_cost += $cant * (float)$precio_real;
                        } else {
                            throw new Exception("Variedad de bonificación no válida o inactiva.");
                        }
                    }
                }
            }

            if ($total_bonif_cost > $max_bonif_credit) {
                throw new Exception("El valor de la bonificación/ñapa ($" . number_format($total_bonif_cost, 0, ',', '.') . " COP) supera el crédito permitido ($" . number_format($max_bonif_credit, 0, ',', '.') . " COP).");
            }

            // Guardar bonificaciones/ñapas
            if (!empty($bonif_items)) {
                foreach ($bonif_items as $bi) {
                    $cant = (int)($bi['cantidad'] ?? 0);
                    $id_var = (int)($bi['id_variedad'] ?? 0);
                    
                    if ($cant > 0 && $id_var > 0) {
                        if ($cant > 99) $cant = 99;
                        $napa = $es_tienda_actual ? 0 : $cant;
                        $bonif = $es_tienda_actual ? $cant : 0;
                        
                        $stmt_precio->execute([$id_var]);
                        $precio_real = $stmt_precio->fetchColumn();
                        
                        if ($precio_real !== false) {
                            $this->pdo->prepare("INSERT INTO pedido_cliente_detalle (id_pedido, id_variedad, cantidad, precio_unitario, napa, bonificacion) VALUES (?, ?, 0, ?, ?, ?)")
                                ->execute([$id_pedido, $id_var, (float)$precio_real, $napa, $bonif]);
                        } else {
                            throw new Exception("Variedad de bonificación no válida o inactiva.");
                        }
                    }
                }
            }

            $this->pdo->commit();
            return $id_pedido;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Verifica que un conjunto de IDs de pedidos pertenezcan a un cliente (como cliente o creador).
     * @param array<mixed> $ids
     */
    public function verificarPedidosPertenecenCliente(array $ids, int $cliente_id): bool {
        if (empty($ids)) return false;
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM pedido_cliente
            WHERE id_pedido IN ($placeholders) AND (id_cliente = ? OR id_creador = ?)
        ");
        $stmt->execute(array_merge($ids, [$cliente_id, $cliente_id]));
        return (int)$stmt->fetchColumn() === count($ids);
    }

    /**
     * Obtiene los pedidos seleccionados con sus respectivos aprendices.
     * @return array<int, array<string, mixed>>
     * @param array<mixed> $ids
     */
    public function getPedidosDetalladosParaExportacion(array $ids): array {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT p.id_pedido, p.fecha_entrega, p.fecha_solicitud, p.total_estimado, p.estado,
                   COALESCE(c2.nombre, 'Mismo cliente') AS aprendiz
            FROM pedido_cliente p
            LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
            WHERE p.id_pedido IN ($placeholders)
            ORDER BY p.fecha_entrega ASC, c2.nombre ASC
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene todos los detalles de productos para un conjunto de IDs de pedidos.
     * @return array<int, array<string, mixed>>
     * @param array<mixed> $ids
     */
    public function getDetallesPedidosParaExportacion(array $ids): array {
        if (empty($ids)) return [];
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("
            SELECT d.id_pedido, vp.nombre AS producto,
                   d.cantidad, d.napa, d.bonificacion
            FROM pedido_cliente_detalle d
            JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
            WHERE d.id_pedido IN ($placeholders)
            ORDER BY vp.nombre
        ");
        $stmt->execute($ids);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información de un pedido y verifica que sea de tipo tienda y pertenezca al cliente.
     * @return array<string, mixed>|null
     */
    public function getPedidoTiendaParaExportacion(int $id_pedido, int $cliente_id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT p.*, c.nombre AS nombre_tienda, c.tipo
            FROM pedido_cliente p
            JOIN cliente c ON p.id_cliente = c.id_cliente
            WHERE p.id_pedido = ? AND (p.id_cliente = ? OR p.id_creador = ?) AND c.tipo = 'tienda'
        ");
        $stmt->execute([$id_pedido, $cliente_id, $cliente_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }
}
