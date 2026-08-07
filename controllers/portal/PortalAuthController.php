<?php
// controllers/portal/PortalAuthController.php

require_once __DIR__ . '/PortalControllerBase.php';
require_once __DIR__ . '/../../models/IntentoLoginModel.php';
require_once __DIR__ . '/../../helpers/Seguridad.php';

/**
 * Autenticación y cuenta del Portal de Clientes: login (tradicional y
 * Google OAuth), registro, recuperación de contraseña, perfil y las
 * pantallas de completar datos de cuentas sociales.
 */
class PortalAuthController extends PortalControllerBase {

    /**
     * Controla el inicio de sesión del portal.
     */
    public function login(): void {
        $this->startSession();

        if (isset($_SESSION['cliente_id'])) {
            header('Location: dashboard.php');
            exit;
        }

        // Build Google OAuth URL
        $google_client_id = get_env('GOOGLE_CLIENT_ID');
        $google_state     = bin2hex(random_bytes(16));
        $_SESSION['google_state'] = $google_state;
        $google_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $google_client_id,
            'redirect_uri'  => APP_URL . '/portal/google_callback.php',
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $google_state,
            'prompt'        => 'select_account',
        ]);

        $error = '';

        // Map callback errors to user-friendly messages
        $callback_errors = [
            'google_cancelado'  => 'Cancelaste el inicio de sesión con Google.',
            'google_token'      => 'No se pudo conectar con Google. Intenta de nuevo.',
            'google_perfil'     => 'No fue posible obtener tu perfil de Google.',
            'google_registro'   => 'Hubo un problema al registrar tu cuenta. Intenta de nuevo.',
            'google_conflicto'  => 'Ese correo ya está vinculado a otra cuenta de Google. Inicia sesión con esa cuenta o contacta al administrador.',
        ];
        if (isset($_GET['error']) && isset($callback_errors[$_GET['error']])) {
            $error = $callback_errors[$_GET['error']];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                $usuario = trim($_POST['usuario'] ?? '');
                $contrasena = $_POST['contrasena'] ?? '';
                $ip = ip_cliente();
                $intentos = new IntentoLoginModel($this->pdo);

                if ($usuario && $contrasena && $intentos->estaBloqueado(IntentoLoginModel::AMBITO_PORTAL, $usuario, $ip)) {
                    // Mismo mensaje se acierte o no la contraseña: si distinguiera,
                    // el bloqueo revelaría qué nombres de usuario existen.
                    $error = 'Demasiados intentos fallidos. Espera '
                        . Seguridad::LOGIN_VENTANA_MINUTOS . ' minutos e intenta de nuevo.';
                } elseif ($usuario && $contrasena) {
                    $cliente = $this->model->getClienteByUsuario($usuario);

                    if ($cliente && password_verify($contrasena, $cliente['contrasena_hash'])) {
                        $intentos->limpiar(IntentoLoginModel::AMBITO_PORTAL, $usuario);
                        $_SESSION['cliente_id'] = $cliente['id_cliente'];
                        $_SESSION['cliente_nombre'] = $cliente['nombre'];
                        // Cuentas de portal antiguas sin correo: pedirlo antes de continuar,
                        // para que Google pueda enlazarlas luego y no crear un duplicado.
                        if (empty($cliente['email'])) {
                            $_SESSION['falta_email'] = true;
                            header('Location: completar_email.php');
                        } else {
                            unset($_SESSION['falta_email']);
                            header('Location: dashboard.php');
                        }
                        exit;
                    } else {
                        $intentos->registrarFallo(IntentoLoginModel::AMBITO_PORTAL, $usuario, $ip);
                        $error = 'Usuario o contraseña incorrectos.';
                    }
                } else {
                    $error = 'Completa todos los campos.';
                }
            }
        }

        require_once __DIR__ . '/../../views/portal/login.php';
    }

    /**
     * Intercambia el código de Google y maneja la sesión.
     */
    public function googleCallback(): void {
        $this->startSession();

        if (isset($_SESSION['cliente_id'])) {
            header('Location: dashboard.php');
            exit;
        }

        $client_id     = get_env('GOOGLE_CLIENT_ID');
        $client_secret = get_env('GOOGLE_CLIENT_SECRET');
        $redirect_uri  = APP_URL . '/portal/google_callback.php';

        $code  = $_GET['code']  ?? '';
        $state = $_GET['state'] ?? '';
        $error = $_GET['error'] ?? '';

        if ($error || !$code || !$state
            || !isset($_SESSION['google_state'])
            || !hash_equals($_SESSION['google_state'], $state)
        ) {
            unset($_SESSION['google_state']);
            header('Location: index.php?error=google_cancelado');
            exit;
        }
        unset($_SESSION['google_state']);

        // Exchange authorization code for access token
        $token_payload = http_build_query([
            'code'          => $code,
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $redirect_uri,
            'grant_type'    => 'authorization_code',
        ]);

        $token_ctx = stream_context_create(['http' => [
            'method'        => 'POST',
            'header'        => "Content-Type: application/x-www-form-urlencoded\r\nContent-Length: " . strlen($token_payload) . "\r\n",
            'content'       => $token_payload,
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);

        $token_resp = @file_get_contents('https://oauth2.googleapis.com/token', false, $token_ctx);
        $token      = json_decode($token_resp ?: '{}', true);

        if (empty($token['access_token'])) {
            header('Location: index.php?error=google_token');
            exit;
        }

        // Get user profile from Google
        $userinfo_ctx = stream_context_create(['http' => [
            'header'        => "Authorization: Bearer {$token['access_token']}\r\n",
            'ignore_errors' => true,
            'timeout'       => 10,
        ]]);
        $user_resp = @file_get_contents('https://www.googleapis.com/oauth2/v3/userinfo', false, $userinfo_ctx);
        $guser     = json_decode($user_resp ?: '{}', true);

        if (empty($guser['sub'])) {
            header('Location: index.php?error=google_perfil');
            exit;
        }

        $google_id = $guser['sub'];
        $email     = isset($guser['email']) && ($guser['email_verified'] ?? false) ? $guser['email'] : '';
        $nombre    = $guser['name']    ?? ($email ?: 'Cliente Google');
        $foto_url  = $guser['picture'] ?? '';

        // 1. Try to find by google_id
        $cliente = $this->model->getClienteByGoogleId($google_id);

        // 2. Buscar por email y enlazar el google_id a la cuenta existente (esto es lo
        //    que evita el duplicado ahora que el registro tradicional sí guarda email;
        //    la comparación de email es case-insensitive por la collation de la columna).
        if (!$cliente && $email) {
            $cliente = $this->model->getClienteByEmail($email);

            if ($cliente) {
                // Caso borde: el correo ya pertenece a una cuenta enlazada a OTRO Google.
                // No se reasigna (sería secuestrar la cuenta): se rechaza con mensaje claro.
                if (!empty($cliente['google_id']) && $cliente['google_id'] !== $google_id) {
                    header('Location: index.php?error=google_conflicto');
                    exit;
                }
                $this->model->vincularGoogleId($cliente['id_cliente'], $google_id, $foto_url);
                $cliente['google_id'] = $google_id;
                $cliente['foto_url']  = $foto_url;
            }
        }

        // 3. Auto-register new client via Google
        $es_nuevo = false;
        if (!$cliente) {
            $new_id = $this->model->registrarClienteGoogle($google_id, $email ?: null, $nombre, $foto_url);
            $cliente = $this->model->getClienteById($new_id);
            $es_nuevo = true;
        }

        if (!$cliente) {
            header('Location: index.php?error=google_registro');
            exit;
        }

        $_SESSION['cliente_id']     = $cliente['id_cliente'];
        $_SESSION['cliente_nombre'] = $cliente['nombre'];
        $_SESSION['cliente_foto']   = $cliente['foto_url'] ?? '';

        if ($es_nuevo) {
            header('Location: completar_perfil.php');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }

    /**
     * Registro de nuevo cliente.
     */
    public function registro(): void {
        $this->startSession();

        if (isset($_SESSION['cliente_id'])) {
            header('Location: dashboard.php');
            exit;
        }

        // Build Google OAuth URL
        $google_client_id = get_env('GOOGLE_CLIENT_ID');
        $google_state     = bin2hex(random_bytes(16));
        $_SESSION['google_state'] = $google_state;
        $google_url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id'     => $google_client_id,
            'redirect_uri'  => APP_URL . '/portal/google_callback.php',
            'response_type' => 'code',
            'scope'         => 'openid email profile',
            'state'         => $google_state,
            'prompt'        => 'select_account',
        ]);

        $error = '';
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                $nombre = trim($_POST['nombre'] ?? '');
                $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
                if (strlen($telefono) > 15) {
                    $telefono = substr($telefono, 0, 15);
                }
                $tipo = 'mostrador';
                $email = trim($_POST['email'] ?? '');
                $usuario = trim(post_texto('usuario'));
                $contrasena = post_texto('contrasena');
                // El vínculo aprendiz-instructor ya NO es manual: se hace canjeando un
                // código del instructor (campo opcional). Ver canjearCodigoAprendiz.
                $codigo_canje = strtoupper(trim($_POST['codigo_aprendiz'] ?? ''));

                if ($nombre && $usuario && $contrasena && $email) {
                    if (!preg_match('/^[a-z0-9_]+$/', $usuario)) {
                        $error = 'El nombre de usuario solo puede contener letras minúsculas, números y guiones bajos.';
                    } elseif (strlen($usuario) > 50) {
                        $error = 'El nombre de usuario no puede superar los 50 caracteres.';
                    } elseif (mb_strlen($nombre) > 100) {
                        $error = 'El nombre de tienda o persona no puede superar los 100 caracteres.';
                    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $error = 'El correo electrónico no es válido.';
                    } elseif (mb_strlen($email) > 150) {
                        $error = 'El correo electrónico es demasiado largo.';
                    } elseif (($fallo = Seguridad::validarContrasena($contrasena)) !== null) {
                        $error = $fallo;
                    } elseif ($this->model->getClienteByUsuario($usuario)) {
                        $error = 'El nombre de usuario ya está en uso. Elige otro.';
                    } elseif ($this->model->emailRegistrado($email)) {
                        $error = 'Ese correo ya está registrado. Inicia sesión o usa otro correo.';
                    } else {
                        $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                        try {
                            $new_id = $this->model->registrarCliente($nombre, $tipo, $telefono, $email, $usuario, $hash, 0, null);
                            if ($new_id > 0) {
                                $success = 'Registro exitoso. Ya puedes iniciar sesión y hacer pedidos.';
                                // Canje opcional del código de aprendiz (único punto).
                                if ($codigo_canje !== '') {
                                    $r = $this->intentarCanjeCodigo($new_id, $codigo_canje);
                                    if ($r['ok']) {
                                        $success = 'Registro exitoso. Quedaste vinculado como aprendiz de '
                                            . htmlspecialchars($r['instructor']) . '. Ya puedes iniciar sesión.';
                                    } else {
                                        $success = 'Tu cuenta se creó, pero el código no se aplicó: '
                                            . htmlspecialchars($r['error'])
                                            . ' Podrás canjearlo luego desde tu perfil.';
                                    }
                                }
                            } else {
                                $error = 'Error al registrar. Verifica los datos.';
                            }
                        } catch (Exception $e) {
                            $error = 'Error al registrar. Verifica los datos.';
                        }
                    }
                } else {
                    $error = 'Completa los campos obligatorios.';
                }
            }
        }

        require_once __DIR__ . '/../../views/portal/registro.php';
    }

    /**
     * Lógica multietapa para la recuperación de contraseña.
     */
    public function recuperarPass(): void {
        $this->startSession();

        if (isset($_SESSION['cliente_id'])) {
            header('Location: dashboard.php');
            exit;
        }

        require_once __DIR__ . '/../../includes/mailer.php';

        if (isset($_GET['reiniciar'])) {
            unset($_SESSION['recover_cid'], $_SESSION['recover_cnombre'], $_SESSION['recover_cemail'],
                  $_SESSION['recover_metodo'], $_SESSION['recover_pin_ok']);
            header('Location: recuperar_pass.php');
            exit;
        }

        $error = '';
        $ok    = '';
        $usuario_input = '';

        $paso = 1;
        if (isset($_SESSION['recover_pin_ok']))  $paso = 3;
        elseif (isset($_SESSION['recover_cid'])) $paso = 2;

        $metodo = $_SESSION['recover_metodo'] ?? 'pin';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                // ── PASO 1: identificar usuario y elegir método
                if (isset($_POST['verificar_usuario'])) {
                    $usuario_input = trim($_POST['usuario'] ?? '');
                    $metodo_sel    = $_POST['metodo'] ?? 'pin';

                    if (!$usuario_input) {
                        $error = 'Ingresa tu nombre de usuario.';
                    } else {
                        $cliente = $this->model->getClienteByUsuario($usuario_input);

                        if (!$cliente) {
                            $error = 'Usuario no encontrado.';
                        } elseif ($metodo_sel === 'email') {
                            if (empty($cliente['email'])) {
                                $error = 'Tu cuenta no tiene correo registrado. Usa el método PIN o contacta al administrador.';
                            } else {
                                $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                                $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                                // Se guarda hasheado; el código en claro solo viaja al correo.
                                $this->model->registrarCodigoRecuperacion(
                                    $cliente['id_cliente'],
                                    Seguridad::hashCodigoRecuperacion($codigo),
                                    $expira
                                );

                                $html    = correo_codigo_html($cliente['nombre'], $codigo, 'Solicitaste recuperar tu contraseña en el portal BreadControl. Tu código es:');
                                $enviado = enviar_correo($cliente['email'], $cliente['nombre'], 'BreadControl — Código de recuperación', $html);

                                if ($enviado) {
                                    $_SESSION['recover_cid']     = $cliente['id_cliente'];
                                    $_SESSION['recover_cnombre'] = $cliente['nombre'];
                                    $_SESSION['recover_cemail']  = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $cliente['email']);
                                    $_SESSION['recover_metodo']  = 'email';
                                    $paso   = 2;
                                    $metodo = 'email';
                                } else {
                                    $error = 'No se pudo enviar el correo. Intenta con el método PIN.';
                                }
                            }
                        } else {
                            if (empty($cliente['pin_recuperacion'])) {
                                $error = 'Tu cuenta no tiene PIN configurado. Usa el método correo o contacta al administrador.';
                            } else {
                                $_SESSION['recover_cid']     = $cliente['id_cliente'];
                                $_SESSION['recover_cnombre'] = $cliente['nombre'];
                                $_SESSION['recover_metodo']  = 'pin';
                                $paso   = 2;
                                $metodo = 'pin';
                            }
                        }
                    }
                }
                // ── PASO 2: verificar código / PIN
                elseif (isset($_POST['verificar_codigo'])) {
                    $codigo = trim($_POST['codigo'] ?? '');
                    $cid    = $_SESSION['recover_cid']    ?? 0;
                    $metodo = $_SESSION['recover_metodo'] ?? '';

                    if (!$cid || !preg_match('/^\d{6}$/', $codigo)) {
                        $error = 'Ingresa el código de 6 dígitos.';
                        $paso  = 2;
                    } elseif ($metodo === 'email') {
                        $cliente = $this->model->getClienteById($cid);
                        if (!$cliente || !Seguridad::verificarCodigoRecuperacion($codigo, $cliente['codigo_recuperacion'])) {
                            $error = 'Código incorrecto.';
                            $paso  = 2;
                        } elseif (strtotime($cliente['codigo_expira']) < time()) {
                            $error = 'El código expiró. Vuelve a empezar.';
                            $paso  = 1;
                            unset($_SESSION['recover_cid'], $_SESSION['recover_cnombre'], $_SESSION['recover_metodo']);
                        } else {
                            $this->model->limpiarCodigoRecuperacion($cid);
                            $_SESSION['recover_pin_ok'] = true;
                            $paso = 3;
                        }
                    } else {
                        $cliente = $this->model->getClienteById($cid);
                        $hash = $cliente['pin_recuperacion'] ?? '';
                        if ($hash && password_verify($codigo, $hash)) {
                            $_SESSION['recover_pin_ok'] = true;
                            $paso = 3;
                        } else {
                            $error = 'PIN incorrecto.';
                            $paso  = 2;
                        }
                    }
                }
                // ── PASO 3: nueva contraseña
                elseif (isset($_POST['cambiar_pass'])) {
                    $nueva   = post_texto('nueva');
                    $confirm = post_texto('confirm');
                    $cid     = $_SESSION['recover_cid']    ?? 0;
                    $pin_ok  = $_SESSION['recover_pin_ok'] ?? false;

                    if (!$cid || !$pin_ok) {
                        header('Location: recuperar_pass.php?reiniciar=1');
                        exit;
                    } elseif (($fallo = Seguridad::validarContrasena($nueva)) !== null) {
                        $error = $fallo;
                        $paso  = 3;
                    } elseif ($nueva !== $confirm) {
                        $error = 'Las contraseñas no coinciden.';
                        $paso  = 3;
                    } else {
                        $hash = password_hash($nueva, PASSWORD_DEFAULT);
                        $this->model->actualizarPassword($cid, $hash);
                        unset($_SESSION['recover_cid'], $_SESSION['recover_cnombre'], $_SESSION['recover_cemail'],
                              $_SESSION['recover_metodo'], $_SESSION['recover_pin_ok']);
                        $ok   = '¡Contraseña restablecida! Ya puedes iniciar sesión.';
                        $paso = 4;
                    }
                }
            }
        }

        require_once __DIR__ . '/../../views/portal/recuperar_pass.php';
    }

    /**
     * Edición de perfil, contraseña y PIN de recuperación.
     */
    public function perfil(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        
        $msg_ok = '';
        $msg_err = '';

        $cliente = $this->model->getClienteById($cliente_id);
        if (!$cliente) {
            header('Location: logout.php');
            exit;
        }

        // Instructor por id (configuracion.id_cliente_adso), NUNCA por tipo.
        $es_instructor = $this->model->esInstructorCapaz($cliente);

        // El vínculo aprendiz-instructor ya NO se edita a mano aquí: se hace canjeando
        // un código del instructor. Puede canjear cualquiera que no sea el instructor ni
        // ya un aprendiz (no se filtra por 'tipo': todas las cuentas son tipo='tienda').
        $puede_canjear = (!$es_instructor && (int)$cliente['es_aprendiz'] !== 1);
        $mi_instructor_nombre = '';
        if ((int)$cliente['es_aprendiz'] === 1 && !empty($cliente['id_instructor'])) {
            $inst = $this->model->getClienteById((int)$cliente['id_instructor']);
            $mi_instructor_nombre = $inst['nombre'] ?? '';
        }

        // Cuenta creada por Google: no tiene usuario ni contraseña tradicional. La vista
        // muestra "Accede con Google" en vez de un campo usuario vacío, y oculta las
        // tarjetas de contraseña/PIN (no aplican sin contraseña).
        $es_google = empty($cliente['usuario']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_err = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } else {
                if (isset($_POST['actualizar_datos'])) {
                    $nombre = mb_substr(trim($_POST['nombre'] ?? ''), 0, 40);
                    $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
                    $telefono = substr($telefono, 0, 15);

                    if ($nombre) {
                        try {
                            $this->model->actualizarDatosBasicos($cliente_id, $nombre, $telefono);
                            $_SESSION['cliente_nombre'] = $nombre;
                            $msg_ok = 'Datos actualizados correctamente.';
                            $cliente['nombre'] = $nombre;
                            $cliente['telefono'] = $telefono;
                        } catch (Exception $e) {
                            $msg_err = 'Error al actualizar los datos.';
                        }
                    } else {
                        $msg_err = 'El nombre es obligatorio.';
                    }
                } elseif (isset($_POST['canjear_codigo'])) {
                    // Canje unificado (rate-limit + validaciones en intentarCanjeCodigo/modelo).
                    $r = $this->intentarCanjeCodigo($cliente_id, $_POST['codigo_aprendiz'] ?? '');
                    if ($r['ok']) {
                        $msg_ok = '¡Listo! Quedaste vinculado como aprendiz de ' . htmlspecialchars($r['instructor']) . '.';
                        $cliente = $this->model->getClienteById($cliente_id);
                        $puede_canjear = false;
                        $mi_instructor_nombre = $r['instructor'];
                    } else {
                        $msg_err = $r['error'];
                    }
                } elseif (isset($_POST['cambiar_pass'])) {
                $actual  = post_texto('pass_actual');
                $nueva   = post_texto('pass_nueva');
                $confirm = post_texto('pass_confirm');

                if (empty($cliente['contrasena_hash'])) {
                    $msg_err = 'Tu cuenta accede con Google; no tiene contraseña que cambiar.';
                } elseif (password_verify($actual, $cliente['contrasena_hash'])) {
                    if ($nueva === $confirm) {
                        $fallo = Seguridad::validarContrasena($nueva);
                        if ($fallo === null) {
                            $hash = password_hash($nueva, PASSWORD_DEFAULT);
                            $this->model->actualizarPassword($cliente_id, $hash);
                            $msg_ok = 'Contraseña cambiada exitosamente.';
                        } else {
                            $msg_err = $fallo;
                        }
                    } else {
                        $msg_err = 'Las contraseñas nuevas no coinciden.';
                    }
                } else {
                    $msg_err = 'La contraseña actual es incorrecta.';
                }
            } elseif (isset($_POST['guardar_pin'])) {
                $pin = trim($_POST['pin'] ?? '');
                $pass = $_POST['pass_pin'] ?? '';

                if (empty($cliente['contrasena_hash'])) {
                    $msg_err = 'Tu cuenta accede con Google; el PIN de recuperación no aplica.';
                } elseif (password_verify($pass, $cliente['contrasena_hash'])) {
                    if (preg_match('/^\d{6}$/', $pin)) {
                        try {
                            $hash = password_hash($pin, PASSWORD_DEFAULT);
                            $this->model->actualizarPin($cliente_id, $hash);
                            $msg_ok = 'PIN de recuperación actualizado correctamente.';
                            $cliente['pin_recuperacion'] = $hash;
                        } catch (Exception $e) {
                            $msg_err = 'Error al guardar el PIN.';
                        }
                    } else {
                        $msg_err = 'El PIN debe ser de 6 dígitos numéricos.';
                    }
                } else {
                    $msg_err = 'Contraseña incorrecta.';
                }
            }
        }
    }

        require_once __DIR__ . '/../../views/portal/perfil.php';
    }


    /**
     * Completa el perfil social de Google OAuth.
     */
    public function completarPerfil(): void {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        
        $error = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } else {
                $nombre = trim($_POST['nombre'] ?? '');

                if (empty($nombre)) {
                    $error = 'El nombre no puede estar vacío.';
                } else {
                    // Se completa el perfil (nombre). El vínculo aprendiz-instructor se hace
                    // por código: campo opcional aquí para quien entró con Google y no pasó
                    // por registro.php. El estado del cliente recién creado por Google es
                    // es_aprendiz=0.
                    $this->model->completarPerfilCliente($cliente_id, $nombre, 0, null);
                    $_SESSION['cliente_nombre'] = $nombre;

                    // Canje opcional del código. NO bloquea el ingreso si falla: el perfil
                    // queda completo igual y el motivo se muestra como aviso en el tablero.
                    $codigo_canje = strtoupper(trim($_POST['codigo_aprendiz'] ?? ''));
                    if ($codigo_canje !== '') {
                        $r = $this->intentarCanjeCodigo($cliente_id, $codigo_canje);
                        if ($r['ok']) {
                            $_SESSION['flash_ok'] = 'Quedaste vinculado como aprendiz de ' . $r['instructor'] . '.';
                        } else {
                            $_SESSION['flash_err'] = 'Tu perfil quedó listo, pero el código no se aplicó: '
                                . $r['error'] . ' Puedes canjearlo luego desde tu perfil.';
                        }
                    }

                    header('Location: dashboard.php');
                    exit;
                }
            }
        }

        $cliente = $this->model->getClienteById($cliente_id);
        $nombre_actual = $cliente['nombre'] ?? '';
        $foto_url      = $cliente['foto_url'] ?? '';

        require_once __DIR__ . '/../../views/portal/completar_perfil.php';
    }

    /**
     * Pantalla intermedia: pide el correo a las cuentas de portal (usuario + contraseña)
     * que aún no lo tienen, antes de dejarlas continuar. Así Google podrá enlazar la
     * cuenta existente por email y no crear un duplicado. NO usa requireCliente (evita el
     * bucle de redirección con la bandera falta_email).
     */
    public function completarEmail(): void {
        $this->startSession();
        if (!isset($_SESSION['cliente_id'])) {
            header('Location: index.php');
            exit;
        }
        $cliente_id = (int)$_SESSION['cliente_id'];
        $cliente = $this->model->getClienteById($cliente_id);
        if (!$cliente) {
            header('Location: logout.php');
            exit;
        }

        // Si ya tiene correo, no hay nada que pedir: limpiar la bandera y continuar.
        if (!empty($cliente['email'])) {
            unset($_SESSION['falta_email']);
            header('Location: dashboard.php');
            exit;
        }

        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } else {
                $email = trim($_POST['email'] ?? '');
                if ($email === '') {
                    $error = 'Ingresa tu correo electrónico.';
                } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $error = 'El correo electrónico no es válido.';
                } elseif (mb_strlen($email) > 150) {
                    $error = 'El correo electrónico es demasiado largo.';
                } elseif ($this->model->emailRegistrado($email, $cliente_id)) {
                    $error = 'Ese correo ya está registrado en otra cuenta. Usa uno distinto.';
                } else {
                    try {
                        $this->model->actualizarEmail($cliente_id, $email);
                        unset($_SESSION['falta_email']);
                        header('Location: dashboard.php');
                        exit;
                    } catch (Exception $e) {
                        log_error($e);
                        $error = 'No se pudo guardar el correo. Intenta de nuevo.';
                    }
                }
            }
        }

        $nombre_actual = $cliente['nombre'] ?? '';
        require_once __DIR__ . '/../../views/portal/completar_email.php';
    }

}
