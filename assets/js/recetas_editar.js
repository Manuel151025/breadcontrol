// assets/js/recetas_editar.js — editor de recetas (ingredientes, filas, modal).
// Requiere: insumosData y contadorFilas definidos inline por la vista.


// Íconos por tipo de insumo (por nombre clave)
function getIco(nombre) {
    const n = nombre.toLowerCase();
    if (n.includes('harina'))    return '🌾';
    if (n.includes('azuc'))      return '🍬';
    if (n.includes('sal'))       return '🧂';
    if (n.includes('levadura'))  return '🧫';
    if (n.includes('huevo'))     return '🥚';
    if (n.includes('leche'))     return '🥛';
    if (n.includes('mantequi'))  return '🧈';
    if (n.includes('aceite'))    return '🫙';
    if (n.includes('esencia'))   return '💧';
    if (n.includes('manjar') || n.includes('arequipe')) return '🍯';
    return '🥄';
}

function getLabelUnidad(und) {
    if (und === 'kg' || und === 'L') return 'g';
    if (und === 'unidad') return 'unid.';
    return und;
}

// ── Referencia a la fila que abrió el modal ──
let filaActiva = null;

function abrirModal(btn) {
    filaActiva = btn.closest('tr');
    const idActual = filaActiva.dataset.id;

    // IDs ya usados en OTRAS filas
    const usados = [...document.querySelectorAll('.fila-ing')]
        .filter(tr => tr !== filaActiva)
        .map(tr => tr.dataset.id)
        .filter(Boolean);

    // Construir cards
    renderCards(insumosData, usados, idActual);
    document.getElementById('modal-buscar').value = '';
    document.getElementById('modal-picker').style.display = 'flex';
    setTimeout(() => document.getElementById('modal-buscar').focus(), 120);
}

function renderCards(lista, usados, idActual, filtro = '') {
    const grid = document.getElementById('modal-grid');
    const empty = document.getElementById('modal-empty');
    const q = filtro.toLowerCase().trim();
    const filtrados = lista.filter(i => !q || i.nombre.toLowerCase().includes(q));

    grid.innerHTML = '';
    if (filtrados.length === 0) {
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';

    filtrados.forEach(ins => {
        const card = document.createElement('div');
        card.className = 'ins-card'
            + (usados.includes(String(ins.id)) ? ' usado' : '')
            + (String(ins.id) === String(idActual) ? ' activo' : '');
        card.dataset.id = ins.id;

        const lbl = getLabelUnidad(ins.unidad);
        card.innerHTML = `
            <div class="ins-card-ico">${getIco(ins.nombre)}</div>
            <div class="ins-card-nombre">${ins.nombre}</div>
            <div class="ins-card-meta">
                <span class="ins-card-tag und">${lbl}</span>
                ${ins.harina ? '<span class="ins-card-tag har">🌾 harina</span>' : ''}
            </div>`;
        card.onclick = () => seleccionarInsumo(ins);
        grid.appendChild(card);
    });
}

function filtrarCards(q) {
    const idActual = filaActiva ? filaActiva.dataset.id : '';
    const usados = [...document.querySelectorAll('.fila-ing')]
        .filter(tr => tr !== filaActiva)
        .map(tr => tr.dataset.id).filter(Boolean);
    renderCards(insumosData, usados, idActual, q);
}

function seleccionarInsumo(ins) {
    if (!filaActiva) return;

    const lbl = getLabelUnidad(ins.unidad);
    const btn = filaActiva.querySelector('.ing-picker-btn');
    const hid = filaActiva.querySelector('.hid-id');
    const lblUnidad = filaActiva.querySelector('.lbl-unidad');

    // Actualizar el hidden input
    hid.value = ins.id;
    filaActiva.dataset.id = ins.id;

    // Actualizar el botón visual
    btn.className = 'ing-picker-btn seleccionado';
    btn.innerHTML = `
        <i class="bi bi-bag-fill picker-ico" style="color:var(--c3)"></i>
        <span class="picker-nombre">${ins.nombre}</span>
        ${ins.harina ? '<span class="picker-tag tag-harina">🌾 harina</span>' : ''}
        <span class="picker-tag tag-unidad">${lbl}</span>
        <i class="bi bi-chevron-down" style="font-size:.65rem;opacity:.4;margin-left:auto"></i>`;
    btn.onclick = () => abrirModal(btn);

    // Actualizar etiqueta de unidad en la fila
    if (lblUnidad) lblUnidad.textContent = lbl;

    cerrarModal();
    actualizarCount();
}

function cerrarModal() {
    document.getElementById('modal-picker').style.display = 'none';
    filaActiva = null;
}

function cerrarModalFuera(e) {
    if (e.target === document.getElementById('modal-picker')) cerrarModal();
}

// Cerrar con Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') cerrarModal();
});


function agregarFila() {
    const usados = [...document.querySelectorAll('.fila-ing')]
        .map(tr => tr.dataset.id).filter(Boolean);

    const tr = document.createElement('tr');
    tr.className = 'fila-ing';
    tr.dataset.id = '';
    tr.innerHTML = `
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
        <td><button type="button" class="btn-del-row" onclick="eliminarFila(this)"><i class="bi bi-trash3"></i></button></td>`;
    document.getElementById('tbody-ing').appendChild(tr);
    actualizarCount();
    // Abrir el modal automáticamente en la nueva fila
    abrirModal(tr.querySelector('.ing-picker-btn'));
}

function eliminarFila(btn) {
    const filas = document.querySelectorAll('.fila-ing');
    if (filas.length <= 1) { alert('Debe haber al menos un ingrediente.'); return; }
    btn.closest('tr').remove();
    actualizarCount();
}

function actualizarCount() {
    const n = document.querySelectorAll('.fila-ing').length;
    document.getElementById('badge-count').textContent = n + ' ingrediente' + (n!==1?'s':'');
}
