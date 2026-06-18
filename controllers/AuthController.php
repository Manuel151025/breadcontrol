<?php
// controllers/AuthController.php

require_once __DIR__ . '/../models/AuthModel.php';
require_once __DIR__ . '/../includes/sesion.php';

class AuthController {
    private $model;
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new AuthModel($pdo);
    }

    /**
     * Asegura que el usuario no esté ya logueado.
     */
    private function redirectIfLoggedIn() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['id_usuario'])) {
            header('Location: ' . APP_URL . '/modules/tablero/index.php');
            exit;
        }
    }

    /**
     * Muestra la portada pública con estadísticas de hoy.
     */
    public function landing() {
        $this->redirectIfLoggedIn();

        $stats = $this->model->getLandingStats();

        // Extraemos para uso directo en la vista
        $total_insumos  = $stats['total_insumos'];
        $insumos_bajos  = $stats['insumos_bajos'];
        $prod_hoy       = $stats['prod_hoy'];
        $tandas_hoy     = $stats['tandas_hoy'];
        $ventas_hoy     = $stats['ventas_hoy'];
        $num_ventas     = $stats['num_ventas'];
        $gastos_hoy     = $stats['gastos_hoy'];
        $costo_prod_hoy = $stats['costo_prod_hoy'];
        $utilidad_hoy   = $stats['utilidad_hoy'];
        $cierre_hoy     = $stats['cierre_hoy'];
        $productos_act  = $stats['productos_act'];

        require_once __DIR__ . '/../views/auth/landing.php';
    }

    /**
     * Muestra y procesa el inicio de sesión del personal administrativo.
     */
    public function login() {
        $this->redirectIfLoggedIn();

        $error = '';
        if (isset($_GET['error']) && $_GET['error'] === 'acceso_denegado') {
            $error = 'Acceso denegado. Solo el propietario puede ingresar.';
        }
        $nombre_saludo = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $clave   = $_POST['clave'] ?? '';
            
            if (empty($usuario) || empty($clave)) {
                $error = 'Por favor ingresa tu usuario y contraseña.';
            } else {
                if (iniciarSesion($usuario, $clave)) {
                    header('Location: ' . APP_URL . '/modules/tablero/index.php');
                    exit;
                } else {
                    $error = 'Usuario o contraseña incorrectos.';
                }
            }
        }

        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Flujo multietapa para la recuperación de contraseña administrativa.
     */
    public function recuperarPin() {
        $this->redirectIfLoggedIn();

        $paso  = 1;
        $error = '';
        $ok    = '';
        $usuario_input = '';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $metodo = $_SESSION['recover_metodo'] ?? '';
        if (isset($_SESSION['recover_pin_ok']))  $paso = 3;
        elseif (isset($_SESSION['recover_user_id'])) $paso = 2;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // PASO 1: Identificación y envío/elección de método
            if (isset($_POST['verificar_usuario'])) {
                $usuario_input = trim($_POST['usuario'] ?? '');
                $metodo_sel    = $_POST['metodo'] ?? 'email';

                if (empty($usuario_input)) {
                    $error = 'Ingresa tu nombre de usuario.';
                } else {
                    $user = $this->model->getUsuarioPorNombre($usuario_input);

                    if (!$user) {
                        $error = 'Usuario no encontrado.';
                    } elseif ($metodo_sel === 'email') {
                        if (empty($user['correo_electronico'])) {
                            $error = 'Este usuario no tiene correo configurado.<br>Ve a Mi Perfil para agregar uno, o usa el método PIN.';
                        } else {
                            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                            $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                            $this->model->registrarCodigoRecuperacion($user['id_usuario'], $codigo, $expira);

                            require_once __DIR__ . '/../includes/mailer.php';
                            $to      = $user['correo_electronico'];
                            $nombre  = $user['nombre_completo'];
                            $subject = 'BreadControl — Código de recuperación';
                            $body    = correo_codigo_html($nombre, $codigo, 'Tu código para recuperar el acceso a BreadControl es:');
                            $enviado = enviar_correo($to, $nombre, $subject, $body);

                            if ($enviado) {
                                $_SESSION['recover_user_id'] = $user['id_usuario'];
                                $_SESSION['recover_usuario'] = $usuario_input;
                                $_SESSION['recover_metodo'] = 'email';
                                $_SESSION['recover_email_masked'] = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $to);
                                $paso = 2; 
                                $metodo = 'email';
                            } else {
                                $error = 'No se pudo enviar el correo.<br>Intenta con el metodo PIN.';
                            }
                        }
                    } elseif ($metodo_sel === 'pin') {
                        if (empty($user['pin_recuperacion'])) {
                            $error = 'Este usuario no tiene PIN configurado.<br>Ve a Mi Perfil para crear uno.';
                        } else {
                            $_SESSION['recover_user_id'] = $user['id_usuario'];
                            $_SESSION['recover_usuario'] = $usuario_input;
                            $_SESSION['recover_metodo'] = 'pin';
                            $paso = 2; 
                            $metodo = 'pin';
                        }
                    }
                }
            }

            // PASO 2: Verificación de Código de email / PIN
            if (isset($_POST['verificar_codigo'])) {
                $codigo = trim($_POST['codigo'] ?? '');
                $uid = $_SESSION['recover_user_id'] ?? null;
                $metodo = $_SESSION['recover_metodo'] ?? '';
                $usuario_input = $_SESSION['recover_usuario'] ?? '';

                if (!$uid) {
                    $error = 'Sesion expirada.<br>Empieza de nuevo.'; 
                    $paso = 1;
                } elseif (empty($codigo) || !preg_match('/^\d{6}$/', $codigo)) {
                    $error = 'El codigo debe ser de 6 digitos.'; 
                    $paso = 2;
                } else {
                    if ($metodo === 'email') {
                        $user = $this->model->getUsuarioPorId($uid);
                        if (!$user || $user['codigo_recuperacion'] !== $codigo) {
                            $error = 'Codigo incorrecto.'; 
                            $paso = 2;
                        } elseif (strtotime($user['codigo_expira']) < time()) {
                            $error = 'El codigo ha expirado.<br>Vuelve a empezar.'; 
                            $paso = 1;
                            unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_metodo']);
                        } else {
                            $_SESSION['recover_pin_ok'] = true;
                            $this->model->limpiarCodigoRecuperacion($uid);
                            $paso = 3;
                        }
                    } else {
                        $user = $this->model->getUsuarioPorId($uid);
                        $hash = $user['pin_recuperacion'] ?? '';
                        if ($hash && password_verify($codigo, $hash)) {
                            $_SESSION['recover_pin_ok'] = true; 
                            $paso = 3;
                        } else {
                            $error = 'PIN incorrecto.'; 
                            $paso = 2;
                        }
                    }
                }
            }

            // PASO 3: Ingreso de la nueva contraseña
            if (isset($_POST['cambiar_clave'])) {
                $nueva = $_POST['nueva_clave'] ?? '';
                $conf = $_POST['confirmar_clave'] ?? '';
                $uid = $_SESSION['recover_user_id'] ?? null;
                $pin_ok = $_SESSION['recover_pin_ok'] ?? false;

                if (!$uid || !$pin_ok) {
                    $error = 'Sesion expirada.'; 
                    $paso = 1;
                } elseif (strlen($nueva) < 6) {
                    $error = 'Minimo 6 caracteres.'; 
                    $paso = 3;
                } elseif ($nueva !== $conf) {
                    $error = 'Las contrasenas no coinciden.'; 
                    $paso = 3;
                } else {
                    $hash = password_hash($nueva, PASSWORD_BCRYPT);
                    $this->model->actualizarClaveUsuario($uid, $hash);
                    unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_pin_ok'], $_SESSION['recover_metodo'], $_SESSION['recover_email_masked']);
                    $ok = 'Contrasena actualizada exitosamente!'; 
                    $paso = 0;
                }
            }
        }

        require_once __DIR__ . '/../views/auth/recuperar_pin.php';
    }

    /**
     * Cierra la sesión activa.
     */
    public function logout() {
        cerrarSesion();
    }
}
