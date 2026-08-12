<?php
// views/portal/exportar_recibo_pago.php — Comprobante de pago.
//
// Es el único de los reportes que además de informar sirve como constancia
// entre dos partes: por eso conserva el número de comprobante, el detalle de
// lo pagado y las dos líneas de firma al pie.

require_once __DIR__ . '/../partials/reporte_documento.php';

$numero    = 'PC-' . str_pad((string) $pedido['id_pedido'], 5, '0', STR_PAD_LEFT);
$abonado   = $total_pagado ?: $pedido['total_estimado'];

reporte_documento_inicio([
    'titulo'    => 'Comprobante de pago',
    'subtitulo' => 'Constancia de los pedidos cubiertos por este pago',
    'meta'      => [
        'Comprobante' => $numero,
        'Emitido'     => date('d/m/Y H:i'),
    ],
]);
?>

<div class="rp-cifras">
    <div class="rp-cifra">
        <span class="k">Pagador</span>
        <span class="v" style="font-size:9.5pt;"><?= htmlspecialchars($_SESSION['cliente_nombre'] ?? '') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Referencia</span>
        <span class="v" style="font-size:9.5pt;"><?= htmlspecialchars($pago_activo['referencia'] ?? 'Individual') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Método</span>
        <span class="v" style="font-size:9.5pt;"><?= htmlspecialchars($pago_activo['metodo_pago'] ?? 'Nequi / Digital') ?></span>
    </div>
    <div class="rp-cifra destacada">
        <span class="k">Estado</span>
        <span class="v" style="font-size:9.5pt;"><?= htmlspecialchars(ucfirst((string) ($pedido['estado_pago'] ?? ''))) ?></span>
    </div>
</div>

<?php if (!empty($pedidos_consolidados)): ?>
<section class="rp-seccion">
    <h2 class="rp-seccion-titulo">Pedidos cubiertos</h2>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th class="num">Pedido</th>
                <th>Aprendiz</th>
                <th>Entrega</th>
                <th class="num">Valor</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pedidos_consolidados as $pc): ?>
            <tr>
                <td class="num">#<?= str_pad((string) $pc['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                <td><?= htmlspecialchars($pc['nombre_aprendiz'] ?? '—') ?></td>
                <td><?= formatearFechaEntrega($pc['fecha_entrega']) ?></td>
                <td class="num">$<?= number_format((float) $pc['total_estimado'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php if (!empty($detalles)): ?>
<section class="rp-seccion">
    <h2 class="rp-seccion-titulo">
        Detalle del pedido #<?= str_pad((string) $pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
    </h2>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th>Producto</th>
                <th class="num">Solicitado</th>
                <th class="num">Ñapa / bonif.</th>
                <th class="num">Total und.</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($detalles as $d):
            $total_und = (int) $d['cantidad'] + (int) $d['napa'] + (int) $d['bonificacion'];
        ?>
            <tr>
                <td><?= htmlspecialchars($d['producto'] ?? '') ?></td>
                <td class="num"><?= (int) $d['cantidad'] ?> und.</td>
                <td class="num">
                    <?php if ((int) $d['napa'] > 0): ?>
                        <span class="rp-chip rp-chip-napa">+<?= (int) $d['napa'] ?> ñapa</span>
                    <?php elseif ((int) $d['bonificacion'] > 0): ?>
                        <span class="rp-chip rp-chip-bonif">+<?= (int) $d['bonificacion'] ?> bonif.</span>
                    <?php else: ?><span class="rp-vacio">—</span><?php endif; ?>
                </td>
                <td class="num"><strong><?= $total_und ?></strong></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<?php if (!empty($abonos)): ?>
<section class="rp-seccion">
    <h2 class="rp-seccion-titulo">Transacciones registradas</h2>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Método</th>
                <th>Observaciones</th>
                <th class="num">Monto</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($abonos as $ab): ?>
            <tr>
                <td><?= date('d/m/Y H:i', (int) strtotime($ab['fecha_abono'])) ?></td>
                <td><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? (string) $ab['metodo_pago']) ?></td>
                <td><?= htmlspecialchars($ab['nota'] ?? 'Confirmación de pago') ?></td>
                <td class="num">$<?= number_format((float) $ab['monto'], 0, ',', '.') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php endif; ?>

<div class="rp-total-general">
    <span class="lbl">Total abonado</span>
    <span class="val">$<?= number_format((float) $abonado, 0, ',', '.') ?></span>
</div>

<div style="display:flex; gap:3rem; margin-top:3.5rem; break-inside:avoid;">
    <div style="flex:1; border-top:.5pt solid var(--r-tinta-3); padding-top:.35rem; font-size:8pt; color:var(--r-tinta-2); text-align:center;">
        Firma del encargado / panadería
    </div>
    <div style="flex:1; border-top:.5pt solid var(--r-tinta-3); padding-top:.35rem; font-size:8pt; color:var(--r-tinta-2); text-align:center;">
        Firma del cliente / pagador
    </div>
</div>

<?php reporte_documento_fin('Comprobante ' . $numero); ?>
