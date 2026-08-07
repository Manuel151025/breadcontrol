<?php
// ============================================================
//  FUNCIONES DE SESIÓN Y AUTENTICACIÓN
//  Archivo: includes/sesion.php
// ============================================================

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

// Configuración de cookie de sesión segura
if (session_status() === PHP_SESSION_NONE) {
    $secure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) {
        $secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $secure = true;
    }
    
    session_set_cookie_params([
        'lifetime' => SESSION_DURACION,
        'path' => '/',
        'domain' => '',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_name(SESSION_NOMBRE);
    session_start();
}

// Verificar que el usuario esté logueado
// Si no lo está, redirigir al login
function requerirLogin(): void {
    if (!isset($_SESSION['id_usuario'])) {
        header('Location: ' . APP_URL . '/login.php');
        exit;
    }

    // Verificar expiración de sesión por inactividad
    if (isset($_SESSION['ultima_actividad'])) {
        if (time() - $_SESSION['ultima_actividad'] > SESSION_DURACION) {
            cerrarSesion();
        }
    }

    $_SESSION['ultima_actividad'] = time();
}

// Verificar que sea propietario para funciones restringidas
function requerirPropietario(): void {
    requerirLogin();
    if ($_SESSION['rol'] !== 'propietario') {
        // Limpiar y destruir la sesión para evitar bucles de redirección infinita en login.php
        session_unset();
        session_destroy();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie((string) session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        header('Location: ' . APP_URL . '/login.php?error=acceso_denegado');
        exit;
    }
}

// Iniciar sesión del usuario
function iniciarSesion(string $nombre_usuario, string $contrasena): bool {
    require_once __DIR__ . '/../models/AuthModel.php';
    $pdo  = getConexion();
    $model = new AuthModel($pdo);
    $usuario = $model->getUsuarioPorNombre($nombre_usuario);

    if ($usuario && password_verify($contrasena, $usuario['contrasena_hash'])) {
        $_SESSION['id_usuario']       = $usuario['id_usuario'];
        $_SESSION['nombre_completo']  = $usuario['nombre_completo'];
        $_SESSION['rol']              = $usuario['rol'];
        $_SESSION['ultima_actividad'] = time();
        return true;
    }

    return false;
}


// Cerrar sesión (nunca retorna: redirige y termina con exit)
function cerrarSesion(): never {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Obtener el usuario actual
/** @return array<mixed> */
function usuarioActual(): array {
    return [
        'id_usuario' => $_SESSION['id_usuario']      ?? null,
        'nombre'     => $_SESSION['nombre_completo'] ?? '',
        'rol'        => $_SESSION['rol']             ?? '',
    ];
}

// Verificar si es propietario (sin redirigir)
function esPropietario(): bool {
    return isset($_SESSION['rol']) && $_SESSION['rol'] === 'propietario';
}

/**
 * IP de origen de la petición, para el control de intentos de inicio de sesión.
 *
 * En producción la aplicación corre detrás de Traefik, así que REMOTE_ADDR es
 * siempre la IP del proxy y hay que leer X-Forwarded-For (primer elemento = el
 * cliente original). Esa cabecera la puede falsificar quien llegue directo al
 * contenedor, por lo que el bloqueo por IP es solo una salvaguarda secundaria:
 * la defensa principal es el bloqueo por cuenta, que no depende de la IP.
 */
function ip_cliente(): ?string {
    $reenviada = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if (is_string($reenviada) && $reenviada !== '') {
        $partes = explode(',', $reenviada);
        $ip     = trim($partes[0]);
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    $remota = $_SERVER['REMOTE_ADDR'] ?? '';
    return is_string($remota) && filter_var($remota, FILTER_VALIDATE_IP) ? $remota : null;
}

/**
 * Genera un token CSRF criptográficamente seguro si no existe en la sesión.
 */
function generar_token_csrf(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Guarda CSRF para las pantallas del back-office: si el token del POST no es
 * válido, corta la petición y devuelve al usuario a la pantalla de origen con
 * `?err=csrf`, que cada controlador traduce a un aviso visible.
 *
 * Se llama UNA vez por cada método de controlador que procese POST, antes de
 * mirar qué acción se pidió, para que ninguna rama nueva quede sin proteger por
 * olvido. Los formularios acompañan el token con campo_csrf().
 */
function requerir_csrf(string $url_error): void {
    // is_string: un formulario manipulado puede enviar csrf_token[] (array).
    $token = $_POST['csrf_token'] ?? '';
    if (validar_token_csrf(is_string($token) ? $token : '')) {
        return;
    }
    header('Location: ' . $url_error);
    exit;
}

/**
 * Campo oculto con el token CSRF, para no repetir el input en cada formulario.
 */
function campo_csrf(): string {
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(generar_token_csrf(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Valida si el token provisto coincide de forma segura con el token de sesión.
 */
function validar_token_csrf(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}
