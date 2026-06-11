<?php
// models/InventarioModel.php

class InventarioModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene un insumo por ID
     */
    public function getInsumoById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM insumo WHERE id_insumo = ?");
        $stmt->execute([$id]);
        $insumo = $stmt->fetch();
        return $insumo ?: null;
    }

    /**
     * Obtiene un insumo activo por ID
     */
    public function getInsumoActivoById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM insumo WHERE id_insumo = ? AND activo = 1");
        $stmt->execute([$id]);
        $insumo = $stmt->fetch();
        return $insumo ?: null;
    }

    /**
     * Verifica si ya existe un insumo con un nombre dado, excluyendo un ID específico si se edita
     */
    public function getInsumoPorNombre(string $nombre, ?int $exceptId = null): ?array {
        if ($exceptId) {
            $stmt = $this->pdo->prepare("SELECT * FROM insumo WHERE nombre = ? AND id_insumo != ?");
            $stmt->execute([$nombre, $exceptId]);
        } else {
            $stmt = $this->pdo->prepare("SELECT * FROM insumo WHERE nombre = ?");
            $stmt->execute([$nombre]);
        }
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Registra un nuevo insumo. Si ya existía uno con el mismo nombre pero inactivo,
     * lo reactiva y actualiza sus campos.
     */
    public function registrarInsumo(string $nombre, string $unidad, float $stock, float $reposicion, int $es_harina): bool {
        $existe = $this->getInsumoPorNombre($nombre);
        if ($existe) {
            // Reactivar y actualizar
            $stmt = $this->pdo->prepare("
                UPDATE insumo 
                SET unidad_medida = ?, stock_actual = ?, punto_reposicion = ?, es_harina = ?, activo = 1 
                WHERE id_insumo = ?
            ");
            return $stmt->execute([$unidad, $stock, $reposicion, $es_harina, $existe['id_insumo']]);
        } else {
            // Insertar nuevo
            $stmt = $this->pdo->prepare("
                INSERT INTO insumo (nombre, unidad_medida, stock_actual, punto_reposicion, es_harina, activo) 
                VALUES (?, ?, ?, ?, ?, 1)
            ");
            return $stmt->execute([$nombre, $unidad, $stock, $reposicion, $es_harina]);
        }
    }

    /**
     * Actualiza las propiedades de un insumo existente (incluyendo el estado activo opcional)
     */
    public function actualizarInsumo(int $id, string $nombre, string $unidad, float $stock, float $reposicion, int $es_harina, int $activo = 1): bool {
        $stmt = $this->pdo->prepare("
            UPDATE insumo 
            SET nombre = ?, unidad_medida = ?, stock_actual = ?, punto_reposicion = ?, es_harina = ?, activo = ? 
            WHERE id_insumo = ?
        ");
        return $stmt->execute([$nombre, $unidad, $stock, $reposicion, $es_harina, $activo, $id]);
    }

    /**
     * Desactivación lógica (soft-delete) de un insumo por ID
     */
    public function desactivarInsumo(int $id): bool {
        $stmt = $this->pdo->prepare("UPDATE insumo SET activo = 0 WHERE id_insumo = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Desactivación lógica de múltiples insumos en lote
     */
    public function desactivarMultiplesInsumos(array $ids): bool {
        if (empty($ids)) {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("UPDATE insumo SET activo = 0 WHERE id_insumo IN ($placeholders)");
        return $stmt->execute(array_map('intval', $ids));
    }

    /**
     * Obtiene la lista de insumos activos aplicando filtros de búsqueda y nivel de alerta
     */
    public function getInsumosList(string $busca = '', bool $soloAlertas = false): array {
        $where  = "WHERE i.activo = 1";
        $params = [];

        if ($busca !== '') {
            $where .= " AND i.nombre LIKE ?";
            $params[] = "%$busca%";
        }
        if ($soloAlertas) {
            $where .= " AND i.stock_actual <= i.punto_reposicion";
        }

        $stmt = $this->pdo->prepare("
            SELECT i.*,
                   COUNT(DISTINCT CASE WHEN l.estado = 'activo' AND l.cantidad_disponible > 0 THEN l.id_lote END) AS num_lotes,
                   COALESCE(
                     (SELECT l2.precio_unitario FROM lote l2
                      WHERE l2.id_insumo = i.id_insumo
                      ORDER BY l2.fecha_ingreso DESC LIMIT 1), 0
                   ) AS precio_ultimo
            FROM insumo i
            LEFT JOIN lote l ON l.id_insumo = i.id_insumo
            $where
            GROUP BY i.id_insumo
            ORDER BY i.nombre
        ");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los KPIs generales del inventario
     */
    public function getKPIs(): array {
        $total_insumos = (int)$this->pdo->query("SELECT COUNT(*) FROM insumo WHERE activo = 1")->fetchColumn();
        $alertas_count = (int)$this->pdo->query("SELECT COUNT(*) FROM insumo WHERE activo = 1 AND stock_actual <= punto_reposicion")->fetchColumn();
        $lotes_activos = (int)$this->pdo->query("SELECT COUNT(*) FROM lote WHERE estado = 'activo' AND cantidad_disponible > 0")->fetchColumn();
        $valor_inventario = (float)$this->pdo->query("
            SELECT COALESCE(SUM(l.cantidad_disponible * l.precio_unitario), 0) 
            FROM lote l 
            WHERE l.estado = 'activo' AND l.cantidad_disponible > 0
        ")->fetchColumn();

        return [
            'total_insumos'    => $total_insumos,
            'alertas_count'    => $alertas_count,
            'lotes_activos'    => $lotes_activos,
            'valor_inventario' => $valor_inventario
        ];
    }

    /**
     * Registra un ajuste de inventario y sincroniza los lotes activos usando FIFO
     */
    public function registrarAjusteInventario(int $id_insumo, int $id_usuario, float $cantidad_real, string $motivo): array {
        // Obtener stock actual antes del ajuste
        $insumo = $this->getInsumoActivoById($id_insumo);
        if (!$insumo) {
            throw new Exception("Insumo activo no encontrado.");
        }

        $stock_antes = (float)$insumo['stock_actual'];
        $diferencia  = $cantidad_real - $stock_antes;

        $this->pdo->beginTransaction();

        try {
            // 1. Registrar el registro de ajuste
            $stmt_aju = $this->pdo->prepare("
                INSERT INTO ajuste_inventario (id_insumo, id_usuario, cantidad_antes, cantidad_despues, diferencia, motivo)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt_aju->execute([$id_insumo, $id_usuario, $stock_antes, $cantidad_real, $diferencia, $motivo]);

            // 2. Actualizar stock del insumo
            $stmt_ins = $this->pdo->prepare("UPDATE insumo SET stock_actual = ? WHERE id_insumo = ?");
            $stmt_ins->execute([$cantidad_real, $id_insumo]);

            // 3. Sincronizar lotes
            $stmt_lotes = $this->pdo->prepare("
                SELECT id_lote, cantidad_disponible FROM lote
                WHERE id_insumo = ? AND estado = 'activo'
                ORDER BY fecha_ingreso ASC
            ");
            $stmt_lotes->execute([$id_insumo]);
            $lotes_activos = $stmt_lotes->fetchAll();

            if ($diferencia > 0) {
                // Stock aumentó: sumar la diferencia al último lote activo
                if (!empty($lotes_activos)) {
                    $ultimo_lote = end($lotes_activos);
                    $nueva_disp  = round((float)$ultimo_lote['cantidad_disponible'] + $diferencia, 4);
                    $stmt_up_lote = $this->pdo->prepare("UPDATE lote SET cantidad_disponible = ?, estado = 'activo' WHERE id_lote = ?");
                    $stmt_up_lote->execute([$nueva_disp, $ultimo_lote['id_lote']]);
                } else {
                    // Sin lotes activos: crear uno de ajuste
                    $prefijo  = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $insumo['nombre']), 0, 3));
                    $num_lote = 'AJU-' . $prefijo . '-' . date('Y-m-d') . '-001';
                    $stmt_ins_lote = $this->pdo->prepare("
                        INSERT INTO lote (id_insumo, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, estado)
                        VALUES (?, ?, ?, ?, 0, NOW(), 'activo')
                    ");
                    $stmt_ins_lote->execute([$id_insumo, $num_lote, $cantidad_real, $cantidad_real]);
                }
            } elseif ($diferencia < 0) {
                // Stock disminuyó: descontar en orden FIFO
                $a_descontar = abs($diferencia);
                foreach ($lotes_activos as $lote) {
                    if ($a_descontar <= 0) {
                        break;
                    }
                    $consumir     = min((float)$lote['cantidad_disponible'], $a_descontar);
                    $nueva_disp   = round((float)$lote['cantidad_disponible'] - $consumir, 4);
                    $nuevo_estado = $nueva_disp <= 0 ? 'agotado' : 'activo';

                    $stmt_up_lote = $this->pdo->prepare("UPDATE lote SET cantidad_disponible = ?, estado = ? WHERE id_lote = ?");
                    $stmt_up_lote->execute([$nueva_disp, $nuevo_estado, $lote['id_lote']]);
                    
                    $a_descontar -= $consumir;
                }
            }

            // 4. Generar alerta si queda bajo el punto de reposición
            if ($cantidad_real <= $insumo['punto_reposicion']) {
                $msg_alerta = "Ajuste dejó stock bajo en: " . $insumo['nombre'] . " — quedan " . $cantidad_real . " " . $insumo['unidad_medida'];
                $stmt_alert = $this->pdo->prepare("
                    INSERT INTO alerta (id_usuario, tipo, modulo_origen, mensaje)
                    VALUES (?, 'stock_bajo', 'inventario', ?)
                ");
                $stmt_alert->execute([$id_usuario, $msg_alerta]);
            }

            $this->pdo->commit();
            return [
                'success' => true,
                'diferencia' => $diferencia,
                'unidad' => $insumo['unidad_medida']
            ];
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene el historial de ajustes de un insumo
     */
    public function getHistorialAjustes(int $id_insumo, int $limite = 10): array {
        $stmt = $this->pdo->prepare("
            SELECT aj.*, u.nombre_completo
            FROM ajuste_inventario aj
            INNER JOIN usuario u ON u.id_usuario = aj.id_usuario
            WHERE aj.id_insumo = ?
            ORDER BY aj.fecha_ajuste DESC
            LIMIT ?
        ");
        // Bind integer parameter to prevent issues with LIMIT
        $stmt->bindValue(1, $id_insumo, PDO::PARAM_INT);
        $stmt->bindValue(2, $limite, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
