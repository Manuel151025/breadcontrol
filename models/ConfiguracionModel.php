<?php
// models/ConfiguracionModel.php

class ConfiguracionModel {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Obtener los datos completos de un usuario
     * @return array<string, mixed>|false
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
}
