<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
requerirPropietario();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['exportar_ids'])) {
    die("No se seleccionaron registros para exportar.");
}

$ids = $_POST['exportar_ids'];
$inQuery = implode(',', array_fill(0, count($ids), '?'));

$pdo = getConexion();
$stmt = $pdo->prepare("
    SELECT v.id_venta, v.fecha_hora, v.tipo_salida, v.unidades_vendidas, 
           v.precio_unitario, v.total_venta, COALESCE(v.unidades_bonificacion, 0) as bonificacion,
           COALESCE(cp.nombre, 'Pedido detallado') as categoria,
           COALESCE(c.nombre, 'Mostrador') as cliente
    FROM venta v
    LEFT JOIN categoria_precio cp ON cp.id_categoria = v.id_categoria_precio
    LEFT JOIN cliente c ON c.id_cliente = v.id_cliente
    WHERE v.id_venta IN ($inQuery)
    ORDER BY v.fecha_hora DESC
");
$stmt->execute($ids);
$ventas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener los detalles de esas ventas
$detalles_brutos = [];
try {
    $det_stmt = $pdo->prepare("
        SELECT vd.id_venta, vd.cantidad, vd.napa, vd.bonificacion, vd.precio_unitario, vp.nombre
        FROM venta_detalle vd
        INNER JOIN variedad_pan vp ON vp.id_variedad = vd.id_variedad
        WHERE vd.id_venta IN ($inQuery)
    ");
    $det_stmt->execute($ids);
    $detalles_brutos = $det_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // La tabla venta_detalle puede no existir aún
}

$detalles_por_venta = [];
foreach ($detalles_brutos as $d) {
    if (!isset($detalles_por_venta[$d['id_venta']])) {
        $detalles_por_venta[$d['id_venta']] = [];
    }
    $detalles_por_venta[$d['id_venta']][] = $d;
}

header("Content-Type: application/vnd.ms-excel; charset=UTF-8");
header("Content-Disposition: attachment; filename=ventas_" . date('Ymd_His') . ".xls");
header("Pragma: no-cache");
header("Expires: 0");

echo "\xEF\xBB\xBF"; // UTF-8 BOM para Excel
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
</head>
<body>
    <table border="1">
        <thead>
            <tr>
                <th style="background-color: #2e7d32; color: white;">ID Venta</th>
                <th style="background-color: #2e7d32; color: white;">Fecha y Hora</th>
                <th style="background-color: #2e7d32; color: white;">Tipo de Salida</th>
                <th style="background-color: #2e7d32; color: white;">Categoría/Producto</th>
                <th style="background-color: #2e7d32; color: white;">Cliente</th>
                <th style="background-color: #2e7d32; color: white;">Unidades</th>
                <th style="background-color: #2e7d32; color: white;">Precio Unitario</th>
                <th style="background-color: #2e7d32; color: white;">Total Venta</th>
                <th style="background-color: #2e7d32; color: white;">Bonificación/Ñapa</th>
                <th style="background-color: #4caf50; color: white;">Detalle: Producto</th>
                <th style="background-color: #4caf50; color: white;">Detalle: Cantidad</th>
                <th style="background-color: #4caf50; color: white;">Detalle: Ñapa/Bonif</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $tot_und = 0; $tot_dinero = 0; $tot_bonif = 0;
            foreach ($ventas as $v): 
                $tot_und += $v['unidades_vendidas'];
                $tot_dinero += $v['total_venta'];
                $tot_bonif += $v['bonificacion'];
                $dets = $detalles_por_venta[$v['id_venta']] ?? [];
                
                if (count($dets) > 0):
                    $first = true;
                    foreach ($dets as $d):
            ?>
            <tr>
                <?php if ($first): ?>
                <td rowspan="<?= count($dets) ?>"><?= $v['id_venta'] ?></td>
                <td rowspan="<?= count($dets) ?>"><?= $v['fecha_hora'] ?></td>
                <td rowspan="<?= count($dets) ?>"><?= ucfirst(str_replace('_', ' ', $v['tipo_salida'])) ?></td>
                <td rowspan="<?= count($dets) ?>"><?= htmlspecialchars($v['categoria']) ?></td>
                <td rowspan="<?= count($dets) ?>"><?= htmlspecialchars($v['cliente']) ?></td>
                <td rowspan="<?= count($dets) ?>"><?= $v['unidades_vendidas'] ?></td>
                <td rowspan="<?= count($dets) ?>"><?= $v['precio_unitario'] ?></td>
                <td rowspan="<?= count($dets) ?>"><?= $v['total_venta'] ?></td>
                <td rowspan="<?= count($dets) ?>"><?= $v['bonificacion'] ?></td>
                <?php $first = false; endif; ?>
                <td style="background-color: #e8f5e9;"><?= htmlspecialchars($d['nombre']) ?></td>
                <td style="background-color: #e8f5e9;"><?= $d['cantidad'] ?></td>
                <td style="background-color: #e8f5e9;"><?= $d['napa'] > 0 ? '+'.$d['napa'].' ñapa' : ($d['bonificacion'] > 0 ? '+'.$d['bonificacion'].' bonif' : '0') ?></td>
            </tr>
            <?php 
                    endforeach;
                else: 
            ?>
            <tr>
                <td><?= $v['id_venta'] ?></td>
                <td><?= $v['fecha_hora'] ?></td>
                <td><?= ucfirst(str_replace('_', ' ', $v['tipo_salida'])) ?></td>
                <td><?= htmlspecialchars($v['categoria']) ?></td>
                <td><?= htmlspecialchars($v['cliente']) ?></td>
                <td><?= $v['unidades_vendidas'] ?></td>
                <td><?= $v['precio_unitario'] ?></td>
                <td><?= $v['total_venta'] ?></td>
                <td><?= $v['bonificacion'] ?></td>
                <td colspan="3" style="color:#777; font-style:italic;">(Sin detalle)</td>
            </tr>
            <?php 
                endif; 
            endforeach; 
            ?>
        </tbody>
        <tfoot>
            <tr>
                <th colspan="5" style="text-align: right; font-weight: bold;">TOTALES</th>
                <th style="font-weight: bold;"><?= $tot_und ?></th>
                <th></th>
                <th style="font-weight: bold;"><?= $tot_dinero ?></th>
                <th style="font-weight: bold;"><?= $tot_bonif ?></th>
                <th colspan="3"></th>
            </tr>
        </tfoot>
    </table>
</body>
</html>
