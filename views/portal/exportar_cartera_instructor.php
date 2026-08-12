<?php
// views/portal/exportar_cartera_instructor.php — Cartera de aprendices del instructor.
// Documento de cobro: quién debe cuánto y cuánto cupo lleva consumido esta semana.

require_once __DIR__ . '/../partials/reporte_documento.php';

$total_deuda = 0.0;
foreach ($aprendices as $a) {
    $total_deuda += (float) ($a['saldo_pendiente'] ?? 0);
}

reporte_documento_inicio([
    'titulo'    => 'Cartera de aprendices',
    'subtitulo' => 'Consumo, cupo semanal y saldo pendiente por aprendiz',
    'meta'      => [
        'Aprendices' => (string) count($aprendices),
        'Por cobrar' => '$' . number_format($total_deuda, 0, ',', '.'),
    ],
]);
?>

<div class="rp-cifras">
    <div class="rp-cifra destacada">
        <span class="k">Pendiente total</span>
        <span class="v">$<?= number_format($resumen_fin['pendiente_total'] ?? 0, 0, ',', '.') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Comprado en el mes</span>
        <span class="v">$<?= number_format($resumen_fin['total_mes'] ?? 0, 0, ',', '.') ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Aprendices</span>
        <span class="v"><?= count($aprendices) ?></span>
    </div>
</div>

<?php if (empty($aprendices)): ?>
    <p class="rp-sin-datos">Todavía no hay aprendices vinculados a este instructor.</p>
<?php else: ?>
<table class="rp-tabla">
    <thead>
        <tr>
            <th>Aprendiz</th>
            <th>Contacto</th>
            <th class="num">Pedidos</th>
            <th class="num">Comprado</th>
            <th class="num">Cupo semanal</th>
            <th class="num">Saldo pendiente</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($aprendices as $a): ?>
        <tr>
            <td><strong><?= htmlspecialchars($a['nombre'] ?? '') ?></strong></td>
            <td style="font-size:8pt;">
                <?php $contacto = $a['email'] ?: ($a['telefono'] ?: ''); ?>
                <?= $contacto !== '' ? htmlspecialchars($contacto) : '<span class="rp-vacio">Sin contacto</span>' ?>
            </td>
            <td class="num"><?= (int) $a['total_pedidos'] ?></td>
            <td class="num">$<?= number_format((float) $a['total_comprado'], 0, ',', '.') ?></td>
            <td class="num">
                $<?= number_format((float) ($a['consumido_semana'] ?? 0), 0, ',', '.') ?>
                <span class="rp-vacio">/ $<?= number_format((float) ($a['cupo_semanal'] ?? 20000), 0, ',', '.') ?></span>
            </td>
            <td class="num">
                <?php if ((int) $a['total_pedidos'] === 0): ?>
                    <span class="rp-vacio">Sin pedidos</span>
                <?php elseif ((float) $a['saldo_pendiente'] > 0): ?>
                    <strong>$<?= number_format((float) $a['saldo_pendiente'], 0, ',', '.') ?></strong>
                <?php else: ?>
                    <span class="rp-chip rp-chip-bonif">Al día</span>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="5" style="text-align:right;">Total por cobrar</td>
            <td class="num">$<?= number_format($total_deuda, 0, ',', '.') ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<?php reporte_documento_fin('Cartera de aprendices'); ?>
