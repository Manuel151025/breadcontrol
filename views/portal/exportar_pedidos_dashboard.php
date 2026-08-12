<?php
// views/portal/exportar_pedidos_dashboard.php — Pedidos detallados del portal.
//
// Hoja de cálculo: una fila por producto de cada pedido, con los datos del
// pedido repetidos, para poder filtrar por aprendiz, producto o estado.
// PDF: un bloque por pedido con su detalle, pensado para imprimir y despachar.

require_once __DIR__ . '/../../helpers/ExportadorCsv.php';

// ── HOJA DE CÁLCULO ────────────────────────────────────────────────────────
if ($formato === 'excel') {
    $filas = [];
    foreach ($pedidos as $ped) {
        $comunes = [
            (int) $ped['id_pedido'],
            $ped['aprendiz'] ?? '',
            formatearFechaEntrega($ped['fecha_entrega'], false),
            $ped['estado'],
            (float) $ped['total_estimado'],
        ];

        $detalles = $detalles_por_pedido[$ped['id_pedido']] ?? [];
        if ($detalles === []) {
            $filas[] = array_merge($comunes, ['', 0, 0, 0, 0]);
            continue;
        }
        foreach ($detalles as $d) {
            $cantidad = (int) $d['cantidad'];
            $napa     = (int) $d['napa'];
            $bonif    = (int) $d['bonificacion'];
            $filas[] = array_merge($comunes, [
                $d['producto'] ?? '',
                $cantidad,
                $napa,
                $bonif,
                $cantidad + $napa + $bonif,
            ]);
        }
    }

    ExportadorCsv::enviar(
        'pedidos_detallados',
        [
            'ID pedido', 'Aprendiz', 'Fecha de entrega', 'Estado', 'Total estimado',
            'Producto', 'Cantidad', 'Ñapa', 'Bonificación', 'Total unidades',
        ],
        $filas
    );
}

// ── PDF ────────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../partials/reporte_documento.php';

$total_valor    = 0.0;
$total_unidades = 0;
foreach ($pedidos as $ped) {
    $total_valor += (float) $ped['total_estimado'];
    foreach (($detalles_por_pedido[$ped['id_pedido']] ?? []) as $d) {
        $total_unidades += (int) $d['cantidad'] + (int) $d['napa'] + (int) $d['bonificacion'];
    }
}

reporte_documento_inicio([
    'titulo'    => 'Pedidos detallados',
    'subtitulo' => $nombre_tienda ?? '',
    'meta'      => [
        'Pedidos'  => (string) count($pedidos),
        'Unidades' => number_format($total_unidades, 0, ',', '.'),
        'Valor'    => '$' . number_format($total_valor, 0, ',', '.'),
    ],
]);
?>

<?php if (empty($pedidos)): ?>
    <p class="rp-sin-datos">No hay pedidos para exportar con el filtro seleccionado.</p>
<?php else: ?>

<?php foreach ($pedidos as $ped):
    $detalles = $detalles_por_pedido[$ped['id_pedido']] ?? [];
    $subtotal_und = 0;
?>
<section class="rp-seccion">
    <h2 class="rp-seccion-titulo">
        Pedido #<?= str_pad((string) $ped['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
        · <?= htmlspecialchars($ped['aprendiz'] ?? '') ?>
    </h2>
    <p class="rp-seccion-nota">
        Entrega: <?= formatearFechaEntrega($ped['fecha_entrega'], true) ?>
        · Estado: <?= htmlspecialchars(ucfirst((string) $ped['estado'])) ?>
        · Valor: $<?= number_format((float) $ped['total_estimado'], 0, ',', '.') ?>
    </p>

    <?php if ($detalles === []): ?>
        <p class="rp-vacio" style="font-size:8.5pt;">Este pedido no tiene productos registrados.</p>
    <?php else: ?>
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
        <?php foreach ($detalles as $d):
            $cantidad = (int) $d['cantidad'];
            $napa     = (int) $d['napa'];
            $bonif    = (int) $d['bonificacion'];
            $total    = $cantidad + $napa + $bonif;
            $subtotal_und += $total;
        ?>
            <tr>
                <td><?= htmlspecialchars($d['producto'] ?? '') ?></td>
                <td class="num"><?= $cantidad ?></td>
                <td class="num"><?= $napa > 0 ? '<span class="rp-chip rp-chip-napa">+' . $napa . '</span>' : '<span class="rp-vacio">—</span>' ?></td>
                <td class="num"><?= $bonif > 0 ? '<span class="rp-chip rp-chip-bonif">+' . $bonif . '</span>' : '<span class="rp-vacio">—</span>' ?></td>
                <td class="num"><strong><?= $total ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="4" style="text-align:right;">Total del pedido</td>
                <td class="num"><?= $subtotal_und ?> und.</td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>
</section>
<?php endforeach; ?>

<div class="rp-total-general">
    <span class="lbl">Total de los pedidos listados</span>
    <span class="val">$<?= number_format($total_valor, 0, ',', '.') ?></span>
</div>

<?php endif; ?>

<?php reporte_documento_fin($nombre_tienda ?? ''); ?>
