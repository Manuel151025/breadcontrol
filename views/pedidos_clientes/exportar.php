<?php
// views/pedidos_clientes/exportar.php — Pedidos de clientes.
//
// La hoja de cálculo emite una fila por producto de cada pedido, en vez de
// meter todos los productos en una sola celda separados por saltos de línea:
// así se puede filtrar y sumar por producto, que es para lo que se exporta.
// El PDF conserva la vista compacta, un pedido por fila.

require_once __DIR__ . '/../../helpers/ExportadorCsv.php';

// ── HOJA DE CÁLCULO ────────────────────────────────────────────────────────
if ($formato === 'excel') {
    $filas = [];
    foreach ($pedidos as $p) {
        $comunes = [
            (int) $p['id_pedido'],
            $p['cliente'] ?? '',
            $p['telefono'] ?? '',
            $p['tipo_cliente'] ?? '',
            $p['fecha_solicitud'],
            formatearFechaEntrega($p['fecha_entrega'], false),
            (float) $p['total_estimado'],
            $p['estado'],
            $p['mensaje_propietario'] ?? '',
        ];

        $productos = $det_por_pedido[$p['id_pedido']] ?? [];
        if ($productos === []) {
            $filas[] = array_merge($comunes, ['']);
            continue;
        }
        foreach ($productos as $producto) {
            $filas[] = array_merge($comunes, [strip_tags((string) $producto)]);
        }
    }

    ExportadorCsv::enviar(
        'pedidos_clientes',
        [
            'ID pedido', 'Cliente', 'Teléfono', 'Tipo de cliente', 'Fecha de solicitud',
            'Fecha de entrega', 'Total estimado', 'Estado', 'Mensaje de la panadería', 'Producto',
        ],
        $filas
    );
}

// ── PDF ────────────────────────────────────────────────────────────────────
if ($formato === 'pdf') {
    require_once __DIR__ . '/../partials/reporte_documento.php';

    $total = 0.0;
    foreach ($pedidos as $p) {
        $total += (float) $p['total_estimado'];
    }

    reporte_documento_inicio([
        'titulo'     => 'Pedidos de clientes',
        'subtitulo'  => 'Listado operativo con productos y estado de cada pedido',
        'horizontal' => true,
        'meta'       => [
            'Pedidos' => (string) count($pedidos),
            'Total'   => '$' . number_format($total, 0, ',', '.'),
        ],
    ]);
    ?>

    <?php if (empty($pedidos)): ?>
        <p class="rp-sin-datos">No hay pedidos que coincidan con el filtro seleccionado.</p>
    <?php else: ?>
    <table class="rp-tabla">
        <thead>
            <tr>
                <th class="num">Pedido</th>
                <th>Cliente</th>
                <th>Teléfono</th>
                <th>Entrega</th>
                <th>Solicitado</th>
                <th>Productos</th>
                <th class="num">Total</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pedidos as $p): ?>
            <tr>
                <td class="num">#<?= str_pad((string) $p['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                <td>
                    <strong><?= htmlspecialchars($p['cliente'] ?? '') ?></strong><br>
                    <span class="rp-vacio" style="font-size:7.5pt;"><?= htmlspecialchars($p['tipo_cliente'] ?? '') ?></span>
                </td>
                <td><?= htmlspecialchars($p['telefono'] ?? '') ?: '<span class="rp-vacio">—</span>' ?></td>
                <td><?= formatearFechaEntrega($p['fecha_entrega'], false) ?></td>
                <td><?= date('d/m/Y H:i', (int) strtotime($p['fecha_solicitud'])) ?></td>
                <td style="font-size:8pt;">
                    <?php
                    $productos = $det_por_pedido[$p['id_pedido']] ?? [];
                    echo $productos === []
                        ? '<span class="rp-vacio">—</span>'
                        : implode('<br>', array_map(fn($x) => htmlspecialchars(strip_tags((string) $x)), $productos));
                    ?>
                </td>
                <td class="num">$<?= number_format((float) $p['total_estimado'], 0, ',', '.') ?></td>
                <td><?= htmlspecialchars(ucfirst((string) $p['estado'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align:right;">Total de los pedidos listados</td>
                <td class="num">$<?= number_format($total, 0, ',', '.') ?></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
    <?php endif; ?>

    <?php
    reporte_documento_fin('Pedidos de clientes');
    exit;
}
