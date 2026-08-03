// assets/js/ventas.js — logica del modulo de Ventas (POS, carrito, modales).
// Requiere: utils.js y la variable global appUrl definida por la vista.

  var precioSel = 0, stockSel = 0;
  var cart = [];

  // ══ MODE TOGGLE ══
  function switchMode(mode) {
    document.getElementById('mode-rapido').classList.toggle('active', mode === 'rapido');
    document.getElementById('mode-detalle').classList.toggle('active', mode === 'detalle');
    document.getElementById('panel-rapido').style.display = mode === 'rapido' ? 'block' : 'none';
    document.getElementById('panel-detalle').style.display = mode === 'detalle' ? 'block' : 'none';
  }

  // ══ QUICK MODE ══
  function toggleCustom() { var ci = document.getElementById('custom-input'); var cc = document.getElementById('cat-custom'); if (ci.style.display === 'none') { ci.style.display = 'block'; cc.classList.add('active'); document.querySelectorAll('.cat-btn:not(#cat-custom)').forEach(function (b) { b.classList.remove('active') }); document.getElementById('inp-custom-precio').focus(); } else { ci.style.display = 'none'; cc.classList.remove('active'); } }
  function setCustomPrice() { var val = parseFloat(document.getElementById('inp-custom-precio').value) || 0; if (val > 20000) { val = 20000; document.getElementById('inp-custom-precio').value = 20000; } if (val > 0) { document.getElementById('inp-cat').value = ''; document.getElementById('inp-precio-custom').value = val; precioSel = val; stockSel = 9999; calcTotal(); } }
  function selCat(el) { document.querySelectorAll('.cat-btn').forEach(function (b) { b.classList.remove('active') }); el.classList.add('active'); document.getElementById('custom-input').style.display = 'none'; document.getElementById('inp-precio-custom').value = '0'; document.getElementById('inp-cat').value = el.dataset.id; precioSel = parseFloat(el.dataset.precio); stockSel = parseInt(el.dataset.stock) || 0; calcTotal(); }
  function toggleNapa() { var chk = document.getElementById('chk-napa'); document.getElementById('napa-input').style.display = chk.checked ? 'block' : 'none'; calcTotal(); }
  function selTipo(el) { document.querySelectorAll('.tipo-btn').forEach(function (b) { b.classList.remove('active') }); el.classList.add('active'); var tipo = el.dataset.tipo; document.getElementById('inp-tipo').value = tipo; document.getElementById('wrap-cliente').style.display = tipo === 'venta' ? 'block' : 'none'; document.getElementById('napa-preview').style.display = 'none'; document.getElementById('inp-napa').value = 0; calcTotal(); }
  var _updating = false;
  // === Núcleo: dada la cantidad ya fijada, recalcula total, ñapa y bonificación ===
  function recalcPreview() {
    var cant = parseInt(document.getElementById('inp-cantidad').value) || 0;
    var box = document.getElementById('total-box');
    var bp = document.getElementById('bonif-preview');
    var np = document.getElementById('napa-preview');
    if (!(cant > 0 && precioSel > 0)) {
      box.style.display = 'none';
      bp.style.display = 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = 0;
      return;
    }
    var tipo = document.getElementById('inp-tipo').value;
    var total = tipo === 'venta' ? cant * precioSel : 0;
    document.getElementById('total-val').textContent = tipo === 'venta' ? '$' + total.toLocaleString('es-CO') : '$0';
    document.getElementById('total-und').textContent = cant + ' und × $' + precioSel.toLocaleString('es-CO');
    box.style.display = 'block';
    // Solo aplica ñapa/bonificación si es venta
    var sel = document.getElementById('sel-cliente');
    var opt = sel ? sel.options[sel.selectedIndex] : null;
    if (tipo === 'venta' && opt && opt.dataset.tipo === 'tienda') {
      var tv = cant * precioSel;
      var cr = Math.floor(tv / 5000) * 1000;
      var na = (precioSel > 0) ? Math.floor(cr / precioSel) : 0;
      document.getElementById('bonif-cant').textContent = na;
      document.getElementById('bonif-total').textContent = (cant + na);
      bp.style.display = (na > 0) ? 'block' : 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = na;
    } else if (tipo === 'venta' && (!opt || opt.value === '0')) {
      bp.style.display = 'none';
      var tv = cant * precioSel;
      var cr = Math.floor(tv / 5000) * 500;
      var na = (precioSel > 0) ? Math.floor(cr / precioSel) : 0;
      if (na > 0) { document.getElementById('napa-auto-cant').textContent = na; np.style.display = 'block'; } else { np.style.display = 'none'; }
      document.getElementById('inp-napa').value = na;
    } else {
      bp.style.display = 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = 0;
    }
  }
  function calcTotal() {
    if (_updating) return; _updating = true;
    // Si cambió la cantidad, sincronizo el monto
    var cant = parseInt(document.getElementById('inp-cantidad').value) || 0;
    if (cant > 0 && precioSel > 0) { document.getElementById('inp-monto').value = cant * precioSel; }
    recalcPreview();
    _updating = false;
  }
  function calcFromMonto() {
    if (_updating) return; _updating = true;
    var monto = parseInt(document.getElementById('inp-monto').value) || 0;
    if (precioSel > 0 && monto > 0) {
      var cant = Math.floor(monto / precioSel);
      document.getElementById('inp-cantidad').value = cant;
    }
    recalcPreview();
    _updating = false;
  }

  // ══ DETAIL MODE (CATALOG + CART) ══
  var currentPrice = 0;
  var currentCatId = 0;
  var catalogVars = [];

  function selPriceTab(el) {
    document.querySelectorAll('.price-tab').forEach(function (t) { t.classList.remove('active') });
    el.classList.add('active');
    currentCatId = parseInt(el.dataset.id);
    currentPrice = parseFloat(el.dataset.precio);
    loadCatalog(currentCatId);
  }

  function loadCatalog(catId) {
    var catalog = document.getElementById('prod-catalog');
    catalog.innerHTML = '<div style="text-align:center;padding:.8rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
    fetch('index.php?ajax_variedades=1&id_cat=' + catId)
      .then(function (r) { return r.json() })
      .then(function (vars) {
        catalogVars = vars;
        if (vars.length === 0) {
          catalog.innerHTML = '<div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Sin variedades para este precio.<br><a href="' + appUrl + '/modules/recetas/variedades.php" style="color:var(--c3);font-weight:600;">Crear variedades</a></div>';
          return;
        }
        var html = '<div class="prod-grid">';
        vars.forEach(function (v) {
          var inCart = cart.find(function (x) { return x.id_variedad == v.id_variedad });
          var cls = inCart ? 'prod-card in-cart' : 'prod-card';
          var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
          html += '<div class="' + cls + '" id="pcard-' + v.id_variedad + '">'
            + '<div onclick="tapProduct(' + v.id_variedad + ')">'
            + imgHtml
            + '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>'
            + '<div class="pc-name" title="' + escHtml(v.nombre) + '">' + escHtml(v.nombre) + '</div>'
            + '</div>'
            + '<div class="pc-form">'
            + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="999" value="1" onclick="event.stopPropagation()" oninput="if(this.value>999)this.value=999"></div>'
            + '<button type="button" class="pf-add" onclick="event.stopPropagation();addToCartFromCard(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
            + '</div>'
            + '</div>';
        });
        html += '</div>';
        catalog.innerHTML = html;
      });
  }

  function tapProduct(idVar) {
    if (cart.find(function (x) { return x.id_variedad == idVar })) return;
    // Toggle expanded state
    var pcard = document.getElementById('pcard-' + idVar);
    if (!pcard) return;
    // Close all other expanded cards
    document.querySelectorAll('.prod-card.expanded').forEach(function (c) { if (c !== pcard) c.classList.remove('expanded'); });
    pcard.classList.toggle('expanded');
    if (pcard.classList.contains('expanded')) {
      var inp = pcard.querySelector('.pf-cant');
      if (inp) { inp.value = 1; inp.focus(); inp.select(); }
    }
  }

  function addToCartFromCard(idVar) {
    var v = catalogVars.find(function (x) { return x.id_variedad == idVar });
    if (!v) return;
    var pcard = document.getElementById('pcard-' + idVar);
    var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
    if (cant <= 0) return;
    cart.push({ id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: currentPrice, cantidad: cant, napa: 0, catId: currentCatId });
    pcard.classList.remove('expanded');
    pcard.classList.add('in-cart');
    pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
    renderCart();
  }

  function removeFromCart(idVar) {
    cart = cart.filter(function (x) { return x.id_variedad != idVar });
    var pcard = document.getElementById('pcard-' + idVar);
    if (pcard) {
      pcard.classList.remove('in-cart');
      pcard.classList.remove('expanded');
      var action = pcard.querySelector('.pc-action');
      if (action) action.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Agregar';
    }
    renderCart();
  }

  function renderCart() {
    var body = document.getElementById('cart-body');
    var countEl = document.getElementById('cart-count');
    var totalBar = document.getElementById('cart-total-bar');
    var btnPedido = document.getElementById('btn-pedido');
    countEl.textContent = cart.length;

    if (cart.length === 0) {
      body.innerHTML = '<div class="cart-empty">Agrega productos desde el catálogo</div>';
      totalBar.style.display = 'none';
      btnPedido.disabled = true;
      document.getElementById('carrito-json').value = '[]';
      return;
    }

    var html = '<div class="cart-list">';
    var totalUnd = 0, totalNapa = 0, totalDinero = 0;
    cart.forEach(function (item) {
      var sub = item.cantidad * item.precio;
      totalUnd += item.cantidad;
      totalNapa += item.napa;
      totalDinero += sub;
      var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
      html += '<div class="cart-item">'
        + imgHtml
        + '<div class="ci-info"><div class="ci-name">' + escHtml(item.nombre) + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>'
        + '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" max="999" value="' + item.cantidad + '" onchange="updateCartItem(' + item.id_variedad + ',\'cantidad\',this.value)" oninput="if(this.value>999)this.value=999"></div>'
        + '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>'
        + '<button type="button" class="ci-del" onclick="removeFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
    });
    html += '</div>';
    body.innerHTML = html;

    document.getElementById('ct-und').textContent = totalUnd;
    document.getElementById('ct-napa').textContent = totalNapa;
    document.getElementById('ct-total').textContent = totalDinero.toLocaleString('es-CO');
    totalBar.style.display = 'block';
    btnPedido.disabled = false;
    document.getElementById('carrito-json').value = JSON.stringify(cart);
    checkBonifPanel();
  }

  function updateCartItem(idVar, field, val) {
    var item = cart.find(function (x) { return x.id_variedad == idVar });
    if (item) { item[field] = Math.max(1, parseInt(val) || 1); }
    renderCart();
  }

  // ══ BONIFICACIÓN TIENDA / ÑAPA MOSTRADOR ══
  var allVarieties = [];
  var bonifCredito = 0;   // crédito disponible en PESOS
  var bonifMode = 'tienda'; // 'tienda' o 'mostrador'
  var bonifModeAnterior = '';
  var bonifLoaded = false;

  function checkBonifPanel() {
    var sel = document.getElementById('ped-cliente');
    var opt = sel.options[sel.selectedIndex];
    var panel = document.getElementById('bonif-panel');
    var card = document.getElementById('bonif-card');
    var titulo = document.getElementById('bonif-titulo');
    var hint = document.getElementById('bonif-hint');

    if (cart.length === 0) {
      panel.style.display = 'none';
      bonifCredito = 0;
      document.getElementById('bonif-json').value = '[]';
      return;
    }

    // Calcular total cobrado en pesos
    var totalDinero = 0;
    cart.forEach(function (it) { totalDinero += it.cantidad * it.precio; });

    var modoAnterior = bonifMode;
    if (opt && opt.dataset.tipo === 'tienda') {
      // TIENDA: $1.000 de crédito por cada $5.000
      bonifMode = 'tienda';
      bonifCredito = Math.floor(totalDinero / 5000) * 1000;
      card.style.background = 'rgba(21,101,192,.06)';
      card.style.borderColor = 'rgba(21,101,192,.18)';
      titulo.style.color = '#1565c0';
      titulo.innerHTML = '🏪 Bonificación tienda';
      hint.style.color = '#1565c0';
      hint.innerHTML = 'Escoge qué panes quiere la tienda. Regla: $1.000 de crédito por cada $5.000 vendidos.';
    } else {
      // MOSTRADOR: $500 de crédito por cada $5.000
      bonifMode = 'mostrador';
      bonifCredito = Math.floor(totalDinero / 5000) * 500;
      card.style.background = 'rgba(198,113,36,.06)';
      card.style.borderColor = 'rgba(198,113,36,.2)';
      titulo.style.color = '#c67124';
      titulo.innerHTML = '🎁 Ñapa mostrador';
      hint.style.color = '#c67124';
      hint.innerHTML = 'Escoge qué pan(es) le das de ñapa. Regla: $500 de crédito por cada $5.000 vendidos.';
    }

    document.getElementById('bonif-credito').textContent = bonifCredito.toLocaleString('es-CO');

    if (bonifCredito <= 0) {
      panel.style.display = 'none';
      document.getElementById('bonif-json').value = '[]';
      return;
    }

    panel.style.display = 'block';
    if (!bonifLoaded) {
      loadAllVarieties();
    } else if (modoAnterior !== bonifMode) {
      // Cambió de tienda↔mostrador: re-render para refrescar colores y reiniciar valores
      renderBonifVarieties();
    } else {
      updateBonifStatus();
    }
  }

  function loadAllVarieties() {
    fetch('index.php?ajax_all_variedades=1')
      .then(function (r) { return r.json() })
      .then(function (vars) {
        allVarieties = vars;
        bonifLoaded = true;
        renderBonifVarieties();
      });
  }

  function renderBonifVarieties() {
    var container = document.getElementById('bonif-varieties');
    if (allVarieties.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Sin variedades registradas</div>';
      return;
    }
    var html = '';
    var currentCat = '';
    // Color secundario según modo
    var col = (bonifMode === 'tienda') ? '#1565c0' : '#c67124';
    var colSoft = (bonifMode === 'tienda') ? '#64b5f6' : '#e4a565';
    allVarieties.forEach(function (v) {
      if (v.cat_nombre !== currentCat) {
        currentCat = v.cat_nombre;
        html += '<div style="font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:' + colSoft + ';padding:.25rem .2rem .1rem;margin-top:.2rem;">' + currentCat + ' · $' + parseFloat(v.precio_unitario).toLocaleString('es-CO') + '</div>';
      }
      var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="br-ph">🍞</div>';
      html += '<div class="bonif-row">'
        + imgHtml
        + '<span class="br-name">' + escHtml(v.nombre) + '</span>'
        + '<input type="number" min="0" value="0" data-bonif-id="' + v.id_variedad + '" data-bonif-precio="' + v.precio_unitario + '" oninput="updateBonifStatus(this)">'
        + '</div>';
    });
    container.innerHTML = html;
    updateBonifStatus();
  }

  function updateBonifStatus(changedInput) {
    var inputs = document.querySelectorAll('#bonif-varieties [data-bonif-id]');

    // Calcular cuánto gastan los OTROS inputs (sin el que se está editando)
    if (changedInput) {
      var gastadoOtros = 0;
      inputs.forEach(function(inp) {
        if (inp !== changedInput) {
          var v2 = parseInt(inp.value) || 0;
          var p2 = parseFloat(inp.dataset.bonifPrecio) || 0;
          if (v2 > 0) gastadoOtros += v2 * p2;
        }
      });
      var pr = parseFloat(changedInput.dataset.bonifPrecio) || 0;
      var maxUnidades = pr > 0 ? Math.floor((bonifCredito - gastadoOtros) / pr) : 0;
      if (maxUnidades < 0) maxUnidades = 0;
      var cur = parseInt(changedInput.value) || 0;
      if (cur > maxUnidades) changedInput.value = maxUnidades;
      if (cur < 0) changedInput.value = 0;
      // Limpiar ceros extra (ej: "00" → "0")
      if (changedInput.value === '' || parseInt(changedInput.value) === 0) changedInput.value = 0;
    }

    var gastado = 0;
    var totalUnd = 0;
    var items = [];
    inputs.forEach(function (inp) {
      var val = parseInt(inp.value) || 0;
      var pr = parseFloat(inp.dataset.bonifPrecio) || 0;
      if (val > 0) {
        gastado += val * pr;
        totalUnd += val;
        items.push({ id_variedad: parseInt(inp.dataset.bonifId), cantidad: val, precio: pr });
      }
    });

    var status = document.getElementById('bonif-status');
    var pesosGastado = '$' + gastado.toLocaleString('es-CO');
    var pesosDisp = '$' + bonifCredito.toLocaleString('es-CO');
    if (gastado === bonifCredito) {
      status.textContent = '✅ ' + pesosGastado + '/' + pesosDisp + ' · ' + totalUnd + ' unid.';
      status.style.background = 'rgba(46,125,50,.1)';
      status.style.color = '#2e7d32';
    } else if (gastado > bonifCredito) {
      status.textContent = '⚠️ ' + pesosGastado + '/' + pesosDisp + ' — te pasas $' + (gastado - bonifCredito).toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)';
      status.style.color = '#c62828';
    } else {
      status.textContent = '📝 ' + pesosGastado + '/' + pesosDisp + ' — quedan $' + (bonifCredito - gastado).toLocaleString('es-CO');
      status.style.background = 'rgba(21,101,192,.08)';
      status.style.color = '#1565c0';
    }

    document.getElementById('bonif-json').value = JSON.stringify(items);
  }

  // Hook: cuando cambia el cliente, verificar bonificación
  document.getElementById('ped-cliente').addEventListener('change', checkBonifPanel);

  // ══ EDIT PEDIDO DETALLADO ══
  var epCart = [];
  var epCatalogVars = [];
  var epCurrentPrice = 0;
  var epCurrentCatId = 0;

  function eliminarVentaRapida(idVenta, token) {
    if (!confirm('¿Eliminar?')) return;
    var body = new URLSearchParams();
    body.set('csrf_token', token);
    body.set('del_venta', idVenta);
    fetch('index.php', { method: 'POST', body: body })
      .then(function (r) { window.location.href = r.url; })
      .catch(function () { alert('Error de red al eliminar. Intenta de nuevo.'); });
  }

  function editarPedido(idVenta) {
    epCart = [];
    document.getElementById('ep-id').value = idVenta;
    document.getElementById('modal-edit-pedido').style.display = 'flex';
    // Reset bonif panel
    epBonifLoaded = false;
    document.getElementById('ep-bonif-panel').style.display = 'none';
    document.getElementById('ep-bonif-json').value = '[]';
    // Load existing items
    fetch('index.php?ajax_detalle_venta=1&id_venta=' + idVenta)
      .then(function (r) { return r.json() })
      .then(function (data) {
        // Separar carrito (cantidad>0) de bonificaciones (cantidad=0 y bonificacion>0)
        var bonifPrellenado = {};
        data.items.forEach(function (item) {
          var cant = parseInt(item.cantidad) || 0;
          var bonif = parseInt(item.bonificacion) || 0;
          if (cant > 0) {
            epCart.push({
              id_variedad: parseInt(item.id_variedad),
              nombre: item.nombre,
              imagen: item.imagen,
              precio: parseFloat(item.precio_unitario),
              cantidad: cant,
              napa: parseInt(item.napa) || 0,
              catId: parseInt(item.id_categoria_precio)
            });
          } else if (bonif > 0) {
            bonifPrellenado[parseInt(item.id_variedad)] = bonif;
          }
        });
        document.getElementById('ep-cliente').value = data.id_cliente || 0;
        epRenderCart();
        // Después de cargar el carrito, espero a que el panel cargue y pre-lleno
        var fillBonif = function () {
          Object.keys(bonifPrellenado).forEach(function (idv) {
            var inp = document.querySelector('#ep-bonif-varieties [data-ep-bonif-id="' + idv + '"]');
            if (inp) inp.value = bonifPrellenado[idv];
          });
          epUpdateBonifStatus();
        };
        if (epBonifLoaded) { fillBonif(); }
        else {
          // Esperar a que carguen las variedades
          var tries = 0;
          var iv = setInterval(function () {
            tries++;
            if (epBonifLoaded || tries > 30) {
              clearInterval(iv);
              fillBonif();
            }
          }, 100);
        }
      });
  }

  function cerrarEditPedido() {
    document.getElementById('modal-edit-pedido').style.display = 'none';
    epCart = [];
    document.getElementById('ep-bonif-panel').style.display = 'none';
    document.getElementById('ep-bonif-json').value = '[]';
    epBonifMode = '';
    epBonifModeAnt = '';
  }
  document.getElementById('modal-edit-pedido').addEventListener('click', function (e) { if (e.target === this) cerrarEditPedido() });

  function epSelPrice(el) {
    document.querySelectorAll('#ep-price-tabs .price-tab').forEach(function (t) { t.classList.remove('active') });
    el.classList.add('active');
    epCurrentCatId = parseInt(el.dataset.id);
    epCurrentPrice = parseFloat(el.dataset.precio);
    epLoadCatalog(epCurrentCatId);
  }

  function epLoadCatalog(catId) {
    var catalog = document.getElementById('ep-catalog');
    catalog.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
    fetch('index.php?ajax_variedades=1&id_cat=' + catId)
      .then(function (r) { return r.json() })
      .then(function (vars) {
        epCatalogVars = vars;
        if (vars.length === 0) {
          catalog.innerHTML = '<div style="text-align:center;padding:.6rem;font-size:.78rem;color:var(--ink3);">Sin variedades</div>';
          return;
        }
        var html = '<div class="prod-grid" style="max-height:160px;">';
        vars.forEach(function (v) {
          var inCart = epCart.find(function (x) { return x.id_variedad == v.id_variedad });
          var cls = inCart ? 'prod-card in-cart' : 'prod-card';
          var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
          html += '<div class="' + cls + '" id="ep-pcard-' + v.id_variedad + '">'
            + '<div onclick="epTapProduct(' + v.id_variedad + ')">'
            + imgHtml
            + '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>'
            + '<div class="pc-name" title="' + escHtml(v.nombre) + '">' + escHtml(v.nombre) + '</div>'
            + '</div>'
            + '<div class="pc-form">'
            + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="999" value="1" onclick="event.stopPropagation()" oninput="if(this.value>999)this.value=999"></div>'
            + '<button type="button" class="pf-add" onclick="event.stopPropagation();epAddToCart(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
            + '</div>'
            + '</div>';
        });
        html += '</div>';
        catalog.innerHTML = html;
      });
  }

  function epTapProduct(idVar) {
    if (epCart.find(function (x) { return x.id_variedad == idVar })) return;
    var pcard = document.getElementById('ep-pcard-' + idVar);
    if (!pcard) return;
    document.querySelectorAll('#ep-catalog .prod-card.expanded').forEach(function (c) { if (c !== pcard) c.classList.remove('expanded') });
    pcard.classList.toggle('expanded');
    if (pcard.classList.contains('expanded')) {
      var inp = pcard.querySelector('.pf-cant');
      if (inp) { inp.value = 1; inp.focus(); }
    }
  }

  function epAddToCart(idVar) {
    var v = epCatalogVars.find(function (x) { return x.id_variedad == idVar });
    if (!v) return;
    var pcard = document.getElementById('ep-pcard-' + idVar);
    var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
    if (cant <= 0) return;
    epCart.push({ id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: epCurrentPrice, cantidad: cant, napa: 0, catId: epCurrentCatId });
    pcard.classList.remove('expanded');
    pcard.classList.add('in-cart');
    pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
    epRenderCart();
  }

  function epRemoveFromCart(idVar) {
    epCart = epCart.filter(function (x) { return x.id_variedad != idVar });
    var pcard = document.getElementById('ep-pcard-' + idVar);
    if (pcard) { pcard.classList.remove('in-cart'); pcard.classList.remove('expanded'); var a = pcard.querySelector('.pc-action'); if (a) a.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Agregar'; }
    epRenderCart();
  }

  function epRenderCart() {
    var body = document.getElementById('ep-cart-body');
    var countEl = document.getElementById('ep-cart-count');
    var totalBar = document.getElementById('ep-cart-total');
    var btn = document.getElementById('ep-btn-save');
    countEl.textContent = epCart.length;

    if (epCart.length === 0) {
      body.innerHTML = '<div class="cart-empty">Agrega productos</div>';
      totalBar.style.display = 'none';
      btn.disabled = true;
      document.getElementById('ep-carrito-json').value = '[]';
      return;
    }

    var html = '<div class="cart-list">';
    var totalUnd = 0, totalNapa = 0, totalDinero = 0;
    epCart.forEach(function (item) {
      var sub = item.cantidad * item.precio;
      totalUnd += item.cantidad; totalNapa += item.napa; totalDinero += sub;
      var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
      html += '<div class="cart-item">'
        + imgHtml
        + '<div class="ci-info"><div class="ci-name">' + escHtml(item.nombre) + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>'
        + '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" max="999" value="' + item.cantidad + '" onchange="epUpdateItem(' + item.id_variedad + ',\'cantidad\',this.value)" oninput="if(this.value>999)this.value=999"></div>'
        + '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>'
        + '<button type="button" class="ci-del" onclick="epRemoveFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
    });
    html += '</div>';
    body.innerHTML = html;

    document.getElementById('ep-ct-und').textContent = totalUnd;
    document.getElementById('ep-ct-napa').textContent = totalNapa;
    document.getElementById('ep-ct-total').textContent = totalDinero.toLocaleString('es-CO');
    totalBar.style.display = 'flex';
    btn.disabled = false;
    document.getElementById('ep-carrito-json').value = JSON.stringify(epCart);
    epCheckBonif();
  }

  function epUpdateItem(idVar, field, val) {
    var item = epCart.find(function (x) { return x.id_variedad === idVar });
    if (item) { item[field] = Math.max(1, parseInt(val) || 1); }
    epRenderCart();
  }

  // ══ BONIFICACIÓN/ÑAPA del MODAL editar pedido ══
  var epBonifCredito = 0;
  var epBonifMode = 'tienda';
  var epBonifModeAnt = '';
  var epBonifLoaded = false;
  var epAllVarieties = [];

  function epCheckBonif() {
    var sel = document.getElementById('ep-cliente');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var panel = document.getElementById('ep-bonif-panel');
    var card = document.getElementById('ep-bonif-card');
    var titulo = document.getElementById('ep-bonif-titulo');
    var hint = document.getElementById('ep-bonif-hint');

    if (epCart.length === 0) {
      panel.style.display = 'none';
      epBonifCredito = 0;
      document.getElementById('ep-bonif-json').value = '[]';
      return;
    }

    var totalDinero = 0;
    epCart.forEach(function (it) { totalDinero += it.cantidad * it.precio; });

    var modoAnt = epBonifMode;
    if (opt && opt.dataset.tipo === 'tienda') {
      epBonifMode = 'tienda';
      epBonifCredito = Math.floor(totalDinero / 5000) * 1000;
      card.style.background = 'rgba(21,101,192,.06)';
      card.style.borderColor = 'rgba(21,101,192,.18)';
      titulo.style.color = '#1565c0';
      titulo.innerHTML = '🏪 Bonificación tienda';
      hint.style.color = '#1565c0';
      hint.innerHTML = 'Tienda: $1.000 de crédito por cada $5.000. Escoge los panes.';
    } else {
      epBonifMode = 'mostrador';
      epBonifCredito = Math.floor(totalDinero / 5000) * 500;
      card.style.background = 'rgba(198,113,36,.06)';
      card.style.borderColor = 'rgba(198,113,36,.2)';
      titulo.style.color = '#c67124';
      titulo.innerHTML = '🎁 Ñapa mostrador';
      hint.style.color = '#c67124';
      hint.innerHTML = 'Mostrador: $500 de crédito por cada $5.000. Escoge la(s) ñapa(s).';
    }

    document.getElementById('ep-bonif-credito').textContent = epBonifCredito.toLocaleString('es-CO');

    if (epBonifCredito <= 0) {
      panel.style.display = 'none';
      document.getElementById('ep-bonif-json').value = '[]';
      return;
    }

    panel.style.display = 'block';
    if (!epBonifLoaded) {
      epLoadAllVarieties();
    } else if (modoAnt !== epBonifMode && modoAnt !== '') {
      epRenderBonifVars();
    } else {
      epUpdateBonifStatus();
    }
  }

  function epLoadAllVarieties() {
    fetch('index.php?ajax_all_variedades=1')
      .then(function (r) { return r.json() })
      .then(function (vars) {
        epAllVarieties = vars;
        epBonifLoaded = true;
        epRenderBonifVars();
      });
  }

  function epRenderBonifVars() {
    var container = document.getElementById('ep-bonif-varieties');
    if (epAllVarieties.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Sin variedades</div>';
      return;
    }
    var html = '';
    var currentCat = '';
    var colSoft = (epBonifMode === 'tienda') ? '#64b5f6' : '#e4a565';
    epAllVarieties.forEach(function (v) {
      if (v.cat_nombre !== currentCat) {
        currentCat = v.cat_nombre;
        html += '<div style="font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:' + colSoft + ';padding:.2rem .15rem .05rem;margin-top:.15rem;">' + currentCat + ' · $' + parseFloat(v.precio_unitario).toLocaleString('es-CO') + '</div>';
      }
      var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="br-ph">🍞</div>';
      html += '<div class="bonif-row">'
        + imgHtml
        + '<span class="br-name">' + escHtml(v.nombre) + '</span>'
        + '<input type="number" min="0" value="0" data-ep-bonif-id="' + v.id_variedad + '" data-ep-bonif-precio="' + v.precio_unitario + '" oninput="epUpdateBonifStatus()">'
        + '</div>';
    });
    container.innerHTML = html;
    epUpdateBonifStatus();
  }

  function epUpdateBonifStatus() {
    var inputs = document.querySelectorAll('#ep-bonif-varieties [data-ep-bonif-id]');
    var gastado = 0;
    var totalUnd = 0;
    var items = [];
    inputs.forEach(function (inp) {
      var val = parseInt(inp.value) || 0;
      var pr = parseFloat(inp.dataset.epBonifPrecio) || 0;
      if (val > 0) {
        gastado += val * pr;
        totalUnd += val;
        items.push({ id_variedad: parseInt(inp.dataset.epBonifId), cantidad: val, precio: pr });
      }
    });
    var status = document.getElementById('ep-bonif-status');
    var pg = '$' + gastado.toLocaleString('es-CO');
    var pd = '$' + epBonifCredito.toLocaleString('es-CO');
    if (gastado === epBonifCredito) {
      status.textContent = '✅ ' + pg + '/' + pd + ' · ' + totalUnd + ' unid.';
      status.style.background = 'rgba(46,125,50,.1)'; status.style.color = '#2e7d32';
    } else if (gastado > epBonifCredito) {
      status.textContent = '⚠️ ' + pg + '/' + pd + ' — te pasas $' + (gastado - epBonifCredito).toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)'; status.style.color = '#c62828';
    } else {
      status.textContent = '📝 ' + pg + '/' + pd + ' — quedan $' + (epBonifCredito - gastado).toLocaleString('es-CO');
      status.style.background = 'rgba(21,101,192,.08)'; status.style.color = '#1565c0';
    }
    document.getElementById('ep-bonif-json').value = JSON.stringify(items);
  }

  // ══ EDIT MODAL ══
  var _editRecalcUpdating = false;
  function abrirEdit(id, cat, tipo, cantTotal, cli, bonifPrev) {
    if (_editRecalcUpdating) return;
    _editRecalcUpdating = true;
    bonifPrev = parseInt(bonifPrev) || 0;
    cantTotal = parseInt(cantTotal) || 0;
    // En BD las "unidades_vendidas" incluyen la bonificación. Restamos para mostrar las cobradas.
    var cantCobradas = Math.max(1, cantTotal - bonifPrev);
    document.getElementById('ev-id').value = id;
    document.getElementById('ev-cat').value = cat;
    document.getElementById('ev-cant').value = cantCobradas;
    document.getElementById('ev-tipo').value = tipo;
    document.getElementById('ev-cli').value = cli;
    document.getElementById('ev-cli-prev').value = cli;
    document.getElementById('ev-und-prev').value = cantCobradas;
    document.getElementById('ev-extra').value = 0;
    document.getElementById('ev-extra').disabled = false;
    document.getElementById('modal-edit').style.display = 'flex';
    _editRecalcUpdating = false;
    evRecalc();
  }
  function cerrarEdit() { document.getElementById('modal-edit').style.display = 'none'; }
  document.getElementById('modal-edit').addEventListener('click', function (e) { if (e.target === this) cerrarEdit() });

  // Recalcula la previa de bonificación/ñapa en el modal edit
  function evRecalc() {
    if (_editRecalcUpdating) return;
    _editRecalcUpdating = true;
    var preview = document.getElementById('ev-preview');
    var extraWrap = document.getElementById('ev-extra-wrap');
    var extraHint = document.getElementById('ev-extra-hint');
    var tipo = document.getElementById('ev-tipo').value;
    var catSel = document.getElementById('ev-cat');
    var precio = parseFloat(catSel.options[catSel.selectedIndex].dataset.precio) || 0;
    var cant = parseInt(document.getElementById('ev-cant').value) || 0;
    var cliSel = document.getElementById('ev-cli');
    var cliOpt = cliSel.options[cliSel.selectedIndex];
    var esTienda = cliOpt && cliOpt.dataset.tipo === 'tienda';
    var cliPrev = parseInt(document.getElementById('ev-cli-prev').value) || 0;

    // Solo aplica para tipo 'venta'
    if (tipo !== 'venta' || cant <= 0 || precio <= 0) {
      preview.style.display = 'none';
      extraWrap.style.display = 'none';
      _editRecalcUpdating = false;
      return;
    }

    var total = cant * precio;
    if (esTienda) {
      var credito = Math.floor(total / 5000) * 1000;
      var und = (precio > 0) ? Math.floor(credito / precio) : 0;
      var extra = parseInt(document.getElementById('ev-extra').value) || 0;
      var undConExtra = und + extra;
      preview.style.display = 'block';
      preview.style.background = 'rgba(21,101,192,.08)';
      preview.style.color = '#1565c0';
      preview.style.border = '1px solid rgba(21,101,192,.2)';
      preview.innerHTML = '🏪 <strong>Tienda</strong> · Crédito: <strong>$' + credito.toLocaleString('es-CO') + '</strong> = <strong>' + undConExtra + '</strong> unidad(es) de bonificación<br><small>Total entregado: <strong>' + (cant + undConExtra) + '</strong> panes</small>';
      // Si antes era mostrador (cliPrev==0) y ahora es tienda → mostrar campo extra
      if (cliPrev === 0) {
        extraWrap.style.display = 'block';
        extraHint.innerHTML = 'Antes era <strong>mostrador</strong>. Ahora es <strong>tienda</strong>: la bonificación es mayor. Aquí puedes <strong>agregar</strong> unidades extra para completar.';
      } else {
        extraWrap.style.display = 'none';
        document.getElementById('ev-extra').value = 0;
      }
    } else {
      // Mostrador
      var credito = Math.floor(total / 5000) * 500;
      var und = (precio > 0) ? Math.floor(credito / precio) : 0;
      preview.style.display = 'block';
      preview.style.background = 'rgba(198,113,36,.08)';
      preview.style.color = '#c67124';
      preview.style.border = '1px solid rgba(198,113,36,.22)';
      preview.innerHTML = '🎁 <strong>Mostrador</strong> · Crédito: <strong>$' + credito.toLocaleString('es-CO') + '</strong> = <strong>' + und + '</strong> de ñapa<br><small>Total entregado: <strong>' + (cant + und) + '</strong> panes</small>';
      // Si antes era tienda y ahora pasa a mostrador → avisar que se le restan las unidades extra a la tienda
      if (cliPrev > 0) {
        extraWrap.style.display = 'block';
        extraWrap.style.borderColor = 'rgba(198,113,36,.4)';
        extraWrap.style.background = 'rgba(198,113,36,.05)';
        extraHint.style.color = '#c67124';
        extraHint.innerHTML = '⚠️ Antes era <strong>tienda</strong>. Al pasar a <strong>mostrador</strong>, las unidades extra de bonificación se descuentan automáticamente (la tienda ya no se las lleva).';
        document.getElementById('ev-extra').value = 0;
        document.getElementById('ev-extra').disabled = true;
      } else {
        extraWrap.style.display = 'none';
        document.getElementById('ev-extra').disabled = false;
      }
    }
    _editRecalcUpdating = false;
  }
