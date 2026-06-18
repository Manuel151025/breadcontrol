<?php
// models/ConfiguracionModel.php

class ConfiguracionModel {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener los datos completos de un usuario
     */
    public function getUsuario(int $id_usuario) {
        $stmt = $this->pdo->prepare("SELECT * FROM usuario WHERE id_usuario = ?");
        $stmt->execute([$id_usuario]);
        return $stmt->fetch();
    }

    /**
     * Actualizar los datos de perfil de un usuario
     */
    public function updateUsuarioPerfil(int $id_usuario, string $nombre, ?string $correo, ?string $telefono): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET nombre_completo = ?, correo_electronico = ?, telefono = ? WHERE id_usuario = ?");
        return $stmt->execute([$nombre, $correo, $telefono, $id_usuario]);
    }

    /**
     * Actualizar la contraseña de un usuario
     */
    public function updateUsuarioPassword(int $id_usuario, string $password_hash): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET contrasena_hash = ? WHERE id_usuario = ?");
        return $stmt->execute([$password_hash, $id_usuario]);
    }

    /**
     * Actualizar el PIN de recuperación de un usuario
     */
    public function updateUsuarioPIN(int $id_usuario, string $pin_hash): bool {
        $stmt = $this->pdo->prepare("UPDATE usuario SET pin_recuperacion = ? WHERE id_usuario = ?");
        return $stmt->execute([$pin_hash, $id_usuario]);
    }

    /**
     * Obtener la configuración general del sistema (un solo registro)
     */
    public function getConfiguracion() {
        return $this->pdo->query("SELECT * FROM configuracion LIMIT 1")->fetch();
    }

    /**
     * Actualizar la configuración de pagos del sistema
     */
    public function updateConfiguracion(?string $link, ?string $titular, int $habilitar, int $auto): bool {
        $stmt = $this->pdo->prepare("UPDATE configuracion SET nequi_link_pago = ?, nequi_titular = ?, wompi_habilitado = ?, wompi_confirmar_auto = ?");
        return $stmt->execute([$link, $titular, $habilitar, $auto]);
    }

    /**
     * Obtener el listado de tiendas beneficiarias activas
     */
    public function getTiendasBeneficiarias() {
        return $this->pdo->query("
            SELECT c.*,
              (SELECT COUNT(*) FROM pedido_cliente WHERE id_tienda_destino = c.id_cliente) AS total_pedidos_destino
            FROM cliente c
            WHERE c.es_beneficiaria = 1 AND c.activo = 1
            ORDER BY c.nombre
        ")->fetchAll();
    }

    /**
     * Obtener el listado de clientes tipo tienda activos que no son beneficiarias
     */
    public function getTiendasCandidatas() {
        return $this->pdo->query("
            SELECT id_cliente, nombre, telefono
            FROM cliente
            WHERE tipo = 'tienda' AND activo = 1 AND es_beneficiaria = 0
            ORDER BY nombre
        ")->fetchAll();
    }

    /**
     * Crear una nueva tienda beneficiaria
     */
    public function crearTiendaBeneficiaria(string $nombre, ?string $telefono): bool {
        $stmt = $this->pdo->prepare("
            INSERT INTO cliente (nombre, tipo, telefono, activo, es_beneficiaria, fecha_creacion)
            VALUES (?, 'tienda', ?, 1, 1, NOW())
        ");
        return $stmt->execute([$nombre, $telefono]);
    }

    /**
     * Alternar el estado de beneficiario de un cliente
     */
    public function toggleBeneficiaria(int $id_cliente, int $es_beneficiaria): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET es_beneficiaria = ? WHERE id_cliente = ?");
        return $stmt->execute([$es_beneficiaria, $id_cliente]);
    }
}
