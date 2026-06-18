<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagar Saldo — BreadControl</title>
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
            --pago-green:#2e7d32; --pago-green-dk:#1b5e20; --pago-green-bg:#e8f5e9; --pago-green-bd:#a5d6a7;
        }
        *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }
        html, body { width:100%; max-width:100%; overflow-x:hidden; font-family:'Plus Jakarta Sans',sans-serif; background:var(--cbg); color:var(--ink); -webkit-text-size-adjust:100%; }
        @keyframes pulse-pay{0%,100%{box-shadow:0 4px 20px rgba(46,125,50,.35);}50%{box-shadow:0 6px 28px rgba(46,125,50,.55);}}
        @keyframes pulse-monto{0%,100%{transform:scale(1);}50%{transform:scale(1.02);}}

        nav { position:fixed; top:0; left:0; right:0; z-index:900; height:var(--nav-h); background:linear-gradient(100deg,var(--c1) 0%,var(--c3) 55%,var(--c4) 100%); display:flex; align-items:center; padding:0 1rem; box-shadow:0 3px 24px rgba(100,40,10,.35); }
        .n-logo { display:flex; align-items:center; gap:.65rem; text-decoration:none; padding:.18rem .6rem .18rem .15rem; border-radius:12px; }
        .n-logo:hover { background:rgba(255,255,255,.12); }
        .n-logo-img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2.5px solid rgba(255,255,255,.6); box-shadow:0 2px 10px rgba(80,30,5,.45); }
        .n-logo-name { font-family:'Fraunces',serif; font-size:1.12rem; font-weight:800; color:#fff; line-height:1.1; text-shadow:0 1px 6px rgba(80,30,5,.35); }
        .n-logo-sub { font-size:.55rem; text-transform:uppercase; letter-spacing:.18em; color:rgba(255,255,255,.65); margin-top:2px;}

        .page { margin-top:var(--nav-h); padding:1.5rem 1rem; width:100%; max-width:680px; margin-left:auto; margin-right:auto; }
        .card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:2rem; }

        .ped-title { font-family:'Fraunces',serif; font-size: 1.8rem; font-weight: 800; color: var(--ink); margin-bottom: 0.5rem; text-align:center;}
        .ped-sub { color: var(--ink3); font-size: 0.9rem; margin-bottom: 1.5rem; text-align:center; line-height:1.45;}

        .monto-destacado { background: linear-gradient(135deg, var(--pago-green), var(--pago-green-dk)); color:#fff; border-radius: 14px; padding: 1.3rem 1.4rem; text-align: center; margin-bottom: 1.4rem; animation: pulse-monto 3s ease infinite; box-shadow: 0 4px 20px rgba(46,125,50,.25);}
        .monto-destacado .lbl { font-size: .72rem; text-transform: uppercase; letter-spacing: .15em; opacity:.85; margin-bottom:.4rem; font-weight:700;}
        .monto-destacado .val { font-family: 'Fraunces', serif; font-size: 2.6rem; font-weight: 800; line-height:1; letter-spacing:-.02em;}
        .monto-destacado .sub { font-size:.78rem; opacity:.9; margin-top:.55rem; font-weight:600;}

        .ped-list { margin-bottom: 1.3rem; background: var(--clight); border: 1px solid var(--border); border-radius: 10px; padding: 1rem; max-height: 240px; overflow-y: auto;}
        .ped-list-title { font-weight:700; margin-bottom:.6rem; color:var(--ink2); font-size:.78rem; text-transform:uppercase; letter-spacing:.1em;}
        .ped-list-item { display: flex; justify-content: space-between; padding: 0.55rem 0; border-bottom: 1px solid rgba(148,91,53,.08); font-size: 0.85rem;}
        .ped-list-item:last-child { border-bottom: none; }
        .ped-list-item span { color: var(--ink); }
        .ped-list-item strong { color: var(--c1); }

        .pago-pasos { background: #fff; border: 1px solid var(--pago-green-bd); border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1.2rem;}
        .pago-pasos .titulo { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--pago-green-dk); margin-bottom: .7rem;}
        .pago-pasos ol { margin-left: 1.2rem; padding-left: 0; }
        .pago-pasos ol li { font-size: .85rem; color: var(--ink); line-height: 1.5; margin-bottom: .35rem;}
        .pago-pasos ol li strong { color: var(--pago-green-dk); }

        .btn-pagar { width:100%; display:flex; align-items:center; justify-content:center; gap:.6rem; background: linear-gradient(135deg, var(--pago-green), var(--pago-green-dk)); color:#fff; border:none; border-radius: 12px; padding: 1.1rem 1.4rem; font-size: 1.1rem; font-weight: 800; text-decoration:none; transition: all .25s; animation: pulse-pay 2.5s ease infinite; cursor:pointer; }
        .btn-pagar:hover { transform: translateY(-2px); animation:none; box-shadow: 0 8px 30px rgba(46,125,50,.45);}

        .btn-generar { width:100%; background: linear-gradient(135deg, var(--c3), var(--c1)); color:#fff; border:none; border-radius:12px; padding:1rem; font-size:1rem; font-weight:700; cursor:pointer; transition:all .2s; display:flex; align-items:center; justify-content:center; gap:.5rem;}
        .btn-generar:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(198,113,36,.3);}

        .btn-back { display: inline-block; margin-top: 1.2rem; color: var(--ink3); text-decoration: none; font-weight: 600; font-size: 0.88rem; text-align:center; width:100%;}
        .btn-back:hover { color: var(--c3); text-decoration: underline; }

        .pago-medios { font-size:.72rem; color: var(--ink3); text-align:center; margin-top:.7rem;}
        .pago-medios strong { color: var(--ink2); }

        .aviso { background:#fff8e1; border:1px solid #ffe082; border-left:3px solid #ffb300; border-radius:10px; padding:.85rem 1rem; font-size:.82rem; color:#856404; margin-bottom:1rem; line-height:1.5;}
        .aviso i { margin-right:.3rem; }

        .msg-error{ background:#ffebee; border:1px solid #ef9a9a; border-left:4px solid #c62828; border-radius:10px; padding:.85rem 1rem; font-size:.85rem; color:#c62828; margin-bottom:1rem; }
        .msg-success{ background:#e8f5e9; border:1px solid #a5d6a7; border-left:4px solid #2e7d32; border-radius:10px; padding:.85rem 1rem; font-size:.85rem; color:#1b5e20; margin-bottom:1rem; font-weight:600;}
    </style>
</head>
<body>
    <nav>
        <a href="dashboard.php" class="n-logo">
            <img src="<?= APP_URL ?>/assets/img/logo.png" alt="BreadControl" class="n-logo-img">
            <div>
                <div class="n-logo-name">BreadControl</div>
                <div class="n-logo-sub">Portal Cliente</div>
            </div>
        </a>
    </nav>

    <div class="page">
        <div class="card">
            <h1 class="ped-title">Pagar Saldo Pendiente</h1>
            <p class="ped-sub">
                <?php if (count($pedidos) === 1): ?>
                    Vas a pagar tu pedido de forma rápida y segura.
                <?php else: ?>
                    Vas a pagar varios pedidos en una sola transacción para ahorrarte tiempo.
                <?php endif; ?>
            </p>

            <?php if ($error): ?>
                <div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="msg-success"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?></div>
            <?php endif; ?>

            <?php if (!$pago_configurado): ?>
                <div class="aviso">
                    <i class="bi bi-info-circle-fill"></i>
                    La panadería aún no ha habilitado los pagos digitales. Comunícate directamente con el propietario para coordinar el pago.
                </div>
            <?php else: ?>

                <div class="monto-destacado">
                    <div class="lbl"><i class="bi bi-cash-coin"></i> Monto total a pagar</div>
                    <div class="val">$<?= number_format($total_saldo, 0, ',', '.') ?></div>
                    <div class="sub">Por <?= count($pedidos) ?> pedido<?= count($pedidos)>1?'s':'' ?> pendiente<?= count($pedidos)>1?'s':'' ?></div>
                </div>

                <div class="ped-list">
                    <div class="ped-list-title"><i class="bi bi-list-ul"></i> Pedidos incluidos en este pago</div>
                    <?php foreach ($pedidos as $p): ?>
                        <div class="ped-list-item">
                            <span>
                                Pedido #<?= str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
                                <span style="color:var(--ink3); font-size:.78rem;">— entrega <?= formatearFechaEntrega($p['fecha_entrega']) ?></span>
                            </span>
                            <strong>$<?= number_format($p['total_estimado'], 0, ',', '.') ?></strong>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($link_pago_url): ?>
                    <div class="pago-pasos">
                        <div class="titulo"><i class="bi bi-list-check"></i> Cómo pagar</div>
                        <ol>
                            <li>Toca el botón verde <strong>Pagar ahora</strong>.</li>
                            <li>En el checkout, digita el monto: <strong>$<?= number_format($total_saldo, 0, ',', '.') ?></strong></li>
                            <li>Elige tu medio de pago (Nequi, Bancolombia, PSE, tarjeta).</li>
                            <li>Completa el pago en tu app.</li>
                        </ol>
                    </div>

                    <a href="<?= htmlspecialchars($link_pago_url) ?>" target="_blank" rel="noopener" class="btn-pagar">
                        <i class="bi bi-shield-lock-fill"></i>
                        Pagar ahora $<?= number_format($total_saldo, 0, ',', '.') ?>
                        <i class="bi bi-box-arrow-up-right" style="font-size:.9rem; opacity:.85;"></i>
                    </a>

                    <div class="pago-medios">
                        Aceptamos: <strong>Nequi · Bancolombia · PSE · Tarjeta</strong>
                        <?php if (!empty($titular_negocio)): ?>
                            <br>Pagas a: <strong><?= htmlspecialchars($titular_negocio) ?></strong>
                        <?php endif; ?>
                    </div>

                    <div class="aviso" style="margin-top:1rem;">
                        <i class="bi bi-info-circle-fill"></i>
                        Una vez completes el pago, la panadería lo verificará y confirmará tus <?= count($pedidos) ?> pedidos. Esto puede tardar unos minutos.
                    </div>

                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                        <button type="submit" name="generar_pago" class="btn-generar">
                            <i class="bi bi-lightning-charge-fill"></i>
                            Habilitar pago consolidado
                        </button>
                    </form>

                    <div class="pago-medios" style="margin-top:.6rem;">
                        Al continuar, todos tus pedidos pendientes se unirán en un solo pago.
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <?php if ($id_pedido_spec > 0): ?>
                <a href="detalle_pedido.php?id=<?= $id_pedido_spec ?>" class="btn-back"><i class="bi bi-arrow-left"></i> Volver al pedido</a>
            <?php else: ?>
                <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver al panel</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
