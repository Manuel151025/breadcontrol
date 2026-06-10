<?php
class TableroModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function getEstadisticasVentas() {
        $hoy = (float)$this->pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=CURDATE()")->fetchColumn();
        $ayer = (float)$this->pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();
        $num_ventas = (int)$this->pdo->query("SELECT COUNT(*) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=CURDATE()")->fetchColumn();
        $diff_v = $ayer > 0 ? round((($hoy - $ayer) / $ayer) * 100, 1) : null;
        
        return [
            'hoy' => $hoy,
            'ayer' => $ayer,
            'num_ventas' => $num_ventas,
            'diff_v' => $diff_v
        ];
    }

    public function getFinanzasMes() {
        $ingresos = (float)$this->pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND MONTH(fecha_hora)=MONTH(CURDATE()) AND YEAR(fecha_hora)=YEAR(CURDATE())")->fetchColumn();
        $compras = (float)$this->pdo->query("SELECT COALESCE(SUM(total_pagado),0) FROM compra WHERE MONTH(fecha_compra)=MONTH(CURDATE()) AND YEAR(fecha_compra)=YEAR(CURDATE())")->fetchColumn();
        return [
            'ingresos' => $ingresos,
            'compras' => $compras,
            'utilidad' => $ingresos - $compras
        ];
    }

    public function getResumenInventario() {
        $total_insumos = (int)$this->pdo->query("SELECT COUNT(*) FROM insumo WHERE activo=1")->fetchColumn();
        $prod_hoy = (int)$this->pdo->query("SELECT COUNT(*) FROM produccion WHERE DATE(fecha_produccion)=CURDATE()")->fetchColumn();
        $prods_act = (int)$this->pdo->query("SELECT COUNT(*) FROM producto WHERE activo=1")->fetchColumn();
        return [
            'total_insumos' => $total_insumos,
            'prod_hoy' => $prod_hoy,
            'prods_act' => $prods_act
        ];
    }

    public function getAlertas() {
        return $this->pdo->query("
            SELECT * FROM v_insumos_alerta 
            ORDER BY (stock_actual / NULLIF(punto_reposicion, 0)) ASC 
            LIMIT 5
        ")->fetchAll();
    }

    public function getProduccionesRecientes() {
        return $this->pdo->query("
            SELECT pr.fecha_produccion, pr.cantidad_tandas, p.nombre, p.unidad_produccion
            FROM produccion pr
            INNER JOIN producto p ON p.id_producto = pr.id_producto
            ORDER BY pr.fecha_produccion DESC, pr.id_produccion DESC
            LIMIT 4
        ")->fetchAll();
    }

    public function getTopVentas() {
        return $this->pdo->query("
            SELECT COALESCE(cp.nombre, p.nombre) AS nombre, SUM(v.unidades_vendidas) AS u, SUM(v.total_venta) AS t
            FROM venta v
            LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio
            LEFT JOIN producto p ON p.id_producto = v.id_producto
            WHERE v.tipo_salida='venta' AND DATE(v.fecha_hora) = CURDATE()
            GROUP BY nombre
            ORDER BY t DESC
            LIMIT 4
        ")->fetchAll();
    }

    public function getVentasUltimos7Dias() {
        return $this->pdo->query("
            SELECT DATE(fecha_hora) AS d, COALESCE(SUM(total_venta), 0) AS t
            FROM venta
            WHERE tipo_salida='venta' AND fecha_hora >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(fecha_hora)
        ")->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    public function getConsumoHoy() {
        $consumo_hoy = $this->pdo->query("
            SELECT i.nombre, i.unidad_medida,
                   COALESCE(SUM(cl.cantidad_consumida),0) AS total
            FROM consumo_lote cl
            INNER JOIN lote l        ON l.id_lote       = cl.id_lote
            INNER JOIN insumo i      ON i.id_insumo      = l.id_insumo
            INNER JOIN produccion pr ON pr.id_produccion = cl.id_produccion
            WHERE DATE(pr.fecha_produccion) = CURDATE()
            GROUP BY i.id_insumo ORDER BY total DESC LIMIT 5
        ")->fetchAll();
        
        if (empty($consumo_hoy)) {
            $consumo_hoy = $this->pdo->query("
                SELECT i.nombre, i.unidad_medida,
                       SUM(ri.cantidad * pr.cantidad_tandas) AS total
                FROM produccion pr
                INNER JOIN receta_ingrediente ri ON ri.id_receta = pr.id_receta
                INNER JOIN insumo i ON i.id_insumo = ri.id_insumo
                WHERE DATE(pr.fecha_produccion) = CURDATE()
                GROUP BY i.id_insumo ORDER BY total DESC LIMIT 5
            ")->fetchAll();
        }
        return $consumo_hoy;
    }

    public function getObservacionCierre() {
        return $this->pdo->query("
            SELECT sugerencia_produccion, fecha FROM cierre_dia 
            WHERE sugerencia_produccion IS NOT NULL AND sugerencia_produccion != '' 
            ORDER BY fecha DESC LIMIT 1
        ")->fetch();
    }
}
