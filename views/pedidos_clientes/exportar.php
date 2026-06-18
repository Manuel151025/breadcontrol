<?php
if ($formato === 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
    header("Content-Disposition: attachment; filename=pedidos_" . date('Ymd_His') . ".xls");
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF"; // UTF-8 BOM
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="UTF-8"></head>
    <body>
        <table border="1">
            <thead>
                <tr>
                    <th style="background-color: #945b35; color: white;">ID Pedido</th>
                    <th style="background-color: #945b35; color: white;">Cliente</th>
                    <th style="background-color: #945b35; color: white;">Teléfono</th>
                    <th style="background-color: #945b35; color: white;">Tipo Cliente</th>
                    <th style="background-color: #945b35; color: white;">Fecha Solicitud</th>
                    <th style="background-color: #945b35; color: white;">Fecha Entrega</th>
                    <th style="background-color: #945b35; color: white;">Productos (Pan)</th>
                    <th style="background-color: #945b35; color: white;">Total Estimado</th>
                    <th style="background-color: #945b35; color: white;">Estado</th>
                    <th style="background-color: #945b35; color: white;">Mensaje de Panadería</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td><?= $p['id_pedido'] ?></td>
                    <td><?= htmlspecialchars($p['cliente']) ?></td>
                    <td><?= htmlspecialchars($p['telefono']) ?></td>
                    <td><?= htmlspecialchars($p['tipo_cliente']) ?></td>
                    <td><?= $p['fecha_solicitud'] ?></td>
                    <td><?= formatearFechaEntrega($p['fecha_entrega'], false) ?></td>
                    <td>
                        <?php 
                        $prods = $det_por_pedido[$p['id_pedido']] ?? [];
                        echo empty($prods) ? '-' : implode("<br>", $prods);
                        ?>
                    </td>
                    <td><?= $p['total_estimado'] ?></td>
                    <td><?= $p['estado'] ?></td>
                    <td><?= htmlspecialchars($p['mensaje_propietario']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}

if ($formato === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <title>Exportar Pedidos</title>
        <style>
            body { font-family: Arial, sans-serif; padding: 20px; color: #333; }
            h1 { text-align: center; color: #945b35; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th, td { border: 1px solid #ccc; padding: 8px; text-align: left; font-size: 14px; }
            th { background-color: #faf3ea; color: #6b3d1e; }
            @media print {
                .no-print { display: none; }
                body { padding: 0; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="margin-bottom:20px; text-align:center;">
            <button onclick="window.print()" style="padding:10px 20px; font-size:16px; cursor:pointer;">Imprimir / Guardar como PDF</button>
        </div>
        <h1>Reporte de Pedidos de Clientes</h1>
        <p>Generado el: <?= date('d/m/Y H:i') ?></p>
        
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>F. Entrega</th>
                    <th>Solicitado</th>
                    <th>Productos (Pan)</th>
                    <th>Total Est.</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos as $p): ?>
                <tr>
                    <td>#<?= str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td><?= htmlspecialchars($p['cliente']) ?> (<?= $p['tipo_cliente'] ?>)</td>
                    <td><?= htmlspecialchars($p['telefono']) ?></td>
                    <td>
                        <?= formatearFechaEntrega($p['fecha_entrega'], false) ?>
                    </td>
                    <td><?= date('d/m/Y H:i', strtotime($p['fecha_solicitud'])) ?></td>
                    <td style="font-size: 12px; line-height: 1.4;">
                        <?php 
                        $prods = $det_por_pedido[$p['id_pedido']] ?? [];
                        echo empty($prods) ? '-' : implode("<br>", $prods);
                        ?>
                    </td>
                    <td>$<?= number_format($p['total_estimado'], 0, ',', '.') ?></td>
                    <td><?= strtoupper($p['estado']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
