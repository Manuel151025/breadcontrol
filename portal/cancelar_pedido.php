<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

$id_pedido = (int)($_GET['id'] ?? 0);
$cliente_id = $_SESSION['cliente_id'];
$pdo = getConexion();

// Validar que el pedido pertenezca al cliente y esté pendiente
$stmt = $pdo->prepare("SELECT * FROM pedido_cliente WHERE id_pedido = ? AND (id_cliente = ? OR id_creador = ?)");
$stmt->execute([$id_pedido, $cliente_id, $cliente_id]);
$pedido = $stmt->fetch();

if (!$pedido || $pedido['estado'] !== 'pendiente') {
    header('Location: dashboard.php');
    exit;
}

// Validar restricción de 48 horas
$fecha_entrega = new DateTime($pedido['fecha_entrega']);
$ahora = new DateTime();
$diff = $ahora->diff($fecha_entrega);
$horas_restantes = ($diff->days * 24) + $diff->h;

$esta_vencido = $diff->invert == 1;
if ($esta_vencido || $horas_restantes < 48) {
    // Redirigir con error si se intenta cancelar por URL saltándose la validación frontal
    header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=limite_tiempo');
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Cambiar estado a rechazado (cancelación del cliente)
    $stmt_upd = $pdo->prepare("UPDATE pedido_cliente SET estado = 'rechazado', mensaje_propietario = 'Cancelado por el cliente' WHERE id_pedido = ?");
    $stmt_upd->execute([$id_pedido]);
    
    // Si formaba parte de un pago consolidado o individual, lo expiramos y desvinculamos todos los pedidos asociados
    if (!empty($pedido['id_pago_activo'])) {
        $id_pago = (int) $pedido['id_pago_activo'];
        
        $stmt_pay = $pdo->prepare("UPDATE pago_pedido SET estado = 'EXPIRED' WHERE id_pago = ? AND estado IN ('PENDING', 'pendiente')");
        $stmt_pay->execute([$id_pago]);
        
        $stmt_ped = $pdo->prepare("UPDATE pedido_cliente SET id_pago_activo = NULL, estado_pago = 'no_aplica' WHERE id_pago_activo = ?");
        $stmt_ped->execute([$id_pago]);
    }
    
    $pdo->commit();
    header('Location: dashboard.php?msg=cancelado');
} catch (Exception $e) {
    $pdo->rollBack();
    header('Location: detalle_pedido.php?id=' . $id_pedido . '&error=db');
}
