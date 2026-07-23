<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Perfil — BreadControl</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --c1:#945b35; --c2:#c8956e; --c3:#c67124; --c4:#e4a565; --c5:#ecc198;
            --cbg:#faf3ea; --ccard:#ffffff; --clight:#fdf6ee;
            --ink:#281508; --ink2:#6b3d1e; --ink3:#b87a4a;
            --border:rgba(148,91,53,.12);
            --shadow:0 1px 8px rgba(148,91,53,.09);
            --shadow2:0 4px 20px rgba(148,91,53,.15);
            --nav-h:64px;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { width:100%; max-width:100%; overflow-x:hidden; font-family:'Plus Jakarta Sans',sans-serif; background:var(--cbg); color:var(--ink); -webkit-text-size-adjust:100%; }

        /* ── NAVBAR ── */
        nav { position:fixed; top:0; left:0; right:0; z-index:900; height:var(--nav-h); background:linear-gradient(100deg,var(--c1) 0%,var(--c3) 55%,var(--c4) 100%); display:flex; align-items:center; justify-content:space-between; padding:0 1rem; box-shadow:0 3px 24px rgba(100,40,10,.35); }
        nav::after { content:''; position:absolute; bottom:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent); }
        .n-logo { display:flex; align-items:center; gap:.65rem; text-decoration:none; padding:.18rem .6rem .18rem .15rem; border-radius:12px; transition:background .2s; }
        .n-logo:hover { background:rgba(255,255,255,.12); }
        .n-logo-img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2.5px solid rgba(255,255,255,.6); box-shadow:0 2px 10px rgba(80,30,5,.45); }
        .n-logo-name { font-family:'Fraunces',serif; font-size:1.12rem; font-weight:800; color:#fff; line-height:1.1; text-shadow:0 1px 6px rgba(80,30,5,.35); }
        .n-logo-sub { font-size:.5rem; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.6); }
        .n-right { display:flex; align-items:center; gap:.55rem; flex-shrink:0; }
        .n-user { display:flex; align-items:center; gap:.45rem; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22); border-radius:22px; padding:.26rem .75rem .26rem .3rem; text-decoration:none; }
        .n-avatar { width:30px; height:30px; border-radius:50%; background:rgba(255,255,255,.28); border:1.5px solid rgba(255,255,255,.45); display:flex; align-items:center; justify-content:center; font-size:.8rem; color:#fff; font-weight:800; flex-shrink:0; overflow:hidden; }
        .n-avatar img { width:100%; height:100%; object-fit:cover; }
        .n-uname { font-size:.78rem; color:#fff; font-weight:700; }
        .n-urole { font-size:.55rem; color:rgba(255,255,255,.62); text-transform:uppercase; letter-spacing:.1em; }
        .n-logout { width:34px; height:34px; border-radius:8px; background:rgba(255,255,255,.12); border:1px solid rgba(255,255,255,.2); color:rgba(255,255,255,.85); font-size:1rem; display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all .2s; }
        .n-logout:hover { background:rgba(220,53,69,.35); color:#fff; border-color:rgba(220,53,69,.5); }

        .page { margin-top:var(--nav-h); padding:2rem 1rem; width:100%; max-width:800px; margin-left:auto; margin-right:auto; animation:fadeUp .4s ease both; }
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        .profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; }
        @media(max-width:768px) { .profile-grid { grid-template-columns: 1fr; } }

        .card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:1.5rem; height: 100%; }
        .card-title { font-family:'Fraunces',serif; font-size: 1.25rem; font-weight: 800; color: var(--ink); margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.6rem; }
        .card-title i { color: var(--c3); }

        .form-group { margin-bottom: 1.2rem; }
        .form-group label { display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: var(--ink3); margin-bottom: 0.4rem; }
        .form-control { width: 100%; padding: 0.75rem 1rem; border: 1px solid var(--border); border-radius: 10px; font-family: inherit; font-size: 0.9rem; color: var(--ink); background: var(--clight); transition: border-color 0.2s; }
        .form-control:focus { outline: none; border-color: var(--c3); box-shadow: 0 0 0 3px rgba(198,113,36,0.1); }
        .form-control[readonly] { background: #f0f0f0; cursor: not-allowed; }

        .btn-save { width: 100%; background: linear-gradient(135deg, var(--c3), var(--c1)); color: #fff; border: none; padding: 0.8rem; border-radius: 10px; font-size: 0.9rem; font-weight: 700; cursor: pointer; transition: all 0.2s; display: flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-save:hover { transform: translateY(-2px); box-shadow: var(--shadow2); }

        .msg-ok { background: #e8f5e9; color: #2e7d32; padding: 1rem; border-radius: 10px; border-left: 4px solid #2e7d32; margin-bottom: 1.5rem; font-size: 0.85rem; }
        .msg-err { background: #ffebee; color: #c62828; padding: 1rem; border-radius: 10px; border-left: 4px solid #c62828; margin-bottom: 1.5rem; font-size: 0.85rem; }

        @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
        
        .wc-banner { background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%); background-size:300% 300%; animation:gradAnim 8s ease infinite; border-radius:14px; padding:1.2rem 1.4rem; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--shadow2); gap:1rem; flex-wrap:wrap; margin-bottom:1.5rem; position: relative; overflow: hidden; }
        .wc-banner::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: url('<?= APP_URL ?>/assets/img/bakery-bg.jpg'); background-size: cover; background-position: center; opacity: 0.1; mix-blend-mode: overlay; }
        .wc-left { display:flex; align-items:center; gap:1.2rem; position: relative; z-index: 2; }
        .wc-greeting { font-size:.65rem; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.65); margin-bottom:.15rem; }
        .wc-name { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:800; color:#fff; line-height:1.1; }
        .wc-name em { font-style:italic; color:var(--c5); }
        .wc-sub { font-size:.72rem; color:rgba(255,255,255,.62); margin-top:.15rem; }

        .wc-p-avatar { width: 64px; height: 64px; border-radius: 50%; background: rgba(255,255,255,0.2); border: 2px solid rgba(255,255,255,0.5); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; font-family: 'Fraunces', serif; color: #fff; text-shadow: 0 2px 10px rgba(0,0,0,0.2); }

        .btn-back { background: var(--ccard); color: var(--ink2); border: 1px solid var(--border); border-radius: 10px; padding: .5rem 1rem; font-size: .82rem; font-weight: 600; display: inline-flex; align-items: center; gap: .4rem; text-decoration: none; transition: all .2s; margin-bottom: 1rem;}
        .btn-back:hover { background: var(--clight); border-color: var(--c3); color: var(--c3); }

        .pin-input { font-family: 'Fraunces', serif; font-size: 1.5rem; letter-spacing: 0.5em; text-align: center; font-weight: 800; color: var(--c1); }
        .sec-tip { background: var(--clight); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; margin-bottom: 1rem; font-size: 0.8rem; color: var(--ink3); line-height: 1.5; }
        .sec-tip strong { display: block; color: var(--ink); margin-bottom: 0.2rem; }

        /* ── Bloque aprendiz SENA ── */
        --sena:#39A900; --sena-dk:#2b7d00; --sena-bg:#eef8e6; --sena-bd:#bfe6a3;
        .sena-card { background:#fff; border:1px solid var(--sena-bd); border-radius:14px; box-shadow:0 1px 8px rgba(43,125,0,.09); padding:1.5rem; margin-top:1.5rem; }
        .sena-title { font-family:'Fraunces',serif; font-size:1.2rem; font-weight:800; color:var(--sena-dk); margin-bottom:1rem; display:flex; align-items:center; gap:.55rem; }
        .sena-title i { color:var(--sena); }
        .sena-hint { background:var(--sena-bg); border:1px solid var(--sena-bd); border-radius:10px; padding:.85rem 1rem; font-size:.82rem; color:var(--sena-dk); line-height:1.5; margin-bottom:1rem; }
        .sena-code-row { display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end; }
        .sena-code-row .form-group { flex:1; min-width:180px; margin-bottom:0; }
        .sena-code-input { text-transform:uppercase; letter-spacing:.18em; font-weight:700; font-family:'Fraunces',serif; }
        .btn-sena { background:linear-gradient(135deg,var(--sena),var(--sena-dk)); color:#fff; border:none; padding:.8rem 1.2rem; border-radius:10px; font-size:.9rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; gap:.5rem; text-decoration:none; }
        .btn-sena:hover { transform:translateY(-2px); box-shadow:0 4px 20px rgba(43,125,0,.2); }
        .sena-status { display:flex; align-items:center; gap:.75rem; background:var(--sena-bg); border-radius:10px; padding:1rem; }
        .sena-status i { font-size:1.6rem; color:var(--sena); }
        .n-navlink { display:inline-flex; align-items:center; gap:.35rem; background:rgba(255,255,255,.15); border:1px solid rgba(255,255,255,.22); border-radius:9px; padding:.4rem .75rem; color:#fff; font-size:.78rem; font-weight:700; text-decoration:none; }
        .n-navlink:hover { background:rgba(255,255,255,.25); }

        .profile-grid-full { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.5rem; }
        @media(max-width:1100px) { .profile-grid-full { grid-template-columns: 1fr 1fr; } }
        @media(max-width:768px) { 
            .profile-grid-full { grid-template-columns: 1fr; } 
            nav { padding:.4rem .7rem; height:auto; min-height:56px; }
            .n-logo-name { font-size:.92rem; }
            .n-logo-sub { display:none; }
            .n-logo-img { width:32px; height:32px; }
            .n-avatar { width:28px; height:28px; font-size:.7rem; }
            .n-uname { max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .n-urole { display:none; }
            .n-logout { display:none; }
        }
    </style>
</head>
<body>
    <nav>
        <a href="dashboard.php" class="n-logo">
            <img src="<?= APP_URL ?>/assets/img/logo.png" alt="BreadControl" class="n-logo-img">
            <div>
                <div class="n-logo-name">BreadControl</div>
                <div class="n-logo-sub"><?= $es_instructor ? 'Instructor ADSO' : 'Portal Cliente' ?></div>
            </div>
        </a>
        <div class="n-right">
            <?php if ($es_tienda): ?>
            <a href="mis_aprendices.php" class="n-navlink" title="Mis aprendices"><i class="bi bi-mortarboard-fill"></i> Aprendices</a>
            <?php endif; ?>
            <a href="perfil.php" class="n-user" title="Mi Perfil">
                <div class="n-avatar">
                    <?php if (!empty($cliente['foto_url'])): ?>
                        <img src="<?= htmlspecialchars($cliente['foto_url']) ?>" alt="avatar">
                    <?php else: ?>
                        <?= strtoupper(substr($_SESSION['cliente_nombre'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="n-uname"><?= htmlspecialchars($_SESSION['cliente_nombre']) ?></div>
                    <div class="n-urole"><?= $es_instructor ? 'Instructor' : 'Cliente' ?></div>
                </div>
            </a>
            <a href="logout.php" class="n-logout" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </nav>

    <div class="page">
        <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver al Dashboard</a>

        <!-- ══ BANNER PROPIETARIO STYLE ══ -->
        <div class="wc-banner">
            <div class="wc-left">
                <div class="wc-p-avatar"><?= strtoupper(substr($cliente['nombre'], 0, 1)) ?></div>
                <div>
                    <div class="wc-greeting">Mi Cuenta</div>
                    <div class="wc-name"><?= htmlspecialchars($cliente['nombre']) ?></div>
                    <div class="wc-sub">
                        <i class="bi bi-<?= $cliente['tipo'] === 'tienda' ? 'shop' : 'person' ?>"></i> 
                        Cliente <?= ucfirst($cliente['tipo']) ?> — Gestiona tu información personal
                    </div>
                </div>
            </div>
        </div>

        <?php if ($msg_ok): ?><div class="msg-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_ok ?></div><?php endif; ?>
        <?php if ($msg_err): ?><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_err ?></div><?php endif; ?>

        <div class="profile-grid-full">
            <!-- Datos Personales -->
            <div class="card">
                <div class="card-title"><i class="bi bi-person-badge"></i> Datos del Perfil</div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <div class="form-group">
                        <label>Usuario</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($cliente['usuario']) ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label>Nombre Completo / Tienda</label>
                        <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($cliente['nombre']) ?>" maxlength="40" required>
                    </div>
                    <div class="form-group">
                        <label>Teléfono de Contacto</label>
                        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($cliente['telefono']) ?>" maxlength="15" oninput="this.value=this.value.replace(/\D/g,'')">
                    </div>
                    <button type="submit" name="actualizar_datos" class="btn-save">
                        <i class="bi bi-check2-circle"></i> Guardar Cambios
                    </button>
                </form>
            </div>

            <!-- Seguridad -->
            <div class="card">
                <div class="card-title"><i class="bi bi-shield-lock"></i> Seguridad</div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <div class="form-group">
                        <label>Contraseña Actual</label>
                        <input type="password" name="pass_actual" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Nueva Contraseña</label>
                        <input type="password" name="pass_nueva" class="form-control" required minlength="4">
                    </div>
                    <div class="form-group">
                        <label>Confirmar Nueva Contraseña</label>
                        <input type="password" name="pass_confirm" class="form-control" required minlength="4">
                    </div>
                    <button type="submit" name="cambiar_pass" class="btn-save" style="background: var(--ink2);">
                        <i class="bi bi-key"></i> Cambiar Contraseña
                    </button>
                </form>
            </div>

            <!-- PIN de Recuperación -->
            <div class="card">
                <div class="card-title"><i class="bi bi-123"></i> PIN de Recuperación</div>
                <div class="sec-tip">
                    <strong>¿Para qué sirve?</strong>
                    Si olvidas tu contraseña, podrás restablecerla usando este código de 6 dígitos.
                </div>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <div class="form-group">
                        <label>Confirmar Contraseña</label>
                        <input type="password" name="pass_pin" class="form-control" placeholder="Para validar el cambio" required>
                    </div>
                    <div class="form-group">
                        <label>Nuevo PIN (6 dígitos)</label>
                        <input type="text" name="pin" class="form-control pin-input" maxlength="6" inputmode="numeric" pattern="\d{6}" placeholder="••••••" oninput="this.value=this.value.replace(/\D/g,'')" required>
                    </div>
                    <button type="submit" name="guardar_pin" class="btn-save" style="background: var(--c3);">
                        <i class="bi bi-shield-check"></i> <?= empty($cliente['pin_recuperacion']) ? 'Configurar PIN' : 'Actualizar PIN' ?>
                    </button>
                    <?php if(!empty($cliente['pin_recuperacion'])): ?>
                        <div style="text-align:center; margin-top:1rem; font-size:0.75rem; color:#2e7d32; font-weight:700;">
                            <i class="bi bi-check-circle"></i> PIN ya configurado
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- ══ APRENDIZ SENA (canje de código) ══ -->
        <?php if ($puede_canjear): ?>
        <div class="sena-card">
            <div class="sena-title"><i class="bi bi-mortarboard-fill"></i> ¿Eres aprendiz SENA?</div>
            <div class="sena-hint">
                <i class="bi bi-info-circle-fill"></i>
                Si tu instructor te dio un código, ingrésalo aquí para unirte a su grupo. Quedarás registrado como aprendiz con tu cupo semanal.
            </div>
            <form method="post" class="sena-code-row">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <div class="form-group">
                    <label>Código de aprendiz</label>
                    <input type="text" name="codigo_aprendiz" class="form-control sena-code-input" maxlength="16" placeholder="Ej: K7M4P2QR" required>
                </div>
                <button type="submit" name="canjear_codigo" class="btn-sena"><i class="bi bi-check2-circle"></i> Canjear código</button>
            </form>
        </div>
        <?php elseif ((int)$cliente['es_aprendiz'] === 1): ?>
        <div class="sena-card">
            <div class="sena-title"><i class="bi bi-mortarboard-fill"></i> Aprendiz SENA</div>
            <div class="sena-status">
                <i class="bi bi-patch-check-fill"></i>
                <div>
                    <div style="font-weight:700; color:var(--ink);">Estás registrado como aprendiz<?= $mi_instructor_nombre ? ' de ' . htmlspecialchars($mi_instructor_nombre) : '' ?>.</div>
                    <div style="font-size:.8rem; color:var(--ink3); margin-top:.2rem;">Si necesitas salir del grupo, pídeselo a tu instructor.</div>
                </div>
            </div>
        </div>
        <?php elseif ($es_tienda): ?>
        <div class="sena-card">
            <div class="sena-title"><i class="bi bi-people-fill"></i> Gestión de aprendices</div>
            <div class="sena-hint" style="margin-bottom:1rem;">
                <i class="bi bi-info-circle-fill"></i>
                Como instructor, genera un código y compártelo para que tus aprendices se registren en tu grupo.
            </div>
            <a href="mis_aprendices.php" class="btn-sena"><i class="bi bi-mortarboard-fill"></i> Ir a Mis aprendices</a>
        </div>
        <?php endif; ?>
    </div>
</body>
</html>
