<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

$cliente_id = $_SESSION['cliente_id'];
$formato    = in_array($_POST['formato'] ?? '', ['excel', 'pdf']) ? $_POST['formato'] : 'pdf';
$ids        = array_values(array_filter(array_map('intval', $_POST['ids'] ?? []), fn($v) => $v > 0));
$pdo        = getConexion();

if (empty($ids)) {
    header('Location: dashboard.php');
    exit;
}

// Verificar que todos los pedidos pertenecen a este cliente
$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt_check = $pdo->prepare("
    SELECT COUNT(*) FROM pedido_cliente
    WHERE id_pedido IN ($placeholders) AND (id_cliente = ? OR id_creador = ?)
");
$stmt_check->execute(array_merge($ids, [$cliente_id, $cliente_id]));
if ((int)$stmt_check->fetchColumn() !== count($ids)) {
    header('Location: dashboard.php');
    exit;
}

// Nombre de la tienda
$stmt_cli = $pdo->prepare("SELECT nombre FROM cliente WHERE id_cliente = ?");
$stmt_cli->execute([$cliente_id]);
$nombre_tienda = $stmt_cli->fetchColumn() ?: 'Tienda';

// Obtener pedidos con sus productos detallados
$stmt_ped = $pdo->prepare("
    SELECT p.id_pedido, p.fecha_entrega, p.fecha_solicitud, p.total_estimado, p.estado,
           COALESCE(c2.nombre, 'Mismo cliente') AS aprendiz
    FROM pedido_cliente p
    LEFT JOIN cliente c2 ON p.id_creador = c2.id_cliente
    WHERE p.id_pedido IN ($placeholders)
    ORDER BY p.fecha_entrega ASC, c2.nombre ASC
");
$stmt_ped->execute($ids);
$pedidos = $stmt_ped->fetchAll();

// Obtener detalles de productos para todos los pedidos
$stmt_det = $pdo->prepare("
    SELECT d.id_pedido, vp.nombre AS producto,
           d.cantidad, d.napa, d.bonificacion
    FROM pedido_cliente_detalle d
    JOIN variedad_pan vp ON d.id_variedad = vp.id_variedad
    WHERE d.id_pedido IN ($placeholders)
    ORDER BY vp.nombre
");
$stmt_det->execute($ids);
$todos_detalles = $stmt_det->fetchAll();

// Agrupar detalles por id_pedido
$detalles_por_pedido = [];
foreach ($todos_detalles as $d) {
    $detalles_por_pedido[$d['id_pedido']][] = $d;
}

$fecha_generado = date('d/m/Y H:i');

// ── EXCEL ─────────────────────────────────────────────────────────────────
if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="pedidos_detallados_' . date('Ymd') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF";
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office"><head><meta charset="utf-8"></head><body>';
    echo '<table border="1" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:10pt;">';

    // Título
    echo '<tr><td colspan="7" style="background:#1a237e;color:#fff;font-weight:bold;font-size:13pt;padding:8px;">';
    echo 'Pedidos Detallados — ' . htmlspecialchars($nombre_tienda) . '</td></tr>';
    echo '<tr><td colspan="7" style="background:#e8eaf6;color:#283593;padding:4px 8px;font-size:9pt;">';
    echo 'Generado: ' . $fecha_generado . '</td></tr>';
    echo '<tr><td colspan="7"></td></tr>';

    foreach ($pedidos as $ped) {
        $est_color = ['confirmado' => '#e8f5e9', 'pendiente' => '#fff8e1', 'rechazado' => '#ffebee'];
        $bg_est = $est_color[$ped['estado']] ?? '#f5f5f5';

        // Cabecera del pedido
        echo '<tr style="background:#c5cae9;">';
        echo '<td colspan="4" style="padding:5px 8px;font-weight:bold;color:#1a237e;">';
        echo 'Pedido #' . str_pad($ped['id_pedido'], 4, '0', STR_PAD_LEFT);
        echo ' — ' . htmlspecialchars($ped['aprendiz']) . '</td>';
        echo '<td colspan="2" style="padding:5px 8px;color:#283593;font-size:9pt;">Entrega: ' . date('d/m/Y', strtotime($ped['fecha_entrega'])) . '</td>';
        echo '<td style="padding:5px 8px;background:' . $bg_est . ';font-weight:bold;font-size:9pt;text-align:center;">' . strtoupper($ped['estado']) . '</td>';
        echo '</tr>';

        // Cabecera columnas productos
        echo '<tr style="background:#e8eaf6;">';
        echo '<th style="padding:4px 8px;text-align:left;" colspan="3">Pan / Producto</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Cant.</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Ñapa</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Bonif.</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Total und</th>';
        echo '</tr>';

        $detalles = $detalles_por_pedido[$ped['id_pedido']] ?? [];
        $total_und = 0;
        foreach ($detalles as $d) {
            $und = $d['cantidad'] + $d['napa'] + $d['bonificacion'];
            $total_und += $und;
            echo '<tr>';
            echo '<td colspan="3" style="padding:4px 8px;">' . htmlspecialchars($d['producto']) . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$d['cantidad'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$d['napa'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$d['bonificacion'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;font-weight:bold;">' . $und . '</td>';
            echo '</tr>';
        }

        // Subtotal del pedido
        echo '<tr style="background:#f5f5f5;">';
        echo '<td colspan="5" style="padding:4px 8px;text-align:right;font-weight:bold;color:#283593;">Subtotal:</td>';
        echo '<td style="padding:4px 8px;text-align:center;font-weight:bold;color:#1a237e;">' . $total_und . ' und</td>';
        echo '<td style="padding:4px 8px;text-align:center;font-weight:bold;color:#1a237e;">$' . number_format($ped['total_estimado'], 0, ',', '.') . '</td>';
        echo '</tr>';
        echo '<tr><td colspan="7" style="padding:3px;"></td></tr>';
    }

    // Total general
    $gran_total = array_sum(array_column($pedidos, 'total_estimado'));
    echo '<tr style="background:#1a237e;">';
    echo '<td colspan="6" style="padding:6px 8px;color:#fff;font-weight:bold;text-align:right;">TOTAL GENERAL:</td>';
    echo '<td style="padding:6px 8px;color:#fff;font-weight:bold;text-align:center;">$' . number_format($gran_total, 0, ',', '.') . '</td>';
    echo '</tr>';
    echo '</table></body></html>';
    exit;
}

// ── PDF (página imprimible) ───────────────────────────────────────────────
$gran_total = array_sum(array_column($pedidos, 'total_estimado'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Pedidos Detallados — <?= htmlspecialchars($nombre_tienda) ?></title>
<style>
    @page { margin:1.5cm 1.8cm; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family:Arial, sans-serif; font-size:10pt; color:#1a1a1a; background:#fff; }

    .encabezado { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:.8rem; border-bottom:2px solid #1a237e; margin-bottom:1.2rem; }
    .enc-titulo { font-size:14pt; font-weight:800; color:#1a237e; }
    .enc-titulo span { font-size:9.5pt; font-weight:400; color:#555; display:block; margin-top:.15rem; }
    .enc-meta { text-align:right; font-size:8.5pt; color:#555; line-height:1.7; }
    .enc-meta strong { color:#1a237e; }

    .pedido-block { margin-bottom:1.4rem; border:1px solid #c5cae9; border-radius:6px; overflow:hidden; page-break-inside:avoid; }
    .ped-cabecera { background:#e8eaf6; padding:.5rem .8rem; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #c5cae9; }
    .ped-cab-izq { font-weight:800; font-size:10pt; color:#1a237e; }
    .ped-cab-der { font-size:8.5pt; color:#283593; display:flex; gap:1rem; align-items:center; }
    .badge-est { font-size:7.5pt; font-weight:700; padding:.15rem .5rem; border-radius:10px; text-transform:uppercase; }
    .b-confirmado { background:#e8f5e9; color:#2e7d32; border:1px solid #a5d6a7; }
    .b-pendiente  { background:#fff8e1; color:#e65100; border:1px solid #ffe082; }
    .b-rechazado  { background:#ffebee; color:#c62828; border:1px solid #ef9a9a; }

    table { width:100%; border-collapse:collapse; }
    th { font-size:7.5pt; text-transform:uppercase; letter-spacing:.04em; color:#555; font-weight:700; padding:.4rem .7rem; background:#f5f5f5; border-bottom:1px solid #e0e0e0; text-align:left; }
    th:not(:first-child) { text-align:center; }
    td { font-size:9pt; padding:.4rem .7rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    td:not(:first-child) { text-align:center; }
    .napa-b  { font-size:7pt; background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; border-radius:3px; padding:1px 4px; }
    .bonif-b { font-size:7pt; background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb; border-radius:3px; padding:1px 4px; }
    .sub-row { background:#f9f9f9; }
    .sub-row td { font-weight:700; font-size:8.5pt; color:#283593; }

    .gran-total { margin-top:1rem; background:#1a237e; color:#fff; border-radius:8px; padding:.7rem 1.1rem; display:flex; justify-content:space-between; align-items:center; }
    .gran-total .lbl { font-size:9pt; font-weight:700; text-transform:uppercase; letter-spacing:.05em; opacity:.9; }
    .gran-total .val { font-size:15pt; font-weight:800; }

    .pie { margin-top:1.2rem; text-align:center; font-size:8pt; color:#aaa; border-top:1px solid #eee; padding-top:.5rem; }

    .no-print { position:fixed; top:1rem; right:1rem; display:flex; gap:.5rem; z-index:999; }
    .btn-p { background:#1a237e; color:#fff; border:none; border-radius:8px; padding:.55rem 1.1rem; font-size:.85rem; font-weight:700; cursor:pointer; }
    .btn-c { background:#555; color:#fff; border:none; border-radius:8px; padding:.55rem 1rem; font-size:.85rem; font-weight:700; cursor:pointer; }
    @media print { .no-print { display:none !important; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-p" onclick="window.print()">🖨 Imprimir / PDF</button>
    <button class="btn-c" onclick="window.close()">✕ Cerrar</button>
</div>

<div class="encabezado">
    <div>
        <div class="enc-titulo">
            Pedidos Detallados
            <span><?= htmlspecialchars($nombre_tienda) ?></span>
        </div>
    </div>
    <div class="enc-meta">
        <strong>Pedidos incluidos:</strong> <?= count($pedidos) ?><br>
        <strong>Generado:</strong> <?= $fecha_generado ?>
    </div>
</div>

<?php foreach ($pedidos as $ped):
    $detalles = $detalles_por_pedido[$ped['id_pedido']] ?? [];
    $total_und = 0;
?>
<div class="pedido-block">
    <div class="ped-cabecera">
        <div class="ped-cab-izq">
            Pedido #<?= str_pad($ped['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
            &nbsp;·&nbsp; <?= htmlspecialchars($ped['aprendiz']) ?>
        </div>
        <div class="ped-cab-der">
            Entrega: <?= date('d/m/Y', strtotime($ped['fecha_entrega'])) ?>
            &nbsp;
            <span class="badge-est b-<?= $ped['estado'] ?>"><?= $ped['estado'] ?></span>
        </div>
    </div>
    <table>
        <thead>
            <tr>
                <th>Pan / Producto</th>
                <th>Cant.</th>
                <th>Ñapa</th>
                <th>Bonif.</th>
                <th>Total und</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($detalles as $d):
            $und = $d['cantidad'] + $d['napa'] + $d['bonificacion'];
            $total_und += $und;
        ?>
            <tr>
                <td><?= htmlspecialchars($d['producto']) ?></td>
                <td><?= (int)$d['cantidad'] ?></td>
                <td><?= $d['napa'] > 0 ? '<span class="napa-b">+' . (int)$d['napa'] . '</span>' : '0' ?></td>
                <td><?= $d['bonificacion'] > 0 ? '<span class="bonif-b">+' . (int)$d['bonificacion'] . '</span>' : '0' ?></td>
                <td><strong><?= $und ?></strong></td>
            </tr>
        <?php endforeach; ?>
        <tr class="sub-row">
            <td colspan="3" style="text-align:right;">Subtotal pedido:</td>
            <td><?= $total_und ?> und</td>
            <td>$<?= number_format($ped['total_estimado'], 0, ',', '.') ?></td>
        </tr>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<div class="gran-total">
    <span class="lbl">Total General</span>
    <span class="val">$<?= number_format($gran_total, 0, ',', '.') ?></span>
</div>

<div class="pie">
    BreadControl · <?= htmlspecialchars($nombre_tienda) ?> · Generado el <?= $fecha_generado ?>
</div>

</body>
</html>
