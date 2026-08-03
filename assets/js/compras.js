// assets/js/compras.js — logica del modulo de Compras e insumos.

// ══ Estado temporal de selección ══
let tmpInsumo    = null; // {id, nombre, unidad}
let tmpProveedor = null; // {id, nombre}

// ── Abrir / cerrar modales ─────────────────────────────────────
function abrirModal(cual) {
  document.getElementById('modal-' + cual).classList.add('open');
  document.body.style.overflow = 'hidden';
  const input = cual === 'insumos' ? 'busca-insumo' : 'busca-prov';
  setTimeout(() => document.getElementById(input)?.focus(), 80);
}
function cerrarModal(id) {
  document.getElementById(id).classList.remove('open');
  document.body.style.overflow = '';
  // Fixed typo from original monolithic code (replaced 'cual' with 'id')
  if (id === 'modal-insumos') {
    tmpInsumo = null;
  }
  if (id === 'modal-proveedores') {
    tmpProveedor = null;
  }
}
function cerrarAlClick(e, id) {
  if (e.target === e.currentTarget) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
  }
}

// ── Cerrar con Escape ──────────────────────────────────────────
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    document.querySelectorAll('.modal-overlay.open')
      .forEach(m => { m.classList.remove('open'); document.body.style.overflow = ''; });
  }
});

// ══ INSUMOS ══════════════════════════════════════════════════
function filtrarInsumos() {
  const q    = document.getElementById('busca-insumo').value.toLowerCase().trim();
  const cards = document.querySelectorAll('#grid-insumos .mcard');
  let vis = 0;
  cards.forEach(c => {
    const match = c.dataset.search.includes(q);
    c.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  document.getElementById('sin-insumos').style.display = vis === 0 ? 'block' : 'none';
}

function seleccionarInsumo(card) {
  document.querySelectorAll('#grid-insumos .mcard').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  tmpInsumo = { id: card.dataset.id, nombre: card.dataset.nombre, unidad: card.dataset.unidad };
  document.getElementById('btn-ok-insumo').disabled = false;
}

function confirmarInsumo() {
  if (!tmpInsumo) return;
  document.getElementById('inp-id-insumo').value = tmpInsumo.id;
  const picker = document.getElementById('picker-insumo');
  picker.querySelector('span').textContent = tmpInsumo.nombre;
  picker.querySelector('span').style.color = '';
  picker.querySelector('i').className = 'bi bi-check-circle-fill';
  picker.classList.add('filled');
  // Set unit label (hidden) and visible tag
  document.getElementById('lbl-unidad').textContent = tmpInsumo.unidad;
  const tagEl = document.getElementById('tag-unidad');
  if (tagEl) {
    let unitTag = tmpInsumo.unidad;
    if (unitTag.toLowerCase() === 'unidad' || unitTag.toLowerCase() === 'unidades') {
      unitTag = 'uds';
    }
    tagEl.textContent = unitTag;
    tagEl.style.display = '';
  }
  recalcular();
  cerrarModal('modal-insumos');
  document.getElementById('modal-insumos').classList.remove('open');
  document.body.style.overflow = '';
}

// ══ Preseleccionar insumo si se llega con ?id_insumo=X (ej: desde Producción) ══
(function preseleccionarInsumoDesdeURL() {
  const idInsumo = new URLSearchParams(window.location.search).get('id_insumo');
  if (!idInsumo) return;
  const card = document.querySelector('#grid-insumos .mcard[data-id="' + idInsumo + '"]');
  if (!card) return;
  seleccionarInsumo(card);
  confirmarInsumo();
  document.getElementById('form-compra')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
})();

// ══ PROVEEDORES ═══════════════════════════════════════════════
function filtrarProveedores() {
  const q     = document.getElementById('busca-prov').value.toLowerCase().trim();
  const cards = document.querySelectorAll('#grid-proveedores .pcard');
  let vis = 0;
  cards.forEach(c => {
    const match = c.dataset.search.includes(q);
    c.style.display = match ? '' : 'none';
    if (match) vis++;
  });
  document.getElementById('sin-proveedores').style.display = vis === 0 ? 'block' : 'none';
}

function seleccionarProveedor(card) {
  document.querySelectorAll('#grid-proveedores .pcard').forEach(c => c.classList.remove('selected'));
  card.classList.add('selected');
  tmpProveedor = { id: card.dataset.id, nombre: card.dataset.nombre };
  document.getElementById('btn-ok-prov').disabled = false;
}

function confirmarProveedor() {
  if (!tmpProveedor) return;
  document.getElementById('inp-id-proveedor').value = tmpProveedor.id;
  const picker = document.getElementById('picker-prov');
  picker.querySelector('span').textContent = tmpProveedor.nombre;
  picker.querySelector('span').style.color = '';
  picker.querySelector('i').className = 'bi bi-check-circle-fill';
  picker.classList.add('filled');
  document.getElementById('modal-proveedores').classList.remove('open');
  document.body.style.overflow = '';
}

// ══ Recalcular en tiempo real ══════════════════════════════════
const UNIDADES_KG = ['kg','g','L','ml','unidad'];
const UK = ['kg'];
const UG = ['g'];
const UL = ['l','L'];
const UML = ['ml'];

function fmt(n)    { return '$' + Math.round(n).toLocaleString('es-CO'); }
function fmtD(n) {
  if (n === 0) return '$0';
  if (n % 1 === 0) {
    return '$' + n.toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }
  if (n >= 100) {
    return '$' + Math.round(n).toLocaleString('es-CO', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
  }
  if (n >= 10) {
    return '$' + n.toLocaleString('es-CO', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  }
  if (n >= 1) {
    return '$' + n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }
  return '$' + n.toLocaleString('es-CO', { minimumFractionDigits: 2, maximumFractionDigits: 3 });
}
function fmtN(n,u) { return n.toLocaleString('es-CO',{maximumFractionDigits:3}) + ' ' + u; }

function recalcular() {
  const numBolsas   = Math.max(1, parseInt(document.getElementById('vis-bultos')?.value)     || 1);
  const cantBolsa   = parseFloat(document.getElementById('vis-cant-bolsa')?.value)           || 0;
  const precioBolsa = parseFloat(document.getElementById('inp-precio')?.value)               || 0;
  const unidad      = document.getElementById('lbl-unidad')?.textContent.trim().toLowerCase() || '';

  let unidadDisplay = unidad;
  if (unidadDisplay === 'unidad' || unidadDisplay === 'unidades') {
    unidadDisplay = 'uds';
  }

  const totalCant   = cantBolsa * numBolsas;
  const totalPagar  = precioBolsa * numBolsas;

  // Badge cantidad
  const badgeCant = document.getElementById('badge-cant');
  const badgeVal  = document.getElementById('badge-cant-val');
  if (cantBolsa > 0) {
    badgeVal.textContent = fmtN(totalCant, unidadDisplay);
    badgeCant.style.display = 'flex';
  } else {
    badgeCant.style.display = 'none';
  }

  // Resumen precio
  const resumen = document.getElementById('compra-resumen');
  if (cantBolsa > 0 && precioBolsa > 0) {
    resumen.style.display = 'flex';
    const precioXunit = totalCant > 0 ? precioBolsa / cantBolsa : 0;

    // Precio por unidad principal
    const unitLabel = (unidad === 'unidad' || unidad === 'unidades') ? 'unidad' : unidad;
    document.getElementById('cr-lbl-unit').textContent = 'Precio por ' + unitLabel;
    
    const unitSuffix = (unidad === 'unidad' || unidad === 'unidades') ? 'ud' : unidad;
    document.getElementById('cr-val-unit').textContent = fmtD(precioXunit) + (unitSuffix ? ' / ' + unitSuffix : '');

    // Fila gramo (solo si kg o g)
    const rowGramo = document.getElementById('cr-row-gramo');
    if (UK.includes(unidad)) {
      document.getElementById('cr-lbl-gramo').textContent = 'Precio por gramo';
      document.getElementById('cr-val-gramo').textContent = fmtD(precioXunit / 1000) + ' / g';
      rowGramo.style.display = 'flex';
    } else if (UG.includes(unidad)) {
      document.getElementById('cr-lbl-gramo').textContent = 'Precio por kg (ref.)';
      document.getElementById('cr-val-gramo').textContent = fmtD(precioXunit * 1000) + ' / kg';
      rowGramo.style.display = 'flex';
    } else {
      rowGramo.style.display = 'none';
    }

    document.getElementById('cr-val-total').textContent = fmt(totalPagar);
  } else {
    resumen.style.display = 'none';
  }

  // Advertencia de cantidad baja para unidades pequeñas (g, ml)
  const adv = document.getElementById('advertencia-cantidad');
  const advLbl = document.getElementById('adv-unidad-lbl');
  if (adv && advLbl) {
    if ((unidad === 'g' || unidad === 'ml') && totalCant > 0 && totalCant < 10) {
      advLbl.textContent = unidad;
      adv.style.display = 'block';
    } else {
      adv.style.display = 'none';
    }
  }

  // Actualizar hiddens para PHP
  document.getElementById('inp-cantidad').value   = totalCant;
  document.getElementById('inp-num-bultos').value = numBolsas;
}

function prepararEnvio() {
  recalcular();
}

// Escuchar todos los inputs del formulario
['vis-bultos','vis-cant-bolsa','inp-precio'].forEach(id => {
  document.getElementById(id)?.addEventListener('input', recalcular);
});

// ══ Selección múltiple para etiquetas ════════════════════════
function actualizarSeleccion() {
  const checks = document.querySelectorAll('.row-chk:checked');
  const bar    = document.getElementById('sel-bar');
  const count  = document.getElementById('sel-count');
  const chkAll = document.getElementById('chk-all');
  const total  = document.querySelectorAll('.row-chk').length;

  if (count && bar && chkAll) {
    count.textContent = checks.length;
    bar.classList.toggle('visible', checks.length > 0);
    chkAll.indeterminate = checks.length > 0 && checks.length < total;
    chkAll.checked = checks.length === total;
  }
}

function toggleTodos(master) {
  document.querySelectorAll('.row-chk').forEach(c => c.checked = master.checked);
  actualizarSeleccion();
}

function limpiarSeleccion() {
  document.querySelectorAll('.row-chk').forEach(c => c.checked = false);
  document.getElementById('chk-all').checked = false;
  actualizarSeleccion();
}

function imprimirSeleccionadas() {
  const ids = [...document.querySelectorAll('.row-chk:checked')].map(c => c.value);
  if (!ids.length) return;
  if (ids.length === 1) {
    window.open('etiqueta_lote.php?id_compra=' + ids[0], '_blank');
  } else {
    window.open('etiqueta_lote.php?ids=' + ids.join(','), '_blank');
  }
}
