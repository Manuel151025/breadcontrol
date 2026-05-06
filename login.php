<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/sesion.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . APP_URL . '/modules/tablero/index.php');
    exit;
}

$error = '';

// Obtener nombre del usuario para saludo personalizado
$nombre_saludo = '';
try {
    $pdo_s = getConexion();
    $row_s = $pdo_s->query("SELECT nombre_completo FROM usuario WHERE activo=1 ORDER BY id_usuario LIMIT 1")->fetch();
    $nombre_saludo = explode(' ', trim($row_s['nombre_completo'] ?? ''))[0];
} catch(Exception $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave   = $_POST['clave'] ?? '';
    if (empty($usuario) || empty($clave)) {
        $error = 'Por favor ingresa tu usuario y contraseña.';
    } else {
        if (iniciarSesion($usuario, $clave)) {
            header('Location: ' . APP_URL . '/modules/tablero/index.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>BreadControl — Acceso</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}

    :root{
      --bg:hsl(24,60%,6%);
      --fg:hsl(30,30%,90%);
      --card:hsla(24,40%,10%,.6);
      --orange:hsl(27,72%,47%);
      --honey:hsl(30,67%,65%);
      --cream:hsl(27,63%,76%);
      --muted:hsl(30,20%,45%);
      --border:rgba(255,255,255,.1);
      --input-bg:hsla(24,40%,8%,.6);
    }

    body{
      font-family:'DM Sans',sans-serif;
      color:var(--fg);
      background:var(--bg);
      min-height:100vh;
      overflow-x:hidden;
    }

    /* ── BG ── */
    .bg-img{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;}
    .bg-overlay{position:fixed;inset:0;background:hsla(24,60%,6%,.82);z-index:1;}

    /* ── GLASS HEADER ── */
    .glass-header{
      position:fixed;top:0;left:0;right:0;z-index:50;
      background:hsla(24,40%,10%,.5);
      backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);
      border-bottom:1px solid rgba(255,255,255,.08);
      padding:.75rem 1.5rem;
    }
    .header-inner{max-width:1200px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;position:relative;}
    .header-clock{display:flex;flex-direction:column;align-items:flex-start;min-width:200px;}
    .header-clock .time{font-size:.9rem;font-weight:600;letter-spacing:.04em;}
    .header-clock .date{font-size:.7rem;color:rgba(255,255,255,.45);text-transform:capitalize;}

    .header-logo{position:absolute; left:50%;transform:translateX(-50%);display:flex;align-items:center;gap:.65rem;}
    .logo-circle{width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--honey));display:flex;align-items:center;justify-content:center;overflow:hidden;}    
    .logo-circle span{font-family:'Playfair Display',serif;font-weight:700;font-size:.95rem;color:#fff;}
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

    /* ── BACK ARROW ── */
    .back-landing{
      position:fixed;top:4.5rem;left:1.2rem;z-index:55;
      background:hsla(24,40%,10%,.5);backdrop-filter:blur(12px);
      border:1px solid rgba(255,255,255,.1);border-radius:50%;
      width:40px;height:40px;display:flex;align-items:center;justify-content:center;
      color:var(--honey);font-size:1.1rem;text-decoration:none;transition:all .3s;
    }
    .back-landing:hover{background:hsla(24,40%,10%,.75);transform:scale(1.08);}

    /* ── LOGIN CARD ── */
    .login-wrap{
      position:relative;z-index:10;
      display:flex;align-items:center;justify-content:center;
      min-height:100vh;padding:6rem 1rem 2rem;
    }
    .glass-card{
      background:var(--card);
      backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);
      border:1px solid var(--border);
      border-radius:1.5rem;
      width:100%;max-width:420px;
      padding:2.2rem 2.2rem 1.8rem;
      display:flex;flex-direction:column;align-items:center;gap:1.3rem;
      animation:fadeUp .6s ease-out forwards;
    }

    /* ── LOGO CARD ── */
    .card-logo{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--orange),var(--honey));display:flex;align-items:center;justify-content:center;box-shadow:0 8px 30px hsla(27,72%,47%,.25);overflow:hidden;}    
    .card-logo img{width:100%;height:100%;object-fit:cover;}
    
    /* ── TITLE ── */
    .card-title{text-align:center;}
    .card-title h1{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:700;margin-bottom:.2rem;}
    .card-title .greeting{
      font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:600;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      -webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;
    }
    .card-title .motivation{font-size:.78rem;color:var(--muted);margin-top:.25rem;line-height:1.4;}

    /* ── ERROR ── */
    .msg-error{
      width:100%;background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);
      border-left:4px solid #dc2626;border-radius:.75rem;
      padding:.65rem .9rem;font-size:.78rem;color:#fca5a5;
      display:flex;align-items:center;gap:.45rem;line-height:1.4;
    }
    .msg-error i{flex-shrink:0;font-size:.9rem;}

    /* ── INPUTS ── */
    .field{width:100%;}
    .field label{display:block;font-size:.78rem;font-weight:500;color:rgba(255,255,255,.6);margin-bottom:.4rem;}
    .field .inp-wrap{position:relative;}
    .field .inp-icon{position:absolute;left:.75rem;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.35);font-size:1rem;}
    .field input{
      width:100%;height:2.85rem;padding-left:2.5rem;padding-right:2.8rem;
      background:var(--input-bg);border:1px solid var(--border);border-radius:.75rem;
      color:var(--fg);font-family:inherit;font-size:.85rem;
      transition:border-color .3s,box-shadow .3s;outline:none;
    }
    .field input:focus{border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.15);}
    .field input::placeholder{color:var(--muted);}
    .eye-btn{
      position:absolute;right:.75rem;top:50%;transform:translateY(-50%);
      background:none;border:none;color:rgba(255,255,255,.35);font-size:1.05rem;
      cursor:pointer;transition:color .2s;padding:0;
    }
    .eye-btn:hover{color:rgba(255,255,255,.65);}

    /* ── SUBMIT ── */
    .btn-login{
      width:100%;height:2.85rem;border:none;border-radius:2rem;
      background:linear-gradient(135deg,var(--orange),var(--honey));
      color:#fff;font-family:inherit;font-size:.88rem;font-weight:700;
      letter-spacing:.04em;cursor:pointer;transition:all .3s;
    }
    .btn-login:hover{transform:translateY(-2px);box-shadow:0 8px 30px hsla(27,72%,47%,.35);}

    /* ── LINKS ── */
    .link-pin{display:flex;align-items:center;gap:.35rem;color:var(--honey);font-size:.8rem;text-decoration:none;transition:all .2s;}
    .link-pin:hover{text-decoration:underline;}

    /* ── FOOTER DOT ── */
    .card-footer{display:flex;align-items:center;gap:.45rem;font-size:.65rem;color:rgba(255,255,255,.28);margin-top:.3rem;}
    .pulse-dot{width:7px;height:7px;border-radius:50%;background:#22c55e;animation:pulse 2s ease-in-out infinite;}
    @keyframes pulse{0%,100%{opacity:.5;transform:scale(.85)}50%{opacity:1;transform:scale(1)}}

    /* ── LOADER ── */
    .loader-overlay{position:fixed;inset:0;z-index:9999;background:hsla(24,60%,6%,.95);backdrop-filter:blur(8px);display:flex;flex-direction:column;align-items:center;justify-content:center;opacity:0;pointer-events:none;transition:opacity .35s ease;}
    .loader-overlay.active{opacity:1;pointer-events:all;}
    .loader-dots{display:flex;gap:.6rem;margin-bottom:1rem;}
    .loader-dot{width:26px;height:26px;border-radius:50%;background:var(--honey);animation:dotPulse 1.4s ease-in-out infinite;}
    .loader-dot:nth-child(2){animation-delay:.2s;background:var(--orange);}
    .loader-dot:nth-child(3){animation-delay:.4s;background:hsl(24,50%,35%);}
    .loader-txt{font-size:.7rem;font-weight:700;color:rgba(255,255,255,.35);letter-spacing:.2em;text-transform:uppercase;}
    @keyframes dotPulse{0%,100%{transform:scale(.55);opacity:.35}50%{transform:scale(1);opacity:1}}

    /* ── ANIMATION ── */
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}

    /* ── RESPONSIVE ── */
    @media(max-width:768px){
      .glass-header{padding:.6rem 1rem;}
      .header-clock,.header-weather{display:none;}
      .header-inner{justify-content:center;}
      .glass-card{padding:1.8rem 1.4rem 1.5rem;}
      .back-landing{top:4rem;left:.8rem;width:36px;height:36px;font-size:1rem;}
    }
    @media(max-width:420px){
      .glass-card{padding:1.5rem 1.1rem 1.3rem;border-radius:1.2rem;}
      .card-title h1{font-size:1.3rem;}
      .card-title .greeting{font-size:.95rem;}
    }
  
  /* ── MANUAL BUTTON ── */
  .btn-manual{
    position:fixed;bottom:1.5rem;right:1.5rem;z-index:90;
    width:52px;height:52px;border-radius:50%;
    background:linear-gradient(135deg,hsl(27,72%,47%),hsl(30,67%,65%));
    border:none;box-shadow:0 4px 20px rgba(148,91,53,.4);
    display:flex;align-items:center;justify-content:center;
    color:#fff;font-size:1.35rem;cursor:pointer;
    text-decoration:none;transition:all .3s ease;
  }
  .btn-manual:hover{transform:translateY(-3px) scale(1.05);box-shadow:0 8px 30px rgba(148,91,53,.5);}
  .btn-manual .manual-tooltip{
    position:absolute;right:62px;top:50%;transform:translateY(-50%);
    background:hsla(24,40%,10%,.9);backdrop-filter:blur(10px);
    color:#fff;padding:.4rem .75rem;border-radius:8px;
    font-size:.72rem;font-weight:600;white-space:nowrap;
    opacity:0;pointer-events:none;transition:opacity .25s;
    font-family:'DM Sans',sans-serif;
  }
  .btn-manual:hover .manual-tooltip{opacity:1;}
  @media(max-width:768px){
    .btn-manual{width:44px;height:44px;bottom:1rem;right:1rem;font-size:1.15rem;}
    .btn-manual .manual-tooltip{display:none;}
  }
</style>
</head>
<body>

<!-- Background -->
<img src="<?= APP_URL ?>/assets/img/bakery-bg.jpg" alt="" class="bg-img">
<div class="bg-overlay"></div>

<!-- Loader -->
<div class="loader-overlay" id="page-loader">
  <div class="loader-dots">
    <div class="loader-dot"></div>
    <div class="loader-dot"></div>
    <div class="loader-dot"></div>
  </div>
  <div class="loader-txt">Iniciando sesión...</div>
</div>

<!-- Glass Header -->
<header class="glass-header">
  <div class="header-inner">

    <div class="header-clock">
      <span class="time" id="hdr-time">--:--:--</span>
      <span class="date" id="hdr-date"></span>
    </div>

    <div class="header-logo">
      <div class="logo-circle">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo">
      </div>
      <div class="logo-text">
        <span class="logo-name">BreadControl</span>
      </div>
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
<!-- Back to landing -->
<a href="<?= APP_URL ?>/" class="back-landing" title="Volver al inicio">
  <i class="bi bi-arrow-left"></i>
</a>

<!-- Login -->
<div class="login-wrap">
  <form method="POST" class="glass-card" id="login-form">

<div class="card-logo">
  <img src="<?= APP_URL ?>/assets/img/logo.png" alt="Logo">
</div>
    <div class="card-title">
      <h1>Iniciar Sesión</h1>
      <p class="greeting" id="greeting-text"></p>
      <p class="motivation" id="motivation-text"></p>
    </div>

    <?php if ($error): ?>
    <div class="msg-error">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="field">
      <label>Usuario</label>
      <div class="inp-wrap">
        <i class="bi bi-person inp-icon"></i>
        <input type="text" name="usuario" placeholder="Introduce tu usuario" required
               value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>" autocomplete="username">
      </div>
    </div>

    <div class="field">
      <label>Contraseña</label>
      <div class="inp-wrap">
        <i class="bi bi-lock inp-icon"></i>
        <input type="password" name="clave" id="inp-clave" placeholder="Introduce tu contraseña"
               required autocomplete="current-password">
        <button type="button" class="eye-btn" id="eye-toggle" tabindex="-1">
          <i class="bi bi-eye" id="eye-ico"></i>
        </button>
      </div>
    </div>

    <button type="submit" class="btn-login">Ingresar</button>

    <a href="<?= APP_URL ?>/recuperar_pin.php" class="link-pin">
      <i class="bi bi-key"></i> ¿Olvidaste tu contraseña?
    </a>

    <div class="card-footer">
      <span class="pulse-dot"></span>
      <span>Solo personal autorizado · Florencia, Caquetá · <?= date('Y') ?></span>
    </div>
  </form>
</div>

<script>
// ── Greeting con nombre ──
(function(){
  var h = new Date().getHours();
  var name = '<?= htmlspecialchars($nombre_saludo) ?>';
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
  fetch('https://api.open-meteo.com/v1/forecast?latitude=1.6144&longitude=-75.6062&current_weather=true&timezone=America/Bogota')
    .then(function(r){return r.json()})
    .then(function(d){
      var cw = d.current_weather;
      document.getElementById('w-temp').textContent = Math.round(cw.temperature) + '°C';
      var ico = WMO[cw.weathercode] || 'bi-thermometer-half';
      document.getElementById('w-icon').className = 'bi ' + ico + ' weather-icon';
    }).catch(function(){});
})();
</script>

<!-- Manual de Usuario -->
<a href="<?= APP_URL ?>/assets/docs/Manual_BreadControl.pdf" target="_blank" class="btn-manual" title="Manual de Usuario">
  <i class="bi bi-book-half"></i>
  <span class="manual-tooltip">Manual de Usuario</span>
</a>

</body>
</html>
