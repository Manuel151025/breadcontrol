<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/ventas.css?v=<?= APP_VERSION ?>">

<div class="page">
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Módulo de <em>Ventas</em></div>
        <div class="wc-sub">Registro de salidas del día · <?= date('d/m/Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_ventas > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($total_ventas / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Ventas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $und_ventas ?></div>
        <div class="wc-pill-lbl">Und.</div>
      </div>
      <?php if ($diff_pct !== null): ?>
        <div class="wc-pill <?= $diff_pct >= 0 ? 'ok' : '' ?>">
          <div class="wc-pill-num"><?= ($diff_pct >= 0 ? '+' : '') . $diff_pct ?>%</div>
          <div class="wc-pill-lbl">vs ayer</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-bag-fill"></i> Ventas</div>
    <div class="top-actions">
      <a href="clientes.php" class="btn-sec"><i class="bi bi-shop"></i> Tiendas</a>
      <a href="<?= APP_URL ?>/modules/recetas/variedades.php" class="btn-sec"><i class="bi bi-list-stars"></i>
        Variedades</a>
    </div>
  </div>

  <div class="g-body">
    <!-- ══ PANEL IZQUIERDO ══ -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-cart-plus-fill"></i></div><span class="ch-title">Nueva
            salida</span>
        </div>
      </div>
      <div class="form-body">
        <?php if ($msg_ok): ?>
          <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div><?php endif; ?>
        <?php if ($msg_err): ?>
          <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div>
        <?php endif; ?>

        <!-- Mode toggle -->
        <div class="mode-toggle">
          <button type="button" class="mode-btn active" id="mode-rapido" onclick="switchMode('rapido')"><i
              class="bi bi-lightning-charge-fill"></i> Venta rápida</button>
          <button type="button" class="mode-btn" id="mode-detalle" onclick="switchMode('detalle')"><i
              class="bi bi-cart4"></i> Detallar pedido</button>
        </div>

        <!-- ══ MODO RÁPIDO ══ -->
        <div id="panel-rapido">
          <form method="POST" id="form-venta">
            <?= campo_csrf() ?>
            <input type="hidden" name="id_categoria" id="inp-cat" value="">
            <input type="hidden" name="tipo_salida" id="inp-tipo" value="venta">
            <input type="hidden" name="precio_custom" id="inp-precio-custom" value="0">
            <div class="sec-sep">¿Qué tipo de pan?</div>
            <div class="fl">
              <div class="cat-grid">
                <?php foreach ($categorias as $c):
                  $stk = max(0, (int) $c['stock_hoy']);
                  $stk_class = $stk > 20 ? 'stk-ok' : ($stk > 0 ? 'stk-warn' : 'stk-zero');
                  ?>
                  <?php // Eran <div onclick>: con el teclado no se podía elegir precio,
                        // así que no había forma de registrar una venta sin ratón. ?>
                  <button type="button" class="cat-btn" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
                    data-stock="<?= $stk ?>" onclick="selCat(this)" aria-pressed="false">
                    <div class="cat-price">$<?= number_format($c['precio_unitario'], 0, ',', '.') ?></div>
                    <div class="cat-stock <?= $stk_class ?>"><?= $stk ?> disp.</div>
                  </button>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:.4rem;">
                <button type="button" class="cat-btn" id="cat-custom" onclick="toggleCustom()" aria-pressed="false" aria-expanded="false" aria-controls="custom-input">
                  <div class="cat-price" style="font-size:.85rem;">✏️ Otro precio</div>
                </button>
                <div id="custom-input" style="display:none;margin-top:.35rem;">
                  <input type="number" id="inp-custom-precio" min="100" max="20000" step="100" placeholder="Ej: 1500"
                    style="width:100%;border:1px solid var(--border);border-radius:9px;padding:.5rem;font-size:.9rem;font-family:'Fraunces',serif;font-weight:700;text-align:center;background:var(--clight);"
                    oninput="setCustomPrice()">
                </div>
              </div>
            </div>
            <div class="sec-sep">¿Cuánto?</div>
            <div class="fl-row">
              <div class="fl"><label for="inp-cantidad">Cantidad</label><input type="number" id="inp-cantidad" name="cantidad" min="1" max="999"
                  step="1" placeholder="Ej: 40" oninput="if(this.value>999)this.value=999;calcTotal()"></div>
              <div class="fl"><label for="inp-monto">O monto total ($)</label><input type="number" id="inp-monto" min="1" step="1" maxlength="5"
                  placeholder="Ej: 20000" oninput="if(this.value.length>5)this.value=this.value.slice(0,5);calcFromMonto()"></div>
            </div>
            <div class="total-display" id="total-box" style="display:none;">
              <div class="total-lbl" id="total-lbl">Total a cobrar</div>
              <div class="total-val" id="total-val">$0</div>
              <div class="total-und" id="total-und">0 unidades</div>
            </div>
            <div class="sec-sep">Tipo de salida</div>
            <div class="fl">
              <div class="tipo-grid">
                <button type="button" class="tipo-btn active" data-tipo="venta" onclick="selTipo(this)" aria-pressed="true"><i class="bi bi-cash-coin" aria-hidden="true"></i>
                  Venta</button>
                <button type="button" class="tipo-btn" data-tipo="consumo_interno" onclick="selTipo(this)" aria-pressed="false"><i class="bi bi-cup-hot" aria-hidden="true"></i>
                  Consumo</button>
              </div>
            </div>
            <div id="wrap-cliente">
              <div class="fl"><label for="sel-cliente">Cliente</label>
                <select name="id_cliente" id="sel-cliente" onchange="calcTotal()">
                  <option value="0">Mostrador</option>
                  <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre'] ?? '') ?> 🏪
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Bonificación tienda (auto) -->
            <div id="bonif-preview"
              style="display:none;background:rgba(21,101,192,.06);border:1px solid rgba(21,101,192,.18);border-radius:10px;padding:.5rem .75rem;margin-bottom:.5rem;font-size:.78rem;color:#1565c0;text-align:center;">
              🏪 Tienda: +<strong id="bonif-cant">0</strong> unidades bonificadas = <strong id="bonif-total">0</strong>
              entregadas
            </div>
            <!-- Ñapa mostrador (auto) -->
            <div id="napa-preview"
              style="display:none;background:rgba(46,125,50,.06);border:1px solid rgba(46,125,50,.18);border-radius:10px;padding:.5rem .75rem;margin-bottom:.5rem;font-size:.78rem;color:#2e7d32;text-align:center;">
              🎁 Ñapa: +<strong id="napa-auto-cant">0</strong> unidades de regalo
            </div>
            <!-- Hidden inputs for form POST -->
            <input type="hidden" name="dar_napa" value="1" id="chk-napa-hidden">
            <input type="hidden" name="napa_cantidad" id="inp-napa" value="0">
            <button type="submit" name="guardar_venta" class="btn-guardar"><i class="bi bi-check-lg"></i>
              Registrar</button>
          </form>
        </div>

        <!-- ══ MODO DETALLE (CARRITO) ══ -->
        <div id="panel-detalle" style="display:none;">
          <form method="POST" id="form-pedido">
            <?= campo_csrf() ?>
            <input type="hidden" name="carrito_json" id="carrito-json" value="[]">

            <div class="sec-sep">Selecciona el precio</div>
            <div class="price-tabs" id="price-tabs">
              <?php foreach ($categorias as $c): ?>
                <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
                  onclick="selPriceTab(this)">
                  $<?= number_format($c['precio_unitario'], 0, ',', '.') ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="sec-sep">Toca un pan para agregarlo</div>
            <div id="prod-catalog">
              <div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio
                arriba</div>
            </div>

            <div class="cart-section">
              <div class="cart-title">🛒 Carrito <span class="cart-badge" id="cart-count">0</span></div>
              <div id="cart-body">
                <div class="cart-empty">Agrega productos desde el catálogo</div>
              </div>
              <div id="cart-total-bar" style="display:none;">
                <div class="cart-total">
                  <span>Cobrados: <strong id="ct-und">0</strong> · Ñapa: <strong id="ct-napa">0</strong></span>
                  <span class="ct-big">$<span id="ct-total">0</span></span>
                </div>
              </div>
            </div>

            <!-- Bonificación tienda / Ñapa mostrador -->
            <div id="bonif-panel" style="display:none;margin-top:.5rem;">
              <div id="bonif-card"
                style="border-radius:10px;padding:.7rem .85rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
                <div
                  style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;flex-wrap:wrap;gap:.3rem;">
                  <span id="bonif-titulo"
                    style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪
                    Bonificación tienda</span>
                  <span id="bonif-credito-lbl" style="font-size:.78rem;font-weight:700;color:#1565c0;">Crédito:
                    <strong>$<span id="bonif-credito">0</span></strong></span>
                </div>
                <div style="font-size:.68rem;color:#1565c0;margin-bottom:.4rem;" id="bonif-hint">
                  Escoge qué panes quiere la tienda. Por defecto $500 (2 panes c/$5.000).
                </div>
                <div id="bonif-varieties" style="max-height:180px;overflow-y:auto;margin-bottom:.4rem;">
                  <div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Cargando variedades...
                  </div>
                </div>
                <div id="bonif-status"
                  style="font-size:.75rem;font-weight:700;text-align:center;padding:.3rem;border-radius:7px;"></div>
              </div>
            </div>
            <input type="hidden" name="bonif_json" id="bonif-json" value="[]">

            <div style="margin-top:.6rem;">
              <div class="sec-sep">Cliente</div>
              <div class="fl">
                <select name="ped_cliente" id="ped-cliente">
                  <option value="0">Mostrador</option>
                  <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre'] ?? '') ?> 🏪
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <button type="submit" name="guardar_pedido" class="btn-guardar" id="btn-pedido" disabled>
              <i class="bi bi-bag-check-fill"></i> Registrar pedido
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ TABLA ══ -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-grn"><i class="bi bi-list-ul"></i></div><span class="ch-title">Registros de hoy</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="badge b-neu"><?= count($registros_hoy) ?></span>
        </div>
      </div>
      <?php if (empty($registros_hoy)): ?>
        <div class="empty"><i class="bi bi-bag-x"></i><strong>Sin registros hoy</strong></div>
      <?php else: ?>
        <div class="tbl-wrap">
          <form method="POST" action="exportar_excel.php" id="form-exportar" target="_blank">
            <div
              style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); background: var(--clight); display:flex; gap: 0.5rem; justify-content: flex-end;">
              <button type="submit" class="btn-sec" style="font-size:0.75rem; padding: 0.3rem 0.6rem;"><i
                  class="bi bi-file-earmark-excel-fill" style="color:#2e7d32;"></i> Exportar a Excel</button>
            </div>
            <table class="gt">
              <thead>
                <tr>
                  <th style="width:30px;"><input type="checkbox" id="chk-all"
                      onclick="document.querySelectorAll('.chk-export').forEach(c => c.checked = this.checked)"></th>
                  <th>Hora</th>
                  <th>Tipo</th>
                  <th>Categoría</th>
                  <th style="text-align:center">Und.</th>
                  <th style="text-align:right">Total</th>
                  <th>Cliente</th>
                  <th style="text-align:center">Bonif.</th>
                  <th style="text-align:center">—</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $tt_map = ['venta' => ['tag-venta', 'bi-cash-coin', 'Venta'], 'bonificacion' => ['tag-bonif', 'bi-gift', 'Bonif.'], 'consumo_interno' => ['tag-consumo', 'bi-cup-hot', 'Consumo']];
                foreach ($registros_hoy as $r):
                  $tt = $tt_map[$r['tipo_salida']] ?? $tt_map['venta'];
                  ?>
                  <tr>
                    <td><input type="checkbox" name="exportar_ids[]" value="<?= $r['id_venta'] ?>" class="chk-export"
                        checked></td>
                    <td style="color:var(--ink3);font-size:.75rem;white-space:nowrap">
                      <?= date('h:i a', strtotime($r['fecha_hora'])) ?></td>
                    <td><span class="tag-tipo <?= $tt[0] ?>"><i class="bi <?= $tt[1] ?>"></i> <?= $tt[2] ?></span></td>
                    <td style="font-weight:600"><?= htmlspecialchars($r['categoria'] ?? 'Pedido detallado') ?></td>
                    <td style="text-align:center;font-family:'Fraunces',serif;font-weight:700;">
                      <?= $r['unidades_vendidas'] ?></td>
                    <td
                      style="text-align:right;font-weight:700;font-family:'Fraunces',serif;<?= $r['tipo_salida'] !== 'venta' ? 'color:var(--ink3)' : 'color:#1b5e20' ?>">
                      <?= $r['tipo_salida'] === 'venta' ? '$' . number_format($r['total_venta'], 0, ',', '.') : '—' ?></td>
                    <td style="font-size:.78rem;color:var(--ink3)">
                      <?= $r['tipo_salida'] === 'venta' ? htmlspecialchars($r['cliente'] ?? '') : '—' ?></td>
                    <td style="text-align:center;font-size:.78rem;">
                      <?php if ($r['bonificacion'] > 0): ?>      <?= $r['cliente'] !== 'Mostrador' ? '<span style="color:#1565c0;font-weight:700;">+' . $r['bonificacion'] . ' 🏪</span>' : '<span style="color:#c67124;font-weight:700;">+' . $r['bonificacion'] . ' 🎁</span>' ?>    <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                      <div style="display:flex;gap:.25rem;justify-content:center;">
                        <?php $tiene_detalle = in_array($r['id_venta'], $ventas_con_detalle); ?>
                        <?php if ($tiene_detalle): ?>
                          <button type="button" class="btn-act btn-edit" title="Editar"
                            onclick="editarPedido(<?= $r['id_venta'] ?>)"><i class="bi bi-pencil"></i></button>
                        <?php else: ?>
                          <button type="button" class="btn-act btn-edit" title="Editar"
                            onclick="abrirEdit(<?= $r['id_venta'] ?>,<?= $r['id_categoria_precio'] ?? 0 ?>,'<?= $r['tipo_salida'] ?>',<?= $r['unidades_vendidas'] ?>,<?= $r['id_cliente'] ?? 0 ?>,<?= (int) $r['bonificacion'] ?>)"><i
                              class="bi bi-pencil"></i></button>
                        <?php endif; ?>
                        <button type="button" class="btn-act btn-del" title="Eliminar"
                          onclick="eliminarVentaRapida(<?= $r['id_venta'] ?>, '<?= generar_token_csrf() ?>')"><i class="bi bi-trash3"></i></button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" style="font-weight:700;">Total ventas</td>
                  <td style="text-align:center;font-family:'Fraunces',serif;"><?= $und_ventas ?></td>
                  <td style="text-align:right;font-family:'Fraunces',serif;font-size:1rem;color:#1b5e20;">
                    $<?= number_format($total_ventas, 0, ',', '.') ?></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
            </table>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal editar -->
<div id="modal-edit"
  style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div
    style="background:#fff;border-radius:14px;padding:1.4rem;width:90%;max-width:420px;box-shadow:0 12px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <strong><i class="bi bi-pencil-square" style="color:var(--c3);"></i> Editar</strong>
      <button onclick="cerrarEdit()"
        style="background:none;border:none;font-size:1.2rem;cursor:pointer;">&times;</button>
    </div>
    <form method="POST">
      <?= campo_csrf() ?>
      <input type="hidden" name="id_venta" id="ev-id">
      <input type="hidden" name="ev_cliente_anterior" id="ev-cli-prev" value="0">
      <input type="hidden" name="ev_unid_anteriores" id="ev-und-prev" value="0">
      <div style="margin-bottom:.6rem;"><label for="ev-cat" style="font-size:.7rem;font-weight:700;">Categoría</label>
        <select name="ev_categoria" id="ev-cat" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>">
              $<?= number_format($c['precio_unitario'], 0, ',', '.') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:.6rem;"><label for="ev-cant" style="font-size:.7rem;font-weight:700;">Cantidad cobrada</label>
        <input type="number" name="ev_cantidad" id="ev-cant" min="1" max="999" required oninput="if(this.value>999)this.value=999;evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;box-sizing:border-box;">
      </div>
      <div style="margin-bottom:.6rem;"><label for="ev-tipo" style="font-size:.7rem;font-weight:700;">Tipo</label>
        <select name="ev_tipo" id="ev-tipo" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="venta">Venta</option>
          <option value="bonificacion">Bonificación</option>
          <option value="consumo_interno">Consumo</option>
        </select>
      </div>
      <div style="margin-bottom:.6rem;"><label for="ev-cli" style="font-size:.7rem;font-weight:700;">Cliente</label>
        <select name="ev_cliente" id="ev-cli" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="0" data-tipo="mostrador">Mostrador</option>
          <?php foreach ($clientes as $cl): ?>
            <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre'] ?? '') ?> 🏪</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Previa dinámica de bonificación / ñapa -->
      <div id="ev-preview"
        style="display:none;margin-bottom:.7rem;border-radius:9px;padding:.55rem .7rem;font-size:.76rem;text-align:center;line-height:1.4;">
      </div>

      <!-- Ajuste extra (cuando faltan unidades al cambiar cliente) -->
      <div id="ev-extra-wrap"
        style="display:none;margin-bottom:.7rem;padding:.55rem .7rem;border:1px dashed rgba(21,101,192,.35);background:rgba(21,101,192,.05);border-radius:9px;">
        <label for="ev-extra"
          style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#1565c0;display:block;margin-bottom:.25rem;">Agregar
          unidades extra (bonificación)</label>
        <div style="font-size:.7rem;color:#1565c0;margin-bottom:.3rem;" id="ev-extra-hint">Antes era mostrador. Si
          quieres entregar más panes ahora que es tienda, súmalos aquí.</div>
        <input type="number" name="ev_extra_bonif" id="ev-extra" min="0" value="0" oninput="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;box-sizing:border-box;text-align:center;font-family:'Fraunces',serif;font-weight:700;">
      </div>

      <button type="submit" name="editar_venta"
        style="width:100%;padding:.55rem;background:var(--c3);color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-family:inherit;"><i
          class="bi bi-check-lg"></i> Guardar</button>
    </form>
  </div>
</div>

<!-- Modal editar pedido detallado -->
<div id="modal-edit-pedido"
  style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;overflow-y:auto;">
  <div
    style="background:#fff;border-radius:14px;padding:1.2rem;width:95%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,.25);margin:1rem auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
      <strong style="font-size:1rem;"><i class="bi bi-pencil-square" style="color:var(--c3)"></i> Editar pedido
        detallado</strong>
      <button onclick="cerrarEditPedido()"
        style="background:none;border:none;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" id="form-edit-pedido">
      <?= campo_csrf() ?>
      <input type="hidden" name="edit_id_venta" id="ep-id">
      <input type="hidden" name="edit_carrito_json" id="ep-carrito-json" value="[]">

      <div
        style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin-bottom:.4rem;">
        Selecciona precio</div>
      <div class="price-tabs" id="ep-price-tabs" style="margin-bottom:.6rem;">
        <?php foreach ($categorias as $c): ?>
          <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
            onclick="epSelPrice(this)">
            $<?= number_format($c['precio_unitario'], 0, ',', '.') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="ep-catalog" style="margin-bottom:.5rem;">
        <div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio</div>
      </div>

      <div
        style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin:.4rem 0;">
        🛒 Carrito <span class="cart-badge" id="ep-cart-count">0</span></div>
      <div id="ep-cart-body">
        <div class="cart-empty">Agrega productos</div>
      </div>
      <div id="ep-cart-total" style="display:none;" class="cart-total">
        <span>Cobrados: <strong id="ep-ct-und">0</strong> · Ñapa: <strong id="ep-ct-napa">0</strong></span>
        <span class="ct-big">$<span id="ep-ct-total">0</span></span>
      </div>

      <!-- Panel de bonificación/ñapa para edición -->
      <div id="ep-bonif-panel" style="display:none;margin-top:.5rem;">
        <div id="ep-bonif-card"
          style="border-radius:10px;padding:.6rem .75rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
          <div
            style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem;flex-wrap:wrap;gap:.25rem;">
            <span id="ep-bonif-titulo"
              style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪
              Bonificación tienda</span>
            <span style="font-size:.74rem;font-weight:700;color:#1565c0;">Crédito: <strong>$<span
                  id="ep-bonif-credito">0</span></strong></span>
          </div>
          <div id="ep-bonif-hint" style="font-size:.66rem;color:#1565c0;margin-bottom:.35rem;">Escoge qué panes entregar
            como bonificación.</div>
          <div id="ep-bonif-varieties" style="max-height:150px;overflow-y:auto;margin-bottom:.35rem;">
            <div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Cargando...</div>
          </div>
          <div id="ep-bonif-status"
            style="font-size:.72rem;font-weight:700;text-align:center;padding:.25rem;border-radius:7px;"></div>
        </div>
      </div>
      <input type="hidden" name="edit_bonif_json" id="ep-bonif-json" value="[]">

      <div style="margin-top:.6rem;">
        <div
          style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin-bottom:.3rem;">
          Cliente</div>
        <select name="edit_ped_cliente" id="ep-cliente" onchange="epCheckBonif()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="0" data-tipo="mostrador">Mostrador</option>
          <?php foreach ($clientes as $cl): ?>
            <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre'] ?? '') ?> 🏪</option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" name="editar_pedido" class="btn-guardar" id="ep-btn-save" style="margin-top:.7rem;"
        disabled>
        <i class="bi bi-check-lg"></i> Guardar cambios
      </button>
    </form>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/utils.js?v=<?= APP_VERSION ?>"></script>
<script>var appUrl = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/ventas.js?v=<?= APP_VERSION ?>"></script>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
