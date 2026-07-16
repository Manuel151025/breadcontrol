<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Registro — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{
      --bg:hsl(24,60%,6%); --fg:hsl(30,30%,90%); --card:hsla(24,40%,10%,.6);
      --orange:hsl(27,72%,47%); --honey:hsl(30,67%,65%); --cream:hsl(27,63%,76%);
      --muted:hsl(30,20%,45%); --border:rgba(255,255,255,.1); --input-bg:hsla(24,40%,8%,.6);
    }
    body{font-family:'DM Sans',sans-serif; color:var(--fg); background:var(--bg); min-height:100vh; overflow-x:hidden;}
    
    .bg-img{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;}
    .bg-overlay{position:fixed;inset:0;background:hsla(24,60%,6%,.82);z-index:1;}
    
    .glass-header{
      position:fixed;top:0;left:0;right:0;z-index:50; background:hsla(24,40%,10%,.5);
      backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(255,255,255,.08); padding:.75rem 1.5rem;
    }
    .header-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;position:relative;}
    .header-clock{display:flex;flex-direction:column;align-items:flex-start;min-width:200px;}
    .header-clock .time{font-size:.9rem;font-weight:600;letter-spacing:.04em;}
    .header-clock .date{font-size:.7rem;color:rgba(255,255,255,.45);text-transform:capitalize;}

    .header-logo{position:absolute; left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:.65rem;}
    .logo-circle{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--honey));display:flex;align-items:center;justify-content:center;overflow:hidden;}    
    .logo-circle img{width:100%;object-fit:cover;}
    .logo-name{font-family:'Playfair Display',serif;font-weight:700;font-size:.88rem;letter-spacing:.08em;}
    .logo-circle{transition:transform .3s ease, box-shadow .3s ease;}
    .logo-circle img{transition:transform .3s ease;}
    .logo-circle:hover{transform:scale(1.1);box-shadow:0 4px 10px rgba(0,0,0,.3);}
    .logo-circle:hover img{transform:scale(1.15);}

    .header-weather{display:flex;align-items:center;gap:.5rem;min-width:200px;justify-content:flex-end;}
    .weather-icon{font-size:1.25rem;color:var(--honey);}
    .weather-info{display:flex;flex-direction:column;align-items:flex-end;}
    .weather-temp{font-size:.88rem;font-weight:600;}
    .weather-city{font-size:.68rem;color:rgba(255,255,255,.45);}
    
    @media(max-width:768px){
      .glass-header{padding:.6rem 1rem;}
      .header-clock,.header-weather{display:none;}
      .header-inner{justify-content:center;}
    }
    
    .login-wrap{
      position:relative;z-index:10; display:flex;align-items:center;justify-content:center;
      min-height:100vh;padding:6rem 1rem 2rem;
    }
    .glass-card{
      background:var(--card); backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid var(--border); border-radius:1.5rem;
      width:100%;max-width:500px; padding:2.2rem 2.2rem 1.8rem;
      display:flex;flex-direction:column;align-items:center;gap:1.3rem;
      animation:fadeUp .6s ease-out forwards;
    }
    
    .card-title{text-align:center;}
    .card-title h1{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:700;margin-bottom:.2rem;}
    .card-title .greeting{
      font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:600;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    }
    
    .msg-error{
      width:100%;background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);
      border-left:4px solid #dc2626;border-radius:.75rem; padding:.65rem .9rem;font-size:.78rem;color:#fca5a5;
      display:flex;align-items:center;gap:.45rem;line-height:1.4;
    }
    .msg-success{
      width:100%;background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);
      border-left:4px solid #22c55e;border-radius:.75rem; padding:.65rem .9rem;font-size:.78rem;color:#86efac;
      display:flex;align-items:center;gap:.45rem;line-height:1.4;
    }
    
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; width:100%;}
    
    .field{width:100%;}
    .field label{display:block;font-size:.78rem;font-weight:500;color:rgba(255,255,255,.6);margin-bottom:.4rem;}
    .field input, .field select{
      width:100%;height:2.85rem;padding-left:1rem;padding-right:1rem;
      background:var(--input-bg);border:1px solid var(--border);border-radius:.75rem;
      color:var(--fg);font-family:inherit;font-size:.85rem; outline:none;
      transition:border-color .3s,box-shadow .3s;
    }
    .field input:focus, .field select:focus{border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.15);}
    .field select { appearance:none; background-image:url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat:no-repeat; background-position:right 1rem center; background-size:1em; }
    .field select option { background: var(--bg); color: var(--fg); }
    
    .btn-login{
      width:100%;height:2.85rem;border:none;border-radius:2rem;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      color:#fff;font-family:inherit;font-size:.88rem;font-weight:700; cursor:pointer;transition:all .3s;
    }
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 30px hsla(27,72%,47%,.35);}
    
    .link-reg{display:flex;align-items:center;gap:.35rem;color:var(--honey);font-size:.8rem;text-decoration:none;transition:all .2s; margin-top:0.5rem; justify-content:center;}
    .link-reg:hover{text-decoration:underline;}

    .btn-google{
      width:100%;height:2.85rem;border:1px solid rgba(255,255,255,.18);border-radius:2rem;
      background:rgba(255,255,255,.06);color:var(--fg);
      font-family:inherit;font-size:.88rem;font-weight:600;
      cursor:pointer;transition:all .3s;
      display:flex;align-items:center;justify-content:center;gap:.6rem;
      text-decoration:none;
    }
    .btn-google:hover{background:rgba(255,255,255,.12);transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.25);}
    .btn-google svg{flex-shrink:0;}

    .separator{width:100%;display:flex;align-items:center;gap:.75rem;}
    .separator::before,.separator::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.12);}
    .separator span{font-size:.68rem;color:rgba(255,255,255,.35);font-weight:500;text-transform:uppercase;letter-spacing:.08em;}

    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>

<img src="<?= APP_URL ?>/assets/img/bakery-bg.jpg" alt="" class="bg-img">
<div class="bg-overlay"></div>

<header class="glass-header">
  <div class="header-inner">
    <div class="header-clock">
      <span class="time" id="hdr-time">--:--:--</span>
      <span class="date" id="hdr-date"></span>
    </div>

    <div class="header-logo">
      <div class="logo-circle"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo"></div>
      <div class="logo-text"><span class="logo-name">BreadControl</span></div>
    </div>

    <div class="header-weather">
      <i class="bi bi-cloud-sun weather-icon" id="w-icon"></i>
      <div class="weather-info">
        <span class="weather-temp" id="w-temp">--°C</span>
        <span class="weather-city" id="w-city">Florencia, CO</span>
      </div>
    </div>
  </div>
</header>

<div class="login-wrap">
  <div class="glass-card">
    <div class="card-title">
      <h1>Crear Cuenta</h1>
      <p class="greeting">Únete para pedir pan</p>
    </div>

    <?php if ($error): ?>
    <div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$success && $google_client_id): ?>
    <a href="<?= htmlspecialchars($google_url) ?>" class="btn-google">
      <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Registrarme con Google
    </a>
    <div class="separator"><span>o con usuario y contraseña</span></div>
    <?php endif; ?>

    <?php if ($success): ?>
    <div class="msg-success" style="flex-direction:column; padding:2rem; text-align:center; display: flex; align-items: center; justify-content: center; width: 100%;">
        <i class="bi bi-check-circle-fill" style="font-size:3rem; margin-bottom:1rem; color: #22c55e;"></i>
        <div style="font-size:1rem; font-weight:bold;"><?= $success ?></div>
        <a href="index.php" class="btn-login" style="margin-top:1rem; text-decoration:none; display:flex; justify-content:center; align-items:center; max-width:200px;">Ir al Login</a>
    </div>
    <?php else: ?>
    <form method="POST" style="width:100%; display:flex; flex-direction:column; gap:1.3rem;">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        <div class="field">
            <label>Nombre de Tienda o Persona</label>
            <input type="text" name="nombre" placeholder="Ej: Panadería El Buen Sabor" required maxlength="100">
        </div>
        
        <div class="field">
            <label>Teléfono</label>
            <input type="text" name="telefono" placeholder="Opcional" maxlength="15">
        </div>
        
        <div class="grid-2">
            <div class="field">
                <label>Usuario</label>
                <input type="text" name="usuario" placeholder="Para iniciar sesión" required maxlength="50">
            </div>
            <div class="field">
                <label>Contraseña</label>
                <input type="password" name="contrasena" placeholder="Mínimo 4 letras" required minlength="4" maxlength="255">
            </div>
        </div>

        <div class="field" style="display:flex; align-items:center; gap:0.5rem; background: rgba(255,167,38,.1); padding: 0.8rem; border-radius: 8px; border: 1px solid rgba(255,167,38,.3);">
            <input type="checkbox" name="es_aprendiz" id="es_aprendiz" value="1" style="width: auto; height: auto;">
            <label for="es_aprendiz" style="margin-bottom:0; color: var(--honey); font-size: 0.85rem;">Soy aprendiz SENA</label>
        </div>

        <div class="field" id="field_instructor" style="display:none; margin-top: -0.5rem; margin-bottom: 0.5rem;">
            <label style="color: var(--honey);">Selecciona tu Instructor (Tienda ADSO)</label>
            <select name="id_instructor" style="width:100%; height:2.85rem; padding-left:1rem; padding-right:1rem; background:var(--input-bg); border:1px solid var(--border); border-radius:.75rem; color:var(--fg); outline:none;">
                <option value="">-- Selecciona un Instructor --</option>
                <?php foreach ($instructores as $inst): ?>
                    <option value="<?= $inst['id_cliente'] ?>"><?= htmlspecialchars($inst['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <button type="submit" class="btn-login">Registrarme</button>
    </form>
    <?php endif; ?>
    
    <?php if (!$success): ?>
    <a href="index.php" class="link-reg">¿Ya tienes cuenta? Inicia sesión aquí</a>
    <?php endif; ?>
  </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function() {
        const telInput = document.querySelector('input[name="telefono"]');
        if (telInput) {
            telInput.addEventListener('keypress', function(e) {
                if (!/[0-9]/.test(e.key)) e.preventDefault();
            });
            telInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^0-9]/g, '');
                if (this.value.length > 15) this.value = this.value.slice(0, 15);
            });
        }
        
        const nomInput = document.querySelector('input[name="nombre"]');
        if (nomInput) {
            nomInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ\s\.,-]/g, '');
            });
        }
        
        const userInput = document.querySelector('input[name="usuario"]');
        if (userInput) {
            userInput.addEventListener('input', function(e) {
                this.value = this.value.toLowerCase().replace(/[^a-z0-9_]/g, '');
            });
        }

        const esAprendizChk = document.getElementById('es_aprendiz');
        const fieldInstructor = document.getElementById('field_instructor');
        if (esAprendizChk && fieldInstructor) {
            const toggleInstructor = () => {
                fieldInstructor.style.display = esAprendizChk.checked ? 'block' : 'none';
                const selectInst = fieldInstructor.querySelector('select');
                if (selectInst) {
                    selectInst.required = esAprendizChk.checked;
                }
            };
            esAprendizChk.addEventListener('change', toggleInstructor);
            toggleInstructor(); // init
        }
    });

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
  fetch('<?= get_env("API_OPEN_METEO_URL", "https://api.open-meteo.com/v1/forecast") ?>?latitude=1.6144&longitude=-75.6062&current_weather=true&timezone=America/Bogota')
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
</script>
</body>
</html>
