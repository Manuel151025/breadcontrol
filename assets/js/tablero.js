(function tick() {
  const el = document.getElementById('nc');
  if (el) {
    el.textContent = new Date().toLocaleTimeString('es-CO', { hour:'2-digit', minute:'2-digit' });
  }
  setTimeout(tick, 1000);
})();

(function() {
  const h = new Date().getHours();
  const d = new Date().toLocaleDateString('es-CO', { weekday:'long', day:'numeric', month:'long' });
  let g;

  if (h >= 5 && h < 12)       g = '☀️ Buenos días';
  else if (h >= 12 && h < 18) g = '🌤️ Buenas tardes';
  else                        g = '🌙 Buenas noches';

  const wg = document.getElementById('wg');
  const ws = document.getElementById('ws');
  if (wg) wg.textContent = g;
  if (ws) ws.textContent = d;
})();

const WMO = {
  0:['☀️','Despejado'],1:['🌤️','Parcial'],2:['⛅','Nubes'],
  3:['☁️','Nublado'],45:['🌫️','Niebla'],61:['🌧️','Lluvia'],
  80:['🌦️','Chubascos'],95:['⛈️','Tormenta']
};

const ccity = document.getElementById('ccity');
if (ccity && typeof CIUDADES !== 'undefined' && typeof ciudadActual !== 'undefined') {
  ccity.textContent = CIUDADES[ciudadActual] || '';
}

async function getClima(manual = false) {
  const btn = document.getElementById('bref');
  if (!btn) return;
  
  btn.classList.add('spin');

  if (typeof ciudadActual === 'undefined') return;
  const coords = ciudadActual.split(',');

  try {
    // API_WEATHER_URL must be defined globally in the view
    const url = (typeof API_WEATHER_URL !== 'undefined') ? API_WEATHER_URL : 'https://api.open-meteo.com/v1/forecast';
    
    const r = await fetch(
      `${url}?latitude=${coords[0]}&longitude=${coords[1]}&current_weather=true&timezone=America%2FBogota`
    );

    const data = await r.json();
    const cw = data.current_weather;
    const [icon, desc] = WMO[cw.weathercode] || ['🌡️','Variable'];

    document.getElementById('cico').textContent = icon;
    document.getElementById('ctemp').textContent = Math.round(cw.temperature) + '°C';
    document.getElementById('cdesc').textContent = desc;
    document.getElementById('cupd').textContent =
      'Act. ' + new Date().toLocaleTimeString('es-CO');

  } catch (e) {
    const cdesc = document.getElementById('cdesc');
    if (cdesc) cdesc.textContent = 'Sin conexión';
  }

  btn.classList.remove('spin');
}

/* Callback desde modal ciudad (header.php) */
window.ciudadCambiada = function(coord, nombre) {
  ciudadActual = coord;
  const ccity = document.getElementById('ccity');
  if (ccity) ccity.textContent = nombre;
  getClima(true);
};

getClima();
setInterval(getClima, 600000);
