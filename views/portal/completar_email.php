<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Confirma tu correo — BreadControl</title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/img/favicon-32.png">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fuentes.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/bootstrap-icons.css?v=<?= APP_VERSION ?>">
<style>
    :root{
      /* Colores institucionales SENA */
      --sena:#39A900; --sena-dk:#2b7d00; --sena-dk2:#1f5c00;
      --sena-bg:#eef8e6; --sena-bd:#bfe6a3;
      --cbg:#f4f8f0; --ccard:#ffffff; --clight:#f3faec;
      --ink:#14260a; --ink2:#3c5a25; --ink3:#6f8a58;
      --border:rgba(57,169,0,.16);
    }
    *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
    html,body{width:100%;max-width:100%;overflow-x:hidden;font-family:'Plus Jakarta Sans',sans-serif;background:var(--cbg);color:var(--ink);min-height:100vh;}
    body{display:flex;align-items:center;justify-content:center;padding:1.5rem;}

    .card{background:var(--ccard);border:1px solid var(--border);border-radius:16px;box-shadow:0 8px 30px rgba(43,125,0,.12);width:100%;max-width:440px;padding:2.2rem 2rem;animation:fadeUp .4s ease both;}
    @keyframes fadeUp{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:translateY(0)}}

    .logo{display:flex;align-items:center;justify-content:center;gap:.6rem;margin-bottom:1.3rem;}
    .logo img{width:44px;height:44px;border-radius:50%;object-fit:cover;border:2px solid var(--sena-bd);}
    .logo-name{font-family:'Fraunces',serif;font-size:1.15rem;font-weight:800;color:var(--sena-dk2);}

    .icono{width:60px;height:60px;border-radius:50%;background:var(--sena-bg);color:var(--sena);display:flex;align-items:center;justify-content:center;font-size:1.8rem;margin:0 auto 1rem;}
    h1{font-family:'Fraunces',serif;font-size:1.4rem;font-weight:800;color:var(--ink);text-align:center;margin-bottom:.4rem;}
    .sub{font-size:.86rem;color:var(--ink3);text-align:center;line-height:1.5;margin-bottom:1.4rem;}

    .msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:.8rem 1rem;font-size:.83rem;color:#c62828;margin-bottom:1.1rem;display:flex;align-items:center;gap:.5rem;}

    label{display:block;font-size:.74rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--ink3);margin-bottom:.4rem;}
    input[type=email]{width:100%;padding:.8rem 1rem;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:.95rem;color:var(--ink);background:var(--clight);outline:none;transition:border-color .2s,box-shadow .2s;}
    input[type=email]:focus{border-color:var(--sena);box-shadow:0 0 0 3px rgba(57,169,0,.12);}

    .btn{width:100%;margin-top:1.3rem;background:linear-gradient(135deg,var(--sena),var(--sena-dk));color:#fff;border:none;border-radius:11px;padding:.85rem;font-family:inherit;font-size:.92rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.5rem;transition:all .2s;}
    .btn:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(43,125,0,.22);}

    .nota{margin-top:1rem;font-size:.76rem;color:var(--ink3);text-align:center;line-height:1.5;}
    .logout{display:block;margin-top:1rem;text-align:center;font-size:.78rem;color:var(--ink3);text-decoration:none;}
    .logout:hover{color:var(--sena-dk);text-decoration:underline;}
  </style>
</head>
<body>
  <div class="card">
    <div class="logo">
      <img src="<?= APP_URL ?>/assets/img/logo.png" alt="BreadControl">
      <span class="logo-name">BreadControl</span>
    </div>

    <div class="icono"><i class="bi bi-envelope-at-fill"></i></div>
    <h1>Confirma tu correo</h1>
    <p class="sub">Hola <strong><?= htmlspecialchars($nombre_actual ?? '') ?></strong>, necesitamos tu correo electrónico para asegurar tu cuenta y que puedas entrar también con Google sin crear una cuenta duplicada.</p>

    <?php if ($error): ?>
    <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error ?? '') ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
      <label for="email">Correo electrónico</label>
      <input type="email" id="email" name="email" placeholder="tucorreo@ejemplo.com" required maxlength="150" autofocus>
      <button type="submit" class="btn"><i class="bi bi-check2-circle"></i> Guardar y continuar</button>
    </form>

    <div class="nota"><i class="bi bi-shield-check"></i> Usa el mismo correo de tu cuenta de Google si piensas entrar con ella.</div>
    <a href="logout.php" class="logout">Cerrar sesión</a>
  </div>
</body>
</html>
