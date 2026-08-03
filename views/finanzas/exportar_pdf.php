<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reporte Financiero — <?= date('d/m/Y',strtotime($desde)) ?> al <?= date('d/m/Y',strtotime($hasta)) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,400;0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/finanzas_exportar_pdf.css">
</head>
<body>

<button class="btn-imprimir no-print" onclick="window.print()">
  🖨️ Guardar como PDF
</button>

<div class="pagina">

  <!-- ══ HEADER ══ -->
  <div class="pdf-header">
    <div class="hdr-inner">
      <div class="hdr-logo-area">
        <div class="hdr-logo-img">
          <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo Panadería">
        </div>
        <div class="hdr-text-block">
          <div class="hdr-nombre">BreadControl</div>
          <div class="hdr-sub">Sistema de gestión · Florencia, Caquetá</div>
        </div>
      </div>
      <div class="hdr-divider"></div>
      <div class="hdr-right">
        <div class="hdr-reporte-lbl">Documento</div>
        <div class="hdr-reporte">Reporte Financiero</div>
        <div class="hdr-periodo">
          📅 <?= date('d/m/Y',strtotime($desde)) ?> — <?= date('d/m/Y',strtotime($hasta)) ?>
        </div>
        <div class="hdr-gen">
          Generado el <?= date('d/m/Y H:i') ?> por <?= htmlspecialchars($user['nombre'] ?? '') ?>
        </div>
      </div>
    </div>
    <div class="hdr-stripe"></div>
  </div>

  <!-- ══ KPI BAND ══ -->
  <div class="kpi-band">
    <div class="kpi-card verde">
      <div class="kpi-lbl">Ingresos totales</div>
      <div class="kpi-val">$<?= number_format($ingresos,0,',','.') ?></div>
      <div class="kpi-sub">
        <span class="kpi-dot" style="background:var(--verde)"></span>
        <?= $num_ventas ?> ventas registradas
      </div>
    </div>
    <div class="kpi-card rojo">
      <div class="kpi-lbl">Compras de insumos</div>
      <div class="kpi-val">$<?= number_format($compras_total,0,',','.') ?></div>
      <div class="kpi-sub">
        <span class="kpi-dot" style="background:var(--rojo)"></span>
        <?= $num_compras ?> compras realizadas
      </div>
    </div>
    <div class="kpi-card naranja">
      <div class="kpi-lbl">Utilidad bruta</div>
      <div class="kpi-val"><?= $utilidad_bruta >= 0 ? '+' : '-' ?>$<?= number_format(abs($utilidad_bruta),0,',','.') ?></div>
      <div class="kpi-sub">
        <span class="kpi-dot" style="background:<?= $utilidad_bruta>=0?'var(--verde)':'var(--rojo)' ?>"></span>
        <?= $utilidad_bruta >= 0 ? 'Resultado positivo ✓' : 'Resultado negativo ✗' ?>
      </div>
    </div>
    <div class="kpi-card azul">
      <div class="kpi-lbl">Margen bruto</div>
      <div class="kpi-val"><?= $margen_bruto ?>%</div>
      <div class="kpi-sub">
        <span class="kpi-dot" style="background:<?= $margen_bruto>=30?'var(--verde)':($margen_bruto>=10?'#e65100':'var(--rojo)') ?>"></span>
        <?= $margen_bruto>=30?'Saludable ✓':($margen_bruto>=10?'Ajustado ⚠':'Bajo ✗') ?>
      </div>
    </div>
  </div>

  <!-- ══ CUERPO ══ -->
  <div class="pdf-body">

    <!-- Gráfico de ventas diarias -->
    <?php if (!empty($dias_chart) && count($dias_chart) > 1): ?>
    <div class="sec">
      <div class="sec-hdr">
        <div class="sec-icon si-naranja">📊</div>
        <span class="sec-titulo">Ventas diarias del período</span>
        <div class="sec-linea"></div>
      </div>
      <div class="grafico-wrap">
        <div class="grafico-linea-cero"></div>
        <div class="grafico-barras">
          <?php
          $hoy_str = date('Y-m-d');
          foreach ($dias_chart as $dc):
            $h = $chart_max>0 ? max(4, round(($dc['v']/$chart_max)*100)) : 4;
            $es_hoy = $dc['f'] === $hoy_str;
          ?>
          <div class="gb-col">
            <div class="gb-bar <?= $es_hoy?'hoy':'' ?>" style="height:<?=$h?>%"
                 title="<?= $dc['lbl'] ?>: $<?= number_format($dc['v'],0,',','.') ?>"></div>
            <?php if (count($dias_chart) <= 22): ?>
            <div class="gb-lbl" style="<?= $es_hoy?'font-weight:700;color:var(--c3)':'' ?>"><?= $dc['lbl'] ?></div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Top productos + Detalle compras en dos columnas -->
    <div class="dos-col">

      <!-- Top productos -->
      <div class="sec">
        <div class="sec-hdr">
          <div class="sec-icon si-naranja">🏆</div>
          <span class="sec-titulo">Productos más vendidos</span>
        </div>
        <?php if (empty($top_prod)): ?>
        <p style="color:var(--ink3);font-size:7.5pt;padding:.3cm 0">Sin ventas en este período.</p>
        <?php else:
          $max_p = max(array_column($top_prod,'t')?:[1]);
          $clases_rank = ['top1','top2','top3'];
          foreach ($top_prod as $i=>$pp):
            $pct = round(($pp['t']/$max_p)*100);
        ?>
        <div class="rank-row">
          <div class="rank-num <?= $clases_rank[$i] ?? '' ?>"><?= $i+1 ?></div>
          <div class="rank-nombre"><?= htmlspecialchars($pp['nombre'] ?? '') ?></div>
          <div class="rank-barra-w"><div class="rank-barra-f" style="width:<?=$pct?>%"></div></div>
          <div class="rank-und"><?= $pp['u'] ?> und</div>
          <div class="rank-val">$<?= number_format($pp['t'],0,',','.') ?></div>
        </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Detalle compras condensado -->
      <div class="sec">
        <div class="sec-hdr">
          <div class="sec-icon si-gris">🛒</div>
          <span class="sec-titulo">Detalle de compras</span>
        </div>
        <?php if (empty($detalle_compras)): ?>
        <p style="color:var(--ink3);font-size:7.5pt;padding:.3cm 0">Sin compras en este período.</p>
        <?php else: ?>
        <table class="pdf-table">
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Insumo</th>
              <th class="r">Total</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($detalle_compras as $c): ?>
            <tr>
              <td><?= date('d/m',strtotime($c['fecha_compra'])) ?></td>
              <td><?= htmlspecialchars(mb_substr($c['insumo'],0,20)) ?></td>
              <td class="r">$<?= number_format($c['total_pagado'],0,',','.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2">Total compras</td>
              <td class="r">$<?= number_format($compras_total,0,',','.') ?></td>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>

    <!-- Detalle de ventas completo -->
    <div class="sec">
      <div class="sec-hdr">
        <div class="sec-icon si-verde">📋</div>
        <span class="sec-titulo">Detalle de ventas</span>
        <div class="sec-linea"></div>
      </div>
      <?php if (empty($detalle_ventas)): ?>
      <p style="color:var(--ink3);font-size:7.5pt;padding:.3cm 0">Sin ventas en este período.</p>
      <?php else: ?>
      <table class="pdf-table">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Cliente</th>
            <th>Producto</th>
            <th class="c">Und.</th>
            <th class="r">Precio</th>
            <th class="r">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($detalle_ventas as $v):
            $tipo = $v['tipo'] ?? '';
            $bonif = (int)($v['bonificacion'] ?? 0);
          ?>
          <tr>
            <td><?= date('d/m/Y',strtotime($v['fecha'])) ?></td>
            <td style="font-size:7.5pt;color:var(--ink3)"><?= date('h:i a',strtotime($v['hora'])) ?></td>
            <td>
              <?php if ($tipo === 'tienda'): ?>
                <span class="tag tag-tienda">Tienda</span>
              <?php elseif ($tipo === 'mayorista'): ?>
                <span class="tag tag-mayor">Mayor.</span>
              <?php else: ?>
                <span class="tag tag-mostr">Mostr.</span>
              <?php endif; ?>
              <?= htmlspecialchars(mb_substr($v['cliente'],0,13)) ?>
            </td>
            <td><?= htmlspecialchars(mb_substr($v['producto'],0,18)) ?></td>
            <td class="c">
              <?= $v['unidades_vendidas'] ?>
              <?php if ($bonif > 0): ?>
              <div class="bonif">+<?= $bonif ?>🏪</div>
              <?php endif; ?>
            </td>
            <td class="r">$<?= number_format($v['precio_unitario'],0,',','.') ?></td>
            <td class="r">$<?= number_format($v['total_venta'],0,',','.') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr>
            <td colspan="6">Total ingresos del período</td>
            <td class="r">$<?= number_format($ingresos,0,',','.') ?></td>
          </tr>
        </tfoot>
      </table>
      <?php endif; ?>
    </div>

    <!-- Resumen financiero final -->
    <div class="resumen-final">
      <div class="rf-item">
        <div class="rf-lbl">Ingresos</div>
        <div class="rf-val pos">$<?= number_format($ingresos,0,',','.') ?></div>
        <div class="rf-sub"><?= $num_ventas ?> ventas · <?= count($dias_chart) ?> días</div>
      </div>
      <div class="rf-item">
        <div class="rf-lbl">Utilidad bruta</div>
        <div class="rf-val <?= $utilidad_bruta>=0?'pos':'neg' ?>">
          <?= $utilidad_bruta>=0?'+':'-' ?>$<?= number_format(abs($utilidad_bruta),0,',','.') ?>
        </div>
        <div class="rf-sub">Margen <?= $margen_bruto ?>%</div>
      </div>
      <div class="rf-item">
        <div class="rf-lbl">Utilidad neta</div>
        <div class="rf-val <?= $utilidad_neta>=0?'pos':'neg' ?>">
          <?= $utilidad_neta>=0?'+':'-' ?>$<?= number_format(abs($utilidad_neta),0,',','.') ?>
        </div>
        <div class="rf-sub">Incl. gastos operativos</div>
      </div>
    </div>

  </div><!-- /pdf-body -->

  <!-- ══ PIE DE PÁGINA ══ -->
  <div class="pdf-footer">
    <div class="footer-logo-area">
      <div class="footer-logo-img">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo">
      </div>
      <div>
        <div class="footer-marca">Sistema BreadControl</div>
        <div class="footer-ciudad">Florencia, Caquetá · Colombia</div>
      </div>
    </div>
    <div class="footer-info">
      Generado por <?= htmlspecialchars($user['nombre'] ?? '') ?> · <?= date('d/m/Y H:i') ?><br>
      Período: <?= date('d/m/Y',strtotime($desde)) ?> — <?= date('d/m/Y',strtotime($hasta)) ?>
      <div class="footer-page">Sistema de Gestión BreadControl</div>
    </div>
  </div>

</div><!-- /pagina -->

<script>
window.addEventListener('load', function() {
  setTimeout(function() { window.print(); }, 700);
});
</script>
</body>
</html>
