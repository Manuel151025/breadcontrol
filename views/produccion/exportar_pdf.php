<?php
// views/produccion/exportar_pdf.php — Reporte de producción del día.
// El estilo y la estructura del documento viven en assets/css/reporte.css y
// views/partials/reporte_documento.php, compartidos por todas las exportaciones.

require_once __DIR__ . '/../partials/reporte_documento.php';

$total_registros = count($producciones);

reporte_documento_inicio([
    'titulo'    => 'Producción del día',
    'subtitulo' => 'Tandas registradas y su detalle por variedad',
    'meta'      => [
        'Producción' => date('d/m/Y', (int) strtotime($fecha_fil)),
        'Registros'  => (string) $total_registros,
    ],
]);
?>

<div class="rp-cifras">
    <div class="rp-cifra destacada">
        <span class="k">Tandas producidas</span>
        <span class="v"><?= (int) $total_tandas ?></span>
    </div>
    <div class="rp-cifra">
        <span class="k">Variedades</span>
        <span class="v"><?= $total_registros ?></span>
    </div>
</div>

<?php if (empty($producciones)): ?>
    <p class="rp-sin-datos">No hay registros de producción para esta fecha.</p>
<?php else: ?>
<table class="rp-tabla">
    <thead>
        <tr>
            <th class="num">#</th>
            <th>Producto / variedad</th>
            <th class="num">Tandas</th>
            <th>Operario</th>
            <th class="num">Hora</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($producciones as $pr): ?>
        <tr>
            <td class="num"><?= (int) $pr['id_produccion'] ?></td>
            <td>
                <strong><?= htmlspecialchars($pr['producto'] ?? '') ?></strong><br>
                <span class="rp-vacio" style="font-size:7.5pt;"><?= htmlspecialchars($pr['unidad_produccion'] ?? '') ?></span>
            </td>
            <td class="num"><strong><?= (int) $pr['cantidad_tandas'] ?></strong></td>
            <td><?= htmlspecialchars($pr['operario'] ?? '—') ?></td>
            <td class="num"><?= date('H:i', (int) strtotime($pr['fecha_produccion'])) ?></td>
            <td style="font-size:8pt;">
                <?= $pr['observaciones'] ? htmlspecialchars($pr['observaciones']) : '<span class="rp-vacio">—</span>' ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr>
            <td colspan="2" style="text-align:right;">Total de tandas</td>
            <td class="num"><?= (int) $total_tandas ?></td>
            <td colspan="3"></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<?php reporte_documento_fin('Producción del ' . date('d/m/Y', (int) strtotime($fecha_fil))); ?>
