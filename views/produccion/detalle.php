<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/produccion.css">


<div class="page">
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Detalle de <em>Producción</em></div>
        <div class="wc-sub"><?= date('l, d \d\e F \d\e Y', strtotime($produccion['fecha_produccion'])) ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill ok">
        <div class="wc-pill-num"><?= formatoInteligente($produccion['cantidad_tandas']) ?></div>
        <div class="wc-pill-lbl">Tandas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $num_insumos ?></div>
        <div class="wc-pill-lbl">Insumos</div>
      </div>
      <div class="wc-pill ok">
        <div class="wc-pill-num">$<?= number_format($costo_total / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Costo</div>
      </div>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-clipboard-data-fill"></i> Detalle #<?= $id_produccion ?></div>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <div class="g-body">
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-info-circle-fill"></i></div>
          <span class="ch-title">Resumen</span>
        </div>
      </div>
      <div class="resumen-body">
        <div class="dato-item">
          <div class="dato-lbl">Producto</div>
          <div class="dato-val"><?= htmlspecialchars($produccion['producto']) ?></div>
          <div style="font-size:.72rem;color:var(--ink3);"><?= $produccion['unidad_produccion'] ?></div>
        </div>
        <div class="dato-item">
          <div class="dato-lbl">Tandas producidas</div>
          <div class="dato-val grande"><?= formatoInteligente($produccion['cantidad_tandas']) ?></div>
        </div>
        <div class="dato-item">
          <div class="dato-lbl">Fecha y hora</div>
          <div class="dato-val"><?= date('d/m/Y', strtotime($produccion['fecha_produccion'])) ?></div>
          <div style="font-size:.72rem;color:var(--ink3);"><?= date('H:i', strtotime($produccion['fecha_produccion'])) ?></div>
        </div>
        <div class="dato-item">
          <div class="dato-lbl">Registrado por</div>
          <div class="dato-val"><?= htmlspecialchars($produccion['usuario'] ?? '—') ?></div>
        </div>
        <div class="sep"></div>
        <div class="dato-item">
          <div class="dato-lbl">Costo total de insumos</div>
          <div class="dato-val verde">$<?= number_format($costo_total, 0, ',', '.') ?></div>
        </div>
        <div class="dato-item">
          <div class="dato-lbl">Insumos utilizados</div>
          <div class="dato-val"><?= $num_insumos ?> ingrediente<?= $num_insumos != 1 ? 's' : '' ?></div>
        </div>
        <?php if (!empty($produccion['observaciones'])): ?>
        <div class="sep"></div>
        <div class="dato-item">
          <div class="dato-lbl">Observaciones</div>
          <div class="obs-box"><?= htmlspecialchars($produccion['observaciones']) ?></div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-fire"><i class="bi bi-layers-fill"></i></div>
          <span class="ch-title">Ingredientes descontados (FIFO)</span>
        </div>
        <span class="badge b-neu"><?= count($consumos) ?> consumo<?= count($consumos) != 1 ? 's' : '' ?></span>
      </div>
      <?php if (empty($consumos)): ?>
      <div class="empty">
        <i class="bi bi-inbox"></i>
        <strong>Sin detalle de consumos</strong>
        <span>Esta producción fue registrada sin descuento de inventario</span>
      </div>
      <?php else: ?>
      <div class="tbl-wrap">
        <table class="gt">
          <thead>
            <tr>
              <th>Ingrediente</th>
              <th>Lote usado</th>
              <th>Ingreso del lote</th>
              <th style="text-align:right">Cantidad</th>
              <th style="text-align:right">Con merma</th>
              <th style="text-align:right">Costo</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($consumos as $c): ?>
          <tr>
            <td><strong><?= htmlspecialchars($c['insumo']) ?></strong></td>
            <td><span class="lote-code"><?= htmlspecialchars($c['numero_lote']) ?></span></td>
            <td style="color:var(--ink3);font-size:.76rem;"><?= date('d/m/Y', strtotime($c['fecha_ingreso'])) ?></td>
            <td style="text-align:right;">
              <?= formatoInteligente($c['cantidad_consumida']) ?>
              <span style="font-size:.72rem;color:var(--ink3)"><?= $c['unidad_medida'] ?></span>
            </td>
            <td style="text-align:right;">
              <?= formatoInteligente($c['cantidad_con_merma']) ?>
              <span style="font-size:.72rem;color:var(--ink3)"><?= $c['unidad_medida'] ?></span>
              <?php if (round($c['cantidad_con_merma'],4) != round($c['cantidad_consumida'],4)): ?>
              <span class="merma-tag">+merma</span>
              <?php endif; ?>
            </td>
            <td style="text-align:right;font-weight:700;color:var(--c3);">
              $<?= number_format($c['costo_consumo'], 0, ',', '.') ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="5" style="font-size:.75rem;color:var(--ink2);text-align:right;text-transform:uppercase;letter-spacing:.08em;">Costo total</td>
              <td style="font-family:'Fraunces',serif;font-size:1.05rem;color:#2e7d32;text-align:right;">$<?= number_format($costo_total, 0, ',', '.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
