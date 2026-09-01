// assets/js/portal_login.js — JavaScript de la pantalla de acceso del portal de clientes.
//
// Estaba incrustado en views/portal/login.php. Se mueve aqui sin cambiar la logica:
// mientras exista un bloque de script sin src, la CSP necesita
// 'unsafe-inline' en script-src (punto 22 del anexo).
//
// Los valores que antes interpolaba PHP llegan por atributos data-* del
// <body>, que no son codigo ejecutable y ninguna politica restringe.

function togglePass() {
    var inp = document.getElementById('login-pass');
    var icon = document.getElementById('eye-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// ── Clock ──
(function(){
  function tick(){
    var n = new Date();
    var timeEl = document.getElementById('hdr-time');
    var dateEl = document.getElementById('hdr-date');
    if(timeEl) timeEl.textContent = n.toLocaleTimeString('es-CO',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    if(dateEl) dateEl.textContent = n.toLocaleDateString('es-CO',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  }
  tick();
  setInterval(tick, 1000);
})();

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
      var wTemp = document.getElementById('w-temp');
      var wIcon = document.getElementById('w-icon');
      if(wTemp) wTemp.textContent = Math.round(cw.temperature) + '°C';
      var ico = WMO[cw.weathercode] || 'bi-thermometer-half';
      if(wIcon) wIcon.className = 'bi ' + ico + ' weather-icon';
    }).catch(function(){});
})();
