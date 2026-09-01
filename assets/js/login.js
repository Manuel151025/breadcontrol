// assets/js/login.js — JavaScript de la pantalla de acceso al back-office.
//
// Estaba incrustado en views/auth/login.php. Se mueve aqui sin cambiar la logica:
// mientras exista un bloque de script sin src, la CSP necesita
// 'unsafe-inline' en script-src (punto 22 del anexo).
//
// Los valores que antes interpolaba PHP llegan por atributos data-* del
// <body>, que no son codigo ejecutable y ninguna politica restringe.

// ── Greeting con nombre ──
(function(){
  var h = new Date().getHours();
  var name = (document.body.dataset.nombreSaludo || '');
  var suffix = name ? ', ' + name : '';
  var g, m;
  if (h >= 5 && h < 12) {
    g = '¡Buenos días' + suffix + '!';
    m = 'Un nuevo día de producción comienza. ¡A hornear con pasión!';
  } else if (h >= 12 && h < 18) {
    g = '¡Buenas tardes' + suffix + '!';
    m = 'La tarde es perfecta para revisar las ventas del día.';
  } else {
    g = '¡Buenas noches' + suffix + '!';
    m = 'Un buen cierre de día empieza con una revisión del inventario.';
  }
  document.getElementById('greeting-text').textContent = g;
  document.getElementById('motivation-text').textContent = m;
})();

// ── Clock ──
(function(){
  function tick(){
    var n = new Date();
    document.getElementById('hdr-time').textContent = n.toLocaleTimeString('es-CO',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    document.getElementById('hdr-date').textContent = n.toLocaleDateString('es-CO',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  }
  tick();
  setInterval(tick, 1000);
})();

// ── Eye toggle ──
document.getElementById('eye-toggle').addEventListener('click', function(){
  var inp = document.getElementById('inp-clave');
  var ico = document.getElementById('eye-ico');
  if(inp.type === 'password'){
    inp.type = 'text';
    ico.className = 'bi bi-eye-slash';
  } else {
    inp.type = 'password';
    ico.className = 'bi bi-eye';
  }
});

// ── Loader on submit ──
document.getElementById('login-form').addEventListener('submit', function(){
  document.getElementById('page-loader').classList.add('active');
});
window.addEventListener('pageshow', function(){
  document.getElementById('page-loader').classList.remove('active');
});

// ── Weather (Open-Meteo) ──
(function(){
  var WMO = {0:'bi-sun',1:'bi-cloud-sun',2:'bi-cloud-sun',3:'bi-clouds',
    45:'bi-cloud-fog',48:'bi-cloud-fog',51:'bi-cloud-drizzle',53:'bi-cloud-drizzle',
    55:'bi-cloud-drizzle',61:'bi-cloud-rain',63:'bi-cloud-rain-heavy',65:'bi-cloud-rain-heavy',
    80:'bi-cloud-rain',81:'bi-cloud-rain-heavy',95:'bi-cloud-lightning-rain'};
  fetch(((document.body && document.body.dataset.weatherUrl) || 'https://api.open-meteo.com/v1/forecast') + '?latitude=1.6144&longitude=-75.6062&current_weather=true&timezone=America/Bogota')
    .then(function(r){return r.json()})
    .then(function(d){
      var cw = d.current_weather;
      document.getElementById('w-temp').textContent = Math.round(cw.temperature) + '°C';
      var ico = WMO[cw.weathercode] || 'bi-thermometer-half';
      document.getElementById('w-icon').className = 'bi ' + ico + ' weather-icon';
    }).catch(function(){});
})();

// Validar ingreso de usuario
document.querySelector('input[name="usuario"]').addEventListener('input', function() {
    this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
});
