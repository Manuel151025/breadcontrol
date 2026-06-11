<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/produccion.css">
<style>
  /* Override / Mejora de Layout Grid de Producción */
  .g-body {
    grid-template-columns: 280px 1fr !important;
    gap: 1.1rem !important;
  }
  @media (max-width: 900px) {
    .g-body { grid-template-columns: 1fr !important; }
  }

  /* Tarjetas del panel principal */
  .card {
    background: #ffffff !important;
    border: 1px solid rgba(148, 91, 53, 0.12) !important;
    border-radius: 16px !important;
    box-shadow: 0 4px 12px rgba(148, 91, 53, 0.04) !important;
    transition: all 0.25s ease;
  }
  .card:hover {
    box-shadow: 0 10px 25px rgba(148, 91, 53, 0.08) !important;
  }

  /* Hero de volumen total (KPI nuevo y limpio) */
  .volume-hero {
    background: linear-gradient(135deg, rgba(198, 113, 36, 0.06), rgba(148, 91, 53, 0.02));
    border: 1px dashed rgba(198, 113, 36, 0.22);
    border-radius: 12px;
    padding: 0.9rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.1rem;
  }
  .volume-hero-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: var(--c3);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    box-shadow: 0 4px 10px rgba(198, 113, 36, 0.2);
    flex-shrink: 0;
  }
  .volume-hero-val {
    font-family: 'Fraunces', serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--ink);
    line-height: 1.1;
  }
  .volume-hero-lbl {
    font-size: 0.62rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--ink3);
    font-weight: 700;
    margin-top: 0.1rem;
  }

  /* Top Productos del Mes */
  .top-prod-section {
    border-top: 1px solid rgba(148, 91, 53, 0.08);
    padding-top: 1rem;
  }
  .top-prod-title {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.12em;
    color: var(--ink3);
    margin-bottom: 0.75rem;
  }
  .top-prod-item {
    margin-bottom: 0.7rem;
  }
  .top-prod-item:last-child {
    margin-bottom: 0;
  }
  .top-prod-info {
    display: flex;
    justify-content: space-between;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--ink);
    margin-bottom: 0.25rem;
  }
  .top-prod-name {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    padding-right: 0.5rem;
  }
  .top-prod-val {
    color: var(--c3);
    font-weight: 700;
    flex-shrink: 0;
  }
  .top-prod-bar-bg {
    height: 6px;
    background: rgba(148, 91, 53, 0.06);
    border-radius: 3px;
    overflow: hidden;
  }
  .top-prod-bar-fill {
    height: 100%;
    border-radius: 3px;
    background: linear-gradient(90deg, var(--c3), var(--c4));
    transition: width 0.4s ease;
  }

  /* Tabla de Producción del Día */
  .tbl-wrap {
    padding: 0.5rem;
  }
  .gt {
    border-radius: 12px;
    overflow: hidden;
  }
  .gt th {
    background: var(--clight) !important;
    color: var(--ink2) !important;
    font-size: 0.65rem !important;
    font-weight: 700 !important;
    letter-spacing: 0.05em !important;
    padding: 0.8rem 1rem !important;
    border-bottom: 1px solid rgba(148, 91, 53, 0.15) !important;
  }
  .gt td {
    padding: 0.75rem 1rem !important;
    font-size: 0.82rem !important;
    color: var(--ink) !important;
    border-bottom: 1px solid rgba(148, 91, 53, 0.06) !important;
  }
  .gt tr:hover td {
    background: rgba(198, 113, 36, 0.03) !important;
  }

  /* Badge de Tandas en la tabla */
  .tanda-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(198, 113, 36, 0.07);
    color: var(--c3);
    font-family: 'Fraunces', serif;
    font-size: 0.95rem;
    font-weight: 800;
    padding: 0.15rem 0.55rem;
    border-radius: 6px;
    min-width: 18px;
    text-align: center;
    border: 1px solid rgba(198, 113, 36, 0.12);
  }

  /* Botón de acción (ver detalle) */
  .btn-act {
    width: 32px;
    height: 32px;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
  }
  .btn-edit {
    background: rgba(25, 118, 210, 0.05) !important;
    border: 1px solid rgba(25, 118, 210, 0.15) !important;
    color: #1565c0 !important;
  }
  .btn-edit:hover {
    background: #1565c0 !important;
    border-color: #1565c0 !important;
    color: #fff !important;
    box-shadow: 0 4px 10px rgba(21, 101, 192, 0.2) !important;
  }

  /* Estado vacío premium */
  .empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 0.6rem;
    padding: 5rem 2rem !important;
    color: var(--ink3);
    text-align: center;
  }
  .empty i {
    font-size: 2.5rem !important;
    color: var(--c3) !important;
    background: rgba(198, 113, 36, 0.06);
    width: 64px;
    height: 64px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 0.3rem;
    border: 1px dashed rgba(198, 113, 36, 0.25);
  }
  .empty strong {
    font-family: 'Fraunces', serif;
    font-size: 1.15rem;
    color: var(--ink);
    font-weight: 800;
  }
  .empty span {
    font-size: 0.82rem;
    color: var(--ink3);
  }
</style>

<div class="page">
  <!-- BANNER -->
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Control de <em>Producción</em></div>
        <div class="wc-sub">Registro de tandas diarias · <?= $productos_activos ?> productos activos</div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $prod_hoy > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num"><?= $prod_hoy ?></div>
        <div class="wc-pill-lbl">Tandas hoy</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $prod_ayer ?></div>
        <div class="wc-pill-lbl">Ayer</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $prod_mes ?></div>
        <div class="wc-pill-lbl">Registros mes</div>
      </div>
      <div class="wc-pill ok">
        <div class="wc-pill-num"><?= $productos_activos ?></div>
        <div class="wc-pill-lbl">Productos</div>
      </div>
    </div>
  </div>

  <!-- TOPBAR -->
  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-fire"></i> Producción</div>
    <div class="fil-wrap">
      <span class="fil-lbl">Fecha:</span>
      <form method="get">
        <input type="date" name="fecha" class="fil-date" value="<?= htmlspecialchars($fecha_fil) ?>" onchange="this.form.submit()">
      </form>
      <a href="nueva_produccion.php" class="btn-grad"><i class="bi bi-plus-lg"></i> Nueva con receta</a>
    </div>
  </div>

  <!-- CUERPO -->
  <div class="g-body">

    <!-- PANEL RESUMEN DE PRODUCCIÓN -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-bar-chart-fill"></i></div>
          <span class="ch-title">Resumen de producción</span>
        </div>
      </div>
      <div class="form-body">
        <!-- Hero Volumen Total -->
        <div class="volume-hero">
          <div class="volume-hero-icon"><i class="bi bi-speedometer2"></i></div>
          <div>
            <div class="volume-hero-val"><?= number_format($total_tandas_mes, 0, ',', '.') ?></div>
            <div class="volume-hero-lbl">Tandas en <?= $nombre_mes ?></div>
          </div>
        </div>

        <?php if (!empty($top_productos)): ?>
        <div class="top-prod-section">
          <div class="top-prod-title">Top productos del mes</div>
          <?php foreach ($top_productos as $tp):
            $pct = round(($tp['tandas'] / $max_tandas) * 100);
          ?>
          <div class="top-prod-item">
            <div class="top-prod-info">
              <span class="top-prod-name" title="<?= htmlspecialchars($tp['nombre']) ?>"><?= htmlspecialchars($tp['nombre']) ?></span>
              <span class="top-prod-val"><?= $tp['tandas'] ?> tandas</span>
            </div>
            <div class="top-prod-bar-bg">
              <div class="top-prod-bar-fill" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- TABLA DEL DÍA -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-fire"><i class="bi bi-clipboard-data"></i></div>
          <span class="ch-title">Producción del <?= date('d/m/Y', strtotime($fecha_fil)) ?></span>
        </div>
        <span class="badge b-neu"><?= count($producciones) ?> registros</span>
      </div>
      <div class="tbl-wrap">
        <?php if (empty($producciones)): ?>
        <div class="empty">
          <i class="bi bi-fire"></i>
          <strong>Sin registros</strong>
          <span>No hay producción para esta fecha</span>
        </div>
        <?php else: ?>
        <table class="gt">
          <thead>
            <tr>
              <th>#</th>
              <th>Producto</th>
              <th>Tandas</th>
              <th>Operario</th>
              <th>Hora</th>
              <th>Notas</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($producciones as $pr): ?>
          <tr>
            <td><span style="font-family:'Fraunces',serif;font-weight:700;color:var(--ink3);"><?= $pr['id_produccion'] ?></span></td>
            <td>
              <strong><?= htmlspecialchars($pr['producto']) ?></strong><br>
              <span style="font-size:.7rem;color:var(--ink3);"><?= $pr['unidad_produccion'] ?></span>
            </td>
            <td><span class="tanda-badge"><?= (int)$pr['cantidad_tandas'] ?></span></td>
            <td><?= htmlspecialchars($pr['operario'] ?? '—') ?></td>
            <td><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></td>
            <td style="font-size:.77rem;color:var(--ink3);">
              <?= $pr['observaciones'] ? htmlspecialchars(substr($pr['observaciones'],0,50)).(strlen($pr['observaciones'])>50?'…':'') : '—' ?>
            </td>
            <td>
              <a href="detalle.php?id=<?= $pr['id_produccion'] ?>" class="btn-act btn-edit" title="Ver detalle"><i class="bi bi-eye"></i></a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="font-size:.75rem;color:var(--ink2);text-align:right;text-transform:uppercase;letter-spacing:.08em;">Total tandas</td>
              <td style="font-family:'Fraunces',serif;font-size:1.1rem;color:var(--c3);"><?= (int)$total_tandas ?></td>
              <td colspan="3"></td>
            </tr>
          </tfoot>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

</body></html>
