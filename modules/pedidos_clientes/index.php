<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';

requerirPropietario();
$pdo  = getConexion();
$user = usuarioActual();

// Cambio masivo de estado
$msg_ok_bulk  = '';
$msg_err_bulk = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'cambiar_estado_lote') {
    $ids        = array_values(array_filter(array_map('intval', $_POST['exportar_ids'] ?? []), fn($v) => $v > 0));
    $nuevo_est  = $_POST['nuevo_estado'] ?? '';
    $estados_ok = ['pendiente', 'confirmado', 'rechazado'];
    if (empty($ids)) {
        $msg_err_bulk = 'Selecciona al menos un pedido.';
    } elseif (!in_array($nuevo_est, $estados_ok, true)) {
        $msg_err_bulk = 'Estado no válido.';
    } else {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params_bulk  = $ids;
        $stmt_bulk = $pdo->prepare("UPDATE pedido_cliente SET estado=? WHERE id_pedido IN ($placeholders)");
        $stmt_bulk->execute(array_merge([$nuevo_est], $params_bulk));
        $n = $stmt_bulk->rowCount();
        $labels = ['pendiente' => 'pendientes', 'confirmado' => 'confirmados', 'rechazado' => 'rechazados'];
        $msg_ok_bulk = $n > 0 ? "$n pedido(s) marcados como <strong>{$labels[$nuevo_est]}</strong>." : "Sin cambios (ya tenían ese estado).";
    }
}

// Confirmar cobro de tienda (Nequi manual) — pedidos seleccionados
$msg_cobro_ok  = '';
$msg_cobro_err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'confirmar_cobro_tienda') {
    $ids_pedidos = array_values(array_filter(array_map('intval', $_POST['ids_pedidos'] ?? []), fn($v) => $v > 0));
    if (empty($ids_pedidos)) {
        $msg_cobro_err = 'Selecciona al menos un pedido.';
    } else {
        try {
            $pdo->beginTransaction();
            $cfg  = $pdo->query("SELECT wompi_confirmar_auto FROM configuracion LIMIT 1")->fetch();
            $auto = !empty($cfg['wompi_confirmar_auto']);
            $ph   = implode(',', array_fill(0, count($ids_pedidos), '?'));

            $sql_upd = "UPDATE pedido_cliente
                        SET estado_pago = 'aprobado'"
                     . ($auto ? ", estado = CASE WHEN estado = 'pendiente' THEN 'confirmado' ELSE estado END" : "")
                     . " WHERE id_pedido IN ($ph)";
            $upd = $pdo->prepare($sql_upd);
            $upd->execute($ids_pedidos);

            // Actualizar pago_pedido vinculados
            $pdo->prepare("
                UPDATE pago_pedido pp
                INNER JOIN pedido_cliente pc ON pc.id_pago_activo = pp.id_pago
                SET pp.estado = 'aprobado'
                WHERE pc.id_pedido IN ($ph) AND pp.estado = 'pendiente'
            ")->execute($ids_pedidos);

            $pdo->commit();
            $n = $upd->rowCount();
            $msg_cobro_ok = "$n pedido(s) marcados como pagados." . ($auto ? ' Los pedidos pendientes pasaron a confirmados.' : '');
        } catch (Exception $e) {
            $pdo->rollBack();
            $msg_cobro_err = 'Error al confirmar: ' . $e->getMessage();
        }
    }
}

// Pedidos pendientes de pago por tienda (para los modales con checkboxes)
$rows_cobros = $pdo->query("
    SELECT p.id_pedido, p.id_cliente, p.total_estimado, p.fecha_entrega, p.fecha_solicitud,
           c.id_cliente AS cli_id, c.nombre AS nombre_tienda
    FROM pedido_cliente p
    JOIN cliente c ON p.id_cliente = c.id_cliente
    WHERE c.tipo = 'tienda'
      AND p.estado != 'rechazado'
      AND p.estado_pago IN ('pendiente','no_aplica')
    ORDER BY p.id_cliente, p.fecha_entrega ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Agrupar por tienda
$cobros_pendientes = [];
foreach ($rows_cobros as $r) {
    $cid = $r['id_cliente'];
    if (!isset($cobros_pendientes[$cid])) {
        $cobros_pendientes[$cid] = [
            'id_cliente'      => $cid,
            'nombre'          => $r['nombre_tienda'],
            'num_pedidos'     => 0,
            'total_pendiente' => 0,
            'pedidos'         => [],
        ];
    }
    $cobros_pendientes[$cid]['num_pedidos']++;
    $cobros_pendientes[$cid]['total_pendiente'] += (float)$r['total_estimado'];
    $cobros_pendientes[$cid]['pedidos'][] = $r;
}
$cobros_pendientes = array_values($cobros_pendientes);

// Filtros
$f_cliente = trim($_GET['cliente'] ?? '');
$f_estado  = trim($_GET['estado'] ?? '');
$f_pago    = trim($_GET['pago'] ?? '');
$f_desde   = $_GET['desde'] ?? '';
$f_hasta   = $_GET['hasta'] ?? '';
$f_entrega = $_GET['entrega'] ?? '';
$f_tipo    = trim($_GET['tipo'] ?? '');

$params = [];
$where = [];

if ($f_cliente) {
    $where[] = "c.nombre LIKE ?";
    $params[] = "%$f_cliente%";
}
if ($f_estado) {
    $where[] = "p.estado = ?";
    $params[] = $f_estado;
}
if ($f_pago) {
    $where[] = "p.estado_pago = ?";
    $params[] = $f_pago;
}
if ($f_desde) {
    $where[] = "DATE(p.fecha_solicitud) >= ?";
    $params[] = $f_desde;
}
if ($f_hasta) {
    $where[] = "DATE(p.fecha_solicitud) <= ?";
    $params[] = $f_hasta;
}
if ($f_entrega) {
    $where[] = "p.fecha_entrega = ?";
    $params[] = $f_entrega;
}
if ($f_tipo) {
    $where[] = "c.tipo = ?";
    $params[] = $f_tipo;
}

$sql_where = $where ? "WHERE " . implode(" AND ", $where) : "";

// Obtener pedidos con filtros
$stmt = $pdo->prepare("
    SELECT p.*, c.nombre as cliente, c.tipo as tipo_cliente, c2.nombre as nombre_creador
    FROM pedido_cliente p
    JOIN cliente c ON p.id_cliente = c.id_cliente
    LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
    $sql_where
    ORDER BY p.fecha_solicitud DESC
");
$stmt->execute($params);
$pedidos = $stmt->fetchAll();

// Calcular estadísticas (siempre sobre el total o sobre lo filtrado? haré sobre lo filtrado para contexto)
$pendientes = 0;
$confirmados = 0;
$hoy = 0;
$total_estimado_hoy = 0;
$fecha_hoy = date('Y-m-d');
foreach($pedidos as $p) {
    if ($p['estado'] === 'pendiente') $pendientes++;
    if ($p['estado'] === 'confirmado') $confirmados++;
    if (strpos($p['fecha_solicitud'], $fecha_hoy) === 0) {
        $hoy++;
        $total_estimado_hoy += $p['total_estimado'];
    }
}

$page_title = 'Pedidos de Clientes';
require_once __DIR__ . '/../../views/layouts/header.php';
?>
<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--shadow2:0 4px 20px rgba(148,91,53,.15);--nav-h:64px;}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
  .page{margin-top:var(--nav-h);padding:1.5rem; min-height:calc(100vh - var(--nav-h));}
  
  .wc-banner{background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);background-size:300% 300%;animation:gradAnim 8s ease infinite;border-radius:14px;padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow2);gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;}
  .wc-left{display:flex;align-items:center;gap:.9rem;}
  .wc-greeting{font-size:.65rem;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.65);margin-bottom:.15rem;}
  .wc-name{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1.1;}
  .wc-name em{font-style:italic;color:var(--c5);}
  .wc-sub{font-size:.72rem;color:rgba(255,255,255,.62);margin-top:.15rem;}
  .wc-pills{display:flex;gap:.55rem;flex-wrap:wrap;}
  .wc-pill{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:.5rem .85rem;text-align:center;min-width:72px;}
  .wc-pill-num{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1;}
  .wc-pill-lbl{font-size:.54rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.58);}
  .wc-pill.warn{background:rgba(255,200,100,.2);border-color:rgba(255,200,100,.35);}.wc-pill.warn .wc-pill-num{color:#ffe0a0;}
  .wc-pill.ok{background:rgba(200,255,220,.2);border-color:rgba(200,255,220,.35);}.wc-pill.ok .wc-pill-num{color:#c8ffd8;}

  .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem; flex-wrap:wrap;}
  .mod-titulo{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
  .mod-titulo i{color:var(--c3);}
  .card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);overflow:hidden;}
  .gt{width:100%;border-collapse:collapse;}
  .gt th{font-size:.65rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink3);font-weight:700;padding:.8rem 1rem;background:var(--clight);border-bottom:1px solid var(--border);text-align:left;}
  .gt td{font-size:.85rem;color:var(--ink);padding:.8rem 1rem;border-bottom:1px solid rgba(148,91,53,.05);vertical-align:middle;}
  .gt tr:hover td{background:rgba(250,243,234,.5);}
  .estado { font-size: 0.7rem; font-weight: 700; padding: 0.2rem 0.6rem; border-radius: 20px; text-transform: uppercase; letter-spacing:0.05em;}
  .e-pendiente { background: #fff3e0; color: #e65100; border:1px solid #ffcc80;}
  .e-confirmado { background: #e8f5e9; color: #2e7d32; border:1px solid #a5d6a7;}
  .e-rechazado { background: #ffebee; color: #c62828; border:1px solid #ef9a9a;}

  .ep { font-size:.65rem; font-weight:700; padding:.18rem .55rem; border-radius:20px; text-transform:uppercase; letter-spacing:.05em; white-space:nowrap; display:inline-flex; align-items:center; gap:.25rem; }
  .ep-pendiente  { background:#fff8e1; color:#b45309; border:1px solid #fde68a; }
  .ep-aprobado   { background:#e8f5e9; color:#1b5e20; border:1px solid #a5d6a7; }
  .ep-rechazado  { background:#ffebee; color:#b91c1c; border:1px solid #fca5a5; }
  .ep-expirado   { background:#f3f4f6; color:#6b7280; border:1px solid #d1d5db; }
  .ep-no_aplica  { background:#f5f3ff; color:#6d28d9; border:1px solid #c4b5fd; }
  .ep-parcial    { background:#e0f2fe; color:#0369a1; border:1px solid #bae6fd; }
  .btn-ver { background: var(--clight); color: var(--c3); border: 1px solid var(--border); padding: 0.3rem 0.8rem; border-radius: 6px; text-decoration: none; font-size: 0.8rem; font-weight: 600; display: inline-block; transition: all 0.2s; }
  .btn-ver:hover { background: var(--c3); color: #fff; }

  /* Filtros */
  .filter-card { background: var(--ccard); border: 1px solid var(--border); border-radius: 14px; padding: 1rem; margin-bottom: 1rem; box-shadow: var(--shadow); }
  .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; align-items: end; }
  .filter-group label { display: block; font-size: 0.65rem; font-weight: 700; text-transform: uppercase; color: var(--ink3); margin-bottom: 0.3rem; }
  .filter-input { width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; font-family: inherit; background: var(--clight); }
  .btn-filter { background: var(--c3); color: #fff; border: 1px solid transparent; padding: 0.55rem 1rem; border-radius: 8px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.4rem; font-size: 0.85rem; transition: all 0.2s; flex: 1; justify-content: center; }
  .btn-filter:hover { background: var(--c1); transform: translateY(-1px); }
  .btn-clear { background: var(--ccard); color: var(--ink3); border: 1px solid var(--border); padding: 0.55rem 1rem; border-radius: 8px; font-weight: 600; cursor: pointer; text-decoration: none; font-size: 0.85rem; display: flex; align-items: center; gap: 0.4rem; transition: all 0.2s; flex: 1; justify-content: center; }
  .btn-clear:hover { background: var(--clight); color: var(--c1); }
  .filter-actions { display: flex; gap: 0.5rem; align-items: center; grid-column: span 2; }
  @media (max-width: 1024px) {
    .filter-actions { grid-column: span 1; flex-wrap: wrap; }
    .filter-actions .btn-filter, .filter-actions .btn-clear { flex: 1 1 100%; }
  }

  /* Acción masiva */
  .bulk-bar { display:none; align-items:center; gap:.7rem; background:#fff8e1; border:1px solid #ffe082; border-radius:10px; padding:.6rem 1rem; margin-bottom:.8rem; flex-wrap:wrap; }
  .bulk-bar.visible { display:flex; }
  .bulk-bar .bulk-info { font-size:.85rem; font-weight:700; color:#856404; flex:1; }
  .btn-confirmar-lote { background:linear-gradient(135deg,#2e7d32,#1b5e20); color:#fff; border:none; border-radius:9px; padding:.55rem 1.1rem; font-size:.83rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.4rem; transition:all .2s; }
  .btn-confirmar-lote:hover { transform:translateY(-1px); box-shadow:0 4px 14px rgba(46,125,50,.3); }
  .msg-bulk-ok  { background:#e8f5e9; border:1px solid #a5d6a7; border-left:3px solid #2e7d32; border-radius:10px; padding:.6rem 1rem; font-size:.85rem; color:#1b5e20; font-weight:600; margin-bottom:.8rem; }
  .msg-bulk-err { background:#ffebee; border:1px solid #ef9a9a; border-left:3px solid #c62828; border-radius:10px; padding:.6rem 1rem; font-size:.85rem; color:#c62828; margin-bottom:.8rem; }

  /* Responsive Table */
  @media (max-width: 768px) {
    .page { padding: 1rem 0.7rem; }
    .wc-banner { padding: 1rem; flex-direction: column; align-items: flex-start; }
    .wc-pills { width: 100%; justify-content: space-between; }
    .wc-pill { flex: 1; min-width: 0; }

    .gt thead { display: none; }
    .gt, .gt tbody, .gt tr, .gt td { display: block; width: 100%; }
    .gt tr { margin-bottom: 1rem; border-bottom: 2px solid var(--border); padding-bottom: 0.5rem; position: relative; }
    .gt td { padding: 0.5rem 1rem; text-align: right; font-size: 0.85rem; border: none; display: flex; justify-content: space-between; align-items: center; }
    .gt td::before { content: attr(data-label); font-weight: 700; color: var(--ink3); text-transform: uppercase; font-size: 0.65rem; }
    .gt td:first-child { background: var(--clight); justify-content: center; }
    .gt td:last-child { border-bottom: none; }
    
    .filter-grid { grid-template-columns: 1fr; }
    .btn-filter, .btn-clear { width: 100%; justify-content: center; }
  }
</style>

<div class="page">
    <div class="wc-banner">
        <div class="wc-left">
            <div>
                <div class="wc-greeting">Recepción de Solicitudes</div>
                <div class="wc-name">Pedidos de <em>Clientes</em></div>
                <div class="wc-sub">Gestiona las reservas y pedidos de la panadería · <?= date('d/m/Y') ?></div>
            </div>
        </div>
        <div class="wc-pills">
            <div class="wc-pill <?= $pendientes > 0 ? 'warn' : '' ?>">
                <div class="wc-pill-num"><?= $pendientes ?></div>
                <div class="wc-pill-lbl">Pendientes</div>
            </div>
            <div class="wc-pill ok">
                <div class="wc-pill-num"><?= $confirmados ?></div>
                <div class="wc-pill-lbl">Confirmados</div>
            </div>
            <div class="wc-pill">
                <div class="wc-pill-num"><?= $hoy ?></div>
                <div class="wc-pill-lbl">Nuevos hoy</div>
            </div>
        </div>
    </div>

    <?php if ($msg_cobro_ok): ?>
    <div class="msg-bulk-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_cobro_ok ?></div>
    <?php endif; ?>
    <?php if ($msg_cobro_err): ?>
    <div class="msg-bulk-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_cobro_err ?></div>
    <?php endif; ?>

    <?php if (!empty($cobros_pendientes)): ?>
    <div class="card" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:center;gap:.6rem;padding:.85rem 1.1rem;border-bottom:1px solid var(--border);background:var(--clight);">
            <div style="width:30px;height:30px;border-radius:8px;background:rgba(239,68,68,.12);color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-cash-coin"></i>
            </div>
            <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.17em;color:#c62828;">
                Cobros Pendientes por Nequi — <?= count($cobros_pendientes) ?> tienda<?= count($cobros_pendientes) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div style="overflow-x:auto;">
        <table class="gt">
            <thead>
                <tr>
                    <th>Tienda / Cliente</th>
                    <th>Pedidos</th>
                    <th>Total Pendiente</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cobros_pendientes as $idx => $cb): ?>
            <tr>
                <td data-label="Tienda">
                    <div style="font-weight:700;color:var(--ink);"><?= htmlspecialchars($cb['nombre']) ?></div>
                    <div style="font-size:.72rem;color:var(--ink3);">Pago manual · Nequi Negocios</div>
                </td>
                <td data-label="Pedidos" style="font-weight:600;"><?= $cb['num_pedidos'] ?></td>
                <td data-label="Total" style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:#c62828;">
                    $<?= number_format($cb['total_pendiente'], 0, ',', '.') ?>
                </td>
                <td data-label="Acción">
                    <button type="button" class="btn-confirmar-lote" onclick="abrirModalCobro(<?= $idx ?>)">
                        <i class="bi bi-cash-stack"></i> Confirmar cobro
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Modales de cobro por tienda -->
    <?php foreach ($cobros_pendientes as $idx => $cb): ?>
    <div id="modal-cobro-<?= $idx ?>" style="display:none;position:fixed;inset:0;background:rgba(40,21,8,.6);z-index:2000;overflow-y:auto;padding:1.5rem 1rem;">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;margin:0 auto;box-shadow:0 20px 60px rgba(40,21,8,.3);">

            <!-- Cabecera -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--border);background:var(--clight);border-radius:16px 16px 0 0;">
                <div>
                    <div style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:var(--ink);">
                        <i class="bi bi-cash-coin" style="color:#dc2626;"></i> <?= htmlspecialchars($cb['nombre']) ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--ink3);margin-top:.1rem;">Selecciona los pedidos que ya recibiste por Nequi</div>
                </div>
                <button type="button" onclick="cerrarModalCobro(<?= $idx ?>)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:#fff;color:var(--ink3);cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form method="POST" id="form-cobro-<?= $idx ?>">
                <input type="hidden" name="accion" value="confirmar_cobro_tienda">

                <!-- Barra seleccionar todos + total -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.2rem;border-bottom:1px solid var(--border);background:#fffbf5;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;font-weight:700;color:var(--ink2);cursor:pointer;">
                        <input type="checkbox" id="chk-all-<?= $idx ?>" checked onchange="toggleTodos(<?= $idx ?>)" style="width:16px;height:16px;accent-color:var(--c3);">
                        Seleccionar todos (<?= count($cb['pedidos']) ?>)
                    </label>
                    <span style="font-size:.85rem;font-weight:800;color:#dc2626;" id="total-cobro-<?= $idx ?>">
                        $<?= number_format($cb['total_pendiente'], 0, ',', '.') ?>
                    </span>
                </div>

                <!-- Lista de pedidos con checkboxes -->
                <div style="padding:.8rem 1.2rem;display:flex;flex-direction:column;gap:.5rem;max-height:340px;overflow-y:auto;">
                <?php foreach ($cb['pedidos'] as $ped): ?>
                <label style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;border-radius:10px;border:1.5px solid var(--border);cursor:pointer;background:#fff;">
                    <input type="checkbox" name="ids_pedidos[]"
                           value="<?= $ped['id_pedido'] ?>"
                           data-monto="<?= (float)$ped['total_estimado'] ?>"
                           checked
                           onchange="recalcularTotal(<?= $idx ?>)"
                           style="width:17px;height:17px;accent-color:var(--c3);flex-shrink:0;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;color:var(--ink);font-size:.85rem;">
                            Pedido #<?= str_pad($ped['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
                        </div>
                        <div style="font-size:.71rem;color:var(--ink3);">
                            Entrega: <?= date('d/m/Y', strtotime($ped['fecha_entrega'])) ?>
                            &nbsp;·&nbsp; Solicitud: <?= date('d/m/Y', strtotime($ped['fecha_solicitud'])) ?>
                        </div>
                    </div>
                    <div style="font-family:'Fraunces',serif;font-weight:800;color:var(--c1);font-size:.95rem;white-space:nowrap;">
                        $<?= number_format($ped['total_estimado'], 0, ',', '.') ?>
                    </div>
                </label>
                <?php endforeach; ?>
                </div>

                <!-- Footer -->
                <div style="display:flex;gap:.6rem;padding:.9rem 1.2rem;border-top:1px solid var(--border);border-radius:0 0 16px 16px;background:var(--clight);">
                    <button type="button" onclick="cerrarModalCobro(<?= $idx ?>)"
                            style="flex:1;padding:.72rem;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--ink3);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-confirmar-<?= $idx ?>"
                            style="flex:2;padding:.72rem;border-radius:10px;border:none;background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        <i class="bi bi-check-circle-fill"></i> Confirmar pago recibido
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Cliente</label>
                <input type="text" name="cliente" class="filter-input" placeholder="Nombre..." value="<?= htmlspecialchars($f_cliente) ?>">
            </div>
            <div class="filter-group">
                <label>Tipo Cliente</label>
                <select name="tipo" class="filter-input">
                    <option value="">Todos</option>
                    <option value="tienda" <?= $f_tipo==='tienda'?'selected':'' ?>>Tienda</option>
                    <option value="mostrador" <?= $f_tipo==='mostrador'?'selected':'' ?>>Mostrador</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Estado pedido</label>
                <select name="estado" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_estado==='pendiente' ?'selected':'' ?>>Pendiente</option>
                    <option value="confirmado" <?= $f_estado==='confirmado'?'selected':'' ?>>Confirmado</option>
                    <option value="rechazado"  <?= $f_estado==='rechazado' ?'selected':'' ?>>Rechazado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Estado pago</label>
                <select name="pago" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_pago==='pendiente' ?'selected':'' ?>>Sin pagar</option>
                    <option value="aprobado"   <?= $f_pago==='aprobado'  ?'selected':'' ?>>Pagado</option>
                    <option value="rechazado"  <?= $f_pago==='rechazado' ?'selected':'' ?>>Rechazado</option>
                    <option value="expirado"   <?= $f_pago==='expirado'  ?'selected':'' ?>>Expirado</option>
                    <option value="no_aplica"  <?= $f_pago==='no_aplica' ?'selected':'' ?>>N/A</option>
                </select>
            </div>
            <div class="filter-group">
                <label>F. Entrega</label>
                <input type="date" name="entrega" class="filter-input" value="<?= htmlspecialchars($f_entrega) ?>">
            </div>
            <div class="filter-group">
                <label>Solicitado Desde</label>
                <input type="date" name="desde" class="filter-input" value="<?= htmlspecialchars($f_desde) ?>">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="hasta" class="filter-input" value="<?= htmlspecialchars($f_hasta) ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="bi bi-filter"></i> Filtrar</button>
                <a href="index.php" class="btn-clear"><i class="bi bi-x-circle"></i> Limpiar</a>
            </div>
        </form>
    </div>

    <?php if ($msg_ok_bulk): ?><div class="msg-bulk-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_ok_bulk ?></div><?php endif; ?>
    <?php if ($msg_err_bulk): ?><div class="msg-bulk-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_err_bulk ?></div><?php endif; ?>

    <div id="bulk-bar" class="bulk-bar">
        <span class="bulk-info"><i class="bi bi-check2-square"></i> <span id="bulk-count">0</span> pedido(s) seleccionado(s)</span>
        <select id="select-nuevo-estado" style="padding:.45rem .7rem; border:1px solid #ffe082; border-radius:8px; font-size:.83rem; font-weight:600; font-family:inherit; background:#fff; color:#5d4d10; cursor:pointer;">
            <option value="confirmado">✅ Confirmar</option>
            <option value="pendiente">🕐 Volver a Pendiente</option>
            <option value="rechazado">❌ Rechazar</option>
        </select>
        <button type="button" class="btn-confirmar-lote" onclick="cambiarEstadoLote()">
            <i class="bi bi-arrow-repeat"></i> Aplicar a seleccionados
        </button>
    </div>

    <div class="topbar">
        <div class="mod-titulo"><i class="bi bi-list-ul"></i> <?= $where ? 'Resultados del Filtro' : 'Todos los Pedidos' ?></div>
    </div>

    <div class="card">
        <form id="form-pedidos" method="POST" action="index.php">
            <input type="hidden" name="accion"        id="input-accion"       value="">
            <input type="hidden" name="nuevo_estado"  id="input-nuevo-estado" value="">
            <div style="padding: 0.8rem 1rem; border-bottom: 1px solid var(--border); background: var(--clight); display:flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="exportar('excel')" style="background:var(--ccard); border:1px solid var(--border); padding:0.4rem 0.8rem; border-radius:8px; font-size:0.8rem; font-weight:bold; cursor:pointer;"><i class="bi bi-file-earmark-excel-fill" style="color:#2e7d32;"></i> Excel</button>
                <button type="button" onclick="exportar('pdf')" style="background:var(--ccard); border:1px solid var(--border); padding:0.4rem 0.8rem; border-radius:8px; font-size:0.8rem; font-weight:bold; cursor:pointer;"><i class="bi bi-file-earmark-pdf-fill" style="color:#c62828;"></i> PDF</button>
            </div>
            <table class="gt">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" id="chk-all-ped" onclick="document.querySelectorAll('.chk-ped').forEach(c => c.checked = this.checked)"></th>
                    <th>ID</th>
                    <th>Cliente / Tienda</th>
                    <th>Creado Por</th>
                    <th>Fecha Entrega</th>
                    <th>Solicitado</th>
                    <th>Total Est.</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                <tr><td colspan="10" style="text-align:center; padding:2rem; color:var(--ink3);">No hay pedidos registrados</td></tr>
                <?php endif; ?>
                <?php foreach($pedidos as $p): ?>
                <tr>
                    <td data-label="Selección"><input type="checkbox" name="exportar_ids[]" value="<?= $p['id_pedido'] ?>" class="chk-ped"></td>
                    <td data-label="Pedido" style="font-weight:700; color:var(--ink2);">#<?= str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td data-label="Cliente" style="font-weight:600; text-align: right;"><?= htmlspecialchars($p['cliente']) ?> <span style="font-size:0.7rem; color:var(--ink3); display:block;"><?= $p['tipo_cliente'] ?></span></td>
                    <td data-label="Creado Por" style="font-size:0.8rem; color:var(--ink2);">
                        <?= htmlspecialchars($p['nombre_creador'] ?? 'Directo') ?>
                    </td>
                    <td data-label="Entrega" style="font-weight:600; color:var(--c1);"><?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></td>
                    <td data-label="Solicitud" style="font-size:0.75rem; color:var(--ink3);"><?= date('d/m/Y H:i', strtotime($p['fecha_solicitud'])) ?></td>
                    <td data-label="Total" style="font-weight:700;">$<?= number_format($p['total_estimado'], 0, ',', '.') ?></td>
                    <td data-label="Estado"><span class="estado e-<?= $p['estado'] ?>"><?= $p['estado'] ?></span></td>
                    <td data-label="Pago">
                    <?php
                        $ep = $p['estado_pago'] ?? 'pendiente';
                        $ep_labels = [
                            'pendiente' => ['<i class="bi bi-clock-fill"></i>', 'Sin pagar'],
                            'aprobado'  => ['<i class="bi bi-check-circle-fill"></i>', 'Pagado'],
                            'parcial'   => ['<i class="bi bi-info-circle-fill"></i>', 'Pago Parcial'],
                            'rechazado' => ['<i class="bi bi-x-circle-fill"></i>', 'Rechazado'],
                            'expirado'  => ['<i class="bi bi-hourglass-bottom"></i>', 'Expirado'],
                            'no_aplica' => ['<i class="bi bi-dash-circle"></i>', 'N/A'],
                        ];
                        [$ep_ico, $ep_txt] = $ep_labels[$ep] ?? ['', $ep];
                    ?>
                        <span class="ep ep-<?= $ep ?>"><?= $ep_ico ?> <?= $ep_txt ?></span>
                    </td>
                    <td data-label="Acción" style="white-space:nowrap;">
                        <?php if ($p['estado'] === 'pendiente'): ?>
                        <button type="button" title="Confirmar" onclick="quickAction(<?= $p['id_pedido'] ?>, 'confirmado')"
                                style="background:transparent;border:1px solid #a5d6a7;border-radius:6px;padding:.28rem .55rem;cursor:pointer;color:#2e7d32;font-size:.85rem;margin-right:.25rem;transition:all .15s;"
                                onmouseover="this.style.background='#e8f5e9'" onmouseout="this.style.background='transparent'">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button type="button" title="Rechazar" onclick="quickAction(<?= $p['id_pedido'] ?>, 'rechazado')"
                                style="background:transparent;border:1px solid #ef9a9a;border-radius:6px;padding:.28rem .55rem;cursor:pointer;color:#c62828;font-size:.85rem;margin-right:.35rem;transition:all .15s;"
                                onmouseover="this.style.background='#ffebee'" onmouseout="this.style.background='transparent'">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php endif; ?>
                        <a href="ver_pedido.php?id=<?= $p['id_pedido'] ?>" class="btn-ver">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </form>
    </div>
</div>

<script>
function actualizarBulkBar() {
    var checked = document.querySelectorAll('.chk-ped:checked');
    var bar = document.getElementById('bulk-bar');
    document.getElementById('bulk-count').textContent = checked.length;
    bar.classList.toggle('visible', checked.length > 0);
}

document.getElementById('chk-all-ped').addEventListener('change', function() {
    document.querySelectorAll('.chk-ped').forEach(c => c.checked = this.checked);
    actualizarBulkBar();
});
document.querySelectorAll('.chk-ped').forEach(c => c.addEventListener('change', actualizarBulkBar));

function cambiarEstadoLote() {
    var checked = document.querySelectorAll('.chk-ped:checked');
    if (checked.length === 0) { alert('Selecciona al menos un pedido.'); return; }
    var estado  = document.getElementById('select-nuevo-estado').value;
    var labels  = { confirmado: 'CONFIRMAR', pendiente: 'volver a PENDIENTE', rechazado: 'RECHAZAR' };
    if (!confirm('¿' + labels[estado] + ' los ' + checked.length + ' pedido(s) seleccionado(s)?')) return;
    document.getElementById('input-accion').value      = 'cambiar_estado_lote';
    document.getElementById('input-nuevo-estado').value = estado;
    document.getElementById('form-pedidos').submit();
}

function exportar(fmt) {
    var f = document.getElementById('form-pedidos');
    var original = f.action;
    var originalTarget = f.target;
    f.action = 'exportar.php';
    f.target = '_blank';
    // Agregar campo formato temporalmente
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'formato'; inp.value = fmt;
    f.appendChild(inp);
    f.submit();
    f.removeChild(inp);
    f.action = original;
    f.target = originalTarget;
}

// ── Acción rápida por fila ──
function quickAction(id, estado) {
    var labels = { confirmado: 'confirmar', rechazado: 'rechazar' };
    if (!confirm('¿' + (labels[estado] || estado) + ' el pedido #' + String(id).padStart(4, '0') + '?')) return;
    document.querySelectorAll('.chk-ped').forEach(function(c) { c.checked = false; });
    var chk = document.querySelector('.chk-ped[value="' + id + '"]');
    if (chk) chk.checked = true;
    document.getElementById('input-accion').value       = 'cambiar_estado_lote';
    document.getElementById('input-nuevo-estado').value = estado;
    document.getElementById('form-pedidos').submit();
}

// ── Modales de cobro ──
function abrirModalCobro(idx) {
    var modal = document.getElementById('modal-cobro-' + idx);
    if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
}
function cerrarModalCobro(idx) {
    var modal = document.getElementById('modal-cobro-' + idx);
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
}
function toggleTodos(idx) {
    var todos = document.getElementById('chk-all-' + idx).checked;
    document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]')
        .forEach(function(c) { c.checked = todos; });
    recalcularTotal(idx);
}
function recalcularTotal(idx) {
    var sum = 0;
    document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]:checked')
        .forEach(function(c) { sum += parseFloat(c.dataset.monto) || 0; });
    document.getElementById('total-cobro-' + idx).textContent =
        'Total: $' + sum.toLocaleString('es-CO', {maximumFractionDigits: 0});
    // Deshabilitar botón si no hay ninguno seleccionado
    var btn = document.getElementById('btn-confirmar-' + idx);
    if (btn) btn.disabled = sum === 0;
    // Actualizar estado del "seleccionar todos"
    var chks = document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]');
    var marcados = document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]:checked');
    var chkAll = document.getElementById('chk-all-' + idx);
    if (chkAll) chkAll.indeterminate = marcados.length > 0 && marcados.length < chks.length;
    if (chkAll) chkAll.checked = marcados.length === chks.length;
}
// Cerrar modal al click en el backdrop
document.querySelectorAll('[id^="modal-cobro-"]').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});
</script>
</body></html>
