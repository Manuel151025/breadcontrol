<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Aprendices — BreadControl</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            /* Colores institucionales SENA */
            --sena:#39A900; --sena-dk:#2b7d00; --sena-dk2:#1f5c00;
            --sena-bg:#eef8e6; --sena-bd:#bfe6a3; --naranja:#FF7300;
            --cbg:#f4f8f0; --ccard:#ffffff; --clight:#f3faec;
            --ink:#14260a; --ink2:#3c5a25; --ink3:#6f8a58;
            --border:rgba(57,169,0,.16);
            --shadow:0 1px 8px rgba(43,125,0,.09);
            --shadow2:0 4px 20px rgba(43,125,0,.16);
            --nav-h:64px;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { width:100%; max-width:100%; overflow-x:hidden; font-family:'Plus Jakarta Sans',sans-serif; background:var(--cbg); color:var(--ink); -webkit-text-size-adjust:100%; }

        nav { position:fixed; top:0; left:0; right:0; z-index:900; height:var(--nav-h); background:linear-gradient(100deg,var(--sena-dk2) 0%,var(--sena-dk) 45%,var(--sena) 100%); display:flex; align-items:center; justify-content:space-between; padding:0 1rem; box-shadow:0 3px 24px rgba(20,60,0,.35); }
        .n-logo { display:flex; align-items:center; gap:.65rem; text-decoration:none; padding:.18rem .6rem .18rem .15rem; border-radius:12px; transition:background .2s; }
        .n-logo:hover { background:rgba(255,255,255,.12); }
        .n-logo-img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2.5px solid rgba(255,255,255,.65); box-shadow:0 2px 10px rgba(20,60,0,.4); }
        .n-logo-name { font-family:'Fraunces',serif; font-size:1.12rem; font-weight:800; color:#fff; line-height:1.1; text-shadow:0 1px 6px rgba(20,60,0,.35); }
        .n-logo-sub { font-size:.5rem; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.75); }
        .n-right { display:flex; align-items:center; gap:.55rem; flex-shrink:0; }
        .n-logout { width:34px; height:34px; border-radius:8px; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); color:#fff; font-size:1rem; display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all .2s; }
        .n-logout:hover { background:rgba(220,53,69,.35); }
        .n-back { display:inline-flex; align-items:center; gap:.35rem; background:rgba(255,255,255,.14); border:1px solid rgba(255,255,255,.22); color:#fff; font-size:.8rem; font-weight:700; padding:.4rem .8rem; border-radius:9px; text-decoration:none; }
        .n-back:hover { background:rgba(255,255,255,.24); }

        .page { margin-top:var(--nav-h); padding:1.5rem 1rem 3rem; width:100%; max-width:900px; margin-left:auto; margin-right:auto; animation:fadeUp .4s ease both; }
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}

        .hero { background:linear-gradient(120deg,var(--sena-dk2),var(--sena-dk) 55%,var(--sena)); border-radius:16px; padding:1.4rem 1.6rem; color:#fff; box-shadow:var(--shadow2); margin-bottom:1.4rem; }
        .hero h1 { font-family:'Fraunces',serif; font-size:1.5rem; font-weight:800; display:flex; align-items:center; gap:.6rem; }
        .hero p { font-size:.85rem; color:rgba(255,255,255,.85); margin-top:.3rem; }

        .msg-ok { background:var(--sena-bg); color:var(--sena-dk2); padding:.9rem 1rem; border-radius:10px; border-left:4px solid var(--sena); margin-bottom:1.2rem; font-size:.85rem; font-weight:600; }
        .msg-err { background:#ffebee; color:#c62828; padding:.9rem 1rem; border-radius:10px; border-left:4px solid #c62828; margin-bottom:1.2rem; font-size:.85rem; font-weight:600; }

        .card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:1.5rem; margin-bottom:1.4rem; }
        .card-title { font-family:'Fraunces',serif; font-size:1.2rem; font-weight:800; color:var(--ink); margin-bottom:1.1rem; display:flex; align-items:center; gap:.55rem; }
        .card-title i { color:var(--sena); }

        .codigo-box { background:var(--clight); border:2px dashed var(--sena-bd); border-radius:12px; padding:1.2rem; text-align:center; margin-bottom:1rem; }
        .codigo-val { font-family:'Fraunces',serif; font-size:2.1rem; font-weight:800; letter-spacing:.28em; color:var(--sena-dk2); text-align:center; width:100%; border:none; background:transparent; user-select:all; }
        .codigo-meta { display:flex; justify-content:center; gap:1.4rem; flex-wrap:wrap; margin-top:.6rem; font-size:.78rem; color:var(--ink3); }
        .codigo-meta strong { color:var(--ink2); }
        .sin-codigo { text-align:center; color:var(--ink3); font-size:.9rem; padding:.5rem 0 1rem; }

        .form-row { display:flex; gap:1rem; flex-wrap:wrap; align-items:flex-end; }
        .fg { flex:1; min-width:150px; margin-bottom:.9rem; }
        .fg label { display:block; font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--ink3); margin-bottom:.35rem; }
        .fg input[type=number] { width:100%; padding:.65rem .85rem; border:1px solid var(--border); border-radius:9px; font-family:inherit; font-size:.9rem; color:var(--ink); background:var(--clight); }
        .fg input:focus { outline:none; border-color:var(--sena); box-shadow:0 0 0 3px rgba(57,169,0,.12); }
        .chk-row { display:flex; align-items:center; gap:.5rem; font-size:.82rem; color:var(--ink2); font-weight:600; margin-bottom:.9rem; }
        .chk-row input { width:16px; height:16px; accent-color:var(--sena); }

        .btn { border:none; border-radius:10px; padding:.72rem 1.1rem; font-family:inherit; font-size:.85rem; font-weight:700; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; gap:.45rem; text-decoration:none; transition:all .2s; }
        .btn-sena { background:linear-gradient(135deg,var(--sena),var(--sena-dk)); color:#fff; }
        .btn-sena:hover { transform:translateY(-2px); box-shadow:var(--shadow2); }
        .btn-ghost { background:#fff; color:var(--ink2); border:1px solid var(--border); }
        .btn-ghost:hover { border-color:var(--sena); color:var(--sena-dk); }
        .btn-danger { background:#fff; color:#c62828; border:1px solid rgba(198,40,40,.3); }
        .btn-danger:hover { background:#ffebee; }
        .btn-sm { padding:.45rem .7rem; font-size:.76rem; border-radius:8px; }

        .hint { background:var(--sena-bg); border:1px solid var(--sena-bd); border-radius:10px; padding:.8rem 1rem; font-size:.8rem; color:var(--sena-dk2); line-height:1.5; margin-bottom:1rem; }
        .hint i { margin-right:.3rem; }

        .apr { display:flex; align-items:center; gap:1rem; padding:1rem; border:1px solid var(--border); border-radius:12px; margin-bottom:.7rem; flex-wrap:wrap; }
        .apr-avatar { width:44px; height:44px; border-radius:50%; background:var(--sena-bg); color:var(--sena-dk); display:flex; align-items:center; justify-content:center; font-family:'Fraunces',serif; font-weight:800; font-size:1.1rem; flex-shrink:0; }
        .apr-info { flex:1; min-width:160px; }
        .apr-nombre { font-weight:700; color:var(--ink); font-size:.95rem; }
        .apr-meta { font-size:.75rem; color:var(--ink3); margin-top:.15rem; }
        .apr-actions { display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; }
        .cupo-form { display:flex; align-items:center; gap:.35rem; }
        .cupo-form input { width:90px; padding:.4rem .5rem; border:1px solid var(--border); border-radius:7px; font-family:inherit; font-size:.8rem; text-align:right; background:var(--clight); color:var(--ink); }
        .cupo-form input:focus { outline:none; border-color:var(--sena); }
        .cupo-lbl { font-size:.7rem; color:var(--ink3); font-weight:700; text-transform:uppercase; letter-spacing:.04em; }
        .empty { text-align:center; color:var(--ink3); font-size:.9rem; padding:2rem 1rem; }
        .empty i { font-size:2.2rem; color:var(--sena-bd); display:block; margin-bottom:.6rem; }

        @media(max-width:640px){
            nav { padding:.4rem .7rem; height:auto; min-height:56px; }
            .n-logo-sub { display:none; }
            .apr { flex-direction:column; align-items:stretch; }
            .apr-actions { justify-content:space-between; }
        }
    </style>
</head>
<body>
    <nav>
        <a href="dashboard.php" class="n-logo">
            <img src="<?= APP_URL ?>/assets/img/logo.png" alt="BreadControl" class="n-logo-img">
            <div>
                <div class="n-logo-name">BreadControl</div>
                <div class="n-logo-sub">Instructor SENA</div>
            </div>
        </a>
        <div class="n-right">
            <a href="dashboard.php" class="n-back"><i class="bi bi-arrow-left"></i> Dashboard</a>
            <a href="logout.php" class="n-logout" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
        </div>
    </nav>

    <div class="page">
        <div class="hero">
            <h1><i class="bi bi-mortarboard-fill"></i> Mis Aprendices</h1>
            <p>Genera un código y compártelo con tus aprendices para que se registren en tu grupo. No necesitas aprobar a nadie uno por uno.</p>
        </div>

        <?php if ($msg_ok): ?><div class="msg-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg_ok) ?></div><?php endif; ?>
        <?php if ($msg_err): ?><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($msg_err) ?></div><?php endif; ?>

        <!-- ══ CÓDIGO DE INVITACIÓN ══ -->
        <div class="card">
            <div class="card-title"><i class="bi bi-key-fill"></i> Código de invitación</div>

            <?php if ($codigo_activo): ?>
                <div class="codigo-box">
                    <input class="codigo-val" type="text" value="<?= htmlspecialchars($codigo_activo['codigo']) ?>" readonly aria-label="Código activo">
                    <div class="codigo-meta">
                        <span>
                            <i class="bi bi-calendar-event"></i>
                            <?php if (!empty($codigo_activo['fecha_expira'])): ?>
                                Vence: <strong><?= date('d/m/Y H:i', strtotime($codigo_activo['fecha_expira'])) ?></strong>
                            <?php else: ?>
                                <strong>Sin vencimiento</strong>
                            <?php endif; ?>
                        </span>
                        <span>
                            <i class="bi bi-people"></i>
                            Usos:
                            <?php if ($codigo_activo['usos_maximos'] !== null): ?>
                                <strong><?= (int)$codigo_activo['usos_actuales'] ?> / <?= (int)$codigo_activo['usos_maximos'] ?></strong>
                            <?php else: ?>
                                <strong><?= (int)$codigo_activo['usos_actuales'] ?> (sin límite)</strong>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div class="hint">
                    <i class="bi bi-info-circle-fill"></i>
                    Comparte este código con tus aprendices. Lo canjean al registrarse o desde su perfil. Si generas uno nuevo, este dejará de funcionar.
                </div>
                <form method="post" style="display:inline;">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <button type="submit" name="desactivar_codigo" class="btn btn-danger">
                        <i class="bi bi-x-circle"></i> Desactivar código actual
                    </button>
                </form>
            <?php else: ?>
                <div class="sin-codigo"><i class="bi bi-key" style="font-size:1.6rem; display:block; margin-bottom:.4rem; color:var(--sena-bd);"></i> No tienes un código activo. Genera uno para invitar aprendices.</div>
            <?php endif; ?>

            <hr style="border:none; border-top:1px dashed var(--border); margin:1.2rem 0;">

            <div style="font-size:.82rem; font-weight:700; color:var(--ink2); margin-bottom:.8rem;">
                <i class="bi bi-plus-circle"></i> <?= $codigo_activo ? 'Generar un código nuevo' : 'Generar código' ?>
            </div>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <div class="form-row">
                    <div class="fg">
                        <label>Días de vigencia</label>
                        <input type="number" name="dias_vigencia" value="30" min="0" max="365">
                        <span style="font-size:.7rem; color:var(--ink3);">0 = sin vencimiento</span>
                    </div>
                    <div class="fg">
                        <label>Usos máximos</label>
                        <input type="number" name="usos_maximos" value="30" min="1" max="1000">
                    </div>
                </div>
                <label class="chk-row">
                    <input type="checkbox" name="sin_limite_usos" value="1">
                    Sin límite de usos (ignora el número de arriba)
                </label>
                <button type="submit" name="generar_codigo" class="btn btn-sena">
                    <i class="bi bi-arrow-repeat"></i> <?= $codigo_activo ? 'Reemplazar por uno nuevo' : 'Generar código' ?>
                </button>
            </form>
        </div>

        <!-- ══ LISTA DE APRENDICES ══ -->
        <div class="card">
            <div class="card-title"><i class="bi bi-people-fill"></i> Mi grupo (<?= count($aprendices) ?>)</div>

            <?php if (empty($aprendices)): ?>
                <div class="empty">
                    <i class="bi bi-person-plus"></i>
                    Aún no tienes aprendices. Comparte tu código para que se unan.
                </div>
            <?php else: ?>
                <?php foreach ($aprendices as $a): ?>
                    <div class="apr">
                        <div class="apr-avatar"><?= strtoupper(mb_substr($a['nombre'], 0, 1)) ?></div>
                        <div class="apr-info">
                            <div class="apr-nombre"><?= htmlspecialchars($a['nombre']) ?></div>
                            <div class="apr-meta">
                                <?php if (!empty($a['telefono'])): ?><i class="bi bi-telephone"></i> <?= htmlspecialchars($a['telefono']) ?> &nbsp;·&nbsp; <?php endif; ?>
                                <i class="bi bi-calendar-check"></i> Se unió:
                                <?= !empty($a['fecha_aprendiz']) ? date('d/m/Y', strtotime($a['fecha_aprendiz'])) : '—' ?>
                            </div>
                        </div>
                        <div class="apr-actions">
                            <form method="post" class="cupo-form">
                                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                <input type="hidden" name="aprendiz_id" value="<?= (int)$a['id_cliente'] ?>">
                                <span class="cupo-lbl">Cupo $</span>
                                <input type="number" name="cupo_semanal" value="<?= (int)$a['cupo_semanal'] ?>" min="0" max="100000" step="500">
                                <button type="submit" name="actualizar_cupo" class="btn btn-ghost btn-sm" title="Guardar cupo">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                <input type="hidden" name="aprendiz_id" value="<?= (int)$a['id_cliente'] ?>">
                                <button type="submit" name="quitar_aprendiz" class="btn btn-danger btn-sm" title="Quitar del grupo">
                                    <i class="bi bi-person-dash"></i> Quitar
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="hint" style="margin-top:1rem; margin-bottom:0;">
                    <i class="bi bi-shield-check"></i>
                    Al quitar a un aprendiz se conservan sus pedidos anteriores. Si tenía un pedido pendiente de pago dirigido a tu cuenta, ese pago sigue a tu cargo hasta confirmarlo.
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
