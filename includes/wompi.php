<?php
// ============================================================
// includes/wompi.php
// ============================================================
//  Helper para integracion con Wompi (pasarela de pago Bancolombia)
//  Usado por el portal de clientes para pagos digitales.
//
//  Funciones publicas:
//    wompi_esta_configurado()           -> bool
//    wompi_es_sandbox()                 -> bool
//    wompi_generar_referencia($id)      -> string
//    wompi_crear_link_pago($datos)      -> array  (resultado)
//    wompi_consultar_transaccion($id)   -> array  (resultado)
//    wompi_validar_firma_webhook($evt)  -> bool
//    wompi_mapear_estado($estado)       -> string ('aprobado', 'pendiente'...)
//
//  Convencion de resultado:
//    ['ok' => true,  ...datos]   o   ['ok' => false, 'error' => string]
// ============================================================

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/logger.php';

// ============================================================
//  Configuracion
// ============================================================

function wompi_config(): array {
    static $config = null;
    if ($config === null) {
        $config = [
            'public_key'    => get_env('WOMPI_PUBLIC_KEY', ''),
            'private_key'   => get_env('WOMPI_PRIVATE_KEY', ''),
            'events_secret' => get_env('WOMPI_EVENTS_SECRET', ''),
            'base_url'      => rtrim(get_env('WOMPI_BASE_URL', 'https://sandbox.wompi.co/v1'), '/'),
        ];
    }
    return $config;
}

function wompi_esta_configurado(): bool {
    $c = wompi_config();
    return !empty($c['public_key'])
        && !empty($c['private_key'])
        && !empty($c['events_secret']);
}

function wompi_es_sandbox(): bool {
    $c = wompi_config();
    return strpos($c['base_url'], 'sandbox') !== false
        || strpos($c['private_key'], 'prv_test_') === 0;
}

// ============================================================
//  Referencia unica
//  Wompi exige que cada transaccion tenga una referencia unica.
//  Si reutilizamos id_pedido y el cliente genera un segundo link
//  (porque el primero expiro), Wompi rechazaria por duplicado.
//  Formato: PED-{id_pedido}-{milisegundos}
// ============================================================

function wompi_generar_referencia(int $id_pedido): string {
    $ms = (int) (microtime(true) * 1000);
    return sprintf('PED-%d-%d', $id_pedido, $ms);
}

// ============================================================
//  Crear link de pago
// ============================================================
//  $datos esperados:
//    'monto'            => 5000         (pesos, REQUERIDO)
//    'referencia'       => 'PED-1-...'  (REQUERIDO)
//    'nombre'           => 'Pedido #1'  (opcional)
//    'descripcion'      => '...'        (opcional)
//    'redirect_url'     => 'https://...' (opcional, a donde vuelve el cliente)
//    'customer_email'   => 'a@b.com'    (opcional, precarga checkout)
//    'horas_expiracion' => 24           (opcional, default 24)
//
//  Retorna en caso de exito:
//    [
//      'ok'             => true,
//      'link_id'        => 'XXXX',
//      'checkout_url'   => 'https://checkout.wompi.co/l/XXXX',
//      'monto_centavos' => 500000,
//      'expira'         => '2026-05-20T...',
//      'referencia'     => 'PED-1-...',
//    ]
// ============================================================

function wompi_crear_link_pago(array $datos): array {
    if (!wompi_esta_configurado()) {
        return ['ok' => false, 'error' => 'Wompi no esta configurado (revisa el .env)'];
    }

    if (empty($datos['monto']) || $datos['monto'] <= 0) {
        return ['ok' => false, 'error' => 'Monto invalido'];
    }

    if (empty($datos['referencia'])) {
        return ['ok' => false, 'error' => 'Falta la referencia unica'];
    }

    $c = wompi_config();
    $monto_centavos = (int) round($datos['monto'] * 100);

    // Expiracion en ISO 8601 UTC con milisegundos (formato exigido por Wompi)
    $horas = max(1, (int) ($datos['horas_expiracion'] ?? 24));
    $exp   = new DateTime('now', new DateTimeZone('UTC'));
    $exp->modify("+{$horas} hours");
    $expira_iso = $exp->format('Y-m-d\TH:i:s.000\Z');

    $payload = [
        'name'             => substr($datos['nombre'] ?? 'Pedido BreadControl', 0, 128),
        'description'      => substr($datos['descripcion'] ?? 'Pago de pedido digital', 0, 240),
        'single_use'       => true,           // se inactiva tras un pago APPROVED
        'collect_shipping' => false,
        'currency'         => 'COP',
        'amount_in_cents'  => $monto_centavos,
        'expires_at'       => $expira_iso,
    ];

    if (!empty($datos['redirect_url'])) {
        $payload['redirect_url'] = $datos['redirect_url'];
    }

    if (!empty($datos['customer_email']) 
        && filter_var($datos['customer_email'], FILTER_VALIDATE_EMAIL)) {
        $payload['customer_data'] = ['customer_email' => $datos['customer_email']];
    }

    $resp = wompi_http_request('POST', '/payment_links', $payload, $c['private_key']);

    if (!$resp['ok']) {
        return $resp;
    }

    $link_id = $resp['body']['data']['id'] ?? null;
    if (empty($link_id)) {
        log_error(['msg' => 'Wompi: respuesta sin id de link', 'body' => $resp['body']]);
        return ['ok' => false, 'error' => 'Respuesta inesperada de Wompi'];
    }

    // Wompi usa checkout.wompi.co tanto en sandbox como en produccion;
    // la llave usada para crear el link determina el ambiente.
    $checkout_url = 'https://checkout.wompi.co/l/' . $link_id;

    return [
        'ok'             => true,
        'link_id'        => $link_id,
        'checkout_url'   => $checkout_url,
        'monto_centavos' => $monto_centavos,
        'expira'         => $expira_iso,
        'referencia'     => $datos['referencia'],
    ];
}

// ============================================================
//  Consultar estado de transaccion
//  Fallback cuando el webhook tarda o se pierde. La idea es
//  llamar esto desde detalle_pedido.php si el estado_pago sigue
//  en 'pendiente' despues de N minutos.
// ============================================================

function wompi_consultar_transaccion(string $transaction_id): array {
    if (!wompi_esta_configurado()) {
        return ['ok' => false, 'error' => 'Wompi no esta configurado'];
    }
    $c = wompi_config();
    return wompi_http_request(
        'GET',
        '/transactions/' . urlencode($transaction_id),
        null,
        $c['private_key']
    );
}

// ============================================================
//  Validar firma de webhook
// ============================================================
//  Wompi envia POST con body JSON:
//  {
//    "event": "transaction.updated",
//    "data": { "transaction": { "id": "...", "status": "APPROVED", "reference": "PED-1-...", ... } },
//    "timestamp": 1530291411,
//    "signature": {
//      "properties": ["transaction.id", "transaction.status", "transaction.amount_in_cents"],
//      "checksum": "abc..."
//    }
//  }
//
//  Algoritmo de validacion (docs.wompi.co):
//    1. Concatenar los valores de las propiedades indicadas, en orden
//    2. Concatenar el timestamp
//    3. Concatenar el events_secret
//    4. SHA256 del string resultante
//    5. Comparar con signature.checksum usando hash_equals (timing-safe)
//
//  IMPORTANTE: properties puede variar entre eventos; no asumir
//  un orden fijo, siempre leer del evento.
// ============================================================

function wompi_validar_firma_webhook(array $evento): bool {
    if (!wompi_esta_configurado()) {
        log_error('Wompi webhook: sistema no configurado, rechazando evento');
        return false;
    }

    // Estructura minima del evento
    if (empty($evento['signature']['properties'])
        || !is_array($evento['signature']['properties'])
        || empty($evento['signature']['checksum'])
        || empty($evento['timestamp'])
        || empty($evento['data'])) {
        log_error('Wompi webhook: estructura invalida del evento');
        return false;
    }

    $c = wompi_config();
    $concatenacion = '';

    foreach ($evento['signature']['properties'] as $ruta) {
        $valor = wompi_obtener_valor_anidado($evento['data'], $ruta);
        if ($valor === null) {
            log_error(['msg' => 'Wompi webhook: propiedad faltante en signature', 'ruta' => $ruta]);
            return false;
        }
        $concatenacion .= $valor;
    }

    $concatenacion .= $evento['timestamp'];
    $concatenacion .= $c['events_secret'];

    $checksum_calculado = hash('sha256', $concatenacion);
    $checksum_recibido  = strtolower(trim((string) $evento['signature']['checksum']));

    $valido = hash_equals($checksum_calculado, $checksum_recibido);

    if (!$valido) {
        log_error([
            'msg'      => 'Wompi webhook: firma INVALIDA',
            'esperado' => $checksum_calculado,
            'recibido' => $checksum_recibido,
        ]);
    }

    return $valido;
}

// Extrae "transaction.id" -> $data['transaction']['id']
// Devuelve null si la ruta no existe (eso anula la firma).
function wompi_obtener_valor_anidado(array $data, string $ruta): ?string {
    $partes = explode('.', $ruta);
    $actual = $data;
    foreach ($partes as $parte) {
        if (!is_array($actual) || !array_key_exists($parte, $actual)) {
            return null;
        }
        $actual = $actual[$parte];
    }
    // Convertir cualquier escalar a string (Wompi compara como texto)
    if (is_bool($actual)) {
        return $actual ? 'true' : 'false';
    }
    if (is_null($actual)) {
        return '';
    }
    return (string) $actual;
}

// ============================================================
//  Mapear estado Wompi -> nuestro enum pedido_cliente.estado_pago
// ============================================================

function wompi_mapear_estado(string $estado_wompi): string {
    $map = [
        'PENDING'  => 'pendiente',
        'APPROVED' => 'aprobado',
        'DECLINED' => 'rechazado',
        'VOIDED'   => 'rechazado',
        'ERROR'    => 'rechazado',
        'EXPIRED'  => 'expirado',
    ];
    $key = strtoupper(trim($estado_wompi));
    return $map[$key] ?? 'pendiente';
}

// ============================================================
//  HTTP request via cURL
//  No usar este metodo directamente desde fuera del helper;
//  todas las llamadas pasan por wompi_crear_link_pago,
//  wompi_consultar_transaccion, etc.
// ============================================================

function wompi_http_request(string $metodo, string $endpoint, ?array $payload, string $token): array {
    $c   = wompi_config();
    $url = $c['base_url'] . $endpoint;

    $ch = curl_init();
    $opciones = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($metodo === 'POST') {
        $opciones[CURLOPT_POST] = true;
        $opciones[CURLOPT_POSTFIELDS] = json_encode($payload ?? new stdClass(), JSON_UNESCAPED_UNICODE);
    } elseif ($metodo === 'GET') {
        $opciones[CURLOPT_HTTPGET] = true;
    } else {
        $opciones[CURLOPT_CUSTOMREQUEST] = $metodo;
        if ($payload !== null) {
            $opciones[CURLOPT_POSTFIELDS] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }
    }

    curl_setopt_array($ch, $opciones);

    $body_raw  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_err  = curl_error($ch);
    curl_close($ch);

    if ($body_raw === false || !empty($curl_err)) {
        log_error([
            'msg'      => 'Wompi cURL fallo',
            'endpoint' => $endpoint,
            'error'    => $curl_err,
        ]);
        return ['ok' => false, 'error' => 'No se pudo conectar con Wompi'];
    }

    $body = json_decode($body_raw, true);

    if ($http_code >= 400) {
        $razon = $body['error']['reason']
            ?? $body['error']['type']
            ?? $body['error']['messages'][0]
            ?? 'Error HTTP ' . $http_code;

        log_error([
            'msg'       => 'Wompi respondio con error',
            'endpoint'  => $endpoint,
            'http_code' => $http_code,
            'body'      => $body,
        ]);

        return [
            'ok'        => false,
            'error'     => is_string($razon) ? $razon : 'Error en Wompi',
            'http_code' => $http_code,
            'body'      => $body,
        ];
    }

    return [
        'ok'        => true,
        'body'      => $body,
        'http_code' => $http_code,
    ];
}
