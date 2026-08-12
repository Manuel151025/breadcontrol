<?php
// views/ventas/exportar_excel.php
//
// Exportación de ventas a hoja de cálculo.
//
// Antes esta plantilla emitía una tabla HTML con celdas combinadas (`rowspan`)
// para agrupar los detalles de cada venta. Se veía ordenada al abrirla, pero
// dejaba la hoja inservible: con celdas combinadas Excel no filtra, no ordena
// y no permite tablas dinámicas. Ahora se emite una tabla plana —los datos de
// la venta se repiten en cada línea de detalle— que es como una hoja de
// cálculo espera recibir los datos.

require_once __DIR__ . '/../../helpers/ExportadorCsv.php';

$filas = [];

foreach ($ventas as $v) {
    $detalles = $detalles_por_venta[$v['id_venta']] ?? [];

    $comunes = [
        (int) $v['id_venta'],
        $v['fecha_hora'],
        ucfirst(str_replace('_', ' ', (string) $v['tipo_salida'])),
        $v['categoria'] ?? '',
        $v['cliente'] ?? 'Mostrador',
        (int) $v['unidades_vendidas'],
        (float) $v['precio_unitario'],
        (float) $v['total_venta'],
        (int) $v['bonificacion'],
    ];

    if ($detalles === []) {
        $filas[] = array_merge($comunes, ['', 0, 0, 0]);
        continue;
    }

    foreach ($detalles as $d) {
        $filas[] = array_merge($comunes, [
            $d['nombre'] ?? '',
            (int) $d['cantidad'],
            (int) $d['napa'],
            (int) $d['bonificacion'],
        ]);
    }
}

ExportadorCsv::enviar(
    'ventas',
    [
        'ID venta',
        'Fecha y hora',
        'Tipo de salida',
        'Categoría de precio',
        'Cliente',
        'Unidades vendidas',
        'Precio unitario',
        'Total venta',
        'Bonificación (venta)',
        'Producto del detalle',
        'Cantidad del detalle',
        'Ñapa del detalle',
        'Bonificación del detalle',
    ],
    $filas
);
