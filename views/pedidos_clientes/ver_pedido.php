<?php require_once __DIR__ . '/../../helpers/PedidoHelper.php'; ?>
<link href="<?= APP_URL ?>/assets/css/pedidos.css" rel="stylesheet">

<div class="page">
    <div class="topbar">
        <div class="mod-titulo"><i class="bi bi-file-earmark-text"></i> Revisar Pedido #<?= str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?></div>
        <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver a pedidos</a>
    </div>

    <?php if ($msg_ok): ?><div class="msg-ok"><?= $msg_ok ?></div><?php endif; ?>
    <?php if ($msg_err): ?><div class="msg-err"><?= $msg_err ?></div><?php endif; ?>

    <?php if ($estado_pago === 'parcial' && $pago_activo): ?>
    <?php
        $total_esperado_p = PedidoHelper::calcularTotalEsperado($pedido, $pedidos_consolidados);
        $deuda = PedidoHelper::calcularDeudaRestante($total_esperado_p, $total_pagado);
    ?>
    <div style="background:#fff5f5; border:1px solid #fee2e2; border-left:4px solid #ef4444; border-radius:12px; padding:1rem 1.2rem; margin-bottom:1.5rem; display:flex; align-items:flex-start; gap:.75rem; box-shadow: 0 1px 5px rgba(239,68,68,.08);">
        <i class="bi bi-exclamation-octagon-fill" style="color:#ef4444; font-size:1.25rem; flex-shrink:0; margin-top:.1rem;"></i>
        <div>
            <div style="font-weight:800; color:#991b1b; font-size:.9rem; margin-bottom:.2rem;">⚠️ ADVERTENCIA: PAGO PARCIAL PENDIENTE</div>
            <div style="font-size:.84rem; color:#7f1d1d; line-height:1.5;">
                Este pedido cuenta con un pago parcial registrado de <strong>$<?= number_format($total_pagado, 0, ',', '.') ?> COP</strong>. 
                El cliente aún debe <strong style="text-decoration: underline; font-size:.9rem;">$<?= number_format($deuda, 0, ',', '.') ?> COP</strong>.
                Por favor, cobra el saldo pendiente de <strong>$<?= number_format($deuda, 0, ',', '.') ?> COP</strong> antes de realizar la entrega del producto.
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid-layout">
        <div class="card">
            <h3>Datos del Pedido</h3>
            <span class="estado-badge e-<?= $pedido['estado'] ?>">ESTADO: <?= $pedido['estado'] ?></span>

            <div class="info-p"><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente']) ?> (<?= $pedido['tipo_cliente'] ?>)</div>
            <div class="info-p"><strong>Digitado por:</strong> <?= htmlspecialchars($pedido['nombre_creador'] ?? 'Mismo cliente') ?></div>
            <div class="info-p"><strong>Teléfono:</strong> <?= htmlspecialchars($pedido['telefono'] ?: 'No registrado') ?></div>
            <div class="info-p"><strong>Fecha Entrega:</strong> <span style="color:var(--c1); font-weight:700;">
                <?= formatearFechaEntrega($pedido['fecha_entrega']) ?>
            </span></div>
            <div class="info-p"><strong>Solicitado el:</strong> <?= date('d/m/Y H:i', strtotime($pedido['fecha_solicitud'])) ?></div>

            <h4 style="margin-top:1.5rem; color:var(--ink2);">Productos Solicitados</h4>
            <div class="det-list">
                <?php foreach($detalles as $d): ?>
                <div class="det-item">
                    <span class="name">
                        <?= htmlspecialchars($d['producto']) ?>
                        <?php if ($d['napa'] > 0): ?>
                            <span style="font-size:.7rem;color:#c67124;font-weight:700;margin-left:5px;">(🎁 Ñapa)</span>
                        <?php elseif ($d['bonificacion'] > 0): ?>
                            <span style="font-size:.7rem;color:#1565c0;font-weight:700;margin-left:5px;">(🏪 Bonificación)</span>
                        <?php endif; ?>
                    </span>
                    <span class="cant"><?= $d['cantidad'] > 0 ? $d['cantidad'] : ($d['napa'] > 0 ? $d['napa'] : $d['bonificacion']) ?> und</span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="total-box">
                <span class="total-lbl">Total Estimado</span>
                <span class="total-val">$<?= number_format($pedido['total_estimado'], 0, ',', '.') ?></span>
            </div>
        </div>

        <div class="card">
            <h3><i class="bi bi-pencil-square"></i> Gestión</h3>
            <form method="post">
                <div class="form-group">
                    <label>Estado del pedido</label>
                    <select name="estado">
                        <option value="pendiente" <?= $pedido['estado'] == 'pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="confirmado" <?= $pedido['estado'] == 'confirmado' ? 'selected' : '' ?>>Confirmado</option>
                        <option value="rechazado" <?= $pedido['estado'] == 'rechazado' ? 'selected' : '' ?>>Rechazado</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Mensaje para el cliente (Opcional)</label>
                    <textarea name="mensaje_propietario" rows="4" placeholder="Ej: Pedido confirmado, pasas el viernes."><?= htmlspecialchars($pedido['mensaje_propietario'] ?? '') ?></textarea>
                </div>
                <button type="submit" name="actualizar" class="btn-save"><i class="bi bi-save"></i> Guardar Cambios</button>
            </form>
        </div>
    </div>

    <!-- ====== TARJETA: PAGO DIGITAL ====== -->
    <?php if ($pedido['estado'] === 'rechazado' && $estado_pago === 'aprobado'): ?>
    <div style="background:#fff0f0;border:1px solid #fca5a5;border-left:4px solid #dc2626;border-radius:12px;padding:1rem 1.2rem;margin-bottom:1rem;display:flex;align-items:flex-start;gap:.75rem;">
        <i class="bi bi-exclamation-triangle-fill" style="color:#dc2626;font-size:1.15rem;flex-shrink:0;margin-top:.1rem;"></i>
        <div>
            <div style="font-weight:700;color:#991b1b;font-size:.9rem;margin-bottom:.2rem;">Pedido rechazado con pago registrado</div>
            <div style="font-size:.82rem;color:#7f1d1d;line-height:1.5;">
                Este pedido está <strong>rechazado</strong> pero figura con un pago <strong>aprobado</strong>.
                Si el rechazo fue un error, cambia el estado en la tarjeta de Gestión.
                Si el pago fue incorrecto, usa la opción <em>"Revertir este pago"</em> más abajo.
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="card pago-card">
        <h3>
            <span><i class="bi bi-credit-card-2-front"></i> Pago Digital</span>
            <?php
            $badge_clase = 'badge-pago-sinpago';
            $badge_texto = 'Sin habilitar';
            if ($estado_pago === 'pendiente') { $badge_clase = 'badge-pago-pendiente'; $badge_texto = 'Esperando pago'; }
            elseif ($estado_pago === 'aprobado') { $badge_clase = 'badge-pago-aprobado'; $badge_texto = 'Pagado'; }
            elseif ($estado_pago === 'parcial') { $badge_clase = 'badge-pago-parcial'; $badge_texto = 'Pago Parcial'; }
            ?>
            <span class="badge-estado <?= $badge_clase ?>"><?= $badge_texto ?></span>
        </h3>

        <?php if ($es_consolidado): ?>
            <div style="background:#e8f4fd; border:1px solid #b3e5fc; border-left:4px solid #0288d1; border-radius:10px; padding:.85rem 1rem; font-size:.82rem; color:#01579b; margin-bottom:1rem; line-height:1.5;">
                <i class="bi bi-info-circle-fill" style="margin-right:.3rem; font-size: 1rem; vertical-align: middle;"></i>
                <strong>Pago Consolidado Agrupado:</strong> Este pedido está agrupado con otros 
                <strong><?= count($pedidos_consolidados) - 1 ?></strong> pedido(s) de este cliente bajo una única transacción de pago.
                <br>
                <span style="font-size:.78rem; opacity:.95; display:inline-block; margin-top:.35rem;">
                    Pedidos incluidos: 
                    <?= implode(', ', array_map(fn($p) => '#' . str_pad($p['id_pedido'], 4, '0', STR_PAD_LEFT), $pedidos_consolidados)) ?>
                    &nbsp;·&nbsp; Monto total consolidado: <strong>$<?= number_format($pago_activo['monto'] ?? 0, 0, ',', '.') ?> COP</strong>
                </span>
            </div>
        <?php endif; ?>

        <?php if (!$pago_configurado): ?>
            <div class="aviso-config">
                <i class="bi bi-info-circle-fill"></i>
                Aún no has configurado tu link de Nequi Negocios.
                <a href="<?= APP_URL ?>/modules/configuracion/pagos.php">Ir a Configuración → Pagos</a> para configurarlo.
            </div>

        <?php elseif ($estado_pago === 'no_aplica' && $pedido['estado'] !== 'confirmado'): ?>
            <div class="aviso-no-confirmado">
                <i class="bi bi-info-circle-fill"></i>
                Primero confirma este pedido (en la tarjeta Gestión de arriba) para habilitar el pago digital.
            </div>

        <?php elseif ($estado_pago === 'no_aplica' && $pedido['estado'] === 'confirmado'): ?>
            <p style="font-size:.9rem;color:var(--ink2);margin-bottom:1rem;">
                Al habilitar el pago digital, el cliente verá en su portal un botón <strong>"Pagar ahora"</strong> que lo llevará a tu link de Nequi Negocios para pagar <strong>$<?= number_format($pedido['total_estimado'], 0, ',', '.') ?></strong>.
            </p>
            <form method="post">
                <button type="submit" name="habilitar_pago" class="btn-habilitar">
                    <i class="bi bi-cash-coin"></i> Habilitar pago digital
                </button>
            </form>

        <?php elseif (in_array($estado_pago, ['pendiente', 'parcial']) && $pago_activo): ?>
            <?php
            $total_esperado_p = PedidoHelper::calcularTotalEsperado($pedido, $pedidos_consolidados);
            $deuda_restante = PedidoHelper::calcularDeudaRestante($total_esperado_p, $total_pagado);
            ?>
            <p style="font-size:.9rem;color:var(--ink2);margin-bottom:1rem;">
                El cliente ya puede pagar desde su portal. Registra los abonos recibidos en tu cuenta aquí.
            </p>

            <div class="pago-data-grid" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 1.2rem;">
                <div>
                    <div class="lbl">Total Esperado</div>
                    <div class="val" style="font-weight:700;">$<?= number_format($total_esperado_p, 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Total Abonado</div>
                    <div class="val" style="font-weight:700; color:var(--pago-green-dk);">$<?= number_format($total_pagado, 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Saldo Restante</div>
                    <div class="val-monto" style="color: #b91c1c; font-size: 1.4rem;">$<?= number_format($deuda_restante, 0, ',', '.') ?></div>
                </div>
            </div>

            <?php if (!empty($abonos)): ?>
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1rem;margin-bottom:.6rem;margin-top:1rem;">
                    Historial de Abonos Recibidos
                </h4>
                <div style="overflow-x:auto; margin-bottom: 1.2rem; border: 1px solid var(--border); border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                        <thead>
                            <tr style="background: var(--clight); border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Fecha</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Medio</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3); text-align: right;">Monto</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonos as $ab): ?>
                                <tr style="border-bottom: 1px solid rgba(148,91,53,.05);">
                                    <td style="padding: 0.5rem 0.75rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                    <td style="padding: 0.5rem 0.75rem;"><span style="font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></span></td>
                                    <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: var(--pago-green-dk);">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                    <td style="padding: 0.5rem 0.75rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <hr style="border:none;border-top:1px solid var(--border);margin:1.2rem 0;">

            <form method="post">
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1.05rem;margin-bottom:.8rem;">
                    Confirmar abono recibido
                </h4>

                <div class="form-group">
                    <label>Monto del abono</label>
                    <input type="number" id="inp-monto-recibido" name="monto_recibido" step="1" min="1"
                           value="<?= (int) $deuda_restante ?>"
                           data-esperado="<?= (int) $deuda_restante ?>"
                           oninput="onMontoChange()" required>
                    <small style="font-size:.72rem;color:var(--ink3);display:block;margin-top:.3rem;">
                        Verifica el monto de la transferencia en tu app Nequi/Bancolombia.
                    </small>
                    <div class="aviso-diferencia" id="aviso-diferencia">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        El monto difiere del saldo restante (<strong>$<?= number_format($deuda_restante, 0, ',', '.') ?></strong>).
                        Agrega una nota explicando la diferencia.
                    </div>
                </div>

                <div class="form-group">
                    <label>¿Cómo recibiste el pago?</label>
                    <select id="inp-metodo" name="metodo_pago">
                        <option value="NEQUI">Nequi</option>
                        <option value="BANCOLOMBIA">Bancolombia</option>
                        <option value="PSE">PSE (otro banco)</option>
                        <option value="TARJETA">Tarjeta débito/crédito</option>
                        <option value="OTRO">Otro</option>
                    </select>
                </div>

                <div class="form-group" id="grupo-nota">
                    <label id="lbl-nota">Nota</label>
                    <textarea id="inp-nota" name="nota_pago" rows="2" placeholder="Ej: ID de transacción Nequi M12345"></textarea>
                </div>

                <button type="submit" name="marcar_pagado" class="btn-pagado"
                        onclick="return validarFormPago() && confirm('¿Confirmas que ya recibiste este abono?')">
                    <i class="bi bi-check-circle-fill"></i> Confirmar abono recibido
                </button>
            </form>

            <form method="post" style="margin-top:1.2rem;">
                <p style="font-size:.77rem;color:#9ca3af;margin-bottom:.5rem;line-height:1.5;">
                    <i class="bi bi-info-circle"></i> Deshabilitar retira el botón de pago del portal del cliente. El pedido queda sin pago hasta que lo vuelvas a habilitar.
                </p>
                <button type="submit" name="deshabilitar_pago" class="btn-danger" onclick="return confirm('Esto retira el link de pago del portal del cliente. ¿Continuar?')">
                    <i class="bi bi-x-circle"></i> Deshabilitar pago digital
                </button>
            </form>

        <?php elseif ($estado_pago === 'aprobado' && $pago_activo): ?>
            <p style="font-size:.95rem;color:#1b5e20;margin-bottom:1rem;font-weight:600;">
                <i class="bi bi-check-circle-fill" style="color:#2e7d32;"></i>
                Pago recibido y verificado.
            </p>

            <div class="pago-data-grid">
                <div>
                    <div class="lbl">Monto pagado</div>
                    <div class="val">$<?= number_format($pago_activo['monto'], 0, ',', '.') ?></div>
                </div>
                <div>
                    <div class="lbl">Método del último abono</div>
                    <div class="val"><?= htmlspecialchars($metodos_legibles[$pago_activo['metodo_pago']] ?? $pago_activo['metodo_pago'] ?? '—') ?></div>
                </div>
                <div>
                    <div class="lbl">Fecha de aprobación</div>
                    <div class="val"><?= $pago_activo['fecha_pago'] ? date('d/m/Y H:i', strtotime($pago_activo['fecha_pago'])) : '—' ?></div>
                </div>
                <div>
                    <div class="lbl">Referencia</div>
                    <div class="val" style="font-family:monospace;font-size:.78rem;"><?= htmlspecialchars($pago_activo['referencia']) ?></div>
                </div>
            </div>

            <?php if (!empty($abonos)): ?>
                <h4 style="font-family:'Fraunces',serif;color:var(--c1);font-size:1rem;margin-bottom:.6rem;margin-top:1.2rem;">
                    Historial de Abonos Recibidos
                </h4>
                <div style="overflow-x:auto; margin-bottom: 1.2rem; border: 1px solid var(--border); border-radius: 8px;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8rem; text-align: left;">
                        <thead>
                            <tr style="background: var(--clight); border-bottom: 1px solid var(--border);">
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Fecha</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Medio</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3); text-align: right;">Monto</th>
                                <th style="padding: 0.5rem 0.75rem; font-weight: 700; color: var(--ink3);">Nota</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($abonos as $ab): ?>
                                <tr style="border-bottom: 1px solid rgba(148,91,53,.05);">
                                    <td style="padding: 0.5rem 0.75rem; white-space: nowrap;"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                                    <td style="padding: 0.5rem 0.75rem;"><span style="font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></span></td>
                                    <td style="padding: 0.5rem 0.75rem; text-align: right; font-weight: 700; color: var(--pago-green-dk);">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                                    <td style="padding: 0.5rem 0.75rem; color: var(--ink2);"><?= htmlspecialchars($ab['nota'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

            <?php if (!empty($pago_activo['nota'])): ?>
                <div class="form-group" style="margin-top: 1rem;">
                    <label>Nota del último registro</label>
                    <div style="background:var(--clight);border:1px solid var(--border);border-radius:8px;padding:.8rem;font-size:.85rem;"><?= nl2br(htmlspecialchars($pago_activo['nota'])) ?></div>
                </div>
            <?php endif; ?>

            <details class="revertir">
                <summary>Revertir este pago (solo si fue un error)</summary>
                <form method="post" style="margin-top:.6rem;">
                    <p style="font-size:.8rem;color:var(--ink2);margin-bottom:.6rem;">
                        Esto desmarca el pago como recibido. El pedido vuelve al estado "sin pago".
                    </p>
                    <button type="submit" name="revertir_pago" class="btn-secundario" onclick="return confirm('¿Seguro que quieres revertir el pago?')">
                        <i class="bi bi-arrow-counterclockwise"></i> Revertir pago
                    </button>
                </form>
            </details>
        <?php endif; ?>
    </div>
</div>
<script src="<?= APP_URL ?>/assets/js/pedidos.js" defer></script>
<?php include __DIR__ . '/../layouts/footer.php'; ?>
