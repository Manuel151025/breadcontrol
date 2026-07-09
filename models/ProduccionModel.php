<?php
// models/ProduccionModel.php

class ProduccionModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    // ══════════════════════════════════════════════════════════════
    //  INDEX — Historial de producción
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene las producciones de una fecha específica con producto y operario
     */
    public function getProduccionesPorFecha(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, p.nombre AS producto, p.unidad_produccion,
                   u.nombre_completo AS operario
            FROM produccion pr
            INNER JOIN producto p ON p.id_producto=pr.id_producto
            LEFT  JOIN usuario  u ON u.id_usuario=pr.id_usuario
            WHERE DATE(pr.fecha_produccion)=?
            ORDER BY pr.fecha_produccion DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los KPIs del panel principal: tandas hoy, ayer, registros mes, productos activos y tandas mes
     */
    public function getKPIs(): array {
        $prod_hoy = (float)$this->pdo->query(
            "SELECT COALESCE(SUM(cantidad_tandas),0) FROM produccion WHERE DATE(fecha_produccion)=CURDATE()"
        )->fetchColumn();

        $prod_ayer = (float)$this->pdo->query(
            "SELECT COALESCE(SUM(cantidad_tandas),0) FROM produccion WHERE DATE(fecha_produccion)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)"
        )->fetchColumn();

        $prod_mes = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM produccion WHERE MONTH(fecha_produccion)=MONTH(CURDATE()) AND YEAR(fecha_produccion)=YEAR(CURDATE())"
        )->fetchColumn();

        $productos_activos = (int)$this->pdo->query(
            "SELECT COUNT(*) FROM producto WHERE activo=1"
        )->fetchColumn();

        $total_tandas_mes = (float)$this->pdo->query(
            "SELECT COALESCE(SUM(cantidad_tandas),0) FROM produccion WHERE MONTH(fecha_produccion)=MONTH(CURDATE()) AND YEAR(fecha_produccion)=YEAR(CURDATE())"
        )->fetchColumn();

        return [
            'prod_hoy'         => $prod_hoy,
            'prod_ayer'        => $prod_ayer,
            'prod_mes'         => $prod_mes,
            'productos_activos'=> $productos_activos,
            'total_tandas_mes' => $total_tandas_mes,
        ];
    }

    /**
     * Obtiene el top 5 de productos más producidos en el mes actual
     */
    public function getTopProductosMes(): array {
        return $this->pdo->query("
            SELECT p.nombre, SUM(pr.cantidad_tandas) AS tandas
            FROM produccion pr INNER JOIN producto p ON p.id_producto=pr.id_producto
            WHERE MONTH(pr.fecha_produccion)=MONTH(CURDATE()) AND YEAR(pr.fecha_produccion)=YEAR(CURDATE())
            GROUP BY pr.id_producto ORDER BY tandas DESC LIMIT 5
        ")->fetchAll();
    }

    // ══════════════════════════════════════════════════════════════
    //  NUEVA PRODUCCIÓN — Recetas, lotes FIFO, registro
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene el ID de la receta vigente para un producto dado
     */
    public function getRecetaVigente(int $id_producto): ?int {
        $r = $this->pdo->prepare("SELECT id_receta FROM receta WHERE id_producto=? AND es_vigente=1 LIMIT 1");
        $r->execute([$id_producto]);
        $id = $r->fetchColumn();
        return $id !== false ? (int)$id : null;
    }

    /**
     * Obtiene los ingredientes de una receta con datos del insumo
     */
    public function getIngredientesReceta(int $id_receta): array {
        $stmt = $this->pdo->prepare("
            SELECT ri.id_insumo, ri.cantidad AS cant_por_unidad, ri.aplica_merma,
                   i.nombre, i.unidad_medida, i.stock_actual
            FROM receta_ingrediente ri
            INNER JOIN insumo i ON i.id_insumo = ri.id_insumo
            WHERE ri.id_receta = ?
            ORDER BY i.nombre
        ");
        $stmt->execute([$id_receta]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene los lotes activos con disponibilidad > 0 para un insumo, ordenados FIFO
     */
    public function getLotesDisponiblesInsumo(int $id_insumo): array {
        $stmt = $this->pdo->prepare("
            SELECT id_lote, numero_lote, fecha_ingreso, cantidad_disponible, precio_unitario
            FROM lote
            WHERE id_insumo = ? AND estado = 'activo' AND cantidad_disponible > 0
            ORDER BY fecha_ingreso ASC
        ");
        $stmt->execute([$id_insumo]);
        return $stmt->fetchAll();
    }

    /**
     * Retorna el stock físico actual de un insumo
     */
    public function getInsumoStockActual(int $id_insumo): float {
        $stmt = $this->pdo->prepare("SELECT stock_actual FROM insumo WHERE id_insumo=?");
        $stmt->execute([$id_insumo]);
        return (float)$stmt->fetchColumn();
    }

    /**
     * Calcula la distribución FIFO de lotes para un ingrediente y genera la respuesta AJAX
     *
     * @param array $ing       Fila de ingrediente con cant_por_unidad, aplica_merma, id_insumo, nombre, unidad_medida, stock_actual
     * @param int   $tandas    Número de tandas a producir
     * @return array           Estructura con info del ingrediente y lotes a usar
     */
    public function calcularLotesFIFO(array $ing, int $tandas): array {
        $cant_necesaria = $ing['cant_por_unidad'] * $tandas;

        $lotes = $this->getLotesDisponiblesInsumo($ing['id_insumo']);
        $total_lotes   = array_sum(array_column($lotes, 'cantidad_disponible'));
        $stock_actual  = (float)$ing['stock_actual'];

        // stock_actual ES la verdad de lo que hay físicamente en bodega
        $total_disponible = $stock_actual;
        $alcanza = $total_disponible >= $cant_necesaria;

        // Detectar stock sin lote (editado manualmente desde Inventario)
        $stock_sin_lote = max(0, $stock_actual - $total_lotes);
        $hay_stock_manual = $stock_sin_lote > 0;

        $lotes_a_usar = [];

        // Primero consumir de lotes FIFO
        $restante = min($cant_necesaria, $stock_actual);
        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $consumir = min((float)$lote['cantidad_disponible'], $restante);
            $lotes_a_usar[] = [
                'id_lote'         => $lote['id_lote'],
                'numero_lote'     => $lote['numero_lote'],
                'fecha_ingreso'   => date('d/m/Y', strtotime($lote['fecha_ingreso'])),
                'disponible'      => (float)$lote['cantidad_disponible'],
                'a_consumir'      => round($consumir, 4),
                'precio_unitario' => (float)$lote['precio_unitario'],
                'es_mas_antiguo'  => count($lotes_a_usar) === 0,
                'sin_lote'        => false,
            ];
            $restante -= $consumir;
        }

        // Si queda restante, es stock manual (sin lote)
        if ($restante > 0 && $hay_stock_manual) {
            $lotes_a_usar[] = [
                'id_lote'         => null,
                'numero_lote'     => 'MANUAL',
                'fecha_ingreso'   => 'Editado en Inventario',
                'disponible'      => $stock_sin_lote,
                'a_consumir'      => round($restante, 4),
                'precio_unitario' => 0,
                'es_mas_antiguo'  => count($lotes_a_usar) === 0,
                'sin_lote'        => true,
            ];
        } elseif (empty($lotes) && $stock_actual > 0) {
            // Sin lotes en absoluto — todo el stock es manual
            $lotes_a_usar[] = [
                'id_lote'         => null,
                'numero_lote'     => 'MANUAL',
                'fecha_ingreso'   => 'Editado en Inventario',
                'disponible'      => $stock_actual,
                'a_consumir'      => round(min($cant_necesaria, $stock_actual), 4),
                'precio_unitario' => 0,
                'es_mas_antiguo'  => true,
                'sin_lote'        => true,
            ];
        }

        return [
            'id_insumo'        => $ing['id_insumo'],
            'nombre'           => $ing['nombre'],
            'unidad_medida'    => $ing['unidad_medida'],
            'cant_necesaria'   => round($cant_necesaria, 4),
            'total_disponible' => round($total_disponible, 4),
            'alcanza'          => $alcanza,
            'aplica_merma'     => (bool)$ing['aplica_merma'],
            'lotes_a_usar'     => $lotes_a_usar,
            'hay_stock_manual' => $hay_stock_manual || empty($lotes),
        ];
    }

    /**
     * Obtiene la cantidad por tanda de un producto
     */
    public function getCantidadPorTanda(int $id_producto): float {
        $stmt = $this->pdo->prepare("SELECT cantidad_por_tanda FROM producto WHERE id_producto=?");
        $stmt->execute([$id_producto]);
        return (float)($stmt->fetchColumn() ?: 1);
    }

    /**
     * Obtiene el nombre de un producto por su ID
     */
    public function getNombreProducto(int $id_producto): string {
        $stmt = $this->pdo->prepare("SELECT nombre FROM producto WHERE id_producto=?");
        $stmt->execute([$id_producto]);
        return $stmt->fetchColumn() ?: '';
    }

    /**
     * Registra una producción completa con consumo FIFO de lotes (transaccional)
     *
     * @param int    $id_prod      ID del producto
     * @param int|null $id_receta  ID de la receta vigente
     * @param int    $id_usuario   ID del usuario operario
     * @param int    $tandas       Número de tandas
     * @param int    $unidades     Unidades producidas calculadas
     * @param string $fecha_hora   Fecha y hora de producción (Y-m-d H:i:s)
     * @param string $obs          Observaciones
     * @param array  $ingredientes Ingredientes de la receta (filas de receta_ingrediente + insumo)
     * @param array  $dist_precios Distribución por categoría de precio [id_cat => unidades]
     * @param bool   $forzar       Si se fuerza el registro con stock insuficiente
     *
     * @return array ['ok'=>bool, 'id_produccion'=>int, 'costo_total'=>float, 'costo_unitario'=>float, 'unidades'=>int]
     */
    public function registrarProduccionConConsumos(
        int $id_prod,
        ?int $id_receta,
        int $id_usuario,
        int $tandas,
        int $unidades,
        string $fecha_hora,
        string $obs,
        array $ingredientes,
        array $dist_precios,
        bool $forzar = false
    ): array {
        $this->pdo->beginTransaction();
        try {
            // 1. Insertar registro de producción
            $this->pdo->prepare("
                INSERT INTO produccion
                    (id_producto, id_receta, id_usuario, cantidad_tandas,
                     fecha_produccion, observaciones, unidades_producidas, costo_total, costo_unitario)
                VALUES (?,?,?,?,?,?,?,0,0)
            ")->execute([
                $id_prod, $id_receta, $id_usuario, $tandas, $fecha_hora,
                $obs . ($forzar ? ($obs ? ' | ' : '') . '⚠ Registrado con stock insuficiente' : ''),
                $unidades
            ]);
            $id_produccion = (int)$this->pdo->lastInsertId();

            $costo_total = 0.0;
            $sin_precio  = [];

            // 2. Consumir lotes FIFO por cada ingrediente
            foreach ($ingredientes as $ing) {
                $cant_necesaria = $ing['cant_por_unidad'] * $tandas;
                $restante = $cant_necesaria;

                $lotes = $this->getLotesDisponiblesFIFOParaConsumo($ing['id_insumo']);

                // Consumir lotes FIFO primero
                foreach ($lotes as $lote) {
                    if ($restante <= 0) break;
                    $consumir = min((float)$lote['cantidad_disponible'], $restante);
                    $costo    = round($consumir * (float)$lote['precio_unitario'], 2);
                    $costo_total += $costo;

                    $this->pdo->prepare("
                        INSERT INTO consumo_lote (id_produccion, id_lote, cantidad_consumida, cantidad_con_merma, costo_consumo)
                        VALUES (?,?,?,?,?)
                    ")->execute([$id_produccion, $lote['id_lote'], $consumir, $consumir, $costo]);

                    $nueva_disp   = round((float)$lote['cantidad_disponible'] - $consumir, 4);
                    $nuevo_estado = $nueva_disp <= 0 ? 'agotado' : 'activo';
                    $this->pdo->prepare("
                        UPDATE lote SET cantidad_disponible=?, estado=? WHERE id_lote=?
                    ")->execute([$nueva_disp, $nuevo_estado, $lote['id_lote']]);

                    $restante -= $consumir;
                }

                // Remanente sin lote real: generar un lote sintético con el último precio
                // conocido (a la fecha de esta producción, sin mirar compras futuras) para
                // que el consumo quede con costo trazable en vez de perderse en silencio.
                if ($restante > 0.0001) {
                    $stmt_precio = $this->pdo->prepare("
                        SELECT precio_unitario FROM lote
                        WHERE id_insumo = ? AND fecha_ingreso <= ?
                        ORDER BY fecha_ingreso DESC LIMIT 1
                    ");
                    $stmt_precio->execute([$ing['id_insumo'], $fecha_hora]);
                    $ultimo_precio = $stmt_precio->fetchColumn();

                    if ($ultimo_precio !== false) {
                        $precio_estimado = (float)$ultimo_precio;
                        $costo_estimado  = round($restante * $precio_estimado, 2);
                        $costo_total    += $costo_estimado;

                        // numero_lote único: id_produccion es autoincremental y cada insumo
                        // aparece una sola vez por producción en este bucle, no puede colisionar
                        $numero_lote_est = 'EST-' . $id_produccion . '-' . $ing['id_insumo'];
                        $this->pdo->prepare("
                            INSERT INTO lote
                                (id_insumo, id_compra, numero_lote, cantidad_inicial, cantidad_disponible, precio_unitario, fecha_ingreso, estado)
                            VALUES (?, NULL, ?, ?, 0, ?, ?, 'agotado')
                        ")->execute([$ing['id_insumo'], $numero_lote_est, $restante, $precio_estimado, $fecha_hora]);
                        $id_lote_sint = (int)$this->pdo->lastInsertId();

                        $this->pdo->prepare("
                            INSERT INTO consumo_lote (id_produccion, id_lote, cantidad_consumida, cantidad_con_merma, costo_consumo)
                            VALUES (?,?,?,?,?)
                        ")->execute([$id_produccion, $id_lote_sint, $restante, $restante, $costo_estimado]);
                    } else {
                        // Sin ningún precio de referencia disponible a esta fecha: costo 0
                        // para este remanente, pero queda advertencia visible (no se oculta).
                        $sin_precio[] = $ing['nombre'];
                    }
                }

                // Siempre descontar stock_actual (cubre lotes + stock manual)
                $this->pdo->prepare("
                    UPDATE insumo SET stock_actual = GREATEST(0, stock_actual - ?) WHERE id_insumo=?
                ")->execute([$ing['cant_por_unidad'] * $tandas, $ing['id_insumo']]);
            }

            // 3. Actualizar costos en la producción
            $costo_unit = $unidades > 0 ? round($costo_total / $unidades, 4) : 0;
            $this->pdo->prepare("
                UPDATE produccion SET costo_total=?, costo_unitario=? WHERE id_produccion=?
            ")->execute([$costo_total, $costo_unit, $id_produccion]);

            // 3b. Advertencia visible si algún insumo no tuvo ningún precio de referencia
            if (!empty($sin_precio)) {
                $this->pdo->prepare("
                    UPDATE produccion
                    SET observaciones = CONCAT(observaciones, IF(observaciones = '', '', ' | '), ?)
                    WHERE id_produccion = ?
                ")->execute(['⚠ Sin precio histórico para: ' . implode(', ', $sin_precio), $id_produccion]);
            }

            // 4. Insertar distribución por categoría de precio
            $total_real = 0;
            foreach ($dist_precios as $id_cat => $und_cat) {
                $und_cat = (int)$und_cat;
                if ($und_cat > 0) {
                    $this->pdo->prepare(
                        "INSERT INTO produccion_precio (id_produccion, id_categoria_precio, unidades) VALUES (?,?,?)"
                    )->execute([$id_produccion, (int)$id_cat, $und_cat]);
                    $total_real += $und_cat;
                }
            }

            // 5. Actualizar unidades_producidas con el total real distribuido
            if ($total_real > 0 && $total_real != $unidades) {
                $this->pdo->prepare(
                    "UPDATE produccion SET unidades_producidas=? WHERE id_produccion=?"
                )->execute([$total_real, $id_produccion]);
                $unidades = $total_real;
            }

            $this->pdo->commit();

            return [
                'ok'              => true,
                'id_produccion'   => $id_produccion,
                'costo_total'     => $costo_total,
                'costo_unitario'  => $costo_unit,
                'unidades'        => $unidades,
            ];

        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Obtiene lotes activos con disponibilidad para consumo FIFO (uso interno en transacción)
     */
    private function getLotesDisponiblesFIFOParaConsumo(int $id_insumo): array {
        $stmt = $this->pdo->prepare("
            SELECT id_lote, cantidad_disponible, precio_unitario
            FROM lote
            WHERE id_insumo=? AND estado='activo' AND cantidad_disponible>0
            ORDER BY fecha_ingreso ASC
        ");
        $stmt->execute([$id_insumo]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica stock suficiente para todos los ingredientes de una receta, y además
     * detecta (sin bloquear) cuándo insumo.stock_actual difiere de la suma real de
     * lotes activos disponibles — esa divergencia es la razón por la que una
     * producción puede pasar esta verificación y aun así quedarse sin lote real
     * que cubra el consumo (ver registrarProduccionConConsumos()).
     *
     * @return array{errores: array, avisos: array} errores bloquea (salvo forzar),
     *         avisos es solo informativo
     */
    public function verificarStockIngredientes(array $ingredientes, int $tandas): array {
        $errores = [];
        $avisos  = [];
        foreach ($ingredientes as $ing) {
            $cant_necesaria = $ing['cant_por_unidad'] * $tandas;
            $disponible = $this->getInsumoStockActual($ing['id_insumo']);
            if ($disponible < $cant_necesaria) {
                $errores[] = [
                    'nombre'         => $ing['nombre'],
                    'unidad_medida'  => $ing['unidad_medida'],
                    'cant_necesaria' => $cant_necesaria,
                    'disponible'     => $disponible,
                ];
            }

            $lotes = $this->getLotesDisponiblesFIFOParaConsumo($ing['id_insumo']);
            $disponible_lotes = array_sum(array_column($lotes, 'cantidad_disponible'));
            if (round($disponible_lotes, 3) !== round($disponible, 3)) {
                $avisos[] = [
                    'nombre'           => $ing['nombre'],
                    'unidad_medida'    => $ing['unidad_medida'],
                    'stock_actual'     => $disponible,
                    'disponible_lotes' => $disponible_lotes,
                ];
            }
        }
        return ['errores' => $errores, 'avisos' => $avisos];
    }

    /**
     * Obtiene los productos activos con indicador de receta vigente
     */
    public function getProductosActivosConReceta(): array {
        return $this->pdo->query("
            SELECT p.id_producto, p.nombre, p.cantidad_por_tanda,
                   (SELECT COUNT(*) FROM receta WHERE id_producto=p.id_producto AND es_vigente=1) AS tiene_receta
            FROM producto p WHERE p.activo=1 ORDER BY p.nombre
        ")->fetchAll();
    }

    /**
     * Obtiene las categorías de precio activas
     */
    public function getCategoriasPrecio(): array {
        return $this->pdo->query(
            "SELECT * FROM categoria_precio WHERE activo=1 ORDER BY precio_unitario"
        )->fetchAll();
    }

    /**
     * Obtiene las producciones del día de hoy con datos de producto
     */
    public function getProduccionesHoy(): array {
        return $this->pdo->query("
            SELECT pr.unidades_producidas, pr.fecha_produccion, pr.observaciones,
                   pr.costo_total, pr.costo_unitario, p.nombre AS producto
            FROM produccion pr
            INNER JOIN producto p ON p.id_producto=pr.id_producto
            WHERE DATE(pr.fecha_produccion)='" . date('Y-m-d') . "'
            ORDER BY pr.fecha_produccion DESC
        ")->fetchAll();
    }

    /**
     * Obtiene la sugerencia de producción del último cierre de día
     */
    public function getUltimaSugerenciaCierre(): ?array {
        $row = $this->pdo->query("
            SELECT sugerencia_produccion, fecha FROM cierre_dia 
            WHERE sugerencia_produccion IS NOT NULL AND sugerencia_produccion != '' 
            ORDER BY fecha DESC LIMIT 1
        ")->fetch();
        return $row ?: null;
    }

    // ══════════════════════════════════════════════════════════════
    //  DETALLE — Vista de producción específica
    // ══════════════════════════════════════════════════════════════

    /**
     * Obtiene los datos generales de una producción específica por su ID
     */
    public function getProduccionDetalle(int $id_produccion): ?array {
        $stmt = $this->pdo->prepare("
            SELECT pr.*, p.nombre AS producto, p.unidad_produccion,
                   u.nombre_completo AS usuario
            FROM produccion pr
            INNER JOIN producto p ON p.id_producto = pr.id_producto
            LEFT  JOIN usuario  u ON u.id_usuario  = pr.id_usuario
            WHERE pr.id_produccion = ?
        ");
        $stmt->execute([$id_produccion]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene los consumos de lote asociados a una producción (ingredientes + lotes FIFO descontados)
     */
    public function getConsumosLote(int $id_produccion): array {
        $stmt = $this->pdo->prepare("
            SELECT cl.cantidad_consumida, cl.cantidad_con_merma, cl.costo_consumo,
                   i.nombre AS insumo, i.unidad_medida,
                   l.numero_lote, l.fecha_ingreso
            FROM consumo_lote cl
            INNER JOIN lote   l ON l.id_lote   = cl.id_lote
            INNER JOIN insumo i ON i.id_insumo = l.id_insumo
            WHERE cl.id_produccion = ?
            ORDER BY i.nombre ASC
        ");
        $stmt->execute([$id_produccion]);
        return $stmt->fetchAll();
    }
}
