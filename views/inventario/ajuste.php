<div class="row">
  <div class="col-md-5">

    <div class="d-flex align-items-center mb-4">
      <a href="<?= APP_URL ?>/modules/inventario/index.php" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
      <h4 class="mb-0"><i class="bi bi-arrow-left-right"></i> Ajuste manual</h4>
    </div>

    <div class="card mb-3">
      <div class="card-body bg-light">
        <strong><?= htmlspecialchars($insumo['nombre']) ?></strong><br>
        <span class="text-muted">Stock actual: </span>
        <strong><?= formatoInteligente($insumo['stock_actual']) ?> <?= $insumo['unidad_medida'] ?></strong>
      </div>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><ul><?php foreach ($errores as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></span></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST">

          <div class="mb-3">
            <label class="form-label fw-semibold">
              Cantidad real contada (<?= $insumo['unidad_medida'] ?>) <span class="text-danger">*</span>
            </label>
            <input type="number" name="cantidad_real" id="cantidad_real"
                   class="form-control form-control-lg input-cantidad"
                   value="<?= htmlspecialchars($_POST['cantidad_real'] ?? '', ENT_QUOTES) ?? '' ?>"
                   min="0" step="0.001" required autofocus>
          </div>

          <!-- Mostrar diferencia en tiempo real -->
          <div class="mb-3 p-3 rounded bg-light" id="preview-diferencia" style="display:none">
            <small class="text-muted">Diferencia:</small>
            <strong id="texto-diferencia" class="fs-5 ms-2"></strong>
          </div>

          <div class="mb-4">
            <label class="form-label fw-semibold">Motivo del ajuste <span class="text-danger">*</span></label>
            <select name="motivo" class="form-select" required>
              <option value="">Seleccionar...</option>
              <option value="Conteo físico — sobrante"  <?= (($_POST['motivo'] ?? '') === 'Conteo físico — sobrante')  ? 'selected' : '' ?>>Conteo físico — sobrante</option>
              <option value="Conteo físico — faltante"  <?= (($_POST['motivo'] ?? '') === 'Conteo físico — faltante')  ? 'selected' : '' ?>>Conteo físico — faltante</option>
              <option value="Producto dañado o vencido" <?= (($_POST['motivo'] ?? '') === 'Producto dañado o vencido') ? 'selected' : '' ?>>Producto dañado o vencido</option>
              <option value="Corrección de error de registro" <?= (($_POST['motivo'] ?? '') === 'Corrección de error de registro') ? 'selected' : '' ?>>Corrección de error de registro</option>
              <option value="Otro">Otro</option>
            </select>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-warning fw-bold">
              <i class="bi bi-check-lg"></i> Confirmar ajuste
            </button>
          </div>

        </form>
      </div>
    </div>
  </div>

  <!-- Historial -->
  <div class="col-md-7">
    <h5 class="mb-3">Historial de ajustes</h5>
    <?php if (empty($ajustes)): ?>
    <p class="text-muted">No hay ajustes previos para este insumo.</p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table tabla-panaderia table-sm">
        <thead>
          <tr>
            <th>Fecha</th>
            <th>Usuario</th>
            <th class="text-end">Antes</th>
            <th class="text-end">Después</th>
            <th class="text-end">Diferencia</th>
            <th>Motivo</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($ajustes as $aj): ?>
          <tr>
            <td><?= date('d/m/Y H:i', strtotime($aj['fecha_ajuste'])) ?></td>
            <td><?= htmlspecialchars($aj['nombre_completo']) ?></td>
            <td class="text-end"><?= formatoInteligente($aj['cantidad_antes']) ?></td>
            <td class="text-end"><?= formatoInteligente($aj['cantidad_despues']) ?></td>
            <td class="text-end <?= $aj['diferencia'] >= 0 ? 'text-success' : 'text-danger' ?>">
              <?= ($aj['diferencia'] >= 0 ? '+' : '') . formatoInteligente($aj['diferencia']) ?>
            </td>
            <td><small><?= htmlspecialchars($aj['motivo']) ?></small></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<script>
const stockActual = <?= $insumo['stock_actual'] ?>;
const inputReal   = document.getElementById('cantidad_real');
const preview     = document.getElementById('preview-diferencia');
const textoDiv    = document.getElementById('texto-diferencia');

inputReal.addEventListener('input', () => {
  const val = parseFloat(inputReal.value);
  if (!isNaN(val)) {
    const dif = val - stockActual;
    textoDiv.textContent = (dif >= 0 ? '+' : '') + dif.toFixed(3) + ' <?= $insumo['unidad_medida'] ?>';
    textoDiv.style.color = dif >= 0 ? '#198754' : '#dc3545';
    preview.style.display = 'block';
  } else {
    preview.style.display = 'none';
  }
});
</script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
