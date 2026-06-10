var precioSel = 0, stockSel = 0;
var cart = [];
var _updating = false;

// ══ MODE TOGGLE ══
function switchMode(mode) {
  document.getElementById('mode-rapido').classList.toggle('active', mode === 'rapido');
  document.getElementById('mode-detalle').classList.toggle('active', mode === 'detalle');
  document.getElementById('panel-rapido').style.display = mode === 'rapido' ? 'block' : 'none';
  document.getElementById('panel-detalle').style.display = mode === 'detalle' ? 'block' : 'none';
}

// ══ QUICK MODE ══
function toggleCustom() {
  var ci = document.getElementById('custom-input');
  var cc = document.getElementById('cat-custom');
  if (ci.style.display === 'none') {
    ci.style.display = 'block';
    cc.classList.add('active');
    document.querySelectorAll('.cat-btn:not(#cat-custom)').forEach(function(b) {
      b.classList.remove('active');
    });
    document.getElementById('inp-custom-precio').focus();
  } else {
    ci.style.display = 'none';
    cc.classList.remove('active');
  }
}

function setCustomPrice() {
  var val = parseFloat(document.getElementById('inp-custom-precio').value) || 0;
  if (val > 20000) {
    val = 20000;
    document.getElementById('inp-custom-precio').value = 20000;
  }
  if (val > 0) {
    document.getElementById('inp-cat').value = '';
    document.getElementById('inp-precio-custom').value = val;
    precioSel = val;
    stockSel = 9999;
    calcTotal();
  }
}

function selCat(el) {
  document.querySelectorAll('.cat-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  el.classList.add('active');
  document.getElementById('custom-input').style.display = 'none';
  document.getElementById('inp-precio-custom').value = '0';
  document.getElementById('inp-cat').value = el.dataset.id;
  precioSel = parseFloat(el.dataset.precio);
  stockSel = parseInt(el.dataset.stock) || 0;
  calcTotal();
}

function toggleNapa() {
  // Legacy — ñapa is now automatic
}

function selTipo(el) {
  document.querySelectorAll('.tipo-btn').forEach(function(b) {
    b.classList.remove('active');
  });
  el.classList.add('active');
  var tipo = el.dataset.tipo;
  document.getElementById('inp-tipo').value = tipo;
  document.getElementById('wrap-cliente').style.display = tipo === 'venta' ? 'block' : 'none';
  document.getElementById('napa-preview').style.display = 'none';
  document.getElementById('inp-napa').value = 0;
  calcTotal();
}

function calcTotal() {
  if (_updating) return;
  _updating = true;
  var cant = parseInt(document.getElementById('inp-cantidad').value) || 0;
  var box = document.getElementById('total-box');
  if (cant > 0 && precioSel > 0) {
    var tipo = document.getElementById('inp-tipo').value;
    var total = tipo === 'venta' ? cant * precioSel : 0;
    document.getElementById('total-val').textContent = tipo === 'venta' ? '$' + total.toLocaleString('es-CO') : '$0';
    document.getElementById('total-und').textContent = cant + ' und × $' + precioSel.toLocaleString('es-CO');
    box.style.display = 'block';
    document.getElementById('inp-monto').value = cant * precioSel;
    var sel = document.getElementById('sel-cliente');
    var opt = sel.options[sel.selectedIndex];
    var bp = document.getElementById('bonif-preview');
    if (tipo === 'venta' && opt && opt.dataset.tipo === 'tienda') {
      // TIENDA: $1.000 de crédito por cada $5.000
      var totalVal = cant * precioSel;
      var creditTotal = Math.floor(totalVal / 5000) * 1000;
      var napaAuto = (precioSel > 0) ? Math.floor(creditTotal / precioSel) : 0;
      
      document.getElementById('bonif-cant').textContent = napaAuto;
      document.getElementById('bonif-total').textContent = (cant + napaAuto);
      bp.style.display = (napaAuto > 0) ? 'block' : 'none';
      document.getElementById('napa-preview').style.display = 'none';
      document.getElementById('inp-napa').value = napaAuto;
    } else if (tipo === 'venta' && (!opt || opt.value === '0')) {
      bp.style.display = 'none';
      
      // MOSTRADOR: $500 de crédito por cada $5.000
      var totalVal = cant * precioSel;
      var creditTotal = Math.floor(totalVal / 5000) * 500;
      var napaAuto = (precioSel > 0) ? Math.floor(creditTotal / precioSel) : 0;
      
      var np = document.getElementById('napa-preview');
      if (napaAuto > 0) {
        document.getElementById('napa-auto-cant').textContent = napaAuto;
        np.style.display = 'block';
      } else {
        np.style.display = 'none';
      }
      document.getElementById('inp-napa').value = napaAuto;
    } else {
      bp.style.display = 'none';
      document.getElementById('napa-preview').style.display = 'none';
      document.getElementById('inp-napa').value = 0;
    }
  } else {
    box.style.display = 'none';
  }
  _updating = false;
}

function calcFromMonto() {
  if (_updating) return;
  _updating = true;
  var monto = parseInt(document.getElementById('inp-monto').value) || 0;
  if (precioSel > 0 && monto > 0) {
    var cant = Math.floor(monto / precioSel);
    document.getElementById('inp-cantidad').value = cant;
  }
  var cant2 = parseInt(document.getElementById('inp-cantidad').value) || 0;
  var box = document.getElementById('total-box');
  if (cant2 > 0 && precioSel > 0) {
    var tipo = document.getElementById('inp-tipo').value;
    var total = tipo === 'venta' ? cant2 * precioSel : 0;
    document.getElementById('total-val').textContent = tipo === 'venta' ? '$' + total.toLocaleString('es-CO') : '$0';
    document.getElementById('total-und').textContent = cant2 + ' und × $' + precioSel.toLocaleString('es-CO');
    box.style.display = 'block';
  }
  _updating = false;
}

// ══ DETAIL MODE (CATALOG + CART) ══
var currentPrice = 0;
var currentCatId = 0;
var catalogVars = [];

function selPriceTab(el) {
  document.querySelectorAll('.price-tab').forEach(function(t) {
    t.classList.remove('active');
  });
  el.classList.add('active');
  currentCatId = parseInt(el.dataset.id);
  currentPrice = parseFloat(el.dataset.precio);
  loadCatalog(currentCatId);
}

function loadCatalog(catId) {
  var catalog = document.getElementById('prod-catalog');
  catalog.innerHTML = '<div style="text-align:center;padding:.8rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
  fetch('index.php?ajax_variedades=1&id_cat=' + catId)
    .then(function(r) {
      return r.json()
    })
    .then(function(vars) {
      catalogVars = vars;
      if (vars.length === 0) {
        catalog.innerHTML = '<div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Sin variedades para este precio.<br><a href="' + appUrl + '/modules/recetas/variedades.php" style="color:var(--c3);font-weight:600;">Crear variedades</a></div>';
        return;
      }
      var html = '<div class="prod-grid">';
      vars.forEach(function(v) {
        var inCart = cart.find(function(x) {
          return x.id_variedad == v.id_variedad
        });
        var cls = inCart ? 'prod-card in-cart' : 'prod-card';
        var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
        html += '<div class="' + cls + '" id="pcard-' + v.id_variedad + '">' +
          '<div onclick="tapProduct(' + v.id_variedad + ')">' +
          imgHtml +
          '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>' +
          '<div class="pc-name" title="' + v.nombre + '">' + v.nombre + '</div>' +
          '</div>' +
          '<div class="pc-form">' +
          '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" value="1" onclick="event.stopPropagation()"></div>' +
          '<div class="pf-row"><label>🎁</label><input type="number" class="pf-napa" min="0" value="0" onclick="event.stopPropagation()"></div>' +
          '<button type="button" class="pf-add" onclick="event.stopPropagation();addToCartFromCard(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>' +
          '</div>' +
          '</div>';
      });
      html += '</div>';
      catalog.innerHTML = html;
    });
}

function tapProduct(idVar) {
  if (cart.find(function(x) {
      return x.id_variedad == idVar
    })) return;
  var pcard = document.getElementById('pcard-' + idVar);
  if (!pcard) return;
  document.querySelectorAll('.prod-card.expanded').forEach(function(c) {
    if (c !== pcard) c.classList.remove('expanded');
  });
  pcard.classList.toggle('expanded');
  if (pcard.classList.contains('expanded')) {
    var inp = pcard.querySelector('.pf-cant');
    if (inp) {
      inp.value = 1;
      inp.focus();
      inp.select();
    }
  }
}

function addToCartFromCard(idVar) {
  var v = catalogVars.find(function(x) {
    return x.id_variedad == idVar
  });
  if (!v) return;
  var pcard = document.getElementById('pcard-' + idVar);
  var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
  if (cant <= 0) return;
  cart.push({
    id_variedad: idVar,
    nombre: v.nombre,
    imagen: v.imagen,
    precio: currentPrice,
    cantidad: cant,
    napa: 0,
    catId: currentCatId
  });
  pcard.classList.remove('expanded');
  pcard.classList.add('in-cart');
  pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
  renderCart();
}

function removeFromCart(idVar) {
  cart = cart.filter(function(x) {
    return x.id_variedad != idVar
  });
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
  var totalUnd = 0,
    totalNapa = 0,
    totalDinero = 0;
  cart.forEach(function(item) {
    var sub = item.cantidad * item.precio;
    totalUnd += item.cantidad;
    totalNapa += item.napa;
    totalDinero += sub;
    var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
    html += '<div class="cart-item">' +
      imgHtml +
      '<div class="ci-info"><div class="ci-name">' + item.nombre + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>' +
      '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" value="' + item.cantidad + '" onchange="updateCartItem(' + item.id_variedad + ',\'cantidad\',this.value)"></div>' +
      '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>' +
      '<button type="button" class="ci-del" onclick="removeFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>' +
      '</div>';
  });
  html += '</div>';
  body.innerHTML = html;

  document.getElementById('ct-und').textContent = totalUnd;
  document.getElementById('ct-napa').textContent = 0;
  document.getElementById('ct-total').textContent = totalDinero.toLocaleString('es-CO');
  totalBar.style.display = 'block';
  btnPedido.disabled = false;
  document.getElementById('carrito-json').value = JSON.stringify(cart);
  checkBonifPanel();
}

function updateCartItem(idVar, field, val) {
  var item = cart.find(function(x) {
    return x.id_variedad == idVar
  });
  if (item) {
    item[field] = Math.max(1, parseInt(val) || 1);
  }
  renderCart();
}

// ══ BONIFICACIÓN TIENDA ══
var allVarieties = [];
var bonifTotal = 0;
var bonifLoaded = false;

function checkBonifPanel() {
  var sel = document.getElementById('ped-cliente');
  var opt = sel.options[sel.selectedIndex];
  var panel = document.getElementById('bonif-panel');
  var isShop = opt && opt.dataset.tipo === 'tienda';
  var isMostrador = !opt || opt.value === '0';

  // Reset bonif_json when recalculating
  document.getElementById('bonif-json').value = '[]';

  if ((isShop || isMostrador) && cart.length > 0) {
    var totalDinero = 0;
    cart.forEach(function(item) {
      totalDinero += (item.cantidad * item.precio);
    });

    if (isShop) {
      // TIENDA: $1.000 de crédito por cada $5.000
      bonifTotal = Math.floor(totalDinero / 5000) * 1000;
      document.querySelector('#bonif-panel span:first-child').textContent = '🏪 Bonif. Tienda ($5k = $1.000)';
      document.getElementById('bonif-total-lbl').textContent = '$' + bonifTotal.toLocaleString('es-CO');
    } else {
      // MOSTRADOR: $500 de crédito por cada $5.000
      bonifTotal = Math.floor(totalDinero / 5000) * 500;
      document.querySelector('#bonif-panel span:first-child').textContent = '🎁 Ñapa Mostrador ($5k = $500)';
      document.getElementById('bonif-total-lbl').textContent = '$' + bonifTotal.toLocaleString('es-CO');
    }

    panel.style.display = bonifTotal > 0 ? 'block' : 'none';
    
    if (bonifTotal > 0) {
      if (!bonifLoaded) loadAllVarieties();
      else renderBonifVarieties();
    }
  } else {
    panel.style.display = 'none';
    bonifTotal = 0;
    document.getElementById('bonif-json').value = '[]';
  }
}

function loadAllVarieties() {
  fetch('index.php?ajax_all_variedades=1')
    .then(function(r) {
      return r.json()
    })
    .then(function(vars) {
      allVarieties = vars;
      bonifLoaded = true;
      renderBonifVarieties();
    });
}

function renderBonifVarieties() {
  var container = document.getElementById('bonif-varieties');
  var sel = document.getElementById('ped-cliente');
  var opt = sel.options[sel.selectedIndex];
  var isMostrador = !opt || opt.value === '0';

  if (allVarieties.length === 0) {
    container.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Sin variedades registradas</div>';
    return;
  }
  var html = '';
  var currentCat = '';
  allVarieties.forEach(function(v) {
    // Solo mostrar panes cuyo precio no exceda el crédito disponible
    if (parseFloat(v.precio_unitario) > bonifTotal) return;

    if (v.cat_nombre !== currentCat) {
      currentCat = v.cat_nombre;
      html += '<div style="font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#64b5f6;padding:.25rem .2rem .1rem;margin-top:.2rem;">' + currentCat + '</div>';
    }
    var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="br-ph">🍞</div>';
    html += '<div class="bonif-row">' +
      imgHtml +
      '<span class="br-name">' + v.nombre + '</span>' +
      '<input type="number" min="0" value="0" data-bonif-id="' + v.id_variedad + '" data-bonif-precio="' + v.precio_unitario + '" oninput="updateBonifStatus()">' +
      '</div>';
  });
  
  if (html === '' && isMostrador) {
    html = '<div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">No hay variedades de $500 disponibles para regalar.</div>';
  }
  
  container.innerHTML = html;
  updateBonifStatus();
}

function updateBonifStatus() {
  var sel = document.getElementById('ped-cliente');
  var opt = sel.options[sel.selectedIndex];
  var isShop = opt && opt.dataset.tipo === 'tienda';

  var inputs = document.querySelectorAll('[data-bonif-id]');
  var totalUnits = 0;
  var totalValue = 0;
  var items = [];
  inputs.forEach(function(inp) {
    var val = parseInt(inp.value) || 0;
    var price = parseFloat(inp.dataset.bonifPrecio);
    totalUnits += val;
    totalValue += (val * price);
    if (val > 0) {
      items.push({
        id_variedad: parseInt(inp.dataset.bonifId),
        cantidad: val,
        precio: price
      });
    }
  });

  var status = document.getElementById('bonif-status');
  if (isShop) {
    if (totalValue <= bonifTotal && totalValue > 0) {
      status.textContent = '✅ Crédito usado: $' + totalValue.toLocaleString('es-CO') + ' / $' + bonifTotal.toLocaleString('es-CO');
      status.style.background = 'rgba(46,125,50,.1)'; status.style.color = '#2e7d32';
    } else if (totalValue > bonifTotal) {
      status.textContent = '⚠️ Excedido: $' + totalValue.toLocaleString('es-CO') + ' / $' + bonifTotal.toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)'; status.style.color = '#c62828';
    } else {
      status.textContent = '📝 Tienes $' + bonifTotal.toLocaleString('es-CO') + ' de crédito';
      status.style.background = 'rgba(21,101,192,.08)'; status.style.color = '#1565c0';
    }
  } else {
    // Modo Crédito (Mostrador)
    if (totalValue <= bonifTotal && totalValue > 0) {
      status.textContent = '✅ Crédito usado: $' + totalValue.toLocaleString('es-CO') + ' / $' + bonifTotal.toLocaleString('es-CO');
      status.style.background = 'rgba(46,125,50,.1)'; status.style.color = '#2e7d32';
    } else if (totalValue > bonifTotal) {
      status.textContent = '⚠️ Excedido: $' + totalValue.toLocaleString('es-CO') + ' / $' + bonifTotal.toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)'; status.style.color = '#c62828';
    } else {
      status.textContent = '📝 Tienes $' + bonifTotal.toLocaleString('es-CO') + ' de crédito';
      status.style.background = 'rgba(21,101,192,.08)'; status.style.color = '#1565c0';
    }
  }

  document.getElementById('bonif-json').value = JSON.stringify(items);
}

// Hook: cuando cambia el cliente, verificar bonificación
var pedCliente = document.getElementById('ped-cliente');
if (pedCliente) pedCliente.addEventListener('change', checkBonifPanel);

// ══ EDIT PEDIDO DETALLADO ══
var epCart = [];
var epCatalogVars = [];
var epCurrentPrice = 0;
var epCurrentCatId = 0;

function editarPedido(idVenta) {
  epCart = [];
  document.getElementById('ep-id').value = idVenta;
  document.getElementById('modal-edit-pedido').style.display = 'flex';
  fetch('index.php?ajax_detalle_venta=1&id_venta=' + idVenta)
    .then(function(r) {
      return r.json()
    })
    .then(function(data) {
      data.items.forEach(function(item) {
        epCart.push({
          id_variedad: parseInt(item.id_variedad),
          nombre: item.nombre,
          imagen: item.imagen,
          precio: parseFloat(item.precio_unitario),
          cantidad: parseInt(item.cantidad),
          napa: parseInt(item.napa) || 0,
          catId: parseInt(item.id_categoria_precio)
        });
      });
      document.getElementById('ep-cliente').value = data.id_cliente || 0;
      epRenderCart();
    });
}

function cerrarEditPedido() {
  document.getElementById('modal-edit-pedido').style.display = 'none';
  epCart = [];
}
var modEditPed = document.getElementById('modal-edit-pedido');
if (modEditPed) modEditPed.addEventListener('click', function(e) {
  if (e.target === this) cerrarEditPedido()
});

function epSelPrice(el) {
  document.querySelectorAll('#ep-price-tabs .price-tab').forEach(function(t) {
    t.classList.remove('active')
  });
  el.classList.add('active');
  epCurrentCatId = parseInt(el.dataset.id);
  epCurrentPrice = parseFloat(el.dataset.precio);
  epLoadCatalog(epCurrentCatId);
}

function epLoadCatalog(catId) {
  var catalog = document.getElementById('ep-catalog');
  catalog.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
  fetch('index.php?ajax_variedades=1&id_cat=' + catId)
    .then(function(r) {
      return r.json()
    })
    .then(function(vars) {
      epCatalogVars = vars;
      if (vars.length === 0) {
        catalog.innerHTML = '<div style="text-align:center;padding:.6rem;font-size:.78rem;color:var(--ink3);">Sin variedades</div>';
        return;
      }
      var html = '<div class="prod-grid" style="max-height:160px;">';
      vars.forEach(function(v) {
        var inCart = epCart.find(function(x) {
          return x.id_variedad == v.id_variedad
        });
        var cls = inCart ? 'prod-card in-cart' : 'prod-card';
        var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
        html += '<div class="' + cls + '" id="ep-pcard-' + v.id_variedad + '">' +
          '<div onclick="epTapProduct(' + v.id_variedad + ')">' +
          imgHtml +
          '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>' +
          '<div class="pc-name" title="' + v.nombre + '">' + v.nombre + '</div>' +
          '</div>' +
          '<div class="pc-form">' +
          '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" value="1" onclick="event.stopPropagation()"></div>' +
          '<div class="pf-row"><label>🎁</label><input type="number" class="pf-napa" min="0" value="0" onclick="event.stopPropagation()"></div>' +
          '<button type="button" class="pf-add" onclick="event.stopPropagation();epAddToCart(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>' +
          '</div>' +
          '</div>';
      });
      html += '</div>';
      catalog.innerHTML = html;
    });
}

function epTapProduct(idVar) {
  if (epCart.find(function(x) {
      return x.id_variedad == idVar
    })) return;
  var pcard = document.getElementById('ep-pcard-' + idVar);
  if (!pcard) return;
  document.querySelectorAll('#ep-catalog .prod-card.expanded').forEach(function(c) {
    if (c !== pcard) c.classList.remove('expanded')
  });
  pcard.classList.toggle('expanded');
  if (pcard.classList.contains('expanded')) {
    var inp = pcard.querySelector('.pf-cant');
    if (inp) {
      inp.value = 1;
      inp.focus();
    }
  }
}

function epAddToCart(idVar) {
  var v = epCatalogVars.find(function(x) {
    return x.id_variedad == idVar
  });
  if (!v) return;
  var pcard = document.getElementById('ep-pcard-' + idVar);
  var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
  var napa = parseInt(pcard.querySelector('.pf-napa').value) || 0;
  if (cant <= 0) return;
  epCart.push({
    id_variedad: idVar,
    nombre: v.nombre,
    imagen: v.imagen,
    precio: epCurrentPrice,
    cantidad: cant,
    napa: napa,
    catId: epCurrentCatId
  });
  pcard.classList.remove('expanded');
  pcard.classList.add('in-cart');
  pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
  epRenderCart();
}

function epRemoveFromCart(idVar) {
  epCart = epCart.filter(function(x) {
    return x.id_variedad != idVar
  });
  var pcard = document.getElementById('ep-pcard-' + idVar);
  if (pcard) {
    pcard.classList.remove('in-cart');
    pcard.classList.remove('expanded');
    var a = pcard.querySelector('.pc-action');
    if (a) a.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Agregar';
  }
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
  var totalUnd = 0,
    totalNapa = 0,
    totalDinero = 0;
  epCart.forEach(function(item) {
    var sub = item.cantidad * item.precio;
    totalUnd += item.cantidad;
    totalNapa += item.napa;
    totalDinero += sub;
    var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
    html += '<div class="cart-item">' +
      imgHtml +
      '<div class="ci-info"><div class="ci-name">' + item.nombre + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>' +
      '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" value="' + item.cantidad + '" onchange="epUpdateItem(' + item.id_variedad + ',\'cantidad\',this.value)"></div>' +
      '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>' +
      '<button type="button" class="ci-del" onclick="epRemoveFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>' +
      '</div>';
  });
  html += '</div>';
  body.innerHTML = html;

  document.getElementById('ep-ct-und').textContent = totalUnd;
  document.getElementById('ep-ct-napa').textContent = totalNapa;
  document.getElementById('ep-ct-total').textContent = totalDinero.toLocaleString('es-CO');
  totalBar.style.display = 'flex';
  btn.disabled = false;
  document.getElementById('ep-carrito-json').value = JSON.stringify(epCart);
}

function epUpdateItem(idVar, field, val) {
  var item = epCart.find(function(x) {
    return x.id_variedad === idVar
  });
  if (item) {
    item[field] = Math.max(1, parseInt(val) || 1);
  }
  epRenderCart();
}

// ══ EDIT MODAL ══
function abrirEdit(id, cat, tipo, cant, cli) {
  document.getElementById('ev-id').value = id;
  document.getElementById('ev-cat').value = cat;
  document.getElementById('ev-cant').value = cant;
  document.getElementById('ev-tipo').value = tipo;
  document.getElementById('ev-cli').value = cli;
  document.getElementById('modal-edit').style.display = 'flex';
}

function cerrarEdit() {
  document.getElementById('modal-edit').style.display = 'none';
}
var modEdit = document.getElementById('modal-edit');
if (modEdit) modEdit.addEventListener('click', function(e) {
  if (e.target === this) cerrarEdit()
});
