<?php
// controllers/portal/PortalControllerBase.php

require_once __DIR__ . '/../../models/PortalClienteModel.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';
require_once __DIR__ . '/../../helpers/ReglasPortal.php';

/**
 * Base común de los controladores del Portal de Clientes.
 * Aporta el modelo, la conexión y los helpers compartidos de sesión,
 * autorización y canje de código de aprendiz.
 */
abstract class PortalControllerBase {
    protected $model;
    protected $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new PortalClienteModel($pdo);
    }

    /**
     * Asegura que el cliente haya iniciado sesión.
     */
    protected function requireCliente() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['cliente_id'])) {
            header('Location: index.php');
            exit;
        }
        // Cuenta de portal sin correo: obligar a completarlo antes de usar el portal
        // (la bandera solo se activa en el login tradicional; ver login() y completarEmail()).
        if (!empty($_SESSION['falta_email'])) {
            header('Location: completar_email.php');
            exit;
        }
    }

    /**
     * Asegura que la sesión esté iniciada sin redireccionar de inmediato (para login/registro).
     */
    protected function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * ÚNICO punto de canje de código de aprendiz para todo el portal (registro, perfil,
     * completar_perfil y tablero). Aplica el límite de intentos por sesión (bloqueo de
     * 10 minutos tras 5 fallos) y delega TODAS las validaciones a
     * PortalClienteModel::canjearCodigoAprendiz (código inexistente/desactivado/vencido,
     * usos agotados, ya es aprendiz, cuenta instructor, FOR UPDATE sobre el código).
     * Ningún llamador debe validar por su cuenta.
     *
     * @return array{ok: bool, error: string, instructor: string} (mensajes SIN escapar)
     */
    protected function intentarCanjeCodigo(int $cliente_id, string $codigo): array {
        $ahora   = time();
        $bloqueo = (int)($_SESSION['canje_bloqueo_hasta'] ?? 0);
        if ($ahora < $bloqueo) {
            $mins = (int)ceil(($bloqueo - $ahora) / 60);
            return ['ok' => false, 'instructor' => '',
                    'error' => "Demasiados intentos con códigos. Espera $mins minuto(s) e intenta de nuevo."];
        }

        try {
            $r = $this->model->canjearCodigoAprendiz($cliente_id, $codigo);
        } catch (Exception $e) {
            log_error($e);
            return ['ok' => false, 'instructor' => '', 'error' => 'No se pudo canjear el código. Intenta de nuevo.'];
        }

        if ($r['ok']) {
            $_SESSION['canje_intentos'] = 0;
            return ['ok' => true, 'instructor' => $r['instructor'], 'error' => ''];
        }

        $intentos = (int)($_SESSION['canje_intentos'] ?? 0) + 1;
        $_SESSION['canje_intentos'] = $intentos;
        if ($intentos >= 5) {
            $_SESSION['canje_bloqueo_hasta'] = $ahora + 600; // 10 minutos
            $_SESSION['canje_intentos'] = 0;
            return ['ok' => false, 'instructor' => '',
                    'error' => 'Demasiados intentos. Espera 10 minutos e intenta de nuevo.'];
        }
        return ['ok' => false, 'instructor' => '', 'error' => $r['error']];
    }
}
