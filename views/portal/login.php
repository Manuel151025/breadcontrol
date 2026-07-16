<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Acceso Clientes — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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
      .glass-card{padding:1.8rem 1.4rem 1.5rem; width:100%; max-width:400px;}
    }
    @media(max-width:480px){
      .login-wrap{padding:5.5rem 1rem 2rem;}
      .glass-card{padding:1.8rem 1.2rem; border-radius:1.2rem;}
      .card-title h1{font-size:1.4rem;}
      .btn-login{height:3rem; font-size:1rem;}
    }
    @media(max-width:375px){
      .glass-card{padding:1.5rem 1rem;}
      .login-wrap{padding:5rem 0.5rem 2rem;}
    }
    
    .login-wrap{
      position:relative;z-index:10; display:flex;align-items:center;justify-content:center;
      min-height:100vh;padding:6rem 1rem 2rem;
    }
    .glass-card{
      background:var(--card); backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid var(--border); border-radius:1.5rem;
      width:100%;max-width:420px; padding:2.2rem 2.2rem 1.8rem;
      display:flex;flex-direction:column;align-items:center;gap:1.3rem;
      animation:fadeUp .6s ease-out forwards;
    }
    
    .card-logo{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--honey));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 30px hsla(27,72%,47%,.25);overflow:hidden;}    
    .card-logo img{width:100%;height:100%;object-fit:cover;}
    
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
    
    .field{width:100%;}
    .field label{display:block;font-size:.78rem;font-weight:500;color:rgba(255,255,255,.6);margin-bottom:.4rem;}
    .field .inp-wrap{position:relative;}
    .field .inp-icon{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.35);font-size:1rem;}
    .field input{
      width:100%;height:2.85rem;padding-left:2.5rem;padding-right:1rem;
      background:var(--input-bg);border:1px solid var(--border);border-radius:.75rem;
      color:var(--fg);font-family:inherit;font-size:.85rem; outline:none;
      transition:border-color .3s,box-shadow .3s;
    }
    .field input:focus{border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.15);}
    .btn-eye{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.35);font-size:1.1rem;cursor:pointer;padding:.2rem;transition:color .2s;}
    .btn-eye:hover{color:var(--orange);}
    
    .btn-login{
      width:100%;height:2.85rem;border:none;border-radius:2rem;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      color:#fff;font-family:inherit;font-size:.88rem;font-weight:700; cursor:pointer;transition:all .3s;
    }
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 30px hsla(27,72%,47%,.35);}
    
    .link-reg{display:flex;align-items:center;gap:.35rem;color:var(--honey);font-size:.8rem;text-decoration:none;transition:all .2s; margin-top:0.5rem;}
    .link-reg:hover{text-decoration:underline;}

    .btn-admin{
      width:100%;height:2.85rem;border:1px solid var(--honey);border-radius:2rem;
      background:transparent;
      color:var(--honey);font-family:inherit;font-size:.88rem;font-weight:700;
      letter-spacing:.04em;cursor:pointer;transition:all .3s;
      display:flex;align-items:center;justify-content:center;text-decoration:none;
      margin-top:0.5rem;
    }
    .btn-admin:hover{background:hsla(30,67%,65%,.1);transform:translateY(-2px);box-shadow:0 8px 30px hsla(30,67%,65%,.15);}

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

<img src="<?= APP_URL ?>/assets/img/bakery-bg.jpg" alt="" class="bg-img" fetchpriority="high">
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
  <form method="POST" class="glass-card">
    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
    <div class="card-logo"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo"></div>
    <div class="card-title">
      <h1>Iniciar Sesión</h1>
      <p class="greeting">Portal de Pedidos</p>
    </div>

    <?php if ($error): ?>
    <div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($google_client_id): ?>
    <a href="<?= htmlspecialchars($google_url) ?>" class="btn-google">
      <svg width="18" height="18" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
        <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
        <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
        <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
        <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
      </svg>
      Continuar con Google
    </a>
    <div class="separator"><span>o con tu usuario</span></div>
    <?php endif; ?>

    <div class="field">
      <label>Usuario</label>
      <div class="inp-wrap">
        <i class="bi bi-person inp-icon"></i>
        <input type="text" name="usuario" placeholder="Introduce tu usuario" required maxlength="50">
      </div>
    </div>

    <div class="field">
      <label>Contraseña</label>
      <div class="inp-wrap">
        <i class="bi bi-lock inp-icon"></i>
        <input type="password" name="contrasena" id="login-pass" placeholder="Introduce tu contraseña" required maxlength="255">
        <button type="button" class="btn-eye" onclick="togglePass()" tabindex="-1"><i class="bi bi-eye" id="eye-icon"></i></button>
      </div>
    </div>

    <button type="submit" class="btn-login">Ingresar</button>
    <a href="<?= APP_URL ?>/login.php" class="btn-admin">
      <i class="bi bi-shield-lock" style="margin-right: .5rem;"></i> Acceso Administrativo
    </a>
    <a href="recuperar_pass.php" class="link-reg" style="color:rgba(255,255,255,.4);font-size:.73rem;">
      <i class="bi bi-key" style="color:var(--orange);"></i> ¿Olvidaste tu contraseña de usuario? Recupérala aquí
    </a>
    <a href="registro.php" class="link-reg">¿No tienes cuenta? Regístrate rápido aquí</a>
  </form>
</div>

<script>
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
