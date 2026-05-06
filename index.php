<?php
require_once __DIR__ . '/config/app.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/sesion.php';

if (isset($_SESSION['id_usuario'])) {
    header('Location: ' . APP_URL . '/modules/tablero/index.php');
    exit;
}

try {
    $pdo = getConexion();
    $total_insumos  = (int)$pdo->query("SELECT COUNT(*) FROM insumo WHERE activo = 1")->fetchColumn();
    $insumos_bajos  = (int)$pdo->query("SELECT COUNT(*) FROM insumo WHERE stock_actual <= punto_reposicion AND activo = 1")->fetchColumn();
    $prod_hoy       = (int)$pdo->query("SELECT COUNT(*) FROM produccion WHERE DATE(fecha_produccion) = CURDATE()")->fetchColumn();
    $tandas_hoy     = (float)$pdo->query("SELECT COALESCE(SUM(cantidad_tandas),0) FROM produccion WHERE DATE(fecha_produccion) = CURDATE()")->fetchColumn();
    $ventas_hoy     = (float)$pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
    $num_ventas     = (int)$pdo->query("SELECT COUNT(*) FROM venta WHERE DATE(fecha_hora) = CURDATE()")->fetchColumn();
    $gastos_hoy     = (float)$pdo->query("SELECT COALESCE(SUM(valor),0) FROM gasto WHERE DATE(fecha_gasto) = CURDATE()")->fetchColumn();
    $costo_prod_hoy = (float)$pdo->query("SELECT COALESCE(SUM(cl.costo_consumo),0) FROM consumo_lote cl INNER JOIN produccion pr ON pr.id_produccion=cl.id_produccion WHERE DATE(pr.fecha_produccion)=CURDATE()")->fetchColumn();
    $utilidad_hoy   = $ventas_hoy - $costo_prod_hoy - $gastos_hoy;
    $cierre_hoy     = $pdo->query("SELECT id_cierre FROM cierre_dia WHERE fecha = CURDATE()")->fetchColumn();
    $productos_act  = (int)$pdo->query("SELECT COUNT(*) FROM producto WHERE activo = 1")->fetchColumn();
} catch(Exception $e) {
    $total_insumos = $insumos_bajos = $prod_hoy = $tandas_hoy = 0;
    $ventas_hoy = $num_ventas = $gastos_hoy = $costo_prod_hoy = $utilidad_hoy = 0;
    $cierre_hoy = false; $productos_act = 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BreadControl · Gestión de Panadería</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700;800&family=DM+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root{
      --bg:hsl(24,60%,6%);--fg:hsl(30,30%,90%);
      --card:hsl(24,40%,10%);--card-fg:hsl(30,30%,90%);
      --primary:hsl(27,72%,47%);--primary-fg:hsl(30,30%,95%);
      --muted:hsl(24,30%,15%);--muted-fg:hsl(30,15%,55%);
      --border:hsl(25,30%,20%);--naranja:hsl(27,72%,47%);--miel:hsl(30,67%,65%);--crema:hsl(27,63%,76%);
      --gradient-cta:linear-gradient(135deg,hsl(27,72%,47%),hsl(30,67%,65%));
      --hero-overlay:linear-gradient(135deg,hsla(24,60%,6%,.88),hsla(24,40%,10%,.75));
      --shadow-warm:0 8px 32px -8px hsla(27,72%,47%,.25);
      --glass-bg:hsla(24,40%,10%,.6);--glass-border:hsla(30,30%,90%,.1);
    }
    *{margin:0;padding:0;box-sizing:border-box;}
    html{scroll-behavior:smooth;}
    body{font-family:'DM Sans',system-ui,sans-serif;background:var(--bg);color:var(--fg);-webkit-font-smoothing:antialiased;overflow-x:hidden;}
    h1,h2,h3,h4,h5,h6{font-family:'Playfair Display',Georgia,serif;}
    .container{max-width:1400px;margin:0 auto;padding:0 2rem;}

    /* Glass card */
    .glass-card{background:var(--glass-bg);border:1px solid var(--glass-border);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);}

    /* Gradient text */
    .text-gradient-warm{background:linear-gradient(135deg,var(--naranja),var(--miel));-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}

    /* Scroll animations */
    .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease;}
    .reveal.visible{opacity:1;transform:translateY(0);}
    .reveal-x{opacity:0;transform:translateX(40px);transition:opacity .7s ease,transform .7s ease;}
    .reveal-x.visible{opacity:1;transform:translateX(0);}
    .stagger:nth-child(1){transition-delay:.05s}.stagger:nth-child(2){transition-delay:.11s}.stagger:nth-child(3){transition-delay:.17s}.stagger:nth-child(4){transition-delay:.23s}.stagger:nth-child(5){transition-delay:.29s}.stagger:nth-child(6){transition-delay:.35s}.stagger:nth-child(7){transition-delay:.41s}.stagger:nth-child(8){transition-delay:.47s}.stagger:nth-child(9){transition-delay:.53s}.stagger:nth-child(10){transition-delay:.59s}

    /* ═══ NAV ═══ */
    .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:1.25rem 0;transition:all .35s ease;}
    .nav.scrolled{background:var(--glass-bg);backdrop-filter:blur(16px);-webkit-backdrop-filter:blur(16px);border-bottom:1px solid var(--glass-border);padding:.75rem 0;}
    .nav-inner{display:flex;align-items:center;justify-content:space-between;}
    .nav-brand{display:flex;align-items:center;gap:.75rem;text-decoration:none;}
    .nav-logo{width:40px;height:40px;border-radius:50%;background:var(--gradient-cta);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:700;color:var(--primary-fg);font-size:1.1rem;flex-shrink:0;}
    .nav-logo img{width:40px;height:40px;border-radius:50%;object-fit:cover;}
    .nav-name{font-family:'Playfair Display',serif;font-size:1.2rem;font-weight:600;color:var(--fg);}
    .nav-links{display:flex;gap:2rem;align-items:center;}
    .nav-links a{font-size:.875rem;font-weight:500;color:hsla(30,30%,90%,.7);text-decoration:none;transition:color .25s;}
    .nav-links a:hover{color:var(--fg);}
    .btn-cta{display:inline-flex;align-items:center;gap:.4rem;background:var(--gradient-cta);color:var(--primary-fg);padding:.55rem 1.4rem;border-radius:.75rem;font-size:.875rem;font-weight:600;text-decoration:none;transition:all .3s;box-shadow:var(--shadow-warm);border:none;cursor:pointer;}
    .btn-cta:hover{transform:translateY(-2px);box-shadow:0 12px 40px -8px hsla(27,72%,47%,.4);}
    .btn-cta-lg{padding:.75rem 2rem;font-size:1rem;border-radius:.75rem;}
    .btn-ghost{display:inline-flex;align-items:center;gap:.4rem;background:transparent;color:hsla(30,30%,90%,.7);border:1px solid hsla(30,30%,90%,.2);padding:.75rem 2rem;border-radius:.75rem;font-size:1rem;font-weight:600;text-decoration:none;transition:all .3s;cursor:pointer;}
    .btn-ghost:hover{color:var(--fg);border-color:hsla(30,30%,90%,.4);transform:translateY(-1px);}

    /* Hamburger */
    .nav-ham{display:none;background:none;border:none;color:var(--fg);font-size:1.5rem;cursor:pointer;}
    .nav-mobile{display:none;}
    .nav-mobile.open{display:flex;flex-direction:column;gap:1rem;position:absolute;top:100%;left:1rem;right:1rem;padding:1.2rem;border-radius:.75rem;}

    /* ═══ HERO ═══ */
    .hero{position:relative;min-height:100vh;display:flex;align-items:center;overflow:hidden;}
    .hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;}
    .hero-overlay{position:absolute;inset:0;background:var(--hero-overlay);}
    .hero-content{position:relative;z-index:2;display:grid;grid-template-columns:3fr 2fr;gap:3rem;align-items:center;padding:6rem 0 4rem;}
    .hero-badge{display:inline-block;padding:.4rem 1rem;border-radius:50px;font-size:.75rem;font-weight:600;letter-spacing:.04em;background:hsla(27,72%,47%,.2);color:var(--miel);border:1px solid hsla(27,72%,47%,.3);margin-bottom:1.5rem;}
    .hero-h1{font-size:clamp(2.2rem,5vw,3.75rem);font-weight:700;line-height:1.08;color:var(--fg);margin-bottom:1rem;}
    .hero-p{font-size:clamp(.9rem,1.5vw,1.1rem);color:hsla(30,30%,90%,.7);line-height:1.7;max-width:560px;margin-bottom:2rem;}
    .hero-btns{display:flex;gap:1rem;flex-wrap:wrap;}
    .scroll-arrow{position:absolute;bottom:2rem;left:50%;transform:translateX(-50%);color:hsla(30,30%,90%,.4);font-size:1.8rem;animation:bob 2.5s ease infinite;text-decoration:none;}
    @keyframes bob{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(8px)}}

    /* Stats panel */
    .stats-panel{border-radius:1rem;padding:1.25rem;display:grid;grid-template-columns:1fr 1fr;gap:.75rem;}
    .stat-card{border-radius:.75rem;padding:.85rem;display:flex;flex-direction:column;gap:.35rem;transition:transform .2s;}
    .stat-card:hover{transform:scale(1.05);}
    .stat-lbl{display:flex;align-items:center;gap:.4rem;font-size:.65rem;font-weight:500;text-transform:uppercase;letter-spacing:.08em;color:hsla(30,30%,90%,.5);}
    .stat-lbl i{font-size:.8rem;}
    .stat-lbl i.ok{color:#4ade80;}.stat-lbl i.warn{color:#f87171;}
    .stat-val{font-family:'Playfair Display',serif;font-size:1.15rem;font-weight:600;}
    .stat-val.ok{color:#86efac;}.stat-val.warn{color:#fca5a5;}.stat-val.nar{color:var(--miel);}

    /* ═══ SECTIONS ═══ */
    .section{padding:5rem 0;}
    .section-bg{background:var(--card);}
    .section-header{max-width:48rem;margin:0 auto 3.5rem;text-align:center;}
    .section-h2{font-size:clamp(1.8rem,3vw,2.5rem);font-weight:700;color:var(--fg);margin-bottom:.6rem;}
    .section-p{font-size:.95rem;color:hsla(30,30%,90%,.5);line-height:1.6;max-width:32rem;margin:0 auto;}
    .section-p .dev{color:var(--miel);font-weight:600;}

    /* ═══ ABOUT CARDS ═══ */
    .about-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;}
    .about-card{border-radius:.75rem;padding:1.5rem;transition:all .3s;cursor:default;}
    .about-card:hover{transform:scale(1.05);box-shadow:var(--shadow-warm);}
    .about-ico{width:2.75rem;height:2.75rem;border-radius:.6rem;background:var(--gradient-cta);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--primary-fg);margin-bottom:1rem;transition:transform .3s;}
    .about-card:hover .about-ico{transform:scale(1.1);}
    .about-card h3{font-size:1.1rem;font-weight:600;color:var(--fg);margin-bottom:.4rem;}
    .about-card p{font-size:.85rem;color:hsla(30,30%,90%,.5);line-height:1.6;}

    /* ═══ MODULES GRID ═══ */
    .mod-grid{display:grid;grid-template-columns:repeat(5,1fr);gap:1rem;}
    .mod-card{border-radius:.75rem;padding:1.25rem;text-align:center;transition:all .3s;cursor:default;}
    .mod-card:hover{transform:scale(1.05);box-shadow:var(--shadow-warm);}
    .mod-ico{width:3rem;height:3rem;margin:0 auto .75rem;border-radius:.6rem;background:var(--gradient-cta);display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:var(--primary-fg);transition:transform .3s;}
    .mod-card:hover .mod-ico{transform:scale(1.1);}
    .mod-card h3{font-size:.95rem;font-weight:600;color:var(--fg);margin-bottom:.25rem;}
    .mod-card p{font-size:.75rem;color:hsla(30,30%,90%,.45);line-height:1.5;}

    /* ═══ TECH GRID ═══ */
    .tech-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:1.25rem;}
    .tech-card{border-radius:.75rem;padding:1.5rem;transition:all .3s;cursor:default;}
    .tech-card:hover{transform:scale(1.05);box-shadow:var(--shadow-warm);}
    .tech-ico{width:2.75rem;height:2.75rem;border-radius:.6rem;background:var(--gradient-cta);display:flex;align-items:center;justify-content:center;font-size:1.1rem;color:var(--primary-fg);margin-bottom:1rem;transition:transform .3s;}
    .tech-card:hover .tech-ico{transform:scale(1.1);}
    .tech-card h3{font-size:1rem;font-weight:600;color:var(--fg);margin-bottom:.2rem;}
    .tech-card p{font-size:.85rem;color:hsla(30,30%,90%,.45);line-height:1.5;}

    /* ═══ FOOTER ═══ */
    .footer{padding:3rem 0;border-top:1px solid var(--border);}
    .footer-inner{display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:1rem;}
    .footer-brand{display:flex;align-items:center;gap:.75rem;}
    .footer-logo{width:36px;height:36px;border-radius:50%;background:var(--gradient-cta);display:flex;align-items:center;justify-content:center;font-family:'Playfair Display',serif;font-weight:700;color:var(--primary-fg);font-size:.9rem;}
    .footer-logo img{width:36px;height:36px;border-radius:50%;object-fit:cover;}
    .footer-txt{font-size:.85rem;color:hsla(30,30%,90%,.6);}
    .footer-right{font-size:.75rem;color:hsla(30,30%,90%,.4);}

    /* ═══ RESPONSIVE ═══ */
    @media(max-width:1024px){
      .hero-content{grid-template-columns:1fr;text-align:center;}
      .hero-p{max-width:100%;margin-left:auto;margin-right:auto;}
      .hero-btns{justify-content:center;}
      .stats-panel{max-width:420px;margin:0 auto;}
      .about-grid{grid-template-columns:1fr 1fr;}
      .mod-grid{grid-template-columns:repeat(3,1fr);}
      .tech-grid{grid-template-columns:1fr 1fr;}
    }
    @media(max-width:768px){
      .container{padding:0 1rem;}
      .nav-links{display:none;}
      .nav-ham{display:block;}
      .section{padding:3.5rem 0;}
      .hero-content{padding:5rem 0 3rem;}
      .about-grid{grid-template-columns:1fr 1fr;}
      .mod-grid{grid-template-columns:1fr 1fr;}
      .tech-grid{grid-template-columns:1fr;}
      .footer-inner{flex-direction:column;text-align:center;}
    }
    @media(max-width:480px){
      .hero-btns{flex-direction:column;}
      .btn-cta-lg,.btn-ghost{width:100%;justify-content:center;}
      .about-grid{grid-template-columns:1fr;}
      .stats-panel{grid-template-columns:1fr;}
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

<!-- ═══ NAV ═══ -->
<nav class="nav" id="mainNav">
  <div class="container nav-inner">
    <a href="#" class="nav-brand">
      <div class="nav-logo"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="B"></div>
      <span class="nav-name">BreadControl</span>
    </a>
    <div class="nav-links">
      <a href="#acerca">Acerca de</a>
      <a href="#modulos">Módulos</a>
      <a href="#tecnologia">Tecnología</a>
      <a href="<?= APP_URL ?>/login.php" class="btn-cta"><i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión</a>
    </div>
    <button class="nav-ham" id="navHam"><i class="bi bi-list" id="hamIco"></i></button>
    <div class="nav-mobile glass-card" id="navMobile">
      <a href="#acerca" style="color:hsla(30,30%,90%,.7);text-decoration:none;font-size:.9rem;font-weight:500">Acerca de</a>
      <a href="#modulos" style="color:hsla(30,30%,90%,.7);text-decoration:none;font-size:.9rem;font-weight:500">Módulos</a>
      <a href="#tecnologia" style="color:hsla(30,30%,90%,.7);text-decoration:none;font-size:.9rem;font-weight:500">Tecnología</a>
      <a href="<?= APP_URL ?>/login.php" class="btn-cta" style="text-align:center;justify-content:center">Iniciar Sesión</a>
    </div>
  </div>
</nav>

<!-- ═══ HERO ═══ -->
<section class="hero">
  <img src="<?= APP_URL ?>/assets/img/hero-bakery.jpg" alt="Panadería artesanal" class="hero-bg">
  <div class="hero-overlay"></div>
  <div class="container hero-content">
    <div class="reveal">
      <div class="hero-badge">Software 100% local · Sin costos mensuales</div>
      <h1 class="hero-h1">Control total de<br><span class="text-gradient-warm">tu panadería</span></h1>
      <p class="hero-p">BreadControl digitaliza el ciclo operativo completo: desde la compra de insumos hasta el cierre financiero del día. Conoce tus costos reales, controla el inventario y toma decisiones con datos.</p>
      <div class="hero-btns">
        <a href="<?= APP_URL ?>/login.php" class="btn-cta btn-cta-lg"><i class="bi bi-box-arrow-in-right"></i> Iniciar Sesión</a>
        <a href="#acerca" class="btn-ghost"><i class="bi bi-arrow-down-circle"></i> Conocer más</a>
      </div>
    </div>
    <div class="reveal-x">
      <div class="glass-card stats-panel">
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-cart3 ok"></i> Ventas hoy</div>
          <div class="stat-val nar">$<?= number_format($ventas_hoy,0,',','.') ?></div>
        </div>
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-graph-up-arrow <?= $utilidad_hoy>=0?'ok':'warn' ?>"></i> Utilidad neta</div>
          <div class="stat-val <?= $utilidad_hoy>=0?'ok':'warn' ?>"><?= $utilidad_hoy>=0?'+':'-' ?>$<?= number_format(abs($utilidad_hoy),0,',','.') ?></div>
        </div>
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-fire ok"></i> Producción</div>
          <div class="stat-val nar"><?= number_format($tandas_hoy,0,',','.') ?> tandas</div>
        </div>
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-exclamation-triangle <?= $insumos_bajos>0?'warn':'ok' ?>"></i> Stock bajo</div>
          <div class="stat-val <?= $insumos_bajos>0?'warn':'ok' ?>"><?= $insumos_bajos ?> alerta<?= $insumos_bajos!=1?'s':'' ?></div>
        </div>
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-receipt warn"></i> Gastos</div>
          <div class="stat-val nar">$<?= number_format($gastos_hoy,0,',','.') ?></div>
        </div>
        <div class="glass-card stat-card">
          <div class="stat-lbl"><i class="bi bi-check-circle <?= $cierre_hoy?'ok':'warn' ?>"></i> Cierre del día</div>
          <div class="stat-val <?= $cierre_hoy?'ok':'nar' ?>"><?= $cierre_hoy?'Completo':'Pendiente' ?></div>
        </div>
      </div>
    </div>
  </div>
  <a href="#acerca" class="scroll-arrow"><i class="bi bi-chevron-double-down"></i></a>
</section>

<!-- ═══ ABOUT ═══ -->
<section class="section section-bg" id="acerca">
  <div class="container">
    <div class="section-header reveal">
      <h2 class="section-h2">Acerca del <span class="text-gradient-warm">Sistema</span></h2>
      <p class="section-p">Nace de una necesidad real: la panadería familiar llevaba todos sus registros en cuadernos. No se conocía el costo real de producción, los insumos se agotaban sin aviso y la utilidad era un misterio.</p>
      <p class="section-p" style="margin-top:.8rem;font-size:.85rem">Desarrollado por <span class="dev">Manuel Cardenas Suarez</span>, aprendiz SENA en Florencia, Caquetá. Funciona 100% sin internet, sin costos mensuales.</p>
    </div>
    <div class="about-grid">
      <div class="glass-card about-card reveal stagger">
        <div class="about-ico"><i class="bi bi-layers"></i></div>
        <h3>FIFO Real</h3>
        <p>Costo calculado con el precio real de cada lote consumido.</p>
      </div>
      <div class="glass-card about-card reveal stagger">
        <div class="about-ico"><i class="bi bi-moisture"></i></div>
        <h3>Merma 6%</h3>
        <p>La harina pierde peso al hornear, ajuste automático en cada tanda.</p>
      </div>
      <div class="glass-card about-card reveal stagger">
        <div class="about-ico"><i class="bi bi-gift"></i></div>
        <h3>Bonificación 20%</h3>
        <p>Por cada 10 panes a tiendas, 2 extra gratis. Cálculo integrado.</p>
      </div>
      <div class="glass-card about-card reveal stagger">
        <div class="about-ico"><i class="bi bi-calculator"></i></div>
        <h3>Cierre del Día</h3>
        <p>Utilidad neta real con un solo click al final de la jornada.</p>
      </div>
    </div>
  </div>
</section>

<!-- ═══ MODULES ═══ -->
<section class="section" id="modulos">
  <div class="container">
    <div class="section-header reveal">
      <h2 class="section-h2">10 <span class="text-gradient-warm">Módulos</span> Integrados</h2>
      <p class="section-p">Cada módulo resuelve un proceso crítico del negocio panadero.</p>
    </div>
    <div class="mod-grid">
      <?php
      $mods = [
        ['bi-box-arrow-in-right','Login','Autenticación bcrypt + PIN de seguridad'],
        ['bi-speedometer2','Tablero','Dashboard KPIs en tiempo real + clima'],
        ['bi-box-seam','Inventario','Stock tiempo real + alertas automáticas'],
        ['bi-fire','Producción','Tandas + descuento FIFO por lotes'],
        ['bi-journal-text','Recetas','Ingredientes por tanda con costos'],
        ['bi-cart4','Compras','Registro de lotes automáticos'],
        ['bi-shop','Ventas','Bonificación 20% + historial completo'],
        ['bi-wallet2','Gastos','Por categoría + gráfico de 7 días'],
        ['bi-lock','Cierre','Utilidad bruta y neta del día'],
        ['bi-graph-up-arrow','Finanzas','Reportes avanzados + exportar PDF'],
      ];
      foreach ($mods as $i => $m): ?>
      <div class="glass-card mod-card reveal stagger">
        <div class="mod-ico"><i class="bi <?= $m[0] ?>"></i></div>
        <h3><?= $m[1] ?></h3>
        <p><?= $m[2] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ TECH ═══ -->
<section class="section section-bg" id="tecnologia">
  <div class="container">
    <div class="section-header reveal">
      <h2 class="section-h2"><span class="text-gradient-warm">Tecnología</span> Robusta</h2>
      <p class="section-p">Stack probado, seguro y portable. Sin dependencias de la nube.</p>
    </div>
    <div class="tech-grid">
      <?php
      $techs = [
        ['bi-code-slash','PHP 8','Backend sin framework, máxima portabilidad'],
        ['bi-database','MySQL 8','19 tablas, 28 FK, FIFO por lotes'],
        ['bi-hdd-stack','XAMPP','Apache + PHP + MySQL gratuito'],
        ['bi-palette','Bootstrap 5','Responsive + paleta personalizada'],
        ['bi-git','Git + GitHub','Control de versiones profesional'],
        ['bi-kanban','Jira','Gestión Scrum, 45+ tareas, 4 sprints'],
        ['bi-shield-check','8 Capas de Seguridad','bcrypt, XSS, SQL Injection, validación'],
        ['bi-boxes','Arquitectura por Capas','Page Controller, 10 módulos por dominio'],
      ];
      foreach ($techs as $t): ?>
      <div class="glass-card tech-card reveal stagger">
        <div class="tech-ico"><i class="bi <?= $t[0] ?>"></i></div>
        <h3><?= $t[1] ?></h3>
        <p><?= $t[2] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ═══ FOOTER ═══ -->
<footer class="footer">
  <div class="container footer-inner">
    <div class="footer-brand">
      <div class="footer-logo"><img src="<?= APP_URL ?>/assets/img/logo.png" alt="B"></div>
      <span class="footer-txt">BreadControl · SENA 2026</span>
    </div>
    <p class="footer-right">Manuel Cardenas Suarez · Florencia, Caquetá · Tecnólogo ADSO</p>
  </div>
</footer>

<script>
// Nav scroll
const nav = document.getElementById('mainNav');
window.addEventListener('scroll', () => nav.classList.toggle('scrolled', window.scrollY > 40));

// Mobile menu
const ham = document.getElementById('navHam');
const mob = document.getElementById('navMobile');
const ico = document.getElementById('hamIco');
ham.addEventListener('click', () => {
  const open = mob.classList.toggle('open');
  ico.className = open ? 'bi bi-x-lg' : 'bi bi-list';
});
mob.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
  mob.classList.remove('open');
  ico.className = 'bi bi-list';
}));

// Scroll reveal
const observer = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('visible'); });
}, { threshold: 0.08, rootMargin: '0px 0px -30px 0px' });
document.querySelectorAll('.reveal, .reveal-x').forEach(el => observer.observe(el));

// Hero instant
setTimeout(() => {
  document.querySelectorAll('.hero .reveal, .hero .reveal-x').forEach(el => el.classList.add('visible'));
}, 150);
</script>

<!-- Manual de Usuario -->
<a href="<?= APP_URL ?>/assets/docs/Manual_BreadControl.pdf" target="_blank" class="btn-manual" title="Manual de Usuario">
  <i class="bi bi-book-half"></i>
  <span class="manual-tooltip">Manual de Usuario</span>
</a>

</body>
</html>