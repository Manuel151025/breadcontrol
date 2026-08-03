<?php
// models/portal/InstructorPortalTrait.php

/**
 * Flujo aprendiz–instructor: estadísticas y cartera, códigos de
 * invitación, gestión del grupo, cupos y aprobación/rechazo de pedidos.
 * Parte de PortalClienteModel (dividido por responsabilidad).
 */
trait InstructorPortalTrait {

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
     * Obtiene el listado de instructores (clientes tipo tienda activos).
     */
    public function getInstructoresActivos(): array {
        return $this->pdo->query("SELECT id_cliente, nombre FROM cliente WHERE tipo = 'tienda' AND activo = 1 AND es_aprendiz = 0 ORDER BY nombre")->fetchAll(PDO::FETCH_ASSOC);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Cuenta del instructor ADSO (enrutamiento por id, nunca por nombre)
    // ══════════════════════════════════════════════════════════════════════

    /**
     * Resuelve la cuenta del instructor ADSO leyendo configuracion.id_cliente_adso,
     * validando que exista y esté activa. Devuelve ['id'=>int, 'error'=>string].
     * NUNCA busca por nombre. Si la clave falta o apunta a una cuenta inexistente o
     * inactiva, devuelve id=0 con un mensaje claro para mostrar al usuario (falla
     * de forma visible, jamás en silencio ni con un fallback por nombre).
     */
    public function getClienteAdso(): array {
        static $cache = null;
        if ($cache !== null) return $cache;

        $val = $this->pdo->query("SELECT id_cliente_adso FROM configuracion LIMIT 1")->fetchColumn();
        if ($val === false || $val === null || (int)$val <= 0) {
            return $cache = ['id' => 0, 'error' => 'La cuenta del instructor ADSO no está configurada (configuracion.id_cliente_adso). Contacta al administrador.'];
        }
        $id = (int)$val;
        $stmt = $this->pdo->prepare("SELECT id_cliente, activo FROM cliente WHERE id_cliente = ?");
        $stmt->execute([$id]);
        $c = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$c) {
            return $cache = ['id' => 0, 'error' => "La cuenta del instructor ADSO configurada (id $id) no existe. Contacta al administrador."];
        }
        if ((int)$c['activo'] !== 1) {
            return $cache = ['id' => 0, 'error' => "La cuenta del instructor ADSO configurada (id $id) está inactiva. Contacta al administrador."];
        }
        return $cache = ['id' => $id, 'error' => ''];
    }

    /**
     * Id de la cuenta ADSO válida, o 0 si no está bien configurada.
     */
    public function getIdClienteAdso(): int {
        return $this->getClienteAdso()['id'];
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Registro de aprendices por código del instructor
    // ══════════════════════════════════════════════════════════════════════

    /**
     * ¿Este cliente puede actuar como instructor? EXCLUSIVAMENTE la cuenta indicada por
     * configuracion.id_cliente_adso, comparando por id. NO se usa 'tipo' como criterio:
     * en producción las 46 cuentas son tipo='tienda' (incluidas personas), así que 'tipo'
     * no discrimina y habilitaría a todas. Si más adelante hay varios instructores, se
     * amplía aquí; por ahora es una sola cuenta.
     */
    public function esInstructorCapaz(?array $cliente): bool {
        if ($cliente === null) return false;
        $idAdso = $this->getIdClienteAdso();
        return $idAdso > 0 && (int)($cliente['id_cliente'] ?? 0) === $idAdso;
    }

    /**
     * Código activo del instructor (el más reciente sin desactivar), o null.
     */
    public function getCodigoActivoInstructor(int $instructor_id): ?array {
        $stmt = $this->pdo->prepare("
            SELECT * FROM codigo_aprendiz
            WHERE id_instructor = ? AND activo = 1
            ORDER BY fecha_creacion DESC, id_codigo DESC
            LIMIT 1
        ");
        $stmt->execute([$instructor_id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * ¿Existe al menos un código de aprendiz activo y NO vencido (de cualquier instructor)?
     * Se usa para mostrar el aviso de canje en el tablero solo durante la temporada de
     * inscripción; si no hay ninguno, no se molesta a los clientes normales.
     */
    public function hayCodigoAprendizActivo(): bool {
        $stmt = $this->pdo->query("
            SELECT 1 FROM codigo_aprendiz
            WHERE activo = 1 AND (fecha_expira IS NULL OR fecha_expira > NOW())
            LIMIT 1
        ");
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Desactiva todos los códigos activos de un instructor.
     */
    public function desactivarCodigosInstructor(int $instructor_id): int {
        $stmt = $this->pdo->prepare("UPDATE codigo_aprendiz SET activo = 0 WHERE id_instructor = ? AND activo = 1");
        $stmt->execute([$instructor_id]);
        return $stmt->rowCount();
    }

    /**
     * Genera un código nuevo (desactivando el anterior). Alfabeto sin caracteres
     * ambiguos (nada de O/0, ni I/1/L); longitud 8. NO usa el número de ficha.
     */
    public function generarCodigoAprendiz(int $instructor_id, int $dias_vigencia, ?int $usos_maximos): string {
        $alfabeto = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789'; // sin O,0,I,1,L
        $len = 8;
        $fecha_expira = $dias_vigencia > 0
            ? date('Y-m-d H:i:s', strtotime("+{$dias_vigencia} days"))
            : null;

        $this->pdo->beginTransaction();
        try {
            $this->desactivarCodigosInstructor($instructor_id);

            $codigo = '';
            for ($intento = 0; $intento < 15; $intento++) {
                $c = '';
                for ($i = 0; $i < $len; $i++) {
                    $c .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
                }
                $chk = $this->pdo->prepare("SELECT 1 FROM codigo_aprendiz WHERE codigo = ?");
                $chk->execute([$c]);
                if (!$chk->fetchColumn()) { $codigo = $c; break; }
            }
            if ($codigo === '') {
                throw new Exception("No se pudo generar un código único. Intenta de nuevo.");
            }

            $ins = $this->pdo->prepare("
                INSERT INTO codigo_aprendiz (id_instructor, codigo, fecha_expira, usos_maximos, usos_actuales, activo)
                VALUES (?, ?, ?, ?, 0, 1)
            ");
            $ins->execute([$instructor_id, $codigo, $fecha_expira, $usos_maximos]);

            $this->pdo->commit();
            return $codigo;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Aprendices vinculados a un instructor (para la pantalla "Mis aprendices").
     */
    public function getAprendicesGestion(int $instructor_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id_cliente, nombre, telefono, email, fecha_aprendiz, cupo_semanal, fecha_creacion
            FROM cliente
            WHERE es_aprendiz = 1 AND id_instructor = ? AND activo = 1
            ORDER BY nombre
        ");
        $stmt->execute([$instructor_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Quita a un aprendiz del grupo del instructor. Scoped: solo aprendices propios.
     * Los pedidos históricos NO se tocan (se conservan con su id_creador).
     */
    public function quitarAprendiz(int $instructor_id, int $aprendiz_id): bool {
        $stmt = $this->pdo->prepare("
            UPDATE cliente
            SET es_aprendiz = 0, id_instructor = NULL, fecha_aprendiz = NULL
            WHERE id_cliente = ? AND id_instructor = ? AND es_aprendiz = 1
        ");
        $stmt->execute([$aprendiz_id, $instructor_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Actualiza el cupo semanal de un aprendiz propio del instructor (scoped).
     */
    public function actualizarCupoAprendizInstructor(int $instructor_id, int $aprendiz_id, float $cupo): bool {
        $stmt = $this->pdo->prepare("
            UPDATE cliente SET cupo_semanal = ?
            WHERE id_cliente = ? AND id_instructor = ? AND es_aprendiz = 1
        ");
        $stmt->execute([$cupo, $aprendiz_id, $instructor_id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Canjea un código de aprendiz. Devuelve ['ok'=>bool, 'error'=>string, 'instructor'=>string].
     * Valida todo transaccionalmente con FOR UPDATE sobre la fila del código para que dos
     * canjes simultáneos no excedan el límite de usos. cupo_semanal por defecto = 20000.
     */
    public function canjearCodigoAprendiz(int $cliente_id, string $codigo): array {
        $codigo = strtoupper(trim($codigo));
        if ($codigo === '') {
            return ['ok' => false, 'error' => 'Ingresa un código de aprendiz.'];
        }

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM codigo_aprendiz WHERE codigo = ? FOR UPDATE");
            $stmt->execute([$codigo]);
            $cod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$cod) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'El código no existe. Verifícalo con tu instructor.'];
            }
            if ((int)$cod['activo'] !== 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Este código fue desactivado. Pídele uno nuevo a tu instructor.'];
            }
            if ($cod['fecha_expira'] !== null && strtotime($cod['fecha_expira']) < time()) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Este código ya venció. Pídele uno nuevo a tu instructor.'];
            }
            if ($cod['usos_maximos'] !== null && (int)$cod['usos_actuales'] >= (int)$cod['usos_maximos']) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Este código ya alcanzó su número máximo de usos.'];
            }

            $instructor_id = (int)$cod['id_instructor'];
            $id_adso = $this->getIdClienteAdso();

            // NO se filtra por 'tipo' (todas las cuentas son tipo='tienda' en producción,
            // así que ese criterio bloquearía a todos). Lo único que no puede volverse
            // aprendiz es la propia cuenta de instructor, identificada por id.
            $stmtc = $this->pdo->prepare("SELECT es_aprendiz FROM cliente WHERE id_cliente = ?");
            $stmtc->execute([$cliente_id]);
            $cli = $stmtc->fetch(PDO::FETCH_ASSOC);

            if (!$cli) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Tu cuenta no es válida.'];
            }
            if ($cliente_id === $instructor_id || ($id_adso > 0 && $cliente_id === $id_adso)) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'La cuenta del instructor no puede registrarse como aprendiz.'];
            }
            if ((int)$cli['es_aprendiz'] === 1) {
                $this->pdo->rollBack();
                return ['ok' => false, 'error' => 'Ya estás registrado como aprendiz de un instructor.'];
            }

            $upd = $this->pdo->prepare("
                UPDATE cliente
                SET es_aprendiz = 1, id_instructor = ?, cupo_semanal = 20000.00, fecha_aprendiz = NOW()
                WHERE id_cliente = ?
            ");
            $upd->execute([$instructor_id, $cliente_id]);

            $this->pdo->prepare("UPDATE codigo_aprendiz SET usos_actuales = usos_actuales + 1 WHERE id_codigo = ?")
                 ->execute([(int)$cod['id_codigo']]);

            $stmti = $this->pdo->prepare("SELECT nombre FROM cliente WHERE id_cliente = ?");
            $stmti->execute([$instructor_id]);
            $nombre_inst = (string)$stmti->fetchColumn();

            $this->pdo->commit();
            return ['ok' => true, 'error' => '', 'instructor' => $nombre_inst];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
