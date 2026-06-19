<link href="<?= APP_URL ?>/assets/css/inventario.css" rel="stylesheet">

<div class="page">
  <!-- BANNER -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Inventario <em>de Insumos</em></div>
        <div class="wc-sub">Control de stock en tiempo real</div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $total_insumos ?></div>
        <div class="wc-pill-lbl">Insumos</div>
      </div>
      <div class="wc-pill <?= $alertas_count > 0 ? 'alert' : 'ok' ?>">
        <div class="wc-pill-num"><?= $alertas_count ?></div>
        <div class="wc-pill-lbl">Alertas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $lotes_activos ?></div>
        <div class="wc-pill-lbl">Lotes</div>
      </div>
      <div class="wc-pill ok">
        <div class="wc-pill-num">$<?= number_format($valor_inventario/1000,1) ?>k</div>
        <div class="wc-pill-lbl">Valor total</div>
      </div>
    </div>
  </div>

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-box-seam-fill"></i> Inventario</div>
    <div class="top-actions">
      <form method="get" style="display:flex;gap:.4rem;align-items:center;">
        <input type="text" name="q" class="inp-search" placeholder="Buscar insumo…" value="<?= htmlspecialchars($busca) ?>">
        <button type="submit" class="btn-sec"><i class="bi bi-search"></i></button>
      </form>
      <button type="button" class="btn-sec <?= $filtro_alerta ? 'active' : '' ?>" onclick="toggleAlerta()">
        <i class="bi bi-exclamation-triangle<?= $filtro_alerta ? '-fill' : '' ?>"></i> Alertas
      </button>
      <script>
      function toggleAlerta(){
        var url = 'index.php';
        var busca = '<?= addslashes($busca) ?>';
        var activo = <?= $filtro_alerta ? 'true' : 'false' ?>;
        if (activo) {
          if (busca) url += '?q=' + encodeURIComponent(busca);
        } else {
          url += '?alerta=1';
          if (busca) url += '&q=' + encodeURIComponent(busca);
        }
        window.location.href = url;
      }
      </script>
      <?php if ($editando): ?>
        <a href="index.php" class="btn-sec"><i class="bi bi-plus-lg"></i> Nuevo</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- CUERPO -->
  <div class="g-body">
    <!-- FORMULARIO -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-<?= $editando ? 'pencil-fill' : 'plus-lg' ?>"></i></div>
          <span class="ch-title"><?= $editando ? 'Editar insumo' : 'Nuevo insumo' ?></span>
        </div>
      </div>
      <div class="form-body">
        <?php if ($msg_ok): ?><div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div><?php endif; ?>
        <?php if ($msg_err): ?><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div><?php endif; ?>
        <?php if ($editando): ?>
        <div class="edit-banner"><i class="bi bi-pencil-square"></i> Editando: <strong><?= htmlspecialchars($editando['nombre']) ?></strong></div>
        <?php endif; ?>
        <form method="post">
          <?php if ($editando): ?><input type="hidden" name="id_insumo" value="<?= $editando['id_insumo'] ?>"><?php endif; ?>
          <div class="fl">
            <label>Nombre del insumo</label>
            <input type="text" name="nombre" placeholder="Ej: Harina de trigo" required value="<?= htmlspecialchars($editando['nombre'] ?? '') ?>" pattern="[A-Za-záéíóúÁÉÍÓÚñÑ\s]+" title="Solo se permiten letras y espacios" oninput="this.value = this.value.replace(/[0-9]/g, '')">
          </div>
          <div class="fl-row">
            <div class="fl">
              <label>Unidad de medida</label>
              <select name="unidad_medida">
                <?php foreach(['kg','g','L','ml','unidad'] as $u): ?>
                <option value="<?= $u ?>" <?= ($editando['unidad_medida'] ?? 'kg') === $u ? 'selected' : '' ?>><?= $u ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="fl">
              <label>Stock actual</label>
              <input type="number" name="stock_actual" min="0" step="0.001" placeholder="0" value="<?= $editando ? rtrim(rtrim(number_format((float)$editando['stock_actual'], 3, '.', ''), '0'), '.') : '' ?>">
            </div>
          </div>
          <div class="fl">
            <label>Punto de reposición</label>
            <input type="number" name="punto_reposicion" min="0" step="0.001" placeholder="Stock mínimo para alertar" value="<?= $editando ? rtrim(rtrim(number_format((float)$editando['punto_reposicion'], 3, '.', ''), '0'), '.') : '' ?>">
          </div>
          <label class="check-row">
            <input type="checkbox" name="es_harina" value="1" <?= ($editando['es_harina'] ?? 0) ? 'checked' : '' ?>>
            <span>Es harina (aplica merma del 6%)</span>
          </label>
          <button type="submit" name="guardar_insumo" class="btn-guardar">
            <i class="bi bi-<?= $editando ? 'check-lg' : 'plus-lg' ?>"></i>
            <?= $editando ? 'Guardar cambios' : 'Registrar insumo' ?>
          </button>
          <?php if ($editando): ?>
          <a href="index.php" class="btn-cancel">Cancelar</a>
          <?php endif; ?>
        </form>
      </div>
    </div>

    <!-- TABLA -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-table"></i></div>
          <span class="ch-title">Insumos registrados</span>
        </div>
        <span class="badge b-neu"><?= count($insumos) ?> resultados</span>
      </div>
      <?php if ($filtro_alerta): ?>
      <div class="filter-banner">
        <i class="bi bi-funnel-fill"></i> Mostrando solo insumos con stock bajo
        <a href="index.php<?= $busca ? '?q='.urlencode($busca) : '' ?>" style="margin-left:auto;font-size:.7rem;color:var(--c3);text-decoration:underline;">Ver todos</a>
      </div>
      <?php endif; ?>
      <div class="tbl-wrap">
        <?php if (empty($insumos)): ?>
        <div class="empty">
          <i class="bi bi-box-seam"></i>
          <strong><?= $filtro_alerta ? 'Sin alertas de stock' : 'Sin insumos' ?></strong>
          <span><?= $filtro_alerta ? 'Todos los insumos tienen stock suficiente' : 'Registra el primer insumo usando el formulario' ?></span>
        </div>
        <?php else: ?>
        <form method="post">
        <table class="gt">
          <thead>
            <tr>
              <th style="width:30px;"><input type="checkbox" id="sel-all" title="Seleccionar todos"></th>
              <th>Insumo</th>
              <th>Unidad</th>
              <th>Stock actual</th>
              <th>Nivel</th>
              <th>Reposición</th>
              <th>Precio/u</th>
              <th>Valor</th>
              <th>Lotes</th>
              <th>Estado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($insumos as $ins):
            $pct      = $ins['punto_reposicion'] > 0 ? min(100, round($ins['stock_actual'] / $ins['punto_reposicion'] * 50)) : 100;
            $alerta   = $ins['stock_actual'] <= $ins['punto_reposicion'];
            $barClass = $pct >= 80 ? 'bar-ok' : ($pct >= 40 ? 'bar-warn' : 'bar-crit');
            $valor_ins = $ins['stock_actual'] * $ins['precio_ultimo'];
          ?>
          <tr class="<?= $alerta ? 'alerta-row' : '' ?>">
            <td><input type="checkbox" name="ids_eliminar[]" value="<?= $ins['id_insumo'] ?>" class="chk-ins"></td>
            <td>
              <strong><?= htmlspecialchars($ins['nombre']) ?></strong>
              <?php if ($ins['es_harina']): ?><span class="tag-harina">🌾 harina</span><?php endif; ?>
            </td>
            <td><?= $ins['unidad_medida'] ?></td>
            <td><span style="font-family:'Fraunces',serif;font-weight:700;"><?= formatoInteligente($ins['stock_actual']) ?></span> <span style="font-size:.72rem;color:var(--ink3)"><?= $ins['unidad_medida'] ?></span></td>
            <td>
              <div class="stock-bar-w"><div class="stock-bar-f <?= $barClass ?>" style="width:<?= $pct ?>%"></div></div>
            </td>
            <td><?= formatoInteligente($ins['punto_reposicion']) ?> <span style="font-size:.72rem;color:var(--ink3)"><?= $ins['unidad_medida'] ?></span></td>
            <td><?= $ins['precio_ultimo'] > 0 ? '$'.formatoInteligente($ins['precio_ultimo']) : '<span style="color:var(--ink3);font-size:.75rem;">—</span>' ?></td>
            <td><?= $valor_ins > 0 ? '$'.number_format($valor_ins,0,',','.') : '—' ?></td>
            <td style="text-align:center;"><?= $ins['num_lotes'] ?></td>
            <td>
              <?= $alerta
                ? '<span class="tag-alerta"><i class="bi bi-exclamation-circle"></i> Bajo</span>'
                : '<span class="tag-ok"><i class="bi bi-check-circle"></i> OK</span>' ?>
            </td>
            <td style="white-space:nowrap;">
              <div style="display:flex;gap:.3rem;">
                <a href="ajuste.php?id=<?= $ins['id_insumo'] ?>" class="btn-act" style="border-color:rgba(198,113,36,.25);color:var(--c3);" title="Ajuste manual"><i class="bi bi-arrow-left-right"></i></a>
                <a href="index.php?edit=<?= $ins['id_insumo'] ?>" class="btn-act btn-edit" title="Editar"><i class="bi bi-pencil"></i></a>
                <a href="index.php?del=<?= $ins['id_insumo'] ?>" class="btn-act btn-del" title="Eliminar" onclick="return confirm('¿Eliminar este insumo?')"><i class="bi bi-trash3-fill"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
      <div id="batch-bar" style="display:none;padding:.75rem 1.1rem;background:rgba(198,57,40,.06);border-top:1px solid rgba(198,57,40,.15);align-items:center;justify-content:space-between;gap:.6rem;">
        <span style="font-size:.82rem;font-weight:700;color:#c62828;" id="batch-count">0 seleccionados</span>
        <button type="submit" name="eliminar_seleccionados" class="btn-del" style="width:auto;height:auto;padding:.45rem .9rem;font-size:.8rem;font-weight:700;border-radius:8px;gap:.4rem;display:inline-flex;align-items:center;cursor:pointer;background:transparent;transition:background .2s;border:1px solid rgba(198,40,40,.3);"
          onclick="return confirm('¿Estás seguro de eliminar los insumos seleccionados?')">
          <i class="bi bi-trash3-fill"></i> Eliminar seleccionados
        </button>
      </div>
      </form>
    </div>
  </div>
</div>

<script>
// Select all / batch delete
var selAll = document.getElementById('sel-all');
if (selAll) {
  selAll.addEventListener('change', function(){
    document.querySelectorAll('.chk-ins').forEach(function(c){ c.checked = selAll.checked; });
    updateBatch();
  });
  document.querySelectorAll('.chk-ins').forEach(function(c){
    c.addEventListener('change', updateBatch);
  });
}
function updateBatch(){
  var checked = document.querySelectorAll('.chk-ins:checked').length;
  var bar = document.getElementById('batch-bar');
  if (bar) {
    bar.style.display = checked > 0 ? 'flex' : 'none';
    document.getElementById('batch-count').textContent = checked + ' seleccionado' + (checked !== 1 ? 's' : '');
  }
}
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
