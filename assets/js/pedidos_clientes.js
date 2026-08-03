// assets/js/pedidos_clientes.js — listado de pedidos de clientes del back-office.

function actualizarBulkBar() {
    var checked = document.querySelectorAll('.chk-ped:checked');
    var bar = document.getElementById('bulk-bar');
    document.getElementById('bulk-count').textContent = checked.length;
    bar.classList.toggle('visible', checked.length > 0);
}

document.getElementById('chk-all-ped').addEventListener('change', function() {
    document.querySelectorAll('.chk-ped').forEach(c => c.checked = this.checked);
    actualizarBulkBar();
});
document.querySelectorAll('.chk-ped').forEach(c => c.addEventListener('change', actualizarBulkBar));

function cambiarEstadoLote() {
    var checked = document.querySelectorAll('.chk-ped:checked');
    if (checked.length === 0) { alert('Selecciona al menos un pedido.'); return; }
    var estado  = document.getElementById('select-nuevo-estado').value;
    var labels  = { confirmado: 'CONFIRMAR', pendiente: 'volver a PENDIENTE', rechazado: 'RECHAZAR' };
    if (!confirm('¿' + labels[estado] + ' los ' + checked.length + ' pedido(s) seleccionado(s)?')) return;
    document.getElementById('input-accion').value      = 'cambiar_estado_lote';
    document.getElementById('input-nuevo-estado').value = estado;
    document.getElementById('form-pedidos').submit();
}

function exportar(fmt) {
    var f = document.getElementById('form-pedidos');
    var original = f.action;
    var originalTarget = f.target;
    f.action = 'exportar.php';
    f.target = '_blank';
    // Agregar campo formato temporalmente
    var inp = document.createElement('input');
    inp.type = 'hidden'; inp.name = 'formato'; inp.value = fmt;
    f.appendChild(inp);
    f.submit();
    f.removeChild(inp);
    f.action = original;
    f.target = originalTarget;
}

// ── Acción rápida por fila ──
function quickAction(id, estado) {
    var labels = { confirmado: 'confirmar', rechazado: 'rechazar' };
    if (!confirm('¿' + (labels[estado] || estado) + ' el pedido #' + String(id).padStart(4, '0') + '?')) return;
    document.querySelectorAll('.chk-ped').forEach(function(c) { c.checked = false; });
    var chk = document.querySelector('.chk-ped[value="' + id + '"]');
    if (chk) chk.checked = true;
    document.getElementById('input-accion').value       = 'cambiar_estado_lote';
    document.getElementById('input-nuevo-estado').value = estado;
    document.getElementById('form-pedidos').submit();
}

// ── Modales de cobro ──
function abrirModalCobro(idx) {
    var modal = document.getElementById('modal-cobro-' + idx);
    if (modal) { modal.style.display = 'block'; document.body.style.overflow = 'hidden'; }
}
function cerrarModalCobro(idx) {
    var modal = document.getElementById('modal-cobro-' + idx);
    if (modal) { modal.style.display = 'none'; document.body.style.overflow = ''; }
}
function toggleTodos(idx) {
    var todos = document.getElementById('chk-all-' + idx).checked;
    document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]')
        .forEach(function(c) { c.checked = todos; });
    recalcularTotal(idx);
}
function recalcularTotal(idx) {
    var sum = 0;
    document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]:checked')
        .forEach(function(c) { sum += parseFloat(c.dataset.monto) || 0; });
    document.getElementById('total-cobro-' + idx).textContent =
        'Total: $' + sum.toLocaleString('es-CO', {maximumFractionDigits: 0});
    // Deshabilitar botón si no hay ninguno seleccionado
    var btn = document.getElementById('btn-confirmar-' + idx);
    if (btn) btn.disabled = sum === 0;
    // Actualizar estado del "seleccionar todos"
    var chks = document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]');
    var marcados = document.querySelectorAll('#form-cobro-' + idx + ' input[name="ids_pedidos[]"]:checked');
    var chkAll = document.getElementById('chk-all-' + idx);
    if (chkAll) chkAll.indeterminate = marcados.length > 0 && marcados.length < chks.length;
    if (chkAll) chkAll.checked = marcados.length === chks.length;
}
// Cerrar modal al click en el backdrop
document.querySelectorAll('[id^="modal-cobro-"]').forEach(function(modal) {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.style.display = 'none';
            document.body.style.overflow = '';
        }
    });
});
