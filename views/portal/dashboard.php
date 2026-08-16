<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Tablero — BreadControl</title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/img/favicon-32.png">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fuentes.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/bootstrap-icons.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal_dashboard.css?v=<?= APP_VERSION ?>">
</head>
<body>
<nav>
    <div class="n-logo">
        <img src="<?= APP_URL ?>/assets/img/logo.png" alt="BreadControl" class="n-logo-img">
        <div>
            <div class="n-logo-name">BreadControl</div>
            <div class="n-logo-sub"><?= $es_instructor ? 'Instructor ADSO' : 'Portal Cliente' ?></div>
        </div>
    </div>
    <div class="n-right">
        <a href="perfil.php" class="n-user" title="Mi Perfil">
            <div class="n-avatar">
                <?php if (!empty($cliente_info['foto_url'])): ?>
                    <img src="<?= htmlspecialchars($cliente_info['foto_url'] ?? '') ?>" alt="avatar">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['cliente_nombre'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="n-uname"><?= htmlspecialchars($_SESSION['cliente_nombre'] ?? '') ?></div>
                <div class="n-urole"><?= $es_instructor ? 'Instructor' : 'Cliente' ?></div>
            </div>
        </a>
        <?php if ($es_instructor): ?>
        <a href="mis_aprendices.php" class="n-logout" title="Mis aprendices"><i class="bi bi-mortarboard-fill"></i></a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/assets/docs/Manual_BreadControl_Clientes.pdf" target="_blank" rel="noopener" class="n-logout" title="Manual de Usuario"><i class="bi bi-question-circle"></i></a>
        <a href="logout.php" class="n-logout" title="Cerrar sesión"><i class="bi bi-box-arrow-right"></i></a>
    </div>
</nav>

<div class="page">

    <!-- ══ BANNER ══ -->
    <div class="wc-banner">
        <div class="wc-left">
            <div>
                <div class="wc-greeting"><?= $es_instructor ? 'Gestión ADSO' : 'Panadería BreadControl' ?></div>
                <div class="wc-name">
                    <?php if ($es_instructor): ?>
                        Portal <em>Instructor</em>
                    <?php else: ?>
                        Portal de <em>Clientes</em>
                    <?php endif; ?>
                </div>
                <div class="wc-sub">
                    <?php if ($es_instructor): ?>
                        <?= $total_reg ?> aprendices registrados · <?= date('F Y') ?>
                    <?php else: ?>
                        Gestiona tus pedidos y compras · <?= date('F Y') ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="wc-pills">
            <?php if ($es_instructor): ?>
                <div class="wc-pill">
                    <div class="wc-pill-num"><?= $resumen_fin['aprendices_activos'] ?></div>
                    <div class="wc-pill-lbl">Con pedidos</div>
                </div>
                <div class="wc-pill">
                    <div class="wc-pill-num"><?= $resumen_fin['total_pedidos'] ?></div>
                    <div class="wc-pill-lbl">Pedidos</div>
                </div>
            <?php else: ?>
                <div class="wc-pill">
                    <div class="wc-pill-num"><?= count($mis_pedidos) ?></div>
                    <div class="wc-pill-lbl">Pedidos</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($success_msg)): ?>
    <div style="background:#e8f5e9;border:1px solid #a5d6a7;border-left:4px solid #2e7d32;border-radius:10px;padding:.85rem 1.1rem;font-size:.85rem;color:#1b5e20;display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;">
        <i class="bi bi-check-circle-fill"></i>
        <?= htmlspecialchars($success_msg ?? '') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_msg)): ?>
    <div style="background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:.85rem 1.1rem;font-size:.85rem;color:#c62828;display:flex;align-items:center;gap:.6rem;margin-bottom:.8rem;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= htmlspecialchars($error_msg ?? '') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($_GET['epago'])): ?>
    <div style="background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:.85rem 1.1rem;font-size:.85rem;color:#c62828;display:flex;align-items:center;gap:.6rem;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= htmlspecialchars($_GET['epago'] ?? '') ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($mostrar_canje)): ?>
    <!-- ══ AVISO CANJE APRENDIZ SENA (solo si hay código activo) ══ -->
    <div style="background:linear-gradient(120deg,#eef8e6,#fff);border:1px solid #bfe6a3;border-left:4px solid #39A900;border-radius:14px;padding:1.1rem 1.3rem;margin-bottom:.8rem;box-shadow:0 1px 8px rgba(43,125,0,.09);">
        <div style="display:flex;align-items:center;gap:.6rem;font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:#2b7d00;margin-bottom:.35rem;">
            <i class="bi bi-mortarboard-fill"></i> ¿Eres aprendiz del SENA?
        </div>
        <div style="font-size:.85rem;color:#3c5a25;margin-bottom:.9rem;line-height:1.45;">
            Ingresa el código de tu instructor para unirte a su grupo y pedir pan a la cuenta ADSO.
        </div>
        <form method="post" style="display:flex;gap:.6rem;flex-wrap:wrap;align-items:center;">
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            <input type="text" name="codigo_aprendiz" maxlength="16" required placeholder="Ej: K7M4P2QR"
                   style="flex:1;min-width:180px;padding:.65rem .85rem;border:1px solid #bfe6a3;border-radius:9px;font-family:inherit;font-size:.95rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;background:#f3faec;color:#14260a;outline:none;">
            <button type="submit" name="canjear_codigo"
                    style="background:linear-gradient(135deg,#39A900,#2b7d00);color:#fff;border:none;border-radius:10px;padding:.7rem 1.2rem;font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:inline-flex;align-items:center;gap:.45rem;">
                <i class="bi bi-check2-circle"></i> Canjear código
            </button>
        </form>
    </div>
    <?php endif; ?>

    <?php if ($es_instructor): ?>
    <!-- ══ RESUMEN FINANCIERO ══ -->
    <div class="stat-grid">
        <div class="stat-card red">
            <i class="bi bi-exclamation-circle-fill stat-icon red"></i>
            <div class="stat-val">$<?= number_format($resumen_fin['pendiente_total'], 0, ',', '.') ?></div>
            <div class="stat-lbl">Saldo Pendiente Total</div>
            <div class="stat-sub">De todos los aprendices</div>
        </div>
        <div class="stat-card green">
            <i class="bi bi-graph-up-arrow stat-icon green"></i>
            <div class="stat-val">$<?= number_format($resumen_fin['total_mes'], 0, ',', '.') ?></div>
            <div class="stat-lbl">Total del Mes</div>
            <div class="stat-sub"><?= date('F Y') ?></div>
        </div>
        <div class="stat-card blue">
            <i class="bi bi-people-fill stat-icon blue"></i>
            <div class="stat-val"><?= $resumen_fin['aprendices_activos'] ?> / <?= $total_reg ?></div>
            <div class="stat-lbl">Aprendices Activos</div>
            <div class="stat-sub">Han realizado pedidos</div>
        </div>
        <div class="stat-card orange">
            <i class="bi bi-basket2-fill stat-icon orange"></i>
            <div class="stat-val"><?= $resumen_fin['total_pedidos'] ?></div>
            <div class="stat-lbl">Pedidos Totales</div>
            <div class="stat-sub">Historial completo ADSO</div>
        </div>
    </div>



    <!-- ══ TARJETA PAGO INSTRUCTOR ══ -->
    <?php if ($es_instructor && $resumen_fin['pendiente_total'] > 0): ?>
    <div style="background:#fff;border:1.5px solid rgba(239,68,68,.25);border-radius:14px;box-shadow:var(--shadow);padding:1.1rem 1.4rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap; margin-top: .8rem;">
        <div style="display:flex;align-items:center;gap:.85rem;">
            <div style="width:44px;height:44px;border-radius:12px;background:rgba(239,68,68,.1);display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:#dc2626;flex-shrink:0;">
                <i class="bi bi-cash-coin"></i>
            </div>
            <div>
                <div style="font-family:'Fraunces',serif;font-size:1rem;font-weight:800;color:var(--ink);">Saldo pendiente ADSO</div>
                <div style="font-size:.75rem;color:var(--ink3);margin-top:.15rem;">Transfiere por Nequi Negocios — el propietario confirmará el recibo.</div>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;">
            <div style="font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;color:#dc2626;">
                $<?= number_format($resumen_fin['pendiente_total'], 0, ',', '.') ?>
            </div>
            <?php if (!empty($nequi_config['nequi_link_pago'])): ?>
            <button type="button" onclick="abrirModalPagoInstructor()"
                    style="display:inline-flex;align-items:center;gap:.45rem;background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;padding:.65rem 1.2rem;border-radius:10px;font-size:.85rem;font-weight:700;border:none;cursor:pointer;box-shadow:0 4px 14px rgba(46,125,50,.25);">
                <i class="bi bi-list-check"></i> Seleccionar y pagar
            </button>
            <?php else: ?>
            <span style="font-size:.78rem;color:var(--ink3);padding:.5rem .9rem;border-radius:8px;background:var(--clight);border:1px solid var(--border);">
                <i class="bi bi-info-circle"></i> Contacta al propietario para datos de pago
            </span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($es_instructor && !empty($pedidos_por_aprobar)): ?>
    <!-- ══ PEDIDOS POR APROBAR (Instructor) ══ -->
    <div class="section-header" style="margin-top: 1.5rem;">
        <div class="section-title"><i class="bi bi-clock-history"></i> Pedidos de Aprendices por Aprobar</div>
    </div>
    
    <div id="bulk-approve-bar" style="display:none; align-items:center; gap:.7rem; background:#e8f5e9; border:1px solid #a5d6a7; border-radius:10px; padding:.6rem 1rem; margin-bottom:.8rem; flex-wrap:wrap;">
        <?php // La etiqueta anterior decía «Fecha Entrega Lote», jerga que no explicaba
              // lo que hace el campo: al aprobar, esa fecha se ESCRIBE en todos los
              // pedidos marcados, que hasta entonces figuran como «Por definir».
              // Quien abría esta pantalla no tenía forma de deducirlo. ?>
        <span style="font-size:.85rem; font-weight:700; color:#1b5e20; width:100%;">
            <i class="bi bi-check2-square"></i>
            ¿Cuándo entregas los <span id="bulk-approve-count">0</span> pedidos seleccionados?
        </span>
        <span style="font-size:.78rem; color:#2e7d32; width:100%; margin-top:-.35rem;">
            Al aprobar, esta fecha y hora quedarán fijadas en todos ellos. Si unos se
            entregan otro día, apruébalos por separado.
        </span>

        <form method="POST" id="form-bulk-approve" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap; margin:0; width: auto; background: none; border: none; box-shadow: none; padding: 0;">
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            <div id="bulk-approve-ids-container"></div>

            <label for="bulk-fecha" style="font-size:.8rem; font-weight:600; color:#1b5e20;">Fecha de entrega:</label>
            <input type="date" name="fecha_entrega" id="bulk-fecha" min="<?= date('Y-m-d') ?>" required style="padding:.45rem; border:1px solid #a5d6a7; border-radius:8px; font-size:.83rem;">

            <label for="bulk-hora" style="font-size:.8rem; font-weight:600; color:#1b5e20;">Hora:</label>
            <input type="time" name="hora_entrega" id="bulk-hora" min="07:00" max="20:00" value="08:00" required style="padding:.45rem; border:1px solid #a5d6a7; border-radius:8px; font-size:.83rem; width:100px;">
            
            <button type="button" onclick="submitBulkApprove()" style="background:linear-gradient(135deg,#2e7d32,#1b5e20); color:#fff; border:none; padding:.45rem 1rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:.8rem;">
                <i class="bi bi-check-circle-fill"></i> Aprobar Seleccionados
            </button>
            <button type="button" onclick="submitBulkReject()" style="background:linear-gradient(135deg,#d32f2f,#c62828); color:#fff; border:none; padding:.45rem 1rem; border-radius:8px; font-weight:700; cursor:pointer; font-size:.8rem;">
                <i class="bi bi-x-circle-fill"></i> Rechazar Seleccionados
            </button>
        </form>
    </div>

    <div class="card" style="padding: 0; overflow: hidden; margin-bottom: 1.5rem;">
        <div class="tbl-wrap">
            <table class="gt">
                <thead>
                    <tr>
                        <th style="width:30px;"><input type="checkbox" id="chk-all-approve" onclick="toggleAllApprove(this)"></th>
                        <th>Aprendiz</th>
                        <th>Pedido #</th>
                        <th>Fecha de Entrega</th>
                        <th>Monto</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pedidos_por_aprobar as $pa): ?>
                    <tr>
                        <td data-label="Seleccionar"><input type="checkbox" name="ids_aprobar[]" value="<?= $pa['id_pedido'] ?>" class="chk-approve" onchange="updateBulkApproveBar()"></td>
                        <td data-label="Aprendiz">
                            <strong><?= htmlspecialchars($pa['nombre_creador'] ?? '') ?></strong>
                        </td>
                        <td data-label="Pedido #">
                            #<?= $pa['id_pedido'] ?>
                        </td>
                        <td data-label="Fecha de Entrega" style="font-size: .8rem; color: var(--ink2);">
                            <?= formatearFechaEntrega($pa['fecha_entrega']) ?>
                        </td>
                        <td data-label="Monto" style="font-weight: 700; color: var(--ink);">
                            $<?= number_format($pa['total_estimado'], 0, ',', '.') ?>
                        </td>
                        <td data-label="Acción" style="text-align:right;">
                            <div style="display:inline-flex; align-items:center; gap:.4rem; flex-wrap:wrap; justify-content: flex-end;">
                                <a href="detalle_pedido.php?id=<?= $pa['id_pedido'] ?>" class="btn-ver" style="padding:.5rem .7rem;">Ver Detalles</a>
                                
                                <div class="individual-actions" id="ind-actions-<?= $pa['id_pedido'] ?>" style="display:inline-flex; align-items:center; gap:.4rem; flex-wrap:wrap;">
                                    <form method="POST" style="display:inline-block; text-align: left; background: var(--clight); border: 1px solid var(--border); border-radius: 8px; padding: .4rem;" onsubmit="return confirm('¿Aprobar el pedido #<?= $pa['id_pedido'] ?> del aprendiz?');">
                                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                        <input type="hidden" name="aprobar_aprendiz_id" value="<?= $pa['id_pedido'] ?>">
                                        <div style="display: flex; gap: .3rem; align-items: center; margin-bottom: .3rem; flex-wrap: wrap;">
                                            <input type="date" name="fecha_entrega" min="<?= date('Y-m-d') ?>" required style="padding: .2rem; font-size: .75rem; border: 1px solid var(--border); border-radius: 4px; width: 110px;">
                                            <input type="time" name="hora_entrega" min="07:00" max="20:00" value="08:00" required style="padding: .2rem; font-size: .75rem; border: 1px solid var(--border); border-radius: 4px; width: 80px;">
                                        </div>
                                        <button type="submit" style="width: 100%; padding: .35rem .6rem; font-size: .78rem; border-radius: 6px; background: linear-gradient(135deg, #2e7d32, #1b5e20); color:#fff; border:none; cursor:pointer; font-weight:600; display: inline-flex; align-items: center; justify-content: center; gap: .25rem; transition: all .2s;">
                                            <i class="bi bi-check-lg"></i> Aprobar
                                        </button>
                                    </form>
                                    
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('¿Rechazar el pedido #<?= $pa['id_pedido'] ?> del aprendiz?');">
                                        <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                        <input type="hidden" name="rechazar_aprendiz_id" value="<?= $pa['id_pedido'] ?>">
                                        <button type="submit" style="padding: .5rem .7rem; font-size: .78rem; border-radius: 9px; background: linear-gradient(135deg, #d32f2f, #c62828); color:#fff; border:none; cursor:pointer; font-weight:600; display: inline-flex; align-items: center; gap: .25rem; transition: all .2s;">
                                            <i class="bi bi-x-lg"></i> Rechazar
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <!-- ══ TABLA APRENDICES ══ -->
    <div class="section-header">
        <div class="section-title"><i class="bi bi-mortarboard-fill"></i> Mis Aprendices</div>
        <div class="search-box">
            <i class="bi bi-search"></i>
            <input type="text" id="buscar-aprendiz" placeholder="Buscar aprendiz...">
        </div>
    </div>

    <div class="card">
        <div class="ch">
            <div class="ch-left">
                <div class="ch-ico"><i class="bi bi-people"></i></div>
                <span class="ch-title">Resumen por Aprendiz</span>
            </div>
            <div style="display: flex; align-items: center; gap: 0.6rem;">
                <a href="exportar_cartera_instructor.php" target="_blank" class="btn-ver" style="display: inline-flex; align-items: center; gap: 0.25rem; font-size: 0.72rem; padding: 0.35rem 0.7rem; border-color: var(--c3); color: var(--c3);">
                    <i class="bi bi-file-earmark-pdf-fill"></i> Exportar Cartera PDF
                </a>
                <span style="font-size:.72rem;color:var(--ink3);"><?= count($aprendices) ?> registrados</span>
            </div>
        </div>
        <div class="tbl-wrap">
            <table class="gt" id="tabla-aprendices">
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <th>Pedidos</th>
                        <th>Total Comprado</th>
                        <th>Saldo Pendiente</th>
                        <th>Cupo Semanal</th>
                        <th>Último Pedido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($aprendices)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="bi bi-person-x"></i>
                            <p>Aún no hay aprendices registrados en el portal.</p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($aprendices as $a): ?>
                    <?php
                        $colores = ['#945b35','#c67124','#2e7d32','#1565c0','#6a1b9a','#c62828'];
                        $color   = $colores[($a['id_cliente']) % count($colores)];
                    ?>
                    <tr class="fila-aprendiz" data-nombre="<?= strtolower(htmlspecialchars($a['nombre'] ?? '')) ?>">
                        <td data-label="Aprendiz">
                            <div class="apr-name-wrap">
                                <div class="apr-avatar" style="background:<?= $color ?>;">
                                    <?php if (!empty($a['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($a['foto_url'] ?? '') ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($a['nombre'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="apr-name"><?= htmlspecialchars($a['nombre'] ?? '') ?></div>
                                    <div class="apr-contact">
                                        <?= $a['telefono'] ? htmlspecialchars($a['telefono'] ?? '') : ($a['email'] ? htmlspecialchars($a['email'] ?? '') : '—') ?>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td data-label="Pedidos" style="font-weight:700;"><?= $a['total_pedidos'] ?></td>
                        <td data-label="Total Comprado" style="font-weight:600;color:var(--ink2);">
                            $<?= number_format($a['total_comprado'], 0, ',', '.') ?>
                        </td>
                        <td data-label="Saldo Pendiente">
                            <?php if ($a['total_pedidos'] == 0): ?>
                                <span class="badge-sin-pedidos"><i class="bi bi-dash"></i> Sin pedidos</span>
                            <?php elseif ($a['saldo_pendiente'] > 0): ?>
                                <span class="badge-pendiente"><i class="bi bi-clock-fill"></i> $<?= number_format($a['saldo_pendiente'], 0, ',', '.') ?></span>
                            <?php else: ?>
                                <span class="badge-ok"><i class="bi bi-check-circle-fill"></i> Al día</span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Cupo Semanal">
                            <?php
                                $consumido = (float)($a['consumido_semana'] ?? 0);
                                $cupo = (float)($a['cupo_semanal'] ?? 20000);
                                $pct = ($cupo > 0) ? ($consumido / $cupo) * 100 : 100;
                                $color_pct = 'var(--ink2)';
                                if ($pct >= 100) {
                                    $color_pct = '#d32f2f';
                                } elseif ($pct >= 80) {
                                    $color_pct = '#f57c00';
                                }
                            ?>
                            <div style="display:flex; flex-direction:column; gap:0.25rem;">
                                <div style="font-size:0.78rem; font-weight:700; color:<?= $color_pct ?>;">
                                    $<?= number_format($consumido, 0, ',', '.') ?> / $<?= number_format($cupo, 0, ',', '.') ?>
                                </div>
                                <form method="POST" style="display:inline-flex; align-items:center; gap:0.25rem; margin-top:0.15rem;">
                                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                                    <input type="hidden" name="actualizar_cupo_aprendiz_id" value="<?= $a['id_cliente'] ?>">
                                    <input type="number" name="cupo_semanal" value="<?= (int)$cupo ?>" min="0" max="100000" step="500" oninput="if(this.value !== '' && parseFloat(this.value) > 100000) this.value = 100000; if(this.value !== '' && parseFloat(this.value) < 0) this.value = 0;" style="width:75px; height:1.6rem; padding: 0 0.4rem; font-size: 0.72rem; border-radius: 6px; border:1px solid var(--border); background:var(--input-bg); color:var(--fg); text-align:right; outline:none;">
                                    <button type="submit" title="Actualizar cupo" style="background:none; border:none; color:var(--c3); cursor:pointer; font-size:0.95rem; padding:0; display:inline-flex; align-items:center;" aria-label="Actualizar cupo">
                                        <i class="bi bi-check-circle-fill"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                        <td data-label="Último Pedido" style="font-size:.76rem;color:var(--ink3);">
                            <?= $a['ultimo_pedido'] ? date('d/m/Y', strtotime($a['ultimo_pedido'])) : '—' ?>
                        </td>
                        <td data-label="Acciones">
                            <?php if ($a['total_pedidos'] > 0): ?>
                            <a href="dashboard.php?aprendiz_id=<?= $a['id_cliente'] ?>" class="btn-filtrar">
                                <i class="bi bi-funnel-fill"></i> Ver pedidos
                            </a>
                            <?php else: ?>
                            <span style="font-size:.75rem;color:var(--ink3);">Sin pedidos</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; // fin es_instructor ?>

    <!-- ══ SECCIÓN PEDIDOS ══ -->
    <div class="topbar" style="margin-top:.4rem;">
        <div class="mod-titulo">
            <i class="bi bi-basket2-fill"></i>
            <?php if ($nombre_filtro): ?>
                Pedidos de <?= htmlspecialchars($nombre_filtro ?? '') ?>
            <?php else: ?>
                <?= $es_instructor ? 'Todos los Pedidos' : 'Mis Pedidos' ?>
            <?php endif; ?>
        </div>
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
            <?php if ($saldo_pendiente > 0 && !$es_instructor): ?>
                <a href="pagar_consolidado.php" class="btn-primary" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);">
                    <i class="bi bi-credit-card"></i> Pagar Saldo ($<?= number_format($saldo_pendiente, 0, ',', '.') ?>)
                </a>
            <?php endif; ?>
            <a href="nuevo_pedido.php" class="btn-primary"><i class="bi bi-plus-circle"></i> Nuevo Pedido</a>
        </div>
    </div>

    <?php if ($nombre_filtro): ?>
    <div class="filtro-activo">
        <i class="bi bi-funnel-fill"></i>
        Mostrando pedidos de <strong><?= htmlspecialchars($nombre_filtro ?? '') ?></strong>
        <a href="dashboard.php" title="Quitar filtro"><i class="bi bi-x-circle-fill"></i></a>
    </div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="filter-grid" id="form-filtros">
            <?php if ($f_aprendiz): ?>
                <input type="hidden" name="aprendiz_id" value="<?= $f_aprendiz ?>">
            <?php endif; ?>
            <?php // Las dos ramas de un if/else escribían el mismo id, lo que hacía
                  // creer que estaba duplicado. Es un solo campo: sin variedad va vacío. ?>
            <input type="hidden" name="variedad_id" id="hdn-variedad" value="<?= $f_variedad ?: '' ?>">
            <div class="filter-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_estado==='pendiente' ?'selected':'' ?>>Pendiente</option>
                    <option value="confirmado" <?= $f_estado==='confirmado'?'selected':'' ?>>Confirmado</option>
                    <option value="rechazado"  <?= $f_estado==='rechazado' ?'selected':'' ?>>Rechazado</option>
                </select>
            </div>
            <div class="filter-group">
                <label for="orden">Ordenar por</label>
                <select id="orden" name="orden" class="filter-input">
                    <option value="recientes" <?= $f_orden==='recientes'?'selected':'' ?>>Más recientes</option>
                    <option value="antiguos"  <?= $f_orden==='antiguos' ?'selected':'' ?>>Más antiguos</option>
                    <option value="entrega"   <?= $f_orden==='entrega'  ?'selected':'' ?>>Entrega más próxima</option>
                </select>
            </div>
            <button type="submit" class="btn-filter"><i class="bi bi-filter"></i> Filtrar</button>
            <a href="dashboard.php<?= $f_aprendiz ? '?aprendiz_id='.$f_aprendiz : '' ?>" class="btn-clear"><i class="bi bi-x-circle"></i> Limpiar</a>
        </form>

        <?php if ($nombre_variedad): ?>
        <div class="filtro-activo" style="margin-top:.7rem;">
            <i class="bi bi-basket2-fill"></i>
            Mostrando pedidos con <strong><?= htmlspecialchars($nombre_variedad ?? '') ?></strong>
            <a href="dashboard.php<?= $f_aprendiz ? '?aprendiz_id='.$f_aprendiz : '' ?>" title="Quitar filtro"><i class="bi bi-x-circle-fill"></i></a>
        </div>
        <?php endif; ?>

        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border);display:flex;justify-content:center;">
            <button type="button" class="btn-search-pan" onclick="abrirModalPan()">
                <i class="bi bi-basket2"></i> <span>Buscar por tipo de pan</span>
                <?php if ($nombre_variedad): ?>
                    <span class="badge-variedad-filtro"><?= htmlspecialchars($nombre_variedad ?? '') ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>

    <?php if ($es_tienda): ?>
    <div id="bulk-bar-dash" class="bulk-bar-dash">
        <span class="bulk-info"><i class="bi bi-check2-square"></i> <span id="bulk-count-dash">0</span> pedido(s) seleccionado(s)</span>
        <button type="button" class="btn-exp-dash btn-exp-excel" onclick="exportarDash('excel')"><i class="bi bi-file-earmark-excel-fill"></i> Excel</button>
        <button type="button" class="btn-exp-dash btn-exp-pdf"   onclick="exportarDash('pdf')"><i class="bi bi-file-earmark-pdf-fill"></i> PDF</button>
    </div>
    <?php endif; ?>

    <div class="card">
        <div class="ch">
            <div class="ch-left">
                <div class="ch-ico"><i class="bi bi-clock-history"></i></div>
                <span class="ch-title">Historial de Pedidos</span>
            </div>
        </div>
        <div class="tbl-wrap">
        <form id="form-dash-export" method="POST" action="exportar_pedidos_dashboard.php" target="_blank">
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            <input type="hidden" name="formato" id="dash-formato" value="">
            <table class="gt">
            <thead>
                <tr>
                    <?php if ($es_tienda): ?>
                    <th style="width:36px;"><input type="checkbox" id="chk-all-dash" title="Seleccionar todos"></th>
                    <?php endif; ?>
                    <th>ID</th>
                    <th>Creado Por</th>
                    <th>Para Entregar</th>
                    <th>Solicitado el</th>
                    <th>Total Est.</th>
                    <th>Estado</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($mis_pedidos)): ?>
                    <tr><td colspan="<?= $es_tienda ? 8 : 7 ?>">
                        <div class="empty-state">
                            <i class="bi bi-receipt"></i>
                            <p><?= $nombre_filtro ? 'Este aprendiz aún no ha realizado pedidos.' : 'Aún no hay pedidos registrados.' ?></p>
                        </div>
                    </td></tr>
                <?php else: ?>
                    <?php foreach ($mis_pedidos as $p): ?>
                    <tr>
                        <?php if ($es_tienda): ?>
                        <td data-label=""><input type="checkbox" name="ids[]" value="<?= $p['id_pedido'] ?>" class="chk-dash"></td>
                        <?php endif; ?>
                        <td data-label="Pedido" style="font-weight:700;color:var(--ink2);">#<?= str_pad($p['id_pedido'],4,'0',STR_PAD_LEFT) ?></td>
                        <td data-label="Creado por" style="font-weight:600;">
                            <?= htmlspecialchars($p['nombre_creador'] ?? 'Yo') ?>
                            <?php if (isset($p['creador_es_aprendiz']) && (int)$p['creador_es_aprendiz'] === 1): ?>
                                <br>
                                <span class="badge-dest <?= (int)$p['id_cliente'] === (int)$p['id_creador'] ? 'personal' : 'adso' ?>">
                                    <?= (int)$p['id_cliente'] === (int)$p['id_creador'] ? 'Personal' : 'ADSO' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td data-label="Para Entregar" style="font-weight:600;color:var(--c1);">
                            <?= formatearFechaEntrega($p['fecha_entrega']) ?>
                        </td>
                        <td data-label="Solicitado" style="font-size:.75rem;color:var(--ink3);"><?= date('d/m/Y H:i', strtotime($p['fecha_solicitud'])) ?></td>
                        <td data-label="Total Est." style="font-weight:700;">$<?= number_format($p['total_estimado'],0,',','.') ?></td>
                        <td data-label="Estado"><span class="estado e-<?= $p['estado'] ?>"><?= $p['estado'] ?></span></td>
                        <td data-label="Acción"><a href="detalle_pedido.php?id=<?= $p['id_pedido'] ?>" class="btn-ver">Ver Detalles</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            </table>
        </form>
        </div>
    </div>

</div><!-- /page -->

<?php if ($es_instructor && !empty($pedidos_pago_instructor)): ?>
<!-- ══ MODAL PAGO INSTRUCTOR ══ -->
<div id="modal-pago-instructor" style="display:none;position:fixed;inset:0;background:rgba(40,21,8,.6);z-index:2000;overflow-y:auto;padding:1.5rem 1rem;">
    <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;margin:0 auto;box-shadow:0 20px 60px rgba(40,21,8,.3);">

        <!-- Cabecera -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--border);background:var(--clight);border-radius:16px 16px 0 0;">
            <div style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:var(--ink);">Datos para pagar instructor</div>
            <button class="modal-close" onclick="cerrarModalPagoInstructor()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>

        <!-- Barra seleccionar todos + total -->
        <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.2rem;border-bottom:1px solid var(--border);background:#fffbf5;">
            <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;font-weight:700;color:var(--ink2);cursor:pointer;">
                <input type="checkbox" id="chk-all-instr" checked onchange="toggleTodosInstructor()" style="width:16px;height:16px;accent-color:#2e7d32;">
                Seleccionar todos (<?= count($pedidos_pago_instructor) ?>)
            </label>
            <span style="font-size:.85rem;font-weight:800;color:#dc2626;" id="total-instr">
                $<?= number_format($resumen_fin['pendiente_total'], 0, ',', '.') ?>
            </span>
        </div>

        <!-- Lista de pedidos -->
        <div style="padding:.8rem 1.2rem;display:flex;flex-direction:column;gap:.5rem;max-height:340px;overflow-y:auto;">
        <?php foreach ($pedidos_pago_instructor as $pp): ?>
        <label style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;border-radius:10px;border:1.5px solid var(--border);cursor:pointer;background:#fff; width: 100%;">
            <input type="checkbox" class="chk-instr"
                   data-monto="<?= (float)$pp['total_estimado'] ?>"
                   checked
                   onchange="recalcularTotalInstructor()"
                   style="width:17px;height:17px;accent-color:#2e7d32;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;color:var(--ink);font-size:.85rem;">
                    Pedido #<?= str_pad($pp['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
                    <?php if (!empty($pp['nombre_creador'])): ?>
                        <span style="font-weight:500;color:var(--ink3);font-size:.75rem;">· <?= htmlspecialchars($pp['nombre_creador'] ?? '') ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:.71rem;color:var(--ink3);">
                    Entrega: <?= formatearFechaEntrega($pp['fecha_entrega']) ?>
                    &nbsp;·&nbsp; Solicitud: <?= date('d/m/Y', strtotime($pp['fecha_solicitud'])) ?>
                </div>
            </div>
            <div style="font-family:'Fraunces',serif;font-weight:800;color:var(--c1);font-size:.95rem;white-space:nowrap;">
                $<?= number_format($pp['total_estimado'], 0, ',', '.') ?>
            </div>
        </label>
        <?php endforeach; ?>
        </div>

        <!-- Footer -->
        <div style="display:flex;gap:.6rem;padding:.9rem 1.2rem;border-top:1px solid var(--border);border-radius:0 0 16px 16px;background:var(--clight);">
            <button type="button" onclick="cerrarModalPagoInstructor()"
                    style="flex:1;padding:.72rem;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--ink3);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;">
                Cancelar
            </button>
            <form method="post" action="pagar_consolidado.php" style="flex:2;display:flex;">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <button type="submit" name="generar_pago" id="btn-ir-nequi"
                   style="flex:1;padding:.72rem;border-radius:10px;border:none;background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                    <i class="bi bi-phone-fill"></i> Registrar y pagar por Nequi
                    <?php if (!empty($nequi_config['nequi_titular'])): ?>
                        <span style="opacity:.7;font-weight:400;font-size:.72rem;">· <?= htmlspecialchars($nequi_config['nequi_titular'] ?? '') ?></span>
                    <?php endif; ?>
                </button>
            </form>
        </div>

        <div style="padding:.6rem 1.2rem 1rem;font-size:.72rem;color:var(--ink3);text-align:center;line-height:1.5;">
            <i class="bi bi-info-circle"></i> Al continuar se registra el pago de <strong>todos</strong> tus pedidos pendientes y se abre el enlace de Nequi. Transfiere el total; el propietario confirmará el recibo.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL BUSCAR POR PAN ══ -->
<div class="modal-backdrop" id="modal-pan" onclick="cerrarModalPan(event)">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="bi bi-basket2-fill"></i> Buscar por tipo de pan</h2>
            <button class="modal-close" onclick="cerrarModalPan()" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="modal-search">
            <div class="modal-search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" id="modal-buscar" placeholder="Escribe el nombre del pan..." autocomplete="off">
            </div>
        </div>
        <div class="modal-body">
            <div class="var-grid" id="var-grid">
                <?php foreach ($variedades as $v): ?>
                <a href="#" class="var-btn <?= $f_variedad === $v['id_variedad'] ? 'selected' : '' ?>"
                   data-id="<?= $v['id_variedad'] ?>"
                   data-nombre="<?= strtolower(htmlspecialchars($v['nombre'] ?? '')) ?>"
                   onclick="seleccionarVariedad(<?= $v['id_variedad'] ?>, event)">
                    <?php if (!empty($v['imagen'])): ?>
                        <img src="<?= APP_URL ?>/assets/img/panes/<?= htmlspecialchars($v['imagen'] ?? '') ?>" alt="" class="var-img">
                    <?php else: ?>
                        <div class="var-img-placeholder">🍞</div>
                    <?php endif; ?>
                    <span class="var-nombre"><?= htmlspecialchars($v['nombre'] ?? '') ?></span>
                </a>
                <?php endforeach; ?>
                <?php if (empty($variedades)): ?>
                <div class="no-results"><i class="bi bi-basket" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>No hay variedades registradas.</div>
                <?php endif; ?>
            </div>
            <div class="no-results" id="no-results-pan" style="display:none;">
                <i class="bi bi-search" style="font-size:2rem;display:block;margin-bottom:.5rem;opacity:.4;"></i>
                No se encontró ninguna variedad con ese nombre.
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-modal-limpiar" onclick="limpiarVariedad()"><i class="bi bi-x-circle"></i> Quitar filtro</button>
            <button class="btn-modal-aplicar" id="btn-aplicar" disabled onclick="aplicarVariedad()"><i class="bi bi-check2-circle"></i> Ver pedidos</button>
        </div>
    </div>
</div>

<script>
<?php if ($es_tienda): ?>
document.getElementById('chk-all-dash').addEventListener('change', function(){
    document.querySelectorAll('.chk-dash').forEach(c => c.checked = this.checked);
    actualizarBulk();
});
document.querySelectorAll('.chk-dash').forEach(c => c.addEventListener('change', actualizarBulk));
function actualizarBulk(){
    var n = document.querySelectorAll('.chk-dash:checked').length;
    document.getElementById('bulk-count-dash').textContent = n;
    document.getElementById('bulk-bar-dash').classList.toggle('visible', n > 0);
}
function exportarDash(fmt){
    var checked = document.querySelectorAll('.chk-dash:checked');
    if(checked.length === 0){ alert('Selecciona al menos un pedido.'); return; }
    document.getElementById('dash-formato').value = fmt;
    document.getElementById('form-dash-export').submit();
}
<?php endif; ?>

<?php if ($es_instructor): ?>
document.getElementById('buscar-aprendiz').addEventListener('input', function(){
    var q = this.value.toLowerCase();
    document.querySelectorAll('.fila-aprendiz').forEach(function(row){
        row.style.display = row.dataset.nombre.includes(q) ? '' : 'none';
    });
});
<?php endif; ?>

// ── Modal pan ──
var variedadSeleccionada = <?= $f_variedad ?: 'null' ?>;

function abrirModalPan() {
    document.getElementById('modal-pan').classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(function(){ document.getElementById('modal-buscar').focus(); }, 200);
}

function cerrarModalPan(e) {
    if (e && e.target !== document.getElementById('modal-pan')) return;
    document.getElementById('modal-pan').classList.remove('open');
    document.body.style.overflow = '';
}

document.addEventListener('keydown', function(e){
    if (e.key === 'Escape') cerrarModalPan();
});

document.getElementById('modal-buscar').addEventListener('input', function(){
    var q = this.value.toLowerCase().trim();
    var items = document.querySelectorAll('.var-btn');
    var visibles = 0;
    items.forEach(function(btn){
        var match = btn.dataset.nombre.includes(q);
        btn.style.display = match ? '' : 'none';
        if (match) visibles++;
    });
    document.getElementById('no-results-pan').style.display = visibles === 0 ? 'block' : 'none';
});

function seleccionarVariedad(id, e) {
    e.preventDefault();
    document.querySelectorAll('.var-btn').forEach(function(b){ b.classList.remove('selected'); });
    var btn = document.querySelector('.var-btn[data-id="' + id + '"]');
    if (btn) btn.classList.add('selected');
    variedadSeleccionada = id;
    document.getElementById('btn-aplicar').disabled = false;
}

function aplicarVariedad() {
    if (!variedadSeleccionada) return;
    document.getElementById('hdn-variedad').value = variedadSeleccionada;
    document.getElementById('form-filtros').submit();
}

function limpiarVariedad() {
    document.getElementById('hdn-variedad').value = '';
    variedadSeleccionada = null;
    document.getElementById('form-filtros').submit();
}

function toggleAllApprove(chk) {
    document.querySelectorAll('.chk-approve').forEach(function(c) {
        c.checked = chk.checked;
    });
    updateBulkApproveBar();
}

function updateBulkApproveBar() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    var bar = document.getElementById('bulk-approve-bar');
    var countEl = document.getElementById('bulk-approve-count');
    if (countEl) countEl.textContent = checked.length;
    if (bar) bar.style.display = checked.length > 0 ? 'flex' : 'none';
    
    // Hide inline date/time and buttons for checked rows
    document.querySelectorAll('.chk-approve').forEach(function(c) {
        var pedId = c.value;
        var indActions = document.getElementById('ind-actions-' + pedId);
        if (indActions) {
            indActions.style.display = c.checked ? 'none' : 'inline-flex';
        }
    });
}

function submitBulkApprove() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    if (checked.length === 0) return;
    var fecha = document.getElementById('bulk-fecha').value;
    var hora = document.getElementById('bulk-hora').value;
    if (!fecha || !hora) {
        alert('Por favor, ingresa fecha y hora de entrega para el lote.');
        return;
    }
    if (!confirm('¿Aprobar los ' + checked.length + ' pedidos seleccionados con entrega para el ' + fecha + ' a las ' + hora + '?')) {
        return;
    }
    var form = document.getElementById('form-bulk-approve');
    var container = document.getElementById('bulk-approve-ids-container');
    container.innerHTML = '';
    checked.forEach(function(chk) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'aprobar_lote_ids[]'; inp.value = chk.value;
        container.appendChild(inp);
    });
    form.submit();
}

function submitBulkReject() {
    var checked = document.querySelectorAll('.chk-approve:checked');
    if (checked.length === 0) return;
    if (!confirm('¿Rechazar los ' + checked.length + ' pedidos seleccionados?')) {
        return;
    }
    var form = document.getElementById('form-bulk-approve');
    var container = document.getElementById('bulk-approve-ids-container');
    container.innerHTML = '';
    checked.forEach(function(chk) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'rechazar_lote_ids[]'; inp.value = chk.value;
        container.appendChild(inp);
    });
    form.submit();
}

<?php if ($es_instructor && !empty($pedidos_pago_instructor)): ?>
// ── Modal pago instructor ──
function abrirModalPagoInstructor() {
    var m = document.getElementById('modal-pago-instructor');
    if (m) { m.style.display = 'block'; document.body.style.overflow = 'hidden'; }
}
function cerrarModalPagoInstructor() {
    var m = document.getElementById('modal-pago-instructor');
    if (m) { m.style.display = 'none'; document.body.style.overflow = ''; }
}
document.getElementById('modal-pago-instructor').addEventListener('click', function(e) {
    if (e.target === this) cerrarModalPagoInstructor();
});
function toggleTodosInstructor() {
    var todos = document.getElementById('chk-all-instr').checked;
    document.querySelectorAll('.chk-instr').forEach(function(c) { c.checked = todos; });
    recalcularTotalInstructor();
}
function recalcularTotalInstructor() {
    var sum = 0;
    document.querySelectorAll('.chk-instr:checked').forEach(function(c) {
        sum += parseFloat(c.dataset.monto) || 0;
    });
    document.getElementById('total-instr').textContent =
        '$' + sum.toLocaleString('es-CO', {maximumFractionDigits: 0});
    var btn = document.getElementById('btn-ir-nequi');
    if (btn) { btn.style.opacity = sum === 0 ? '.4' : '1'; btn.style.pointerEvents = sum === 0 ? 'none' : ''; }
    var chks = document.querySelectorAll('.chk-instr');
    var marcados = document.querySelectorAll('.chk-instr:checked');
    var chkAll = document.getElementById('chk-all-instr');
    if (chkAll) {
        chkAll.indeterminate = marcados.length > 0 && marcados.length < chks.length;
        chkAll.checked = marcados.length === chks.length;
    }
}
<?php endif; ?>
</script>
</body>
</html>
