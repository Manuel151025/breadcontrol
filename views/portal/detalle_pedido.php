<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalle del Pedido — BreadControl</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fuentes.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/bootstrap-icons.css?v=<?= APP_VERSION ?>">
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal_detalle_pedido.css?v=<?= APP_VERSION ?>">
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
                                <?= htmlspecialchars($_GET['error'] ?? '') ?>
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
                                Cuenta ADSO (<?= htmlspecialchars($nombre_tienda ?? '') ?>)
                            <?php endif; ?>
                        </strong>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if ($pedido['mensaje_propietario']): ?>
                    <div class="msg-box">
                        <strong><i class="bi bi-chat-quote-fill"></i> Mensaje de la Panadería</strong>
                        <?= nl2br(htmlspecialchars($pedido['mensaje_propietario'] ?? '')) ?>
                    </div>
                <?php endif; ?>

                <h3 class="section-title"><i class="bi bi-box-seam"></i> Productos Seleccionados</h3>
                <div class="d-list">
                    <?php foreach ($detalles as $d): ?>
                    <div class="d-item">
                        <span class="d-name">
                            <?= htmlspecialchars($d['producto'] ?? '') ?>
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
                                <span>Titular: <strong><?= htmlspecialchars($titular_negocio ?? '') ?></strong></span>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($nequi_link_pago)): ?>
                            <div class="cuenta-fila">
                                <i class="bi bi-phone-fill"></i>
                                <span>Nequi Negocios: <strong style="font-family:monospace; font-size:.82rem; word-break:break-all;"><?= htmlspecialchars($nequi_link_pago ?? '') ?></strong></span>
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
                        <a href="<?= htmlspecialchars($pago_activo['wompi_link_url'] ?? '') ?>" target="_blank" rel="noopener" class="btn-pagar-ahora">
                            <i class="bi bi-shield-lock-fill"></i>
                            Pagar ahora
                            <i class="bi bi-box-arrow-up-right" style="font-size:.9rem; opacity:.85;"></i>
                        </a>
                        <?php endif; ?>

                        <div class="pago-medios">
                            Aceptamos: <strong>Nequi · Bancolombia · PSE · Tarjeta débito/crédito</strong>
                            <?php if (!empty($titular_negocio)): ?>
                                <br>Pagas a: <strong><?= htmlspecialchars($titular_negocio ?? '') ?></strong>
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
                    <div class="reporte-header-sub">Entrega: <?= date('d/m/Y', strtotime($pedido['fecha_entrega'])) ?> &nbsp;·&nbsp; <?= htmlspecialchars($nombre_tienda ?? '') ?></div>
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
                        <?= htmlspecialchars($aprendiz ?? '') ?>
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
                                <td><?= htmlspecialchars($pr['producto'] ?? '') ?></td>
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
                        Total de <?= htmlspecialchars($aprendiz ?? '') ?>: <strong><?= $subtotal_und ?> unidades</strong>
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
