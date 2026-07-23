<?php
// controllers/PortalClienteController.php

require_once __DIR__ . '/../models/PortalClienteModel.php';
require_once __DIR__ . '/../includes/sesion.php';
require_once __DIR__ . '/../includes/funciones.php';

class PortalClienteController {
    private $model;
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->model = new PortalClienteModel($pdo);
    }

    /**
     * Asegura que el cliente haya iniciado sesión.
     */
    private function requireCliente() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['cliente_id'])) {
            header('Location: index.php');
            exit;
        }
    }

    /**
     * Asegura que la sesión esté iniciada sin redireccionar de inmediato (para login/registro).
     */
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Controla el inicio de sesión del portal.
     */
    public function login() {
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
            'google_cancelado' => 'Cancelaste el inicio de sesión con Google.',
            'google_token'     => 'No se pudo conectar con Google. Intenta de nuevo.',
            'google_perfil'    => 'No fue posible obtener tu perfil de Google.',
            'google_registro'  => 'Hubo un problema al registrar tu cuenta. Intenta de nuevo.',
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

                if ($usuario && $contrasena) {
                    $cliente = $this->model->getClienteByUsuario($usuario);

                    if ($cliente && password_verify($contrasena, $cliente['contrasena_hash'])) {
                        $_SESSION['cliente_id'] = $cliente['id_cliente'];
                        $_SESSION['cliente_nombre'] = $cliente['nombre'];
                        header('Location: dashboard.php');
                        exit;
                    } else {
                        $error = 'Usuario o contraseña incorrectos.';
                    }
                } else {
                    $error = 'Completa todos los campos.';
                }
            }
        }

        require_once __DIR__ . '/../views/portal/login.php';
    }

    /**
     * Intercambia el código de Google y maneja la sesión.
     */
    public function googleCallback() {
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

        // 2. Try to find by email and link google_id
        if (!$cliente && $email) {
            $cliente = $this->model->getClienteByEmail($email);

            if ($cliente) {
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
    public function registro() {
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
                $usuario = trim($_POST['usuario'] ?? '');
                $contrasena = $_POST['contrasena'] ?? '';
                // El vínculo aprendiz-instructor ya NO es manual: se hace canjeando un
                // código del instructor (campo opcional). Ver canjearCodigoAprendiz.
                $codigo_canje = strtoupper(trim($_POST['codigo_aprendiz'] ?? ''));

                if ($nombre && $usuario && $contrasena) {
                    if (!preg_match('/^[a-z0-9_]+$/', $usuario)) {
                        $error = 'El nombre de usuario solo puede contener letras minúsculas, números y guiones bajos.';
                    } elseif (strlen($usuario) > 50) {
                        $error = 'El nombre de usuario no puede superar los 50 caracteres.';
                    } elseif (mb_strlen($nombre) > 100) {
                        $error = 'El nombre de tienda o persona no puede superar los 100 caracteres.';
                    } elseif (strlen($contrasena) < 4) {
                        $error = 'La contraseña debe tener al menos 4 caracteres.';
                    } else {
                        $existente = $this->model->getClienteByUsuario($usuario);
                        if ($existente) {
                            $error = 'El nombre de usuario ya está en uso. Elige otro.';
                        } else {
                            $hash = password_hash($contrasena, PASSWORD_DEFAULT);
                            try {
                                $new_id = $this->model->registrarCliente($nombre, $tipo, $telefono, $usuario, $hash, 0, null);
                                if ($new_id > 0) {
                                    $success = 'Registro exitoso. Ya puedes iniciar sesión y hacer pedidos.';
                                    // Canje opcional del código de aprendiz.
                                    if ($codigo_canje !== '') {
                                        $r = $this->model->canjearCodigoAprendiz($new_id, $codigo_canje);
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
                    }
                } else {
                    $error = 'Completa los campos obligatorios.';
                }
            }
        }

        require_once __DIR__ . '/../views/portal/registro.php';
    }

    /**
     * Lógica multietapa para la recuperación de contraseña.
     */
    public function recuperarPass() {
        $this->startSession();

        if (isset($_SESSION['cliente_id'])) {
            header('Location: dashboard.php');
            exit;
        }

        require_once __DIR__ . '/../includes/mailer.php';

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
                                $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                                $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
                                $this->model->registrarCodigoRecuperacion($cliente['id_cliente'], $codigo, $expira);

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
                        if (!$cliente || $cliente['codigo_recuperacion'] !== $codigo) {
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
                    $nueva   = $_POST['nueva']   ?? '';
                    $confirm = $_POST['confirm'] ?? '';
                    $cid     = $_SESSION['recover_cid']    ?? 0;
                    $pin_ok  = $_SESSION['recover_pin_ok'] ?? false;

                    if (!$cid || !$pin_ok) {
                        header('Location: recuperar_pass.php?reiniciar=1');
                        exit;
                    } elseif (strlen($nueva) < 6) {
                        $error = 'Mínimo 6 caracteres.';
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

        require_once __DIR__ . '/../views/portal/recuperar_pass.php';
    }

    /**
     * Carga el Dashboard del portal.
     */
    public function dashboard() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        $es_tienda = ($cliente_info['tipo'] === 'tienda');

        // Detectar si es instructor ADSO
        $es_instructor = false;
        $resumen_fin   = [];
        $aprendices    = [];
        $total_reg     = 0;

        if ($es_tienda) {
            $aprendices_resumen = $this->model->getAprendicesResumen($cliente_id);
            $stats_inst = $this->model->getInstructorStats($cliente_id);
            $es_instructor = ($stats_inst['total_pedidos'] > 0 || $this->model->contarAprendices($cliente_id) > 0);
            
            if ($es_instructor) {
                $resumen_fin = $stats_inst;
                $aprendices = $aprendices_resumen;
                $total_reg = $this->model->contarAprendices($cliente_id, true);
            }
        }

        $success_msg = '';
        $error_msg = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error_msg = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } elseif ($es_instructor) {
                if (isset($_POST['aprobar_aprendiz_id']) || isset($_POST['aprobar_lote_ids'])) {
                    $ids = [];
                    if (isset($_POST['aprobar_lote_ids'])) {
                        $ids = array_values(array_filter(array_map('intval', $_POST['aprobar_lote_ids']), fn($v) => $v > 0));
                    } elseif (isset($_POST['aprobar_aprendiz_id'])) {
                        $ids = [(int)$_POST['aprobar_aprendiz_id']];
                    }

                    $fecha_entrega = $_POST['fecha_entrega'] ?? '';
                    $hora_entrega = $_POST['hora_entrega'] ?? '';
                    
                    $datetime_entrega = $fecha_entrega . ' ' . $hora_entrega . ':00';
                    $ahora_str = date('Y-m-d H:i:s');
                    
                    if (empty($ids)) {
                        $error_msg = 'Debes seleccionar al menos un pedido para aprobar.';
                    } elseif (empty($fecha_entrega) || empty($hora_entrega)) {
                        $error_msg = 'Debes seleccionar una fecha y hora de entrega.';
                    } elseif ($hora_entrega < '07:00' || $hora_entrega > '20:00') {
                        $error_msg = 'El horario de entrega de la panadería es de 7:00 AM a 8:00 PM.';
                    } elseif ($fecha_entrega < date('Y-m-d')) {
                        $error_msg = 'La fecha de entrega no puede ser en el pasado.';
                    } elseif ($datetime_entrega <= $ahora_str) {
                        $error_msg = 'La fecha y hora de entrega no pueden ser en el pasado.';
                    } else {
                        try {
                            $n = $this->model->aprobarPedidosInstructorLote($ids, $cliente_id, $datetime_entrega);
                            $success_msg = $n > 1 
                                ? "$n pedidos aprobados y programados con éxito." 
                                : "Pedido aprobado y programado con éxito.";
                            if ($es_tienda) {
                                $resumen_fin = $this->model->getInstructorStats($cliente_id);
                                $aprendices = $this->model->getAprendicesResumen($cliente_id);
                            }
                        } catch (Exception $e) {
                            $error_msg = $e->getMessage();
                        }
                    }
                } elseif (isset($_POST['rechazar_aprendiz_id']) || isset($_POST['rechazar_lote_ids'])) {
                    $ids = [];
                    if (isset($_POST['rechazar_lote_ids'])) {
                        $ids = array_values(array_filter(array_map('intval', $_POST['rechazar_lote_ids']), fn($v) => $v > 0));
                    } elseif (isset($_POST['rechazar_aprendiz_id'])) {
                        $ids = [(int)$_POST['rechazar_aprendiz_id']];
                    }
                    
                    if (empty($ids)) {
                        $error_msg = 'Debes seleccionar al menos un pedido para rechazar.';
                    } else {
                        try {
                            $n = $this->model->rechazarPedidosInstructorLote($ids, $cliente_id);
                            $success_msg = $n > 1 
                                ? "$n pedidos rechazados con éxito." 
                                : "Pedido rechazado con éxito.";
                            if ($es_tienda) {
                                $resumen_fin = $this->model->getInstructorStats($cliente_id);
                                $aprendices = $this->model->getAprendicesResumen($cliente_id);
                            }
                        } catch (Exception $e) {
                            $error_msg = $e->getMessage();
                        }
                    }
                } elseif (isset($_POST['actualizar_cupo_aprendiz_id'])) {
                    $id_apr = (int)$_POST['actualizar_cupo_aprendiz_id'];
                    $nuevo_cupo = (float)($_POST['cupo_semanal'] ?? 0);
                    try {
                        // Validar que el aprendiz pertenezca a este instructor
                        $stmt_v = $this->pdo->prepare("SELECT COUNT(*) FROM cliente WHERE id_cliente = ? AND id_instructor = ? AND es_aprendiz = 1");
                    $stmt_v->execute([$id_apr, $cliente_id]);
                    if ((int)$stmt_v->fetchColumn() === 0) {
                        throw new Exception("El aprendiz no pertenece a tu grupo.");
                    }
                    
                    if ($nuevo_cupo < 0 || $nuevo_cupo > 100000) {
                        throw new Exception("El cupo semanal debe estar entre $0 y $100.000 COP.");
                    }
                    $nuevo_cupo_int = (int)$nuevo_cupo;
                    if ($nuevo_cupo_int % 500 !== 0 || $nuevo_cupo != $nuevo_cupo_int) {
                        throw new Exception("El cupo semanal debe ser múltiplo de $500 COP.");
                    }
                    
                    $stmt_u = $this->pdo->prepare("UPDATE cliente SET cupo_semanal = ? WHERE id_cliente = ?");
                    $stmt_u->execute([$nuevo_cupo, $id_apr]);
                    $success_msg = "Cupo semanal del aprendiz actualizado con éxito.";
                    
                    // Recargar datos
                    if ($es_tienda) {
                        $resumen_fin = $this->model->getInstructorStats($cliente_id);
                        $aprendices = $this->model->getAprendicesResumen($cliente_id);
                    }
                } catch (Exception $e) {
                    $error_msg = $e->getMessage();
                }
            }
        }
    }

        // Datos Nequi / Wompi para el instructor
        $nequi_config = [];
        $pedidos_pago_instructor = [];
        $pedidos_por_aprobar = [];
        if ($es_instructor) {
            $nequi_config = $this->model->getConfiguracionPago();
            if ($resumen_fin['pendiente_total'] > 0) {
                $pedidos_pago_instructor = $this->model->getPedidosPagoInstructor($cliente_id);
            }
            $pedidos_por_aprobar = $this->model->getPedidosPendientesAprobacionInstructor($cliente_id);
        }

        $variedades = $this->model->getVariedadesPanActivas();

        // Filtros
        $f_estado   = trim($_GET['estado'] ?? '');
        $f_orden    = trim($_GET['orden'] ?? 'recientes');
        $f_aprendiz = (int)($_GET['aprendiz_id'] ?? 0);
        $f_variedad = (int)($_GET['variedad_id'] ?? 0);

        $nombre_variedad = '';
        if ($f_variedad) {
            foreach ($variedades as $v) {
                if ($v['id_variedad'] === $f_variedad) { 
                    $nombre_variedad = $v['nombre']; 
                    break; 
                }
            }
        }

        $filtros = [
            'estado' => $f_estado,
            'orden' => $f_orden,
            'aprendiz_id' => $f_aprendiz,
            'variedad_id' => $f_variedad
        ];

        $nombre_filtro = '';
        if ($f_aprendiz && $es_instructor) {
            $aprendiz_fil = $this->model->getClienteById($f_aprendiz);
            $nombre_filtro = $aprendiz_fil ? $aprendiz_fil['nombre'] : 'Aprendiz';
        }

        $mis_pedidos = $this->model->getPedidosFiltrados($cliente_id, $es_instructor, $filtros);
        $saldo_pendiente = $this->model->getSaldoPendiente($cliente_id);

        require_once __DIR__ . '/../views/portal/dashboard.php';
    }

    /**
     * Muestra la vista detallada de un pedido específico.
     */
    public function detallePedido() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        $id_pedido  = (int)($_GET['id'] ?? 0);

        $cliente_info = $this->model->getClienteById($cliente_id);
        $es_aprendiz = $cliente_info ? ((int)$cliente_info['es_aprendiz'] === 1) : false;

        $es_tienda_logueada = $cliente_info ? ($cliente_info['tipo'] === 'tienda') : false;
        $es_instructor = false;
        if ($es_tienda_logueada) {
            $stats_inst = $this->model->getInstructorStats($cliente_id);
            $es_instructor = ($stats_inst['total_pedidos'] > 0 || $this->model->contarAprendices($cliente_id) > 0);
        }

        $pedido = $this->model->getPedido($id_pedido, $cliente_id);
        if (!$pedido) {
            header('Location: dashboard.php');
            exit;
        }

        // Regla unica de pago (D2): solo puede pagar quien figura como id_cliente del
        // pedido (destinatario/facturado). El id_creador puede VER el pedido pero nunca pagarlo.
        $puede_pagar = ((int)$pedido['id_cliente'] === $cliente_id);

        $detalles = $this->model->getDetallesPedido($id_pedido);

        $fecha_entrega = new DateTime($pedido['fecha_entrega']);
        $ahora = new DateTime();
        $diff = $ahora->diff($fecha_entrega);
        $horas_restantes = ($diff->days * 24) + $diff->h;
        $esta_vencido = $diff->invert == 1;
        $dentro_limite = (!$esta_vencido && $horas_restantes < 48);
        $puede_gestionar = ($pedido['estado'] === 'pendiente' && !$esta_vencido && !$dentro_limite);

        // Pago digital
        $estado_pago = $pedido['estado_pago'] ?? 'no_aplica';
        $pago_activo = null;
        $abonos = [];
        $total_pagado = 0.0;
        if (!empty($pedido['id_pago_activo'])) {
            $pago_activo = $this->model->getPagoPendientePorId($pedido['id_pago_activo']);
            if ($pago_activo) {
                $abonos = $this->model->getAbonos($pedido['id_pago_activo']);
                foreach ($abonos as $ab) {
                    $total_pagado += (float)$ab['monto'];
                }
            }
        }

        $metodos_legibles = [
            'NEQUI' => 'Nequi', 'BANCOLOMBIA' => 'Bancolombia',
            'PSE' => 'PSE', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro medio',
        ];

        // Configuración de la tienda
        $cfg = $this->model->getConfiguracionPago();
        $titular_negocio = $cfg['nequi_titular'] ?? '';
        $nequi_link_pago = $cfg['nequi_link_pago'] ?? '';

        // Detalle por tienda
        $row_tipo = $this->model->getClienteTipoAsociadoPedido($id_pedido);
        $orden_es_de_tienda = ($row_tipo && $row_tipo['tipo'] === 'tienda');
        $es_tienda          = $orden_es_de_tienda && ($pedido['id_cliente'] === $cliente_id);
        $nombre_tienda      = $row_tipo['nombre'] ?? '';

        $todos_confirmados  = false;
        $pendientes_count   = 0;
        if ($es_tienda) {
            $pendientes_count  = $this->model->getCountPedidosPendientesTiendaFecha($pedido['id_cliente'], $pedido['fecha_entrega']);
            $todos_confirmados = ($pendientes_count === 0);
        }

        // Reporte por aprendiz
        $reporte_por_aprendiz = [];
        $total_general_reporte = 0.0;
        if ($es_tienda) {
            $reporte_por_aprendiz = $this->model->getReporteAgrupadoTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
            $total_general_reporte = $this->model->getTotalGeneralReporteTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
        }

        require_once __DIR__ . '/../views/portal/detalle_pedido.php';
    }

    /**
     * Creación y edición de pedidos.
     */
    public function nuevoPedido() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $min_fecha = date('Y-m-d');
        if ((int)date('H') >= 20) {
            $min_fecha = date('Y-m-d', strtotime('+1 day'));
        }

        // Obtener info del cliente (saber si es tienda, mostrador o aprendiz)
        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        $es_tienda = ($cliente_info['tipo'] === 'tienda');
        $es_aprendiz = (int)$cliente_info['es_aprendiz'] === 1;

        $es_instructor = false;
        if ($es_tienda) {
            $stats_inst = $this->model->getInstructorStats($cliente_id);
            $es_instructor = ($stats_inst['total_pedidos'] > 0 || $this->model->contarAprendices($cliente_id) > 0);
        }

        // ══ AJAX: TODAS las variedades (para bonificación) ══
        if (isset($_GET['ajax_all_variedades'])) {
            header('Content-Type: application/json');
            try {
                $all = $this->model->getProductosActivos();
                echo json_encode($all);
            } catch (Exception $e) { 
                echo json_encode([]); 
            }
            exit;
        }

        // ══ AJAX: variedades por categoría ══
        if (isset($_GET['ajax_variedades'])) {
            header('Content-Type: application/json');
            try {
                $id_cat = (int)$_GET['id_cat'];
                $vars = $this->model->getVariedadesPorCategoria($id_cat);
                echo json_encode($vars);
            } catch (Exception $e) {
                echo json_encode([]);
            }
            exit;
        }

        $error = '';
        $success = '';

        // ══ POST — Guardar o Editar Pedido ══
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pedido'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } else {
                $fecha_entrega = $_POST['fecha_entrega'] ?? '';
                $hora_entrega = $_POST['hora_entrega'] ?? '';
                $cart_json = $_POST['carrito_json'] ?? '[]';
                $bonif_json = $_POST['bonif_json'] ?? '[]';
                $edit_id = (int)($_POST['edit_id'] ?? 0);
                
                $cart = json_decode($cart_json, true);
                $bonif_items = json_decode($bonif_json, true);

                $pedido_para = $_POST['pedido_para'] ?? 'adso';
                $es_adso = ($es_aprendiz && $pedido_para === 'adso');

                if ($es_adso) {
                    $datetime_entrega = '1000-01-01 00:00:00';
                } else {
                    $datetime_entrega = $fecha_entrega . ' ' . $hora_entrega . ':00';
                }
                $ahora_str = date('Y-m-d H:i:s');

                if (!$es_adso && (empty($fecha_entrega) || empty($hora_entrega))) {
                    $error = 'Debes seleccionar una fecha y hora de entrega.';
                } elseif (!$es_adso && ($hora_entrega < '07:00' || $hora_entrega > '20:00')) {
                    $error = 'El horario de entrega de la panadería es de 7:00 AM a 8:00 PM.';
                } elseif (!$es_adso && $fecha_entrega < $min_fecha && $edit_id == 0) {
                    if ($min_fecha > date('Y-m-d')) {
                        $error = 'Por la hora actual, la fecha de entrega debe ser a partir de mañana (' . date('d/m/Y', strtotime($min_fecha)) . ').';
                    } else {
                        $error = 'La fecha de entrega no puede ser en el pasado.';
                    }
                } elseif (!$es_adso && $datetime_entrega <= $ahora_str && $edit_id == 0) {
                    $error = 'La fecha y hora de entrega no pueden ser en el pasado.';
                } elseif (!$es_adso && $fecha_entrega > date('Y-m-d', strtotime('+3 months'))) {
                    $error = 'La fecha de entrega no puede ser mayor a 3 meses.';
                } elseif (empty($cart)) {
                    $error = 'El carrito está vacío. Debes pedir al menos un producto.';
                } else {
                    // Determinar el cliente destino
                    $id_cli_destino = $cliente_id;
                    $pedido_para = $_POST['pedido_para'] ?? 'adso';
                    if ($es_aprendiz && $pedido_para === 'adso') {
                        if (!empty($cliente_info['id_instructor'])) {
                            $id_cli_destino = (int)$cliente_info['id_instructor'];
                        } else {
                            // Tienda ADSO Fallback
                            $stmt_tienda_adso = $this->pdo->prepare("SELECT id_cliente FROM cliente WHERE nombre LIKE '%Tienda ADSO%' AND tipo='tienda' LIMIT 1");
                            $stmt_tienda_adso->execute();
                            $id_tienda_adso = $stmt_tienda_adso->fetchColumn();
                            if ($id_tienda_adso) {
                                $id_cli_destino = (int)$id_tienda_adso;
                            }
                        }
                    }

                    try {
                        $id_ped_creado = $this->model->crearPedido($id_cli_destino, $cliente_id, $datetime_entrega, $cart, $bonif_items, $edit_id > 0 ? $edit_id : null);
                        $success = $edit_id > 0 ? "Pedido actualizado exitosamente." : "Pedido enviado exitosamente a la panadería.";
                    } catch (Exception $e) {
                        $error = 'Hubo un error al procesar tu pedido: ' . $e->getMessage();
                    }
                }
            }
        }

        // Preload para edición
        $edit_id = (int)($_GET['edit_id'] ?? 0);
        $ped_edit = null;
        $edit_fecha = '';
        $edit_hora = '';
        $cart_preload = [];
        $bonif_preload = [];

        if ($edit_id > 0) {
            $ped_edit = $this->model->getPedido($edit_id, $cliente_id);
            if ($ped_edit && $ped_edit['estado'] === 'pendiente') {
                $dt = new DateTime($ped_edit['fecha_entrega']);
                $yr = (int)$dt->format('Y');
                if ($yr <= 1970) {
                    $edit_fecha = date('Y-m-d');
                    $edit_hora = '08:00';
                } else {
                    $edit_fecha = $dt->format('Y-m-d');
                    $edit_hora = $dt->format('H:i');
                }
                // Bloqueo por pago en proceso si es aprendiz
                if (!empty($ped_edit['id_pago_activo'])) {
                    $stmt_pay_check = $this->pdo->prepare("SELECT estado FROM pago_pedido WHERE id_pago = ?");
                    $stmt_pay_check->execute([(int)$ped_edit['id_pago_activo']]);
                    $pay_status = $stmt_pay_check->fetchColumn();
                    if ($pay_status && in_array(strtoupper($pay_status), ['PENDING', 'PENDIENTE'])) {
                        if ($es_aprendiz) {
                            header('Location: detalle_pedido.php?id=' . $edit_id . '&error=pago_proceso');
                            exit;
                        }
                    }
                }

                // Validar restricción de 48 horas en la carga
                $fe_dt = new DateTime($ped_edit['fecha_entrega']);
                $ahora = new DateTime();
                $diff = $ahora->diff($fe_dt);
                $hrs = ($diff->days * 24) + $diff->h;
                if ($diff->invert == 1 || $hrs < 48) {
                    header('Location: detalle_pedido.php?id=' . $edit_id . '&error=limite_tiempo');
                    exit;
                }

                $rows = $this->model->getDetallesPedido($edit_id);
                // Necesitamos la estructura pregrabada que requiere la vista (incluyendo catId)
                // Para esto volvemos a consultar con categorias
                $stmt_det_edit = $this->pdo->prepare("
                    SELECT d.*, vp.nombre, vp.imagen, cp.precio_unitario, cp.id_categoria 
                    FROM pedido_cliente_detalle d 
                    JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
                    JOIN categoria_precio cp ON vp.id_categoria_precio = cp.id_categoria
                    WHERE d.id_pedido = ?
                ");
                $stmt_det_edit->execute([$edit_id]);
                $det_rows = $stmt_det_edit->fetchAll(PDO::FETCH_ASSOC);

                foreach ($det_rows as $r) {
                    if ($r['cantidad'] > 0) {
                        $cart_preload[] = [
                            'id_variedad' => (int)$r['id_variedad'],
                            'nombre' => $r['nombre'],
                            'imagen' => $r['imagen'],
                            'precio' => (float)$r['precio_unitario'],
                            'cantidad' => (int)$r['cantidad'],
                            'catId' => (int)$r['id_categoria']
                        ];
                    } else if ($r['napa'] > 0 || $r['bonificacion'] > 0) {
                        $bonif_preload[(int)$r['id_variedad']] = $r['napa'] > 0 ? (int)$r['napa'] : (int)$r['bonificacion'];
                    }
                }
            }
        }

        $pedido_para_actual = 'adso';
        if ($ped_edit && $es_aprendiz) {
            $pedido_para_actual = ((int)$ped_edit['id_cliente'] === $cliente_id) ? 'personal' : 'adso';
        }

        $categorias = $this->model->getCategoriasActivas();

        require_once __DIR__ . '/../views/portal/nuevo_pedido.php';
    }

    /**
     * Cancela un pedido.
     */
    public function cancelarPedido() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        $id_pedido  = (int)($_GET['id'] ?? 0);

        try {
            $this->model->cancelarPedido($id_pedido, $cliente_id);
            header('Location: dashboard.php?msg=cancelado');
        } catch (Exception $e) {
            $msg = $e->getMessage();
            if (strpos($msg, 'pago') !== false) {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=pago_proceso');
            } elseif (strpos($msg, '48 horas') !== false) {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=limite_tiempo');
            } else {
                header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=' . urlencode($msg));
            }
        }
        exit;
    }

    /**
     * Edición de perfil, contraseña y PIN de recuperación.
     */
    public function perfil() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        
        $msg_ok = '';
        $msg_err = '';

        $cliente = $this->model->getClienteById($cliente_id);
        if (!$cliente) {
            header('Location: logout.php');
            exit;
        }

        $es_tienda = ($cliente['tipo'] === 'tienda');
        $es_instructor = false;
        if ($es_tienda) {
            $stats_inst = $this->model->getInstructorStats($cliente_id);
            $es_instructor = ($stats_inst['total_pedidos'] > 0 || $this->model->contarAprendices($cliente_id) > 0);
        }

        // El vínculo aprendiz-instructor ya NO se edita a mano aquí: se hace canjeando
        // un código del instructor. Se calcula si este cliente puede canjear uno y, si ya
        // es aprendiz, el nombre de su instructor (solo para mostrarlo).
        $puede_canjear = (!$es_tienda && (int)$cliente['es_aprendiz'] !== 1);
        $mi_instructor_nombre = '';
        if ((int)$cliente['es_aprendiz'] === 1 && !empty($cliente['id_instructor'])) {
            $inst = $this->model->getClienteById((int)$cliente['id_instructor']);
            $mi_instructor_nombre = $inst['nombre'] ?? '';
        }

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
                    // Límite de intentos por sesión para que nadie pruebe códigos al azar.
                    $ahora   = time();
                    $bloqueo = (int)($_SESSION['canje_bloqueo_hasta'] ?? 0);
                    if ($ahora < $bloqueo) {
                        $mins = (int)ceil(($bloqueo - $ahora) / 60);
                        $msg_err = "Demasiados intentos con códigos. Espera $mins minuto(s) e intenta de nuevo.";
                    } elseif ($es_tienda) {
                        $msg_err = 'Las cuentas de tienda no pueden registrarse como aprendices.';
                    } elseif ((int)$cliente['es_aprendiz'] === 1) {
                        $msg_err = 'Ya estás registrado como aprendiz de un instructor.';
                    } else {
                        try {
                            $r = $this->model->canjearCodigoAprendiz($cliente_id, $_POST['codigo_aprendiz'] ?? '');
                            if ($r['ok']) {
                                $_SESSION['canje_intentos'] = 0;
                                $msg_ok = '¡Listo! Quedaste vinculado como aprendiz de ' . htmlspecialchars($r['instructor']) . '.';
                                $cliente = $this->model->getClienteById($cliente_id);
                                $puede_canjear = false;
                                $mi_instructor_nombre = $r['instructor'];
                            } else {
                                $intentos = (int)($_SESSION['canje_intentos'] ?? 0) + 1;
                                $_SESSION['canje_intentos'] = $intentos;
                                if ($intentos >= 5) {
                                    $_SESSION['canje_bloqueo_hasta'] = $ahora + 600; // 10 minutos
                                    $_SESSION['canje_intentos'] = 0;
                                    $msg_err = 'Demasiados intentos. Espera 10 minutos e intenta de nuevo.';
                                } else {
                                    $msg_err = $r['error'];
                                }
                            }
                        } catch (Exception $e) {
                            $msg_err = 'No se pudo canjear el código. Intenta de nuevo.';
                        }
                    }
                } elseif (isset($_POST['cambiar_pass'])) {
                $actual = $_POST['pass_actual'] ?? '';
                $nueva = $_POST['pass_nueva'] ?? '';
                $confirm = $_POST['pass_confirm'] ?? '';
                
                if (password_verify($actual, $cliente['contrasena_hash'])) {
                    if ($nueva === $confirm) {
                        if (strlen($nueva) >= 4) {
                            $hash = password_hash($nueva, PASSWORD_DEFAULT);
                            $this->model->actualizarPassword($cliente_id, $hash);
                            $msg_ok = 'Contraseña cambiada exitosamente.';
                        } else {
                            $msg_err = 'La nueva contraseña debe tener al menos 4 caracteres.';
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
                
                if (password_verify($pass, $cliente['contrasena_hash'])) {
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

        require_once __DIR__ . '/../views/portal/perfil.php';
    }

    /**
     * Pantalla "Mis aprendices": el instructor genera/rota su código de invitación,
     * ve su grupo y ajusta cupos o retira aprendices. Solo cuentas instructor-capaces
     * (tienda que no es aprendiz); un instructor solo gestiona SUS aprendices.
     */
    public function misAprendices() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente = $this->model->getClienteById($cliente_id);
        if (!$cliente) {
            header('Location: logout.php');
            exit;
        }

        // Solo una cuenta instructor-capaz entra aquí (seguridad).
        if (!$this->model->esInstructorCapaz($cliente)) {
            header('Location: dashboard.php');
            exit;
        }

        // Mensajes vía POST-Redirect-GET.
        $msg_ok  = $_SESSION['flash_ok']  ?? '';
        $msg_err = $_SESSION['flash_err'] ?? '';
        unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $msg_err = 'Token de seguridad inválido o expirado. Recarga la página e intenta de nuevo.';
            } elseif (isset($_POST['generar_codigo'])) {
                $dias = (int)($_POST['dias_vigencia'] ?? 0);
                $dias = max(0, min(365, $dias));
                $sin_limite = isset($_POST['sin_limite_usos']);
                $usos = $sin_limite ? null : max(1, min(1000, (int)($_POST['usos_maximos'] ?? 1)));
                try {
                    $codigo = $this->model->generarCodigoAprendiz($cliente_id, $dias, $usos);
                    $msg_ok = 'Código generado: ' . $codigo . '. Compártelo con tus aprendices.';
                } catch (Exception $e) {
                    log_error($e);
                    $msg_err = 'No se pudo generar el código. Intenta de nuevo.';
                }
            } elseif (isset($_POST['desactivar_codigo'])) {
                $n = $this->model->desactivarCodigosInstructor($cliente_id);
                $msg_ok = $n > 0 ? 'Código desactivado. Puedes generar uno nuevo cuando quieras.' : 'No había un código activo.';
            } elseif (isset($_POST['quitar_aprendiz'])) {
                $aid = (int)($_POST['aprendiz_id'] ?? 0);
                if ($this->model->quitarAprendiz($cliente_id, $aid)) {
                    $msg_ok = 'Aprendiz retirado de tu grupo. Sus pedidos anteriores se conservan.';
                } else {
                    $msg_err = 'No se pudo retirar: esa persona no es un aprendiz de tu grupo.';
                }
            } elseif (isset($_POST['actualizar_cupo'])) {
                $aid  = (int)($_POST['aprendiz_id'] ?? 0);
                $cupo = (float)($_POST['cupo_semanal'] ?? 0);
                if ($cupo < 0 || $cupo > 100000) {
                    $msg_err = 'El cupo semanal debe estar entre $0 y $100.000 COP.';
                } elseif ((int)$cupo % 500 !== 0 || $cupo != (int)$cupo) {
                    $msg_err = 'El cupo semanal debe ser múltiplo de $500 COP.';
                } elseif ($this->model->actualizarCupoAprendizInstructor($cliente_id, $aid, $cupo)) {
                    $msg_ok = 'Cupo semanal actualizado.';
                } else {
                    $msg_err = 'No se pudo actualizar: esa persona no es un aprendiz de tu grupo.';
                }
            }

            $_SESSION['flash_ok']  = $msg_ok;
            $_SESSION['flash_err'] = $msg_err;
            redirigir(APP_URL . '/portal/mis_aprendices.php');
        }

        $codigo_activo = $this->model->getCodigoActivoInstructor($cliente_id);
        $aprendices    = $this->model->getAprendicesGestion($cliente_id);
        $es_instructor = true;

        require_once __DIR__ . '/../views/portal/mis_aprendices.php';
    }

    /**
     * Completa el perfil social de Google OAuth.
     */
    public function completarPerfil() {
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
                    // El vínculo aprendiz-instructor NO se asigna aquí: se hace luego
                    // canjeando un código del instructor desde el perfil. Se conserva el
                    // estado actual del cliente (recién creado por Google: no es aprendiz).
                    $this->model->completarPerfilCliente($cliente_id, $nombre, 0, null);
                    $_SESSION['cliente_nombre'] = $nombre;
                    header('Location: dashboard.php');
                    exit;
                }
            }
        }

        $cliente = $this->model->getClienteById($cliente_id);
        $nombre_actual = $cliente['nombre'] ?? '';
        $foto_url      = $cliente['foto_url'] ?? '';

        require_once __DIR__ . '/../views/portal/completar_perfil.php';
    }

    /**
     * Generación del link de pago consolidado y redirección.
     */
    public function pagarConsolidado() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $config_pago = $this->model->getConfiguracionPago();
        $pago_configurado = !empty($config_pago['nequi_link_pago']) && !empty($config_pago['wompi_habilitado']);

        $id_pedido_spec = (int)($_GET['id_pedido'] ?? 0);

        // getPedidosPendientesPago aplica la regla unica de pago por id_cliente (D2/D5).
        $pedidos = $this->model->getPedidosPendientesPago($cliente_id, $id_pedido_spec);
        if (empty($pedidos)) {
            header('Location: dashboard.php');
            exit;
        }

        // Defensa en profundidad (D2): ningun pedido del lote puede pertenecer a otra
        // cuenta. Si alguno no factura a este usuario, se aborta el lote completo — nunca
        // se filtra silenciosamente ni se expone el link de pago de otro.
        foreach ($pedidos as $p) {
            if ((int)$p['id_cliente'] !== $cliente_id) {
                header('Location: dashboard.php?error=pago_no_autorizado');
                exit;
            }
        }

        $total_saldo = 0;
        $ids_pedidos = [];
        $pedidos_por_pago = [];

        foreach ($pedidos as $p) {
            $pago_id = !empty($p['id_pago_activo']) ? (int)$p['id_pago_activo'] : 0;
            $pedidos_por_pago[$pago_id][] = $p;
            $ids_pedidos[] = (int)$p['id_pedido'];
        }

        foreach ($pedidos_por_pago as $pago_id => $grupo_pedidos) {
            if ($pago_id === 0) {
                foreach ($grupo_pedidos as $p) {
                    $total_saldo += (float)$p['total_estimado'];
                }
            } else {
                $pago_rec = $this->model->getPagoPendientePorId($pago_id);
                $suma_grupo = 0;
                foreach ($grupo_pedidos as $p) {
                    $suma_grupo += (float)$p['total_estimado'];
                }
                
                // Si está aprobado pero es un abono parcial, se debe sumar la diferencia restante
                $stmt_pay_actual = $this->pdo->prepare("SELECT estado, monto FROM pago_pedido WHERE id_pago = ?");
                $stmt_pay_actual->execute([$pago_id]);
                $pago_rec_db = $stmt_pay_actual->fetch(PDO::FETCH_ASSOC);

                if ($pago_rec_db && in_array(strtoupper($pago_rec_db['estado']), ['APPROVED', 'APROBADO'])) {
                    $deficit = $suma_grupo - (float)$pago_rec_db['monto'];
                    if ($deficit > 0) {
                        $total_saldo += $deficit;
                    }
                } else {
                    $total_saldo += $suma_grupo;
                }
            }
        }

        $error = '';
        $success = '';
        $link_pago_url = '';
        $pago_existente = null;

        // ¿Todos los pedidos ya comparten un unico pago PENDING? (idempotencia D3/C5)
        $pagos_activos = array_unique(array_filter(array_column($pedidos, 'id_pago_activo')));
        $id_pago_compartido = 0;
        if (count($pagos_activos) === 1) {
            $candidato = (int)reset($pagos_activos);
            $todos = true;
            foreach ($pedidos as $p) {
                if (empty($p['id_pago_activo']) || (int)$p['id_pago_activo'] !== $candidato) {
                    $todos = false;
                    break;
                }
            }
            if ($todos && $this->model->getPagoPendientePorId($candidato)) {
                $id_pago_compartido = $candidato;
            }
        }

        // URL de redireccion (POST-Redirect-GET). Preserva el pedido especifico si aplica.
        $redir = 'pagar_consolidado.php' . ($id_pedido_spec > 0 ? '?id_pedido=' . $id_pedido_spec . '&' : '?') . 'pago=ok';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generar_pago'])) {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                $error = 'Token de seguridad inválido o expirado. Por favor, intente de nuevo.';
            } elseif (!$pago_configurado) {
                $error = 'La panadería aún no ha configurado el pago digital. Por favor contacta al propietario.';
            } elseif ($id_pago_compartido > 0) {
                // Idempotencia: ya existe un pago PENDING que cubre estos pedidos. No se
                // crea otro; se redirige a mostrar el enlace (POST-Redirect-GET).
                header('Location: ' . $redir);
                exit;
            } else {
                try {
                    $referencia = sprintf('CON-%d-%d', $cliente_id, (int)(microtime(true) * 1000));

                    // Extraer link_id del link estatico de Nequi (alojado en checkout.wompi.co)
                    $link_id = null;
                    if (preg_match('#/l/([A-Za-z0-9_-]+)#', $config_pago['nequi_link_pago'], $m)) {
                        $link_id = $m[1];
                    }

                    // Auditoria (D3): dejar constancia de quien inicio el pago.
                    $pagador = trim($_SESSION['cliente_nombre'] ?? '');
                    $nota_consolidado = sprintf('Pago de %d pedido(s) [%s] iniciado por %s (cliente #%d)',
                        count($ids_pedidos),
                        implode(', ', array_map(fn($id) => '#' . str_pad($id, 4, '0', STR_PAD_LEFT), $ids_pedidos)),
                        $pagador !== '' ? $pagador : 'cliente',
                        $cliente_id
                    );

                    $this->model->iniciarPagoConsolidado(
                        $cliente_id,
                        $pedidos,
                        $ids_pedidos,
                        $total_saldo,
                        $referencia,
                        $link_id,
                        $config_pago['nequi_link_pago'],
                        $nota_consolidado
                    );

                    // POST-Redirect-GET: evita re-registrar el pago con F5/doble submit (C5).
                    header('Location: ' . $redir);
                    exit;
                } catch (Exception $e) {
                    log_error($e);
                    $error = 'Error al habilitar el pago. Intenta de nuevo.';
                }
            }
        } else {
            // GET: si ya existe el pago consolidado, mostrar el enlace de Nequi.
            // El enlace SIEMPRE proviene de configuracion.nequi_link_pago (nunca hardcodeado).
            if ($id_pago_compartido > 0) {
                $pago_existente = $this->model->getPagoPendientePorId($id_pago_compartido);
                if ($pago_existente) {
                    $link_pago_url = $config_pago['nequi_link_pago'];
                }
            }
            if (isset($_GET['pago']) && $_GET['pago'] === 'ok' && $link_pago_url) {
                $success = 'Pago registrado. Toca el botón verde de abajo para pagar por Nequi.';
            }
        }

        $titular_negocio = $config_pago['nequi_titular'] ?? '';

        require_once __DIR__ . '/../views/portal/pagar_consolidado.php';
    }

    /**
     * Exporta pedidos seleccionados desde el dashboard en formato Excel o PDF.
     */
    public function exportarPedidosDashboard() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!validar_token_csrf($_POST['csrf_token'] ?? '')) {
                die("Error de seguridad: Token CSRF inválido.");
            }
        }

        $formato    = in_array($_POST['formato'] ?? '', ['excel', 'pdf']) ? $_POST['formato'] : 'pdf';
        $ids        = array_values(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($v) => $v > 0));

        if (empty($ids)) {
            header('Location: dashboard.php');
            exit;
        }

        // Verificar pertenencia
        if (!$this->model->verificarPedidosPertenecenCliente($ids, $cliente_id)) {
            header('Location: dashboard.php');
            exit;
        }

        $cliente_info = $this->model->getClienteById($cliente_id);
        $nombre_tienda = $cliente_info ? $cliente_info['nombre'] : 'Tienda';

        $pedidos = $this->model->getPedidosDetalladosParaExportacion($ids);
        $todos_detalles = $this->model->getDetallesPedidosParaExportacion($ids);

        // Agrupar detalles por id_pedido
        $detalles_por_pedido = [];
        foreach ($todos_detalles as $d) {
            $detalles_por_pedido[$d['id_pedido']][] = $d;
        }

        $fecha_generado = date('d/m/Y H:i');

        require_once __DIR__ . '/../views/portal/exportar_pedidos_dashboard.php';
    }

    /**
     * Exporta el reporte de panes agrupado por aprendiz para una tienda.
     */
    public function exportarReporteTienda() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $id_pedido  = (int)($_GET['id'] ?? 0);
        $formato    = in_array($_GET['formato'] ?? '', ['excel', 'pdf']) ? $_GET['formato'] : 'pdf';

        $pedido = $this->model->getPedidoTiendaParaExportacion($id_pedido, $cliente_id);
        if (!$pedido) {
            header('Location: dashboard.php');
            exit;
        }

        $reporte_por_aprendiz = $this->model->getReporteAgrupadoTienda($pedido['id_cliente'], $pedido['fecha_entrega']);
        $total_general = $this->model->getTotalGeneralReporteTienda($pedido['id_cliente'], $pedido['fecha_entrega']);

        $fecha_entrega_fmt = date('H:i', strtotime($pedido['fecha_entrega'])) !== '00:00'
            ? date('d/m/Y H:i', strtotime($pedido['fecha_entrega']))
            : date('d/m/Y', strtotime($pedido['fecha_entrega']));
        $fecha_generado    = date('d/m/Y H:i');
        $nombre_tienda     = $pedido['nombre_tienda'];

        require_once __DIR__ . '/../views/portal/exportar_reporte_tienda.php';
    }

    /**
     * Exporta la cartera de todos los aprendices vinculados al instructor en formato PDF/Impresión.
     */
    public function exportarCarteraInstructor() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];

        $cliente_info = $this->model->getClienteById($cliente_id);
        if (!$cliente_info) {
            header('Location: logout.php');
            exit;
        }

        // Validar si realmente es instructor ADSO o tiene aprendices
        $total_reg = $this->model->contarAprendices($cliente_id, true);
        if ($total_reg === 0) {
            header('Location: dashboard.php');
            exit;
        }

        $aprendices = $this->model->getAprendicesResumen($cliente_id);
        $resumen_fin = $this->model->getInstructorStats($cliente_id);

        $nombre_instructor = $cliente_info['nombre'];
        $fecha_generado    = date('d/m/Y H:i');

        require_once __DIR__ . '/../views/portal/exportar_cartera_instructor.php';
    }

    /**
     * Exporta el recibo de un pago aprobado o parcial en formato PDF/Impresión.
     */
    public function exportarReciboPago() {
        $this->requireCliente();
        $cliente_id = (int)$_SESSION['cliente_id'];
        $id_pedido  = (int)($_GET['id'] ?? 0);

        $pedido = $this->model->getPedido($id_pedido, $cliente_id);
        if (!$pedido || !in_array($pedido['estado_pago'], ['aprobado', 'parcial'])) { 
            header('Location: dashboard.php'); 
            exit; 
        }

        $detalles = $this->model->getDetallesPedido($id_pedido);

        $abonos = [];
        $total_pagado = 0.0;
        $pago_activo = null;
        if (!empty($pedido['id_pago_activo'])) {
            $pago_id = (int)$pedido['id_pago_activo'];
            
            // Buscar pago_pedido activo o inactivo (aprobado)
            $stmt_p = $this->pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ?");
            $stmt_p->execute([$pago_id]);
            $pago_activo = $stmt_p->fetch(PDO::FETCH_ASSOC);
            
            if ($pago_activo) {
                $abonos = $this->model->getAbonos($pago_id);
                foreach ($abonos as $ab) {
                    $total_pagado += (float)$ab['monto'];
                }
            }
        }

        // Consultar otros pedidos incluidos en esta misma transacción de pago
        $pedidos_consolidados = [];
        if ($pago_activo) {
            $stmt_pc = $this->pdo->prepare("
                SELECT p.id_pedido, p.total_estimado, p.fecha_entrega, c.nombre AS nombre_aprendiz
                FROM pedido_cliente p
                LEFT JOIN cliente c ON p.id_creador = c.id_cliente
                WHERE p.id_pago_activo = ? AND p.estado_pago IN ('aprobado', 'parcial')
            ");
            $stmt_pc->execute([$pago_activo['id_pago']]);
            $pedidos_consolidados = $stmt_pc->fetchAll(PDO::FETCH_ASSOC);
        }

        $metodos_legibles = [
            'NEQUI' => 'Nequi', 'BANCOLOMBIA' => 'Bancolombia',
            'PSE' => 'PSE', 'TARJETA' => 'Tarjeta', 'OTRO' => 'Otro medio',
        ];

        $cfg = $this->model->getConfiguracionPago();
        $titular_negocio = $cfg['nequi_titular'] ?? 'BreadControl';
        
        $row_tipo = $this->model->getClienteTipoAsociadoPedido($id_pedido);
        $nombre_tienda = $row_tipo['nombre'] ?? '';

        $fecha_generado = date('d/m/Y H:i');

        require_once __DIR__ . '/../views/portal/exportar_recibo_pago.php';
    }
}


