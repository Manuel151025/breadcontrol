<?php
// controllers/ConfiguracionController.php

require_once __DIR__ . '/../models/ConfiguracionModel.php';
require_once __DIR__ . '/../helpers/Seguridad.php';
require_once __DIR__ . '/../includes/funciones.php';

class ConfiguracionController {
    private ConfiguracionModel $model;

    public function __construct(PDO $pdo) {
        $this->model = new ConfiguracionModel($pdo);
    }

    /**
     * Primer problema de los datos personales del perfil, o '' si son válidos.
     * Los límites son los de las columnas de `usuario`.
     */
    private static function errorDatosPerfil(string $nombre, string $correo, string $telefono): string {
        if ($nombre === '') {
            return 'El nombre es obligatorio.';
        }
        if (!texto_cabe($nombre, 100)) {
            return 'El nombre no puede superar los 100 caracteres.';
        }
        if ($correo !== '' && (!filter_var($correo, FILTER_VALIDATE_EMAIL) || !texto_cabe($correo, 150))) {
            return 'El correo no es válido.';
        }
        if (!preg_match('/^[0-9 +()-]{0,20}$/', $telefono)) {
            return 'El teléfono solo puede tener números (máximo 20 caracteres).';
        }
        return '';
    }

    /**
     * Gestión del Perfil de Usuario (perfil.php)
     */
    public function perfil(): void {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        $tab_activo = 'datos';

        $datos = $this->model->getUsuario($user['id_usuario']);
        if (!$datos) {
            cerrarSesion(); // la sesión apunta a un usuario que ya no existe
        }

        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('perfil.php?err=csrf');

            // ── 1. Guardar Datos Personales ──────────────────────────────────
            if (isset($_POST['guardar_perfil'])) {
                $nombre   = trim(post_texto('nombre_completo'));
                $correo   = trim(post_texto('correo_electronico'));
                $telefono = trim(post_texto('telefono'));

                $msg_err = self::errorDatosPerfil($nombre, $correo, $telefono);
                if ($msg_err === '') {
                    try {
                        $this->model->updateUsuarioPerfil($user['id_usuario'], $nombre, $correo ?: null, $telefono ?: null);
                        $msg_ok = "Datos actualizados correctamente.";
                        $datos = $this->model->getUsuario($user['id_usuario']) ?: $datos;
                    } catch (Exception $e) {
                        $msg_err = 'Error al actualizar datos: ' . mensaje_para_usuario($e);
                    }
                }
                $tab_activo = 'datos';
            }

            // ── 2. Cambiar Contraseña ────────────────────────────────────────
            elseif (isset($_POST['cambiar_clave'])) {
                $actual = post_texto('clave_actual');
                $nueva  = post_texto('clave_nueva');
                $conf   = post_texto('clave_confirmar');

                if (!password_verify($actual, $datos['contrasena_hash'])) {
                    $msg_err = 'Contraseña actual incorrecta.';
                } elseif (($fallo = Seguridad::validarContrasena($nueva)) !== null) {
                    $msg_err = $fallo;
                } elseif ($nueva !== $conf) {
                    $msg_err = 'Las contraseñas no coinciden.';
                } else {
                    try {
                        $new_hash = password_hash($nueva, PASSWORD_BCRYPT);
                        $this->model->updateUsuarioPassword($user['id_usuario'], $new_hash);
                        $msg_ok = "Contraseña actualizada.";
                    } catch (Exception $e) {
                        $msg_err = 'Error al actualizar contraseña: ' . mensaje_para_usuario($e);
                    }
                }
                $tab_activo = 'seguridad';
            }

            // ── 3. Guardar PIN desde Perfil ───────────────────────────────────
            elseif (isset($_POST['guardar_pin'])) {
                $pin     = trim($_POST['pin'] ?? '');
                $clave_p = $_POST['clave_pin'] ?? '';

                if (!password_verify($clave_p, $datos['contrasena_hash'])) {
                    $msg_err = 'Contraseña incorrecta.';
                } elseif (!preg_match('/^\d{6}$/', $pin)) {
                    $msg_err = 'El PIN debe ser de 6 dígitos.';
                } else {
                    try {
                        $pin_hash = password_hash($pin, PASSWORD_BCRYPT);
                        $this->model->updateUsuarioPIN($user['id_usuario'], $pin_hash);
                        $msg_ok = "PIN guardado correctamente.";
                        $datos = $this->model->getUsuario($user['id_usuario']) ?: $datos;
                    } catch (Exception $e) {
                        $msg_err = 'Error al guardar el PIN: ' . mensaje_para_usuario($e);
                    }
                }
                $tab_activo = 'pin';
            }
        }

        // Calcular iniciales para el avatar
        $initials = mb_strtoupper(mb_substr($datos['nombre_completo'], 0, 1));
        $parts = explode(' ', $datos['nombre_completo']);
        if (count($parts) > 1) {
            $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
        }

        $page_title = 'Mi Perfil';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/configuracion/perfil.php';
    }

    /**
     * Configuración de PIN de recuperación (pin.php)
     */
    public function pin(): void {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';

        // Verificar si ya tiene PIN
        $datos = $this->model->getUsuario($user['id_usuario']);
        if (!$datos) {
            cerrarSesion(); // la sesión apunta a un usuario que ya no existe
        }
        $tiene_pin = !empty($datos['pin_recuperacion']);

        if (($_GET['err'] ?? '') === 'csrf') {
            $msg_err = 'No se pudo completar la acción: token de seguridad inválido o expirado. Intenta de nuevo.';
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            requerir_csrf('pin.php?err=csrf');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pin'])) {
            $clave_actual = $_POST['clave_actual'] ?? '';
            $pin          = trim($_POST['pin'] ?? '');
            $pin_conf     = trim($_POST['pin_confirmar'] ?? '');

            if (!password_verify($clave_actual, $datos['contrasena_hash'])) {
                $msg_err = 'Contraseña actual incorrecta.';
            } elseif (!preg_match('/^\d{6}$/', $pin)) {
                $msg_err = 'El PIN debe ser exactamente 6 dígitos numéricos.';
            } elseif ($pin !== $pin_conf) {
                $msg_err = 'Los PINs no coinciden.';
            } else {
                try {
                    $pin_hash = password_hash($pin, PASSWORD_BCRYPT);
                    $this->model->updateUsuarioPIN($user['id_usuario'], $pin_hash);
                    $msg_ok = 'PIN de recuperación guardado correctamente.';
                    $tiene_pin = true;
                } catch (Exception $e) {
                    $msg_err = 'Error al guardar el PIN.';
                }
            }
        }

        $page_title = 'Configurar PIN';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/configuracion/pin.php';
    }
}
