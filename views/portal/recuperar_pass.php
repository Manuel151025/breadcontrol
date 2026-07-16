<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title>Recuperar Contraseña — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <style>
    *{margin:0;padding:0;box-sizing:border-box;}
    :root{--bg:hsl(24,60%,6%);--fg:hsl(30,30%,90%);--card:hsla(24,40%,10%,.6);--orange:hsl(27,72%,47%);--honey:hsl(30,67%,65%);--muted:hsl(30,20%,45%);--border:rgba(255,255,255,.1);--input-bg:hsla(24,40%,8%,.6);}
    body{font-family:'DM Sans',sans-serif;color:var(--fg);background:var(--bg);min-height:100vh;overflow-x:hidden;}
    .bg-img{position:fixed;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;}
    .bg-overlay{position:fixed;inset:0;background:hsla(24,60%,6%,.82);z-index:1;}
    .login-wrap{position:relative;z-index:10;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:2rem 1rem;}
    .glass-card{background:var(--card);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border:1px solid var(--border);border-radius:1.5rem;width:100%;max-width:430px;padding:2.2rem;display:flex;flex-direction:column;gap:1.2rem;animation:fadeUp .6s ease-out forwards;}
    .card-title{text-align:center;}
    .card-title h1{font-family:'Playfair Display',serif;font-size:1.5rem;font-weight:700;margin-bottom:.2rem;}
    .card-title .sub{font-size:.88rem;color:var(--honey);font-weight:600;}
    .steps{display:flex;justify-content:center;align-items:center;gap:.5rem;}
    .step{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.78rem;font-weight:700;color:rgba(255,255,255,.4);border:2px solid rgba(255,255,255,.15);transition:all .3s;}
    .step.active{background:var(--orange);border-color:var(--orange);color:#fff;box-shadow:0 4px 16px rgba(198,113,36,.4);}
    .step.done{background:rgba(34,197,94,.2);border-color:rgba(34,197,94,.5);color:#86efac;}
    .msg-err{background:rgba(220,38,38,.12);border:1px solid rgba(220,38,38,.25);border-left:4px solid #dc2626;border-radius:.75rem;padding:.65rem .9rem;font-size:.8rem;color:#fca5a5;display:flex;align-items:flex-start;gap:.4rem;line-height:1.4;}
    .msg-ok{background:rgba(34,197,94,.12);border:1px solid rgba(34,197,94,.25);border-left:4px solid #22c55e;border-radius:.75rem;padding:.65rem .9rem;font-size:.8rem;color:#86efac;display:flex;align-items:center;gap:.4rem;}
    .field label{display:block;font-size:.75rem;font-weight:600;color:rgba(255,255,255,.55);margin-bottom:.4rem;text-transform:uppercase;letter-spacing:.08em;}
    .field input{width:100%;height:2.85rem;padding:0 1rem 0 2.6rem;background:var(--input-bg);border:1px solid var(--border);border-radius:.75rem;color:var(--fg);font-family:inherit;font-size:.88rem;outline:none;transition:all .3s;}
    .field input:focus{border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.15);}
    .field .inp-wrap{position:relative;}
    .field .ico{position:absolute;left:.8rem;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.35);font-size:1rem;}
    .eye-btn{position:absolute;right:.75rem;top:50%;transform:translateY(-50%);background:none;border:none;color:rgba(255,255,255,.35);font-size:1rem;cursor:pointer;padding:0;}

    /* Método selector */
    .method-grid{display:grid;grid-template-columns:1fr 1fr;gap:.6rem;}
    .method-btn{border:2px solid var(--border);border-radius:.8rem;padding:.8rem .5rem;text-align:center;cursor:pointer;background:var(--input-bg);transition:all .25s;font-family:inherit;}
    .method-btn:hover{border-color:var(--orange);}
    .method-btn.active{border-color:var(--orange);background:hsla(27,72%,47%,.12);}
    .method-btn input{position:absolute;opacity:0;}
    .method-btn i{display:block;font-size:1.25rem;color:var(--orange);margin-bottom:.3rem;}
    .method-btn .m-label{font-size:.8rem;font-weight:700;color:var(--fg);}
    .method-btn .m-sub{font-size:.62rem;color:var(--muted);margin-top:.1rem;}

    /* PIN digits */
    .pin-wrap{display:flex;justify-content:center;gap:.5rem;margin:.5rem 0;}
    .pin-digit{width:46px;height:54px;border:2px solid var(--border);border-radius:.75rem;text-align:center;font-size:1.4rem;font-weight:800;color:var(--fg);background:var(--input-bg);transition:all .25s;}
    .pin-digit:focus{outline:none;border-color:var(--orange);box-shadow:0 0 0 3px hsla(27,72%,47%,.2);}

    .btn-primary{width:100%;height:2.85rem;border:none;border-radius:2rem;background:linear-gradient(135deg,var(--orange),var(--honey));color:#fff;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;transition:all .3s;display:flex;align-items:center;justify-content:center;gap:.4rem;text-decoration:none;}
    .btn-primary:hover{transform:translateY(-2px);box-shadow:0 8px 30px hsla(27,72%,47%,.35);}
    .link-back{display:flex;align-items:center;justify-content:center;gap:.35rem;color:var(--honey);font-size:.8rem;text-decoration:none;transition:all .2s;}
    .link-back:hover{text-decoration:underline;}
    .hint{font-size:.72rem;color:var(--muted);text-align:center;line-height:1.5;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
  </style>
</head>
<body>
  <img src="<?= APP_URL ?>/assets/img/bakery-bg.jpg" alt="" class="bg-img">
  <div class="bg-overlay"></div>

  <div class="login-wrap">
    <div class="glass-card">

      <div class="card-title">
        <h1><i class="bi bi-shield-lock-fill" style="color:var(--orange);"></i> Recuperar Acceso</h1>
        <p class="sub">Portal de Clientes — BreadControl</p>
      </div>

      <div style="background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-left:3px solid var(--orange);border-radius:.75rem;padding:.7rem .9rem;font-size:.75rem;color:rgba(255,255,255,.5);line-height:1.55;">
        <i class="bi bi-google" style="color:#EA4335;"></i>
        <strong style="color:rgba(255,255,255,.65);">¿Entraste con Google?</strong>
        Esta página es solo para cuentas con usuario y contraseña manual.
        Si usas Google, <a href="index.php" style="color:var(--honey);text-decoration:none;font-weight:600;">vuelve al login</a> y usa el botón de Google.
      </div>

      <?php if ($paso >= 1 && $paso <= 3): ?>
      <div class="steps">
        <div class="step <?= $paso==1?'active':($paso>1?'done':'') ?>"><?= $paso>1?'✓':'1' ?></div>
        <div style="width:28px;height:2px;background:rgba(255,255,255,.15);"></div>
        <div class="step <?= $paso==2?'active':($paso>2?'done':'') ?>"><?= $paso>2?'✓':'2' ?></div>
        <div style="width:28px;height:2px;background:rgba(255,255,255,.15);"></div>
        <div class="step <?= $paso==3?'active':'' ?>">3</div>
      </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="msg-err"><i class="bi bi-exclamation-triangle-fill" style="flex-shrink:0;"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if ($ok): ?>
        <div class="msg-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($ok) ?></div>
        <a href="index.php" class="btn-primary"><i class="bi bi-box-arrow-in-right"></i> Ir al inicio de sesión</a>
      <?php endif; ?>

      <!-- ── PASO 1: usuario + método ── -->
      <?php if ($paso == 1): ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        <div class="field" style="margin-bottom:1rem;">
          <label>Nombre de Usuario</label>
          <div class="inp-wrap">
            <i class="bi bi-person-fill ico"></i>
            <input type="text" name="usuario" placeholder="Tu usuario de acceso" value="<?= htmlspecialchars($usuario_input) ?>" required autofocus>
          </div>
        </div>

        <label style="display:block;font-size:.7rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:rgba(255,255,255,.5);margin-bottom:.5rem;">Método de verificación</label>
        <div class="method-grid" style="margin-bottom:1.1rem;">
          <label class="method-btn active" id="m-email" onclick="selM('email')">
            <input type="radio" name="metodo" value="email" checked>
            <i class="bi bi-envelope-fill"></i>
            <div class="m-label">Correo</div>
            <div class="m-sub">Código al email</div>
          </label>
          <label class="method-btn" id="m-pin" onclick="selM('pin')">
            <input type="radio" name="metodo" value="pin">
            <i class="bi bi-123"></i>
            <div class="m-label">PIN</div>
            <div class="m-sub">6 dígitos</div>
          </label>
        </div>

        <button type="submit" name="verificar_usuario" class="btn-primary">Continuar <i class="bi bi-arrow-right"></i></button>
      </form>
      <div class="hint">¿No tienes correo ni PIN? Contacta al administrador de BreadControl.</div>
      <script>function selM(m){document.querySelectorAll('.method-btn').forEach(function(b){b.classList.remove('active');});document.getElementById('m-'+m).classList.add('active');document.querySelector('input[name="metodo"][value="'+m+'"]').checked=true;}</script>
      <?php endif; ?>

      <!-- ── PASO 2: código / PIN ── -->
      <?php if ($paso == 2): ?>
      <?php if ($metodo === 'email'): ?>
        <div style="text-align:center;font-size:.85rem;color:var(--fg);line-height:1.6;">
          Enviamos un código a <strong style="color:var(--honey);"><?= htmlspecialchars($_SESSION['recover_cemail'] ?? '') ?></strong>.<br>
          <span style="font-size:.75rem;color:var(--muted);">Expira en 10 minutos. Revisa tu carpeta de spam si no llega.</span>
        </div>
      <?php else: ?>
        <div style="text-align:center;font-size:.85rem;color:var(--fg);line-height:1.6;">
          Hola <strong style="color:var(--honey);"><?= htmlspecialchars($_SESSION['recover_cnombre'] ?? '') ?></strong>,<br>ingresa tu PIN de 6 dígitos.
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        <div class="pin-wrap" id="pin-display">
          <?php for ($i = 0; $i < 6; $i++): ?>
          <input type="text" class="pin-digit" maxlength="1" inputmode="numeric" pattern="[0-9]" data-idx="<?= $i ?>" autocomplete="off">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="codigo" id="ch" value="">
        <button type="submit" name="verificar_codigo" class="btn-primary" style="margin-top:.5rem;">Verificar <i class="bi bi-shield-check"></i></button>
        <a href="recuperar_pass.php?reiniciar=1" class="link-back" style="margin-top:.6rem;"><i class="bi bi-arrow-left"></i> Volver</a>
      </form>
      <script>
      (function(){
        var d = document.querySelectorAll('.pin-digit'), h = document.getElementById('ch');
        function s(){ h.value = Array.from(d).map(function(x){return x.value;}).join(''); }
        d.forEach(function(x, i){
          x.addEventListener('input', function(){ this.value = this.value.replace(/\D/g,'').slice(0,1); s(); if(this.value && i<5) d[i+1].focus(); });
          x.addEventListener('keydown', function(e){ if(e.key==='Backspace' && !this.value && i>0){ d[i-1].focus(); d[i-1].value=''; s(); } });
          x.addEventListener('paste', function(e){ e.preventDefault(); var t=(e.clipboardData.getData('text')||'').replace(/\D/g,'').slice(0,6); for(var j=0;j<6;j++) d[j].value=t[j]||''; s(); if(t.length>=6) d[5].focus(); });
        });
        d[0].focus();
      })();
      </script>
      <?php endif; ?>

      <!-- ── PASO 3: nueva contraseña ── -->
      <?php if ($paso == 3): ?>
      <div style="text-align:center;font-size:.85rem;color:#86efac;line-height:1.5;">
        <i class="bi bi-patch-check-fill"></i> Identidad verificada. Crea tu nueva contraseña.
      </div>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
        <div class="field" style="margin-bottom:.9rem;">
          <label>Nueva contraseña</label>
          <div class="inp-wrap">
            <i class="bi bi-shield-lock ico"></i>
            <input type="password" name="nueva" id="p1" placeholder="Mínimo 6 caracteres" required minlength="6" autofocus>
            <button type="button" class="eye-btn" onclick="tg('p1',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <div class="field" style="margin-bottom:1.1rem;">
          <label>Confirmar contraseña</label>
          <div class="inp-wrap">
            <i class="bi bi-shield-check ico"></i>
            <input type="password" name="confirm" id="p2" placeholder="Repite la contraseña" required minlength="6">
            <button type="button" class="eye-btn" onclick="tg('p2',this)"><i class="bi bi-eye"></i></button>
          </div>
        </div>
        <button type="submit" name="cambiar_pass" class="btn-primary"><i class="bi bi-key-fill"></i> Restablecer contraseña</button>
      </form>
      <script>function tg(id,b){var i=document.getElementById(id),c=b.querySelector('i');if(i.type==='password'){i.type='text';c.className='bi bi-eye-slash';}else{i.type='password';c.className='bi bi-eye';}}</script>
      <?php endif; ?>

      <?php if ($paso > 0 && $paso < 4): ?>
      <a href="index.php" class="link-back"><i class="bi bi-arrow-left"></i> Volver al login</a>
      <?php endif; ?>

    </div>
  </div>
</body>
</html>
