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
     * @return array<string, mixed>
     */
    public function getInstructorStats(int $cliente_id): array {
        // Resumen financiero global.
        //
        // Cancelar un pedido lo pone en 'rechazado' pero le deja
        // aprobado_instructor = 1 (PedidosPortalTrait::cancelarPedido), así que
        // los contadores tienen que excluir ese estado explícitamente. Cuando no
        // lo hacían, un pedido cancelado seguía sumando en «pedidos totales» y
        // su autor seguía figurando como aprendiz activo, aunque su dinero ya no
        // apareciera por ningún lado: el contador y el importe describían
        // conjuntos distintos de pedidos.
        $sf = $this->pdo->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN estado != 'rechazado' AND aprobado_instructor = 1 AND MONTH(fecha_solicitud) = MONTH(NOW()) AND YEAR(fecha_solicitud) = YEAR(NOW()) THEN total_estimado ELSE 0 END), 0) AS total_mes,
                COUNT(DISTINCT CASE WHEN aprobado_instructor = 1 AND estado != 'rechazado' THEN id_creador ELSE NULL END) AS aprendices_activos,
                COUNT(CASE WHEN aprobado_instructor = 1 AND estado != 'rechazado' THEN 1 ELSE NULL END) AS total_pedidos
            FROM pedido_cliente
            WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ?
        ");
        $sf->execute([$cliente_id, $cliente_id]);
        $resumen = $sf->fetch(PDO::FETCH_ASSOC) ?: [];

        $saldo = $this->calcularSaldoPendiente($cliente_id);
        $resumen['pendiente_total'] = $saldo['total'];
        return $resumen;
    }

    /**
     * Saldo pendiente del instructor, en total y desglosado por aprendiz.
     *
     * Es la única definición de «cuánto se debe» en el portal: la usan el KPI
     * del tablero, la columna de la tabla y el PDF de cartera. Antes cada uno
     * la calculaba por su cuenta con reglas distintas, así que los números de
     * la misma pantalla no cuadraban entre sí:
     *
     *   - El KPI contaba los pedidos con estado_pago nulo o 'parcial'; la
     *     columna de la tabla no, así que la tabla escondía deuda real.
     *   - El KPI restaba los abonos; la columna no, así que la inflaba.
     *
     * Gana la regla del KPI, que es la correcta: un pedido sin estado de pago
     * está sin pagar, y de uno con abono parcial solo se debe el resto.
     *
     * @return array{total: float, por_aprendiz: array<int, float>}
     */
    private function calcularSaldoPendiente(int $cliente_id): array {
        $stmt = $this->pdo->prepare("
            SELECT id_pedido, id_creador, total_estimado, id_pago_activo
            FROM pedido_cliente
            WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ?
              AND estado != 'rechazado' AND aprobado_instructor = 1
              AND (estado_pago IS NULL OR estado_pago IN ('pendiente', 'no_aplica', 'parcial'))
        ");
        $stmt->execute([$cliente_id, $cliente_id]);

        // PDO devuelve las filas sin tipo, así que se convierten aquí una sola
        // vez y el resto del cálculo trabaja con números de verdad.
        $pedidos = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $pedidos[] = [
                'id_creador'     => self::aEntero($fila['id_creador'] ?? null),
                'total_estimado' => self::aDecimal($fila['total_estimado'] ?? null),
                'id_pago_activo' => self::aEntero($fila['id_pago_activo'] ?? null),
            ];
        }

        // Un pago consolidado cubre varios pedidos a la vez, y el abono se
        // registra contra el pago, no contra cada pedido: hay que agrupar
        // primero para saber cuánto queda debiendo el grupo.
        $por_pago = [];
        foreach ($pedidos as $p) {
            $por_pago[$p['id_pago_activo']][] = $p;
        }

        $por_aprendiz = [];
        $total = 0.0;

        foreach ($por_pago as $pago_id => $grupo) {
            // Sin pago abierto: cada pedido debe su importe completo.
            if ($pago_id === 0) {
                foreach ($grupo as $p) {
                    $creador = $p['id_creador'];
                    $por_aprendiz[$creador] = ($por_aprendiz[$creador] ?? 0.0) + $p['total_estimado'];
                    $total += $p['total_estimado'];
                }
                continue;
            }

            $suma_grupo = 0.0;
            foreach ($grupo as $p) {
                $suma_grupo += $p['total_estimado'];
            }

            $stmt_ab = $this->pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
            $stmt_ab->execute([$pago_id]);
            $abonado = (float) $stmt_ab->fetchColumn();

            $deficit = $suma_grupo - $abonado;
            if ($deficit <= 0) {
                continue;
            }
            $total += $deficit;

            // El pago puede cubrir pedidos de varios aprendices, así que lo que
            // queda debiendo se reparte a prorrata de lo que puso cada pedido.
            $montos = [];
            foreach ($grupo as $p) {
                $montos[] = $p['total_estimado'];
            }
            $partes = self::repartirDeficit($montos, $deficit);

            foreach ($grupo as $i => $p) {
                $creador = $p['id_creador'];
                $por_aprendiz[$creador] = ($por_aprendiz[$creador] ?? 0.0) + $partes[$i];
            }
        }

        return ['total' => $total, 'por_aprendiz' => $por_aprendiz];
    }

    /**
     * Convierte a entero un valor recién salido de PDO, que llega sin tipo.
     *
     * Devuelve 0 si no era un número: un identificador ilegible no puede
     * convertirse en un id válido por accidente.
     */
    private static function aEntero(mixed $valor): int {
        return is_numeric($valor) ? (int) $valor : 0;
    }

    /** Igual que aEntero(), para importes. */
    private static function aDecimal(mixed $valor): float {
        return is_numeric($valor) ? (float) $valor : 0.0;
    }

    /**
     * Reparte lo que se sigue debiendo de un pago entre los pedidos que cubre,
     * a prorrata de lo que aportó cada uno.
     *
     * Las partes suman **exactamente** el déficit: el residuo del redondeo se
     * le carga al último pedido. Sin eso, el desglose por aprendiz podría
     * quedar unos pesos por encima o por debajo del total del tablero, que es
     * justo el descuadre que este cálculo existe para evitar.
     *
     * @param array<int, float> $montos Importe de cada pedido del pago.
     * @return array<int, float> Una parte por pedido, en el mismo orden.
     */
    private static function repartirDeficit(array $montos, float $deficit): array {
        $suma = array_sum($montos);
        $ultimo = count($montos) - 1;

        if ($ultimo < 0) {
            return [];
        }
        // Sin importes de referencia no hay prorrata posible: se carga al último.
        if ($suma <= 0) {
            $partes = array_fill(0, $ultimo + 1, 0.0);
            $partes[$ultimo] = $deficit;
            return $partes;
        }

        $partes   = [];
        $restante = $deficit;
        foreach ($montos as $i => $monto) {
            if ($i === $ultimo) {
                $partes[$i] = round($restante, 2);
                break;
            }
            $parte      = round($deficit * ($monto / $suma), 2);
            $partes[$i] = $parte;
            $restante  -= $parte;
        }

        return $partes;
    }

    /**
     * Obtiene el listado de alumnos y sus deudas.
     * @return array<int, array<string, mixed>>
     */
    public function getAprendicesResumen(int $cliente_id): array {
        // Se listan los aprendices del grupo actual y, además, cualquiera que
        // todavía deba dinero aunque ya no esté activo o lo hayan pasado a otro
        // instructor. Si no, al desactivar a alguien su deuda desaparecía de la
        // tabla pero seguía dentro del total de arriba, y el KPI dejaba de estar
        // explicado por las filas visibles. La deuda no se esconde: se marca.
        $sa = $this->pdo->prepare("
            SELECT
                c.id_cliente,
                c.nombre,
                c.telefono,
                c.email,
                c.foto_url,
                c.cupo_semanal,
                (c.activo = 1 AND c.id_instructor = ?) AS en_mi_grupo,
                (
                    SELECT COALESCE(SUM(pc.total_estimado), 0)
                    FROM pedido_cliente pc
                    WHERE pc.id_creador = c.id_cliente
                      AND pc.id_cliente = ?
                      AND pc.estado != 'rechazado'
                      AND pc.fecha_solicitud >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY), '%Y-%m-%d 00:00:00')
                ) AS consumido_semana,
                COUNT(CASE WHEN p.aprobado_instructor = 1 AND p.estado != 'rechazado' THEN p.id_pedido ELSE NULL END) AS total_pedidos,
                COALESCE(SUM(CASE WHEN p.estado != 'rechazado' AND p.aprobado_instructor = 1 THEN p.total_estimado ELSE 0 END), 0) AS total_comprado,
                MAX(CASE WHEN p.aprobado_instructor = 1 AND p.estado != 'rechazado' THEN p.fecha_solicitud ELSE NULL END) AS ultimo_pedido,
                COALESCE(SUM(CASE WHEN p.estado = 'pendiente' AND p.aprobado_instructor = 1 THEN 1 ELSE 0 END), 0) AS sin_confirmar
            FROM cliente c
            LEFT JOIN pedido_cliente p ON p.id_creador = c.id_cliente AND p.id_cliente = ?
            WHERE c.es_aprendiz = 1
              AND (
                    (c.activo = 1 AND c.id_instructor = ?)
                 OR EXISTS (
                        SELECT 1 FROM pedido_cliente pe
                        WHERE pe.id_creador = c.id_cliente
                          AND pe.id_cliente = ?
                          AND pe.aprobado_instructor = 1
                          AND pe.estado != 'rechazado'
                    )
              )
            GROUP BY c.id_cliente, c.nombre, c.telefono, c.email, c.foto_url,
                     c.cupo_semanal, c.activo, c.id_instructor
        ");
        $sa->execute([$cliente_id, $cliente_id, $cliente_id, $cliente_id, $cliente_id]);

        // El saldo no se calcula en esta consulta: sale del mismo sitio que el
        // KPI, para que los dos números no puedan volver a separarse.
        $saldo = $this->calcularSaldoPendiente($cliente_id);

        $aprendices = [];
        foreach ($sa->fetchAll(PDO::FETCH_ASSOC) as $fila) {
            if (!is_array($fila)) {
                continue;
            }
            $id = self::aEntero($fila['id_cliente'] ?? null);
            $aprendices[] = [
                'id_cliente'       => $id,
                'nombre'           => $fila['nombre']    ?? '',
                'telefono'         => $fila['telefono']  ?? '',
                'email'            => $fila['email']     ?? '',
                'foto_url'         => $fila['foto_url']  ?? '',
                'ultimo_pedido'    => $fila['ultimo_pedido'] ?? null,
                'en_mi_grupo'      => !empty($fila['en_mi_grupo']),
                'cupo_semanal'     => self::aDecimal($fila['cupo_semanal'] ?? null),
                'consumido_semana' => self::aDecimal($fila['consumido_semana'] ?? null),
                'total_pedidos'    => self::aEntero($fila['total_pedidos'] ?? null),
                'total_comprado'   => self::aDecimal($fila['total_comprado'] ?? null),
                'sin_confirmar'    => self::aEntero($fila['sin_confirmar'] ?? null),
                'saldo_pendiente'  => $saldo['por_aprendiz'][$id] ?? 0.0,
            ];
        }

        // El orden lo hacía el SQL por saldo_pendiente, que ya no es una columna.
        usort($aprendices, static function (array $x, array $y): int {
            return [self::aDecimal($y['saldo_pendiente'] ?? null), self::aDecimal($y['total_comprado'] ?? null)]
               <=> [self::aDecimal($x['saldo_pendiente'] ?? null), self::aDecimal($x['total_comprado'] ?? null)];
        });

        return $aprendices;
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
     * @return array<int, array<string, mixed>>
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
     * @return array<int, array<string, mixed>>
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
     * @return array<mixed>
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
     * @param array<mixed> $cliente
     */
    public function esInstructorCapaz(?array $cliente): bool {
        if ($cliente === null) return false;
        $idAdso = $this->getIdClienteAdso();
        return $idAdso > 0 && (int)($cliente['id_cliente'] ?? 0) === $idAdso;
    }

    /**
     * Código activo del instructor (el más reciente sin desactivar), o null.
     * @return array<mixed>
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
            ? date('Y-m-d H:i:s', (int) strtotime("+{$dias_vigencia} days"))
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
     * @return array<int, array<string, mixed>>
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
     * @return array<mixed>
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
     * @return array<int, array<string, mixed>>
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
     * @param array<mixed> $ids
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
     * @param array<mixed> $ids
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
