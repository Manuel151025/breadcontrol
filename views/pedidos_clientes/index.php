<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/pedidos_clientes.css?v=<?= APP_VERSION ?>">

<div class="page">
    <div class="wc-banner">
        <div class="wc-left">
            <div>
                <div class="wc-greeting">Recepción de Solicitudes</div>
                <div class="wc-name">Pedidos de <em>Clientes</em></div>
                <div class="wc-sub">Gestiona las reservas y pedidos de la panadería · <?= date('d/m/Y') ?></div>
            </div>
        </div>
        <div class="wc-pills">
            <div class="wc-pill <?= $pendientes > 0 ? 'warn' : '' ?>">
                <div class="wc-pill-num"><?= $pendientes ?></div>
                <div class="wc-pill-lbl">Pendientes</div>
            </div>
            <div class="wc-pill ok">
                <div class="wc-pill-num"><?= $confirmados ?></div>
                <div class="wc-pill-lbl">Confirmados</div>
            </div>
            <div class="wc-pill">
                <div class="wc-pill-num"><?= $hoy ?></div>
                <div class="wc-pill-lbl">Nuevos hoy</div>
            </div>
        </div>
    </div>

    <?php if ($msg_cobro_ok): ?>
    <div class="msg-bulk-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_cobro_ok ?></div>
    <?php endif; ?>
    <?php if ($msg_cobro_err): ?>
    <div class="msg-bulk-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_cobro_err ?></div>
    <?php endif; ?>

    <?php if (!empty($cobros_pendientes)): ?>
    <div class="card" style="margin-bottom:1rem;">
        <div style="display:flex;align-items:center;gap:.6rem;padding:.85rem 1.1rem;border-bottom:1px solid var(--border);background:var(--clight);">
            <div style="width:30px;height:30px;border-radius:8px;background:rgba(239,68,68,.12);color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:1rem;">
                <i class="bi bi-cash-coin"></i>
            </div>
            <span style="font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.17em;color:#c62828;">
                Cobros Pendientes por Nequi — <?= count($cobros_pendientes) ?> tienda<?= count($cobros_pendientes) !== 1 ? 's' : '' ?>
            </span>
        </div>
        <div style="overflow-x:auto;">
        <table class="gt">
            <thead>
                <tr>
                    <th>Tienda / Cliente</th>
                    <th>Pedidos</th>
                    <th>Total Pendiente</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cobros_pendientes as $idx => $cb): ?>
            <tr>
                <td data-label="Tienda">
                    <div style="font-weight:700;color:var(--ink);"><?= htmlspecialchars($cb['nombre'] ?? '') ?></div>
                    <div style="font-size:.72rem;color:var(--ink3);">Pago manual · Nequi Negocios</div>
                </td>
                <td data-label="Pedidos" style="font-weight:600;"><?= $cb['num_pedidos'] ?></td>
                <td data-label="Total" style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:#c62828;">
                    $<?= number_format($cb['total_pendiente'], 0, ',', '.') ?>
                </td>
                <td data-label="Acción">
                    <button type="button" class="btn-confirmar-lote" onclick="abrirModalCobro(<?= $idx ?>)">
                        <i class="bi bi-cash-stack"></i> Confirmar cobro
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>

    <!-- Modales de cobro por tienda -->
    <?php foreach ($cobros_pendientes as $idx => $cb): ?>
    <div id="modal-cobro-<?= $idx ?>" style="display:none;position:fixed;inset:0;background:rgba(40,21,8,.6);z-index:2000;overflow-y:auto;padding:1.5rem 1rem;">
        <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;margin:0 auto;box-shadow:0 20px 60px rgba(40,21,8,.3);">

            <!-- Cabecera -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:1rem 1.2rem;border-bottom:1px solid var(--border);background:var(--clight);border-radius:16px 16px 0 0;">
                <div>
                    <div style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:var(--ink);">
                        <i class="bi bi-cash-coin" style="color:#dc2626;"></i> <?= htmlspecialchars($cb['nombre'] ?? '') ?>
                    </div>
                    <div style="font-size:.72rem;color:var(--ink3);margin-top:.1rem;">Selecciona los pedidos que ya recibiste por Nequi</div>
                </div>
                <button type="button" onclick="cerrarModalCobro(<?= $idx ?>)" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:#fff;color:var(--ink3);cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <form method="POST" id="form-cobro-<?= $idx ?>">
                <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                <input type="hidden" name="accion" value="confirmar_cobro_tienda">

                <!-- Barra seleccionar todos + total -->
                <div style="display:flex;align-items:center;justify-content:space-between;padding:.7rem 1.2rem;border-bottom:1px solid var(--border);background:#fffbf5;">
                    <label style="display:flex;align-items:center;gap:.5rem;font-size:.83rem;font-weight:700;color:var(--ink2);cursor:pointer;">
                        <input type="checkbox" id="chk-all-<?= $idx ?>" checked onchange="toggleTodos(<?= $idx ?>)" style="width:16px;height:16px;accent-color:var(--c3);">
                        Seleccionar todos (<?= count($cb['pedidos']) ?>)
                    </label>
                    <span style="font-size:.85rem;font-weight:800;color:#dc2626;" id="total-cobro-<?= $idx ?>">
                        $<?= number_format($cb['total_pendiente'], 0, ',', '.') ?>
                    </span>
                </div>

                <!-- Lista de pedidos con checkboxes -->
                <div style="padding:.8rem 1.2rem;display:flex;flex-direction:column;gap:.5rem;max-height:340px;overflow-y:auto;">
                <?php foreach ($cb['pedidos'] as $ped): ?>
                <label style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;border-radius:10px;border:1.5px solid var(--border);cursor:pointer;background:#fff;">
                    <input type="checkbox" name="ids_pedidos[]"
                           value="<?= $ped['id_pedido'] ?>"
                           data-monto="<?= (float)$ped['total_estimado'] ?>"
                           checked
                           onchange="recalcularTotal(<?= $idx ?>)"
                           style="width:17px;height:17px;accent-color:var(--c3);flex-shrink:0;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;color:var(--ink);font-size:.85rem;">
                            Pedido #<?= str_pad($ped['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
                        </div>
                        <div style="font-size:.71rem;color:var(--ink3);">
                            Entrega: <?= formatearFechaEntrega($ped['fecha_entrega']) ?>
                            &nbsp;·&nbsp; Solicitud: <?= date('d/m/Y', strtotime($ped['fecha_solicitud'])) ?>
                        </div>
                    </div>
                    <div style="font-family:'Fraunces',serif;font-weight:800;color:var(--c1);font-size:.95rem;white-space:nowrap;">
                        $<?= number_format($ped['total_estimado'], 0, ',', '.') ?>
                    </div>
                </label>
                <?php endforeach; ?>
                </div>

                <!-- Footer -->
                <div style="display:flex;gap:.6rem;padding:.9rem 1.2rem;border-top:1px solid var(--border);border-radius:0 0 16px 16px;background:var(--clight);">
                    <button type="button" onclick="cerrarModalCobro(<?= $idx ?>)"
                            style="flex:1;padding:.72rem;border-radius:10px;border:1px solid var(--border);background:#fff;color:var(--ink3);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;">
                        Cancelar
                    </button>
                    <button type="submit" id="btn-confirmar-<?= $idx ?>"
                            style="flex:2;padding:.72rem;border-radius:10px;border:none;background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;">
                        <i class="bi bi-check-circle-fill"></i> Confirmar pago recibido
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="filter-grid">
            <div class="filter-group">
                <label>Cliente</label>
                <input type="text" name="cliente" class="filter-input" placeholder="Nombre..." value="<?= htmlspecialchars($f_cliente ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Tipo Cliente</label>
                <select name="tipo" class="filter-input">
                    <option value="">Todos</option>
                    <option value="tienda" <?= $f_tipo==='tienda'?'selected':'' ?>>Tienda</option>
                    <option value="mostrador" <?= $f_tipo==='mostrador'?'selected':'' ?>>Mostrador</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Estado pedido</label>
                <select name="estado" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_estado==='pendiente' ?'selected':'' ?>>Pendiente</option>
                    <option value="confirmado" <?= $f_estado==='confirmado'?'selected':'' ?>>Confirmado</option>
                    <option value="rechazado"  <?= $f_estado==='rechazado' ?'selected':'' ?>>Rechazado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Estado pago</label>
                <select name="pago" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_pago==='pendiente' ?'selected':'' ?>>Sin pagar</option>
                    <option value="aprobado"   <?= $f_pago==='aprobado'  ?'selected':'' ?>>Pagado</option>
                    <option value="rechazado"  <?= $f_pago==='rechazado' ?'selected':'' ?>>Rechazado</option>
                    <option value="expirado"   <?= $f_pago==='expirado'  ?'selected':'' ?>>Expirado</option>
                    <option value="no_aplica"  <?= $f_pago==='no_aplica' ?'selected':'' ?>>N/A</option>
                </select>
            </div>
            <div class="filter-group">
                <label>F. Entrega</label>
                <input type="date" name="entrega" class="filter-input" value="<?= htmlspecialchars($f_entrega ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Solicitado Desde</label>
                <input type="date" name="desde" class="filter-input" value="<?= htmlspecialchars($f_desde ?? '') ?>">
            </div>
            <div class="filter-group">
                <label>Hasta</label>
                <input type="date" name="hasta" class="filter-input" value="<?= htmlspecialchars($f_hasta ?? '') ?>">
            </div>
            <div class="filter-actions">
                <button type="submit" class="btn-filter"><i class="bi bi-filter"></i> Filtrar</button>
                <a href="index.php" class="btn-clear"><i class="bi bi-x-circle"></i> Limpiar</a>
            </div>
        </form>
    </div>

    <?php if ($msg_ok_bulk): ?><div class="msg-bulk-ok"><i class="bi bi-check-circle-fill"></i> <?= $msg_ok_bulk ?></div><?php endif; ?>
    <?php if ($msg_err_bulk): ?><div class="msg-bulk-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= $msg_err_bulk ?></div><?php endif; ?>

    <div id="bulk-bar" class="bulk-bar">
        <span class="bulk-info"><i class="bi bi-check2-square"></i> <span id="bulk-count">0</span> pedido(s) seleccionado(s)</span>
        <select id="select-nuevo-estado" style="padding:.45rem .7rem; border:1px solid #ffe082; border-radius:8px; font-size:.83rem; font-weight:600; font-family:inherit; background:#fff; color:#5d4d10; cursor:pointer;">
            <option value="confirmado">✅ Confirmar</option>
            <option value="pendiente">🕐 Volver a Pendiente</option>
            <option value="rechazado">❌ Rechazar</option>
        </select>
        <button type="button" class="btn-confirmar-lote" onclick="cambiarEstadoLote()">
            <i class="bi bi-arrow-repeat"></i> Aplicar a seleccionados
        </button>
    </div>

    <div class="topbar">
        <div class="mod-titulo"><i class="bi bi-list-ul"></i> <?= $where ? 'Resultados del Filtro' : 'Todos los Pedidos' ?></div>
    </div>

    <div class="card">
        <form id="form-pedidos" method="POST" action="index.php">
            <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
            <input type="hidden" name="accion"        id="input-accion"       value="">
            <input type="hidden" name="nuevo_estado"  id="input-nuevo-estado" value="">
            <div style="padding: 0.8rem 1rem; border-bottom: 1px solid var(--border); background: var(--clight); display:flex; gap: 0.5rem; justify-content: flex-end;">
                <button type="button" onclick="exportar('excel')" style="background:var(--ccard); border:1px solid var(--border); padding:0.4rem 0.8rem; border-radius:8px; font-size:0.8rem; font-weight:bold; cursor:pointer;"><i class="bi bi-file-earmark-excel-fill" style="color:#2e7d32;"></i> Excel</button>
                <button type="button" onclick="exportar('pdf')" style="background:var(--ccard); border:1px solid var(--border); padding:0.4rem 0.8rem; border-radius:8px; font-size:0.8rem; font-weight:bold; cursor:pointer;"><i class="bi bi-file-earmark-pdf-fill" style="color:#c62828;"></i> PDF</button>
            </div>
            <table class="gt">
            <thead>
                <tr>
                    <th style="width:30px;"><input type="checkbox" id="chk-all-ped" onclick="document.querySelectorAll('.chk-ped').forEach(c => c.checked = this.checked)"></th>
                    <th>ID</th>
                    <th>Cliente / Tienda</th>
                    <th>Creado Por</th>
                    <th>Fecha Entrega</th>
                    <th>Solicitado</th>
                    <th>Total Est.</th>
                    <th>Estado</th>
                    <th>Pago</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($pedidos)): ?>
                <tr><td colspan="10" style="text-align:center; padding:2rem; color:var(--ink3);">No hay pedidos registrados</td></tr>
                <?php endif; ?>
                <?php foreach($pedidos as $p): ?>
                <tr>
                    <td data-label="Selección"><input type="checkbox" name="exportar_ids[]" value="<?= $p['id_pedido'] ?>" class="chk-ped"></td>
                    <td data-label="Pedido" style="font-weight:700; color:var(--ink2);">#<?= str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td data-label="Cliente" style="font-weight:600; text-align: right;"><?= htmlspecialchars($p['cliente'] ?? '') ?> <span style="font-size:0.7rem; color:var(--ink3); display:block;"><?= $p['tipo_cliente'] ?></span></td>
                    <td data-label="Creado Por" style="font-size:0.8rem; color:var(--ink2);">
                        <?= htmlspecialchars($p['nombre_creador'] ?? 'Directo') ?>
                        <?php if (isset($p['creador_es_aprendiz']) && (int)$p['creador_es_aprendiz'] === 1): ?>
                            <br>
                            <span class="ep <?= (int)$p['id_cliente'] === (int)$p['id_creador'] ? 'ep-no_aplica' : 'ep-parcial' ?>" style="font-size:0.6rem; padding:0.1rem 0.35rem; margin-top:0.25rem;">
                                <?= (int)$p['id_cliente'] === (int)$p['id_creador'] ? 'Personal' : 'ADSO' ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td data-label="Entrega" style="font-weight:600; color:var(--c1);">
                        <?= formatearFechaEntrega($p['fecha_entrega']) ?>
                    </td>
                    <td data-label="Solicitud" style="font-size:0.75rem; color:var(--ink3);"><?= date('d/m/Y H:i', strtotime($p['fecha_solicitud'])) ?></td>
                    <td data-label="Total" style="font-weight:700;">$<?= number_format($p['total_estimado'], 0, ',', '.') ?></td>
                    <td data-label="Estado"><span class="estado e-<?= $p['estado'] ?>"><?= $p['estado'] ?></span></td>
                    <td data-label="Pago">
                    <?php
                        $ep = $p['estado_pago'] ?? 'pendiente';
                        $ep_labels = [
                            'pendiente' => ['<i class="bi bi-clock-fill"></i>', 'Sin pagar'],
                            'aprobado'  => ['<i class="bi bi-check-circle-fill"></i>', 'Pagado'],
                            'parcial'   => ['<i class="bi bi-info-circle-fill"></i>', 'Pago Parcial'],
                            'rechazado' => ['<i class="bi bi-x-circle-fill"></i>', 'Rechazado'],
                            'expirado'  => ['<i class="bi bi-hourglass-bottom"></i>', 'Expirado'],
                            'no_aplica' => ['<i class="bi bi-dash-circle"></i>', 'N/A'],
                        ];
                        [$ep_ico, $ep_txt] = $ep_labels[$ep] ?? ['', $ep];
                    ?>
                        <span class="ep ep-<?= $ep ?>"><?= $ep_ico ?> <?= $ep_txt ?></span>
                    </td>
                    <td data-label="Acción" style="white-space:nowrap;">
                        <?php if ($p['estado'] === 'pendiente'): ?>
                        <button type="button" title="Confirmar" onclick="quickAction(<?= $p['id_pedido'] ?>, 'confirmado')"
                                style="background:transparent;border:1px solid #a5d6a7;border-radius:6px;padding:.28rem .55rem;cursor:pointer;color:#2e7d32;font-size:.85rem;margin-right:.25rem;transition:all .15s;"
                                onmouseover="this.style.background='#e8f5e9'" onmouseout="this.style.background='transparent'">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button type="button" title="Rechazar" onclick="quickAction(<?= $p['id_pedido'] ?>, 'rechazado')"
                                style="background:transparent;border:1px solid #ef9a9a;border-radius:6px;padding:.28rem .55rem;cursor:pointer;color:#c62828;font-size:.85rem;margin-right:.35rem;transition:all .15s;"
                                onmouseover="this.style.background='#ffebee'" onmouseout="this.style.background='transparent'">
                            <i class="bi bi-x-lg"></i>
                        </button>
                        <?php endif; ?>
                        <a href="ver_pedido.php?id=<?= $p['id_pedido'] ?>" class="btn-ver">Revisar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </form>
    </div>
</div>

<script src="<?= APP_URL ?>/assets/js/pedidos_clientes.js?v=<?= APP_VERSION ?>"></script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
