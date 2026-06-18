<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--shadow2:0 4px 20px rgba(148,91,53,.15);--nav-h:64px;}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
  .page{margin-top:var(--nav-h);height:calc(100vh - var(--nav-h));overflow:hidden;display:grid;grid-template-rows:auto auto 1fr;gap:.7rem;padding:.75rem;}
  .wc-banner{background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);background-size:300% 300%;animation:gradAnim 8s ease infinite;border-radius:14px;padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow2);gap:1rem;flex-wrap:wrap;}
  .wc-left{display:flex;align-items:center;gap:.9rem;}
  .wc-greeting{font-size:.65rem;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.65);margin-bottom:.15rem;}
  .wc-name{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1.1;}
  .wc-name em{font-style:italic;color:var(--c5);}
  .wc-sub{font-size:.72rem;color:rgba(255,255,255,.62);margin-top:.15rem;}
  .wc-pills{display:flex;gap:.55rem;flex-wrap:wrap;}
  .wc-pill{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:.5rem .85rem;text-align:center;min-width:68px;}
  .wc-pill-num{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1;}
  .wc-pill-lbl{font-size:.54rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.58);}
  .wc-pill.ok{background:rgba(200,255,220,.2);border-color:rgba(200,255,220,.35);}.wc-pill.ok .wc-pill-num{color:#c8ffd8;}
  .wc-pill.warn{background:rgba(255,235,59,.18);border-color:rgba(255,235,59,.3);}.wc-pill.warn .wc-pill-num{color:#fff9c4;}
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;}
  .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
  .mod-titulo i{color:var(--c3);}
  .top-actions{display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;}
  .inp-search{border:1px solid var(--border);border-radius:9px;padding:.45rem .75rem;font-size:.82rem;font-family:inherit;color:var(--ink);background:var(--ccard);width:200px;}
  .inp-search:focus{outline:none;border-color:var(--c3);box-shadow:0 0 0 3px rgba(198,113,36,.1);}
  .btn-grad{background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 4px 14px rgba(198,113,36,.3);display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;}
  .btn-grad:hover{transform:translateY(-2px);color:#fff;box-shadow:0 6px 20px rgba(198,113,36,.4);}
  .btn-sec{background:var(--ccard);color:var(--ink2);border:1px solid var(--border);border-radius:10px;padding:.5rem 1rem;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;text-decoration:none;transition:all .2s;}
  .btn-sec:hover{background:var(--clight);border-color:var(--c3);color:var(--ink);}
  .card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;min-height:0;animation:fadeUp .4s ease both;}
  .ch{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1.1rem;flex-shrink:0;border-bottom:1px solid var(--border);}
  .ch-left{display:flex;align-items:center;gap:.5rem;}
  .ch-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;}
  .ico-nar{background:rgba(198,113,36,.1);color:var(--c3);}
  .ch-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.17em;color:var(--ink3);}
  .badge{display:inline-flex;align-items:center;font-size:.62rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;}
  .b-neu{background:var(--clight);color:var(--c1);border:1px solid var(--border);}
  .prod-grid{overflow-y:auto;flex:1;padding:1.1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:1.1rem;align-content:start;}
  .prod-card{background:#ffffff;border:1px solid rgba(148,91,53,.12);border-radius:16px;padding:1.1rem;box-shadow:0 4px 12px rgba(148,91,53,.04);transition:all .25s cubic-bezier(.25,.8,.25,1);position:relative;display:flex;flex-direction:column;animation:fadeUp .35s ease both;}
  .prod-card:hover{border-color:var(--c3);box-shadow:0 12px 28px rgba(148,91,53,.12);transform:translateY(-4px);}
  .prod-card-header{display:flex;align-items:flex-start;gap:.8rem;margin-bottom:.5rem;}
  .prod-card-title-block{flex:1;min-width:0;}
  .prod-icon{width:40px;height:40px;border-radius:10px;background:rgba(198,113,36,.07);border:1px solid rgba(198,113,36,.15);color:var(--c3);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform .3s;}
  .prod-card:hover .prod-icon{transform:scale(1.05) rotate(-3deg);}
  .prod-nombre{font-family:'Fraunces',serif;font-size:1.08rem;font-weight:800;color:var(--ink);line-height:1.25;margin-bottom:.2rem;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;height:2.7em;word-break:break-word;}
  .prod-meta{font-size:.72rem;color:var(--ink3);display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;line-height:1.2;}
  .prod-price-row{display:flex;justify-content:space-between;align-items:center;margin-top:auto;padding-top:.7rem;border-top:1px dashed rgba(148,91,53,.12);margin-bottom:.6rem;}
  .price-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:var(--ink3);font-weight:600;}
  .prod-precio{font-family:'Fraunces',serif;font-size:1.25rem;font-weight:800;color:var(--c3);line-height:1;}
  .prod-tags{display:flex;gap:.35rem;flex-wrap:wrap;min-height:1.6rem;align-items:center;}
  .tag{font-size:.62rem;font-weight:700;padding:.2rem .55rem;border-radius:8px;display:inline-flex;align-items:center;gap:.25rem;text-transform:uppercase;letter-spacing:.02em;}
  .tag-receta{background:rgba(46,125,50,.06);color:#2e7d32;border:1px solid rgba(46,125,50,.15);}
  .tag-noreceta{background:rgba(198,40,40,.05);color:#c62828;border:1px solid rgba(198,40,40,.12);}
  .tag-ing{background:rgba(198,113,36,.06);color:var(--c3);border:1px solid rgba(198,113,36,.12);}
  .tag-cat{background:rgba(107,61,30,.05);color:var(--ink2);border:1px solid rgba(107,61,30,.1);}
  .prod-actions{display:flex;gap:.4rem;padding-top:.75rem;border-top:1px solid rgba(148,91,53,.08);align-items:center;}
  .btn-act{height:34px;border-radius:8px;border:1px solid;display:inline-flex;align-items:center;justify-content:center;font-size:.78rem;text-decoration:none;transition:all .2s ease;cursor:pointer;font-family:inherit;font-weight:700;gap:.3rem;background:transparent;}
  .btn-edit{flex:1;background:rgba(25,118,210,.05);border-color:rgba(25,118,210,.15);color:#1565c0;}
  .btn-edit:hover{background:#1565c0;border-color:#1565c0;color:#fff;box-shadow:0 4px 10px rgba(21,101,192,.2);}
  .btn-receta{flex:1;background:rgba(198,113,36,.05);border-color:rgba(198,113,36,.15);color:var(--c3);}
  .btn-receta:hover{background:var(--c3);border-color:var(--c3);color:#fff;box-shadow:0 4px 10px rgba(198,113,36,.25);}
  .btn-del{flex:0 0 34px;width:34px;background:rgba(198,40,40,.04);border-color:rgba(198,40,40,.15);color:#c62828;padding:0;}
  .btn-del:hover{background:#c62828;border-color:#c62828;color:#fff;box-shadow:0 4px 10px rgba(198,40,40,.2);}
  .empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.6rem;padding:3rem 1rem;color:var(--ink3);font-size:.82rem;text-align:center;flex:1;}
  .empty i{font-size:2.5rem;opacity:.3;}
  .msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:4px solid #2e7d32;border-radius:10px;padding:.7rem 1rem;font-size:.8rem;color:#1b5e20;font-weight:600;margin-bottom:.65rem;display:flex;align-items:flex-start;gap:.5rem;line-height:1.5;}
  .msg-ok i{flex-shrink:0;font-size:.95rem;margin-top:.12rem;}.msg-ok span{flex:1;}
  @media(max-width:768px){.page{height:auto;overflow:visible;margin-top:60px;}.inp-search{width:130px;}}
</style>

<div class="page">
  <div class="wc-banner">
    <div class="wc-left"><div>
      <div class="wc-greeting">Panadería BreadControl</div>
      <div class="wc-name">Módulo de <em>Recetas</em></div>
      <div class="wc-sub">Catálogo de productos y sus ingredientes</div>
    </div></div>
    <div class="wc-pills">
      <div class="wc-pill ok"><div class="wc-pill-num"><?= $total_productos ?></div><div class="wc-pill-lbl">Productos</div></div>
      <div class="wc-pill ok"><div class="wc-pill-num"><?= $con_receta ?></div><div class="wc-pill-lbl">Con receta</div></div>
      <?php if ($sin_receta > 0): ?>
      <div class="wc-pill warn"><div class="wc-pill-num"><?= $sin_receta ?></div><div class="wc-pill-lbl">Sin receta</div></div>
      <?php endif; ?>
      <div class="wc-pill"><div class="wc-pill-num">$<?= number_format($precio_prom, 0, ',', '.') ?></div><div class="wc-pill-lbl">Precio prom.</div></div>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-journal-richtext"></i> Recetas</div>
    <div class="top-actions">
      <form method="get" style="display:flex;gap:.4rem;">
        <input type="text" name="q" class="inp-search" placeholder="Buscar producto…" value="<?= htmlspecialchars($busca) ?>">
      </form>
      <a href="variedades.php" class="btn-sec"><i class="bi bi-list-stars"></i> Variedades</a>
      <a href="crear_producto.php" class="btn-grad"><i class="bi bi-plus-lg"></i> Nuevo producto</a>
    </div>
  </div>

  <div class="card">
    <div class="ch">
      <div class="ch-left">
        <div class="ch-ico ico-nar"><i class="bi bi-grid-3x3-gap-fill"></i></div>
        <span class="ch-title">Catálogo de productos</span>
      </div>
      <span class="badge b-neu"><?= $total_productos ?> activos</span>
    </div>

    <?php if ($msg_ok): ?>
    <div style="padding:.7rem 1.1rem 0;">
      <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div>
    </div>
    <?php endif; ?>

    <?php if (empty($productos)): ?>
    <div class="empty">
      <i class="bi bi-journal-x"></i>
      <strong>Sin productos</strong>
      <span>Crea el primer producto con el botón Nuevo producto</span>
    </div>
    <?php else: ?>
    <div class="prod-grid">
      <?php foreach ($productos as $i => $p): ?>
      <div class="prod-card" style="animation-delay:<?= $i * 0.04 ?>s">
        <div class="prod-card-header">
          <div class="prod-icon" title="Panadería BreadControl">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <path d="M12 2a9 9 0 0 0-9 9v7a3 3 0 0 0 3 3h12a3 3 0 0 0 3-3v-7a9 9 0 0 0-9-9Z" />
              <path d="M3 11h18" />
            </svg>
          </div>
          <div class="prod-card-title-block">
            <div class="prod-nombre" title="<?= htmlspecialchars($p['nombre']) ?>"><?= htmlspecialchars($p['nombre']) ?></div>
            <div class="prod-meta">
              <span><?= $p['unidad_produccion'] ?></span>
              <?php if ($p['cantidad_por_tanda'] > 0): ?>
              <span>· <?= floatval($p['cantidad_por_tanda']) ?> uds/tanda</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="prod-tags">
          <span class="tag tag-cat"><?= ucfirst($p['categoria'] ?? '') ?></span>
          <?php if ($p['id_receta']): ?>
          <span class="tag tag-receta"><i class="bi bi-check-circle"></i> Receta</span>
          <span class="tag tag-ing"><?= $p['num_ingredientes'] ?> ing.</span>
          <?php else: ?>
          <span class="tag tag-noreceta"><i class="bi bi-exclamation-circle"></i> Sin receta</span>
          <?php endif; ?>
        </div>

        <div class="prod-price-row">
          <span class="price-label">Precio de venta</span>
          <span class="prod-precio">$<?= number_format($p['precio_venta'], 0, ',', '.') ?></span>
        </div>

        <div class="prod-actions">
          <a href="editar_producto.php?id=<?= $p['id_producto'] ?>" class="btn-act btn-edit" title="Editar datos">
            <i class="bi bi-pencil"></i> <span>Editar</span>
          </a>
          <?php if ($p['id_receta']): ?>
          <a href="editar_receta.php?id=<?= $p['id_producto'] ?>" class="btn-act btn-receta" title="Ver/editar receta">
            <i class="bi bi-journal-plus"></i> <span>Receta</span>
          </a>
          <?php else: ?>
          <a href="editar_receta.php?id=<?= $p['id_producto'] ?>" class="btn-act btn-receta" title="Crear receta">
            <i class="bi bi-plus-circle"></i> <span>Receta</span>
          </a>
          <?php endif; ?>
          <a href="index.php?del=<?= $p['id_producto'] ?>" class="btn-act btn-del" title="Desactivar"
             onclick="return confirm('¿Desactivar «<?= htmlspecialchars($p['nombre']) ?>»?')">
            <i class="bi bi-trash3"></i>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../layouts/footer.php'; ?>
