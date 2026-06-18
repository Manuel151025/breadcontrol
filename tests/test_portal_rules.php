<?php
// tests/test_portal_rules.php
// Pruebas unitarias para validar las reglas de negocio del Portal de Clientes.

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/funciones.php';

// Helper local para calcular el crédito según las reglas del portal
function testHelperCalcularCredito(string $tipo_cliente, float $total_dinero): float {
    return ($tipo_cliente === 'tienda') 
        ? floor($total_dinero / 5000) * 1000 
        : floor($total_dinero / 5000) * 500;
}

// Helper local para verificar si se puede gestionar un pedido según el límite de 48 horas
function testHelperVerificarPuedeGestionar(string $estado, string $fecha_entrega_str, string $ahora_str): bool {
    $fecha_entrega = new DateTime($fecha_entrega_str);
    $ahora = new DateTime($ahora_str);
    $diff = $ahora->diff($fecha_entrega);
    $horas_restantes = ($diff->days * 24) + $diff->h;
    $esta_vencido = $diff->invert == 1;
    $dentro_limite = (!$esta_vencido && $horas_restantes < 48);
    return ($estado === 'pendiente' && !$esta_vencido && !$dentro_limite);
}

// ==========================================
// 🧪 GRUPO 1: Cálculo de Crédito (Bonificación/Ñapa)
// ==========================================

// Tienda: Bonificación de $1000 por cada $5000
TestRunner::assertEquals(1000.0, (float)testHelperCalcularCredito('tienda', 5000), "Una tienda con $5000 gastados debe recibir $1000 de crédito.");
TestRunner::assertEquals(2000.0, (float)testHelperCalcularCredito('tienda', 12000), "Una tienda con $12000 gastados debe recibir $2000 de crédito.");
TestRunner::assertEquals(0.0, (float)testHelperCalcularCredito('tienda', 4999), "Una tienda con $4999 gastados debe recibir $0 de crédito.");
TestRunner::assertEquals(10000.0, (float)testHelperCalcularCredito('tienda', 54000), "Una tienda con $54000 gastados debe recibir $10000 de crédito.");

// Mostrador: Ñapa de $500 por cada $5000
TestRunner::assertEquals(500.0, (float)testHelperCalcularCredito('mostrador', 5000), "Un cliente mostrador con $5000 gastados debe recibir $500 de crédito.");
TestRunner::assertEquals(1000.0, (float)testHelperCalcularCredito('mostrador', 12000), "Un cliente mostrador con $12000 gastados debe recibir $1000 de crédito.");
TestRunner::assertEquals(0.0, (float)testHelperCalcularCredito('mostrador', 4999), "Un cliente mostrador con $4999 gastados debe recibir $0 de crédito.");
TestRunner::assertEquals(5000.0, (float)testHelperCalcularCredito('mostrador', 54000), "Un cliente mostrador con $54000 gastados debe recibir $5000 de crédito.");


// ==========================================
// 🧪 GRUPO 2: Límite de Gestión de Pedidos (48 horas)
// ==========================================

// Caso A: Pedido pendiente a 72 horas de entregarse (Debe permitir gestión)
TestRunner::assertTrue(
    testHelperVerificarPuedeGestionar('pendiente', '2026-06-15 12:00:00', '2026-06-12 12:00:00'),
    "Debe permitir gestionar un pedido pendiente que falte 72 horas para entregarse."
);

// Caso B: Pedido pendiente a 47 horas de entregarse (No debe permitir gestión)
TestRunner::assertTrue(
    !testHelperVerificarPuedeGestionar('pendiente', '2026-06-14 11:00:00', '2026-06-12 12:00:00'),
    "No debe permitir gestionar un pedido pendiente que falte menos de 48 horas (47 horas)."
);

// Caso C: Pedido ya vencido en el pasado (No debe permitir gestión)
TestRunner::assertTrue(
    !testHelperVerificarPuedeGestionar('pendiente', '2026-06-11 12:00:00', '2026-06-12 12:00:00'),
    "No debe permitir gestionar un pedido vencido en el pasado."
);

// Caso D: Pedido confirmado a 72 horas (No debe permitir gestión por no estar pendiente)
TestRunner::assertTrue(
    !testHelperVerificarPuedeGestionar('confirmado', '2026-06-15 12:00:00', '2026-06-12 12:00:00'),
    "No debe permitir gestionar un pedido que ya está confirmado, independientemente del tiempo."
);


// ==========================================
// 🧪 GRUPO 3: Bloqueo de Edición por Pago Pendiente del Instructor
// ==========================================

function testHelperVerificarBloqueoPago(bool $es_aprendiz, ?string $estado_pago): bool {
    if (!$es_aprendiz) {
        return false;
    }
    if ($estado_pago !== null && in_array(strtoupper($estado_pago), ['PENDING', 'PENDIENTE'])) {
        return true;
    }
    return false;
}

// Cliente normal con pago pendiente (No debe bloquearse)
TestRunner::assertTrue(
    !testHelperVerificarBloqueoPago(false, 'PENDING'),
    "Un cliente normal con pago pendiente del instructor no debe tener bloqueo de edición."
);

// Aprendiz con pago pendiente (Debe bloquearse)
TestRunner::assertTrue(
    testHelperVerificarBloqueoPago(true, 'PENDING'),
    "Un aprendiz con pago del instructor en estado PENDING debe tener bloqueo de edición."
);

TestRunner::assertTrue(
    testHelperVerificarBloqueoPago(true, 'pendiente'),
    "Un aprendiz con pago del instructor en estado 'pendiente' debe tener bloqueo de edición."
);

// Aprendiz con pago aprobado/pagado (No debe bloquearse)
TestRunner::assertTrue(
    !testHelperVerificarBloqueoPago(true, 'PAID'),
    "Un aprendiz con pago del instructor ya aprobado (PAID) no debe tener bloqueo de edición."
);

// Aprendiz sin pago activo (No debe bloquearse)
TestRunner::assertTrue(
    !testHelperVerificarBloqueoPago(true, null),
    "Un aprendiz sin pago activo no debe tener bloqueo de edición."
);


// ==========================================
// 🧪 GRUPO 4: Visibilidad y Cuentas de Pedidos de Aprendices
// ==========================================

function testHelperFiltrarPedidosPropietario(array $pedidos): array {
    return array_values(array_filter($pedidos, function($p) {
        return !($p['es_aprendiz'] == 1 && $p['aprobado_instructor'] == 0);
    }));
}

function testHelperCalcularCarteraInstructor(array $pedidos): float {
    $total = 0.0;
    foreach ($pedidos as $p) {
        if ($p['aprobado_instructor'] == 1) {
            $total += (float)$p['monto'];
        }
    }
    return $total;
}

$mock_pedidos = [
    ['id_pedido' => 1, 'es_aprendiz' => 0, 'aprobado_instructor' => 1, 'monto' => 5000.0], // Cliente normal
    ['id_pedido' => 2, 'es_aprendiz' => 1, 'aprobado_instructor' => 1, 'monto' => 8000.0], // Aprendiz aprobado
    ['id_pedido' => 3, 'es_aprendiz' => 1, 'aprobado_instructor' => 0, 'monto' => 6000.0], // Aprendiz pendiente aprobación
];

// Comprobar filtrado para el Propietario (Bandejas de producción/horneado)
$filtrados = testHelperFiltrarPedidosPropietario($mock_pedidos);
TestRunner::assertEquals(2, count($filtrados), "El propietario solo debe ver 2 pedidos (se oculta el del aprendiz pendiente).");
TestRunner::assertEquals(1, $filtrados[0]['id_pedido'], "El primer pedido visible debe ser el #1.");
TestRunner::assertEquals(2, $filtrados[1]['id_pedido'], "El segundo pedido visible debe ser el #2.");

// Comprobar cálculo de Cartera del Instructor
$cartera = testHelperCalcularCarteraInstructor($mock_pedidos);
TestRunner::assertEquals(13000.0, $cartera, "La cartera del instructor solo debe sumar los pedidos aprobados (5000 + 8000 = 13000). El de 6000 debe ignorarse.");


// ==========================================
// 🧪 GRUPO 5: Límite o Cupo de Consumo Semanal
// ==========================================

function testHelperVerificarExcesoCupo(float $monto_acumulado, float $monto_nuevo, float $cupo_semanal): bool {
    return ($monto_acumulado + $monto_nuevo) > $cupo_semanal;
}

// Caso A: Consumo semanal dentro del límite por defecto ($20,000)
TestRunner::assertTrue(
    !testHelperVerificarExcesoCupo(15000.0, 4000.0, 20000.0),
    "Consumo de $15000 + pedido de $4000 no debe exceder el cupo de $20000."
);

// Caso B: Consumo semanal sobrepasa el límite por defecto
TestRunner::assertTrue(
    testHelperVerificarExcesoCupo(15000.0, 6000.0, 20000.0),
    "Consumo de $15000 + pedido de $6000 debe exceder el cupo de $20000."
);

// Caso C: Consumo dentro del cupo personalizado por el instructor ($50,000)
TestRunner::assertTrue(
    !testHelperVerificarExcesoCupo(30000.0, 15000.0, 50000.0),
    "Consumo de $30000 + pedido de $15000 no debe exceder un cupo personalizado de $50000."
);

// Caso D: Consumo sobrepasa el cupo personalizado por el instructor
TestRunner::assertTrue(
    testHelperVerificarExcesoCupo(45000.0, 10000.0, 50000.0),
    "Consumo de $45000 + pedido de $10000 debe exceder un cupo personalizado de $50000."
);

// Caso E: Validación de límites de cupo (Máximo 100.000 COP y en múltiplos de 500)
function testHelperValidarBordesCupo(float $cupo): bool {
    if ($cupo < 0 || $cupo > 100000) {
        return false;
    }
    $cupo_int = (int)$cupo;
    if ($cupo_int % 500 !== 0 || $cupo != $cupo_int) {
        return false;
    }
    return true;
}

TestRunner::assertTrue(testHelperValidarBordesCupo(0), "Cupo de 0 debe ser válido.");
TestRunner::assertTrue(testHelperValidarBordesCupo(500), "Cupo de 500 debe ser válido.");
TestRunner::assertTrue(testHelperValidarBordesCupo(50000), "Cupo de 50000 debe ser válido.");
TestRunner::assertTrue(testHelperValidarBordesCupo(100000), "Cupo de 100000 debe ser válido.");
TestRunner::assertTrue(!testHelperValidarBordesCupo(100500), "Cupo superior a 100000 (100500) debe ser inválido.");
TestRunner::assertTrue(!testHelperValidarBordesCupo(-500), "Cupo negativo (-500) debe ser inválido.");
TestRunner::assertTrue(!testHelperValidarBordesCupo(450), "Cupo que no es múltiplo de 500 (450) debe ser inválido.");
TestRunner::assertTrue(!testHelperValidarBordesCupo(100000.5), "Cupo con decimales (100000.5) debe ser inválido.");

// ==========================================
// 🧪 GRUPO 6: Validación CSRF y Seguridad
// ==========================================
require_once __DIR__ . '/../includes/sesion.php';

// Si no hay sesión iniciada, validar_token_csrf con null o vacío debe retornar false
TestRunner::assertTrue(!validar_token_csrf(null), "Validar token nulo debe retornar false.");
TestRunner::assertTrue(!validar_token_csrf(''), "Validar token vacío debe retornar false.");

// Generar un token
$token = generar_token_csrf();
TestRunner::assertTrue(strlen($token) === 64, "El token generado debe ser una cadena hexadecimal de 64 caracteres (32 bytes).");

// Validar con el token correcto
TestRunner::assertTrue(validar_token_csrf($token), "Validar con el token correcto debe retornar true.");

// Validar con token incorrecto
TestRunner::assertTrue(!validar_token_csrf('incorrect_token'), "Validar con token incorrecto debe retornar false.");

// ==========================================
// 🧪 GRUPO 7: Validación de Horario de Entrega
// ==========================================

function testHelperValidarHorario(string $hora): bool {
    return ($hora >= '07:00' && $hora <= '20:00');
}

function testHelperCalcularMinFecha(string $hora_pedido): string {
    $min_fecha = date('Y-m-d');
    $hora_int = (int)explode(':', $hora_pedido)[0];
    if ($hora_int >= 20) {
        $min_fecha = date('Y-m-d', strtotime('+1 day'));
    }
    return $min_fecha;
}

// Horarios válidos
TestRunner::assertTrue(testHelperValidarHorario('07:00'), "7:00 AM debe ser un horario de entrega válido.");
TestRunner::assertTrue(testHelperValidarHorario('12:30'), "12:30 PM debe ser un horario de entrega válido.");
TestRunner::assertTrue(testHelperValidarHorario('20:00'), "8:00 PM (20:00) debe ser un horario de entrega válido.");

// Horarios inválidos
TestRunner::assertTrue(!testHelperValidarHorario('06:59'), "Antes de las 7:00 AM (06:59) debe ser inválido.");
TestRunner::assertTrue(!testHelperValidarHorario('20:01'), "Después de las 8:00 PM (20:01) debe ser inválido.");
TestRunner::assertTrue(!testHelperValidarHorario('22:00'), "Tarde en la noche (22:00) debe ser inválido.");

// Lógica de fecha mínima para pedidos nocturnos
$hoy = date('Y-m-d');
$manana = date('Y-m-d', strtotime('+1 day'));

TestRunner::assertEquals($hoy, testHelperCalcularMinFecha('10:00'), "Pedido a las 10:00 AM permite entrega el mismo día.");
TestRunner::assertEquals($hoy, testHelperCalcularMinFecha('19:59'), "Pedido a las 7:59 PM permite entrega el mismo día.");
TestRunner::assertEquals($manana, testHelperCalcularMinFecha('20:00'), "Pedido a las 8:00 PM (20:00) restringe la entrega a partir de mañana.");
TestRunner::assertEquals($manana, testHelperCalcularMinFecha('22:30'), "Pedido a las 10:30 PM restringe la entrega a partir de mañana.");

// ==========================================
// 🧪 GRUPO 8: Helper de Fecha de Entrega y Aprobación en Lote
// ==========================================

// Probar helper formatearFechaEntrega
$fecha_dummy = '1000-01-01 00:00:00';
$fecha_valida_con_hora = '2026-06-15 12:30:00';
$fecha_valida_sin_hora = '2026-06-15 00:00:00';

TestRunner::assertEquals(
    '<span style="color:#c62828; font-weight:700;"><i class="bi bi-clock-history"></i> Por definir (Tienda ADSO)</span>',
    formatearFechaEntrega($fecha_dummy, true),
    "Debe retornar HTML con badge de Por definir para fecha dummy."
);

TestRunner::assertEquals(
    'Por definir (Tienda ADSO)',
    formatearFechaEntrega($fecha_dummy, false),
    "Debe retornar texto simple para fecha dummy."
);

TestRunner::assertEquals(
    '15/06/2026 12:30 PM',
    formatearFechaEntrega($fecha_valida_con_hora, false),
    "Debe formatear fecha y hora válidas correctamente."
);

TestRunner::assertEquals(
    '15/06/2026',
    formatearFechaEntrega($fecha_valida_sin_hora, false),
    "Debe formatear fecha sin hora (00:00) correctamente."
);

// Probar operaciones en lote de base de datos
try {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../models/PortalClienteModel.php';
    
    $pdo = getConexion();
    $pdo->beginTransaction();
    
    // Crear un instructor de prueba temporal
    $stmt_inst = $pdo->prepare("
        INSERT INTO cliente (nombre, tipo, telefono, usuario, contrasena_hash, es_aprendiz, activo, fecha_creacion)
        VALUES ('Instructor Test Temp', 'tienda', '1234567', 'inst_test_temp', 'hash', 0, 1, NOW())
    ");
    $stmt_inst->execute();
    $id_instructor = (int)$pdo->lastInsertId();
    
    // Crear pedidos pendientes asociados al instructor
    $stmt_ped1 = $pdo->prepare("
        INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, aprobado_instructor, estado)
        VALUES (?, ?, '1000-01-01 00:00:00', 5000.0, 0, 'pendiente')
    ");
    $stmt_ped1->execute([$id_instructor, $id_instructor]);
    $id_ped1 = (int)$pdo->lastInsertId();
    
    $stmt_ped2 = $pdo->prepare("
        INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, aprobado_instructor, estado)
        VALUES (?, ?, '1000-01-01 00:00:00', 10000.0, 0, 'pendiente')
    ");
    $stmt_ped2->execute([$id_instructor, $id_instructor]);
    $id_ped2 = (int)$pdo->lastInsertId();
    
    $model = new PortalClienteModel($pdo);
    
    // 1. Probar aprobación en lote
    $fecha_aprobacion = '2026-06-20 08:30:00';
    $afectados_aprob = $model->aprobarPedidosInstructorLote([$id_ped1, $id_ped2], $id_instructor, $fecha_aprobacion);
    
    TestRunner::assertEquals(2, $afectados_aprob, "Debe reportar 2 pedidos aprobados.");
    
    // Verificar que se hayan actualizado
    $stmt_check = $pdo->prepare("SELECT aprobado_instructor, fecha_entrega, estado FROM pedido_cliente WHERE id_pedido = ?");
    
    $stmt_check->execute([$id_ped1]);
    $p1 = $stmt_check->fetch();
    TestRunner::assertEquals(1, (int)$p1['aprobado_instructor'], "El pedido 1 debe quedar aprobado por el instructor.");
    TestRunner::assertEquals('2026-06-20 08:30:00', $p1['fecha_entrega'], "El pedido 1 debe tener la nueva fecha de entrega.");
    TestRunner::assertEquals('pendiente', $p1['estado'], "El pedido 1 debe continuar en estado pendiente ante la panadería.");
    
    $stmt_check->execute([$id_ped2]);
    $p2 = $stmt_check->fetch();
    TestRunner::assertEquals(1, (int)$p2['aprobado_instructor'], "El pedido 2 debe quedar aprobado por el instructor.");
    TestRunner::assertEquals('2026-06-20 08:30:00', $p2['fecha_entrega'], "El pedido 2 debe tener la nueva fecha de entrega.");
    
    // 2. Probar rechazo en lote
    // Creamos otro pedido para rechazar
    $stmt_ped3 = $pdo->prepare("
        INSERT INTO pedido_cliente (id_cliente, id_creador, fecha_entrega, total_estimado, aprobado_instructor, estado)
        VALUES (?, ?, '1000-01-01 00:00:00', 3000.0, 0, 'pendiente')
    ");
    $stmt_ped3->execute([$id_instructor, $id_instructor]);
    $id_ped3 = (int)$pdo->lastInsertId();
    
    $afectados_rech = $model->rechazarPedidosInstructorLote([$id_ped3], $id_instructor);
    TestRunner::assertEquals(1, $afectados_rech, "Debe reportar 1 pedido rechazado.");
    
    $stmt_check->execute([$id_ped3]);
    $p3 = $stmt_check->fetch();
    TestRunner::assertEquals(0, (int)$p3['aprobado_instructor'], "El pedido 3 no debe marcarse como aprobado por el instructor.");
    TestRunner::assertEquals('rechazado', $p3['estado'], "El pedido 3 debe quedar en estado rechazado.");
    
    $pdo->rollBack();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
