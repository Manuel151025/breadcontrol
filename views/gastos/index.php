<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/gastos.css?v=<?= APP_VERSION ?>">

<div class="page">

  <!-- ══ BANNER ══ -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Gastos <em>Operativos</em></div>
        <div class="wc-sub"><?= date('l, d \d\e F \d\e Y', strtotime($fecha_fil)) ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_dia > 0 ? 'alert' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($total_dia / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Gastos día</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num">$<?= number_format($gastos_mes / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Mes actual</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $num_gastos_mes ?></div>
        <div class="wc-pill-lbl">Registros</div>
      </div>
      <div class="wc-pill <?= $utilidad_neta < 0 ? 'alert' : 'ok' ?>">
        <div class="wc-pill-num"><?= $utilidad_neta >= 0 ? '+' : '-' ?>$<?= number_format(abs($utilidad_neta) / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Util. neta</div>
      </div>
    </div>
  </div>

  <!-- ══ TOPBAR ══ -->
  <div class="topbar">
    <div class="mod-titulo">
      <i class="bi bi-receipt-cutoff"></i> Gastos
    </div>
    <div class="top-actions">
      <span class="fil-lbl"><i class="bi bi-calendar3"></i></span>
      <input type="date" class="fil-date" value="<?= $fecha_fil ?>"
             onchange="location.href='?fecha='+this.value">
      <?php if ($fecha_fil !== $hoy): ?>
      <a href="index.php" class="btn-hoy"><i class="bi bi-arrow-counterclockwise"></i> Hoy</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ CUERPO ══ -->
  <div class="g-body">

    <!-- ── CARD IZQUIERDA: formulario + gráfico + resumen ── -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-plus-circle-fill"></i></div>
          <span class="ch-title">Registrar gasto</span>
        </div>
      </div>

      <div class="form-body">
        <?php if ($msg_ok): ?>
        <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div>
        <?php endif; ?>

        <form method="POST">
          <?= campo_csrf() ?>
          <div class="fl">
            <label>Categoría</label>
            <div class="cat-grid">
              <button type="button" class="cat-btn compra"   onclick="selCat('compra')"   id="cat-compra">  🛒<span>Compras</span></button>
              <button type="button" class="cat-btn servicio" onclick="selCat('servicio')" id="cat-servicio">💡<span>Servicios</span></button>
              <button type="button" class="cat-btn otro"     onclick="selCat('otro')"     id="cat-otro">    📝<span>Otros</span></button>
            </div>
            <input type="hidden" name="categoria" id="inp-cat" value="">
          </div>
          <div class="fl">
            <label>Descripción</label>
            <input type="text" name="descripcion" placeholder="Ej: Pago energía, Arriendo…" required>
          </div>
          <div class="fl">
            <label>Valor ($)</label>
            <input type="number" name="valor" placeholder="Ej: 85000" min="1" step="1" required>
          </div>
          <button type="submit" name="guardar_gasto" class="btn-guardar">
            <i class="bi bi-floppy-fill"></i> Guardar gasto
          </button>
        </form>
      </div>

      <!-- Mini gráfico 7 días -->
      <div class="graf-zona">
        <div class="graf-titulo"><i class="bi bi-bar-chart-fill"></i>Gastos últimos 7 días</div>
        <div class="grafico-mini">
          <?php foreach ($gastos_7d as $gd):
            $h = $chart_max_7d > 0 ? max(3, round(($gd['v'] / $chart_max_7d) * 48)) : 3;
            $es_hoy = $gd['lbl'] === date('d/m');
          ?>
          <div class="gm-col">
            <div class="gm-bar <?= $es_hoy ? 'hoy' : '' ?>" style="height:<?= $h ?>px"
                 data-tip="<?= $gd['lbl'] ?>: $<?= number_format($gd['v'],0,',','.') ?>"></div>
            <span class="gm-lbl"><?= $gd['lbl'] ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Resumen por categoría del día -->
      <?php if (!empty($por_cat)): ?>
      <div class="cat-sum">
        <div class="cat-sum-title">Distribución del día</div>
        <?php
        $max_cat  = max($por_cat ?: [1]);
        $cat_clrs = ['compra' => '#1565c0', 'servicio' => '#e65100', 'otro' => '#2e7d32'];
        foreach ($cat_labels as $k => $c):
          if (!isset($por_cat[$k])) continue;
          $pct = round(($por_cat[$k] / $max_cat) * 100);
        ?>
        <div class="cat-row">
          <div class="cat-dot" style="background:<?= $cat_clrs[$k] ?>"></div>
          <span class="cat-name"><?= $c[0] ?> <?= $c[1] ?></span>
          <div class="cat-bar-w"><div class="cat-bar-f" style="width:<?= $pct ?>%;background:<?= $cat_clrs[$k] ?>"></div></div>
          <span class="cat-val">$<?= number_format($por_cat[$k], 0, ',', '.') ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

    </div><!-- /card izquierda -->

    <!-- ── CARD DERECHA: tabla ── -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-red"><i class="bi bi-list-ul"></i></div>
          <span class="ch-title">Gastos del <?= date('d/m/Y', strtotime($fecha_fil)) ?></span>
        </div>
        <span class="badge b-neu"><?= count($gastos_dia) ?> registro<?= count($gastos_dia) != 1 ? 's' : '' ?></span>
      </div>

      <?php if (empty($gastos_dia)): ?>
      <div class="empty">
        <i class="bi bi-receipt"></i>
        <strong>Sin gastos este día</strong>
        <span>Usa el formulario para registrar uno</span>
      </div>
      <?php else: ?>

      <div class="tbl-wrap">
        <table class="gt">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Categoría</th>
              <th>Descripción</th>
              <th>Usuario</th>
              <th style="text-align:right">Valor</th>
              <th style="text-align:center">—</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $cat_cc = [
              'compra'   => ['#1565c0', 'rgba(21,101,192,.1)'],
              'servicio' => ['#e65100', 'rgba(230,81,0,.1)'],
              'otro'     => ['#2e7d32', 'rgba(46,125,50,.1)'],
            ];
            foreach ($gastos_dia as $g):
              $cc = $cat_cc[$g['categoria']] ?? ['#666','rgba(0,0,0,.08)'];
              $cl = $cat_labels[$g['categoria']] ?? ['📝','Otro'];
            ?>
            <tr>
              <td style="color:var(--ink3);font-size:.75rem;white-space:nowrap">
                <?= date('H:i', strtotime($g['fecha_gasto'])) ?>
              </td>
              <td>
                <span class="cat-tag" style="color:<?= $cc[0] ?>;background:<?= $cc[1] ?>">
                  <?= $cl[0] ?> <?= $cl[1] ?>
                </span>
              </td>
              <td style="font-weight:600;max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                  title="<?= htmlspecialchars($g['descripcion'] ?? '') ?>">
                <?= htmlspecialchars($g['descripcion'] ?? '') ?>
              </td>
              <td style="font-size:.75rem;color:var(--ink3)">
                <?= htmlspecialchars($g['usuario'] ?? '—') ?>
              </td>
              <td style="text-align:right;font-weight:700;color:#c62828;font-family:'Fraunces',serif">
                $<?= number_format($g['valor'], 0, ',', '.') ?>
              </td>
              <td style="text-align:center">
                <div style="display:flex;gap:.25rem;">
                <?php if ($fecha_fil === $hoy): ?>
                <button type="button" class="btn-act btn-edit" title="Editar"
                  onclick="abrirEditGasto(<?= $g['id_gasto'] ?>, '<?= $g['categoria'] ?>', '<?= htmlspecialchars(addslashes($g['descripcion']),ENT_QUOTES) ?>', <?= (int)$g['valor'] ?>)">
                  <i class="bi bi-pencil"></i>
                </button>
                <form method="POST" style="display:contents;" onsubmit="return confirm('¿Eliminar este gasto?')">
                  <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                  <input type="hidden" name="del" value="<?= $g['id_gasto'] ?>">
                  <input type="hidden" name="fecha" value="<?= $fecha_fil ?>">
                  <button type="submit" class="btn-act btn-del" title="Eliminar">
                    <i class="bi bi-trash3"></i>
                  </button>
                </form>
                <?php else: ?>
                <span style="font-size:.7rem;color:var(--ink3)">—</span>
                <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="4" class="tot-lbl">Total gastos del día</td>
              <td class="tot-val">$<?= number_format($total_dia, 0, ',', '.') ?></td>
              <td></td>
            </tr>
          </tfoot>
        </table>
      </div>

      <!-- Caja utilidad neta del día -->
      <div class="util-box <?= $utilidad_neta >= 0 ? 'pos' : 'neg' ?>">
        <i class="bi bi-<?= $utilidad_neta >= 0 ? 'graph-up-arrow' : 'graph-down-arrow' ?>"></i>
        <div>
          <div style="font-size:.7rem;opacity:.72;font-weight:600">
            Ingresos $<?= number_format($ingresos_dia, 0, ',', '.') ?> —
            Compras $<?= number_format($compras_dia, 0, ',', '.') ?> —
            Gastos $<?= number_format($total_dia, 0, ',', '.') ?>
          </div>
          <div>Utilidad neta del día</div>
        </div>
        <div class="util-num">
          <?= $utilidad_neta >= 0 ? '+' : '-' ?>$<?= number_format(abs($utilidad_neta), 0, ',', '.') ?>
        </div>
      </div>

      <?php endif; ?>
    </div><!-- /card derecha -->

  </div><!-- /g-body -->
</div><!-- /page -->

<script>
function selCat(k) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('sel'));
  document.getElementById('cat-' + k).classList.add('sel');
  document.getElementById('inp-cat').value = k;
}
</script>


<!-- Modal editar gasto -->
<div id="modal-edit-gasto" style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:14px;padding:1.4rem;width:90%;max-width:400px;box-shadow:0 12px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <strong style="font-size:.9rem;"><i class="bi bi-pencil-square" style="color:var(--c3);"></i> Editar gasto</strong>
      <button onclick="cerrarEditGasto()" style="background:none;border:none;font-size:1.2rem;cursor:pointer;color:var(--ink3);">&times;</button>
    </div>
    <form method="POST">
      <?= campo_csrf() ?>
      <input type="hidden" name="id_gasto" id="eg-id">
      <div style="margin-bottom:.7rem;">
        <label style="font-size:.75rem;font-weight:700;color:var(--ink2);display:block;margin-bottom:.3rem;">Categoría</label>
        <select name="cat_edit" id="eg-cat" style="width:100%;padding:.45rem;border:1px solid var(--border);border-radius:8px;font-size:.82rem;">
          <option value="compra">🛒 Compras</option>
          <option value="servicio">💡 Servicios</option>
          <option value="otro">📝 Otros</option>
        </select>
      </div>
      <div style="margin-bottom:.7rem;">
        <label style="font-size:.75rem;font-weight:700;color:var(--ink2);display:block;margin-bottom:.3rem;">Descripción</label>
        <input type="text" name="desc_edit" id="eg-desc" required style="width:100%;padding:.45rem;border:1px solid var(--border);border-radius:8px;font-size:.82rem;box-sizing:border-box;">
      </div>
      <div style="margin-bottom:.9rem;">
        <label style="font-size:.75rem;font-weight:700;color:var(--ink2);display:block;margin-bottom:.3rem;">Valor ($)</label>
        <input type="number" name="val_edit" id="eg-val" min="1" step="1" required style="width:100%;padding:.45rem;border:1px solid var(--border);border-radius:8px;font-size:.82rem;box-sizing:border-box;">
      </div>
      <button type="submit" name="editar_gasto" style="width:100%;padding:.55rem;background:var(--c3);color:#fff;border:none;border-radius:9px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;">
        <i class="bi bi-check-lg"></i> Guardar cambios
      </button>
    </form>
  </div>
</div>

<script>
function abrirEditGasto(id, cat, desc, val){
  document.getElementById('eg-id').value = id;
  document.getElementById('eg-cat').value = cat;
  document.getElementById('eg-desc').value = desc;
  document.getElementById('eg-val').value = val;
  var m = document.getElementById('modal-edit-gasto');
  m.style.display = 'flex';
}
function cerrarEditGasto(){
  document.getElementById('modal-edit-gasto').style.display = 'none';
}
document.getElementById('modal-edit-gasto').addEventListener('click', function(e){
  if(e.target === this) cerrarEditGasto();
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
