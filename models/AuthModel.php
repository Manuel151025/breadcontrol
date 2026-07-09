<?php
// models/AuthModel.php

require_once __DIR__ . '/../helpers/FinanzasHelper.php';

class AuthModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtiene los detalles de un usuario activo por su nombre de usuario.
     */
    public function getUsuarioPorNombre(string $usuario): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM usuario WHERE nombre_usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Obtiene detalles de un usuario por su ID.
     */
    public function getUsuarioPorId(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Guarda el código y expiración para restablecimiento por correo.
     */
    public function registrarCodigoRecuperacion(int $id_usuario, string $codigo, string $expira): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET codigo_recuperacion = ?, codigo_expira = ? WHERE id_usuario = ?");
        return $stmt->execute([$codigo, $expira, $id_usuario]);
    }

    /**
     * Borra el código de recuperación.
     */
    public function limpiarCodigoRecuperacion(int $id_usuario): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET codigo_recuperacion = NULL, codigo_expira = NULL WHERE id_usuario = ?");
        return $stmt->execute([$id_usuario]);
    }

    /**
     * Actualiza la contraseña hash del usuario.
     */
    public function actualizarClaveUsuario(int $id_usuario, string $hash): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET contrasena_hash = ? WHERE id_usuario = ?");
        return $stmt->execute([$hash, $id_usuario]);
    }

    /**
     * Obtiene de manera centralizada las estadísticas diarias para la portada pública.
     */
    public function getLandingStats(): array {
        $stats = [];
        try {
            $hoy = date('Y-m-d');
            $stats['total_insumos']  = (int)$this->pdo->query("SELECT COUNT(*) FROM insumo WHERE activo = 1")->fetchColumn();
            $stats['insumos_bajos']  = (int)$this->pdo->query("SELECT COUNT(*) FROM insumo WHERE stock_actual <= punto_reposicion AND activo = 1")->fetchColumn();
            $stats['prod_hoy']       = (int)$this->pdo->query("SELECT COUNT(*) FROM produccion WHERE DATE(fecha_produccion) = CURDATE()")->fetchColumn();
            $stats['tandas_hoy']     = (float)$this->pdo->query("SELECT COALESCE(SUM(cantidad_tandas),0) FROM produccion WHERE DATE(fecha_produccion) = CURDATE()")->fetchColumn();
            $stats['ventas_hoy']     = (float)$this->pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora) = CURDATE()")->fetchColumn();
            $stats['num_ventas']     = (int)$this->pdo->query("SELECT COUNT(*) FROM venta WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
            $stats['gastos_hoy']     = (float)$this->pdo->query("SELECT COALESCE(SUM(valor),0) FROM gasto WHERE DATE(fecha_gasto) = CURDATE()")->fetchColumn();
            $stats['costo_prod_hoy'] = FinanzasHelper::costoProduccionEnRango($this->pdo, $hoy, $hoy);
            $stats['utilidad_hoy']   = FinanzasHelper::calcularUtilidad($stats['ventas_hoy'], $stats['costo_prod_hoy'], $stats['gastos_hoy'])['neta'];
            $stats['cierre_hoy']     = (bool)$this->pdo->query("SELECT id_cierre FROM cierre_dia WHERE fecha = CURDATE()")->fetchColumn();
            $stats['productos_act']  = (int)$this->pdo->query("SELECT COUNT(*) FROM producto WHERE activo = 1")->fetchColumn();
        } catch (Exception $e) {
            $stats = [
                'total_insumos'  => 0,
                'insumos_bajos'  => 0,
                'prod_hoy'       => 0,
                'tandas_hoy'     => 0.0,
                'ventas_hoy'     => 0.0,
                'num_ventas'     => 0,
                'gastos_hoy'     => 0.0,
                'costo_prod_hoy' => 0.0,
                'utilidad_hoy'   => 0.0,
                'cierre_hoy'     => false,
                'productos_act'  => 0
            ];
        }
        return $stats;
    }
}
