function changeUnd(d) {
  const i = document.getElementById('inp-und');
  // Limitar hasta 5 tandas máximo, mínimo 1
  i.value = Math.min(5, Math.max(1, (parseInt(i.value) || 1) + d));
  cargarLotes();
}

let timer = null;
function showDistribucion(totalUnidades) {
  var panel = document.getElementById('panel-distribucion');
  if (totalUnidades > 0) {
    panel.style.display = 'block';
    document.getElementById('dist-total-label').textContent = totalUnidades;
    document.querySelectorAll('.dist-input').forEach(function(inp) {
      inp.value = 0;
    });
    var first = document.querySelector('.dist-input');
    if (first) first.value = totalUnidades;
    checkDistTotal();
  } else {
    panel.style.display = 'none';
  }
}

function checkDistTotal() {
  var inputs = document.querySelectorAll('.dist-input');
  var target = parseInt(document.getElementById('dist-total-label').textContent) || 0;

  var sum = 0;
  inputs.forEach(function(inp) {
    // Evitar que ingrese un número mayor al límite del sistema
    var val = parseInt(inp.value) || 0;
    if (val > target) {
      inp.value = target;
      val = target;
    }
    if (val < 0) {
      inp.value = 0;
      val = 0;
    }
    sum += val;
  });

  var status = document.getElementById('dist-status');
  var btnG = document.getElementById('btn-guardar');

  if (sum === 0 && target > 0) {
    status.textContent = 'Falta distribuir ' + target + ' unidades';
    status.style.background = 'transparent';
    if(btnG) btnG.disabled = true;
  } else if (sum === target) {
    status.textContent = '✅ ' + sum + ' unidades — coincide con lo esperado';
    status.style.background = 'rgba(46,125,50,.1)';
    status.style.color = '#2e7d32';
    if(btnG) btnG.disabled = false;
  } else if (sum > target) {
    var extra = sum - target;
    status.textContent = '📈 ' + sum + ' unidades — ' + extra + ' más de lo esperado';
    status.style.background = 'rgba(21,101,192,.1)';
    status.style.color = '#1565c0';
    if(btnG) btnG.disabled = true;
  } else {
    var menos = target - sum;
    status.textContent = '📉 ' + sum + ' unidades — ' + menos + ' menos de lo esperado';
    status.style.background = 'rgba(230,81,0,.08)';
    status.style.color = '#e65100';
    if(btnG) btnG.disabled = true;
  }
}

function cargarLotes() {
  clearTimeout(timer);
  timer = setTimeout(_fetch, 420);
}

function _fetch() {
  const prodEl = document.getElementById('sel-prod');
  if (!prodEl) return;
  const prod = prodEl.value;
  const tandas = parseInt(document.getElementById('inp-und').value) || 1;
  const selOpt = prodEl.selectedOptions[0];
  const cantXTanda = parseInt(selOpt?.dataset?.tanda || 1);
  const totalUnidades = tandas * cantXTanda;
  const panel = document.getElementById('panel-lotes');

  // Update preview
  const prev = document.getElementById('preview-unidades');
  const prevVal = document.getElementById('preview-unidades-val');
  if (selOpt && selOpt.value && cantXTanda > 0) {
    if (prevVal) prevVal.textContent = totalUnidades.toLocaleString('es-CO');
    if (prev) prev.style.display = 'block';
    showDistribucion(totalUnidades);
  } else {
    if (prev) prev.style.display = 'none';
    showDistribucion(0);
  }

  if (!prod) {
    if (panel) panel.style.display = 'none';
    return;
  }

  if (panel) panel.style.display = 'block';
  var cont = document.getElementById('lotes-contenido');
  if (cont) cont.innerHTML = '<div class="lotes-loading"><i class="bi bi-arrow-repeat" style="animation:spin .8s linear infinite;display:inline-block"></i> Cargando lotes…</div>';
  var btnG = document.getElementById('btn-guardar');
  if (btnG) btnG.disabled = false;

  fetch('nueva_produccion.php?ajax_lotes=1&id_producto=' + prod + '&unidades=' + tandas)
    .then(r => r.json())
    .then(data => {
      if (data.error === 'sin_receta') {
        if (cont) cont.innerHTML = '<div class="lotes-loading" style="color:#c62828"><i class="bi bi-exclamation-circle"></i> Sin receta vigente — créala en <strong>Recetas</strong> primero.</div>';
        var badge = document.getElementById('badge-lotes');
        if (badge) badge.textContent = '';
        return;
      }
      if (!data.ok) return;

      var badge = document.getElementById('badge-lotes');
      if (badge) badge.textContent = data.ingredientes.length + ' ingrediente(s)';
      if (btnG) btnG.disabled = false;

      const fmt = n => Number(n).toLocaleString('es-CO', {
        maximumFractionDigits: 3
      });
      let html = '';

      data.ingredientes.forEach(ing => {
        html += '<div class="ing-block">';
        html += '<div class="ing-nombre">' +
          '<i class="bi bi-bag" style="color:var(--c3);font-size:.72rem"></i>' +
          '<strong>' + ing.nombre + '</strong>' +
          '<span class="cant-badge' + (!ing.alcanza ? ' falta' : (ing.hay_stock_manual ? ' manual' : '')) + '">' +
          fmt(ing.cant_necesaria) + ' ' + ing.unidad_medida +
          (ing.total_disponible > 0 ? ' · disp: ' + fmt(ing.total_disponible) : '') + '</span>' +
          (ing.aplica_merma ? '<span style="font-size:.56rem;color:var(--c3)">🌾 merma</span>' : '') +
          '</div>';

        if (ing.lotes_a_usar.length === 0) {
          html += '<div class="sin-lote"><i class="bi bi-exclamation-triangle"></i> Sin stock disponible para este insumo — registra una compra primero.</div>';
        } else {
          ing.lotes_a_usar.forEach(lote => {
            if (lote.sin_lote) {
              html += '<div class="lote-fila mas-antiguo" style="background:rgba(198,113,36,.06);border-color:rgba(198,113,36,.2);">' + '<div class="lote-row1">' + '<span class="lote-num"><i class="bi bi-pencil-square" style="font-size:.58rem;margin-right:.2rem;color:var(--c3)"></i>Stock editado en Inventario</span>' + '<span style="font-size:.55rem;font-weight:700;padding:.04rem .3rem;border-radius:20px;background:rgba(198,113,36,.12);color:var(--c3);border:1px solid rgba(198,113,36,.25);">sin lote</span>' + '</div>' + '<div class="lote-row2">' + '<span class="lote-fecha">Sin número de lote</span>' + '<span class="lote-consumir">−' + fmt(lote.a_consumir) + ' ' + ing.unidad_medida + '</span>' + '<span class="lote-disp">disp: ' + fmt(lote.disponible) + '</span>' + '</div>' + '</div>';
            } else {
              html += '<div class="lote-fila' + (lote.es_mas_antiguo ? ' mas-antiguo' : '') + '">' + '<div class="lote-row1">' + '<span class="lote-num"><i class="bi bi-tag-fill" style="color:var(--c3);font-size:.58rem;margin-right:.2rem"></i>' + lote.numero_lote + '</span>' + (lote.es_mas_antiguo ? '<span class="tag-antiguo">📦 más antiguo</span>' : '') + '</div>' + '<div class="lote-row2">' + '<span class="lote-fecha">' + lote.fecha_ingreso + '</span>' + '<span class="lote-consumir">−' + fmt(lote.a_consumir) + ' ' + ing.unidad_medida + '</span>' + '<span class="lote-disp">disp: ' + fmt(lote.disponible) + '</span>' + '</div>' + '</div>';
            }
          });
        }

        if (!ing.alcanza) {
          const falta = ing.cant_necesaria - ing.total_disponible;
          html += '<div class="alert-falta"><i class="bi bi-exclamation-triangle-fill"></i>' +
            '<span class="alert-falta-text">Faltan <strong>' + fmt(falta) + ' ' + ing.unidad_medida + '</strong>. ' +
            'Actualiza el stock en <strong>Inventario</strong> o registra una compra.</span>' +
            '</div>';
        }
        html += '</div>';
      });

      if (data.hay_faltante) {
        html += '<div class="bloqueo-bar">' +
          '<i class="bi bi-exclamation-triangle-fill"></i>' +
          '<span class="alert-falta-text">Stock insuficiente. Si ya sacaste los ingredientes del estante, haz clic en <strong>Registrar</strong> de todas formas — el sistema consumirá lo que haya y registrará el faltante.</span>' +
          '</div>';
      }

      if (cont) cont.innerHTML = html;
    })
    .catch(() => {
      if (cont) cont.innerHTML = '<div class="lotes-loading" style="color:#c62828"><i class="bi bi-wifi-off"></i> Error de conexión.</div>';
    });
}

window.addEventListener('DOMContentLoaded', () => {
  const sel = document.getElementById('sel-prod');
  if (sel && sel.value) cargarLotes();
});
