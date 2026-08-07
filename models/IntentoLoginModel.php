<?php
// models/IntentoLoginModel.php

require_once __DIR__ . '/../helpers/Seguridad.php';

/**
 * Registro de intentos fallidos de inicio de sesión, para frenar la fuerza
 * bruta en el login administrativo y en el del Portal de Clientes.
 *
 * Se persiste en base de datos (no en sesión) a propósito: un atacante controla
 * su propia cookie, así que un contador en `$_SESSION` se esquiva descartándola.
 * Los umbrales y la ventana viven en Seguridad.
 */
class IntentoLoginModel {

    public const AMBITO_ADMIN  = 'admin';
    public const AMBITO_PORTAL = 'portal';

    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    /**
     * Deja constancia de un intento fallido y purga los registros antiguos.
     */
    public function registrarFallo(string $ambito, string $identificador, ?string $ip): bool {
        $this->purgarAntiguos();
        $stmt = $this->pdo->prepare(
            "INSERT INTO intento_login (ambito, identificador, ip, fecha) VALUES (?, ?, ?, NOW())"
        );
        return $stmt->execute([$ambito, mb_substr($identificador, 0, 150), $ip]);
    }

    /**
     * Borra los intentos de una cuenta tras un inicio de sesión correcto, para
     * que un fallo de tecleo previo no acerque al usuario legítimo al bloqueo.
     */
    public function limpiar(string $ambito, string $identificador): bool {
        $stmt = $this->pdo->prepare(
            "DELETE FROM intento_login WHERE ambito = ? AND identificador = ?"
        );
        return $stmt->execute([$ambito, mb_substr($identificador, 0, 150)]);
    }

    /**
     * Indica si la cuenta o la IP superaron su umbral dentro de la ventana.
     */
    public function estaBloqueado(string $ambito, string $identificador, ?string $ip): bool {
        $desde = date('Y-m-d H:i:s', time() - (Seguridad::LOGIN_VENTANA_MINUTOS * 60));

        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM intento_login
             WHERE ambito = ? AND identificador = ? AND fecha >= ?"
        );
        $stmt->execute([$ambito, mb_substr($identificador, 0, 150), $desde]);
        if ((int) $stmt->fetchColumn() >= Seguridad::LOGIN_MAX_INTENTOS) {
            return true;
        }

        if ($ip === null || $ip === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM intento_login WHERE ip = ? AND fecha >= ?"
        );
        $stmt->execute([$ip, $desde]);
        return (int) $stmt->fetchColumn() >= Seguridad::LOGIN_MAX_INTENTOS_IP;
    }

    /**
     * Elimina los intentos de más de un día: la tabla solo alimenta una ventana
     * de minutos, así que nada más antiguo tiene uso.
     */
    private function purgarAntiguos(): void {
        $this->pdo->exec("DELETE FROM intento_login WHERE fecha < (NOW() - INTERVAL 1 DAY)");
    }
}
