window.onMontoChange = function() {
    var inp = document.getElementById('inp-monto-recibido');
    if (!inp) return;
    var recibido  = parseFloat(inp.value) || 0;
    var esperado  = parseFloat(inp.dataset.esperado) || 0;
    var difiere   = Math.abs(recibido - esperado) > 1;
    var aviso     = document.getElementById('aviso-diferencia');
    var grupoNota = document.getElementById('grupo-nota');
    var lblNota   = document.getElementById('lbl-nota');
    if (aviso)     aviso.style.display     = difiere ? 'block' : 'none';
    if (grupoNota) grupoNota.classList.toggle('nota-requerida', difiere);
    if (lblNota)   lblNota.textContent = difiere ? 'Nota (obligatoria — explica la diferencia)' : 'Nota';
};

window.validarFormPago = function() {
    var inp = document.getElementById('inp-monto-recibido');
    if (!inp) return true;
    var recibido = parseFloat(inp.value) || 0;
    if (recibido <= 0) {
        alert('Ingresa el monto recibido antes de confirmar.');
        inp.focus();
        return false;
    }
    var esperado = parseFloat(inp.dataset.esperado) || 0;
    if (Math.abs(recibido - esperado) > 1) {
        var nota = (document.getElementById('inp-nota') || {}).value || '';
        if (!nota.trim()) {
            alert('El monto difiere del esperado. Por favor agrega una nota explicando la diferencia.');
            var n = document.getElementById('inp-nota');
            if (n) n.focus();
            return false;
        }
    }
    return true;
};
