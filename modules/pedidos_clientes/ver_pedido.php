<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';

requerirPropietario();
$pdo  = getConexion();
$user = usuarioActual();

$id_pedido = (int)($_GET['id'] ?? 0);
$msg_ok = '';
$msg_err = '';

$config_pago = $pdo->query("SELECT nequi_link_pago, nequi_titular, wompi_habilitado, wompi_confirmar_auto FROM configuracion LIMIT 1")->fetch();

$metodos_legibles = [
    'NEQUI' => 'Nequi',
    'BANCOLOMBIA' => 'Bancolombia',
    'PSE' => 'PSE',
    'TARJETA' => 'Tarjeta',
    'OTRO' => 'Otro medio',
];

function cargar_pedido(PDO $pdo, int $id_pedido) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.nombre as cliente, c.tipo as tipo_cliente, c.telefono, c2.nombre as nombre_creador
        FROM pedido_cliente p
        JOIN cliente c ON p.id_cliente = c.id_cliente
        LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
        WHERE p.id_pedido = ?
    ");
    $stmt->execute([$id_pedido]);
    return $stmt->fetch();
}

// (1) Actualizar estado del pedido
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar'])) {
    $estado = $_POST['estado'] ?? 'pendiente';
    $mensaje = trim($_POST['mensaje_propietario'] ?? '');
    if (in_array($estado, ['pendiente', 'confirmado', 'rechazado'])) {
        $stmt_upd = $pdo->prepare("UPDATE pedido_cliente SET estado = ?, mensaje_propietario = ? WHERE id_pedido = ?");
        if ($stmt_upd->execute([$estado, $mensaje, $id_pedido])) {
            $msg_ok = 'Pedido actualizado correctamente.';
        } else {
            $msg_err = 'Error al actualizar pedido.';
        }
    }
}

// (2) Habilitar pago digital (usar link unico de configuracion)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['habilitar_pago'])) {
    if (empty($config_pago['nequi_link_pago'])) {
        $msg_err = 'Primero configura tu link de Nequi Negocios en Configuracion > Pagos.';
    } else {
        try {
            $pdo->beginTransaction();
            $pedido_tmp = cargar_pedido($pdo, $id_pedido);
            $monto = (float) $pedido_tmp['total_estimado'];
            $monto_centavos = (int) round($monto * 100);
            $referencia = sprintf('PED-%d-%d', $id_pedido, (int) (microtime(true) * 1000));

            $link_id = null;
            if (preg_match('#/l/([A-Za-z0-9_-]+)#', $config_pago['nequi_link_pago'], $m)) {
                $link_id = $m[1];
            }

            $stmt = $pdo->prepare("
                INSERT INTO pago_pedido
                  (id_pedido, referencia, wompi_link_id, wompi_link_url, monto, monto_centavos, estado, fecha_expiracion)
                VALUES (?, ?, ?, ?, ?, ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmt->execute([$id_pedido, $referencia, $link_id, $config_pago['nequi_link_pago'], $monto, $monto_centavos]);
            $id_pago_nuevo = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare("UPDATE pedido_cliente SET estado_pago='pendiente', id_pago_activo=? WHERE id_pedido=?");
            $stmt->execute([$id_pago_nuevo, $id_pedido]);

            $pdo->commit();
            $msg_ok = 'Pago habilitado. El cliente ya puede pagar desde su portal.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_err = 'Error: ' . $e->getMessage();
        }
    }
}

// (3) Marcar como pagado (con monto recibido verificable)
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['marcar_pagado'])) {
    $metodo = $_POST['metodo_pago'] ?? 'NEQUI';
    $monto_recibido = (float) ($_POST['monto_recibido'] ?? 0);
    $nota = trim($_POST['nota_pago'] ?? '');

    $metodos_validos = ['NEQUI', 'BANCOLOMBIA', 'PSE', 'TARJETA', 'OTRO'];
    if (!in_array($metodo, $metodos_validos, true)) $metodo = 'OTRO';

    $pedido_tmp = cargar_pedido($pdo, $id_pedido);
    if (empty($pedido_tmp['id_pago_activo'])) {
        $msg_err = 'No hay un pago activo para este pedido.';
    } elseif ($monto_recibido <= 0) {
        $msg_err = 'Debes ingresar el monto recibido.';
    } else {
        try {
            $pdo->beginTransaction();
            $id_pago_activo = (int)$pedido_tmp['id_pago_activo'];

            // 1. Insertar el abono individual en la tabla pago_abono
            $stmt_abono = $pdo->prepare("
                INSERT INTO pago_abono (id_pago, monto, metodo_pago, nota, fecha_abono)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt_abono->execute([$id_pago_activo, $monto_recibido, $metodo, $nota ?: null]);

            // 2. Calcular la suma total acumulada de todos los abonos para este pago
            $stmt_sum_ab = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
            $stmt_sum_ab->execute([$id_pago_activo]);
            $total_abonado = (float)$stmt_sum_ab->fetchColumn();

            // 3. Calcular el total esperado consolidado de los pedidos
            $stmt_sum = $pdo->prepare("SELECT SUM(total_estimado) FROM pedido_cliente WHERE id_pago_activo = ?");
            $stmt_sum->execute([$id_pago_activo]);
            $total_esperado = (float)$stmt_sum->fetchColumn();

            // 4. Determinar estado de pago de los pedidos y de pago_pedido
            $es_pago_parcial = ($total_abonado < ($total_esperado - 1));
            $nuevo_estado_pago = $es_pago_parcial ? 'parcial' : 'aprobado';
            $nuevo_estado_pago_pedido = $es_pago_parcial ? 'PARTIAL' : 'APPROVED';

            $total_abonado_centavos = (int) round($total_abonado * 100);

            // 5. Actualizar el pago_pedido acumulativo
            $stmt = $pdo->prepare("
                UPDATE pago_pedido
                SET estado = ?, fecha_pago = NOW(), metodo_pago = ?, nota = ?, monto = ?, monto_centavos = ?
                WHERE id_pago = ?
            ");
            $stmt->execute([$nuevo_estado_pago_pedido, $metodo, $nota ?: null, $total_abonado, $total_abonado_centavos, $id_pago_activo]);

            // 6. Actualizar pedidos
            $stmt = $pdo->prepare("UPDATE pedido_cliente SET estado_pago = ? WHERE id_pago_activo = ?");
            $stmt->execute([$nuevo_estado_pago, $id_pago_activo]);

            // Forzar estado confirmado en los pedidos pendientes agrupados
            $stmt = $pdo->prepare("UPDATE pedido_cliente SET estado = 'confirmado' WHERE id_pago_activo = ? AND estado = 'pendiente'");
            $stmt->execute([$id_pago_activo]);

            $pdo->commit();
            $msg_ok = 'Abono confirmado y registrado con éxito.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_err = 'Error: ' . $e->getMessage();
        }
    }
}

// (4) Deshabilitar pago
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deshabilitar_pago'])) {
    $pedido_tmp = cargar_pedido($pdo, $id_pedido);
    if (!empty($pedido_tmp['id_pago_activo'])) {
        try {
            $pdo->beginTransaction();
            $id_pago = $pedido_tmp['id_pago_activo'];
            $pdo->prepare("UPDATE pago_pedido SET estado='EXPIRED' WHERE id_pago=?")->execute([$id_pago]);
            $pdo->prepare("UPDATE pedido_cliente SET estado_pago='no_aplica', id_pago_activo=NULL WHERE id_pago_activo=?")->execute([$id_pago]);
            $pdo->commit();
            $msg_ok = 'Pago deshabilitado.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_err = 'Error: ' . $e->getMessage();
        }
    }
}

// (5) Revertir pago aprobado
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['revertir_pago'])) {
    $pedido_tmp = cargar_pedido($pdo, $id_pedido);
    if (!empty($pedido_tmp['id_pago_activo'])) {
        try {
            $pdo->beginTransaction();
            $id_pago = $pedido_tmp['id_pago_activo'];
            $pdo->prepare("UPDATE pago_pedido SET estado='VOIDED' WHERE id_pago=?")->execute([$id_pago]);
            $pdo->prepare("UPDATE pedido_cliente SET estado_pago='no_aplica', id_pago_activo=NULL WHERE id_pago_activo=?")->execute([$id_pago]);
            $pdo->commit();
            $msg_ok = 'Pago revertido.';
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_err = 'Error: ' . $e->getMessage();
        }
    }
}

$pedido = cargar_pedido($pdo, $id_pedido);
if (!$pedido) { header('Location: index.php'); exit; }

$stmt_det = $pdo->prepare("SELECT d.*, vp.nombre as producto FROM pedido_cliente_detalle d JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad WHERE d.id_pedido = ?");
$stmt_det->execute([$id_pedido]);
$detalles = $stmt_det->fetchAll();

$pago_activo = null;
$pedidos_consolidados = [];
$es_consolidado = false;
$abonos = [];
$total_pagado = 0.0;
if (!empty($pedido['id_pago_activo'])) {
    $stmt = $pdo->prepare("SELECT * FROM pago_pedido WHERE id_pago = ?");
    $stmt->execute([$pedido['id_pago_activo']]);
    $pago_activo = $stmt->fetch();
    
    if ($pago_activo) {
        $stmt_con = $pdo->prepare("SELECT id_pedido, total_estimado FROM pedido_cliente WHERE id_pago_activo = ?");
        $stmt_con->execute([$pedido['id_pago_activo']]);
        $pedidos_consolidados = $stmt_con->fetchAll(PDO::FETCH_ASSOC);
        $es_consolidado = count($pedidos_consolidados) > 1;

        // Cargar el historial de abonos
        $stmt_ab = $pdo->prepare("SELECT * FROM pago_abono WHERE id_pago = ? ORDER BY fecha_abono ASC");
        $stmt_ab->execute([$pedido['id_pago_activo']]);
        $abonos = $stmt_ab->fetchAll();
        foreach ($abonos as $ab) {
            $total_pagado += (float)$ab['monto'];
        }
    }
}

$hace_pago = '';
if ($pago_activo && !empty($pago_activo['fecha_creacion'])) {
    $diff = (new DateTime())->diff(new DateTime($pago_activo['fecha_creacion']));
    if ($diff->days >= 1) {
        $hace_pago = 'hace ' . $diff->days . ' día' . ($diff->days > 1 ? 's' : '');
    } else {
        $h = $diff->h;
        $hace_pago = $h > 0 ? "hace {$h}h" : 'hace menos de 1h';
    }
}

$estado_pago      = $pedido['estado_pago'] ?? 'no_aplica';
$pago_configurado = !empty($config_pago['nequi_link_pago']);

$page_title = 'Detalle de Pedido';
require_once __DIR__ . '/../../views/layouts/header.php';
?>
<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--nav-h:64px;}
  .page{margin-top:var(--nav-h);padding:1.5rem;min-height:calc(100vh - var(--nav-h));}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1.5rem;}
  .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
  .mod-titulo i{color:var(--c3);}
  .btn-back{background:var(--ccard);color:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.45rem .9rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;}
  .btn-back:hover{background:var(--clight);border-color:var(--c3);color:var(--ink);}

  .grid-layout{display:grid;grid-template-columns:2fr 1fr;gap:1.5rem;align-items:start;}
  @media(max-width:800px){.grid-layout{grid-template-columns:1fr;}}

  .card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:1.5rem;}
  .card h3{font-family:'Fraunces',serif;color:var(--c1);margin-top:0;border-bottom:1px solid var(--border);padding-bottom:.8rem;margin-bottom:1rem;}

  .info-p{font-size:.9rem;margin-bottom:.5rem;color:var(--ink);}
  .info-p strong{color:var(--ink2);display:inline-block;width:120px;}

  .det-list{border:1px solid var(--border);border-radius:8px;overflow:hidden;margin-top:1rem;}
  .det-item{display:flex;justify-content:space-between;padding:.8rem 1rem;border-bottom:1px solid var(--border);}
  .det-item:last-child{border-bottom:none;}
  .det-item .name{font-weight:600;}
  .det-item .cant{background:var(--clight);color:var(--c3);padding:.2rem .5rem;border-radius:6px;font-weight:700;font-size:.85rem;}

  .total-box{margin-top:1rem;background:linear-gradient(135deg,rgba(198,113,36,.08),rgba(148,91,53,.04));border:1px solid rgba(198,113,36,.2);border-radius:10px;padding:1rem;display:flex;justify-content:space-between;align-items:center;}
  .total-lbl{font-size:.85rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink2);font-weight:700;}
  .total-val{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;color:var(--c3);}

  .form-group{margin-bottom:1rem;}
  .form-group label{display:block;margin-bottom:.4rem;font-size:.8rem;font-weight:700;color:var(--ink3);text-transform:uppercase;}
  .form-group select,.form-group textarea,.form-group input[type=text],.form-group input[type=number]{width:100%;padding:.7rem;border:1px solid var(--border);border-radius:8px;font-family:inherit;font-size:.9rem;box-sizing:border-box;}
  .form-group select:focus,.form-group textarea:focus,.form-group input:focus{outline:none;border-color:var(--c3);}

  .btn-save{width:100%;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:8px;padding:.8rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;transition:all .2s;}
  .btn-save:hover{transform:translateY(-2px);box-shadow:0 4px 15px rgba(198,113,36,.3);}

  .msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:3px solid #2e7d32;border-radius:10px;padding:.6rem .9rem;font-size:.82rem;color:#1b5e20;font-weight:600;margin-bottom:.6rem;}
  .msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:3px solid #c62828;border-radius:10px;padding:.6rem .9rem;font-size:.82rem;color:#c62828;margin-bottom:.6rem;}

  .estado-badge{font-size:.75rem;font-weight:700;padding:.3rem .8rem;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;display:inline-block;margin-bottom:1rem;}
  .e-pendiente{background:#fff3e0;color:#e65100;border:1px solid #ffcc80;}
  .e-confirmado{background:#e8f5e9;color:#2e7d32;border:1px solid #a5d6a7;}
  .e-rechazado{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}

  .pago-card{margin-top:1.5rem;}
  .pago-card h3{display:flex;align-items:center;gap:.6rem;flex-wrap:wrap;}
  .pago-card h3 .badge-estado{font-size:.65rem;padding:.25rem .6rem;border-radius:20px;text-transform:uppercase;letter-spacing:.05em;font-weight:700;}
  .badge-pago-pendiente{background:#e65100;color:#fff;border:1px solid #e65100;}
  .badge-pago-aprobado{background:#2e7d32;color:#fff;border:1px solid #2e7d32;}
  .badge-pago-parcial{background:#0288d1;color:#fff;border:1px solid #0288d1;}
  .badge-pago-sinpago{background:#f5f5f5;color:#757575;border:1px solid #e0e0e0;}

  .pago-data-grid{display:grid;grid-template-columns:1fr 1fr;gap:.8rem;margin-bottom:1rem;}
  @media(max-width:700px){.pago-data-grid{grid-template-columns:1fr;}}
  .pago-data-grid>div{background:var(--clight);border-radius:8px;padding:.7rem .9rem;}
  .pago-data-grid .lbl{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink3);font-weight:700;margin-bottom:.2rem;}
  .pago-data-grid .val{font-size:.95rem;color:var(--ink);font-weight:600;}
  .pago-data-grid .val-monto{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;color:var(--c3);line-height:1.1;}

  .btn-pagado{background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;border:none;border-radius:8px;padding:.7rem 1rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.4rem;transition:all .2s;}
  .btn-pagado:hover{transform:translateY(-1px);box-shadow:0 4px 15px rgba(46,125,50,.3);}

  .btn-habilitar{background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;border:none;border-radius:10px;padding:.9rem 1.4rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.5rem;transition:all .2s;font-size:.95rem;}
  .btn-habilitar:hover{transform:translateY(-1px);box-shadow:0 6px 20px rgba(46,125,50,.3);}

  .btn-secundario{background:transparent;color:var(--ink2);border:1px solid var(--border);border-radius:8px;padding:.6rem 1rem;font-weight:600;font-size:.82rem;cursor:pointer;transition:all .2s;}
  .btn-secundario:hover{background:var(--clight);border-color:var(--c3);color:var(--c1);}
  .btn-danger{background:transparent;color:#b91c1c;border:1px solid #fca5a5;border-radius:8px;padding:.6rem 1rem;font-weight:600;font-size:.82rem;cursor:pointer;transition:all .2s;display:inline-flex;align-items:center;gap:.4rem;}
  .btn-danger:hover{background:#ffebee;border-color:#b91c1c;}
  .aviso-diferencia{display:none;background:#fff8e1;border:1px solid #fde68a;border-left:3px solid #f59e0b;border-radius:8px;padding:.65rem .9rem;font-size:.78rem;color:#92400e;margin-top:.4rem;}
  .nota-requerida label::after{content:' *';color:#b91c1c;}

  .aviso-config{background:#fff8e1;border:1px solid #ffe082;border-left:3px solid #ffb300;border-radius:10px;padding:1rem;font-size:.85rem;color:#856404;}
  .aviso-config a{color:var(--c3);font-weight:700;text-decoration:none;}
  .aviso-config a:hover{text-decoration:underline;}

  .aviso-no-confirmado{background:#fff3e0;border:1px solid #ffcc80;border-left:3px solid #e65100;border-radius:10px;padding:1rem;font-size:.85rem;color:#e65100;}

  details.revertir{margin-top:1rem;}
  details.revertir summary{font-size:.8rem;color:var(--ink3);cursor:pointer;}
  details.revertir summary:hover{color:var(--c3);}
</style>

<div class="page">
    <div class="topbar">
        <div class="mod-titulo"><i class="bi bi-file-earmark-text"></i> Revisar Pedido #<?= str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?></div>
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver a pedidos</a>
    </div>

    <?php if ($msg_ok): ?><div class="msg-ok"><?= $msg_ok ?></div><?php endif; ?>
    <?php if ($msg_err): ?><div class="msg-err"><?= $msg_err ?></div><?php endif; ?>

    <?php if ($estado_pago === 'parcial' && $pago_activo): ?>
    <?php
        $total_esperado_p = 0;
        if (!empty($pedidos_consolidados)) {
            foreach ($pedidos_consolidados as $pc) {
                $total_esperado_p += (float)$pc['total_estimado'];
            }
        } else {
            $total_esperado_p = (float)$pedido['total_estimado'];
        }
        $deuda = $total_esperado_p - $total_pagado;
    ?>
    <div style="background:#fff5f5; border:1px solid #fee2e2; border-left:4px solid #ef4444; border-radius:12px; padding:1rem 1.2rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:.75rem; box-shadow: 0 1px 5px rgba(239,68,68,.08);">
        <i class="bi bi-exclamation-octagon-fill" style="color:#ef4444; font-size:1.25rem; flex-shrink:0; margin-top:.1rem;"></i>
        <div>
            <div style="font-weight:800; color:#991b1b; font-size:.9rem; margin-bottom:.2rem;">⚠️ ADVERTENCIA: PAGO PARCIAL PENDIENTE</div>
            <div style="font-size:.84rem; color:#7f1d1d; line-height:1.5;">
                Este pedido cuenta con un pago parcial registrado de <strong>$<?= number_format($total_pagado, 0, ',', '.') ?> COP</strong>. 
                El cliente aún debe <strong style="text-decoration: underline; font-size:.9rem;">$<?= number_format($deuda, 0, ',', '.') ?> COP</strong>.
                Por favor, cobra el saldo pendiente de <strong>$<?= number_format($deuda, 0, ',', '.') ?> COP</strong> antes de realizar la entrega del producto.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid-layout">
        <div class="card">
            <h3>Datos del Pedido</h3>
            <span class="estado-badge e-<?= $pedido['estado'] ?>">ESTADO: <?= $pedido['estado'] ?></span>

            <div class="info-p"><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente']) ?> (<?= $pedido['tipo_cliente'] ?>)</div>
            <div class="info-p"><strong>Digitado por:</strong> <?= htmlspecialchars($pedido['nombre_creador'] ?? 'Mismo cliente') ?></div>
            <div class="info-p"><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?: 'No registrado') ?></div>
            <div class="info-p"><strong>Fecha Entrega:</strong> <span style="color:var(--c1); font-weight:700;"><?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?></span></div>
            <div class="info-p"><strong>Solicitado el:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_solicitud'])) ?></div>

            <h4 style="margin-top:1.5rem; color:var(--ink2);">Productos Solicitados</h4>
            <div class="det-list">
                <?php foreach($detalles as $d): ?>
                <div class="det-item">
                    <span class="name">
                        <?= htmlspecialchars($d['producto']) ?>
                        <?php if ($d['napa'] > 0): ?>
                            <span style="font-size:.7rem;color:#c67124;font-weight:700;margin-left:5px;">(🎁 Ñapa)</span>
                        <?php elseif ($d['bonificacion'] > 0): ?>
                            <span style="font-size:.7rem;color:#1565c0;font-weight:700;margin-left:5px;">(🏪 Bonificación)</span>
                        <?php endif; ?>
                    </span>
                    <span class="cant"><?= $d['cantidad'] > 0 ? $d['cantidad'] : ($d['napa'] > 0 ? $d['napa'] : $d['bonificacion']) ?> und</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="total-box">
                <span class="total-lbl">Total Estimado</span>
                <span class="total-val">$<?= number_format($pedido['total_estimado'], 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="card">
            <h3><i class="bi bi-pencil-square"></i> Gestión</h3>
            <form method="post">
                <div class="form-group">
                    <label>Estado del pedido</label>
                    <select name="estado">
                        <option value="pendiente" <?= $pedido['estado'] == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="confirmado" <?= $pedido['estado'] == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="rechazado" <?= $pedido['estado'] == 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mensaje para el cliente (Opcional)</label>
                    <textarea name="mensaje_propietario" rows="4" placeholder="Ej: Pedido confirmado, pasas el viernes."><?= htmlspecialchars($pedido['mensaje_propietario'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="actualizar" class="btn-save"><i class="bi bi-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- ====== TARJETA: PAGO DIGITAL ====== -->
    <?php if ($pedido['estado'] === 'rechazado' && $estado_pago === 'aprobado'): ?>
    <div style="background:#fff0f0;border:1px solid #fca5a5;border-left:4px solid #dc2626;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;">
        <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.15rem;flex-shrink:0;margin-top:.1rem;"></i>
        <div>
            <div style="font-weight:700;color:#991b1b;font-size:.9rem;margin-bottom:.2rem;">Pedido rechazado con pago registrado</div>
            <div style="font-size:.82rem;color:#7f1d1d;line-height:1.5;">
                Este pedido está <strong>rechazado</strong> pero figura con un pago <strong>aprobado</strong>.
                Si el rechazo fue un error, cambia el estado en la tarjeta de Gestión.
                Si el pago fue incorrecto, usa la opción <em>"Revertir este pago"</em> más abajo.
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card pago-card">
        <h3>
            <span><i class="bi bi-credit-card-2-front"></i> Pago Digital</span>
            <?php
            $badge_clase = 'badge-pago-sinpago';
            $badge_texto = 'Sin habilitar';
            if ($estado_pago === 'pendiente') { $badge_clase = 'badge-pago-pendiente'; $badge_texto = 'Esperando pago'; }
            elseif ($estado_pago === 'aprobado') { $badge_clase = 'badge-pago-aprobado'; $badge_texto = 'Pagado'; }
            elseif ($estado_pago === 'parcial') { $badge_clase = 'badge-pago-parcial'; $badge_texto = 'Pago Parcial'; }
            ?>
            <span class="badge-estado <?= $badge_clase ?>"><?= $badge_texto ?></span>
        </h3>

        <?php if ($es_consolidado): ?>
            <div style="background:#e8f4fd; border:1px solid #b3e5fc; border-left:4px solid #0288d1; border-radius:10px; padding:.85rem 1rem; font-size:.82rem; color:#01579b; margin-bottom:1rem; line-height:1.5;">
                <i class="bi bi-info-circle-fill" style="margin-right:.3rem; font-size: 1rem; vertical-align: middle;"></i>
                <strong>Pago Consolidado Agrupado:</strong> Este pedido está agrupado con otros 
                <strong><?= count($pedidos_consolidados) - 1 ?></strong> pedido(s) de este cliente bajo una única transacción de pago.
                <br>
                <span style="font-size:.78rem; opacity:.95; display:inline-block; margin-top:.35rem;">
                    Pedidos incluidos: 
                    <?= implode(', ', array_map(fn($p) => '#' . str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT), $pedidos_consolidados)) ?>
                    &nbsp;·&nbsp; Monto total consolidado: <strong>$<?= number_format($pago_activo['monto'] ?? 0, 0, ',', '.') ?> COP</strong>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!$pago_configurado): ?>
            <div class="aviso-config">
                <i class="bi bi-info-circle-fill"></i>
                Aún no has configurado tu link de Nequi Negocios.
                <a href="<?= APP_URL ?>/modules/configuracion/pagos.php">Ir a Configuración → Pagos</a> para configurarlo.
            </div>

        <?php elseif ($estado_pago === 'no_aplica' && $pedido['estado'] !== 'confirmado'): ?>
            <div class="aviso-no-confirmado">
                <i class="bi bi-info-circle-fill"></i>
                Primero confirma este pedido (en la tarjeta Gestión de arriba) para habilitar el pago digital.
            </div>

        <?php elseif ($estado_pago === 'no_aplica' && $pedido['estado'] === 'confirmado'): ?>
            <p style="font-size:.9rem;color:var(--ink2);margin-bottom:1rem;">
                Al habilitar el pago digital, el cliente verá en su portal un botón <strong>"Pagar ahora"</strong> que lo llevará a tu link de Nequi Negocios para pagar <strong>$<?= number_format($pedido['total_estimado'], 0, ',', '.') ?></strong>.
            </p>
            <form method="post">
                <button type="submit" name="habilitar_pago" class="btn-habilitar">
                    <i class="bi bi-cash-coin"></i> Habilitar pago digital
                </button>
            </form>

        <?php elseif (in_array($estado_pago, ['pendiente', 'parcial']) && $pago_activo): ?>
            <?php
            $total_esperado_p = 0;
            if (!empty($pedidos_consolidados)) {
                foreach ($pedidos_consolidados as $pc) {
                    $total_esperado_p += (float)$pc['total_estimado'];
                }
            } else {
                $total_esperado_p = (float)$pedido['total_estimado'];
            }
            $deuda_restante = $total_esperado_p - $total_pagado;
            ?>
            <p style="font-size:.9rem;color:var(--ink2);margin-bottom:1rem;">
                El cliente ya puede pagar desde su portal. Registra los abonos recibidos en tu cuenta aquí.
            </p>

            <div class="pago-data-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.2rem;">
                <div>
                    <div class="lbl">Total Esperado</div>
                    <div class="val" style="font-weight:700;">$<?= number_format($total_esperado_p, 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Total Abonado</div>
                    <div class="val" style="font-weight:700; color:var(--pago-green-dk);">$<?= number_format($total_pagado, 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Saldo Restante</div>
                    <div class="val-monto" style="color: #b91c1c; font-size: 1.4rem;">$<?= number_format($deuda_restante, 0, ',', '.') ?></div>
                </div>
            </div>

            <?php if (!empty($abonos)): ?>
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1rem;margin-bottom:.6rem;margin-top:1rem;">
                    Historial de Abonos Recibidos
                </h4>
                <div style="overflow-x:auto; margin-bottom: 1.2rem; border: 1px solid var(--border); border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                        <thead>
                            <tr style="background: var(--clight); border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Fecha</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Medio</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3); text-align: right;">Monto</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonos as $ab): ?>
                                <tr style="border-bottom: 1px solid rgba(148,91,53,.05);">
                                    <td style="padding: 0.5rem 0.75rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                    <td style="padding: 0.5rem 0.75rem;"><span style="font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></span></td>
                                    <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: var(--pago-green-dk);">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                    <td style="padding: 0.5rem 0.75rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid var(--border);margin:1.2rem 0;">

            <form method="post">
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1.05rem;margin-bottom:.8rem;">
                    Confirmar abono recibido
                </h4>

                <div class="form-group">
                    <label>Monto del abono</label>
                    <input type="number" id="inp-monto-recibido" name="monto_recibido" step="1" min="1"
                           value="<?= (int) $deuda_restante ?>"
                           data-esperado="<?= (int) $deuda_restante ?>"
                           oninput="onMontoChange()" required>
                    <small style="font-size:.72rem;color:var(--ink3);display:block;margin-top:.3rem;">
                        Verifica el monto de la transferencia en tu app Nequi/Bancolombia.
                    </small>
                    <div class="aviso-diferencia" id="aviso-diferencia">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        El monto difiere del saldo restante (<strong>$<?= number_format($deuda_restante, 0, ',', '.') ?></strong>).
                        Agrega una nota explicando la diferencia.
                    </div>
                </div>

                <div class="form-group">
                    <label>¿Cómo recibiste el pago?</label>
                    <select id="inp-metodo" name="metodo_pago">
                        <option value="NEQUI">Nequi</option>
                        <option value="BANCOLOMBIA">Bancolombia</option>
                        <option value="PSE">PSE (otro banco)</option>
                        <option value="TARJETA">Tarjeta débito/crédito</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>

                <div class="form-group" id="grupo-nota">
                    <label id="lbl-nota">Nota</label>
                    <textarea id="inp-nota" name="nota_pago" rows="2" placeholder="Ej: ID de transacción Nequi M12345"></textarea>
                </div>

                <button type="submit" name="marcar_pagado" class="btn-pagado"
                        onclick="return validarFormPago() && confirm('¿Confirmas que ya recibiste este abono?')">
                    <i class="bi bi-check-circle-fill"></i> Confirmar abono recibido
                </button>
            </form>

            <form method="post" style="margin-top:1.2rem;">
                <p style="font-size:.77rem;color:#9ca3af;margin-bottom:.5rem;line-height:1.5;">
                    <i class="bi bi-info-circle"></i> Deshabilitar retira el botón de pago del portal del cliente. El pedido queda sin pago hasta que lo vuelvas a habilitar.
                </p>
                <button type="submit" name="deshabilitar_pago" class="btn-danger" onclick="return confirm('Esto retira el link de pago del portal del cliente. ¿Continuar?')">
                    <i class="bi bi-x-circle"></i> Deshabilitar pago digital
                </button>
            </form>

        <?php elseif ($estado_pago === 'aprobado' && $pago_activo): ?>
            <p style="font-size:.95rem;color:#1b5e20;margin-bottom:1rem;font-weight:600;">
                <i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i>
                Pago recibido y verificado.
            </p>

            <div class="pago-data-grid">
                <div>
                    <div class="lbl">Monto pagado</div>
                    <div class="val">$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Método del último abono</div>
                    <div class="val"><?= htmlspecialchars($metodos_legibles[$pago_activo['metodo_pago']] ?? $pago_activo['metodo_pago'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="lbl">Fecha de aprobación</div>
                    <div class="val"><?= $pago_activo['fecha_pago'] ? date('d/m/Y H:i', strtotime($pago_activo['fecha_pago'])) : '—' ?></div>
                </div>
                <div>
                    <div class="lbl">Referencia</div>
                    <div class="val" style="font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($pago_activo['referencia']) ?></div>
                </div>
            </div>

            <?php if (!empty($abonos)): ?>
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1rem;margin-bottom:.6rem;margin-top:1.2rem;">
                    Historial de Abonos Recibidos
                </h4>
                <div style="overflow-x:auto; margin-bottom: 1.2rem; border: 1px solid var(--border); border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                        <thead>
                            <tr style="background: var(--clight); border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Fecha</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Medio</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3); text-align: right;">Monto</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonos as $ab): ?>
                                <tr style="border-bottom: 1px solid rgba(148,91,53,.05);">
                                    <td style="padding: 0.5rem 0.75rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                    <td style="padding: 0.5rem 0.75rem;"><span style="font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></span></td>
                                    <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: var(--pago-green-dk);">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                    <td style="padding: 0.5rem 0.75rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($pago_activo['nota'])): ?>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>Nota del último registro</label>
                    <div style="background:var(--clight);border:1px solid var(--border);border-radius:8px;padding:.8rem;font-size:.85rem;"><?= nl2br(htmlspecialchars($pago_activo['nota'])) ?></div>
                </div>
            <?php endif; ?>

            <details class="revertir">
                <summary>Revertir este pago (solo si fue un error)</summary>
                <form method="post" style="margin-top:.6rem;">
                    <p style="font-size:.8rem;color:var(--ink2);margin-bottom:.6rem;">
                        Esto desmarca el pago como recibido. El pedido vuelve al estado "sin pago".
                    </p>
                    <button type="submit" name="revertir_pago" class="btn-secundario" onclick="return confirm('¿Seguro que quieres revertir el pago?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Revertir pago
                    </button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>
<script>
function onMontoChange() {
    var inp = document.getElementById('inp-monto-recibido');
    if (!inp) return;
    var recibido  = parseFloat(inp.value) || 0;
    var esperado  = parseFloat(inp.dataset.esperado) || 0;
    var difiere   = Math.abs(recibido - esperado) > 1;
    var aviso     = document.getElementById('aviso-diferencia');
    var grupoNota = document.getElementById('grupo-nota');
    var lblNota   = document.getElementById('lbl-nota');
    if (aviso)     aviso.style.display     = difiere ? 'block' : 'none';
    if (grupoNota) grupoNota.classList.toggle('nota-requerida', difiere);
    if (lblNota)   lblNota.textContent = difiere ? 'Nota (obligatoria — explica la diferencia)' : 'Nota';
}

function validarFormPago() {
    var inp = document.getElementById('inp-monto-recibido');
    if (!inp) return true;
    var recibido = parseFloat(inp.value) || 0;
    if (recibido <= 0) {
        alert('Ingresa el monto recibido antes de confirmar.');
        inp.focus();
        return false;
    }
    var esperado = parseFloat(inp.dataset.esperado) || 0;
    if (Math.abs(recibido - esperado) > 1) {
        var nota = (document.getElementById('inp-nota') || {}).value || '';
        if (!nota.trim()) {
            alert('El monto difiere del esperado. Por favor agrega una nota explicando la diferencia.');
            var n = document.getElementById('inp-nota');
            if (n) n.focus();
            return false;
        }
    }
    return true;
}
</script>
</body></html>