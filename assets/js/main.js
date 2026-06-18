/* ── Reloj ── */
(function tick(){
  var clockEl = document.getElementById('nc');
  if (clockEl) {
    clockEl.textContent = new Date().toLocaleTimeString('es-CO', {hour:'2-digit', minute:'2-digit'});
  }
  setTimeout(tick, 1000);
})();

/* ── Ciudades ── */
var CIUDADES = {
  '1.6144,-75.6062':'Caquetá, Florencia',
  '4.6097,-74.0817':'Cundinamarca, Bogotá',
  '6.2518,-75.5636':'Antioquia, Medellín',
  '3.4516,-76.5320':'Valle del Cauca, Cali',
  '10.9685,-74.7813':'Atlántico, Barranquilla',
  '10.2508,-75.3217':'Bolívar, Cartagena',
  '2.9273,-75.2819':'Huila, Neiva',
  '7.1193,-73.1227':'Santander, Bucaramanga',
  '4.4389,-75.2322':'Tolima, Ibagué',
  '4.8133,-75.6961':'Risaralda, Pereira',
  '4.5339,-75.6811':'Quindío, Armenia',
  '5.0689,-75.5174':'Caldas, Manizales',
  '5.5353,-73.3678':'Boyacá, Tunja',
  '11.2404,-74.1990':'Magdalena, Santa Marta',
  '10.4631,-73.2532':'Cesar, Valledupar',
  '1.2136,-77.2811':'Nariño, Pasto',
  '7.8939,-72.5078':'Norte de Santander, Cúcuta',
  '8.7500,-75.8833':'Córdoba, Montería',
  '9.3047,-75.3978':'Sucre, Sincelejo',
  '11.5444,-72.9072':'La Guajira, Riohacha',
  '5.6922,-76.6581':'Chocó, Quibdó',
  '4.1420,-73.6266':'Meta, Villavicencio',
  '7.0805,-70.7602':'Arauca, Arauca',
  '5.3080,-72.4121':'Casanare, Yopal',
  '2.4419,-76.6063':'Cauca, Popayán',
  '1.1472,-76.6464':'Putumayo, Mocoa',
  '4.1227,-69.5642':'Amazonas, Leticia',
  '3.8653,-67.9239':'Guainía, Inírida',
  '2.5683,-72.6417':'Guaviare, San José del Guaviare',
  '1.1983,-70.1733':'Vaupés, Mitú',
  '6.1890,-67.4850':'Vichada, Puerto Carreño',
  '12.5847,-81.7006':'San Andrés'
};

/* Leer ciudad guardada */
var ciudadActual = localStorage.getItem('pan_ciudad') || '1.6144,-75.6062';
(function(){
  var nombre = CIUDADES[ciudadActual] || 'Florencia';
  var lbl = document.getElementById('ciudad-lbl');
  if (lbl) lbl.textContent = nombre.split(',')[1] ? nombre.split(',')[1].trim() : nombre;
})();

function renderListaCiudades(filtro) {
  var lista = document.getElementById('ciudad-lista');
  if (!lista) return;
  lista.innerHTML = '';
  var q = (filtro || '').toLowerCase();
  Object.keys(CIUDADES).forEach(function(coord) {
    var nombre = CIUDADES[coord];
    if (q && nombre.toLowerCase().indexOf(q) === -1) return;
    var div = document.createElement('div');
    div.className = 'modal-ciudad-item' + (coord === ciudadActual ? ' activa' : '');
    div.innerHTML = '<i class="bi bi-' + (coord === ciudadActual ? 'geo-alt-fill' : 'geo-alt') + '"></i>' + nombre;
    div.onclick = function() {
      ciudadActual = coord;
      localStorage.setItem('pan_ciudad', coord);
      var nombreCorto = nombre.split(',')[1] ? nombre.split(',')[1].trim() : nombre;
      var lbl = document.getElementById('ciudad-lbl');
      if (lbl) lbl.textContent = nombreCorto;
      cerrarModalCiudad();
      if (typeof window.ciudadCambiada === 'function') window.ciudadCambiada(coord, nombre);
    };
    lista.appendChild(div);
  });
}

window.filtrarCiudades = function(q) { renderListaCiudades(q); };

window.abrirModalCiudad = function() {
  var input = document.getElementById('ciudad-buscar');
  if (input) input.value = '';
  renderListaCiudades('');
  var modal = document.getElementById('modal-ciudad');
  if (modal) modal.classList.add('open');
  document.body.style.overflow = 'hidden';
  setTimeout(function(){ 
    var input = document.getElementById('ciudad-buscar');
    if (input) input.focus(); 
  }, 100);
};

window.cerrarModalCiudad = function() {
  var modal = document.getElementById('modal-ciudad');
  if (modal) modal.classList.remove('open');
  document.body.style.overflow = '';
};

document.addEventListener('DOMContentLoaded', function() {
  var modal = document.getElementById('modal-ciudad');
  if (modal) {
    modal.addEventListener('click', function(e){
      if (e.target === this) window.cerrarModalCiudad();
    });
  }
});

/* ── Hamburguesa ── */
(function(){
  document.addEventListener('DOMContentLoaded', function() {
    var btn  = document.getElementById('n-ham');
    var menu = document.getElementById('n-menu');
    var nav  = document.getElementById('main-nav');
    var ico  = document.getElementById('ham-ico');
    if (!btn || !menu || !nav || !ico) return;

    btn.addEventListener('click', function(){
      var open = menu.classList.toggle('open');
      nav.classList.toggle('nav-open', open);
      ico.className = open ? 'bi bi-x-lg' : 'bi bi-list';
    });

    menu.querySelectorAll('.n-item').forEach(function(a){
      a.addEventListener('click', function(){
        menu.classList.remove('open');
        nav.classList.remove('nav-open');
        ico.className = 'bi bi-list';
      });
    });
  });
})();

/* ── AUTO-LOGOUT 60s inactividad ── */
(function(){
  var timeout;
  var LIMIT = 360; // 6 minutos
  function resetTimer(){
    clearTimeout(timeout);
    timeout = setTimeout(function(){
      var logoutUrl = '/logout.php';
      if (window.BREADCONTROL_CONFIG && window.BREADCONTROL_CONFIG.logoutUrl) {
        logoutUrl = window.BREADCONTROL_CONFIG.logoutUrl;
      }
      window.location.href = logoutUrl;
    }, LIMIT * 1000);
  }
  ['mousemove','keydown','click','scroll','touchstart'].forEach(function(ev){
    document.addEventListener(ev, resetTimer, {passive:true});
  });
  resetTimer();
})();

/* ── Validaciones Globales de Inputs (Propietario) ── */
document.addEventListener("DOMContentLoaded", function() {
  document.querySelectorAll('input[type="number"]').forEach(function(inp) {
    inp.addEventListener('keypress', function(e) {
      var step = this.getAttribute('step');
      var isDecimal = step && step.indexOf('.') !== -1;
      if (isDecimal) {
        if (!/[0-9.,]/.test(e.key)) e.preventDefault();
      } else {
        if (!/[0-9]/.test(e.key)) e.preventDefault();
      }
    });
    
    inp.addEventListener('input', function(e) {
      var step = this.getAttribute('step');
      var isDecimal = step && step.indexOf('.') !== -1;
      if (!isDecimal) {
        this.value = this.value.replace(/[^0-9]/g, '');
      } else {
        this.value = this.value.replace(/[^0-9.,]/g, '');
      }
      var maxl = this.getAttribute('maxlength') || 10; 
      if (this.value.length > maxl) this.value = this.value.slice(0, maxl);
      
      var maxVal = this.getAttribute('max');
      if (maxVal && parseFloat(this.value) > parseFloat(maxVal)) {
        this.value = maxVal;
      }
    });
  });

  document.querySelectorAll('input[name*="telefono"], input[name*="celular"]').forEach(function(inp) {
    if (!inp.hasAttribute('maxlength')) inp.setAttribute('maxlength', '15');
    inp.addEventListener('keypress', function(e) {
      if (!/[0-9]/.test(e.key)) e.preventDefault();
    });
    inp.addEventListener('input', function(e) {
      this.value = this.value.replace(/[^0-9]/g, '');
    });
  });

  document.querySelectorAll('input[type="text"]:not([maxlength])').forEach(function(inp) {
    inp.setAttribute('maxlength', '150');
  });

  // Notificaciones flotantes auto-descartables (.msg-ok, .msg-err)
  document.querySelectorAll('.msg-ok, .msg-err').forEach(function(alert) {
    document.body.appendChild(alert);

    if (!alert.querySelector('.alert-close-btn')) {
      var closeBtn = document.createElement('button');
      closeBtn.className = 'alert-close-btn';
      closeBtn.innerHTML = '&times;';
      closeBtn.title = 'Cerrar';
      closeBtn.onclick = function(e) {
        e.preventDefault();
        alert.style.transition = 'opacity 0.35s ease, transform 0.35s ease, bottom 0.35s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(15px) scale(0.92)';
        setTimeout(function() { alert.remove(); }, 350);
      };
      alert.appendChild(closeBtn);
    }

    setTimeout(function() {
      if (alert.parentNode) {
        alert.style.transition = 'opacity 0.35s ease, transform 0.35s ease, bottom 0.35s ease';
        alert.style.opacity = '0';
        alert.style.transform = 'translateY(15px) scale(0.92)';
        setTimeout(function() { alert.remove(); }, 350);
      }
    }, 6000);
  });
});
