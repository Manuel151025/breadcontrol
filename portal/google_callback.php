<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';

session_start();

if (isset($_SESSION['cliente_id'])) {
    header('Location: ' . APP_URL . '/portal/dashboard.php');
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
    header('Location: ' . APP_URL . '/portal/index.php?error=google_cancelado');
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
    header('Location: ' . APP_URL . '/portal/index.php?error=google_token');
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
    header('Location: ' . APP_URL . '/portal/index.php?error=google_perfil');
    exit;
}

$google_id = $guser['sub'];
$email     = isset($guser['email']) && ($guser['email_verified'] ?? false) ? $guser['email'] : '';
$nombre    = $guser['name']    ?? ($email ?: 'Cliente Google');
$foto_url  = $guser['picture'] ?? '';

$pdo = getConexion();

// 1. Try to find by google_id
$stmt = $pdo->prepare("SELECT * FROM cliente WHERE google_id = ? AND activo = 1 LIMIT 1");
$stmt->execute([$google_id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Try to find by email and link google_id
if (!$cliente && $email) {
    $stmt = $pdo->prepare("SELECT * FROM cliente WHERE email = ? AND activo = 1 LIMIT 1");
    $stmt->execute([$email]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($cliente) {
        $pdo->prepare("UPDATE cliente SET google_id = ?, foto_url = ? WHERE id_cliente = ?")
            ->execute([$google_id, $foto_url, $cliente['id_cliente']]);
        $cliente['google_id'] = $google_id;
        $cliente['foto_url']  = $foto_url;
    }
}

// 3. Auto-register new client via Google
$es_nuevo = false;

if (!$cliente) {
    $pdo->prepare(
        "INSERT INTO cliente (nombre, tipo, email, google_id, foto_url, activo, fecha_creacion)
         VALUES (?, 'mostrador', ?, ?, ?, 1, NOW())"
    )->execute([$nombre, $email ?: null, $google_id, $foto_url]);

    $new_id = (int) $pdo->lastInsertId();
    $stmt   = $pdo->prepare("SELECT * FROM cliente WHERE id_cliente = ?");
    $stmt->execute([$new_id]);
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
    $es_nuevo = true;
}

if (!$cliente) {
    header('Location: ' . APP_URL . '/portal/index.php?error=google_registro');
    exit;
}

$_SESSION['cliente_id']     = $cliente['id_cliente'];
$_SESSION['cliente_nombre'] = $cliente['nombre'];
$_SESSION['cliente_foto']   = $cliente['foto_url'] ?? '';

if ($es_nuevo) {
    header('Location: ' . APP_URL . '/portal/completar_perfil.php');
} else {
    header('Location: ' . APP_URL . '/portal/dashboard.php');
}
exit;
