<?php
// models/portal/CuentaClienteTrait.php

/**
 * Cuenta del cliente del portal: búsqueda por usuario/email/Google,
 * registro (tradicional y OAuth), perfil, contraseña, PIN y recuperación.
 * Parte de PortalClienteModel (dividido por responsabilidad).
 *
 * Fila completa de `cliente` (SELECT *). Tipos según lo que entrega PDO con
 * EMULATE_PREPARES = false: INT/TINYINT como int, DECIMAL y fechas como string.
 *
 * @phpstan-type FilaCliente array{
 *     id_cliente: int,
 *     nombre: string,
 *     tipo: string,
 *     telefono: string|null,
 *     activo: int,
 *     fecha_creacion: string,
 *     usuario: string|null,
 *     contrasena_hash: string|null,
 *     es_aprendiz: int,
 *     cupo_semanal: string,
 *     id_instructor: int|null,
 *     email: string|null,
 *     foto_url: string|null,
 *     google_id: string|null,
 *     notas: string|null,
 *     es_beneficiaria: int,
 *     pin_recuperacion: string|null,
 *     codigo_recuperacion: string|null,
 *     codigo_expira: string|null,
 *     fecha_aprendiz: string|null
 * }
 */
trait CuentaClienteTrait {

    /**
     * Busca un cliente activo por nombre de usuario.
     * @return FilaCliente|null
     */
    public function getClienteByUsuario(string $usuario): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE usuario = ? AND activo = 1");
        $stmt->execute([$usuario]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por ID.
     * @return FilaCliente|null
     */
    public function getClienteById(int $id): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE id_cliente = ?");
        $stmt->execute([$id]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por correo electrónico (Google OAuth).
     * @return FilaCliente|null
     */
    public function getClienteByEmail(string $email): ?array {
        $stmt = $this->pdo->prepare("SELECT * FROM cliente WHERE email = ? AND activo = 1 LIMIT 1");
        $stmt->execute([$email]);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Busca un cliente por google_id.
     * @return FilaCliente|null
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
     * Registra un cliente tradicional. Devuelve el id del nuevo cliente, o 0 si falla.
     * El email es obligatorio (clave para enlazar luego con Google y evitar duplicados).
     */
    public function registrarCliente(string $nombre, string $tipo, string $telefono, ?string $email, string $usuario, string $hash, int $es_aprendiz, ?int $id_instructor = null): int {
        $stmt = $this->pdo->prepare("
            INSERT INTO cliente (nombre, tipo, telefono, email, usuario, contrasena_hash, es_aprendiz, id_instructor, activo, fecha_creacion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
        ");
        if (!$stmt->execute([$nombre, $tipo, $telefono, $email, $usuario, $hash, $es_aprendiz, $id_instructor])) {
            return 0;
        }
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * ¿El correo ya está registrado en alguna cuenta (activa o no)? Incluye NULL-safe:
     * un email vacío nunca se considera "registrado". Sirve para el mensaje claro y como
     * pre-chequeo del índice único uq_cliente_email.
     */
    public function emailRegistrado(string $email, ?int $excluirId = null): bool {
        $email = trim($email);
        if ($email === '') return false;
        $sql = "SELECT 1 FROM cliente WHERE email = ?";
        $params = [$email];
        if ($excluirId !== null) {
            $sql .= " AND id_cliente != ?";
            $params[] = $excluirId;
        }
        $sql .= " LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Actualiza el correo de un cliente (pantalla intermedia para cuentas de portal
     * que aún no tienen email).
     */
    public function actualizarEmail(int $id, string $email): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET email = ? WHERE id_cliente = ?");
        return $stmt->execute([$email, $id]);
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
     * Actualiza solo nombre y teléfono, sin tocar el vínculo aprendiz/instructor
     * (ese vínculo se gestiona por código, no desde la edición de datos del perfil).
     */
    public function actualizarDatosBasicos(int $id, string $nombre, string $telefono): bool {
        $stmt = $this->pdo->prepare("UPDATE cliente SET nombre = ?, telefono = ? WHERE id_cliente = ?");
        return $stmt->execute([$nombre, $telefono, $id]);
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
}
