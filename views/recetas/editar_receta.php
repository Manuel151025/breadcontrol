<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/recetas_editar.css">

<div class="page">

  <!-- BANNER -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Editar <em>Receta</em></div>
        <div class="wc-sub"><?= htmlspecialchars($producto['nombre'] ?? '') ?> · <?= $producto['unidad_produccion'] ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= !empty($ingredientes) ? 'ok' : '' ?>">
        <div class="wc-pill-num"><?= count($ingredientes) ?></div>
        <div class="wc-pill-lbl">Ingredientes</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num">$<?= number_format($producto['precio_venta'],0,',','.') ?></div>
        <div class="wc-pill-lbl">Precio venta</div>
      </div>
    </div>
  </div>

  <!-- TOPBAR -->
  <div class="topbar">
    <div style="display:flex;align-items:center;gap:.7rem;flex-wrap:wrap;">
      <div class="mod-titulo"><i class="bi bi-journal-text"></i> <?= htmlspecialchars($producto['nombre'] ?? '') ?></div>
      <?php if ($producto['cantidad_por_tanda'] > 0): ?>
      <span class="mod-sub">Rinde <?= $producto['cantidad_por_tanda'] ?> unidades por tanda</span>
      <?php endif; ?>
    </div>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <!-- CARD -->
  <div class="card">
    <div class="ch">
      <div class="ch-left">
        <div class="ch-ico ico-pur"><i class="bi bi-list-check"></i></div>
        <span class="ch-title">Ingredientes de la receta</span>
      </div>
      <span class="badge b-neu" id="badge-count"><?= count($ingredientes) ?> ingredientes</span>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="msg-err-list">
      <strong><i class="bi bi-exclamation-triangle-fill"></i> Errores:</strong>
      <ul style="margin:.3rem 0 0 1.2rem;padding:0"><?php foreach ($errores as $e): ?><li><?= $e ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="post" id="form-receta">
      <?= campo_csrf() ?>
      <div class="tbl-wrap">
        <table class="gt">
          <thead>
            <tr>
              <th style="width:36%">Ingrediente</th>
              <th style="width:17%">Cantidad</th>
              <th style="width:7%">Unidad</th>
              <th style="width:8%;text-align:center">Merma</th>
              <th>Nota</th>
              <th style="width:36px"></th>
            </tr>
          </thead>
          <tbody id="tbody-ing">
          <?php
          $ids_usados = array_column($ingredientes, 'id_insumo');
          if (empty($ingredientes)):
              echo filaVacia($todos_insumos, [], 0);
          else:
              foreach ($ingredientes as $idx => $ing):
                  $label = in_array($ing['unidad_medida'],['kg','L']) ? 'g'
                         : ($ing['unidad_medida']==='unidad' ? 'unid.' : $ing['unidad_medida']);
          ?>
          <tr class="fila-ing" data-id="<?= $ing['id_insumo'] ?>">
            <td>
              <!-- Hidden input para el POST -->
              <input type="hidden" name="id_insumo[]" value="<?= $ing['id_insumo'] ?>" class="hid-id">
              <!-- Botón visual picker -->
              <button type="button" class="ing-picker-btn seleccionado"
                      data-id="<?= $ing['id_insumo'] ?>"
                      onclick="abrirModal(this)">
                <i class="bi bi-bag-fill picker-ico" style="color:var(--c3)"></i>
                <span class="picker-nombre"><?= htmlspecialchars($ing['nombre_insumo'] ?? '') ?></span>
                <?php if ($ing['es_harina']): ?>
                <span class="picker-tag tag-harina">🌾 harina</span>
                <?php endif; ?>
                <span class="picker-tag tag-unidad"><?= $label ?></span>
                <i class="bi bi-chevron-down" style="font-size:.65rem;opacity:.4;margin-left:auto"></i>
              </button>
            </td>
            <td><input type="number" name="cantidad[]" class="inp-cant" min="0.001" step="0.001" value="<?= rtrim(rtrim(number_format((float)$ing['cant_mostrar'],4,'.',''),'0'),'.') ?>""></td>
            <td><span class="lbl-unidad"><?= $label ?></span></td>
            <td style="text-align:center"><input type="checkbox" name="aplica_merma[<?= $idx ?>]" class="chk-merma" <?= $ing['aplica_merma']?'checked':'' ?>></td>
            <td><input type="text" name="notas[]" class="inp-nota" placeholder="Notas…" value="<?= htmlspecialchars($ing['notas'] ?? '') ?>"></td>
            <td><button type="button" class="btn-del-row" onclick="eliminarFila(this)"><i class="bi bi-trash3"></i></button></td>
          </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>

      <div class="card-foot">
        <button type="button" class="btn-agregar" onclick="agregarFila()">
          <i class="bi bi-plus-lg"></i> Agregar ingrediente
        </button>
        <button type="submit" class="btn-guardar">
          <i class="bi bi-check-lg"></i> Guardar receta
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ══ MODAL PICKER ══ -->
<div class="modal-backdrop" id="modal-picker" style="display:none" onclick="cerrarModalFuera(event)">
  <div class="modal-box">
    <div class="modal-hdr">
      <div class="modal-titulo"><i class="bi bi-bag-heart-fill"></i> Seleccionar ingrediente</div>
      <button type="button" class="modal-cerrar" onclick="cerrarModal()"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-search-wrap">
      <input type="text" class="modal-search" id="modal-buscar"
             placeholder="Buscar ingrediente…"
             oninput="filtrarCards(this.value)"
             autocomplete="off">
    </div>
    <div class="modal-grid-wrap">
      <div class="modal-grid" id="modal-grid"></div>
      <div class="modal-empty" id="modal-empty" style="display:none">
        <i class="bi bi-search"></i>
        No hay ingredientes que coincidan
      </div>
    </div>
  </div>
</div>

<?php
function filaVacia($insumos, $usados = [], $idx = 0) {
    ob_start(); ?>
    <tr class="fila-ing" data-id="">
      <td>
        <input type="hidden" name="id_insumo[]" value="" class="hid-id">
        <button type="button" class="ing-picker-btn vacio" onclick="abrirModal(this)">
          <i class="bi bi-plus-circle picker-ico"></i>
          <span class="picker-nombre" style="color:var(--ink3)">— Seleccionar ingrediente —</span>
          <i class="bi bi-chevron-down" style="font-size:.65rem;opacity:.35;margin-left:auto"></i>
        </button>
      </td>
      <td><input type="number" name="cantidad[]" class="inp-cant" min="0.001" step="0.001" placeholder="0"></td>
      <td><span class="lbl-unidad">g</span></td>
      <td style="text-align:center"><input type="checkbox" name="aplica_merma[]" class="chk-merma"></td>
      <td><input type="text" name="notas[]" class="inp-nota" placeholder="Notas…"></td>
      <td><button type="button" class="btn-del-row" onclick="eliminarFila(this)"><i class="bi bi-trash3"></i></button></td>
    </tr>
    <?php return ob_get_clean();
}
?>

<script>
// Configuración generada por PHP para recetas_editar.js
const insumosData = <?= json_encode(array_map(fn($i) => [
    'id'     => $i['id_insumo'],
    'nombre' => $i['nombre'],
    'unidad' => $i['unidad_medida'],
    'harina' => (bool)$i['es_harina'],
], $todos_insumos)) ?>;
let contadorFilas = <?= count($ingredientes) ?>;
</script>
<script src="<?= APP_URL ?>/assets/js/recetas_editar.js"></script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
