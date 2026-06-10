<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/wompi.php';

// Responder 200 inmediatamente — Wompi reintenta si no recibe 200
http_response_code(200);
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
if (empty($raw)) {
    echo json_encode(['ok' => false, 'error' => 'empty body']);
    exit;
}

$evento = json_decode($raw, true);
if (!is_array($evento)) {
    echo json_encode(['ok' => false, 'error' => 'invalid json']);
    exit;
}

// Validar firma antes de hacer cualquier cosa
if (!wompi_validar_firma_webhook($evento)) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'invalid signature']);
    exit;
}

// Solo procesar actualizaciones de transacciones
$event_name = $evento['event'] ?? '';
if ($event_name !== 'transaction.updated') {
    echo json_encode(['ok' => true, 'msg' => 'event ignored']);
    exit;
}

$tx           = $evento['data']['transaction'] ?? [];
$referencia   = $tx['reference'] ?? '';
$estado_wompi = $tx['status']    ?? '';
$tx_id        = $tx['id']        ?? '';

if (empty($referencia) || empty($estado_wompi)) {
    log_error(['msg' => 'Wompi webhook: referencia o estado ausente', 'data' => $tx]);
    echo json_encode(['ok' => false, 'error' => 'missing fields']);
    exit;
}

$nuevo_estado = wompi_mapear_estado($estado_wompi);

// PENDING no es terminal — ignorar hasta que llegue el estado definitivo
if ($nuevo_estado === 'pendiente') {
    echo json_encode(['ok' => true, 'msg' => 'still pending, waiting']);
    exit;
}

$pdo = getConexion();

// Buscar el pago por referencia
$stmt = $pdo->prepare("SELECT id_pago, estado FROM pago_pedido WHERE referencia = ? LIMIT 1");
$stmt->execute([$referencia]);
$pago = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$pago) {
    log_error(['msg' => 'Wompi webhook: referencia no encontrada', 'referencia' => $referencia, 'tx_id' => $tx_id]);
    echo json_encode(['ok' => false, 'error' => 'reference not found']);
    exit;
}

// Idempotencia: si ya está en el estado correcto, no hacer nada
if ($pago['estado'] === $nuevo_estado) {
    echo json_encode(['ok' => true, 'msg' => 'already processed']);
    exit;
}

$id_pago = (int) $pago['id_pago'];

try {
    $pdo->beginTransaction();

    // 1. Obtener el monto esperado y el recibido
    $stmt_monto = $pdo->prepare("SELECT monto FROM pago_pedido WHERE id_pago = ?");
    $stmt_monto->execute([$id_pago]);
    $monto_pago = (float)$stmt_monto->fetchColumn();

    $monto_centavos = (int)($tx['amount_in_cents'] ?? 0);
    $monto_recibido = $monto_centavos / 100.0;

    // 2. Registrar el abono de Wompi en pago_abono (solo si es aprobado)
    if ($nuevo_estado === 'aprobado') {
        $monto_abono = $monto_recibido > 0 ? $monto_recibido : $monto_pago;
        if ($monto_recibido > 0 && abs($monto_recibido - $monto_pago) > 0.01) {
            log_error([
                'msg' => 'Wompi webhook: Monto recibido difiere del esperado',
                'id_pago' => $id_pago,
                'esperado' => $monto_pago,
                'recibido' => $monto_recibido,
                'tx_id' => $tx_id
            ]);
        }

        $stmt_abono = $pdo->prepare("
            INSERT INTO pago_abono (id_pago, monto, metodo_pago, nota, fecha_abono)
            VALUES (?, ?, 'PSE', ?, NOW())
        ");
        $stmt_abono->execute([$id_pago, $monto_abono, 'Aprobación automática Wompi Tx: ' . $tx_id]);
    }

    // 3. Actualizar pago_pedido
    $pdo->prepare("UPDATE pago_pedido SET estado = ? WHERE id_pago = ?")
        ->execute([$nuevo_estado, $id_pago]);

    // 4. Actualizar todos los pedidos vinculados a este pago
    $pdo->prepare("UPDATE pedido_cliente SET estado_pago = ? WHERE id_pago_activo = ?")
        ->execute([$nuevo_estado, $id_pago]);

    $pdo->commit();

    log_error([
        'msg'         => 'Wompi webhook: pago procesado',
        'id_pago'     => $id_pago,
        'referencia'  => $referencia,
        'estado'      => $nuevo_estado,
        'tx_id'       => $tx_id,
    ]);

    echo json_encode(['ok' => true, 'estado' => $nuevo_estado]);

} catch (Exception $e) {
    $pdo->rollBack();
    log_error(['msg' => 'Wompi webhook: error BD', 'error' => $e->getMessage(), 'id_pago' => $id_pago]);
    echo json_encode(['ok' => false, 'error' => 'db error']);
}
