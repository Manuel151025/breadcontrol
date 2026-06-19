<link href="<?= APP_URL ?>/assets/css/inventario.css" rel="stylesheet">
<div class="page">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-arrow-left-right"></i> Ajuste de Inventario</div>
    <div class="top-actions">
      <a href="<?= APP_URL ?>/modules/inventario/index.php" class="btn-sec">
        <i class="bi bi-arrow-left"></i> Volver a inventario
      </a>
    </div>
  </div>

  <!-- CUERPO EN GRID -->
  <div class="g-body" style="grid-template-columns: 320px 1fr;">
    
    <!-- CARD: FORMULARIO DE AJUSTE -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-sliders"></i></div>
          <span class="ch-title">Registrar Ajuste</span>
        </div>
      </div>
      <div class="form-body">
        
        <div style="background: var(--clight); border: 1px solid var(--border); border-radius: 10px; padding: .8rem 1rem; margin-bottom: 1.1rem; font-size: .85rem;">
          <span style="color: var(--ink3); text-transform: uppercase; font-size: .62rem; font-weight: 700; display: block; margin-bottom: .2rem;">Insumo seleccionado</span>
          <strong style="font-size: 1rem; color: var(--ink);"><?= htmlspecialchars($insumo['nombre']) ?></strong>
          <div style="margin-top: .4rem; display: flex; justify-content: space-between; align-items: center;">
            <span style="color: var(--ink2);">Stock actual:</span>
            <strong style="color: var(--c3); font-size: 1.05rem; font-family: 'Fraunces', serif;"><?= formatoInteligente($insumo['stock_actual']) ?> <?= $insumo['unidad_medida'] ?></strong>
          </div>
        </div>

        <?php if (!empty($errores)): ?>
          <div class="msg-err" style="margin-bottom: 1rem;">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <span>
              <ul style="margin: 0; padding-left: 1.1rem; font-size: .78rem;">
                <?php foreach ($errores as $e): ?>
                  <li><?= $e ?></li>
                <?php endforeach; ?>
              </ul>
            </span>
          </div>
        <?php endif; ?>

        <form method="POST">
          <div class="fl">
            <label>Cantidad real contada (<?= $insumo['unidad_medida'] ?>) <span style="color:#c62828;">*</span></label>
            <input type="number" name="cantidad_real" id="cantidad_real"
                   value="<?= htmlspecialchars($_POST['cantidad_real'] ?? '', ENT_QUOTES) ?>"
                   min="0" step="0.001" placeholder="Ej: 5.200" required autofocus
                   style="font-size: 1.1rem; font-weight: 700;">
          </div>

          <!-- Mostrar diferencia en tiempo real -->
          <div class="fl" id="preview-diferencia" style="display:none; background: var(--clight); border: 1px dashed var(--border); border-radius: 10px; padding: .85rem 1rem; margin-bottom: .8rem;">
            <span style="font-size: .62rem; text-transform: uppercase; letter-spacing: .05em; color: var(--ink3); font-weight: 700; display: block; margin-bottom: .15rem;">Diferencia calculada</span>
            <strong id="texto-diferencia" style="font-size: 1.2rem; font-family: 'Fraunces', serif;"></strong>
          </div>

          <div class="fl">
            <label>Motivo del ajuste <span style="color:#c62828;">*</span></label>
            <select name="motivo" required>
              <option value="">Seleccionar...</option>
              <option value="Conteo físico — sobrante"  <?= (($_POST['motivo'] ?? '') === 'Conteo físico — sobrante')  ? 'selected' : '' ?>>Conteo físico — sobrante</option>
              <option value="Conteo físico — faltante"  <?= (($_POST['motivo'] ?? '') === 'Conteo físico — faltante')  ? 'selected' : '' ?>>Conteo físico — faltante</option>
              <option value="Producto dañado o vencido" <?= (($_POST['motivo'] ?? '') === 'Producto dañado o vencido') ? 'selected' : '' ?>>Producto dañado o vencido</option>
              <option value="Corrección de error de registro" <?= (($_POST['motivo'] ?? '') === 'Corrección de error de registro') ? 'selected' : '' ?>>Corrección de error de registro</option>
              <option value="Otro" <?= (($_POST['motivo'] ?? '') === 'Otro') ? 'selected' : '' ?>>Otro</option>
            </select>
          </div>

          <button type="submit" class="btn-guardar">
            <i class="bi bi-check-lg"></i> Confirmar ajuste
          </button>
        </form>
      </div>
    </div>

    <!-- CARD: HISTORIAL DE AJUSTES -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-clock-history"></i></div>
          <span class="ch-title">Historial de ajustes</span>
        </div>
        <span class="badge b-neu"><?= count($ajustes) ?> registros</span>
      </div>
      <div class="tbl-wrap">
        <?php if (empty($ajustes)): ?>
          <div class="empty">
            <i class="bi bi-clock-history" style="font-size: 2.2rem; color: var(--ink3); opacity: .35;"></i>
            <strong>Sin historial</strong>
            <span>No hay ajustes previos registrados para este insumo.</span>
          </div>
        <?php else: ?>
          <table class="gt">
            <thead>
              <tr>
                <th>Fecha</th>
                <th>Usuario</th>
                <th style="text-align: right;">Cant. Antes</th>
                <th style="text-align: right;">Cant. Después</th>
                <th style="text-align: right;">Diferencia</th>
                <th>Motivo</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ajustes as $aj): ?>
              <tr>
                <td><?= date('d/m/Y H:i', strtotime($aj['fecha_ajuste'])) ?></td>
                <td>
                  <strong style="color: var(--ink2);"><?= htmlspecialchars($aj['nombre_completo']) ?></strong>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: .85rem;">
                  <?= formatoInteligente($aj['cantidad_antes']) ?> <?= $insumo['unidad_medida'] ?>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: .85rem;">
                  <?= formatoInteligente($aj['cantidad_despues']) ?> <?= $insumo['unidad_medida'] ?>
                </td>
                <td style="text-align: right; font-family: monospace; font-size: .85rem; font-weight: 700; color: <?= $aj['diferencia'] >= 0 ? '#2e7d32' : '#c62828' ?>;">
                  <?= ($aj['diferencia'] >= 0 ? '+' : '') . formatoInteligente($aj['diferencia']) ?> <?= $insumo['unidad_medida'] ?>
                </td>
                <td style="font-size: .78rem; color: var(--ink3);">
                  <?= htmlspecialchars($aj['motivo']) ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
const stockActual = <?= (float)$insumo['stock_actual'] ?>;
const inputReal   = document.getElementById('cantidad_real');
const preview     = document.getElementById('preview-diferencia');
const textoDiv    = document.getElementById('texto-diferencia');

if (inputReal) {
  inputReal.addEventListener('input', () => {
    const val = parseFloat(inputReal.value);
    if (!isNaN(val)) {
      const dif = val - stockActual;
      textoDiv.textContent = (dif >= 0 ? '+' : '') + dif.toFixed(3) + ' <?= $insumo['unidad_medida'] ?>';
      textoDiv.style.color = dif >= 0 ? '#2e7d32' : '#c62828';
      preview.style.display = 'block';
    } else {
      preview.style.display = 'none';
    }
  });
}
</script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
