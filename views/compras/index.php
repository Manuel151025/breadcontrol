<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/compras.css?v=<?= APP_VERSION ?>">

<div class="page">

  <!-- ══ BANNER ══ -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Registro de <em>Compras</em></div>
        <div class="wc-sub">Control de insumos y proveedores — <?= date('F Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $compras_mes ?></div>
        <div class="wc-pill-lbl">Compras mes</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num">$<?= number_format($total_mes / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Gasto mes</div>
      </div>
      <div class="wc-pill <?= $alertas_precio > 0 ? 'alert' : 'ok' ?>">
        <div class="wc-pill-num"><?= $alertas_precio ?></div>
        <div class="wc-pill-lbl">Alertas precio</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $proveedores_n ?></div>
        <div class="wc-pill-lbl">Proveedores</div>
      </div>
    </div>
  </div>

  <!-- ══ TOPBAR ══ -->
  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-cart3"></i> Compras</div>
    <div class="top-actions">
      <form method="get" style="display:flex;gap:.4rem;align-items:center;flex-wrap:wrap;">
        <input type="text" name="q" class="inp-search" placeholder="Buscar insumo o proveedor…" value="<?= htmlspecialchars($busca ?? '') ?>">
        <input type="month" name="mes" class="inp-search" style="width:145px;" value="<?= htmlspecialchars($mes_filtro ?? '') ?>" onchange="this.form.submit()">
        <button type="submit" class="btn-sec" style="padding:.42rem .7rem;" aria-label="Buscar"><i class="bi bi-search"></i></button>
        <?php
          $alerta_params = [];
          if (!$filtro_alerta) $alerta_params[] = 'alerta=1';
          if ($busca) $alerta_params[] = 'q=' . urlencode($busca);
          $alerta_href = 'index.php' . ($alerta_params ? '?' . implode('&', $alerta_params) : '');
        ?>
        <a href="<?= $alerta_href ?>" class="btn-sec <?= $filtro_alerta ? 'active' : '' ?>">
          <i class="bi bi-exclamation-triangle<?= $filtro_alerta ? '-fill' : '' ?>"></i> Alertas
        </a>
      </form>
      <a href="<?= APP_URL ?>/modules/compras/proveedores.php" class="btn-sec">
        <i class="bi bi-people"></i> Proveedores
      </a>
    </div>
  </div>

  <!-- ══ CUERPO ══ -->
  <div class="g-body">

    <!-- FORMULARIO NUEVA COMPRA -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-plus-lg"></i></div>
          <span class="ch-title">Nueva compra</span>
        </div>
      </div>
      <div class="form-body">

        <?php if ($msg_ok): ?>
        <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div>
        <?php if ($last_id): ?>
        <a href="etiqueta_lote.php?id_compra=<?= $last_id ?>" target="_blank"
           style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;font-weight:700;color:var(--c3);text-decoration:none;background:rgba(198,113,36,.07);border:1px solid rgba(198,113,36,.2);border-radius:9px;padding:.45rem .8rem;margin-bottom:.6rem;transition:all .2s"
           onmouseover="this.style.background='rgba(198,113,36,.13)'"
           onmouseout="this.style.background='rgba(198,113,36,.07)'"
        ><i class="bi bi-tag-fill"></i> Imprimir etiquetas de este lote</a>
        <?php endif; ?>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div>
        <?php endif; ?>
        <?php if (esHoyDomingo()): ?>
        <div class="domingo-aviso"><i class="bi bi-moon-stars-fill"></i> Los domingos no se registran compras.</div>
        <?php endif; ?>

        <form method="POST" id="form-compra" onsubmit="prepararEnvio()">
          <?= campo_csrf() ?>
          <input type="hidden" name="id_insumo"    id="inp-id-insumo">
          <input type="hidden" name="id_proveedor" id="inp-id-proveedor">
          <input type="hidden" name="cantidad"     id="inp-cantidad">
          <input type="hidden" name="num_bultos"   id="inp-num-bultos" value="1">
          <span id="lbl-unidad" style="display:none"></span>

          <!-- ── PASO 1: ¿Qué compraste? ── -->
          <div class="sec-sep">¿Qué compraste?</div>

          <?php
          // Estos dos selectores eran <div onclick>: el ratón los abría, pero con
          // el teclado no había forma de llegar a ellos ni de activarlos, así que
          // el formulario de compras era imposible de completar sin ratón. Como
          // <button> el navegador da foco, Enter y Espacio sin JavaScript extra.
          //
          // El domingo van 'disabled' y no con pointer-events:none, que solo
          // desactiva el ratón: con el teclado se seguían pudiendo abrir.
          // aria-labelledby une el rótulo con el valor elegido, porque <label for>
          // no funciona sobre un <button>.
          ?>
          <div class="fl">
            <label id="et-insumo">Insumo</label>
            <button type="button" class="picker-field" id="picker-insumo"
                    onclick="abrirModal('insumos')"
                    aria-labelledby="et-insumo lbl-insumo"
                    <?= esHoyDomingo() ? 'disabled' : '' ?>>
              <span id="lbl-insumo" style="color:var(--ink3)">Seleccionar insumo…</span>
              <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            </button>
          </div>

          <div class="fl">
            <label id="et-proveedor">Proveedor</label>
            <button type="button" class="picker-field" id="picker-prov"
                    onclick="abrirModal('proveedores')"
                    aria-labelledby="et-proveedor lbl-prov"
                    <?= esHoyDomingo() ? 'disabled' : '' ?>>
              <span id="lbl-prov" style="color:var(--ink3)">Seleccionar proveedor…</span>
              <i class="bi bi-grid-3x3-gap" aria-hidden="true"></i>
            </button>
          </div>

          <!-- ── PASO 2: ¿Cuánto compraste? ── -->
          <div class="sec-sep">¿Cuánto compraste?</div>

          <div class="fl-row">
            <div class="fl">
              <label for="vis-bultos">N° de bolsas / empaques</label>
              <input type="number" id="vis-bultos"
                     value="<?= htmlspecialchars($_POST['num_bultos'] ?? '1', ENT_QUOTES) ?? '1' ?>"
                     min="1" step="1" placeholder="1"
                     oninput="recalcular()"
                     <?= esHoyDomingo() ? 'disabled' : '' ?>>
            </div>
            <div class="fl">
              <label for="vis-cant-bolsa">Cantidad por bolsa</label>
              <div class="inp-unidad-wrap">
                <input type="number" id="vis-cant-bolsa"
                       value="<?= htmlspecialchars($_POST['vis_cant_bolsa'] ?? '', ENT_QUOTES) ?? '' ?>"
                       min="0.001" step="0.001" placeholder="Ej: 2.5"
                       oninput="recalcular()"
                       <?= esHoyDomingo() ? 'disabled' : '' ?>>
                <span id="tag-unidad" class="inp-unidad-tag" style="display:none"></span>
              </div>
            </div>
          </div>

          <!-- Advertencia de cantidad muy baja para g y ml -->
          <div id="advertencia-cantidad" style="display:none;margin-bottom:.85rem;padding:.55rem .75rem;border-radius:9px;background:rgba(198,113,36,.06);border:1px dashed rgba(198,113,36,.25);font-size:.73rem;color:var(--c1);line-height:1.45;">
            <i class="bi bi-exclamation-triangle-fill" style="color:var(--c3);margin-right:.2rem;"></i>
            <span>Estás ingresando una cantidad muy baja para un insumo medido en <strong id="adv-unidad-lbl">g</strong>. Recuerda ingresar el peso/volumen real equivalente (ej: bulto de 25 kg = 25.000 g, o botella de 500 ml = 500 ml).</span>
          </div>

          <!-- Total cantidad calculado -->
          <div class="total-badge" id="badge-cant" style="display:none">
            <i class="bi bi-check-circle-fill"></i>
            <span>Total: <strong id="badge-cant-val">—</strong></span>
          </div>

          <!-- ── PASO 3: ¿Cuánto pagaste? ── -->
          <div class="sec-sep">¿Cuánto pagaste?</div>

          <div class="fl">
            <label for="inp-precio">Precio por bolsa / empaque ($)</label>
            <input type="number" name="precio_bulto" id="inp-precio"
                   value="<?= htmlspecialchars($_POST['precio_bulto'] ?? '', ENT_QUOTES) ?? '' ?>"
                   min="1" placeholder="Ej: 9.800"
                   oninput="recalcular()"
                   <?= esHoyDomingo() ? 'disabled' : '' ?>>
          </div>

          <!-- Resumen automático -->
          <div class="compra-resumen" id="compra-resumen">
            <div class="cr-row">
              <span class="cr-lbl" id="cr-lbl-unit">Precio por kg</span>
              <span class="cr-val" id="cr-val-unit">—</span>
            </div>
            <div class="cr-row" id="cr-row-gramo" style="display:none">
              <span class="cr-lbl" id="cr-lbl-gramo">Precio por gramo</span>
              <span class="cr-val" id="cr-val-gramo" style="font-size:.78rem;font-family:inherit;font-weight:700;color:var(--ink3)">—</span>
            </div>
            <div class="cr-sep"></div>
            <div class="cr-row">
              <span class="cr-lbl" style="font-weight:700;color:var(--ink2)">Total a pagar</span>
              <span class="cr-val grande" id="cr-val-total">—</span>
            </div>
          </div>

          <!-- ── Fecha (secundaria) ── -->
          <div class="fl" style="margin-top:.3rem">
            <label for="fecha_compra" style="color:var(--ink3)">Fecha de compra</label>
            <input id="fecha_compra" type="date" name="fecha_compra"
                   value="<?= htmlspecialchars($_POST['fecha_compra'] ?? date('Y-m-d'), ENT_QUOTES) ?? date('Y-m-d') ?>"
                   max="<?= date('Y-m-d') ?>"
                   <?= esHoyDomingo() ? 'disabled' : '' ?>>
          </div>

          <button type="submit" name="guardar_compra" id="btn-guardar" class="btn-guardar"
                  <?= esHoyDomingo() ? 'disabled' : '' ?>>
            <i class="bi bi-cart-check-fill"></i> Registrar compra
          </button>
        </form>
      </div>
    </div>

    <!-- TABLA HISTORIAL -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-table"></i></div>
          <span class="ch-title">Historial de compras</span>
        </div>
        <span class="badge-n"><?= count($compras) ?> registros</span>
      </div>
      <div class="tbl-wrap">
        <?php if (empty($compras)): ?>
        <div class="empty">
          <i class="bi bi-cart3"></i>
          <strong>Sin compras</strong>
          <span>Registra la primera compra usando el formulario</span>
        </div>
        <?php else: ?>
        <!-- Barra de selección múltiple -->
        <div class="sel-bar" id="sel-bar">
          <span class="sel-bar-txt"><span id="sel-count">0</span> compras seleccionadas</span>
          <button class="btn-print-sel" onclick="imprimirSeleccionadas()">
            <i class="bi bi-printer-fill"></i> Imprimir etiquetas
          </button>
          <button class="btn-cancel-sel" onclick="limpiarSeleccion()">Cancelar</button>
        </div>

        <table class="gt">
          <thead>
            <tr>
              <th class="th-chk"><input type="checkbox" class="chk-all" id="chk-all" onclick="toggleTodos(this)" title="Seleccionar todas"></th>
              <th>Fecha</th>
              <th>Insumo</th>
              <th>Proveedor</th>
              <th style="text-align:right">Cantidad</th>
              <th style="text-align:right">Precio/u</th>
              <th style="text-align:right">Total</th>
              <th style="text-align:center">Variación</th>
              <th style="text-align:center">Etiqueta</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($compras as $c):
            $var = (float)$c['variacion_precio_pct']; ?>
          <tr>
            <td class="td-chk"><input type="checkbox" class="row-chk" value="<?= $c['id_compra'] ?>" onchange="actualizarSeleccion()"></td>
            <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($c['fecha_compra'])) ?></td>
            <td><strong><?= htmlspecialchars($c['insumo'] ?? '') ?></strong></td>
            <td><?= htmlspecialchars($c['proveedor'] ?? '') ?></td>
            <td style="text-align:right">
              <span style="font-family:'Fraunces',serif;font-weight:700"><?= formatoInteligente($c['cantidad']) ?></span>
              <span style="font-size:.72rem;color:var(--ink3)"> <?= (strtolower($c['unidad_medida']) === 'unidad' || strtolower($c['unidad_medida']) === 'unidades') ? 'uds' : $c['unidad_medida'] ?></span>
            </td>
            <?php
              $pu = (float)$c['precio_unitario'];
              $pu_display = ($pu >= 100) ? number_format(round($pu), 0, ',', '.') : formatoInteligente($pu);
            ?>
            <td style="text-align:right">$<?= $pu_display ?></td>
            <td style="text-align:right;font-family:'Fraunces',serif;font-weight:700"><?= formatoPeso($c['total_pagado']) ?></td>
            <td style="text-align:center">
              <?php if ($var == 0): ?>
                <span class="tag-neu">Sin cambio</span>
              <?php elseif ($var > 0): ?>
                <span class="tag-alerta">▲ <?= $var ?>%</span>
              <?php else: ?>
                <span class="tag-baja">▼ <?= abs($var) ?>%</span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <a href="etiqueta_lote.php?id_compra=<?= $c['id_compra'] ?>"
                 target="_blank"
                 class="btn-act btn-etq"
                 title="Imprimir etiqueta de lote">
                 <i class="bi bi-tag-fill"></i>
              </a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div><!-- /g-body -->
</div><!-- /page -->


<!-- ══════════════════════════════════════════════════════
     MODAL INSUMOS
══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-insumos" onclick="cerrarAlClick(event,'modal-insumos')">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-ico ico-nar"><i class="bi bi-box-seam-fill"></i></div>
      <div>
        <div class="modal-head-title">Seleccionar insumo</div>
        <div class="modal-head-sub">Elige el insumo que vas a comprar</div>
      </div>
      <button class="modal-close" onclick="cerrarModal('modal-insumos')" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-search">
      <input type="text" id="busca-insumo" placeholder="Buscar insumo…" oninput="filtrarInsumos()" autocomplete="off">
    </div>
    <div class="modal-grid cols2" id="grid-insumos">
      <?php foreach ($insumos as $ins):
        $stock   = (float)$ins['stock_actual'];
        $punto   = (float)$ins['punto_reposicion'];
        if ($stock <= $punto)              { $semaforo = 'crit'; $dot = 'dot-crit'; $lbl = 'Stock crítico'; }
        elseif ($stock <= $punto * 1.5)    { $semaforo = 'low';  $dot = 'dot-low';  $lbl = 'Stock bajo'; }
        else                               { $semaforo = 'ok';   $dot = 'dot-ok';   $lbl = 'En stock'; }
      ?>
      <div class="mcard"
           data-id="<?= $ins['id_insumo'] ?>"
           data-nombre="<?= htmlspecialchars($ins['nombre'] ?? '') ?>"
           data-unidad="<?= $ins['unidad_medida'] ?>"
           data-search="<?= strtolower($ins['nombre']) ?>"
           onclick="seleccionarInsumo(this)">
        <div class="mcard-check"><i class="bi bi-check2"></i></div>
        <div class="mcard-name"><?= htmlspecialchars($ins['nombre'] ?? '') ?></div>
        <?php
          $u_med = (strtolower($ins['unidad_medida']) === 'unidad' || strtolower($ins['unidad_medida']) === 'unidades') ? 'uds' : $ins['unidad_medida'];
        ?>
        <div class="mcard-unit"><?= $u_med ?></div>
        <div class="mcard-stock <?= $semaforo ?>">
          <span class="mcard-dot <?= $dot ?>"></span>
          <?= number_format($stock, 1) ?> <?= $u_med ?> · <?= $lbl ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="modal-empty" id="sin-insumos" style="display:none">
      <i class="bi bi-search"></i>Sin resultados para tu búsqueda
    </div>
    <div class="modal-foot">
      <button class="btn-modal-cancel" onclick="cerrarModal('modal-insumos')">Cancelar</button>
      <button class="btn-modal-ok" id="btn-ok-insumo" onclick="confirmarInsumo()" disabled>
        <i class="bi bi-check2-circle"></i> Confirmar
      </button>
    </div>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════
     MODAL PROVEEDORES
══════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="modal-proveedores" onclick="cerrarAlClick(event,'modal-proveedores')">
  <div class="modal">
    <div class="modal-head">
      <div class="modal-head-ico ico-nar"><i class="bi bi-truck"></i></div>
      <div>
        <div class="modal-head-title">Seleccionar proveedor</div>
        <div class="modal-head-sub">Elige quién suministra este insumo</div>
      </div>
      <button class="modal-close" onclick="cerrarModal('modal-proveedores')" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
    </div>
    <div class="modal-search">
      <input type="text" id="busca-prov" placeholder="Buscar proveedor…" oninput="filtrarProveedores()" autocomplete="off">
    </div>
    <div class="modal-grid cols1" id="grid-proveedores">
      <?php
      $entrega_labels = ['domicilio' => '🚚 Domicilio', 'recogida' => '🏪 Recogida', 'visita' => '🤝 Visita'];
      foreach ($proveedores as $prov):
        $entrega_lbl = $entrega_labels[$prov['tipo_entrega']] ?? $prov['tipo_entrega'];
        $det_parts = [$entrega_lbl];
        if ($prov['telefono'])              $det_parts[] = '📞 ' . $prov['telefono'];
        if ($prov['dias_entrega_promedio']) $det_parts[] = $prov['dias_entrega_promedio'] . ' días entrega';
        if ($prov['dias_visita'])           $det_parts[] = 'Visitas: ' . $prov['dias_visita'];
      ?>
      <div class="pcard"
           data-id="<?= $prov['id_proveedor'] ?>"
           data-nombre="<?= htmlspecialchars($prov['nombre'] ?? '') ?>"
           data-search="<?= strtolower($prov['nombre']) ?>"
           onclick="seleccionarProveedor(this)">
        <div class="pcard-ico"><i class="bi bi-truck"></i></div>
        <div class="pcard-info">
          <div class="pcard-name"><?= htmlspecialchars($prov['nombre'] ?? '') ?></div>
          <div class="pcard-det"><?= implode(' · ', $det_parts) ?></div>
        </div>
        <div class="pcard-check"><i class="bi bi-check2"></i></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="modal-empty" id="sin-proveedores" style="display:none">
      <i class="bi bi-search"></i>Sin resultados para tu búsqueda
    </div>
    <div class="modal-foot">
      <button class="btn-modal-cancel" onclick="cerrarModal('modal-proveedores')">Cancelar</button>
      <button class="btn-modal-ok" id="btn-ok-prov" onclick="confirmarProveedor()" disabled>
        <i class="bi bi-check2-circle"></i> Confirmar
      </button>
    </div>
  </div>
</div>


<script src="<?= APP_URL ?>/assets/js/compras.js?v=<?= APP_VERSION ?>"></script>

<?php require_once __DIR__ . '/../layouts/footer.php'; ?>
