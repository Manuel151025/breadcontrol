<?php
// views/finanzas/exportar_pdf.php — Reporte financiero del período.
//
// Es el documento con más carga de análisis del sistema: cifras del período,
// ventas por día, productos más vendidos, compras y detalle de ventas. El
// estilo sale de assets/css/reporte.css, común a todas las exportaciones.

require_once __DIR__ . '/../partials/reporte_documento.php';

$periodo = date('d/m/Y', (int) strtotime($desde)) . ' — ' . date('d/m/Y', (int) strtotime($hasta));

reporte_documento_inicio([
    'titulo'    => 'Reporte financiero',
    'subtitulo' => $titulo_periodo ?? $periodo,
    'meta'      => [
        'Período'  => $periodo,
        'Generado' => date('d/m/Y H:i'),
        'Por'      => $user['nombre'] ?? '',
    ],
]);
?>

<div class="rp-cifras">
    <div class="rp-cifra destacada">
        <span class="k">Ingresos totales</span>
        <span class="v">$<?= number_format($ingresos, 0, ',', '.') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Compras de insumos</span>
        <span class="v">$<?= number_format($compras_total, 0, ',', '.') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Utilidad bruta</span>
        <span class="v <?= $utilidad_bruta >= 0 ? 'rp-pos' : 'rp-neg' ?>">
            <?= $utilidad_bruta >= 0 ? '+' : '−' ?>$<?= number_format(abs($utilidad_bruta), 0, ',', '.') ?>
        </span>
    </div>
    <div class="rp-cifra">
        <span class="k">Margen bruto</span>
        <span class="v"><?= $margen_bruto ?>%</span>
    </div>
    <div class="rp-cifra">
        <span class="k">Utilidad neta</span>
        <span class="v <?= $utilidad_neta >= 0 ? 'rp-pos' : 'rp-neg' ?>">
            <?= $utilidad_neta >= 0 ? '+' : '−' ?>$<?= number_format(abs($utilidad_neta), 0, ',', '.') ?>
        </span>
    </div>
</div>

<?php if (!empty($dias_chart) && count($dias_chart) > 1): ?>
<section class="rp-seccion rp-grafico">
    <h2 class="rp-seccion-titulo">Ventas por día</h2>
    <div class="rp-grafico-barras">
        <?php
        $hoy_str = date('Y-m-d');
        foreach ($dias_chart as $dc):
            $alto = $chart_max > 0 ? max(1, round(($dc['v'] / $chart_max) * 100)) : 1;
        ?>
        <div class="rp-gb">
            <div class="rp-gb-barra <?= $dc['f'] === $hoy_str ? 'hoy' : '' ?>" style="height:<?= $alto ?>%"></div>
            <?php if (count($dias_chart) <= 22): ?>
            <div class="rp-gb-lbl"><?= htmlspecialchars((string) $dc['lbl']) ?></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="rp-grafico-pie">
        <span><?= count($dias_chart) ?> días del período</span>
        <span>Máximo del día: $<?= number_format($chart_max, 0, ',', '.') ?></span>
    </div>
</section>
<?php endif; ?>

<div class="rp-dos-col">
    <section class="rp-seccion">
        <h2 class="rp-seccion-titulo">Productos más vendidos</h2>
        <?php if (empty($top_prod)): ?>
            <p class="rp-seccion-nota">Sin ventas en este período.</p>
        <?php else: ?>
        <div class="rp-ranking">
            <?php
            $max_p = max(array_column($top_prod, 't') ?: [1]);
            foreach ($top_prod as $i => $pp):
                $pct = $max_p > 0 ? round(($pp['t'] / $max_p) * 100) : 0;
            ?>
            <div class="rp-rk">
                <span class="rp-rk-num"><?= $i + 1 ?></span>
                <span class="rp-rk-nombre"><?= htmlspecialchars($pp['nombre'] ?? '') ?></span>
                <span class="rp-rk-und"><?= (int) $pp['u'] ?> und.</span>
                <span class="rp-rk-val">$<?= number_format($pp['t'], 0, ',', '.') ?></span>
                <div class="rp-rk-barra" style="grid-column:2/3;"><i style="width:<?= $pct ?>%"></i></div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="rp-seccion">
        <h2 class="rp-seccion-titulo">Compras de insumos</h2>
        <?php if (empty($detalle_compras)): ?>
            <p class="rp-seccion-nota">Sin compras en este período.</p>
        <?php else: ?>
        <table class="rp-tabla">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Insumo</th>
                    <th class="num">Total</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($detalle_compras as $c): ?>
                <tr>
                    <td><?= date('d/m/Y', (int) strtotime($c['fecha_compra'])) ?></td>
                    <td><?= htmlspecialchars($c['insumo'] ?? '') ?></td>
                    <td class="num">$<?= number_format($c['total_pagado'], 0, ',', '.') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="2" style="text-align:right;">Total de compras</td>
                    <td class="num">$<?= number_format($compras_total, 0, ',', '.') ?></td>
                </tr>
            </tfoot>
        </table>
        <?php endif; ?>
    </section>
</div>

<section class="rp-seccion">
    <h2 class="rp-seccion-titulo">Detalle de ventas</h2>
    <?php if (empty($detalle_ventas)): ?>
        <p class="rp-seccion-nota">Sin ventas en este período.</p>
    <?php else: ?>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th class="num">Hora</th>
                <th>Cliente</th>
                <th>Producto</th>
                <th class="num">Und.</th>
                <th class="num">Precio</th>
                <th class="num">Total</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($detalle_ventas as $v):
            $bonif = (int) ($v['bonificacion'] ?? 0);
        ?>
            <tr>
                <td><?= date('d/m/Y', (int) strtotime($v['fecha'])) ?></td>
                <td class="num"><?= date('H:i', (int) strtotime($v['hora'])) ?></td>
                <td><?= htmlspecialchars($v['cliente'] ?? '') ?></td>
                <td><?= htmlspecialchars($v['producto'] ?? '') ?></td>
                <td class="num">
                    <?= (int) $v['unidades_vendidas'] ?>
                    <?php if ($bonif > 0): ?>
                        <span class="rp-chip rp-chip-bonif">+<?= $bonif ?></span>
                    <?php endif; ?>
                </td>
                <td class="num">$<?= number_format($v['precio_unitario'], 0, ',', '.') ?></td>
                <td class="num">$<?= number_format($v['total_venta'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align:right;">Total de ingresos del período</td>
                <td class="num">$<?= number_format($ingresos, 0, ',', '.') ?></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</section>

<div class="rp-total-general">
    <span class="lbl">Utilidad neta del período</span>
    <span class="val <?= $utilidad_neta >= 0 ? 'rp-pos' : 'rp-neg' ?>">
        <?= $utilidad_neta >= 0 ? '+' : '−' ?>$<?= number_format(abs($utilidad_neta), 0, ',', '.') ?>
    </span>
</div>

<?php reporte_documento_fin('Período ' . $periodo); ?>
