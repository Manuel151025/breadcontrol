<?php
// config/logger.php

define('LOG_PATH', __DIR__ . '/../logs');

if (!is_dir(LOG_PATH)) {
    @mkdir(LOG_PATH, 0777, true);
}

function log_error($error) {
    $date = date('Y-m-d H:i:s');
    $logFile = LOG_PATH . '/app-' . date('Y-m-d') . '.log';
    
    $message = "[$date] ";
    if ($error instanceof Exception || $error instanceof Error) {
        $message .= get_class($error) . ": " . $error->getMessage() . " en " . $error->getFile() . " en la línea " . $error->getLine() . "\n";
        $message .= "Stack trace:\n" . $error->getTraceAsString() . "\n";
    } else {
        $message .= "Mensaje: " . print_r($error, true) . "\n";
    }
    
    @error_log($message . str_repeat("-", 40) . "\n", 3, $logFile);
}

// Configurar el manejador global de excepciones
set_exception_handler(function($e) {
    log_error($e);
    
    // Verificar si es una petición AJAX
    $isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Algunas peticiones en este proyecto pueden estar esperando JSON porque la URL termina en .php o por los headers que enviaron.
    // Usamos heurística simple. Si detectamos Fetch/Ajax o Accept json, devolvemos JSON.
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if ($isAjax || strpos($accept, 'application/json') !== false) {
        header('Content-Type: application/json', true, 500);
        echo json_encode([
            'error' => true,
            'mensaje' => 'Ha ocurrido un error interno. Por favor, inténtelo de nuevo o contacte al administrador.'
        ]);
    } else {
        http_response_code(500);
        echo "<div style='font-family:sans-serif; text-align:center; padding:50px; background-color:#f8f9fa; color:#343a40; border-radius:8px; max-width:600px; margin:50px auto; box-shadow:0 4px 6px rgba(0,0,0,0.1);'>";
        echo "<h2 style='color:#dc3545;'>Ocurrió un error inesperado</h2>";
        echo "<p>El error ha sido registrado en los archivos de log.</p>";
        echo "<p>Por favor, contacta al administrador del sistema si el problema persiste.</p>";
        echo "<a href='javascript:history.back()' style='display:inline-block; margin-top:20px; padding:10px 20px; background-color:#0d6efd; color:white; text-decoration:none; border-radius:5px;'>Volver</a>";
        echo "</div>";
    }
    exit;
});
