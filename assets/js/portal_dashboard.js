// assets/js/portal_dashboard.js — tablero del portal de clientes.
//
// Estaba en un bloque de script incrustado de 165 lineas dentro de
// views/portal/dashboard.php. Se saca aqui para poder retirar 'unsafe-inline'
// de la CSP (punto 22 del anexo).
//
// A diferencia de las extracciones anteriores, esta SI cambia estructura, no
// solo de sitio. El bloque tenia control de flujo de PHP mezclado con el
// JavaScript: tres `if` que decidian, al renderizar, que codigo se emitia
// segun fuera tienda, instructor o hubiera pagos pendientes.
//
// Un archivo estatico no puede llevar esos `if` dentro, asi que la decision se
// mueve del renderizado al navegador. Las funciones se declaran SIEMPRE —una
// funcion que nadie llama no hace nada— y lo que se protege son los cuatro
// enlaces al DOM, comprobando que el elemento exista.
//
// El resultado es mas robusto que el original: antes, si la plantilla dejaba de
// pintar uno de esos elementos, el getElementById devolvia null y la excepcion
// tumbaba el resto del archivo.

var chkTodosDash = document.getElementById('chk-all-dash');
if (chkTodosDash) chkTodosDash.addEventListener('change', function(){
    document.querySelectorAll('.chk-dash').forEach(c => c.checked = this.checked);
    actualizarBulk();
});
document.querySelectorAll('.chk-dash').forEach(c => c.addEventListener('change', actualizarBulk));
function actualizarBulk(){
    var n = document.querySelectorAll('.chk-dash:checked').length;
    document.getElementById('bulk-count-dash').textContent = n;
    document.getElementById('bulk-bar-dash').classList.toggle('visible', n > 0);
}
function exportarDash(fmt){
    var checked = document.querySelectorAll('.chk-dash:checked');
    if(checked.length === 0){ alert('Selecciona al menos un pedido.'); return; }
    document.getElementById('dash-formato').value = fmt;
    document.getElementById('form-dash-export').submit();
}



var buscarAprendiz = document.getElementById('buscar-aprendiz');
if (buscarAprendiz) buscarAprendiz.addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('.fila-aprendiz').forEach(function(row){
        row.style.display = row.dataset.nombre.includes(q) ? '' : 'none';
    });
});


// ── Modal pan ──
// El filtro activo llega por <body data-variedad-filtro>. Cadena vacia = sin filtro.
//
// Se convierte a numero a proposito: antes PHP interpolaba el entero directo
// (`<?= $f_variedad ?: 'null' ?>`), y un atributo data-* siempre devuelve texto.
// Hoy la diferencia seria inocua —el valor solo se asigna al campo oculto del
// formulario, que es texto de todas formas— pero dejar un tipo distinto al del
// original es sembrar una sorpresa para el dia que alguien compare con ===.
var variedadSeleccionada = document.body.dataset.variedadFiltro
    ? Number(document.body.dataset.variedadFiltro)
    : null;

function abrirModalPan() {
    document.getElementById('modal-pan').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(function(){ document.getElementById('modal-buscar').focus(); }, 200);
}

function cerrarModalPan(e) {
    if (e && e.target !== document.getElementById('modal-pan')) return;
    document.getElementById('modal-pan').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') cerrarModalPan();
});

var modalBuscar = document.getElementById('modal-buscar');
if (modalBuscar) modalBuscar.addEventListener('input', function(){
    var q = this.value.toLowerCase().trim();
    var items = document.querySelectorAll('.var-btn');
    var visibles = 0;
    items.forEach(function(btn){
        var match = btn.dataset.nombre.includes(q);
        btn.style.display = match ? '' : 'none';
        if (match) visibles++;
    });
    document.getElementById('no-results-pan').style.display = visibles === 0 ? 'block' : 'none';
});

function seleccionarVariedad(id, e) {
    e.preventDefault();
    document.querySelectorAll('.var-btn').forEach(function(b){ b.classList.remove('selected'); });
    var btn = document.querySelector('.var-btn[data-id="' + id + '"]');
    if (btn) btn.classList.add('selected');
    variedadSeleccionada = id;
    document.getElementById('btn-aplicar').disabled = false;
}

function aplicarVariedad() {
    if (!variedadSeleccionada) return;
    document.getElementById('hdn-variedad').value = variedadSeleccionada;
    document.getElementById('form-filtros').submit();
}

function limpiarVariedad() {
    document.getElementById('hdn-variedad').value = '';
    variedadSeleccionada = null;
    document.getElementById('form-filtros').submit();
}

function toggleAllApprove(chk) {
    document.querySelectorAll('.chk-approve').forEach(function(c) {
        c.checked = chk.checked;
    });
    updateBulkApproveBar();
}

function updateBulkApproveBar() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    var bar = document.getElementById('bulk-approve-bar');
    var countEl = document.getElementById('bulk-approve-count');
    if (countEl) countEl.textContent = checked.length;
    if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
    
    // Hide inline date/time and buttons for checked rows
    document.querySelectorAll('.chk-approve').forEach(function(c) {
        var pedId = c.value;
        var indActions = document.getElementById('ind-actions-' + pedId);
        if (indActions) {
            indActions.style.display = c.checked ? 'none' : 'inline-flex';
        }
    });
}

function submitBulkApprove() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    if (checked.length === 0) return;
    var fecha = document.getElementById('bulk-fecha').value;
    var hora = document.getElementById('bulk-hora').value;
    if (!fecha || !hora) {
        alert('Por favor, ingresa fecha y hora de entrega para el lote.');
        return;
    }
    if (!confirm('¿Aprobar los ' + checked.length + ' pedidos seleccionados con entrega para el ' + fecha + ' a las ' + hora + '?')) {
        return;
    }
    var form = document.getElementById('form-bulk-approve');
    var container = document.getElementById('bulk-approve-ids-container');
    container.innerHTML = '';
    checked.forEach(function(chk) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'aprobar_lote_ids[]'; inp.value = chk.value;
        container.appendChild(inp);
    });
    form.submit();
}

function submitBulkReject() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    if (checked.length === 0) return;
    if (!confirm('¿Rechazar los ' + checked.length + ' pedidos seleccionados?')) {
        return;
    }
    var form = document.getElementById('form-bulk-approve');
    var container = document.getElementById('bulk-approve-ids-container');
    container.innerHTML = '';
    checked.forEach(function(chk) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'rechazar_lote_ids[]'; inp.value = chk.value;
        container.appendChild(inp);
    });
    form.submit();
}


// ── Modal pago instructor ──
function abrirModalPagoInstructor() {
    var m = document.getElementById('modal-pago-instructor');
    if (m) { m.style.display = 'block'; document.body.style.overflow = 'hidden'; }
}
function cerrarModalPagoInstructor() {
    var m = document.getElementById('modal-pago-instructor');
    if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
}
var modalPagoInstr = document.getElementById('modal-pago-instructor');
if (modalPagoInstr) modalPagoInstr.addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPagoInstructor();
});
function toggleTodosInstructor() {
    var todos = document.getElementById('chk-all-instr').checked;
    document.querySelectorAll('.chk-instr').forEach(function(c) { c.checked = todos; });
    recalcularTotalInstructor();
}
function recalcularTotalInstructor() {
    var sum = 0;
    document.querySelectorAll('.chk-instr:checked').forEach(function(c) {
        sum += parseFloat(c.dataset.monto) || 0;
    });
    document.getElementById('total-instr').textContent =
        '$' + sum.toLocaleString('es-CO', {maximumFractionDigits: 0});
    var btn = document.getElementById('btn-ir-nequi');
    if (btn) { btn.style.opacity = sum === 0 ? '.4' : '1'; btn.style.pointerEvents = sum === 0 ? 'none' : ''; }
    var chks = document.querySelectorAll('.chk-instr');
    var marcados = document.querySelectorAll('.chk-instr:checked');
    var chkAll = document.getElementById('chk-all-instr');
    if (chkAll) {
        chkAll.indeterminate = marcados.length > 0 && marcados.length < chks.length;
        chkAll.checked = marcados.length === chks.length;
    }
}
