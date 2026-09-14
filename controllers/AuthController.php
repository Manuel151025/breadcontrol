<?php
// controllers/AuthController.php

require_once __DIR__ . '/../models/AuthModel.php';
require_once __DIR__ . '/../models/IntentoLoginModel.php';
require_once __DIR__ . '/../helpers/Seguridad.php';
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/funciones.php';

class AuthController {
    private AuthModel $model;
    private IntentoLoginModel $intentos;

    public function __construct(PDO $pdo) {
        $this->model    = new AuthModel($pdo);
        $this->intentos = new IntentoLoginModel($pdo);
    }

    /**
     * Asegura que el usuario no esté ya logueado.
     */
    private function redirectIfLoggedIn(): void {
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
    public function landing(): void {
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
    public function login(): void {
        $this->redirectIfLoggedIn();

        $error = '';
        if (isset($_GET['error']) && $_GET['error'] === 'acceso_denegado') {
            $error = 'Acceso denegado. Solo el propietario puede ingresar.';
        }
        $nombre_saludo = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validar_token_csrf(post_texto('csrf_token'))) {
            // Aquí no se redirige (como en el back-office) sino que se muestra el
            // aviso en la propia pantalla: quien llega al login suele venir de una
            // pestaña vieja cuyo token expiró, y recargar basta.
            $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $usuario = trim($_POST['usuario'] ?? '');
            $clave   = $_POST['clave'] ?? '';

            $ip = ip_cliente();

            if (empty($usuario) || empty($clave)) {
                $error = 'Por favor ingresa tu usuario y contraseña.';
            } elseif ($this->intentos->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $usuario, $ip)) {
                // Mismo mensaje se acierte o no la contraseña: si distinguiera,
                // el bloqueo revelaría qué nombres de usuario existen.
                $error = 'Demasiados intentos fallidos. Espera '
                    . Seguridad::LOGIN_VENTANA_MINUTOS . ' minutos e intenta de nuevo.';
            } else {
                if (iniciarSesion($usuario, $clave)) {
                    $this->intentos->limpiar(IntentoLoginModel::AMBITO_ADMIN, $usuario);
                    header('Location: ' . APP_URL . '/modules/tablero/index.php');
                    exit;
                } else {
                    $this->intentos->registrarFallo(IntentoLoginModel::AMBITO_ADMIN, $usuario, $ip);
                    $error = 'Usuario o contraseña incorrectos.';
                }
            }
        }

        require_once __DIR__ . '/../views/auth/login.php';
    }

    /**
     * Flujo multietapa para la recuperación de contraseña administrativa.
     */
    public function recuperarPin(): void {
        $this->redirectIfLoggedIn();

        $paso  = 1;
        $error = '';
        $ok    = '';
        $usuario_input = '';

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // «Volver» desde el paso 2 manda aquí. Sin esto el botón no hacía nada: la
        // sesión de recuperación seguía viva y la página volvía a abrir el paso 2.
        if (isset($_GET['reiniciar'])) {
            unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_pin_ok'],
                  $_SESSION['recover_metodo'], $_SESSION['recover_email_masked']);
            header('Location: ' . APP_URL . '/recuperar_pin.php');
            exit;
        }

        $metodo = $_SESSION['recover_metodo'] ?? '';
        if (isset($_SESSION['recover_pin_ok']))  $paso = 3;
        elseif (isset($_SESSION['recover_usuario'])) $paso = 2;

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validar_token_csrf(post_texto('csrf_token'))) {
            $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
        } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // PASO 1: Identificación y envío/elección de método
            //
            // NO revela si la cuenta existe ni qué método de recuperación tiene.
            // Antes devolvía tres mensajes distintos —«Usuario no encontrado»,
            // «no tiene correo configurado», «no tiene PIN configurado»— que juntos
            // permitían enumerar cuentas y elegir cuál atacar por PIN. El login ya no
            // enumeraba (punto C05 del informe técnico); la recuperación, que acaba
            // en lo mismo —una contraseña nueva—, sí lo hacía.
            //
            // Ahora siempre se avanza al paso 2 con la misma respuesta. Si la cuenta
            // no existe o no tiene ese método, no hay nada que verificar y el paso 2
            // falla igual que con cualquier código incorrecto.
            if (isset($_POST['verificar_usuario'])) {
                $usuario_input = trim($_POST['usuario'] ?? '');
                $metodo_sel    = ($_POST['metodo'] ?? 'email') === 'pin' ? 'pin' : 'email';

                if ($usuario_input === '') {
                    $error = 'Ingresa tu nombre de usuario.';
                } else {
                    $user = $this->model->getUsuarioPorNombre($usuario_input);
                    $uid  = 0;

                    if ($user && $metodo_sel === 'email' && !empty($user['correo_electronico'])) {
                        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                        $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                        // Se guarda hasheado; el código en claro solo viaja al correo.
                        $this->model->registrarCodigoRecuperacion(
                            $user['id_usuario'],
                            Seguridad::hashCodigoRecuperacion($codigo),
                            $expira
                        );

                        require_once __DIR__ . '/../includes/mailer.php';
                        $to      = $user['correo_electronico'];
                        $nombre  = $user['nombre_completo'];
                        $subject = 'BreadControl — Código de recuperación';
                        $body    = correo_codigo_html($nombre, $codigo, 'Tu código para recuperar el acceso a BreadControl es:');

                        if (enviar_correo($to, $nombre, $subject, $body)) {
                            $uid = is_numeric($user['id_usuario']) ? (int) $user['id_usuario'] : 0;
                        } else {
                            // Un fallo de envío no se le cuenta a quien lo pide:
                            // hacerlo confirmaría que la cuenta existe y tiene correo.
                            // Queda en el registro para quien administra el servidor.
                            log_error('Recuperación de acceso: no se pudo enviar el código por correo.');
                        }
                    } elseif ($user && $metodo_sel === 'pin' && !empty($user['pin_recuperacion'])) {
                        $uid = is_numeric($user['id_usuario']) ? (int) $user['id_usuario'] : 0;
                    }

                    $_SESSION['recover_user_id'] = $uid;
                    $_SESSION['recover_usuario'] = $usuario_input;
                    $_SESSION['recover_metodo']  = $metodo_sel;
                    $paso   = 2;
                    $metodo = $metodo_sel;
                }
            }

            // PASO 2: Verificación de Código de email / PIN
            //
            // CON LÍMITE DE INTENTOS, que antes no existía. El PIN son 6 dígitos —un
            // millón de combinaciones— y un fallo solo devolvía «PIN incorrecto» y
            // dejaba volver a probar, sin tope ni caducidad. Al acertar, el paso 3
            // fija una contraseña nueva: era una vía directa para tomar la cuenta del
            // propietario, la de más privilegios del sistema.
            //
            // Se reutiliza el limitador del login (5 fallos por cuenta cada 15
            // minutos, 20 por IP) con un identificador propio, para que los intentos
            // de recuperación y los de acceso no se sumen entre sí. Vive en base de
            // datos y por cuenta, no en la sesión: descartar la cookie o volver a
            // empezar no reinicia el contador.
            if (isset($_POST['verificar_codigo'])) {
                $codigo        = trim($_POST['codigo'] ?? '');
                $uid           = is_int($_SESSION['recover_user_id'] ?? null) ? $_SESSION['recover_user_id'] : 0;
                $metodo        = ($_SESSION['recover_metodo'] ?? '') === 'pin' ? 'pin' : 'email';
                $usuario_input = is_string($_SESSION['recover_usuario'] ?? null) ? $_SESSION['recover_usuario'] : '';
                $clave_limite  = 'recuperar:' . mb_strtolower($usuario_input);
                $ip            = ip_cliente();

                if ($usuario_input === '') {
                    $error = 'Sesión expirada.<br>Empieza de nuevo.';
                    $paso  = 1;
                } elseif ($this->intentos->estaBloqueado(IntentoLoginModel::AMBITO_ADMIN, $clave_limite, $ip)) {
                    unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_metodo']);
                    $error = 'Demasiados intentos fallidos.<br>Espera ' . Seguridad::LOGIN_VENTANA_MINUTOS . ' minutos y vuelve a empezar.';
                    $paso  = 1;
                } elseif (!preg_match('/^\d{6}$/', $codigo)) {
                    // Un error de formato no cuenta como intento: es un despiste de
                    // tecleo, y quien ataca siempre envía seis dígitos.
                    $error = 'El código debe ser de 6 dígitos.';
                    $paso  = 2;
                } else {
                    $user     = $uid > 0 ? $this->model->getUsuarioPorId($uid) : null;
                    $valido   = false;
                    $expirado = false;

                    if ($user && $metodo === 'email') {
                        $valido   = Seguridad::verificarCodigoRecuperacion($codigo, $user['codigo_recuperacion']);
                        $exp      = $user['codigo_expira'] ?? null;
                        $expirado = $valido && (!is_string($exp) || strtotime($exp) < time());
                    } elseif ($user && $metodo === 'pin') {
                        $hash   = $user['pin_recuperacion'] ?? null;
                        $valido = is_string($hash) && $hash !== '' && password_verify($codigo, $hash);
                    }

                    if ($valido && $expirado) {
                        unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_metodo']);
                        $error = 'El código ha expirado.<br>Vuelve a empezar.';
                        $paso  = 1;
                    } elseif ($valido) {
                        $this->intentos->limpiar(IntentoLoginModel::AMBITO_ADMIN, $clave_limite);
                        if ($metodo === 'email') {
                            $this->model->limpiarCodigoRecuperacion($uid);
                        }
                        $_SESSION['recover_pin_ok'] = true;
                        $paso = 3;
                    } else {
                        $this->intentos->registrarFallo(IntentoLoginModel::AMBITO_ADMIN, $clave_limite, $ip);
                        $error = $metodo === 'pin' ? 'PIN incorrecto.' : 'Código incorrecto.';
                        $paso  = 2;
                    }
                }
            }

            // PASO 3: Ingreso de la nueva contraseña
            if (isset($_POST['cambiar_clave'])) {
                $nueva = post_texto('nueva_clave');
                $conf  = post_texto('confirmar_clave');
                $uid = $_SESSION['recover_user_id'] ?? null;
                $pin_ok = $_SESSION['recover_pin_ok'] ?? false;

                if (!$uid || !$pin_ok) {
                    $error = 'Sesion expirada.';
                    $paso = 1;
                } elseif (($fallo = Seguridad::validarContrasena($nueva)) !== null) {
                    $error = $fallo;
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
    public function logout(): void {
        cerrarSesion();
    }
}
