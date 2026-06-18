<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Recuperar contrasena — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@500;600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{--bg-dark:hsl(25,60%,5%);--card-bg:hsl(25,30%,12%);--muted-bg:hsl(25,20%,15%);--border:hsl(25,25%,20%);--fg:hsl(30,40%,90%);--fg-muted:hsl(30,20%,60%);--naranja:hsl(27,72%,47%);--miel:hsl(30,65%,65%);--crema:hsl(28,60%,76%);--gradient-warm:linear-gradient(135deg,hsl(27,72%,47%),hsl(30,65%,65%));--shadow-warm:0 8px 32px -8px hsla(27,72%,47%,.3);--shadow-glow:0 0 40px -10px hsla(30,65%,65%,.4);}
    body{font-family:'DM Sans',sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden;background:var(--bg-dark);}
    .bg-img{position:fixed;inset:0;z-index:0;}.bg-img img{width:100%;height:100%;object-fit:cover;}
    .bg-overlay{position:fixed;inset:0;z-index:1;background:linear-gradient(180deg,hsla(25,60%,5%,.75) 0%,hsla(25,60%,5%,.5) 50%,hsla(25,60%,5%,.85) 100%);}
    .bg-overlay2{position:fixed;inset:0;z-index:2;background:hsla(25,60%,6%,.55);}
    .logo{position:fixed;top:1.5rem;left:1.5rem;z-index:20;display:flex;align-items:center;gap:.7rem;animation:fadeIn .6s ease;}
    .logo-circle{width:42px;height:42px;border-radius:50%;background:var(--gradient-warm);display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-warm);}
    .logo-circle span{font-family:'Playfair Display',serif;font-size:1.1rem;font-weight:700;color:#fff;}
    .logo-name{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:600;color:var(--crema);}
    .back-arrow{position:fixed;top:1.5rem;right:1.5rem;z-index:20;width:40px;height:40px;border-radius:50%;background:hsla(25,20%,15%,.6);backdrop-filter:blur(12px);border:1px solid hsla(255,255,255,.08);display:flex;align-items:center;justify-content:center;color:var(--crema);text-decoration:none;font-size:1.1rem;transition:all .3s;}
    .back-arrow:hover{background:var(--naranja);color:#fff;}
    .card{position:relative;z-index:10;width:100%;max-width:430px;margin:1rem;border-radius:1rem;overflow:hidden;box-shadow:var(--shadow-warm);backdrop-filter:blur(8px);border:1px solid hsla(25,25%,20%,.5);animation:fadeUp .7s ease;}
    .card-header{background:var(--gradient-warm);padding:2rem 2rem 1.8rem;text-align:center;}
    .card-header .icon-wrap{display:inline-flex;align-items:center;justify-content:center;width:56px;height:56px;border-radius:14px;background:hsla(255,255,255,.15);backdrop-filter:blur(8px);margin-bottom:.8rem;font-size:1.5rem;color:#fff;}
    .card-header h1{font-family:'Playfair Display',serif;font-size:1.55rem;font-weight:700;color:#fff;margin-bottom:.25rem;}
    .card-header p{font-size:.82rem;color:hsla(255,255,255,.75);}
    .card-body{background:var(--card-bg);padding:1.6rem 2rem 2rem;}
    .steps{display:flex;align-items:center;justify-content:center;gap:.6rem;margin-bottom:1.6rem;}
    .step{width:40px;height:40px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.82rem;font-weight:700;transition:all .35s;border:2px solid var(--border);color:var(--fg-muted);background:var(--muted-bg);}
    .step.active{background:var(--gradient-warm);border-color:transparent;color:#fff;box-shadow:var(--shadow-warm);transform:scale(1.12);}
    .step.done{background:hsla(27,72%,47%,.2);border-color:hsla(27,72%,47%,.35);color:var(--naranja);}
    .fl{margin-bottom:1.1rem;}.fl label{display:block;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--fg-muted);margin-bottom:.4rem;}
    .input-wrap{position:relative;}.input-wrap i.ico{position:absolute;left:.9rem;top:50%;transform:translateY(-50%);font-size:.95rem;color:var(--fg-muted);}
    .fl input{width:100%;padding:.85rem .9rem .85rem 2.6rem;border:1.5px solid var(--border);border-radius:.75rem;background:var(--muted-bg);color:var(--fg);font-size:.88rem;font-family:inherit;transition:all .25s;}
    .fl input:focus{outline:none;border-color:var(--naranja);box-shadow:0 0 0 3px hsla(27,72%,47%,.2);}
    .fl input::placeholder{color:var(--fg-muted);opacity:.5;}
    .eye-toggle{position:absolute;right:.9rem;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--fg-muted);cursor:pointer;font-size:.95rem;padding:0;}
    .method-grid{display:grid;grid-template-columns:1fr 1fr;gap:.5rem;margin-bottom:1.2rem;}
    .method-btn{border:2px solid var(--border);border-radius:.75rem;padding:.7rem .5rem;text-align:center;cursor:pointer;background:var(--muted-bg);transition:all .25s;font-family:inherit;}
    .method-btn:hover{border-color:var(--naranja);}
    .method-btn.active{border-color:var(--naranja);background:hsla(27,72%,47%,.12);box-shadow:0 0 0 3px hsla(27,72%,47%,.1);}
    .method-btn i{display:block;font-size:1.3rem;color:var(--naranja);margin-bottom:.3rem;}
    .method-btn .m-label{font-size:.78rem;font-weight:700;color:var(--fg);}.method-btn .m-sub{font-size:.62rem;color:var(--fg-muted);margin-top:.15rem;}
    .method-btn input{position:absolute;opacity:0;}
    .pin-wrap{display:flex;justify-content:center;gap:.55rem;margin:1.2rem 0;}
    .pin-digit{width:46px;height:54px;border:2px solid var(--border);border-radius:.75rem;text-align:center;font-size:1.4rem;font-weight:800;font-family:'Playfair Display',serif;color:var(--fg);background:var(--muted-bg);transition:all .25s;}
    .pin-digit:focus{outline:none;border-color:var(--naranja);box-shadow:0 0 0 3px hsla(27,72%,47%,.2);}
    input[name="codigo"]{position:absolute;opacity:0;pointer-events:none;}
    .step-info{text-align:center;font-size:.85rem;color:var(--fg);margin-bottom:1.2rem;line-height:1.5;}
    .step-info strong{color:var(--miel);}.step-info.success{color:hsl(130,50%,65%);}
    .email-masked{font-size:.75rem;color:var(--fg-muted);text-align:center;margin-top:.3rem;}
    .btn-primary{width:100%;padding:.85rem;border-radius:.75rem;background:var(--gradient-warm);color:#fff;border:none;font-size:.9rem;font-weight:700;font-family:inherit;cursor:pointer;box-shadow:var(--shadow-warm);display:flex;align-items:center;justify-content:center;gap:.4rem;transition:all .25s;margin-top:.4rem;}
    .btn-primary:hover{box-shadow:var(--shadow-glow);transform:translateY(-2px);}
    .btn-back{width:100%;padding:.7rem;border-radius:.75rem;border:1px solid var(--border);background:transparent;color:var(--fg-muted);font-size:.82rem;font-weight:600;font-family:inherit;cursor:pointer;margin-top:.6rem;display:flex;align-items:center;justify-content:center;gap:.3rem;transition:all .2s;}
    .btn-back:hover{background:var(--muted-bg);color:var(--fg);}
    .link-login{display:block;text-align:center;margin-top:1rem;font-size:.8rem;color:var(--naranja);text-decoration:none;font-weight:600;}.link-login:hover{color:var(--miel);}
    .hint{font-size:.72rem;color:var(--fg-muted);text-align:center;margin-top:.8rem;line-height:1.5;}
    .msg-err{background:hsla(0,60%,40%,.12);border:1px solid hsla(0,60%,50%,.25);border-left:3px solid hsl(0,70%,50%);border-radius:.7rem;padding:.65rem .9rem;font-size:.8rem;color:hsl(0,70%,70%);font-weight:600;margin-bottom:1.1rem;display:flex;align-items:flex-start;gap:.45rem;line-height:1.5;}
    .msg-err i{flex-shrink:0;margin-top:.15rem;}
    .msg-ok{background:hsla(130,50%,40%,.12);border:1px solid hsla(130,50%,50%,.25);border-left:3px solid hsl(130,50%,50%);border-radius:.7rem;padding:.65rem .9rem;font-size:.8rem;color:hsl(130,50%,70%);font-weight:600;margin-bottom:1.1rem;display:flex;align-items:flex-start;gap:.45rem;line-height:1.5;}
    .msg-ok i{flex-shrink:0;margin-top:.15rem;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}
    @media(max-width:480px){/deep/ .card-header{padding:1.5rem 1.3rem;}.card-body{padding:1.3rem;}.pin-digit{width:40px;height:48px;font-size:1.2rem;}.logo{top:1rem;left:1rem;}.back-arrow{top:1rem;right:1rem;}}
  </style>
</head>
<body>
  <div class="bg-img"><img src="<?= APP_URL ?>/assets/img/bakery-bg.jpg" alt=""></div>
  <div class="bg-overlay"></div>
  <div class="bg-overlay2"></div>
  <div class="logo"><div class="logo-circle"><span>B</span></div><span class="logo-name">BreadControl</span></div>
  <a href="<?= APP_URL ?>/login.php" class="back-arrow" title="Volver al login"><i class="bi bi-arrow-left"></i></a>

  <div class="card">
    <div class="card-header">
      <div class="icon-wrap"><i class="bi bi-shield-lock-fill"></i></div>
      <h1>Recuperar contrasena</h1>
      <p>Verifica tu identidad para restablecer tu acceso</p>
    </div>
    <div class="card-body">
      <?php if ($paso >= 1 && $paso <= 3): ?>
      <div class="steps">
        <div class="step <?= $paso==1?'active':($paso>1?'done':'') ?>"><?= $paso>1?'&#10003;':'1' ?></div>
        <div class="step <?= $paso==2?'active':($paso>2?'done':'') ?>"><?= $paso>2?'&#10003;':'2' ?></div>
        <div class="step <?= $paso==3?'active':'' ?>">3</div>
      </div>
      <?php endif; ?>

      <?php if ($error): ?><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $error ?></span></div><?php endif; ?>
      <?php if ($ok): ?><div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $ok ?></span></div>
      <a href="<?= APP_URL ?>/login.php" class="btn-primary"><i class="bi bi-box-arrow-in-right"></i> Ir al login</a><?php endif; ?>

      <?php if ($paso == 1): ?>
      <form method="POST">
        <div class="fl"><label>Nombre de usuario</label><div class="input-wrap"><i class="bi bi-person-fill ico"></i>
          <input type="text" name="usuario" placeholder="Ej: propietario" value="<?= htmlspecialchars($usuario_input) ?>" required autofocus></div></div>
        <label style="display:block;font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--fg-muted);margin-bottom:.5rem;">Metodo de verificacion</label>
        <div class="method-grid">
          <label class="method-btn active" id="m-email" onclick="selM('email')"><input type="radio" name="metodo" value="email" checked>
            <i class="bi bi-envelope-fill"></i><div class="m-label">Correo</div><div class="m-sub">Codigo al email</div></label>
          <label class="method-btn" id="m-pin" onclick="selM('pin')"><input type="radio" name="metodo" value="pin">
            <i class="bi bi-123"></i><div class="m-label">PIN</div><div class="m-sub">6 digitos</div></label>
        </div>
        <button type="submit" name="verificar_usuario" class="btn-primary">Siguiente <i class="bi bi-arrow-right"></i></button>
      </form>
      <div class="hint">No tienes correo ni PIN? Pidelo al propietario.</div>
      <script>function selM(m){document.querySelectorAll('.method-btn').forEach(b=>b.classList.remove('active'));document.getElementById('m-'+m).classList.add('active');document.querySelector('input[name=metodo][value='+m+']').checked=true;}</script>
      <?php endif; ?>

      <?php if ($paso == 2): ?>
      <div class="step-info"><?php if($metodo==='email'): ?>Enviamos un codigo a<br><strong><?= $_SESSION['recover_email_masked']??'' ?></strong>
        <?php else: ?>Ingresa el PIN de 6 digitos para<br><strong><?= htmlspecialchars($_SESSION['recover_usuario']??'') ?></strong><?php endif; ?></div>
      <form method="POST">
        <div class="pin-wrap"><?php for($i=0;$i<6;$i++): ?><input type="text" class="pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="<?=$i?>" autocomplete="off"><?php endfor; ?></div>
        <input type="hidden" name="codigo" id="ch" value="">
        <?php if($metodo==='email'): ?><div class="email-masked">El codigo expira en 5 minutos</div><?php endif; ?>
        <button type="submit" name="verificar_codigo" class="btn-primary">Verificar <i class="bi bi-arrow-right"></i></button>
        <button type="button" onclick="window.location='<?= APP_URL ?>/recuperar_pin.php'" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</button>
      </form>
      <script>(function(){const d=document.querySelectorAll('.pin-digit'),h=document.getElementById('ch');function s(){h.value=Array.from(d).map(x=>x.value).join('');}d.forEach((x,i)=>{x.addEventListener('input',function(){this.value=this.value.replace(/\D/g,'').slice(0,1);s();if(this.value&&i<5)d[i+1].focus();});x.addEventListener('keydown',function(e){if(e.key==='Backspace'&&!this.value&&i>0){d[i-1].focus();d[i-1].value='';s();}});x.addEventListener('paste',function(e){e.preventDefault();const t=(e.clipboardData.getData('text')||'').replace(/\D/g,'').slice(0,6);for(let j=0;j<6;j++)d[j].value=t[j]||'';s();if(t.length>=6)d[5].focus();});});d[0].focus();})();</script>
      <?php endif; ?>

      <?php if ($paso == 3): ?>
      <div class="step-info success"><i class="bi bi-check-circle-fill"></i> Verificacion exitosa. Crea tu nueva contrasena.</div>
      <form method="POST">
        <div class="fl"><label>Nueva contrasena</label><div class="input-wrap"><i class="bi bi-shield-lock ico"></i>
          <input type="password" name="nueva_clave" id="p1" placeholder="Minimo 6 caracteres" required minlength="6" autofocus>
          <button type="button" class="eye-toggle" onclick="tE('p1',this)"><i class="bi bi-eye"></i></button></div></div>
        <div class="fl"><label>Confirmar contrasena</label><div class="input-wrap"><i class="bi bi-shield-check ico"></i>
          <input type="password" name="confirmar_clave" id="p2" placeholder="Repite la contrasena" required minlength="6">
          <button type="button" class="eye-toggle" onclick="tE('p2',this)"><i class="bi bi-eye"></i></button></div></div>
        <button type="submit" name="cambiar_clave" class="btn-primary"><i class="bi bi-key-fill"></i> Restablecer contrasena</button>
      </form>
      <script>function tE(id,b){const i=document.getElementById(id),c=b.querySelector('i');if(i.type==='password'){i.type='text';c.className='bi bi-eye-off';}else{i.type='password';c.className='bi bi-eye';}}</script>
      <?php endif; ?>

      <?php if ($paso > 0): ?><a href="<?= APP_URL ?>/login.php" class="link-login"><i class="bi bi-arrow-left"></i> Volver al login</a><?php endif; ?>
    </div>
  </div>
</body>
</html>
