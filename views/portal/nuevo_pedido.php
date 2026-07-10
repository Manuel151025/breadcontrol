<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido — BreadControl</title>
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
        html, body { width:100%; max-width:100%; overflow-x:hidden; font-family:'Plus Jakarta Sans',sans-serif; background:var(--cbg); color:var(--ink); padding-bottom:6rem; -webkit-text-size-adjust:100%; }

        /* Ocultar spinners de input number */
        input[type=number]::-webkit-inner-spin-button, 
        input[type=number]::-webkit-outer-spin-button { -webkit-appearance: none; margin: 0; }
        input[type=number] { -moz-appearance: textfield; }

        /* ── NAVBAR ── */
        nav { position:fixed; top:0; left:0; right:0; z-index:900; height:var(--nav-h); background:linear-gradient(100deg,var(--c1) 0%,var(--c3) 55%,var(--c4) 100%); display:flex; align-items:center; justify-content:space-between; padding:0 1rem; box-shadow:0 3px 24px rgba(100,40,10,.35); }
        nav::after { content:''; position:absolute; bottom:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent); }
        .n-logo { display:flex; align-items:center; gap:.65rem; text-decoration:none; margin-right:.5rem; padding:.18rem .6rem .18rem .15rem; border-radius:12px; transition:background .2s; }
        .n-logo:hover { background:rgba(255,255,255,.12); }
        .n-logo-img { width:42px; height:42px; border-radius:50%; object-fit:cover; border:2.5px solid rgba(255,255,255,.6); box-shadow:0 2px 10px rgba(80,30,5,.45); transition:transform .3s; }
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

        /* ── LAYOUT Y ANIMACIONES ── */
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
        
        .page { margin-top:var(--nav-h); padding:1rem; width:100%; max-width:1050px; margin-left:auto; margin-right:auto; animation:fadeUp .4s ease both; }
        
        /* ── BANNER ── */
        .wc-banner { background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%); background-size:300% 300%; animation:gradAnim 8s ease infinite; border-radius:14px; padding:.9rem 1.4rem; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--shadow2); gap:1rem; flex-wrap:wrap; margin-bottom:.5rem; }
        .wc-left { display:flex; align-items:center; gap:.9rem; }
        .wc-greeting { font-size:.65rem; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.65); margin-bottom:.15rem; }
        .wc-name { font-family:'Fraunces',serif; font-size:1.35rem; font-weight:800; color:#fff; line-height:1.1; }
        .wc-name em { font-style:italic; color:var(--c5); }
        .wc-sub { font-size:.72rem; color:rgba(255,255,255,.62); margin-top:.15rem; }
        
        .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1rem;margin-top:.8rem;}
        .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
        .mod-titulo i{color:var(--c3);}
        .btn-back{background:var(--ccard);color:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;white-space:nowrap;}
        .btn-back:hover{background:var(--clight);border-color:var(--c3);color:var(--c3);}
        
        .card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); padding:1.5rem; display:grid; grid-template-columns:1fr 320px; gap:1.5rem; }
        @media(max-width:992px) { .card { grid-template-columns:1fr; } }
        .panel-izq, .panel-der { min-width: 0; width: 100%; }
        
        .sec-sep{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--ink3);padding:.3rem 0;margin:.15rem 0 .4rem;border-bottom:1px dashed var(--border);}
        
        .price-tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-bottom:.8rem;padding-bottom:.5rem;}
        .price-tab{padding:.5rem 1rem;border:2px solid var(--border);border-radius:12px;font-size:.85rem;font-weight:700;cursor:pointer;background:var(--clight);color:var(--ink2);white-space:nowrap;font-family:'Fraunces',serif;transition:all .2s;}
        .price-tab.active{border-color:var(--c3);background:var(--c3);color:#fff;}
        
        .prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.75rem;margin-bottom:.6rem;max-height:500px;overflow-y:auto;padding:.2rem;}
        @media(max-width:500px) { .prod-grid { grid-template-columns: 1fr 1fr; gap:.5rem; max-height:none; overflow-y:visible; } }
        .prod-card{border:1.5px solid var(--border);border-radius:12px;overflow:hidden;background:#fff;cursor:pointer;transition:all .25s;text-align:center;position:relative;}
        .prod-card.in-cart{border-color:#2e7d32;box-shadow:0 0 0 1px #2e7d32;}
        .prod-card img{width:100%;height:100px;object-fit:cover;display:block;}
        @media(max-width:500px) { .prod-card img { height:90px; } }
        .prod-card .pc-placeholder{width:100%;height:100px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(198,113,36,.06),rgba(198,113,36,.02));font-size:2.5rem;}
        @media(max-width:500px) { .prod-card .pc-placeholder { height:90px; } }
        .prod-card .pc-name{padding:.4rem .4rem;font-size:.78rem;font-weight:700;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .prod-card .pc-action{padding:0 .4rem .6rem;font-size:.65rem;color:var(--c3);font-weight:700;display:flex;align-items:center;justify-content:center;gap:.25rem;}
        .prod-card.in-cart .pc-action{color:#2e7d32;}
        .prod-card.expanded{border-color:var(--c3);box-shadow:0 4px 16px rgba(198,113,36,.2);transform:translateY(-2px);}
        .prod-card .pc-form{display:none;padding:.4rem;border-top:1px solid var(--border);background:var(--clight);}
        .prod-card.expanded .pc-form{display:block;}
        .pf-row{display:flex;align-items:center;gap:.3rem;margin-bottom:.3rem;}
        .pf-row label{font-size:.55rem;font-weight:700;text-transform:uppercase;color:var(--ink3);min-width:32px;}
        .pf-row input{width:55px;border:1px solid var(--border);border-radius:6px;padding:.25rem;text-align:center;font-size:.82rem;font-family:'Fraunces',serif;font-weight:700;background:#fff;}
        .pf-add{width:100%;padding:.35rem;border:none;border-radius:7px;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;}
        
        .cart-section{border:1px solid var(--border); border-radius:12px; padding:1rem; background:#fff;}
        .cart-title{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:var(--ink3);margin-bottom:.4rem;display:flex;align-items:center;gap:.35rem;}
        .cart-badge{background:var(--c3);color:#fff;font-size:.55rem;padding:.1rem .4rem;border-radius:10px;}
        .cart-empty{text-align:center;padding:.7rem;font-size:.75rem;color:var(--ink3);border:1.5px dashed var(--border);border-radius:9px;}
        .cart-list{display:flex;flex-direction:column;gap:.3rem;max-height:220px;overflow-y:auto;}
        .cart-item{display:flex;align-items:center;gap:.4rem;padding:.4rem .5rem;border:1px solid var(--border);border-radius:9px;background:var(--clight);}
        .cart-item img{width:32px;height:32px;border-radius:6px;object-fit:cover;flex-shrink:0;}
        .cart-item .ci-ph{width:32px;height:32px;border-radius:6px;background:rgba(198,113,36,.08);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
        .cart-item .ci-info{flex:1;min-width:0;}
        .cart-item .ci-name{font-size:.72rem;font-weight:700;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .cart-item .ci-price{font-size:.6rem;color:var(--ink3);}
        .cart-item .ci-fields input{width:40px;border:1px solid var(--border);border-radius:6px;padding:.2rem;text-align:center;font-size:.78rem;font-family:'Fraunces',serif;font-weight:700;}
        .cart-item .ci-sub{font-family:'Fraunces',serif;font-size:.72rem;font-weight:700;color:#2e7d32;min-width:55px;text-align:right;}
        .cart-item .ci-del{width:22px;height:22px;border-radius:5px;border:1px solid rgba(198,40,40,.2);background:none;color:#c62828;cursor:pointer;}
        
        .cart-total{display:flex;justify-content:space-between;align-items:center;padding:.5rem .6rem;margin-top:.4rem;background:linear-gradient(135deg,rgba(46,125,50,.08),rgba(46,125,50,.03));border:1px solid rgba(46,125,50,.2);border-radius:9px;font-size:.8rem;font-weight:700;color:#1b5e20;}
        
        .bonif-row{display:flex;align-items:center;gap:.4rem;padding:.3rem .4rem;border-radius:7px;margin-bottom:.2rem;background:rgba(21,101,192,.04);}
        .bonif-row img, .bonif-row .br-ph{width:28px;height:28px;border-radius:5px;flex-shrink:0;}
        .bonif-row .br-ph{background:rgba(21,101,192,.1);display:flex;align-items:center;justify-content:center;font-size:.7rem;}
        .bonif-row .br-name{flex:1;font-size:.72rem;font-weight:600;color:var(--ink);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
        .bonif-row input{width:45px;border:1px solid rgba(21,101,192,.2);border-radius:5px;padding:.2rem;text-align:center;font-size:.78rem;font-family:'Fraunces',serif;font-weight:700;}
        
        .btn-submit { width:100%; margin-top:1rem; background: linear-gradient(135deg, var(--c3), var(--c1)); color: #fff; border: none; padding: .8rem 2rem; border-radius: 10px; font-size: 1rem; font-weight: 700; cursor: pointer; transition: all .3s ease; box-shadow: var(--shadow2); display: flex; align-items: center; justify-content:center; gap: .5rem; }
        .btn-submit:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(198,113,36,.4); }
        .msg-error{background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:1rem;font-size:.85rem;color:#c62828;grid-column: 1 / -1;}
        .msg-success{background:#e8f5e9;border:1px solid #a5d6a7;border-left:4px solid #2e7d32;border-radius:10px;padding:2rem;text-align:center;color:#1b5e20;grid-column: 1 / -1;}

        @media(max-width:768px){
            nav { padding:.4rem .7rem; height:auto; min-height:56px; }
            .n-logo-name { font-size:.92rem; }
            .n-logo-sub { display:none; }
            .n-logo-img { width:32px; height:32px; }
            .n-avatar { width:28px; height:28px; font-size:.7rem; }
            .n-uname { max-width:70px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
            .n-urole { display:none; }
            .n-logout { display:none; }

            .wc-banner { padding:.75rem 1rem; }
            .wc-name { font-size:1.1rem; }
        }

        @media(max-width:500px){
            .page { padding: .75rem .4rem; }
            .topbar { flex-direction: column; align-items: stretch; gap: .75rem; margin-bottom: 1rem; }
            .mod-titulo { font-size: 1.15rem; width: 100%; justify-content: space-between; }
            .btn-back { width: 100%; justify-content: center; padding: .75rem; font-size: .85rem; border-radius: 12px; }
            .card { padding: .75rem; border-radius: 14px; border: 1px solid var(--border); }
            .cart-item { padding: .5rem; }
            .cart-item img, .cart-item .ci-ph { width: 32px; height: 32px; }
            .cart-item .ci-name { font-size: .7rem; }
            .cart-item .ci-fields input { width: 40px; font-size: .8rem; padding: .25rem; }
            .cart-item .ci-sub { font-size: .7rem; min-width: 50px; }
            .prod-grid { grid-template-columns: 1fr 1fr; gap: .4rem; }
        }
        @media(max-width:375px){
            .page { padding: .5rem .3rem; }
            .prod-grid { grid-template-columns: 1fr; }
            .cart-item .ci-name { font-size: 0.65rem; }
            .btn-submit { padding: 0.8rem 1rem; font-size: 0.9rem; }
            .mod-titulo { font-size: 1rem; }
        }

        .ap-toggle-wrap{margin-bottom:1rem;padding:.9rem 1rem;background:rgba(255,167,38,.07);border:1px solid rgba(255,167,38,.22);border-radius:12px;}
        .ap-toggle-label{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin-bottom:.55rem;}
        .ap-toggle{display:flex;gap:.5rem;margin-bottom:.45rem;}
        .ap-opt{flex:1;padding:.55rem .5rem;border:1.5px solid var(--border);border-radius:9px;background:var(--clight);color:var(--ink2);font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:.35rem;}
        .ap-opt.active.adso-btn{border-color:#1565c0;background:rgba(21,101,192,.1);color:#1565c0;}
        .ap-opt.active.personal-btn{border-color:var(--c3);background:rgba(198,113,36,.1);color:var(--c3);}
        .ap-toggle-hint{font-size:.72rem;line-height:1.4;transition:color .2s;}
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
            <a href="perfil.php" class="n-user" title="Mi Perfil">
                <div class="n-avatar">
                    <?php if (!empty($cliente_info['foto_url'])): ?>
                        <img src="<?= htmlspecialchars($cliente_info['foto_url']) ?>" alt="avatar">
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
        <!-- ══ BANNER PROPIETARIO ══ -->
        <div class="wc-banner">
            <div class="wc-left">
                <div>
                    <div class="wc-greeting">Panadería BreadControl</div>
                    <div class="wc-name">Armar <em>Pedido</em></div>
                    <div class="wc-sub">Selecciona los productos y cantidades para tu pedido</div>
                </div>
            </div>
        </div>

        <div class="topbar">
            <div class="mod-titulo"><i class="bi bi-cart-plus"></i> <?= $ped_edit ? 'Editar Pedido #' . str_pad($ped_edit['id_pedido'], 4, '0', STR_PAD_LEFT) : 'Armar Pedido' ?></div>
            <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        
        <div class="card">
            <?php if ($error): ?><div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="msg-success">
                    <i class="bi bi-check-circle-fill" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
                    <h3 style="font-family:'Fraunces',serif; font-size:1.5rem; margin-bottom:.5rem;">¡Pedido Confirmado!</h3>
                    <p><?= $success ?></p>
                    <a href="dashboard.php" class="btn-submit" style="margin: 1.5rem auto 0; text-decoration:none; width:fit-content;">Ver mis pedidos</a>
                </div>
            <?php else: ?>
            
            <div class="panel-izq">
                <div class="sec-sep">1. Selecciona el precio</div>
                <div class="price-tabs" id="price-tabs">
                  <?php foreach ($categorias as $c): ?>
                  <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>" onclick="selPriceTab(this)">
                    $<?= number_format($c['precio_unitario'],0,',','.') ?>
                  </div>
                  <?php endforeach; ?>
                </div>

                <div class="sec-sep">2. Toca un pan para agregarlo</div>
                <div id="prod-catalog">
                  <div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio arriba</div>
                </div>
            </div>
            
            <div class="panel-der">
                <form method="post" id="form-pedido">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <input type="hidden" name="carrito_json" id="carrito-json" value="[]">
                    <input type="hidden" name="bonif_json" id="bonif-json" value="[]">
                    <input type="hidden" name="edit_id" value="<?= $ped_edit['id_pedido'] ?? 0 ?>">
                    
                    <?php if ($es_aprendiz): ?>
                    <div class="ap-toggle-wrap">
                        <div class="ap-toggle-label">Este pedido es para:</div>
                        <div class="ap-toggle">
                            <button type="button" class="ap-opt adso-btn <?= $pedido_para_actual === 'adso' ? 'active' : '' ?>" id="btn-adso" onclick="setPedidoPara('adso')">
                                <i class="bi bi-building"></i> Cuenta ADSO
                            </button>
                            <button type="button" class="ap-opt personal-btn <?= $pedido_para_actual === 'personal' ? 'active' : '' ?>" id="btn-personal" onclick="setPedidoPara('personal')">
                                <i class="bi bi-person"></i> Mi cuenta
                            </button>
                        </div>
                        <div class="ap-toggle-hint" id="ap-hint" style="color:<?= $pedido_para_actual === 'adso' ? '#1565c0' : 'var(--c3)' ?>;">
                            <?= $pedido_para_actual === 'adso'
                                ? 'Tu pedido se cargará a la cuenta del instructor ADSO.'
                                : 'Tu pedido se cargará a tu propia cuenta y lo pagas tú directamente.' ?>
                        </div>
                        <input type="hidden" name="pedido_para" id="pedido_para_input" value="<?= $pedido_para_actual ?>">
                    </div>
                    <?php endif; ?>

                    <div id="delivery-datetime-section" style="display: <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? 'none' : 'grid' ?>; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-size: .75rem; font-weight: 700; color: var(--ink3); text-transform: uppercase; display:block; margin-bottom:.3rem;">Fecha de entrega</label>
                            <input type="date" name="fecha_entrega" id="inp_fecha_entrega" min="<?= $min_fecha ?>" max="<?= date('Y-m-d', strtotime('+3 months')) ?>" value="<?= $ped_edit ? $edit_fecha : $min_fecha ?>" <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? '' : 'required' ?> style="width: 100%; padding: .6rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size:.9rem; color: var(--ink);">
                        </div>
                        <div>
                            <label style="font-size: .75rem; font-weight: 700; color: var(--ink3); text-transform: uppercase; display:block; margin-bottom:.3rem;">Hora de entrega</label>
                            <input type="time" name="hora_entrega" id="inp_hora_entrega" min="07:00" max="20:00" value="<?= $ped_edit ? $edit_hora : '08:00' ?>" <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? '' : 'required' ?> style="width: 100%; padding: .6rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size:.9rem; color: var(--ink);">
                        </div>
                    </div>

                    <div class="cart-section">
                        <div class="cart-title">🛒 Carrito <span class="cart-badge" id="cart-count">0</span></div>
                        <div id="cart-body"><div class="cart-empty">Agrega productos</div></div>
                        <div id="cart-total-bar" style="display:none;">
                            <div class="cart-total">
                                <span>Cobrados: <strong id="ct-und">0</strong></span>
                                <span class="ct-big">$<span id="ct-total">0</span></span>
                            </div>
                        </div>
                        
                        <!-- Bonificación tienda / Ñapa mostrador -->
                        <div id="bonif-panel" style="display:none;margin-top:.5rem;">
                            <div id="bonif-card" style="border-radius:10px;padding:.7rem .85rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;flex-wrap:wrap;gap:.3rem;">
                                    <span id="bonif-titulo" style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪 Bonificación tienda</span>
                                    <span id="bonif-credito-lbl" style="font-size:.78rem;font-weight:700;color:#1565c0;">Crédito: <strong>$<span id="bonif-credito">0</span></strong></span>
                                </div>
                                <div style="font-size:.68rem;color:#1565c0;margin-bottom:.4rem;" id="bonif-hint"></div>
                                <div id="bonif-varieties" style="max-height:180px;overflow-y:auto;margin-bottom:.4rem;">
                                    <div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Cargando...</div>
                                </div>
                                <div id="bonif-status" style="font-size:.75rem;font-weight:700;text-align:center;padding:.3rem;border-radius:7px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="guardar_pedido" class="btn-submit" id="btn-pedido" disabled>
                        <i class="bi bi-send-fill"></i> Enviar Pedido
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="<?= APP_URL ?>/assets/js/utils.js"></script>
    <script>
    function setPedidoPara(val) {
        document.getElementById('pedido_para_input').value = val;
        document.getElementById('btn-adso').classList.toggle('active', val === 'adso');
        document.getElementById('btn-personal').classList.toggle('active', val === 'personal');
        var hint = document.getElementById('ap-hint');
        var dtSection = document.getElementById('delivery-datetime-section');
        var inpFecha = document.getElementById('inp_fecha_entrega');
        var inpHora = document.getElementById('inp_hora_entrega');

        if (val === 'adso') {
            hint.textContent = 'Tu pedido se cargará a la cuenta del instructor ADSO.';
            hint.style.color = '#1565c0';
            esTienda = esAprendiz ? false : true;
            if (dtSection) dtSection.style.display = 'none';
            if (inpFecha) inpFecha.removeAttribute('required');
            if (inpHora) inpHora.removeAttribute('required');
        } else {
            hint.textContent = 'Tu pedido se cargará a tu propia cuenta y lo pagas tú directamente.';
            hint.style.color = 'var(--c3)';
            esTienda = false;
            if (dtSection) dtSection.style.display = 'grid';
            if (inpFecha) inpFecha.setAttribute('required', 'required');
            if (inpHora) inpHora.setAttribute('required', 'required');
        }
        checkBonif();
    }

    var appUrl = '<?= APP_URL ?>';
    var cart = <?= json_encode($cart_preload) ?>;
    var bonifPreload = <?= json_encode($bonif_preload) ?>;
    var catalogVars = [];
    var currentPrice = 0;
    var currentCatId = 0;
    
    var esAprendiz = <?= $es_aprendiz ? 'true' : 'false' ?>;
    var esTienda = <?= ($es_tienda && !$es_aprendiz) ? 'true' : 'false' ?>;
    var bonifCredito = 0;
    var bonifLoaded = false;
    var allVarieties = [];

    function selPriceTab(el) {
        document.querySelectorAll('#price-tabs .price-tab').forEach(t=>t.classList.remove('active'));
        el.classList.add('active');
        currentCatId = parseInt(el.dataset.id);
        currentPrice = parseFloat(el.dataset.precio);
        loadCatalog(currentCatId);
    }

    function loadCatalog(catId) {
        var catalog = document.getElementById('prod-catalog');
        catalog.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
        fetch('?ajax_variedades=1&id_cat=' + catId)
            .then(r => r.json())
            .then(vars => {
                catalogVars = vars;
                if(vars.length===0) {
                    catalog.innerHTML = '<div style="text-align:center;padding:.6rem;font-size:.78rem;color:var(--ink3);">Sin variedades</div>';
                    return;
                }
                var html = '<div class="prod-grid">';
                vars.forEach(v => {
                    var inCart = cart.find(x => x.id_variedad==v.id_variedad);
                    var cls = inCart ? 'prod-card in-cart' : 'prod-card';
                    var imgHtml = v.imagen ? '<img src="'+appUrl+'/'+v.imagen+'">' : '<div class="pc-placeholder">🍞</div>';
                    html += '<div class="'+cls+'" id="pcard-'+v.id_variedad+'">'
                        + '<div onclick="tapProduct('+v.id_variedad+')">'
                        + imgHtml
                        + '<div class="pc-action">'+(inCart?'✅ En carrito':'<i class="bi bi-plus-circle-fill"></i> Agregar')+'</div>'
                        + '<div class="pc-name" title="'+escHtml(v.nombre)+'">'+escHtml(v.nombre)+'</div>'
                        + '</div>'
                        + '<div class="pc-form">'
                        + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="99" value="1" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2)" onclick="event.stopPropagation()"></div>'
                        + '<button type="button" class="pf-add" onclick="event.stopPropagation();addToCart('+v.id_variedad+')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
                        + '</div>'
                        + '</div>';
                });
                html += '</div>';
                catalog.innerHTML = html;
            });
    }

    function tapProduct(idVar) {
        if(cart.find(x=>x.id_variedad==idVar)) return;
        var pcard = document.getElementById('pcard-'+idVar);
        if(!pcard) return;
        document.querySelectorAll('#prod-catalog .prod-card.expanded').forEach(c=>{if(c!==pcard) c.classList.remove('expanded')});
        pcard.classList.toggle('expanded');
    }

    function addToCart(idVar) {
        var v = catalogVars.find(x=>x.id_variedad==idVar);
        if(!v) return;
        var pcard = document.getElementById('pcard-'+idVar);
        var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
        if(cant <= 0) return;
        if(cant > 99) cant = 99; // Límite de 99
        cart.push({id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: currentPrice, cantidad: cant});
        pcard.classList.remove('expanded');
        pcard.classList.add('in-cart');
        pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
        renderCart();
    }

    function removeFromCart(idVar) {
        cart = cart.filter(x=>x.id_variedad != idVar);
        var pcard = document.getElementById('pcard-'+idVar);
        if(pcard){ pcard.classList.remove('in-cart'); pcard.classList.remove('expanded'); pcard.querySelector('.pc-action').innerHTML='<i class="bi bi-plus-circle-fill"></i> Agregar'; }
        renderCart();
    }

    function renderCart() {
        var body = document.getElementById('cart-body');
        document.getElementById('cart-count').textContent = cart.length;
        var btn = document.getElementById('btn-pedido');
        var totalBar = document.getElementById('cart-total-bar');

        if(cart.length===0){
            body.innerHTML='<div class="cart-empty">Agrega productos</div>';
            totalBar.style.display='none';
            btn.disabled=true;
            document.getElementById('carrito-json').value='[]';
            checkBonif();
            return;
        }

        var html = '<div class="cart-list">';
        var totalUnd=0, totalDinero=0;
        cart.forEach(item => {
            var sub = item.cantidad * item.precio;
            totalUnd+=item.cantidad; totalDinero+=sub;
            var imgHtml = item.imagen ? '<img src="'+appUrl+'/'+item.imagen+'">' : '<div class="ci-ph">🍞</div>';
            html += '<div class="cart-item">'
                + imgHtml
                + '<div class="ci-info"><div class="ci-name">'+escHtml(item.nombre)+'</div><div class="ci-price">$'+item.precio.toLocaleString('es-CO')+'</div></div>'
                + '<div class="ci-fields"><input type="number" min="1" max="99" value="'+item.cantidad+'" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2); updateItem('+item.id_variedad+',this.value)"></div>'
                + '<div class="ci-sub">$'+sub.toLocaleString('es-CO')+'</div>'
                + '<button type="button" class="ci-del" onclick="removeFromCart('+item.id_variedad+')"><i class="bi bi-x-lg"></i></button>'
                + '</div>';
        });
        html += '</div>';
        body.innerHTML = html;
        document.getElementById('ct-und').textContent = totalUnd;
        document.getElementById('ct-total').textContent = totalDinero.toLocaleString('es-CO');
        totalBar.style.display='block';
        btn.disabled=false;
        document.getElementById('carrito-json').value=JSON.stringify(cart);
        checkBonif();
    }

    function updateItem(idVar, val){
        var item = cart.find(x=>x.id_variedad===idVar);
        if(item){ 
            item.cantidad = Math.max(1, parseInt(val)||1);
            if(item.cantidad > 99) item.cantidad = 99; // Límite de 99
        }
        renderCart();
    }

    function checkBonif() {
        var panel = document.getElementById('bonif-panel');
        var card = document.getElementById('bonif-card');
        var titulo = document.getElementById('bonif-titulo');
        var hint = document.getElementById('bonif-hint');

        if(cart.length===0) {
            panel.style.display='none';
            bonifCredito=0;
            document.getElementById('bonif-json').value='[]';
            return;
        }

        var totalDinero=0;
        cart.forEach(it=>totalDinero+=it.cantidad*it.precio);

        if(esTienda) {
            bonifCredito = Math.floor(totalDinero/5000)*1000;
            card.style.background='rgba(21,101,192,.06)'; card.style.borderColor='rgba(21,101,192,.18)';
            titulo.style.color='#1565c0'; titulo.innerHTML='🏪 Bonificación tienda';
            hint.style.color='#1565c0'; hint.innerHTML='Tienda: $1.000 de crédito por cada $5.000. Escoge tu pan bonificado.';
        } else {
            bonifCredito = Math.floor(totalDinero/5000)*500;
            card.style.background='rgba(198,113,36,.06)'; card.style.borderColor='rgba(198,113,36,.2)';
            titulo.style.color='#c67124'; titulo.innerHTML='🎁 Ñapa mostrador';
            hint.style.color='#c67124'; hint.innerHTML='Mostrador: $500 de crédito por cada $5.000. Escoge tu pan de ñapa.';
        }

        document.getElementById('bonif-credito').textContent = bonifCredito.toLocaleString('es-CO');

        if(bonifCredito<=0) {
            panel.style.display='none';
            document.getElementById('bonif-json').value='[]';
            return;
        }
        panel.style.display='block';
        if(!bonifLoaded) loadAllVarieties();
        else updateBonifStatus();
    }

    function loadAllVarieties() {
        fetch('?ajax_all_variedades=1')
            .then(r=>r.json())
            .then(vars => {
                allVarieties = vars;
                bonifLoaded = true;
                renderBonifVars();
            });
    }

    function renderBonifVars() {
        var container = document.getElementById('bonif-varieties');
        if(allVarieties.length===0){
            container.innerHTML = '<div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Sin variedades</div>';
            return;
        }
        var html = '';
        allVarieties.forEach(v => {
            var imgHtml = v.imagen ? '<img src="'+appUrl+'/'+v.imagen+'">' : '<div class="br-ph">🍞</div>';
            html += '<div class="bonif-row">'
                + imgHtml
                + '<span class="br-name">'+escHtml(v.nombre)+'</span>'
                + '<input type="number" min="0" max="99" value="0" data-bonif-id="'+v.id_variedad+'" data-bonif-precio="'+v.precio_unitario+'" oninput="this.value=this.value.replace(/[^0-9]/g,\'\').slice(0,2); updateBonifStatus(this)">'
                + '</div>';
        });
        container.innerHTML = html;
        updateBonifStatus();
    }

    function updateBonifStatus(triggerInput = null) {
        var inputs = document.querySelectorAll('#bonif-varieties [data-bonif-id]');
        var gastado = 0, totalUnd = 0, items = [];
        
        inputs.forEach(inp => {
            var val = parseInt(inp.value)||0;
            var pr = parseFloat(inp.dataset.bonifPrecio)||0;
            if(val > 0) gastado += val*pr;
        });

        if (triggerInput && gastado > bonifCredito) {
            var prTrigger = parseFloat(triggerInput.dataset.bonifPrecio)||0;
            if (prTrigger > 0) {
                var valActual = parseInt(triggerInput.value)||0;
                var gastadoSinEste = gastado - (valActual * prTrigger);
                var maxPermitido = Math.floor((bonifCredito - gastadoSinEste) / prTrigger);
                if (maxPermitido < 0) maxPermitido = 0;
                triggerInput.value = maxPermitido;
                return updateBonifStatus();
            }
        }

        gastado = 0; 
        inputs.forEach(inp => {
            var val = parseInt(inp.value)||0;
            var pr = parseFloat(inp.dataset.bonifPrecio)||0;
            if(val > 0){
                gastado += val*pr; totalUnd += val;
                items.push({id_variedad: parseInt(inp.dataset.bonifId), cantidad: val, precio: pr});
            }
        });

        var status = document.getElementById('bonif-status');
        var pg = '$'+gastado.toLocaleString('es-CO');
        var pd = '$'+bonifCredito.toLocaleString('es-CO');
        if(gastado === bonifCredito){
            status.textContent = '✅ '+pg+'/'+pd+' · '+totalUnd+' unid.';
            status.style.background='rgba(46,125,50,.1)'; status.style.color='#2e7d32';
        } else if(gastado > bonifCredito){
            status.textContent = '⚠️ '+pg+'/'+pd+' — te pasas $'+(gastado-bonifCredito).toLocaleString('es-CO');
            status.style.background='rgba(198,40,40,.1)'; status.style.color='#c62828';
        } else {
            status.textContent = '📝 '+pg+'/'+pd+' — quedan $'+(bonifCredito-gastado).toLocaleString('es-CO');
            status.style.background= esTienda ? 'rgba(21,101,192,.08)' : 'rgba(198,113,36,.08)'; 
            status.style.color= esTienda ? '#1565c0' : '#c67124';
        }
        document.getElementById('bonif-json').value = JSON.stringify(items);
        document.getElementById('btn-pedido').disabled = (gastado > bonifCredito);
    }

    document.addEventListener("DOMContentLoaded", function() {
        if (cart.length > 0) {
            renderCart();
            let firstTab = document.querySelector('.price-tab');
            if (firstTab) firstTab.click();
            
            var checkInt = setInterval(function() {
                if (bonifLoaded) {
                    clearInterval(checkInt);
                    Object.keys(bonifPreload).forEach(function(idv) {
                        var inp = document.querySelector('#bonif-varieties [data-bonif-id="'+idv+'"]');
                        if (inp) inp.value = bonifPreload[idv];
                    });
                    updateBonifStatus();
                }
            }, 100);
        }
    });
    </script>
</body>
</html>
