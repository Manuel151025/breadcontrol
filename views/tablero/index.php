<!-- VISTA: TABLERO -->
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/tablero.css">

<!-- PÁGINA -->
<div class="page">

  <div class="welcome">
    <div class="wc-banner">
      <div>
        <div class="wc-greeting" id="wg"></div>
        <div class="wc-name">Bienvenido, <em><?= htmlspecialchars($user['nombre']) ?></em></div>
        <div class="wc-sub" id="ws"></div>
      </div>
      <div class="wc-pills">
        <div class="wc-pill">
          <div class="wc-pill-num"><?= $total_insumos ?></div>
          <div class="wc-pill-lbl">Insumos</div>
        </div>
        <div class="wc-pill" style="<?= $num_alertas > 0 ? 'background:rgba(255,205,210,.3)' : '' ?>">
          <div class="wc-pill-num" style="<?= $num_alertas > 0 ? 'color:#ffcdd2' : '' ?>"><?= $num_alertas ?></div>
          <div class="wc-pill-lbl">Alertas</div>
        </div>
        <div class="wc-pill">
          <div class="wc-pill-num"><?= $prod_hoy ?></div>
          <div class="wc-pill-lbl">Prod. hoy</div>
        </div>
        <div class="wc-pill">
          <div class="wc-pill-num"><?= $prods_act ?></div>
          <div class="wc-pill-lbl">Productos</div>
        </div>
      </div>
    </div>

    <div class="clima-box">
      <div>
        <div class="clima-top">
          <span class="clima-ico" id="cico">⏳</span>
          <div>
            <div class="clima-temp" id="ctemp">--°C</div>
            <div class="clima-desc" id="cdesc">Cargando...</div>
          </div>
        </div>
        <div class="clima-city" id="ccity" onclick="abrirModalCiudad()" style="cursor:pointer">
          Florencia, Caquetá <i class="bi bi-chevron-down"></i>
        </div>
      </div>
      <div class="clima-bottom">
        <span class="clima-upd" id="cupd">—</span>
        <button class="btn-ref" id="bref" onclick="getClima(true)">
          <i class="bi bi-arrow-clockwise" id="bico"></i> Actualizar
        </button>
      </div>
    </div>
  </div>


  <?php if (!empty($obs_cierre['sugerencia_produccion'])): ?>
  <div class="obs-banner" id="obs-cierre-banner">
    <div class="obs-ico"><i class="bi bi-chat-left-text-fill"></i></div>
    <div class="obs-body">
      <div class="obs-label">Nota del último cierre</div>
      <div class="obs-text"><?= htmlspecialchars($obs_cierre['sugerencia_produccion']) ?></div>
      <div class="obs-date">Cierre del <?= date('d/m/Y', strtotime($obs_cierre['fecha'])) ?></div>
    </div>
    <button class="obs-close" onclick="this.parentElement.style.display='none'" title="Cerrar">&times;</button>
  </div>
  <?php endif; ?>

  <div class="grid">

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-nar"><i class="bi bi-currency-dollar"></i></span>
          <span class="ch-title">Ventas del día</span>
        </div>
        <?php if ($diff_v !== null): ?>
        <span class="badge <?= $diff_v >= 0 ? 'b-ok' : 'b-bad' ?>">
          <?= $diff_v >= 0 ? '+' : '' ?><?= $diff_v ?>% vs ayer
        </span>
        <?php else: ?>
        <span class="badge b-neu">Sin histórico</span>
        <?php endif; ?>
      </div>
      <div class="bign nar">$<?= number_format($ventas_hoy, 0, ',', '.') ?></div>
      <div class="sublbl"><?= $num_ventas ?> venta<?= $num_ventas != 1 ? 's' : '' ?> registrada<?= $num_ventas != 1 ? 's' : '' ?> hoy</div>
      <?php if (!empty($top_ventas)): ?>
      <table class="mt">
        <thead><tr><th>Producto</th><th>Und.</th><th>Total</th></tr></thead>
        <tbody>
          <?php foreach ($top_ventas as $tv): ?>
          <tr>
            <td title="<?= htmlspecialchars($tv['nombre']) ?>"><?= htmlspecialchars($tv['nombre']) ?></td>
            <td><?= $tv['u'] ?></td>
            <td>$<?= number_format($tv['t'], 0, ',', '.') ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty"><i class="bi bi-bag-x"></i>Sin ventas registradas hoy</div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-red"><i class="bi bi-exclamation-triangle-fill"></i></span>
          <span class="ch-title">Stock bajo</span>
        </div>
        <span class="badge <?= $num_alertas > 0 ? 'b-bad' : 'b-ok' ?>">
          <?= $num_alertas > 0 ? $num_alertas . ' insumo' . ($num_alertas != 1 ? 's' : '') : '✓ Todo OK' ?>
        </span>
      </div>
      <?php if (empty($alertas)): ?>
      <div class="empty">
        <i class="bi bi-check-circle-fill" style="color:#4caf50;opacity:1"></i>
        Inventario en orden
      </div>
      <?php else: ?>
        <?php foreach ($alertas as $a):
          $pct = $a['punto_reposicion'] > 0 ? min(100, round(($a['stock_actual'] / $a['punto_reposicion']) * 100)) : 0;
        ?>
        <div class="al-row">
          <span style="font-size:.9rem;flex-shrink:0">⚠️</span>
          <span class="al-name" title="<?= htmlspecialchars($a['nombre']) ?>"><?= htmlspecialchars($a['nombre']) ?></span>
          <div class="al-bar-w"><div class="al-bar-f" style="width:<?= $pct ?>%"></div></div>
          <span class="al-val"><?= formatoInteligente($a['stock_actual']) ?> <?= $a['unidad_medida'] ?></span>
        </div>
        <?php endforeach; ?>
        <div style="margin-top:.65rem;flex-shrink:0">
          <a href="<?= APP_URL ?>/modules/compras/index.php"
             style="font-size:.75rem;color:var(--c3);font-weight:700;text-decoration:none">
            <i class="bi bi-plus-circle"></i> Registrar compra
          </a>
        </div>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-grn"><i class="bi bi-graph-up-arrow"></i></span>
          <span class="ch-title">Finanzas — <?= $mes_actual ?></span>
        </div>
      </div>
      <div class="fin3">
        <div class="fcard">
          <div class="fcard-lbl"><i class="bi bi-arrow-up-circle" style="color:#2e7d32"></i>Ingresos</div>
          <div class="fcard-val grn">$<?= number_format($ingresos_mes, 0, ',', '.') ?></div>
        </div>
        <div class="fcard">
          <div class="fcard-lbl"><i class="bi bi-arrow-down-circle" style="color:#c62828"></i>Compras</div>
          <div class="fcard-val red">$<?= number_format($compras_mes, 0, ',', '.') ?></div>
        </div>
        <div class="fcard">
          <div class="fcard-lbl"><i class="bi bi-calculator" style="color:var(--c3)"></i>Utilidad</div>
          <div class="fcard-val <?= $utilidad_mes >= 0 ? 'grn' : 'red' ?>">
            $<?= number_format(abs($utilidad_mes), 0, ',', '.') ?>
          </div>
        </div>
      </div>
      <div class="graf-lbl">Ventas últimos 7 días</div>
      <div class="graf-wrap" style="height:88px;flex:none">
        <?php foreach ($chart as $dc):
          $h = $chartMax > 0 ? max(4, round(($dc['v'] / $chartMax) * 100)) . '%' : '4%';
        ?>
        <div class="gc <?= $dc['hoy'] ? 'today' : '' ?>">
          <div class="gb" style="height:<?= $h ?>" title="$<?= number_format($dc['v'], 0, ',', '.') ?>"></div>
          <span class="gd"><?= substr($dc['lbl'], 0, 2) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-nar"><i class="bi bi-fire"></i></span>
          <span class="ch-title">Últimas producciones</span>
        </div>
        <a href="<?= APP_URL ?>/modules/produccion/nueva_produccion.php"
           style="font-size:.73rem;color:var(--c3);font-weight:700;text-decoration:none">
          <i class="bi bi-plus-lg"></i> Nueva
        </a>
      </div>
      <?php if (empty($prods_recientes)): ?>
      <div class="empty"><i class="bi bi-inbox"></i>Sin producciones aún</div>
      <?php else: ?>
        <?php foreach ($prods_recientes as $pr): ?>
        <div class="pr-row">
          <div class="pr-dot"></div>
          <div style="flex:1;min-width:0">
            <div class="pr-name"><?= htmlspecialchars($pr['nombre']) ?></div>
            <div class="pr-det"><?= formatoInteligente($pr['cantidad_tandas']) ?> <?= $pr['unidad_produccion'] ?></div>
          </div>
          <div class="pr-time"><?= date('d/m H:i', strtotime($pr['fecha_produccion'])) ?></div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-caf"><i class="bi bi-lightning-fill"></i></span>
          <span class="ch-title">Acciones rápidas</span>
        </div>
      </div>
      <div class="ac-grid">
        <?php
        $acs = [
          ['/modules/produccion/nueva_produccion.php', 'bi-fire',           'Producción', 'var(--c3)', 'rgba(198,113,36,.08)'],
          ['/modules/ventas/index.php',          'bi-bag-plus-fill',  'Nueva venta','#198754',   'rgba(25,135,84,.08)'],
          ['/modules/compras/index.php',               'bi-cart-plus-fill', 'Compra',     '#0d6efd',   'rgba(13,110,253,.08)'],
          ['/modules/inventario/index.php',            'bi-box-seam-fill',  'Inventario', 'var(--c1)', 'rgba(148,91,53,.08)'],
          ['/modules/ventas/clientes.php',             'bi-shop',           'Tiendas',   '#e91e63',   'rgba(233,30,99,.08)'],
          ['/modules/recetas/index.php',               'bi-journal-richtext','Recetas',   '#6f42c1',   'rgba(111,66,193,.08)'],
        ];
        foreach ($acs as [$url, $ico, $lbl, $col, $bg]): ?>
        <a href="<?= APP_URL . $url ?>" class="ac-btn"
           style="border-color:<?= $bg ?>;background:<?= $bg ?>">
          <i class="bi <?= $ico ?>" style="color:<?= $col ?>"></i>
          <?= $lbl ?>
        </a>
        <?php endforeach; ?>
      </div>
    </div>


    <!-- ── Ingredientes más usados hoy ── -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <span class="ch-ico ico-nar"><i class="bi bi-boxes"></i></span>
          <span class="ch-title">Ingredientes más usados hoy</span>
        </div>
        <a href="<?= APP_URL ?>/modules/finanzas/index.php"
           style="font-size:.73rem;color:var(--c3);font-weight:700;text-decoration:none">
          <i class="bi bi-graph-up"></i> Análisis
        </a>
      </div>
      <?php if (empty($consumo_hoy)): ?>
      <div class="empty"><i class="bi bi-box-seam"></i>Sin producción hoy</div>
      <?php else: ?>
        <?php foreach ($consumo_hoy as $c):
          $pct = $max_consumo_hoy > 0 ? max(4, round(($c['total'] / $max_consumo_hoy) * 100)) : 4;
        ?>
        <div class="al-row">
          <span class="al-name"><?= htmlspecialchars($c['nombre']) ?></span>
          <div class="al-bar-w">
            <div class="al-bar-f" style="width:<?= $pct ?>%;background:linear-gradient(90deg,var(--c3),var(--c5))"></div>
          </div>
          <span class="al-val" style="color:var(--c1)">
            <?= formatoInteligente((float)$c['total']) ?> <span style="font-weight:400;color:var(--ink3)"><?= $c['unidad_medida'] ?></span>
          </span>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
    // Variables dinámicas para el JS externo
    const API_WEATHER_URL = "<?= get_env('API_OPEN_METEO_URL', 'https://api.open-meteo.com/v1/forecast') ?>";
</script>
<script src="<?= APP_URL ?>/assets/js/tablero.js"></script>
