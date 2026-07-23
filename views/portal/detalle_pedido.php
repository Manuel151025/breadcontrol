<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido — BreadControl</title>
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

        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
        @keyframes pulse-pay{0%,100%{box-shadow:0 4px 20px rgba(46,125,50,.35);}50%{box-shadow:0 6px 28px rgba(46,125,50,.55);}}
        @keyframes pulse-monto{0%,100%{transform:scale(1);}50%{transform:scale(1.02);}}

        .page { margin-top:var(--nav-h); padding:1rem; width:100%; max-width:850px; margin-left:auto; margin-right:auto; animation:fadeUp .4s ease both; }

        .wc-banner { background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%); background-size:300% 300%; animation:gradAnim 8s ease infinite; border-radius:14px; padding:.9rem 1.4rem; display:flex; align-items:center; justify-content:space-between; box-shadow:var(--shadow2); gap:1rem; flex-wrap:wrap; margin-bottom:.5rem; }
        .wc-left { display:flex; align-items:center; gap:.9rem; }
        .wc-greeting { font-size:.65rem; text-transform:uppercase; letter-spacing:.2em; color:rgba(255,255,255,.65); margin-bottom:.15rem; }
        .wc-name { font-family:'Fraunces',serif; font-size:1.35rem; font-weight:800; color:#fff; line-height:1.1; }
        .wc-name em { font-style:italic; color:var(--c5); }
        .wc-sub { font-size:.72rem; color:rgba(255,255,255,.62); margin-top:.15rem; }

        .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;margin-bottom:1.5rem;margin-top:.8rem;}
        .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
        .mod-titulo i{color:var(--c3);}
        .btn-back{background:var(--ccard);color:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;}
        .btn-back:hover{background:var(--clight);border-color:var(--c3);color:var(--c3);}
        .btn-edit{background:var(--c3);color:#fff;border:none;border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:700;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;box-shadow:var(--shadow2);}
        .btn-edit:hover{transform:translateY(-2px);box-shadow:0 6px 15px rgba(198,113,36,.3);}

        .card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); margin-bottom:1.5rem; overflow:hidden; }

        .ped-header { display: flex; justify-content: space-between; align-items: center; padding: 1.5rem; background: var(--clight); border-bottom: 1px solid var(--border); gap: 1rem; }
        .ped-info { flex: 1; }
        .ped-title { font-family:'Fraunces',serif; font-size: 1.5rem; font-weight: 800; color: var(--ink); margin-bottom: 0.2rem; }

        .estado { font-size: 0.75rem; font-weight: 800; padding: 0.3rem 0.8rem; border-radius: 20px; text-transform: uppercase; letter-spacing:0.05em; display:inline-flex; align-items:center; gap:0.4rem;}
        .estado::before { content:''; width:8px; height:8px; border-radius:50%; background:currentColor; }
        .e-pendiente { background: rgba(255,167,38,.12); color: #e65100; border:1px solid rgba(255,167,38,.35);}
        .e-confirmado { background: rgba(76,175,80,.12); color: #2e7d32; border:1px solid rgba(76,175,80,.25);}
        .e-rechazado { background: rgba(229,57,53,.12); color: #c62828; border:1px solid rgba(229,57,53,.2);}

        .r-body { padding: 1.5rem; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.2rem; margin-bottom: 2rem; background: var(--clight); padding: 1.2rem; border-radius: 12px; border: 1px solid var(--border); }
        .info-grid .info-item { display: flex; flex-direction: column; gap: 0.3rem; }
        .info-grid .info-item span { font-size: 0.75rem; color: var(--ink3); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 700; }
        .info-grid .info-item strong { font-size: 1rem; color: var(--ink); }

        .msg-box { background: rgba(198,113,36,0.06); border-left: 4px solid var(--c3); padding: 1rem 1.2rem; border-radius: 8px; margin-bottom: 2rem; color: var(--ink2); font-size: 0.9rem; line-height: 1.5; }
        .msg-box strong { display: block; color: var(--c3); font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem; }

        .alert-info-ped { background: #fff8e1; border: 1px solid #ffe082; border-left: 4px solid #ffb300; padding: 1rem; border-radius: 10px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.8rem; color: #856404; font-size: 0.85rem; }
        .alert-info-ped i { font-size: 1.2rem; }

        .btn-cancel { background: #fff; color: #d32f2f; border: 1px solid #ef9a9a; border-radius: 10px; padding: 0.4rem 0.8rem; font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 0.4rem; text-decoration: none; transition: all 0.2s; }
        .btn-cancel:hover { background: #ffebee; border-color: #d32f2f; }

        .section-title { font-family:'Fraunces',serif; font-size: 1.1rem; font-weight: 800; color: var(--ink); margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.6rem; }
        .section-title::after { content: ''; flex: 1; height: 1px; background: var(--border); }

        .d-list { display: flex; flex-direction: column; gap: 0.8rem; margin-bottom: 2rem; }
        .d-item { display: flex; justify-content: space-between; align-items: center; padding: 0.8rem 1rem; background: #fff; border: 1px solid var(--border); border-radius: 10px; transition: transform 0.2s, border-color 0.2s; }
        .d-item:hover { transform: translateX(5px); border-color: var(--c3); }
        .d-name { font-weight: 600; color: var(--ink); display: flex; flex-direction: column; }
        .d-qty { font-family: 'Fraunces', serif; font-weight: 800; color: var(--c1); background: var(--clight); padding: 0.3rem 0.6rem; border-radius: 8px; font-size: 0.9rem; }
        .d-badge { font-size: 0.65rem; font-weight: 700; text-transform: uppercase; padding: 0.1rem 0.4rem; border-radius: 4px; margin-top: 0.2rem; display: inline-block; width: fit-content; }
        .b-napa { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
        .b-bonif { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }

        .r-total { display: flex; justify-content: space-between; align-items: center; padding-top: 1.5rem; border-top: 2px dashed var(--border); margin-top: 1rem; }
        .t-lbl { font-family: 'Fraunces', serif; font-size: 1.2rem; font-weight: 800; color: var(--ink3); }
        .t-val { font-family: 'Fraunces', serif; font-size: 1.8rem; font-weight: 800; color: var(--c1); }

        /* ===== PAGO DIGITAL ===== */
        .pago-card-cta { margin-top: 1.5rem; background: linear-gradient(135deg, #f1f8e9, #fff); border: 2px solid var(--pago-green-bd); border-radius: 14px; padding: 1.4rem; }
        .pago-card-cta h4 { font-family:'Fraunces',serif; font-size: 1.15rem; color: var(--pago-green-dk); margin-bottom: .3rem; display:flex; align-items:center; gap:.5rem; }
        .pago-card-cta .desc { font-size: .88rem; color: var(--ink2); margin-bottom: 1.2rem; line-height: 1.5; }

        .monto-destacado { background: linear-gradient(135deg, var(--pago-green), var(--pago-green-dk)); color: #fff; border-radius: 14px; padding: 1.2rem 1.4rem; text-align: center; margin-bottom: 1rem; animation: pulse-monto 3s ease infinite; box-shadow: 0 4px 20px rgba(46,125,50,.25); }
        .monto-destacado .lbl-monto { font-size: .7rem; text-transform: uppercase; letter-spacing: .15em; opacity: .85; margin-bottom: .35rem; font-weight: 700; }
        .monto-destacado .val-monto { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
        .monto-destacado .sub-monto { font-size: .78rem; opacity: .9; margin-top: .5rem; font-weight: 600; }

        .pago-pasos { background: #fff; border: 1px solid var(--pago-green-bd); border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1.2rem; }
        .pago-pasos .titulo { font-size: .72rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: var(--pago-green-dk); margin-bottom: .7rem; }
        .pago-pasos ol { margin-left: 1.2rem; padding-left: 0; }
        .pago-pasos ol li { font-size: .85rem; color: var(--ink); line-height: 1.5; margin-bottom: .35rem; }
        .pago-pasos ol li strong { color: var(--pago-green-dk); }

        .btn-pagar-ahora { width:100%; display:flex; align-items:center; justify-content:center; gap:.6rem; background: linear-gradient(135deg, var(--pago-green), var(--pago-green-dk)); color:#fff; border:none; border-radius: 12px; padding: 1.1rem 1.4rem; font-size: 1.1rem; font-weight: 800; text-decoration:none; transition: all .25s; animation: pulse-pay 2.5s ease infinite; }
        .btn-pagar-ahora:hover { transform: translateY(-2px); animation: none; box-shadow: 0 8px 30px rgba(46,125,50,.45); }
        .btn-pagar-ahora i { font-size: 1.3rem; }

        .pago-medios { font-size: .72rem; color: var(--ink3); text-align: center; margin-top: .7rem; }
        .pago-medios strong { color: var(--ink2); }

        .pago-aviso-postpago { background: #fff8e1; border-left: 3px solid #ffb300; padding: .75rem 1rem; border-radius: 8px; font-size: .78rem; color: #856404; margin-top: 1rem; line-height: 1.45; }
        .pago-aviso-postpago i { margin-right: .3rem; }

        /* ===== CUENTA TIENDA ===== */
        .cuenta-tienda-card { margin-top: 1.5rem; background: linear-gradient(135deg, #e8eaf6, #fff); border: 2px solid #9fa8da; border-radius: 14px; padding: 1.4rem; }
        .cuenta-tienda-card h4 { font-family:'Fraunces',serif; font-size: 1.15rem; color: #283593; margin-bottom: .3rem; display:flex; align-items:center; gap:.5rem; }
        .cuenta-tienda-card .desc { font-size: .88rem; color: var(--ink2); margin-bottom: 1.2rem; line-height: 1.5; }
        .monto-tienda { background: linear-gradient(135deg, #283593, #1a237e); color: #fff; border-radius: 14px; padding: 1.2rem 1.4rem; text-align: center; margin-bottom: 1rem; box-shadow: 0 4px 20px rgba(40,53,147,.25); }
        .monto-tienda .lbl-monto { font-size: .7rem; text-transform: uppercase; letter-spacing: .15em; opacity: .85; margin-bottom: .35rem; font-weight: 700; }
        .monto-tienda .val-monto { font-family: 'Fraunces', serif; font-size: 2.4rem; font-weight: 800; line-height: 1; letter-spacing: -.02em; }
        .cuenta-datos { background: #fff; border: 1px solid #9fa8da; border-radius: 12px; padding: 1rem 1.2rem; margin-bottom: 1rem; }
        .cuenta-datos .cuenta-lbl { font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .1em; color: #283593; margin-bottom: .6rem; }
        .cuenta-datos .cuenta-fila { display: flex; align-items: center; gap: .6rem; padding: .5rem 0; border-bottom: 1px solid rgba(159,168,218,.3); font-size: .88rem; color: var(--ink); }
        .cuenta-datos .cuenta-fila:last-child { border-bottom: none; }
        .cuenta-datos .cuenta-fila i { color: #283593; font-size: 1rem; width: 18px; flex-shrink: 0; }
        .cuenta-datos .cuenta-fila strong { color: #1a237e; }
        .aviso-tienda { background: #e8eaf6; border-left: 3px solid #283593; padding: .75rem 1rem; border-radius: 8px; font-size: .78rem; color: #283593; margin-top: 1rem; line-height: 1.45; }

        .pago-card-pagado { margin-top: 1.5rem; background: var(--pago-green-bg); border: 1px solid var(--pago-green-bd); border-radius: 12px; padding: 1.2rem 1.4rem; display:flex; gap: 1rem; align-items:center; flex-wrap: wrap; }
        .pago-check-circle { width: 48px; height: 48px; border-radius: 50%; background: var(--pago-green); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.5rem; flex-shrink:0; }
        .pago-card-pagado .info { flex: 1; min-width: 180px; }
        .pago-card-pagado .info h4 { font-family:'Fraunces',serif; font-size: 1.1rem; color: var(--pago-green-dk); margin-bottom: .2rem; }
        .pago-card-pagado .info .sub { font-size: .82rem; color: var(--ink2); line-height: 1.45; }
        .pago-card-pagado .info .sub strong { color: var(--pago-green-dk); }

        /* ===== REPORTE TIENDA ===== */
        .reporte-card { background:var(--ccard); border:1px solid var(--border); border-radius:14px; box-shadow:var(--shadow); margin-top:1.5rem; overflow:hidden; }
        .reporte-header { background:linear-gradient(125deg,#1a237e 0%,#283593 40%,#3949ab 100%); padding:1.1rem 1.4rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.8rem; }
        .reporte-header-title { font-family:'Fraunces',serif; font-size:1.15rem; font-weight:800; color:#fff; display:flex; align-items:center; gap:.5rem; }
        .reporte-header-sub { font-size:.72rem; color:rgba(255,255,255,.7); margin-top:.2rem; }
        .reporte-export-btns { display:flex; gap:.5rem; flex-wrap:wrap; }
        .btn-export { display:inline-flex; align-items:center; gap:.4rem; padding:.5rem .9rem; border-radius:9px; font-size:.78rem; font-weight:700; text-decoration:none; transition:all .2s; border:none; cursor:pointer; }
        .btn-export-excel { background:#1d6f42; color:#fff; }
        .btn-export-excel:hover { background:#155634; transform:translateY(-1px); }
        .btn-export-pdf { background:#c62828; color:#fff; }
        .btn-export-pdf:hover { background:#b71c1c; transform:translateY(-1px); }

        .reporte-body { padding:1.2rem 1.4rem; }
        .aprendiz-block { margin-bottom:1.5rem; border:1px solid rgba(57,73,171,.15); border-radius:12px; overflow:hidden; }
        .aprendiz-block:last-child { margin-bottom:0; }
        .aprendiz-nombre { background:rgba(57,73,171,.08); padding:.65rem 1rem; display:flex; align-items:center; gap:.5rem; font-weight:800; font-size:.92rem; color:#1a237e; border-bottom:1px solid rgba(57,73,171,.12); }
        .aprendiz-nombre i { font-size:1rem; color:#3949ab; }
        .rep-table { width:100%; border-collapse:collapse; }
        .rep-table th { font-size:.62rem; text-transform:uppercase; letter-spacing:.1em; color:var(--ink3); font-weight:700; padding:.6rem .9rem; background:var(--clight); border-bottom:1px solid var(--border); text-align:left; }
        .rep-table th:last-child { text-align:right; }
        .rep-table td { font-size:.83rem; color:var(--ink); padding:.6rem .9rem; border-bottom:1px solid rgba(148,91,53,.05); vertical-align:middle; }
        .rep-table tr:last-child td { border-bottom:none; }
        .rep-table tr:hover td { background:rgba(250,243,234,.5); }
        .rep-table td:last-child { text-align:right; font-family:'Fraunces',serif; font-weight:700; color:var(--c1); }
        .rep-badge-napa { font-size:.6rem; font-weight:700; background:#fff3e0; color:#e65100; border:1px solid #ffe0b2; border-radius:4px; padding:.1rem .35rem; margin-left:.3rem; }
        .rep-badge-bonif { font-size:.6rem; font-weight:700; background:#e3f2fd; color:#1565c0; border:1px solid #bbdefb; border-radius:4px; padding:.1rem .35rem; margin-left:.3rem; }
        .aprendiz-subtotal { padding:.5rem 1rem; background:rgba(57,73,171,.04); display:flex; justify-content:flex-end; align-items:center; gap:.6rem; border-top:1px dashed rgba(57,73,171,.2); font-size:.83rem; color:#283593; font-weight:700; }
        .reporte-total-general { margin-top:1.2rem; background:linear-gradient(135deg,#1a237e,#283593); color:#fff; border-radius:12px; padding:1rem 1.4rem; display:flex; justify-content:space-between; align-items:center; }
        .reporte-total-general .lbl { font-size:.75rem; text-transform:uppercase; letter-spacing:.1em; opacity:.85; font-weight:700; }
        .reporte-total-general .val { font-family:'Fraunces',serif; font-size:1.8rem; font-weight:800; }

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

            .ped-header { flex-direction: column; align-items: flex-start; padding: 1.2rem; }
            .t-val { font-size: 1.4rem; }
            .monto-destacado .val-monto { font-size: 2rem; }
            .btn-pagar-ahora { font-size: 1rem; padding: 1rem 1.1rem; }
        }

        @media(max-width:500px){
            .page { padding: .75rem .4rem; }
            .topbar { flex-direction: column; align-items: stretch; gap: .75rem; }
            .mod-titulo { font-size: 1.15rem; width: 100%; justify-content: space-between; }
            .btn-back, .btn-edit { width: 100%; justify-content: center; }
            .info-grid { grid-template-columns: 1fr; }
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
        <div class="wc-banner">
            <div class="wc-left">
                <div>
                    <div class="wc-greeting">Panadería BreadControl</div>
                    <div class="wc-name">Detalle de <em>Pedido</em></div>
                    <div class="wc-sub">Visualiza los productos y el estado de tu pedido</div>
                </div>
            </div>
        </div>

        <div class="topbar">
            <div class="mod-titulo"><i class="bi bi-receipt"></i> Detalles del Pedido</div>
            <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>

        <div class="card">
            <div class="ped-header">
                <div class="ped-info">
                    <div class="ped-title">Pedido #<?= str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?></div>
                    <div style="display:flex; align-items:center; gap: 0.8rem; margin-top:0.6rem; flex-wrap:wrap;">
                        <span class="estado e-<?= $pedido['estado'] ?>"><?= $pedido['estado'] ?></span>
                        <?php if($puede_gestionar): ?>
                            <a href="nuevo_pedido.php?edit_id=<?= $pedido['id_pedido'] ?>" class="btn-edit" style="padding: 0.4rem 0.8rem; font-size: 0.75rem;">
                                <i class="bi bi-pencil-square"></i> Editar
                            </a>
                            <a href="#" onclick="cancelarPedido(<?= $pedido['id_pedido'] ?>)" class="btn-cancel">
                                <i class="bi bi-trash"></i> Cancelar
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="r-body">
                <?php if (isset($_GET['error'])): ?>
                    <div class="alert-info-ped" style="background:#ffebee; border-color:#ef9a9a; color:#c62828; border-left:4px solid #c62828; margin-bottom:1.5rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            <?php if ($_GET['error'] === 'limite_tiempo'): ?>
                                Ya no es posible modificar o cancelar este pedido (menos de 48 horas para la entrega o no está pendiente).
                            <?php elseif ($_GET['error'] === 'pago_proceso'): ?>
                                No puedes modificar o cancelar este pedido porque está vinculado a una transacción de pago activa de tu instructor.
                            <?php else: ?>
                                <?= htmlspecialchars($_GET['error']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if($dentro_limite && $pedido['estado'] === 'pendiente' && !isset($_GET['error'])): ?>
                    <div class="alert-info-ped">
                        <i class="bi bi-info-circle-fill"></i>
                        <div>Este pedido ya no puede ser editado ni cancelado porque faltan menos de 48 horas para su entrega.</div>
                    </div>
                <?php endif; ?>

                <div class="info-grid">
                    <div class="info-item">
                        <span><i class="bi bi-calendar-event"></i> Para entregar el</span>
                            <?= formatearFechaEntrega($pedido['fecha_entrega']) ?>
                        </strong>
                    </div>
                    <div class="info-item">
                        <span><i class="bi bi-clock-history"></i> Solicitado el</span>
                        <strong><?= date('d/m/Y H:i', strtotime($pedido['fecha_solicitud'])) ?></strong>
                    </div>
                    <?php if ($es_aprendiz || (isset($pedido['creador_es_aprendiz']) && (int)$pedido['creador_es_aprendiz'] === 1)): ?>
                    <div class="info-item">
                        <span><i class="bi bi-person-badge"></i> Creado por</span>
                        <strong><?= htmlspecialchars($pedido['nombre_creador'] ?? 'Yo') ?></strong>
                    </div>
                    <div class="info-item">
                        <span><i class="bi bi-journal-check"></i> Dirigido a</span>
                        <strong>
                            <?php if ((int)$pedido['id_cliente'] === (int)$pedido['id_creador']): ?>
                                Mi cuenta (Personal)
                            <?php else: ?>
                                Cuenta ADSO (<?= htmlspecialchars($nombre_tienda) ?>)
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($pedido['mensaje_propietario']): ?>
                    <div class="msg-box">
                        <strong><i class="bi bi-chat-quote-fill"></i> Mensaje de la Panadería</strong>
                        <?= nl2br(htmlspecialchars($pedido['mensaje_propietario'])) ?>
                    </div>
                <?php endif; ?>

                <h3 class="section-title"><i class="bi bi-box-seam"></i> Productos Seleccionados</h3>
                <div class="d-list">
                    <?php foreach ($detalles as $d): ?>
                    <div class="d-item">
                        <span class="d-name">
                            <?= htmlspecialchars($d['producto']) ?>
                            <?php if ($d['napa'] > 0): ?>
                                <span class="d-badge b-napa">🎁 Incluye Ñapa (+<?= $d['napa'] ?>)</span>
                            <?php elseif ($d['bonificacion'] > 0): ?>
                                <span class="d-badge b-bonif">🏪 Bonificación (+<?= $d['bonificacion'] ?>)</span>
                            <?php endif; ?>
                        </span>
                        <span class="d-qty">
                            <?php $total_cant = $d['cantidad'] + $d['napa'] + $d['bonificacion']; echo $total_cant; ?> und
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="r-total">
                    <span class="t-lbl">Total Estimado</span>
                    <span class="t-val">$<?= number_format($pedido['total_estimado'], 0, ',', '.') ?></span>
                </div>

                <!-- ====== PAGO DIGITAL ====== -->

                <?php if ($estado_pago === 'pendiente' && $pago_activo): ?>
                    <?php if ($es_tienda): ?>
                    <!-- Tarjeta de cuenta para clientes tienda (sin botón Wompi) -->
                    <div class="cuenta-tienda-card">
                        <h4><i class="bi bi-bank"></i> Datos para realizar el pago</h4>
                        <div class="desc">
                            Tu pedido ha sido confirmado. Realiza el pago a la cuenta de la panadería usando los datos que aparecen a continuación.
                        </div>

                        <div class="monto-tienda">
                            <div class="lbl-monto"><i class="bi bi-cash-coin"></i> Monto a pagar</div>
                            <div class="val-monto">$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></div>
                        </div>

                        <div class="cuenta-datos">
                            <div class="cuenta-lbl"><i class="bi bi-person-vcard"></i> Cuenta de la panadería</div>
                            <?php if (!empty($titular_negocio)): ?>
                            <div class="cuenta-fila">
                                <i class="bi bi-person-fill"></i>
                                <span>Titular: <strong><?= htmlspecialchars($titular_negocio) ?></strong></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($nequi_link_pago)): ?>
                            <div class="cuenta-fila">
                                <i class="bi bi-phone-fill"></i>
                                <span>Nequi Negocios: <strong style="font-family:monospace; font-size:.82rem; word-break:break-all;"><?= htmlspecialchars($nequi_link_pago) ?></strong></span>
                            </div>
                            <?php endif; ?>
                            <div class="cuenta-fila">
                                <i class="bi bi-cash-coin"></i>
                                <span>Monto exacto: <strong>$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></strong></span>
                            </div>
                        </div>

                        <div class="aviso-tienda">
                            <i class="bi bi-info-circle-fill"></i>
                            Una vez realizado el pago, comunícate con la panadería para confirmar la transacción. Esto puede tardar unos minutos.
                        </div>

                        <?php if (!empty($reporte_por_aprendiz)): ?>
                        <div style="margin-top:1rem; padding-top:1rem; border-top:1px solid rgba(159,168,218,.35);">
                            <div style="font-size:.72rem; font-weight:700; text-transform:uppercase; letter-spacing:.1em; color:#283593; margin-bottom:.6rem;">
                                <i class="bi bi-clipboard2-data"></i> Reporte detallado de panes
                            </div>
                            <?php if ($todos_confirmados): ?>
                            <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                                <a href="exportar_reporte_tienda.php?id=<?= $id_pedido ?>&formato=excel" target="_blank" class="btn-export btn-export-excel">
                                    <i class="bi bi-file-earmark-excel-fill"></i> Exportar Excel
                                </a>
                                <a href="exportar_reporte_tienda.php?id=<?= $id_pedido ?>&formato=pdf" target="_blank" class="btn-export btn-export-pdf">
                                    <i class="bi bi-file-earmark-pdf-fill"></i> Exportar PDF
                                </a>
                            </div>
                            <?php else: ?>
                            <div style="background:#fff8e1; border-left:3px solid #ffb300; border-radius:8px; padding:.65rem .9rem; font-size:.8rem; color:#856404; display:flex; align-items:center; gap:.5rem;">
                                <i class="bi bi-hourglass-split"></i>
                                Aún hay <strong><?= $pendientes_count ?> pedido<?= $pendientes_count > 1 ? 's' : '' ?> pendiente<?= $pendientes_count > 1 ? 's' : '' ?></strong> de confirmar. Los botones se habilitarán cuando el propietario confirme todos.
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php elseif (!$orden_es_de_tienda): ?>
                    <!-- Tarjeta Wompi para clientes normales -->
                    <div class="pago-card-cta">
                        <h4><i class="bi bi-credit-card-2-front"></i> Tu pedido está listo para pagar</h4>
                        <div class="desc">
                            Paga este pedido de forma rápida y segura desde tu app de Nequi, Bancolombia o cualquier banco.
                        </div>

                        <div class="monto-destacado">
                            <div class="lbl-monto"><i class="bi bi-cash-coin"></i> Monto exacto a pagar</div>
                            <div class="val-monto">$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></div>
                            <div class="sub-monto"><i class="bi bi-info-circle"></i> Digita este monto en el checkout</div>
                        </div>

                        <div class="pago-pasos">
                            <div class="titulo"><i class="bi bi-list-check"></i> Cómo pagar</div>
                            <ol>
                                <li>Toca el botón <strong>Pagar ahora</strong>.</li>
                                <li>En el checkout, digita el monto: <strong>$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></strong></li>
                                <li>Elige tu medio de pago (Nequi, Bancolombia, PSE, tarjeta).</li>
                                <li>Completa el pago en tu app.</li>
                            </ol>
                        </div>

                        <?php if ($puede_pagar): ?>
                        <a href="<?= htmlspecialchars($pago_activo['wompi_link_url']) ?>" target="_blank" rel="noopener" class="btn-pagar-ahora">
                            <i class="bi bi-shield-lock-fill"></i>
                            Pagar ahora
                            <i class="bi bi-box-arrow-up-right" style="font-size:.9rem; opacity:.85;"></i>
                        </a>
                        <?php endif; ?>

                        <div class="pago-medios">
                            Aceptamos: <strong>Nequi · Bancolombia · PSE · Tarjeta débito/crédito</strong>
                            <?php if (!empty($titular_negocio)): ?>
                                <br>Pagas a: <strong><?= htmlspecialchars($titular_negocio) ?></strong>
                            <?php endif; ?>
                        </div>

                        <div class="pago-aviso-postpago">
                            <i class="bi bi-info-circle-fill"></i>
                            Una vez completes el pago, la panadería verificará la transacción y actualizará el estado de tu pedido. Esto puede tardar unos minutos.
                        </div>
                    </div>
                    <?php endif; ?>

                <?php elseif ($estado_pago === 'aprobado' && $pago_activo): ?>
                    <div class="pago-card-pagado" style="display: block;">
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                            <div class="pago-check-circle"><i class="bi bi-check-lg"></i></div>
                            <div class="info">
                                <h4>Pago recibido</h4>
                                <div class="sub">
                                    Has completado el pago de <strong>$<?= number_format($pago_activo['monto'], 0, ',', '.') ?> COP</strong>. ¡Muchas gracias!
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="exportar_recibo_pago.php?id=<?= $pedido['id_pedido'] ?>" target="_blank" class="btn-ver" style="display: inline-flex; align-items: center; gap: 0.35rem; border-color: var(--pago-green); color: var(--pago-green); background: rgba(46,125,50,0.05);">
                                <i class="bi bi-file-earmark-pdf-fill"></i> Descargar Recibo PDF
                            </a>
                        </div>


                        <?php if (!empty($abonos)): ?>
                            <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px dashed var(--pago-green-bd);">
                                <div style="font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--pago-green-dk); margin-bottom: .6rem;">
                                    <i class="bi bi-clock-history"></i> Desglose de Abonos
                                </div>
                                <div style="overflow-x:auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid var(--pago-green-bd);">
                                                <th style="padding: 0.4rem 0.5rem; color: var(--pago-green-dk);">Fecha</th>
                                                <th style="padding: 0.4rem 0.5rem; color: var(--pago-green-dk);">Medio</th>
                                                <th style="padding: 0.4rem 0.5rem; color: var(--pago-green-dk); text-align: right;">Monto</th>
                                                <th style="padding: 0.4rem 0.5rem; color: var(--pago-green-dk);">Nota</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($abonos as $ab): ?>
                                                <tr style="border-bottom: 1px solid rgba(46,125,50,.1);">
                                                    <td style="padding: 0.4rem 0.5rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; text-align: right; font-weight: 700; color: var(--pago-green-dk);">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($estado_pago === 'parcial' && $pago_activo): ?>
                    <?php
                    $saldo_restante = $pedido['total_estimado'] - $total_pagado;
                    ?>
                    <div class="pago-card-pagado" style="background:#e0f2fe; border-color:#bae6fd; display: block;">
                        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
                            <div class="pago-check-circle" style="background:#0288d1;"><i class="bi bi-info-lg"></i></div>
                            <div class="info" style="flex: 1; min-width: 180px;">
                                <h4 style="color:#0369a1;">Pago Parcial Recibido</h4>
                                <div class="sub" style="color:#0c4a6e;">
                                    Has abonado un total de <strong>$<?= number_format($total_pagado, 0, ',', '.') ?></strong>. 
                                    Aún queda un saldo pendiente de <strong style="color:#b91c1c;">$<?= number_format($saldo_restante, 0, ',', '.') ?></strong>.
                                    <br><span style="font-size:.78rem; opacity:.85;">Puedes saldar esta diferencia en tu próximo pago consolidado.</span>
                                </div>
                            </div>
                        </div>
                        <div style="margin-top: 1rem; display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="exportar_recibo_pago.php?id=<?= $pedido['id_pedido'] ?>" target="_blank" class="btn-ver" style="display: inline-flex; align-items: center; gap: 0.35rem; border-color: #0288d1; color: #0288d1; background: rgba(2,136,209,0.05);">
                                <i class="bi bi-file-earmark-pdf-fill"></i> Descargar Recibo de Abonos PDF
                            </a>
                        </div>


                        <?php if (!empty($abonos)): ?>
                            <div style="margin-top: 1.2rem; padding-top: 1rem; border-top: 1px dashed #bae6fd;">
                                <div style="font-size: .75rem; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: #0369a1; margin-bottom: .6rem;">
                                    <i class="bi bi-clock-history"></i> Historial de Abonos
                                </div>
                                <div style="overflow-x:auto;">
                                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                                        <thead>
                                            <tr style="border-bottom: 1px solid #bae6fd;">
                                                <th style="padding: 0.4rem 0.5rem; color: #0369a1;">Fecha</th>
                                                <th style="padding: 0.4rem 0.5rem; color: #0369a1;">Medio</th>
                                                <th style="padding: 0.4rem 0.5rem; color: #0369a1; text-align: right;">Monto</th>
                                                <th style="padding: 0.4rem 0.5rem; color: #0369a1;">Nota</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($abonos as $ab): ?>
                                                <tr style="border-bottom: 1px solid rgba(3,105,161,.1);">
                                                    <td style="padding: 0.4rem 0.5rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; text-align: right; font-weight: 700; color: #0369a1;">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                                    <td style="padding: 0.4rem 0.5rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($puede_pagar): ?>
                        <a href="pagar_consolidado.php?id_pedido=<?= $pedido['id_pedido'] ?>" class="btn-pagar-ahora" style="margin-top: 1.2rem; background: linear-gradient(135deg, var(--c3), var(--c1)); text-decoration: none; animation: none; text-align: center;">
                            <i class="bi bi-cash-coin"></i> Pagar saldo restante ($<?= number_format($saldo_restante, 0, ',', '.') ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($puede_pagar && !$pago_activo && $pedido['estado'] !== 'rechazado' && $pedido['estado_pago'] !== 'aprobado'): ?>
                    <div class="pago-card-cta" style="margin-top: 1.5rem; background: linear-gradient(135deg, #fdf6ee, #fff); border: 2px solid var(--border); border-radius: 14px; padding: 1.4rem;">
                        <h4><i class="bi bi-credit-card-2-front"></i> Pagar este pedido</h4>
                        <div class="desc">
                            Para realizar el pago de este pedido individual, genera el enlace de pago a continuación.
                        </div>
                        <a href="pagar_consolidado.php?id_pedido=<?= $pedido['id_pedido'] ?>" class="btn-pagar-ahora" style="background: linear-gradient(135deg, var(--c3), var(--c1)); text-decoration: none; animation: none; text-align: center;">
                            <i class="bi bi-lightning-charge-fill"></i> Pagar este pedido ($<?= number_format($pedido['total_estimado'], 0, ',', '.') ?>)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($es_tienda && !empty($reporte_por_aprendiz)): ?>
        <div class="reporte-card">
            <div class="reporte-header">
                <div>
                    <div class="reporte-header-title"><i class="bi bi-clipboard2-data"></i> Reporte de Panes por Aprendiz</div>
                    <div class="reporte-header-sub">Entrega: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?> &nbsp;·&nbsp; <?= htmlspecialchars($nombre_tienda) ?></div>
                </div>
                <div class="reporte-export-btns">
                    <a href="exportar_reporte_tienda.php?id=<?= $id_pedido ?>&formato=excel" class="btn-export btn-export-excel" target="_blank">
                        <i class="bi bi-file-earmark-excel-fill"></i> Excel
                    </a>
                    <a href="exportar_reporte_tienda.php?id=<?= $id_pedido ?>&formato=pdf" class="btn-export btn-export-pdf" target="_blank">
                        <i class="bi bi-file-earmark-pdf-fill"></i> PDF
                    </a>
                </div>
            </div>

            <div class="reporte-body">
                <?php foreach ($reporte_por_aprendiz as $aprendiz => $productos): ?>
                <div class="aprendiz-block">
                    <div class="aprendiz-nombre">
                        <i class="bi bi-person-fill"></i>
                        <?= htmlspecialchars($aprendiz) ?>
                    </div>
                    <table class="rep-table">
                        <thead>
                            <tr>
                                <th>Pan / Producto</th>
                                <th>Cant. Base</th>
                                <th>Extras</th>
                                <th>Total und</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php
                        $subtotal_und = 0;
                        foreach ($productos as $pr):
                            $total_und = $pr['cantidad'] + $pr['napa'] + $pr['bonificacion'];
                            $subtotal_und += $total_und;
                        ?>
                            <tr>
                                <td><?= htmlspecialchars($pr['producto']) ?></td>
                                <td><?= (int)$pr['cantidad'] ?></td>
                                <td>
                                    <?php if ($pr['napa'] > 0): ?>
                                        <span class="rep-badge-napa">+<?= (int)$pr['napa'] ?> ñapa</span>
                                    <?php elseif ($pr['bonificacion'] > 0): ?>
                                        <span class="rep-badge-bonif">+<?= (int)$pr['bonificacion'] ?> bonif.</span>
                                    <?php else: ?>
                                        <span style="color:var(--ink3); font-size:.75rem;">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= $total_und ?> und</td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="aprendiz-subtotal">
                        <i class="bi bi-box-seam"></i>
                        Total de <?= htmlspecialchars($aprendiz) ?>: <strong><?= $subtotal_und ?> unidades</strong>
                    </div>
                </div>
                <?php endforeach; ?>

                <?php if ($total_general_reporte > 0): ?>
                <div class="reporte-total-general">
                    <span class="lbl"><i class="bi bi-calculator"></i> Total General de la Tienda</span>
                    <span class="val">$<?= number_format($total_general_reporte, 0, ',', '.') ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
    function cancelarPedido(id) {
        if (confirm('¿Estás seguro de que deseas cancelar este pedido? Esta acción no se puede deshacer.')) {
            window.location.href = 'cancelar_pedido.php?id=' + id;
        }
    }
    </script>
</body>
</html>
