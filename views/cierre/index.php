<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/cierre.css?v=<?= APP_VERSION ?>">

<div class="page">

  <!-- ══ BANNER ══ -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Cierre <em>del Día</em></div>
        <div class="wc-sub"><?= date('l, d \d\e F \d\e Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_ventas > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($total_ventas / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Ventas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $total_tandas ?></div>
        <div class="wc-pill-lbl">Tandas</div>
      </div>
      <div class="wc-pill <?= $num_alertas > 0 ? 'alert' : 'ok' ?>">
        <div class="wc-pill-num"><?= $num_alertas ?></div>
        <div class="wc-pill-lbl">Alertas</div>
      </div>
      <div class="wc-pill <?= $utilidad_neta >= 0 ? 'ok' : 'alert' ?>">
        <div class="wc-pill-num"><?= $utilidad_neta >= 0 ? '+' : '-' ?>$<?= number_format(abs($utilidad_neta) / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Util. neta</div>
      </div>
      <?php if ($cierre_guardado): ?>
      <div class="wc-pill ok">
        <div class="wc-pill-num">✓</div>
        <div class="wc-pill-lbl">Cerrado</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ TOPBAR + MENSAJES ══ -->
  <div>
    <div class="topbar">
      <div class="mod-titulo">
        <i class="bi bi-moon-stars-fill"></i> Cierre del día
      </div>
      <span class="mod-fecha">📅 <?= date('d/m/Y') ?></span>
    </div>
    <?php if ($msg_ok): ?>
    <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><?= $msg_ok ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><?= $msg_err ?></div>
    <?php endif; ?>
  </div>

  <!-- ══ ZONA CENTRAL ══ -->
  <div class="zona-central">

    <!-- KPIs -->
    <div class="kpi-row">
      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-currency-dollar" style="color:#2e7d32"></i>Ventas del día</div>
        <div class="kpi-val grn">$<?= number_format($total_ventas, 0, ',', '.') ?></div>
        <div style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;margin-top:.2rem">
          <span class="badge b-neu"><?= $num_ventas ?> venta<?= $num_ventas != 1 ? 's' : '' ?></span>
          <?php if ($diff_ventas !== null): ?>
          <span class="badge <?= $diff_ventas >= 0 ? 'b-ok' : 'b-bad' ?>">
            <i class="bi bi-arrow-<?= $diff_ventas >= 0 ? 'up' : 'down' ?>"></i>
            <?= $diff_ventas >= 0 ? '+' : '' ?><?= $diff_ventas ?>% vs ayer
          </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-fire" style="color:var(--c3)"></i>Producción</div>
        <div class="kpi-val nar"><?= $total_tandas ?></div>
        <div class="kpi-sub"><?= count($producciones) ?> lote<?= count($producciones) != 1 ? 's' : '' ?> · tandas producidas</div>
      </div>

      <div class="kpi">
        <?php // La cifra es el COSTO de lo producido hoy mas los gastos, no lo que se
              // pago a proveedores: es la base con la que se calcula la utilidad neta.
              // El rotulo decia "Compras + Gastos" y el subtitulo enumeraba compras, asi
              // que un dia sin comprar mostraba "0 compras - gastos $0" junto a una cifra
              // de seis digitos. Las compras del dia se listan mas abajo. ?>
        <div class="kpi-lbl"><i class="bi bi-cash-stack" style="color:#c62828"></i>Costo + Gastos</div>
        <div class="kpi-val red">$<?= number_format($costo_produccion_hoy + $total_gastos, 0, ',', '.') ?></div>
        <div class="kpi-sub">producción $<?= number_format($costo_produccion_hoy, 0, ',', '.') ?> · gastos $<?= number_format($total_gastos, 0, ',', '.') ?> · <?= $num_compras ?> compra<?= $num_compras != 1 ? 's' : '' ?></div>
      </div>

      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-calculator" style="color:#1565c0"></i>Utilidad neta</div>
        <div class="kpi-val <?= $utilidad_neta >= 0 ? 'grn' : 'red' ?>">$<?= number_format(abs($utilidad_neta), 0, ',', '.') ?></div>
        <span class="badge <?= $utilidad_neta >= 0 ? 'b-ok' : 'b-bad' ?>" style="margin-top:.2rem;align-self:flex-start">
          <?= $utilidad_neta >= 0 ? '✓ Positiva' : '✗ Negativa' ?>
        </span>
      </div>
    </div>

    <!-- Cards detalle -->
    <div class="c-body">

      <!-- Ventas por producto -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-nar"><i class="bi bi-bag-fill"></i></span>
            <span class="ch-title">Ventas / producto</span>
          </div>
          <span class="ch-val">$<?= number_format($total_ventas, 0, ',', '.') ?></span>
        </div>
        <?php if (empty($ventas_prod)): ?>
        <div class="c-empty"><i class="bi bi-bag-x"></i>Sin ventas hoy</div>
        <?php else: ?>
        <div class="d-scroll">
          <?php $max_vp = max(array_column($ventas_prod, 't') ?: [1]);
          foreach ($ventas_prod as $vp):
            $pct = round(($vp['t'] / $max_vp) * 100); ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($vp['nombre'] ?? '') ?></span>
            <div class="barra-w"><div class="barra-f" style="width:<?= $pct ?>%"></div></div>
            <span class="dato-det"><?= $vp['u'] ?> und</span>
            <span class="dato-val">$<?= number_format($vp['t'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Ventas por cliente -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-caf"><i class="bi bi-people-fill"></i></span>
            <span class="ch-title">Ventas / cliente</span>
          </div>
          <span class="ch-val"><?= count($ventas_cli) ?> cliente<?= count($ventas_cli) != 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($ventas_cli)): ?>
        <div class="c-empty"><i class="bi bi-people"></i>Sin ventas hoy</div>
        <?php else: ?>
        <div class="d-scroll">
          <?php $max_vc = max(array_column($ventas_cli, 't') ?: [1]);
          foreach ($ventas_cli as $vc):
            $pct = round(($vc['t'] / $max_vc) * 100); ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= $vc['tipo'] === 'tienda' ? '🏪' : '🧑' ?> <?= htmlspecialchars($vc['cliente'] ?? '') ?></span>
            <div class="barra-w"><div class="barra-f" style="width:<?= $pct ?>%"></div></div>
            <span class="dato-det"><?= $vc['n'] ?> venta<?= $vc['n'] != 1 ? 's' : '' ?></span>
            <span class="dato-val">$<?= number_format($vc['t'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Producción + compras + alertas -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-grn"><i class="bi bi-fire"></i></span>
            <span class="ch-title">Operaciones</span>
          </div>
          <?php if ($num_alertas > 0): ?>
          <span class="badge b-bad"><i class="bi bi-exclamation-triangle-fill"></i><?= $num_alertas ?> alerta<?= $num_alertas != 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>
        <div class="d-scroll">

          <!-- Producción -->
          <?php if (!empty($producciones)): ?>
          <div class="sub-sep"><i class="bi bi-fire" style="color:var(--c3)"></i>Producción — <?= $total_tandas ?> tandas</div>
          <?php foreach ($producciones as $pr): ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($pr['nombre'] ?? '') ?></span>
            <span class="dato-det"><?= formatoInteligente($pr['cantidad_tandas']) ?> <?= $pr['unidad_produccion'] ?></span>
            <span class="dato-det"><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></span>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div class="c-empty" style="padding:1rem"><i class="bi bi-inbox"></i>Sin producción hoy</div>
          <?php endif; ?>

          <!-- Compras -->
          <?php if (!empty($compras_hoy)): ?>
          <div class="sub-sep"><i class="bi bi-cart-fill" style="color:#c62828"></i>Compras</div>
          <?php foreach ($compras_hoy as $ch): ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($ch['insumo'] ?? '') ?></span>
            <span class="dato-det"><?= formatoInteligente($ch['cantidad']) ?> <?= $ch['unidad_medida'] ?></span>
            <span class="dato-val" style="color:#c62828">$<?= number_format($ch['total_pagado'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; endif; ?>

          <!-- Alertas stock -->
          <?php if (!empty($alertas)): ?>
          <div class="sub-sep" style="color:#c62828"><i class="bi bi-exclamation-triangle-fill"></i>Stock bajo</div>
          <?php foreach ($alertas as $a):
            $pct = $a['punto_reposicion'] > 0 ? min(100, round(($a['stock_actual'] / $a['punto_reposicion']) * 100)) : 0; ?>
          <div class="dato-row">
            <span class="dato-nombre">⚠️ <?= htmlspecialchars($a['nombre'] ?? '') ?></span>
            <div class="barra-w"><div class="barra-alert" style="width:<?= $pct ?>%"></div></div>
            <span style="font-size:.7rem;font-weight:700;color:#c62828"><?= formatoInteligente($a['stock_actual']) ?> <?= $a['unidad_medida'] ?></span>
          </div>
          <?php endforeach; endif; ?>

        </div>
      </div>

    </div><!-- /c-body -->
  </div><!-- /zona-central -->

  <!-- ══ ZONA INFERIOR: 3 cards alineados con c-body ══ -->
  <div class="cierre-bottom">

    <!-- Card 1: Cierre del día -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico" style="background:rgba(103,58,183,.1);color:#673ab7"><i class="bi bi-moon-stars-fill"></i></span>
          <span class="ch-title">Cierre del día</span>
        </div>
      </div>
      <div class="cierre-form">
        <?php if ($cierre_guardado): ?>
        <div class="cierre-ya">
          <i class="bi bi-check-circle-fill"></i>
          <div>
            Cierre guardado
            <?php if (!empty($cierre_guardado['sugerencia_produccion'])): ?>
            <div style="font-size:.65rem;font-weight:400;opacity:.75;margin-top:.1rem">
              "<?= htmlspecialchars($cierre_guardado['sugerencia_produccion'] ?? '') ?>"
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <form method="POST" style="display:flex;flex-direction:column;gap:.5rem;width:100%;">
          <?= campo_csrf() ?>
          <div class="obs-wrap">
            <div class="obs-label">Sugerencia producción mañana</div>
            <textarea name="sugerencia_produccion" class="obs-textarea"
              placeholder="Ej: Aumentar pan de sal..."><?= htmlspecialchars($cierre_guardado['sugerencia_produccion'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="confirmar_cierre" class="btn-cerrar">
            <i class="bi bi-moon-stars-fill"></i>
            <?= $cierre_guardado ? 'Actualizar cierre' : 'Confirmar cierre' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Card 2: Historial reciente -->
    <?php if (!empty($historial)): ?>
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-blu"><i class="bi bi-clock-history"></i></span>
          <span class="ch-title">Historial reciente</span>
        </div>
        <span class="badge b-neu"><?= count($historial) ?> días</span>
      </div>
      <div class="d-scroll">
        <table class="ht">
          <thead>
            <tr><th>Fecha</th><th>Ingresos</th><th>U. bruta</th><th>U. neta</th></tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $h): ?>
            <tr>
              <td style="font-weight:600;white-space:nowrap">
                <?= date('d/m', strtotime($h['fecha'])) ?>
                <?php if ($h['fecha'] === $hoy): ?>
                <span class="badge b-ok" style="margin-left:.2rem">hoy</span>
                <?php endif; ?>
              </td>
              <td class="grn">$<?= number_format($h['total_ingresos'], 0, ',', '.') ?></td>
              <td class="<?= $h['utilidad_bruta'] >= 0 ? 'grn' : 'red' ?>">
                <?= $h['utilidad_bruta'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($h['utilidad_bruta']), 0, ',', '.') ?>
              </td>
              <td class="<?= $h['utilidad_neta'] >= 0 ? 'grn' : 'red' ?>">
                <?= $h['utilidad_neta'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($h['utilidad_neta']), 0, ',', '.') ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-blu"><i class="bi bi-clock-history"></i></span>
          <span class="ch-title">Historial reciente</span>
        </div>
      </div>
      <div class="c-empty"><i class="bi bi-clock-history"></i>Sin cierres previos</div>
    </div>
    <?php endif; ?>

    <!-- Card 3: Sin vender hoy -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-red"><i class="bi bi-box-seam"></i></span>
          <span class="ch-title">Sin vender hoy</span>
        </div>
        <span class="badge <?= $total_sobrante <= 0 ? 'b-ok' : 'b-bad' ?>">
          <?= $total_sobrante <= 0 ? '✓ Todo vendido' : $total_sobrante . ' und' ?>
        </span>
      </div>
      <?php if (empty($sobrantes) || $total_sobrante <= 0): ?>
      <div class="c-empty"><i class="bi bi-emoji-smile"></i>¡Todo vendido hoy!</div>
      <?php else: ?>
      <div class="sob-lista">
        <?php foreach ($sobrantes as $s): if ($s['sobrante'] <= 0) continue; ?>
        <div class="sob-fila">
          <span class="sob-nombre"><?= htmlspecialchars($s['nombre'] ?? '') ?></span>
          <span class="sob-det"><?= $s['vendidas'] ?>/<span style="color:var(--ink2)"><?= $s['producidas'] ?></span></span>
          <span class="badge b-bad" style="font-size:.58rem"><?= $s['sobrante'] ?> und</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($ventas_sin_producto > 0): ?>
      <div class="sub-sep"><i class="bi bi-info-circle-fill"></i>No incluido arriba</div>
      <div class="dato-row">
        <span class="dato-nombre">Vendido por categoría de precio sin producto específico</span>
        <span class="dato-val" style="color:var(--ink2)"><?= $ventas_sin_producto ?> und</span>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /cierre-bottom -->
</div><!-- /page -->

<?php include __DIR__ . '/../layouts/footer.php'; ?>
