<?php
// config/env.php
$envPath = __DIR__ . '/../.env';
if (file_exists($envPath)) {
    $vars = parse_ini_file($envPath, false, INI_SCANNER_RAW);
    if ($vars) {
        foreach ($vars as $key => $value) {
            // Las variables de entorno reales tienen prioridad sobre .env
            // (permite a CI y Docker sobreescribir valores sin tocar el archivo)
            if (getenv($key) !== false) {
                continue;
            }
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

if (!function_exists('get_env')) {
    function get_env(string $key, mixed $default = null): mixed {
        if (isset($_ENV[$key])) return $_ENV[$key];
        if (getenv($key) !== false) return getenv($key);
        return $default;
    }
}
