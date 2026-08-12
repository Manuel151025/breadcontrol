<?php
// views/portal/exportar_reporte_tienda.php
//
// Dos artefactos con propósitos distintos a partir del mismo reporte:
//  - HOJA DE CÁLCULO: tabla plana, una fila por aprendiz+producto, para filtrar
//    y pivotar. Sin títulos ni subtotales, que son justo lo que impide usar los
//    datos en Excel.
//  - PDF: documento presentable, con secciones por aprendiz, subtotales y total
//    general. Ver views/partials/reporte_documento.php.

require_once __DIR__ . '/../../helpers/ExportadorCsv.php';

// ── HOJA DE CÁLCULO ────────────────────────────────────────────────────────
if ($formato === 'excel') {
    $filas = [];
    foreach ($reporte_por_aprendiz as $aprendiz => $productos) {
        foreach ($productos as $pr) {
            $cantidad     = (int) $pr['cantidad'];
            $napa         = (int) $pr['napa'];
            $bonificacion = (int) $pr['bonificacion'];
            $filas[] = [
                $aprendiz,
                $pr['producto'] ?? '',
                $cantidad,
                $napa,
                $bonificacion,
                $cantidad + $napa + $bonificacion,
                $fecha_entrega_fmt,
                $nombre_tienda,
            ];
        }
    }

    ExportadorCsv::enviar(
        'reporte_panes',
        ['Aprendiz', 'Producto', 'Cantidad', 'Ñapa', 'Bonificación', 'Total unidades', 'Fecha de entrega', 'Tienda'],
        $filas
    );
}

// ── PDF ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../partials/reporte_documento.php';

reporte_documento_inicio([
    'titulo'    => 'Reporte de panes por aprendiz',
    'subtitulo' => $nombre_tienda,
    'meta'      => [
        'Entrega' => $fecha_entrega_fmt,
        'Pedido'  => '#' . str_pad((string) $id_pedido, 4, '0', STR_PAD_LEFT),
    ],
]);

$total_unidades = 0;
foreach ($reporte_por_aprendiz as $productos) {
    foreach ($productos as $pr) {
        $total_unidades += (int) $pr['cantidad'] + (int) $pr['napa'] + (int) $pr['bonificacion'];
    }
}
?>

<?php if (empty($reporte_por_aprendiz)): ?>
    <p class="rp-sin-datos">Este pedido no tiene panes registrados para la fecha de entrega indicada.</p>
<?php else: ?>

<div class="rp-cifras">
    <div class="rp-cifra">
        <span class="k">Aprendices</span>
        <span class="v"><?= count($reporte_por_aprendiz) ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Unidades totales</span>
        <span class="v"><?= number_format($total_unidades, 0, ',', '.') ?></span>
    </div>
    <?php if ($total_general > 0): ?>
    <div class="rp-cifra destacada">
        <span class="k">Valor del pedido</span>
        <span class="v">$<?= number_format($total_general, 0, ',', '.') ?></span>
    </div>
    <?php endif; ?>
</div>

<?php foreach ($reporte_por_aprendiz as $aprendiz => $productos): ?>
<section class="rp-seccion">
    <h2 class="rp-seccion-titulo"><?= htmlspecialchars($aprendiz ?? '') ?></h2>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th>Pan / producto</th>
                <th class="num">Cantidad</th>
                <th class="num">Ñapa</th>
                <th class="num">Bonificación</th>
                <th class="num">Total und.</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $subtotal = 0;
        foreach ($productos as $pr):
            $cantidad = (int) $pr['cantidad'];
            $napa     = (int) $pr['napa'];
            $bonif    = (int) $pr['bonificacion'];
            $total    = $cantidad + $napa + $bonif;
            $subtotal += $total;
        ?>
            <tr>
                <td><?= htmlspecialchars($pr['producto'] ?? '') ?></td>
                <td class="num"><?= $cantidad ?></td>
                <td class="num">
                    <?php if ($napa > 0): ?>
                        <span class="rp-chip rp-chip-napa">+<?= $napa ?></span>
                    <?php else: ?><span class="rp-vacio">—</span><?php endif; ?>
                </td>
                <td class="num">
                    <?php if ($bonif > 0): ?>
                        <span class="rp-chip rp-chip-bonif">+<?= $bonif ?></span>
                    <?php else: ?><span class="rp-vacio">—</span><?php endif; ?>
                </td>
                <td class="num"><strong><?= $total ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;">Total <?= htmlspecialchars($aprendiz ?? '') ?></td>
                <td class="num"><?= $subtotal ?> und.</td>
            </tr>
        </tfoot>
    </table>
</section>
<?php endforeach; ?>

<?php if ($total_general > 0): ?>
<div class="rp-total-general">
    <span class="lbl">Total general de la tienda</span>
    <span class="val">$<?= number_format($total_general, 0, ',', '.') ?></span>
</div>
<?php endif; ?>

<?php endif; ?>

<?php reporte_documento_fin($nombre_tienda); ?>
