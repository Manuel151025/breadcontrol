<?php
// config/env.php
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $vars = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($vars) {
        foreach ($vars as $key => $value) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

if (!function_exists('get_env')) {
    function get_env($key, $default = null) {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (getenv($key) !== false) return getenv($key);
        return $default;
    }
}
