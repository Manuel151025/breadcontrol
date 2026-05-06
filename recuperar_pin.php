<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';

session_name(SESSION_NOMBRE);
session_start();

if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . APP_URL . '/index.php');
    exit;
}

$pdo   = getConexion();
$paso  = 1;
$error = '';
$ok    = '';
$usuario_input = '';
$metodo = $_SESSION['recover_metodo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // PASO 1
    if (isset($_POST['verificar_usuario'])) {
        $usuario_input = trim($_POST['usuario'] ?? '');
        $metodo_sel    = $_POST['metodo'] ?? 'email';

        if (empty($usuario_input)) {
            $error = 'Ingresa tu nombre de usuario.';
        } else {
            $stmt = $pdo->prepare("SELECT id_usuario, nombre_completo, correo_electronico, pin_recuperacion FROM usuario WHERE nombre_usuario=? AND activo=1");
            $stmt->execute([$usuario_input]);
            $user = $stmt->fetch();

            if (!$user) {
                $error = 'Usuario no encontrado.';
            } elseif ($metodo_sel === 'email') {
                if (empty($user['correo_electronico'])) {
                    $error = 'Este usuario no tiene correo configurado.<br>Ve a Mi Perfil para agregar uno, o usa el método PIN.';
                } else {
                    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                    $expira = date('Y-m-d H:i:s', strtotime('+5 minutes'));
                    $pdo->prepare("UPDATE usuario SET codigo_recuperacion=?, codigo_expira=? WHERE id_usuario=?")->execute([$codigo, $expira, $user['id_usuario']]);

                    $to = $user['correo_electronico'];
                    $nombre = $user['nombre_completo'];
                    $subject = 'BreadControl - Codigo de recuperacion';
                    $body = "<div style='font-family:Arial,sans-serif;max-width:480px;margin:0 auto;'>"
                          . "<div style='background:linear-gradient(135deg,#945b35,#c67124);padding:20px;border-radius:12px 12px 0 0;text-align:center;'>"
                          . "<h1 style='color:#fff;margin:0;font-size:22px;'>BreadControl</h1>"
                          . "<p style='color:rgba(255,255,255,0.8);margin:5px 0 0;font-size:13px;'>Recuperacion de contrasena</p></div>"
                          . "<div style='background:#fff;padding:25px;border:1px solid #e0d5c8;border-radius:0 0 12px 12px;'>"
                          . "<p style='color:#333;font-size:14px;'>Hola <strong>{$nombre}</strong>,</p>"
                          . "<p style='color:#555;font-size:14px;'>Tu codigo de verificacion es:</p>"
                          . "<div style='background:#faf3ea;border:2px solid #c67124;border-radius:10px;padding:15px;text-align:center;margin:15px 0;'>"
                          . "<span style='font-size:32px;font-weight:800;letter-spacing:8px;color:#945b35;'>{$codigo}</span></div>"
                          . "<p style='color:#888;font-size:12px;'>Este codigo expira en 5 minutos.</p>"
                          . "<p style='color:#888;font-size:12px;'>Si no solicitaste este cambio, ignora este mensaje.</p></div></div>";

                    $headers = "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\nFrom: BreadControl <noreply@breadcontrol.adso.pro>\r\n";
                    $enviado = @mail($to, $subject, $body, $headers);

                    if ($enviado) {
                        $_SESSION['recover_user_id'] = $user['id_usuario'];
                        $_SESSION['recover_usuario'] = $usuario_input;
                        $_SESSION['recover_metodo'] = 'email';
                        $_SESSION['recover_email_masked'] = preg_replace('/(?<=.{2}).(?=.*@)/', '*', $to);
                        $paso = 2; $metodo = 'email';
                    } else {
                        $error = 'No se pudo enviar el correo.<br>Intenta con el metodo PIN.';
                    }
                }
            } elseif ($metodo_sel === 'pin') {
                if (empty($user['pin_recuperacion'])) {
                    $error = 'Este usuario no tiene PIN configurado.<br>Ve a Mi Perfil para crear uno.';
                } else {
                    $_SESSION['recover_user_id'] = $user['id_usuario'];
                    $_SESSION['recover_usuario'] = $usuario_input;
                    $_SESSION['recover_metodo'] = 'pin';
                    $paso = 2; $metodo = 'pin';
                }
            }
        }
    }

    // PASO 2
    if (isset($_POST['verificar_codigo'])) {
        $codigo = trim($_POST['codigo'] ?? '');
        $uid = $_SESSION['recover_user_id'] ?? null;
        $metodo = $_SESSION['recover_metodo'] ?? '';
        $usuario_input = $_SESSION['recover_usuario'] ?? '';

        if (!$uid) { $error = 'Sesion expirada.<br>Empieza de nuevo.'; $paso = 1; }
        elseif (empty($codigo) || !preg_match('/^\d{6}$/', $codigo)) { $error = 'El codigo debe ser de 6 digitos.'; $paso = 2; }
        else {
            if ($metodo === 'email') {
                $stmt = $pdo->prepare("SELECT codigo_recuperacion, codigo_expira FROM usuario WHERE id_usuario=?");
                $stmt->execute([$uid]); $row = $stmt->fetch();
                if (!$row || $row['codigo_recuperacion'] !== $codigo) { $error = 'Codigo incorrecto.'; $paso = 2; }
                elseif (strtotime($row['codigo_expira']) < time()) {
                    $error = 'El codigo ha expirado.<br>Vuelve a empezar.'; $paso = 1;
                    unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_metodo']);
                } else {
                    $_SESSION['recover_pin_ok'] = true;
                    $pdo->prepare("UPDATE usuario SET codigo_recuperacion=NULL, codigo_expira=NULL WHERE id_usuario=?")->execute([$uid]);
                    $paso = 3;
                }
            } else {
                $stmt = $pdo->prepare("SELECT pin_recuperacion FROM usuario WHERE id_usuario=?");
                $stmt->execute([$uid]); $hash = $stmt->fetchColumn();
                if ($hash && password_verify($codigo, $hash)) { $_SESSION['recover_pin_ok'] = true; $paso = 3; }
                else { $error = 'PIN incorrecto.'; $paso = 2; }
            }
        }
    }

    // PASO 3
    if (isset($_POST['cambiar_clave'])) {
        $nueva = $_POST['nueva_clave'] ?? '';
        $conf = $_POST['confirmar_clave'] ?? '';
        $uid = $_SESSION['recover_user_id'] ?? null;
        $pin_ok = $_SESSION['recover_pin_ok'] ?? false;

        if (!$uid || !$pin_ok) { $error = 'Sesion expirada.'; $paso = 1; }
        elseif (strlen($nueva) < 6) { $error = 'Minimo 6 caracteres.'; $paso = 3; }
        elseif ($nueva !== $conf) { $error = 'Las contrasenas no coinciden.'; $paso = 3; }
        else {
            $hash = password_hash($nueva, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE usuario SET contrasena_hash=? WHERE id_usuario=?")->execute([$hash, $uid]);
            unset($_SESSION['recover_user_id'], $_SESSION['recover_usuario'], $_SESSION['recover_pin_ok'], $_SESSION['recover_metodo'], $_SESSION['recover_email_masked']);
            $ok = 'Contrasena actualizada exitosamente!'; $paso = 0;
        }
    }
}
?>
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
    @media(max-width:480px){.card-header{padding:1.5rem 1.3rem;}.card-body{padding:1.3rem;}.pin-digit{width:40px;height:48px;font-size:1.2rem;}.logo{top:1rem;left:1rem;}.back-arrow{top:1rem;right:1rem;}}
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
