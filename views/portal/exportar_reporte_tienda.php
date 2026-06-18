<?php
// views/portal/exportar_reporte_tienda.php

// ── EXCEL ──────────────────────────────────────────────────────────────────
if ($formato === 'excel') {
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_panes_' . date('Ymd') . '.xls"');
    header('Cache-Control: max-age=0');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel">';
    echo '<head><meta charset="utf-8"></head><body>';
    echo '<table border="1" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:11pt;">';
    // Título
    echo '<tr><td colspan="5" style="background:#1a237e;color:#fff;font-weight:bold;font-size:13pt;padding:8px;">';
    echo htmlspecialchars("Reporte de Panes — " . $nombre_tienda) . '</td></tr>';
    echo '<tr><td colspan="5" style="background:#e8eaf6;color:#283593;padding:4px 8px;">';
    echo 'Fecha de entrega: ' . $fecha_entrega_fmt . ' &nbsp;&nbsp; Generado: ' . $fecha_generado . '</td></tr>';
    echo '<tr><td colspan="5"></td></tr>';

    foreach ($reporte_por_aprendiz as $aprendiz => $productos) {
        // Cabecera aprendiz
        echo '<tr><td colspan="5" style="background:#c5cae9;color:#1a237e;font-weight:bold;padding:5px 8px;">';
        echo htmlspecialchars($aprendiz) . '</td></tr>';
        // Cabecera columnas
        echo '<tr style="background:#e8eaf6;">';
        echo '<th style="padding:4px 8px;text-align:left;">Pan / Producto</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Cant. Base</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Ñapa</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Bonificación</th>';
        echo '<th style="padding:4px 8px;text-align:center;">Total und</th>';
        echo '</tr>';
        $subtotal = 0;
        foreach ($productos as $pr) {
            $total_und = $pr['cantidad'] + $pr['napa'] + $pr['bonificacion'];
            $subtotal += $total_und;
            echo '<tr>';
            echo '<td style="padding:4px 8px;">' . htmlspecialchars($pr['producto']) . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$pr['cantidad'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$pr['napa'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;">' . (int)$pr['bonificacion'] . '</td>';
            echo '<td style="padding:4px 8px;text-align:center;font-weight:bold;">' . $total_und . '</td>';
            echo '</tr>';
        }
        // Subtotal aprendiz (solo unidades, sin monto individual)
        echo '<tr style="background:#f5f5f5;">';
        echo '<td colspan="4" style="padding:4px 8px;text-align:right;font-weight:bold;color:#283593;">Total ' . htmlspecialchars($aprendiz) . ':</td>';
        echo '<td style="padding:4px 8px;text-align:center;font-weight:bold;color:#1a237e;">' . $subtotal . ' und</td>';
        echo '</tr>';
        echo '<tr><td colspan="5" style="padding:3px;"></td></tr>';
    }

    if ($total_general > 0) {
        echo '<tr style="background:#1a237e;">';
        echo '<td colspan="4" style="padding:6px 8px;color:#fff;font-weight:bold;text-align:right;">TOTAL GENERAL:</td>';
        echo '<td style="padding:6px 8px;color:#fff;font-weight:bold;text-align:center;">$' . number_format($total_general, 0, ',', '.') . '</td>';
        echo '</tr>';
    }
    echo '</table></body></html>';
    exit;
}

// ── PDF (página imprimible) ────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte Panes — <?= htmlspecialchars($nombre_tienda) ?></title>
<style>
    @page { margin: 1.5cm 1.8cm; }
    * { margin:0; padding:0; box-sizing:border-box; }
    body { font-family: 'Arial', sans-serif; font-size: 10.5pt; color: #1a1a1a; background: #fff; }

    .encabezado { display:flex; justify-content:space-between; align-items:flex-start; padding-bottom:.8rem; border-bottom:2px solid #1a237e; margin-bottom:1rem; }
    .enc-titulo { font-size:15pt; font-weight:800; color:#1a237e; }
    .enc-titulo span { font-size:10pt; font-weight:400; color:#555; display:block; margin-top:.15rem; }
    .enc-meta { text-align:right; font-size:8.5pt; color:#555; line-height:1.6; }
    .enc-meta strong { color:#1a237e; }

    .aprendiz-block { margin-bottom:1.2rem; border:1px solid #c5cae9; border-radius:6px; overflow:hidden; page-break-inside:avoid; }
    .aprendiz-nombre { background:#e8eaf6; padding:.45rem .75rem; font-weight:800; font-size:10pt; color:#1a237e; border-bottom:1px solid #c5cae9; }
    table { width:100%; border-collapse:collapse; }
    th { font-size:7.5pt; text-transform:uppercase; letter-spacing:.05em; color:#555; font-weight:700; padding:.4rem .65rem; background:#f5f5f5; border-bottom:1px solid #e0e0e0; text-align:left; }
    th:not(:first-child) { text-align:center; }
    td { font-size:9pt; padding:.4rem .65rem; border-bottom:1px solid #f0f0f0; vertical-align:middle; }
    tr:last-child td { border-bottom:none; }
    td:not(:first-child) { text-align:center; }
    .badge-napa  { font-size:7pt; background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; border-radius:3px; padding:1px 4px; }
    .badge-bonif { font-size:7pt; background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb; border-radius:3px; padding:1px 4px; }
    .subtotal-row { background:#f9f9f9; }
    .subtotal-row td { font-weight:700; color:#283593; font-size:8.5pt; }

    .total-general { margin-top:1rem; background:#1a237e; color:#fff; border-radius:8px; padding:.7rem 1rem; display:flex; justify-content:space-between; align-items:center; }
    .total-general .lbl { font-size:9pt; font-weight:700; text-transform:uppercase; letter-spacing:.05em; opacity:.9; }
    .total-general .val { font-size:15pt; font-weight:800; }

    .pie-pagina { margin-top:1.5rem; text-align:center; font-size:8pt; color:#aaa; border-top:1px solid #eee; padding-top:.6rem; }

    .no-print { position:fixed; top:1rem; right:1rem; display:flex; gap:.5rem; z-index:999; }
    .btn-print { background:#1a237e; color:#fff; border:none; border-radius:8px; padding:.6rem 1.2rem; font-size:.88rem; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:.4rem; }
    .btn-print:hover { background:#283593; }
    .btn-close { background:#555; color:#fff; border:none; border-radius:8px; padding:.6rem 1rem; font-size:.88rem; font-weight:700; cursor:pointer; }
    @media print { .no-print { display:none !important; } }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Cerrar</button>
</div>

<div class="encabezado">
    <div>
        <div class="enc-titulo">
            Reporte de Panes por Aprendiz
            <span><?= htmlspecialchars($nombre_tienda) ?></span>
        </div>
    </div>
    <div class="enc-meta">
        <strong>Fecha de entrega:</strong> <?= $fecha_entrega_fmt ?><br>
        <strong>Generado:</strong> <?= $fecha_generado ?><br>
        <strong>Pedido ref.:</strong> #<?= str_pad($id_pedido, 4, '0', STR_PAD_LEFT) ?>
    </div>
</div>

<?php foreach ($reporte_por_aprendiz as $aprendiz => $productos): ?>
<div class="aprendiz-block">
    <div class="aprendiz-nombre"><?= htmlspecialchars($aprendiz) ?></div>
    <table>
        <thead>
            <tr>
                <th>Pan / Producto</th>
                <th>Cant. Base</th>
                <th>Ñapa</th>
                <th>Bonificación</th>
                <th>Total und</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $subtotal_und = 0;
        foreach ($productos as $pr):
            $total_und = $pr['cantidad'] + $pr['napa'] + $pr['bonificacion'];
            $subtotal_und += $total_und;
        ?>
            <tr>
                <td><?= htmlspecialchars($pr['producto']) ?></td>
                <td><?= (int)$pr['cantidad'] ?></td>
                <td>
                    <?php if ($pr['napa'] > 0): ?>
                        <span class="badge-napa">+<?= (int)$pr['napa'] ?></span>
                    <?php else: ?>0<?php endif; ?>
                </td>
                <td>
                    <?php if ($pr['bonificacion'] > 0): ?>
                        <span class="badge-bonif">+<?= (int)$pr['bonificacion'] ?></span>
                    <?php else: ?>0<?php endif; ?>
                </td>
                <td><strong><?= $total_und ?></strong></td>
            </tr>
        <?php endforeach; ?>
        <tr class="subtotal-row">
            <td colspan="4" style="text-align:right;">Total <?= htmlspecialchars($aprendiz) ?>:</td>
            <td><?= $subtotal_und ?> und</td>
        </tr>
        </tbody>
    </table>
</div>
<?php endforeach; ?>

<?php if ($total_general > 0): ?>
<div class="total-general">
    <span class="lbl">Total General de la Tienda</span>
    <span class="val">$<?= number_format($total_general, 0, ',', '.') ?></span>
</div>
<?php endif; ?>

<div class="pie-pagina">
    BreadControl · Reporte generado el <?= $fecha_generado ?> · <?= htmlspecialchars($nombre_tienda) ?>
</div>

</body>
</html>
