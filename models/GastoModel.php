<?php
// models/GastoModel.php

require_once __DIR__ . '/../helpers/FinanzasHelper.php';

class GastoModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene los gastos de una fecha específica
     */
    public function getGastosPorFecha(string $fecha): array {
        $stmt = $this->pdo->prepare("
            SELECT g.*, u.nombre_completo AS usuario
            FROM gasto g
            LEFT JOIN usuario u ON u.id_usuario = g.id_usuario
            WHERE DATE(g.fecha_gasto) = ?
            ORDER BY g.fecha_gasto DESC
        ");
        $stmt->execute([$fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Registra un nuevo gasto
     */
    public function registrarGasto(int $id_usuario, string $categoria, string $descripcion, float $valor): bool {
        $stmt = $this->pdo->prepare("INSERT INTO gasto (id_usuario, categoria, descripcion, valor) VALUES (?,?,?,?)");
        return $stmt->execute([$id_usuario, $categoria, $descripcion, $valor]);
    }

    /**
     * Actualiza un gasto (solo permitido para el día actual)
     */
    public function actualizarGasto(int $id_gasto, string $categoria, string $descripcion, float $valor): bool {
        $stmt = $this->pdo->prepare("
            UPDATE gasto 
            SET categoria = ?, descripcion = ?, valor = ? 
            WHERE id_gasto = ? AND DATE(fecha_gasto) = CURDATE()
        ");
        return $stmt->execute([$categoria, $descripcion, $valor, $id_gasto]);
    }

    /**
     * Elimina un gasto (solo permitido para el día actual)
     */
    public function eliminarGasto(int $id_gasto): bool {
        $stmt = $this->pdo->prepare("DELETE FROM gasto WHERE id_gasto = ? AND DATE(fecha_gasto) = CURDATE()");
        return $stmt->execute([$id_gasto]);
    }

    /**
     * Obtiene la suma total de gastos del mes actual
     */
    public function getGastosMes(): float {
        return (float)$this->pdo->query("
            SELECT COALESCE(SUM(valor), 0) 
            FROM gasto 
            WHERE MONTH(fecha_gasto) = MONTH(CURDATE()) AND YEAR(fecha_gasto) = YEAR(CURDATE())
        ")->fetchColumn();
    }

    /**
     * Obtiene el número total de registros de gastos en el mes actual
     */
    public function getNumGastosMes(): int {
        return (int)$this->pdo->query("
            SELECT COUNT(*) 
            FROM gasto 
            WHERE MONTH(fecha_gasto) = MONTH(CURDATE()) AND YEAR(fecha_gasto) = YEAR(CURDATE())
        ")->fetchColumn();
    }

    /**
     * Obtiene el total de gastos acumulado por día de los últimos 7 días
     */
    public function getGastosUltimos7Dias(): array {
        $stmt = $this->pdo->query("
            SELECT DATE(fecha_gasto) AS dia, SUM(valor) AS total
            FROM gasto
            WHERE fecha_gasto >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(fecha_gasto) 
            ORDER BY dia ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Obtiene el resumen de finanzas (ingresos por ventas, compras y costo real
     * de producción del día)
     */
    public function getResumenFinanzasDia(string $fecha): array {
        $stmt_ventas = $this->pdo->prepare("SELECT COALESCE(SUM(total_venta), 0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora) = ?");
        $stmt_ventas->execute([$fecha]);
        $ingresos_dia = (float)$stmt_ventas->fetchColumn();

        $stmt_compras = $this->pdo->prepare("SELECT COALESCE(SUM(total_pagado), 0) FROM compra WHERE DATE(fecha_compra) = ?");
        $stmt_compras->execute([$fecha]);
        $compras_dia = (float)$stmt_compras->fetchColumn();

        $costo_produccion_dia = FinanzasHelper::costoProduccionEnRango($this->pdo, $fecha, $fecha);

        return [
            'ingresos' => $ingresos_dia,
            'compras'  => $compras_dia,
            'costo_produccion' => $costo_produccion_dia
        ];
    }
}
