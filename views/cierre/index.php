<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--shadow2:0 4px 20px rgba(148,91,53,.15);--nav-h:64px;}
  @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
  @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

  /* ── PAGE: scrollable ── */
  .page{margin-top:var(--nav-h);min-height:calc(100vh - var(--nav-h));overflow-y:auto;display:grid;grid-template-rows:auto auto auto auto;gap:.7rem;padding:.75rem;}

  /* ── BANNER ── */
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
  .wc-pill.ok{background:rgba(200,255,220,.2);border-color:rgba(200,255,220,.35);}
  .wc-pill.ok .wc-pill-num{color:#c8ffd8;}
  .wc-pill.alert{background:rgba(255,205,210,.25);border-color:rgba(255,205,210,.4);}
  .wc-pill.alert .wc-pill-num{color:#ffcdd2;}

  /* ── TOPBAR ── */
  .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;}
  .mod-titulo{font-family:'Fraunces',serif;font-size:1.45rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
  .mod-titulo i{color:var(--c3);}
  .mod-fecha{font-size:.75rem;color:var(--ink3);background:var(--clight);border:1px solid var(--border);border-radius:8px;padding:.3rem .8rem;font-weight:600;}
  .msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:3px solid #2e7d32;border-radius:10px;padding:.6rem .9rem;font-size:.82rem;color:#1b5e20;font-weight:600;margin-top:.5rem;display:flex;align-items:center;gap:.4rem;}
  .msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:3px solid #c62828;border-radius:10px;padding:.6rem .9rem;font-size:.82rem;color:#c62828;margin-top:.5rem;display:flex;align-items:center;gap:.4rem;}

  /* ── ZONA CENTRAL: kpis + cards ── */
  .zona-central{display:grid;grid-template-rows:auto auto;gap:.7rem;}

  /* KPIs */
  .kpi-row{display:grid;grid-template-columns:repeat(4,1fr);gap:.7rem;}
  .kpi{background:var(--ccard);border:1px solid var(--border);border-radius:14px;padding:.85rem 1.1rem;box-shadow:var(--shadow);animation:fadeUp .4s ease both;}
  .kpi:nth-child(1){animation-delay:.05s}.kpi:nth-child(2){animation-delay:.10s}.kpi:nth-child(3){animation-delay:.15s}.kpi:nth-child(4){animation-delay:.20s}
  .kpi-lbl{font-size:.62rem;text-transform:uppercase;letter-spacing:.18em;color:var(--ink3);font-weight:700;display:flex;align-items:center;gap:.35rem;margin-bottom:.3rem;}
  .kpi-lbl i{font-size:.9rem;}
  .kpi-val{font-family:'Fraunces',serif;font-size:1.7rem;font-weight:800;color:var(--ink);line-height:1;}
  .kpi-val.grn{color:#2e7d32;}.kpi-val.red{color:#c62828;}.kpi-val.nar{color:var(--c3);}
  .kpi-sub{font-size:.7rem;color:var(--ink3);margin-top:.25rem;}
  .badge{display:inline-flex;align-items:center;font-size:.62rem;font-weight:700;padding:.13rem .45rem;border-radius:20px;gap:.2rem;}
  .b-ok{background:#e8f5e9;color:#2e7d32;}.b-bad{background:#ffebee;color:#c62828;}.b-neu{background:var(--clight);color:var(--c1);border:1px solid var(--border);}

  /* Cards detalle */
  .c-body{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;}
  .card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;animation:fadeUp .45s ease both;}
  .card:nth-child(1){animation-delay:.25s}.card:nth-child(2){animation-delay:.30s}.card:nth-child(3){animation-delay:.35s}
  .ch{display:flex;align-items:center;justify-content:space-between;padding:.75rem 1rem;flex-shrink:0;border-bottom:1px solid var(--border);}
  .ch-left{display:flex;align-items:center;gap:.5rem;}
  .ch-ico{width:28px;height:28px;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:.95rem;}
  .ico-nar{background:rgba(198,113,36,.1);color:var(--c3);}
  .ico-caf{background:rgba(148,91,53,.1);color:var(--c1);}
  .ico-grn{background:rgba(46,125,50,.1);color:#2e7d32;}
  .ico-red{background:rgba(198,40,40,.1);color:#c62828;}
  .ico-blu{background:rgba(13,110,253,.1);color:#0d6efd;}
  .ch-title{font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.17em;color:var(--ink3);}
  .ch-val{font-size:.73rem;font-weight:700;color:var(--ink2);}

  /* Filas internas */
  .d-scroll{overflow-y:auto;max-height:260px;}
  .dato-row{display:flex;align-items:center;gap:.45rem;padding:.42rem .9rem;border-bottom:1px solid rgba(148,91,53,.05);}
  .dato-row:last-child{border-bottom:none;}
  .dato-nombre{font-size:.8rem;font-weight:600;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .dato-det{font-size:.68rem;color:var(--ink3);flex-shrink:0;white-space:nowrap;}
  .dato-val{font-size:.8rem;font-weight:700;color:var(--c3);white-space:nowrap;flex-shrink:0;}
  .barra-w{width:45px;height:4px;background:var(--clight);border-radius:2px;overflow:hidden;flex-shrink:0;}
  .barra-f{height:100%;border-radius:2px;background:linear-gradient(90deg,var(--c3),var(--c5));}
  .barra-alert{height:100%;border-radius:2px;background:linear-gradient(90deg,#c62828,#ef9a9a);}
  .sub-sep{padding:.32rem .9rem;font-size:.58rem;font-weight:700;text-transform:uppercase;letter-spacing:.14em;color:var(--ink3);border-top:1px solid var(--border);margin-top:.2rem;display:flex;align-items:center;gap:.3rem;}
  .c-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:.35rem;padding:1.5rem;color:var(--ink3);font-size:.78rem;opacity:.7;text-align:center;flex:1;}
  .c-empty i{font-size:1.6rem;opacity:.35;}

  /* ── ZONA INFERIOR: misma grid que c-body ── */
  .cierre-bottom{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.7rem;}

  /* Form cierre dentro de card */
  .cierre-form{display:flex;flex-direction:column;gap:.55rem;padding:.65rem .85rem;}
  .obs-wrap{width:100%;}
  .obs-label{font-size:.6rem;font-weight:700;text-transform:uppercase;letter-spacing:.15em;color:var(--ink3);margin-bottom:.25rem;}
  .obs-textarea{width:100%;border:1px solid var(--border);border-radius:9px;padding:.45rem .7rem;font-size:.78rem;font-family:inherit;color:var(--ink);background:var(--clight);resize:none;height:48px;transition:border-color .2s;box-sizing:border-box;}
  .obs-textarea:focus{outline:none;border-color:var(--c3);box-shadow:0 0 0 3px rgba(198,113,36,.1);}
  .btn-cerrar{width:35% ;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:9px;padding:.52rem 1rem;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit;box-shadow:0 3px 12px rgba(198,113,36,.25);display:flex;align-items:center;justify-content:center;gap:.35rem;transition:all .2s;}
  .btn-cerrar:hover{transform:translateY(-1px);box-shadow:0 5px 18px rgba(198,113,36,.4);}
  .cierre-ya{display:flex;align-items:flex-start;gap:.4rem;background:#e8f5e9;border:1px solid #a5d6a7;border-radius:9px;padding:.45rem .7rem;font-size:.72rem;color:#2e7d32;font-weight:600;}
  .cierre-ya i{font-size:.9rem;flex-shrink:0;margin-top:.1rem;}

  /* Sobrantes filas dentro de card */
  .sob-lista{display:flex;flex-direction:column;gap:.2rem;padding:.4rem .85rem;}
  .sob-fila{display:flex;align-items:center;gap:.4rem;padding:.3rem .4rem;background:var(--clight);border-radius:7px;}
  .sob-nombre{font-size:.75rem;font-weight:600;color:var(--ink);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .sob-det{font-size:.62rem;color:var(--ink3);white-space:nowrap;flex-shrink:0;}
  .sob-vacio{font-size:.75rem;color:#2e7d32;font-weight:600;display:flex;align-items:center;justify-content:center;gap:.3rem;padding:1rem;flex:1;}
  .ht{width:100%;border-collapse:collapse;}
  .ht th{font-size:.59rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink3);font-weight:700;padding:.45rem .8rem;background:var(--clight);border-bottom:1px solid var(--border);white-space:nowrap;}
  .ht th:not(:first-child){text-align:right;}
  .ht td{font-size:.78rem;color:var(--ink);padding:.42rem .8rem;border-bottom:1px solid rgba(148,91,53,.05);}
  .ht td:not(:first-child){text-align:right;font-weight:600;}
  .ht tr:last-child td{border-bottom:none;}
  .ht tr:hover td{background:rgba(250,243,234,.5);}
  .ht td.grn{color:#2e7d32;}.ht td.red{color:#c62828;}

  @media(max-width:1100px){
    .c-body{grid-template-columns:1fr 1fr;}
    .kpi-row{grid-template-columns:repeat(2,1fr);}
    .cierre-bottom{grid-template-columns:1fr 1fr;}
  }
  @media(max-width:768px){
    .page{margin-top:60px;padding:.5rem;}
    .kpi-row{grid-template-columns:1fr 1fr;}
    .c-body{grid-template-columns:1fr;}
    .cierre-bottom{grid-template-columns:1fr;}
  }
</style>

<div class="page">

  <!-- ══ BANNER ══ -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Cierre <em>del Día</em></div>
        <div class="wc-sub"><?= date('l, d \d\e F \d\e Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_ventas > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($total_ventas / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Ventas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $total_tandas ?></div>
        <div class="wc-pill-lbl">Tandas</div>
      </div>
      <div class="wc-pill <?= $num_alertas > 0 ? 'alert' : 'ok' ?>">
        <div class="wc-pill-num"><?= $num_alertas ?></div>
        <div class="wc-pill-lbl">Alertas</div>
      </div>
      <div class="wc-pill <?= $utilidad_neta >= 0 ? 'ok' : 'alert' ?>">
        <div class="wc-pill-num"><?= $utilidad_neta >= 0 ? '+' : '-' ?>$<?= number_format(abs($utilidad_neta) / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Util. neta</div>
      </div>
      <?php if ($cierre_guardado): ?>
      <div class="wc-pill ok">
        <div class="wc-pill-num">✓</div>
        <div class="wc-pill-lbl">Cerrado</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ TOPBAR + MENSAJES ══ -->
  <div>
    <div class="topbar">
      <div class="mod-titulo">
        <i class="bi bi-moon-stars-fill"></i> Cierre del día
      </div>
      <span class="mod-fecha">📅 <?= date('d/m/Y') ?></span>
    </div>
    <?php if ($msg_ok): ?>
    <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><?= $msg_ok ?></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
    <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><?= $msg_err ?></div>
    <?php endif; ?>
  </div>

  <!-- ══ ZONA CENTRAL ══ -->
  <div class="zona-central">

    <!-- KPIs -->
    <div class="kpi-row">
      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-currency-dollar" style="color:#2e7d32"></i>Ventas del día</div>
        <div class="kpi-val grn">$<?= number_format($total_ventas, 0, ',', '.') ?></div>
        <div style="display:flex;align-items:center;gap:.35rem;flex-wrap:wrap;margin-top:.2rem">
          <span class="badge b-neu"><?= $num_ventas ?> venta<?= $num_ventas != 1 ? 's' : '' ?></span>
          <?php if ($diff_ventas !== null): ?>
          <span class="badge <?= $diff_ventas >= 0 ? 'b-ok' : 'b-bad' ?>">
            <i class="bi bi-arrow-<?= $diff_ventas >= 0 ? 'up' : 'down' ?>"></i>
            <?= $diff_ventas >= 0 ? '+' : '' ?><?= $diff_ventas ?>% vs ayer
          </span>
          <?php endif; ?>
        </div>
      </div>

      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-fire" style="color:var(--c3)"></i>Producción</div>
        <div class="kpi-val nar"><?= $total_tandas ?></div>
        <div class="kpi-sub"><?= count($producciones) ?> lote<?= count($producciones) != 1 ? 's' : '' ?> · tandas producidas</div>
      </div>

      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-cart-fill" style="color:#c62828"></i>Compras + Gastos</div>
        <div class="kpi-val red">$<?= number_format($costo_produccion_hoy + $total_gastos, 0, ',', '.') ?></div>
        <div class="kpi-sub"><?= $num_compras ?> compra<?= $num_compras != 1 ? 's' : '' ?> · gastos $<?= number_format($total_gastos, 0, ',', '.') ?></div>
      </div>

      <div class="kpi">
        <div class="kpi-lbl"><i class="bi bi-calculator" style="color:#1565c0"></i>Utilidad neta</div>
        <div class="kpi-val <?= $utilidad_neta >= 0 ? 'grn' : 'red' ?>">$<?= number_format(abs($utilidad_neta), 0, ',', '.') ?></div>
        <span class="badge <?= $utilidad_neta >= 0 ? 'b-ok' : 'b-bad' ?>" style="margin-top:.2rem;align-self:flex-start">
          <?= $utilidad_neta >= 0 ? '✓ Positiva' : '✗ Negativa' ?>
        </span>
      </div>
    </div>

    <!-- Cards detalle -->
    <div class="c-body">

      <!-- Ventas por producto -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-nar"><i class="bi bi-bag-fill"></i></span>
            <span class="ch-title">Ventas / producto</span>
          </div>
          <span class="ch-val">$<?= number_format($total_ventas, 0, ',', '.') ?></span>
        </div>
        <?php if (empty($ventas_prod)): ?>
        <div class="c-empty"><i class="bi bi-bag-x"></i>Sin ventas hoy</div>
        <?php else: ?>
        <div class="d-scroll">
          <?php $max_vp = max(array_column($ventas_prod, 't') ?: [1]);
          foreach ($ventas_prod as $vp):
            $pct = round(($vp['t'] / $max_vp) * 100); ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($vp['nombre']) ?></span>
            <div class="barra-w"><div class="barra-f" style="width:<?= $pct ?>%"></div></div>
            <span class="dato-det"><?= $vp['u'] ?> und</span>
            <span class="dato-val">$<?= number_format($vp['t'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Ventas por cliente -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-caf"><i class="bi bi-people-fill"></i></span>
            <span class="ch-title">Ventas / cliente</span>
          </div>
          <span class="ch-val"><?= count($ventas_cli) ?> cliente<?= count($ventas_cli) != 1 ? 's' : '' ?></span>
        </div>
        <?php if (empty($ventas_cli)): ?>
        <div class="c-empty"><i class="bi bi-people"></i>Sin ventas hoy</div>
        <?php else: ?>
        <div class="d-scroll">
          <?php $max_vc = max(array_column($ventas_cli, 't') ?: [1]);
          foreach ($ventas_cli as $vc):
            $pct = round(($vc['t'] / $max_vc) * 100); ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= $vc['tipo'] === 'tienda' ? '🏪' : '🧑' ?> <?= htmlspecialchars($vc['cliente']) ?></span>
            <div class="barra-w"><div class="barra-f" style="width:<?= $pct ?>%"></div></div>
            <span class="dato-det"><?= $vc['n'] ?> venta<?= $vc['n'] != 1 ? 's' : '' ?></span>
            <span class="dato-val">$<?= number_format($vc['t'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Producción + compras + alertas -->
      <div class="card">
        <div class="ch">
          <div class="ch-left">
            <span class="ch-ico ico-grn"><i class="bi bi-fire"></i></span>
            <span class="ch-title">Operaciones</span>
          </div>
          <?php if ($num_alertas > 0): ?>
          <span class="badge b-bad"><i class="bi bi-exclamation-triangle-fill"></i><?= $num_alertas ?> alerta<?= $num_alertas != 1 ? 's' : '' ?></span>
          <?php endif; ?>
        </div>
        <div class="d-scroll">

          <!-- Producción -->
          <?php if (!empty($producciones)): ?>
          <div class="sub-sep"><i class="bi bi-fire" style="color:var(--c3)"></i>Producción — <?= $total_tandas ?> tandas</div>
          <?php foreach ($producciones as $pr): ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($pr['nombre']) ?></span>
            <span class="dato-det"><?= formatoInteligente($pr['cantidad_tandas']) ?> <?= $pr['unidad_produccion'] ?></span>
            <span class="dato-det"><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></span>
          </div>
          <?php endforeach; ?>
          <?php else: ?>
          <div class="c-empty" style="padding:1rem"><i class="bi bi-inbox"></i>Sin producción hoy</div>
          <?php endif; ?>

          <!-- Compras -->
          <?php if (!empty($compras_hoy)): ?>
          <div class="sub-sep"><i class="bi bi-cart-fill" style="color:#c62828"></i>Compras</div>
          <?php foreach ($compras_hoy as $ch): ?>
          <div class="dato-row">
            <span class="dato-nombre"><?= htmlspecialchars($ch['insumo']) ?></span>
            <span class="dato-det"><?= formatoInteligente($ch['cantidad']) ?> <?= $ch['unidad_medida'] ?></span>
            <span class="dato-val" style="color:#c62828">$<?= number_format($ch['total_pagado'], 0, ',', '.') ?></span>
          </div>
          <?php endforeach; endif; ?>

          <!-- Alertas stock -->
          <?php if (!empty($alertas)): ?>
          <div class="sub-sep" style="color:#c62828"><i class="bi bi-exclamation-triangle-fill"></i>Stock bajo</div>
          <?php foreach ($alertas as $a):
            $pct = $a['punto_reposicion'] > 0 ? min(100, round(($a['stock_actual'] / $a['punto_reposicion']) * 100)) : 0; ?>
          <div class="dato-row">
            <span class="dato-nombre">⚠️ <?= htmlspecialchars($a['nombre']) ?></span>
            <div class="barra-w"><div class="barra-alert" style="width:<?= $pct ?>%"></div></div>
            <span style="font-size:.7rem;font-weight:700;color:#c62828"><?= formatoInteligente($a['stock_actual']) ?> <?= $a['unidad_medida'] ?></span>
          </div>
          <?php endforeach; endif; ?>

        </div>
      </div>

    </div><!-- /c-body -->
  </div><!-- /zona-central -->

  <!-- ══ ZONA INFERIOR: 3 cards alineados con c-body ══ -->
  <div class="cierre-bottom">

    <!-- Card 1: Cierre del día -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico" style="background:rgba(103,58,183,.1);color:#673ab7"><i class="bi bi-moon-stars-fill"></i></span>
          <span class="ch-title">Cierre del día</span>
        </div>
      </div>
      <div class="cierre-form">
        <?php if ($cierre_guardado): ?>
        <div class="cierre-ya">
          <i class="bi bi-check-circle-fill"></i>
          <div>
            Cierre guardado
            <?php if (!empty($cierre_guardado['sugerencia_produccion'])): ?>
            <div style="font-size:.65rem;font-weight:400;opacity:.75;margin-top:.1rem">
              "<?= htmlspecialchars($cierre_guardado['sugerencia_produccion']) ?>"
            </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; ?>
        <form method="POST" style="display:flex;flex-direction:column;gap:.5rem;width:100%;">
          <div class="obs-wrap">
            <div class="obs-label">Sugerencia producción mañana</div>
            <textarea name="sugerencia_produccion" class="obs-textarea"
              placeholder="Ej: Aumentar pan de sal..."><?= htmlspecialchars($cierre_guardado['sugerencia_produccion'] ?? '') ?></textarea>
          </div>
          <button type="submit" name="confirmar_cierre" class="btn-cerrar">
            <i class="bi bi-moon-stars-fill"></i>
            <?= $cierre_guardado ? 'Actualizar cierre' : 'Confirmar cierre' ?>
          </button>
        </form>
      </div>
    </div>

    <!-- Card 2: Historial reciente -->
    <?php if (!empty($historial)): ?>
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-blu"><i class="bi bi-clock-history"></i></span>
          <span class="ch-title">Historial reciente</span>
        </div>
        <span class="badge b-neu"><?= count($historial) ?> días</span>
      </div>
      <div class="d-scroll">
        <table class="ht">
          <thead>
            <tr><th>Fecha</th><th>Ingresos</th><th>U. bruta</th><th>U. neta</th></tr>
          </thead>
          <tbody>
            <?php foreach ($historial as $h): ?>
            <tr>
              <td style="font-weight:600;white-space:nowrap">
                <?= date('d/m', strtotime($h['fecha'])) ?>
                <?php if ($h['fecha'] === $hoy): ?>
                <span class="badge b-ok" style="margin-left:.2rem">hoy</span>
                <?php endif; ?>
              </td>
              <td class="grn">$<?= number_format($h['total_ingresos'], 0, ',', '.') ?></td>
              <td class="<?= $h['utilidad_bruta'] >= 0 ? 'grn' : 'red' ?>">
                <?= $h['utilidad_bruta'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($h['utilidad_bruta']), 0, ',', '.') ?>
              </td>
              <td class="<?= $h['utilidad_neta'] >= 0 ? 'grn' : 'red' ?>">
                <?= $h['utilidad_neta'] >= 0 ? '+' : '-' ?>$<?= number_format(abs($h['utilidad_neta']), 0, ',', '.') ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php else: ?>
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-blu"><i class="bi bi-clock-history"></i></span>
          <span class="ch-title">Historial reciente</span>
        </div>
      </div>
      <div class="c-empty"><i class="bi bi-clock-history"></i>Sin cierres previos</div>
    </div>
    <?php endif; ?>

    <!-- Card 3: Sin vender hoy -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-red"><i class="bi bi-box-seam"></i></span>
          <span class="ch-title">Sin vender hoy</span>
        </div>
        <span class="badge <?= $total_sobrante <= 0 ? 'b-ok' : 'b-bad' ?>">
          <?= $total_sobrante <= 0 ? '✓ Todo vendido' : $total_sobrante . ' und' ?>
        </span>
      </div>
      <?php if (empty($sobrantes) || $total_sobrante <= 0): ?>
      <div class="c-empty"><i class="bi bi-emoji-smile"></i>¡Todo vendido hoy!</div>
      <?php else: ?>
      <div class="sob-lista">
        <?php foreach ($sobrantes as $s): if ($s['sobrante'] <= 0) continue; ?>
        <div class="sob-fila">
          <span class="sob-nombre"><?= htmlspecialchars($s['nombre']) ?></span>
          <span class="sob-det"><?= $s['vendidas'] ?>/<span style="color:var(--ink2)"><?= $s['producidas'] ?></span></span>
          <span class="badge b-bad" style="font-size:.58rem"><?= $s['sobrante'] ?> und</span>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php if ($ventas_sin_producto > 0): ?>
      <div class="sub-sep"><i class="bi bi-info-circle-fill"></i>No incluido arriba</div>
      <div class="dato-row">
        <span class="dato-nombre">Vendido por categoría de precio sin producto específico</span>
        <span class="dato-val" style="color:var(--ink2)"><?= $ventas_sin_producto ?> und</span>
      </div>
      <?php endif; ?>
    </div>

  </div><!-- /cierre-bottom -->
</div><!-- /page -->

<?php include __DIR__ . '/../layouts/footer.php'; ?>
