<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/produccion.css">


<div class="page">


        <?php if (!empty($obs_cierre['sugerencia_produccion'])): ?>
        <div class="obs-banner" id="obs-cierre-banner">
          <div class="obs-ico"><i class="bi bi-chat-left-text-fill"></i></div>
          <div class="obs-body">
            <div class="obs-label">Nota del último cierre</div>
            <div class="obs-text"><?= htmlspecialchars($obs_cierre['sugerencia_produccion']) ?></div>
            <div class="obs-date">Cierre del <?= date('d/m/Y', strtotime($obs_cierre['fecha'])) ?></div>
          </div>
          <button class="obs-close" onclick="this.parentElement.style.display='none'" title="Cerrar">&times;</button>
        </div>
        <?php endif; ?>

  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreakControl</div>
        <div class="wc-name">Nueva <em>Producción</em></div>
        <div class="wc-sub">Consumo FIFO de lotes automático · <?= date('d/m/Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_hoy > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num"><?= $total_hoy ?></div>
        <div class="wc-pill-lbl">Unidades hoy</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= count($prod_hoy) ?></div>
        <div class="wc-pill-lbl">Registros</div>
      </div>
      <div class="wc-pill <?= $costo_hoy > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($costo_hoy/1000,1) ?>k</div>
        <div class="wc-pill-lbl">Costo hoy</div>
      </div>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-fire"></i> Nueva producción</div>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <div class="g-body">

    <!-- FORMULARIO -->
    <div class="card">
      <div class="ch">
        <div class="ch-left"><div class="ch-ico ico-nar"><i class="bi bi-pencil-fill"></i></div><span class="ch-title">Registrar producción</span></div>
      </div>
      <div class="form-body">

        <?php if ($msg_ok): ?>
        <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div class="<?= !empty($msg_err_class) ? $msg_err_class : 'msg-err' ?>"><i class="bi bi-<?= !empty($msg_err_class) ? 'info-circle-fill' : 'exclamation-circle-fill' ?>"></i> <?= $msg_err ?></div>
        <?php endif; ?>

        <form method="post" id="form-prod">

          <div class="fl">
            <label>Producto</label>
            <select name="id_producto" id="sel-prod" required onchange="cargarLotes()">
              <option value="">— Seleccionar producto —</option>
              <?php foreach ($productos as $p): ?>
              <option value="<?= $p['id_producto'] ?>"
                data-tanda="<?= (int)$p['cantidad_por_tanda'] ?>"
                <?= (isset($_POST['id_producto']) && $_POST['id_producto']==$p['id_producto']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nombre']) ?>
                (<?= (int)$p['cantidad_por_tanda'] ?> und/tanda)
                <?= !$p['tiene_receta'] ? ' ⚠ sin receta' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="fl">
            <label>N° de tandas a producir</label>
            <div class="und-ctrl">
              <button type="button" class="und-btn" onclick="changeUnd(-1)">−</button>
              <input type="number" name="num_tandas" id="inp-und" class="und-inp"
                     min="1" max="5" value="<?= (int)($_POST['num_tandas'] ?? 1) ?>"
                     required oninput="cargarLotes()">
              <button type="button" class="und-btn" onclick="changeUnd(1)">+</button>
            </div>
            <!-- Preview total unidades -->
            <div id="preview-unidades" style="margin-top:.35rem;font-size:.78rem;color:var(--ink3);display:none;">
              = <span id="preview-unidades-val" style="font-family:'Fraunces',serif;font-weight:800;color:var(--c3);font-size:.92rem;"></span>
              <span id="preview-unidades-lbl"> panes disponibles para venta</span>
            </div>
          </div>



          <!-- DISTRIBUCIÓN POR PRECIO -->
          <div id="panel-distribucion" class="fl" style="display:none;">
            <label><i class="bi bi-grid-3x3-gap" style="color:var(--c3)"></i> ¿Cuántos de cada precio?</label>
            <div style="background:var(--clight);border:1px solid var(--border);border-radius:10px;padding:.7rem .85rem;">
              <div style="font-size:.7rem;color:var(--ink3);margin-bottom:.5rem;">
                Se esperan <strong id="dist-total-label">0</strong> unidades. Escribe cuántas salieron realmente de cada precio:
              </div>
              <?php foreach ($categorias_precio as $cp): ?>
              <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
                <span style="font-size:.8rem;font-weight:700;color:var(--ink);min-width:65px;">$<?= number_format($cp['precio_unitario'],0,',','.') ?></span>
                <input type="number" name="dist[<?= $cp['id_categoria'] ?>]" 
                       class="dist-input" data-cat="<?= $cp['id_categoria'] ?>"
                       min="0" step="1" value="0" oninput="checkDistTotal()"
                       style="width:80px;border:1px solid var(--border);border-radius:8px;padding:.35rem .5rem;font-size:.88rem;font-family:'Fraunces',serif;font-weight:700;text-align:center;background:#fff;">
                <span style="font-size:.7rem;color:var(--ink3);">unidades</span>
              </div>
              <?php endforeach; ?>
              <div id="dist-status" style="margin-top:.5rem;padding:.4rem .6rem;border-radius:8px;font-size:.75rem;font-weight:700;text-align:center;"></div>
            </div>
          </div>

          <!-- PANEL DE LOTES FIFO -->
          <div id="panel-lotes" class="fl" style="display:none;">
            <label><i class="bi bi-boxes" style="color:var(--c3)"></i> Guía de lotes a usar (más antiguos primero)</label>
            <div class="lotes-panel">
              <div class="lotes-hdr">
                <span class="lotes-hdr-ttl"><i class="bi bi-sort-up"></i> Orden FIFO — saca estos lotes del estante</span>
                <span id="badge-lotes" class="badge b-neu"></span>
              </div>
              <div id="lotes-contenido">
                <div class="lotes-loading"><i class="bi bi-arrow-repeat" style="animation:spin .8s linear infinite;display:inline-block"></i> Cargando…</div>
              </div>
            </div>
          </div>

          <div class="fl">
            <label>Fecha</label>
            <input type="date" name="fecha_produccion"
                   min="<?= date('Y-m-d', strtotime('-7 days')) ?>"
                   max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['fecha_produccion'] ?? date('Y-m-d'), ENT_QUOTES) ?? date('Y-m-d') ?>">
          </div>

          <div class="fl">
            <label>Observaciones <span style="font-weight:400;text-transform:none;font-size:.7rem;">(opcional)</span></label>
            <textarea name="observaciones" placeholder="Ej: levadura reducida, horneado doble…"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
          </div>

          <button type="submit" name="guardar" class="btn-guardar" id="btn-guardar">
            <i class="bi bi-check-lg"></i> Registrar y descontar lotes
          </button>

        </form>
      </div>
    </div>

    <!-- TABLA DE HOY -->
    <div class="card">
      <div class="ch">
        <div class="ch-left"><div class="ch-ico ico-grn"><i class="bi bi-clock-history"></i></div><span class="ch-title">Producción de hoy</span></div>
        <span class="badge b-grn"><?= $total_hoy ?> unidades · $<?= number_format($costo_hoy,0,',','.') ?></span>
      </div>
      <?php if (empty($prod_hoy)): ?>
      <div class="empty"><i class="bi bi-basket"></i><strong>Sin registros aún</strong><span>Los registros de hoy aparecen aquí</span></div>
      <?php else: ?>
      <div class="tbl-wrap">
        <table class="gt">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Producto</th>
              <th style="text-align:center">Unids.</th>
              <th style="text-align:right">Costo total</th>
              <th style="text-align:right">C/unidad</th>
              <th>Observ.</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($prod_hoy as $pr): ?>
          <tr>
            <td style="color:var(--ink3);font-size:.75rem"><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($pr['producto']) ?></td>
            <td style="text-align:center;font-weight:800;font-family:'Fraunces',serif;font-size:1.05rem;color:var(--c3)"><?= $pr['unidades_producidas'] ?></td>
            <td style="text-align:right;color:#1b5e20;font-weight:600;font-size:.8rem">$<?= number_format($pr['costo_total'],0,',','.') ?></td>
            <td style="text-align:right;color:var(--ink3);font-size:.75rem">$<?= number_format($pr['costo_unitario'],0,',','.') ?></td>
            <td style="color:var(--ink3);font-size:.76rem"><?= htmlspecialchars($pr['observaciones'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="text-align:right;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:var(--ink2)">Total hoy</td>
              <td style="text-align:center;font-family:'Fraunces',serif;color:#2e7d32"><?= $total_hoy ?></td>
              <td style="text-align:right;color:#1b5e20">$<?= number_format($costo_hoy,0,',','.') ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>var appUrl = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/produccion.js"></script>

</body></html>
