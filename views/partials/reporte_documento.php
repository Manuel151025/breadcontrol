<?php
// views/partials/reporte_documento.php
//
// Armazón común de todos los reportes imprimibles. Antes cada exportación
// repetía su propio <head>, su CSS y su barra de botones, y con el tiempo
// divergieron: paletas distintas, cabeceras distintas y ninguna con la
// identidad del producto. Aquí viven la cabecera, el pie y la barra de
// acciones; el estilo está en assets/css/reporte.css.
//
// Uso:
//   reporte_documento_inicio([
//       'titulo'    => 'Reporte de Panes por Aprendiz',
//       'subtitulo' => $nombre_tienda,
//       'meta'      => ['Fecha de entrega' => $fecha, 'Pedido' => '#0024'],
//       'horizontal'=> false,   // opcional, para tablas anchas
//   ]);
//   ... contenido ...
//   reporte_documento_fin('Texto opcional del pie');

if (!function_exists('reporte_documento_inicio')) {

    /**
     * @param array{titulo:string, subtitulo?:string, meta?:array<string,string>, horizontal?:bool} $opts
     */
    function reporte_documento_inicio(array $opts): void {
        $titulo     = $opts['titulo'];
        $subtitulo  = $opts['subtitulo'] ?? '';
        $meta       = $opts['meta'] ?? [];
        $horizontal = !empty($opts['horizontal']);
        ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($titulo) ?> — BreadControl</title>
<link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/img/favicon-32.png">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/reporte.css?v=<?= APP_VERSION ?>">
<?php if ($horizontal): ?>
<style>@page { size: A4 landscape; } body { max-width: 297mm; }</style>
<?php endif; ?>
</head>
<body>

<div class="rp-acciones">
    <div class="rp-aviso">
        Al guardar como PDF, desactiva <b>«Encabezados y pies de página»</b> en
        las opciones de impresión: el documento ya trae los suyos.
    </div>
    <button class="rp-btn rp-btn-primario" onclick="window.print()">Guardar PDF</button>
    <button class="rp-btn rp-btn-secundario" onclick="window.close()">Cerrar</button>
</div>

<header class="rp-cabecera">
    <div class="rp-marca">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="">
        <div class="rp-marca-texto">
            <div class="rp-marca-nombre">BreadControl</div>
            <div class="rp-marca-sub">Panadería</div>
        </div>
    </div>
    <div style="flex:1;">
        <div class="rp-titulo"><?= htmlspecialchars($titulo) ?></div>
        <?php if ($subtitulo !== ''): ?>
        <div class="rp-subtitulo"><?= htmlspecialchars($subtitulo) ?></div>
        <?php endif; ?>
    </div>
    <?php if ($meta): ?>
    <div class="rp-meta">
        <?php foreach ($meta as $clave => $valor): ?>
        <div><b><?= htmlspecialchars((string) $clave) ?></b><span><?= htmlspecialchars((string) $valor) ?></span></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</header>

<main class="rp-contenido">
        <?php
    }

    /**
     * Cierra el documento. El pie se repite en todas las páginas al imprimir.
     */
    function reporte_documento_fin(string $nota = ''): void {
        ?>
</main>

<footer class="rp-pie">
    <span>BreadControl<?= $nota !== '' ? ' · ' . htmlspecialchars($nota) : '' ?></span>
    <span>Generado el <?= date('d/m/Y \a \l\a\s H:i') ?></span>
</footer>

</body>
</html>
        <?php
    }
}
