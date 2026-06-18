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
            setcookie(session_name(), '', time() - 42000,
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


// Cerrar sesión
function cerrarSesion(): void {
    session_unset();
    session_destroy();
    header('Location: ' . APP_URL . '/login.php');
    exit;
}

// Obtener el usuario actual
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
