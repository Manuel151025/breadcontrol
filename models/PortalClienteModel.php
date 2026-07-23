<?php
// models/PortalClienteModel.php

require_once __DIR__ . '/../includes/estados_pago.php';

class PortalClienteModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Busca un cliente activo por nombre de usuario.
     */
    public function getClienteByUsuario(string $usuario): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por ID.
     */
    public function getClienteById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE id_cliente = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por correo electrónico (Google OAuth).
     */
    public function getClienteByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por google_id.
     */
    public function getClienteByGoogleId(string $google_id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE google_id = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$google_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Vincula google_id y foto_url a un cliente existente.
     */
    public function vincularGoogleId(int $id_cliente, string $google_id, string $foto_url): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET google_id = ?, foto_url = ? WHERE id_cliente = ?");
        return $stmt->execute([$google_id, $foto_url, $id_cliente]);
    }

    /**
     * Registra un cliente tradicional.
     */
    public function registrarCliente(string $nombre, string $tipo, string $telefono, string $usuario, string $hash, int $es_aprendiz, ?int $id_instructor = null): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO cliente (nombre, tipo, telefono, usuario, contrasena_hash, es_aprendiz, id_instructor, activo, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        return $stmt->execute([$nombre, $tipo, $telefono, $usuario, $hash, $es_aprendiz, $id_instructor]);
    }

    /**
     * Registra un cliente de Google.
     */
    public function registrarClienteGoogle(string $google_id, ?string $email, string $nombre, string $avatar): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO cliente (nombre, tipo, email, google_id, foto_url, activo, fecha_creacion)
            VALUES (?, 'mostrador', ?, ?, ?, 1, NOW())
        ");
        $stmt->execute([$nombre, $email, $google_id, $avatar]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Completa el perfil de Google de un cliente.
     */
    public function completarPerfilCliente(int $id, string $nombre, int $es_aprendiz, ?int $id_instructor = null): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET nombre = ?, es_aprendiz = ?, id_instructor = ? WHERE id_cliente = ?");
        return $stmt->execute([$nombre, $es_aprendiz, $id_instructor, $id]);
    }

    /**
     * Actualiza la información básica del cliente.
     */
    public function actualizarPerfil(int $id, string $nombre, string $telefono, int $es_aprendiz, ?int $id_instructor = null): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET nombre = ?, telefono = ?, es_aprendiz = ?, id_instructor = ? WHERE id_cliente = ?");
        return $stmt->execute([$nombre, $telefono, $es_aprendiz, $id_instructor, $id]);
    }

    /**
     * Actualiza la contraseña del cliente.
     */
    public function actualizarPassword(int $id, string $hash): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET contrasena_hash = ? WHERE id_cliente = ?");
        return $stmt->execute([$hash, $id]);
    }

    /**
     * Actualiza el PIN de recuperación del cliente.
     */
    public function actualizarPin(int $id, string $hash): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET pin_recuperacion = ? WHERE id_cliente = ?");
        return $stmt->execute([$hash, $id]);
    }

    /**
     * Registra un código de recuperación por correo.
     */
    public function registrarCodigoRecuperacion(int $id_cliente, string $codigo, string $expira): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET codigo_recuperacion = ?, codigo_expira = ? WHERE id_cliente = ?");
        return $stmt->execute([$codigo, $expira, $id_cliente]);
    }

    /**
     * Limpia el código de recuperación.
     */
    public function limpiarCodigoRecuperacion(int $id_cliente): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET codigo_recuperacion = NULL, codigo_expira = NULL WHERE id_cliente = ?");
        return $stmt->execute([$id_cliente]);
    }

    /**
     * Obtiene los datos de pago configurados en la panadería.
     */
    public function getConfiguracionPago(): array {
        return $this->pdo->query("SELECT nequi_link_pago, nequi_titular, wompi_habilitado FROM configuracion LIMIT 1")->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Obtiene variedades activas.
     */
    public function getVariedadesPanActivas(): array {
        $stmt = $this->pdo->query("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE activo = 1 ORDER BY nombre ASC");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el catálogo completo de productos con precio.
     */
    public function getProductosActivos(): array {
        return $this->pdo->query("
            SELECT vp.id_variedad, vp.nombre, vp.imagen, vp.id_categoria_precio,
                   cp.nombre AS cat_nombre, cp.precio_unitario
            FROM variedad_pan vp
            INNER JOIN categoria_precio cp ON cp.id_categoria = vp.id_categoria_precio
            WHERE vp.activo = 1 
            ORDER BY cp.precio_unitario, vp.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el listado de categorías activas para los productos.
     */
    public function getCategoriasActivas(): array {
        return $this->pdo->query("SELECT * FROM categoria_precio WHERE activo = 1 ORDER BY precio_unitario")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene variedades activas por categoría.
     */
    public function getVariedadesPorCategoria(int $id_cat): array {
        $stmt = $this->pdo->prepare("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE id_categoria_precio = ? AND activo = 1 ORDER BY nombre");
        $stmt->execute([$id_cat]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene los pedidos asociados a un cliente, aplicando filtros de estado, orden, variedad y aprendiz.
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
     * Obtiene abonos relacionados a un pago.
     */
    public function getAbonos(int $id_pago): array {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_abono WHERE id_pago = ? ORDER BY fecha_abono ASC");
        $stmt->execute([$id_pago]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene la información del tipo de cliente asociado a un pedido.
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
     * Obtiene los KPIs de un instructor.
     */
    public function getInstructorStats(int $cliente_id): array {
        // Resumen financiero global
        $sf = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN estado != 'rechazado' AND aprobado_instructor = 1 AND MONTH(fecha_solicitud) = MONTH(NOW()) AND YEAR(fecha_solicitud) = YEAR(NOW()) THEN total_estimado ELSE 0 END), 0) AS total_mes,
                COUNT(DISTINCT CASE WHEN aprobado_instructor = 1 THEN id_creador ELSE NULL END) AS aprendices_activos,
                COUNT(CASE WHEN aprobado_instructor = 1 THEN 1 ELSE NULL END) AS total_pedidos
            FROM pedido_cliente
            WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ?
        ");
        $sf->execute([$cliente_id, $cliente_id]);
        $resumen = $sf->fetch(PDO::FETCH_ASSOC) ?: [];

        // Obtener pedidos pendientes de cobro para calcular pendiente real
        $stmt_pends = $this->pdo->prepare("
            SELECT id_pedido, total_estimado, id_pago_activo
            FROM pedido_cliente
            WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ? AND estado != 'rechazado' AND aprobado_instructor = 1
              AND (estado_pago IS NULL OR estado_pago IN ('pendiente', 'no_aplica', 'parcial'))
        ");
        $stmt_pends->execute([$cliente_id, $cliente_id]);
        $pedidos_pends = $stmt_pends->fetchAll(PDO::FETCH_ASSOC);

        $pedidos_por_pago = [];
        foreach ($pedidos_pends as $p) {
            $pago_id = !empty($p['id_pago_activo']) ? (int)$p['id_pago_activo'] : 0;
            $pedidos_por_pago[$pago_id][] = $p;
        }

        $pendiente_total_real = 0.0;
        foreach ($pedidos_por_pago as $pago_id => $grupo_pedidos) {
            $suma_grupo = 0.0;
            foreach ($grupo_pedidos as $p) {
                $suma_grupo += (float)$p['total_estimado'];
            }
            if ($pago_id === 0) {
                $pendiente_total_real += $suma_grupo;
            } else {
                $stmt_ab = $this->pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
                $stmt_ab->execute([$pago_id]);
                $abonado = (float)$stmt_ab->fetchColumn();
                
                $deficit = $suma_grupo - $abonado;
                if ($deficit > 0) {
                    $pendiente_total_real += $deficit;
                }
            }
        }

        $resumen['pendiente_total'] = $pendiente_total_real;
        return $resumen;
    }

    /**
     * Obtiene el listado de alumnos y sus deudas.
     */
    public function getAprendicesResumen(int $cliente_id): array {
        $sa = $this->pdo->prepare("
            SELECT
                c.id_cliente,
                c.nombre,
                c.telefono,
                c.email,
                c.foto_url,
                c.cupo_semanal,
                (
                    SELECT COALESCE(SUM(pc.total_estimado), 0)
                    FROM pedido_cliente pc
                    WHERE pc.id_creador = c.id_cliente
                      AND pc.id_cliente = ?
                      AND pc.estado != 'rechazado'
                      AND pc.fecha_solicitud >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), '%Y-%m-%d 00:00:00')
                ) AS consumido_semana,
                COUNT(CASE WHEN p.aprobado_instructor = 1 THEN p.id_pedido ELSE NULL END) AS total_pedidos,
                COALESCE(SUM(CASE WHEN p.estado != 'rechazado' AND p.aprobado_instructor = 1 THEN p.total_estimado ELSE 0 END), 0) AS total_comprado,
                COALESCE(SUM(CASE WHEN p.estado != 'rechazado' AND p.aprobado_instructor = 1 AND p.estado_pago IN ('pendiente','no_aplica') THEN p.total_estimado ELSE 0 END), 0) AS saldo_pendiente,
                MAX(CASE WHEN p.aprobado_instructor = 1 THEN p.fecha_solicitud ELSE NULL END) AS ultimo_pedido,
                COALESCE(SUM(CASE WHEN p.estado = 'pendiente' AND p.aprobado_instructor = 1 THEN 1 ELSE 0 END), 0) AS sin_confirmar
            FROM cliente c
            LEFT JOIN pedido_cliente p ON p.id_creador = c.id_cliente AND p.id_cliente = ?
            WHERE c.es_aprendiz = 1 AND c.activo = 1 AND c.id_instructor = ?
            GROUP BY c.id_cliente, c.nombre, c.telefono, c.email, c.foto_url, c.cupo_semanal
            ORDER BY saldo_pendiente DESC, total_comprado DESC
        ");
        $sa->execute([$cliente_id, $cliente_id, $cliente_id]);
        return $sa->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Cuenta los aprendices activos en el sistema.
     */
    public function getCountAprendicesActivos(): int {
        return (int)$this->pdo->query("SELECT COUNT(*) FROM cliente WHERE es_aprendiz = 1 AND activo = 1")->fetchColumn();
    }

    /**
     * Cuenta los aprendices vinculados a un instructor. Metodo unico y parametrizado
     * que reemplaza la interpolacion directa de $cliente_id repetida en 6 puntos del
     * controlador (A2/C16): elimina el anti-patron de SQL concatenado.
     */
    public function contarAprendices(int $instructor_id, bool $soloActivos = false): int {
        $sql = "SELECT COUNT(*) FROM cliente WHERE es_aprendiz = 1 AND id_instructor = ?";
        if ($soloActivos) {
            $sql .= " AND activo = 1";
        }
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$instructor_id]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtiene pedidos de aprendices listos para ser pagados por el instructor.
     */
    public function getPedidosPagoInstructor(int $cliente_id): array {
        $sp = $this->pdo->prepare("
            SELECT p.id_pedido, p.total_estimado, p.fecha_entrega, p.fecha_solicitud,
                   c.nombre AS nombre_creador
            FROM pedido_cliente p
            LEFT JOIN cliente c ON p.id_creador = c.id_cliente
            WHERE p.id_cliente = ?
              AND p.id_creador IS NOT NULL AND p.id_creador != ?
              AND p.estado != 'rechazado'
              AND p.aprobado_instructor = 1
              AND p.estado_pago IN ('pendiente','no_aplica')
            ORDER BY p.fecha_entrega ASC
        ");
        $sp->execute([$cliente_id, $cliente_id]);
        return $sp->fetchAll(PDO::FETCH_ASSOC);
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
            if ($pay_status && in_array(strtoupper($pay_status), ['PENDING', 'PENDIENTE'])) {
                $stmt_cli_check = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
                $stmt_cli_check->execute([$cliente_id]);
                if ((int)$stmt_cli_check->fetchColumn() === 1) {
                    throw new Exception("No puedes cancelar este pedido porque está vinculado a un pago en proceso de tu instructor.");
                }
            }
        }

        // Validar restricción de 48 horas
        $fecha_entrega = new DateTime($pedido['fecha_entrega']);
        $ahora = new DateTime();
        $diff = $ahora->diff($fecha_entrega);
        $horas_restantes = ($diff->days * 24) + $diff->h;
        $esta_vencido = $diff->invert == 1;

        if ($esta_vencido || $horas_restantes < 48) {
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
     */
    public function getPagoPendientePorId(int $id_pago): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ? AND estado IN (" . EstadoPagoPedido::pendientesSql() . ")");
        $stmt->execute([$id_pago]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Registra el inicio de un pago consolidado.
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

    /**
     * Transacción para crear o actualizar un pedido con su detalle y validaciones de bonificación/ñapa.
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
                
                if ($consumido_semana + $total_dinero > $cupo_semanal) {
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
                    if ($pay_status && in_array(strtoupper($pay_status), ['PENDING', 'PENDIENTE'])) {
                        $stmt_cli_check = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
                        $stmt_cli_check->execute([$id_creador]);
                        if ((int)$stmt_cli_check->fetchColumn() === 1) {
                            throw new Exception("No puedes editar este pedido porque está vinculado a un pago en proceso de tu instructor.");
                        }
                    }
                }

                // Validar restricción de 48 horas en el guardado
                $fe_dt = new DateTime($ped_chk['fecha_entrega']);
                $ahora = new DateTime();
                $diff = $ahora->diff($fe_dt);
                $hrs = ($diff->days * 24) + $diff->h;
                if ($diff->invert == 1 || $hrs < 48) {
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

            $max_bonif_credit = $es_tienda_actual ? floor($total_dinero / 5000) * 1000 : floor($total_dinero / 5000) * 500;
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

    /**
     * Obtiene el listado de instructores (clientes tipo tienda activos).
     */
    public function getInstructoresActivos(): array {
        return $this->pdo->query("SELECT id_cliente, nombre FROM cliente WHERE tipo = 'tienda' AND activo = 1 AND es_aprendiz = 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene pedidos de aprendices vinculados al instructor que están pendientes de aprobación.
     */
    public function getPedidosPendientesAprobacionInstructor(int $instructor_id): array {
        $stmt = $this->pdo->prepare("
            SELECT p.id_pedido, p.total_estimado, p.fecha_entrega, p.fecha_solicitud,
                   c.nombre AS nombre_creador
            FROM pedido_cliente p
            LEFT JOIN cliente c ON p.id_creador = c.id_cliente
            WHERE p.id_cliente = ?
              AND p.id_creador IS NOT NULL AND p.id_creador != ?
              AND p.estado = 'pendiente'
              AND p.aprobado_instructor = 0
            ORDER BY p.fecha_solicitud DESC
        ");
        $stmt->execute([$instructor_id, $instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Aprueba un pedido de aprendiz (por el instructor).
     */
    public function aprobarPedidoInstructor(int $id_pedido, int $instructor_id, string $datetime_entrega): bool {
        return $this->aprobarPedidosInstructorLote([$id_pedido], $instructor_id, $datetime_entrega) > 0;
    }

    /**
     * Aprueba pedidos de aprendices en lote (por el instructor) y les asigna una fecha y hora.
     */
    public function aprobarPedidosInstructorLote(array $ids, int $instructor_id, string $datetime_entrega): int {
        if (empty($ids)) return 0;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $query_check = "SELECT id_pedido FROM pedido_cliente WHERE id_pedido IN ($placeholders) AND id_cliente = ? AND aprobado_instructor = 0";
        $stmt_check = $this->pdo->prepare($query_check);
        $params_check = array_merge($ids, [$instructor_id]);
        $stmt_check->execute($params_check);
        $ids_validos = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids_validos)) {
            throw new Exception("Ninguno de los pedidos seleccionados pertenece a tu grupo o ya fueron procesados.");
        }
        
        $placeholders_upd = implode(',', array_fill(0, count($ids_validos), '?'));
        $query_upd = "UPDATE pedido_cliente SET aprobado_instructor = 1, fecha_entrega = ? WHERE id_pedido IN ($placeholders_upd) AND id_cliente = ?";
        $stmt_upd = $this->pdo->prepare($query_upd);
        $params_upd = array_merge([$datetime_entrega], $ids_validos, [$instructor_id]);
        $stmt_upd->execute($params_upd);
        
        return count($ids_validos);
    }

    /**
     * Rechaza un pedido de aprendiz (por el instructor).
     */
    public function rechazarPedidoInstructor(int $id_pedido, int $instructor_id): bool {
        return $this->rechazarPedidosInstructorLote([$id_pedido], $instructor_id) > 0;
    }

    /**
     * Rechaza pedidos de aprendices en lote (por el instructor).
     */
    public function rechazarPedidosInstructorLote(array $ids, int $instructor_id): int {
        if (empty($ids)) return 0;
        
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        
        $query_check = "SELECT id_pedido FROM pedido_cliente WHERE id_pedido IN ($placeholders) AND id_cliente = ? AND aprobado_instructor = 0";
        $stmt_check = $this->pdo->prepare($query_check);
        $params_check = array_merge($ids, [$instructor_id]);
        $stmt_check->execute($params_check);
        $ids_validos = $stmt_check->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($ids_validos)) {
            throw new Exception("Ninguno de los pedidos seleccionados pertenece a tu grupo o ya fueron procesados.");
        }
        
        $placeholders_upd = implode(',', array_fill(0, count($ids_validos), '?'));
        $query_upd = "UPDATE pedido_cliente SET estado = 'rechazado', aprobado_instructor = 0, mensaje_propietario = 'Rechazado por el instructor' WHERE id_pedido IN ($placeholders_upd) AND id_cliente = ?";
        $stmt_upd = $this->pdo->prepare($query_upd);
        $params_upd = array_merge($ids_validos, [$instructor_id]);
        $stmt_upd->execute($params_upd);
        
        return count($ids_validos);
    }
}

