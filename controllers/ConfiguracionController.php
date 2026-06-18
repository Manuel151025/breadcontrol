<?php
// controllers/ConfiguracionController.php

require_once __DIR__ . '/../models/ConfiguracionModel.php';

class ConfiguracionController {
    private $model;

    public function __construct(PDO $pdo) {
        $this->model = new ConfiguracionModel($pdo);
    }

    /**
     * Gestión del Perfil de Usuario (perfil.php)
     */
    public function perfil() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';
        $tab_activo = 'datos';

        $datos = $this->model->getUsuario($user['id_usuario']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ── 1. Guardar Datos Personales ──────────────────────────────────
            if (isset($_POST['guardar_perfil'])) {
                $nombre   = trim($_POST['nombre_completo'] ?? '');
                $correo   = trim($_POST['correo_electronico'] ?? '');
                $telefono = trim($_POST['telefono'] ?? '');
                
                if (empty($nombre)) {
                    $msg_err = 'El nombre es obligatorio.';
                } elseif ($correo && !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
                    $msg_err = 'El correo no es válido.';
                } else {
                    try {
                        $this->model->updateUsuarioPerfil($user['id_usuario'], $nombre, $correo ?: null, $telefono ?: null);
                        $msg_ok = "Datos actualizados correctamente.";
                        $datos = $this->model->getUsuario($user['id_usuario']);
                    } catch (Exception $e) {
                        $msg_err = 'Error al actualizar datos: ' . $e->getMessage();
                    }
                }
                $tab_activo = 'datos';
            }

            // ── 2. Cambiar Contraseña ────────────────────────────────────────
            elseif (isset($_POST['cambiar_clave'])) {
                $actual = $_POST['clave_actual'] ?? '';
                $nueva  = $_POST['clave_nueva'] ?? '';
                $conf   = $_POST['clave_confirmar'] ?? '';

                if (!password_verify($actual, $datos['contrasena_hash'])) {
                    $msg_err = 'Contraseña actual incorrecta.';
                } elseif (strlen($nueva) < 6) {
                    $msg_err = 'Mínimo 6 caracteres.';
                } elseif ($nueva !== $conf) {
                    $msg_err = 'Las contraseñas no coinciden.';
                } else {
                    try {
                        $new_hash = password_hash($nueva, PASSWORD_BCRYPT);
                        $this->model->updateUsuarioPassword($user['id_usuario'], $new_hash);
                        $msg_ok = "Contraseña actualizada.";
                    } catch (Exception $e) {
                        $msg_err = 'Error al actualizar contraseña: ' . $e->getMessage();
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
                        $datos = $this->model->getUsuario($user['id_usuario']);
                    } catch (Exception $e) {
                        $msg_err = 'Error al guardar el PIN: ' . $e->getMessage();
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
     * Configuración de Pagos Digitales (pagos.php)
     */
    public function pagos() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';

        // Cargar configuracion actual
        $config = $this->model->getConfiguracion();

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_link'])) {
            $link     = trim($_POST['nequi_link_pago'] ?? '');
            $titular  = trim($_POST['nequi_titular'] ?? '');
            $habilitar = isset($_POST['wompi_habilitado']) ? 1 : 0;
            $auto     = isset($_POST['wompi_confirmar_auto']) ? 1 : 0;

            if ($habilitar && empty($link)) {
                $msg_err = 'Si activas los pagos digitales, debes ingresar tu link.';
            } elseif ($link && !filter_var($link, FILTER_VALIDATE_URL)) {
                $msg_err = 'La URL del link no es válida.';
            } elseif ($link && !preg_match('#^https?://([a-z0-9\-]+\.)*wompi\.co/#i', $link)) {
                $msg_err = 'La URL debe ser un link válido de Wompi (checkout.wompi.co o checkout.nequi.wompi.co).';
            } else {
                try {
                    $this->model->updateConfiguracion($link ?: null, $titular ?: null, $habilitar, $auto);
                    $msg_ok = 'Configuración de pagos guardada correctamente.';
                    $config = $this->model->getConfiguracion();
                } catch (Exception $e) {
                    $msg_err = 'Error al guardar: ' . $e->getMessage();
                }
            }
        }

        // Calcular iniciales para el header/avatar
        $initials = mb_strtoupper(mb_substr($user['nombre_completo'] ?? 'U', 0, 1));
        $parts = explode(' ', $user['nombre_completo'] ?? '');
        if (count($parts) > 1) {
            $initials .= mb_strtoupper(mb_substr($parts[1], 0, 1));
        }

        $page_title = 'Configuracion de Pagos';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/configuracion/pagos.php';
    }

    /**
     * Configuración de PIN de recuperación (pin.php)
     */
    public function pin() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok  = '';
        $msg_err = '';

        // Verificar si ya tiene PIN
        $datos = $this->model->getUsuario($user['id_usuario']);
        $tiene_pin = !empty($datos['pin_recuperacion']);

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

    /**
     * Gestión de Tiendas Beneficiarias (tiendas.php)
     */
    public function tiendas() {
        requerirPropietario();
        $user = usuarioActual();

        $msg_ok = '';
        $msg_err = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // ── 1. Crear nueva tienda beneficiaria ───────────────────────────
            if (isset($_POST['crear_tienda'])) {
                $nombre   = trim($_POST['nombre'] ?? '');
                $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
                if (strlen($telefono) > 15) {
                    $telefono = substr($telefono, 0, 15);
                }

                if (mb_strlen($nombre) < 3) {
                    $msg_err = 'El nombre de la tienda debe tener al menos 3 caracteres.';
                } elseif (mb_strlen($nombre) > 100) {
                    $msg_err = 'El nombre de la tienda no puede superar los 100 caracteres.';
                } else {
                    try {
                        $this->model->crearTiendaBeneficiaria($nombre, $telefono ?: null);
                        $msg_ok = 'Tienda beneficiaria creada correctamente.';
                    } catch (Exception $e) {
                        $msg_err = 'Error al crear: ' . $e->getMessage();
                    }
                }
            }

            // ── 2. Marcar / desmarcar como beneficiaria ───────────────────────
            elseif (isset($_POST['toggle_beneficiaria'])) {
                $id_cliente = (int)($_POST['id_cliente'] ?? 0);
                $accion = $_POST['toggle_beneficiaria'] === 'marcar' ? 1 : 0;
                try {
                    $this->model->toggleBeneficiaria($id_cliente, $accion);
                    $msg_ok = $accion ? 'Tienda marcada como beneficiaria.' : 'Tienda desmarcada.';
                } catch (Exception $e) {
                    $msg_err = 'Error: ' . $e->getMessage();
                }
            }
        }

        // Cargar datos de tiendas
        $beneficiarias = $this->model->getTiendasBeneficiarias();
        $candidatos    = $this->model->getTiendasCandidatas();

        $page_title = 'Tiendas Beneficiarias';

        // Cargar vista
        require_once __DIR__ . '/../views/layouts/header.php';
        require_once __DIR__ . '/../views/configuracion/tiendas.php';
    }
}
