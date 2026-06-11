<div class="row justify-content-center">
  <div class="col-md-6">

    <div class="d-flex align-items-center mb-4">
      <a href="<?= APP_URL ?>/modules/inventario/index.php" class="btn btn-outline-secondary btn-sm me-3">
        <i class="bi bi-arrow-left"></i> Volver
      </a>
      <h4 class="mb-0"><i class="bi bi-pencil"></i> Editar insumo</h4>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><ul><?php foreach ($errores as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul></span></div>
    <?php endif; ?>

    <div class="card">
      <div class="card-body">
        <form method="POST">

          <div class="mb-3">
            <label class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
            <input type="text" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($insumo['nombre']) ?>" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Unidad de medida <span class="text-danger">*</span></label>
            <select name="unidad_medida" class="form-select" required>
              <?php foreach (['kg','g','L','ml','unidad'] as $u): ?>
              <option value="<?= $u ?>" <?= $insumo['unidad_medida'] === $u ? 'selected' : '' ?>><?= $u ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Punto de reposición</label>
            <input type="number" name="punto_reposicion" class="form-control input-cantidad"
                   value="<?= $insumo['punto_reposicion'] ?>" min="0" step="0.001">
          </div>

          <div class="mb-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="es_harina" id="es_harina"
                     <?= $insumo['es_harina'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="es_harina">
                Es <strong>harina</strong> (aplica merma del 6%)
              </label>
            </div>
          </div>

          <div class="mb-4">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="activo" id="activo"
                     <?= $insumo['activo'] ? 'checked' : '' ?>>
              <label class="form-check-label" for="activo">Insumo activo</label>
            </div>
          </div>

          <div class="d-grid">
            <button type="submit" class="btn btn-dark">
              <i class="bi bi-check-lg"></i> Guardar cambios
            </button>
          </div>

        </form>
      </div>
    </div>

  </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
