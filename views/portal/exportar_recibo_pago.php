<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Comprobante de Pago — Pedido #<?= str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?></title>
<style>
    @page { margin: 1.5cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 9.5pt; color: #281508; background: #fff; line-height: 1.4; }

    .receipt-container { max-width: 700px; margin: 0 auto; padding: 1.5rem; border: 1px dashed #c8956e; border-radius: 10px; background: #fff; }

    /* Header */
    .receipt-header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px dashed #ecc198; padding-bottom: 1rem; margin-bottom: 1.2rem; }
    .brand-logo { width: 45px; height: 45px; border-radius: 50%; border: 1.5px solid #945b35; }
    .brand-name { font-size: 14pt; font-weight: 800; color: #945b35; font-family: 'Fraunces', serif; }
    .brand-sub { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.1em; color: #b87a4a; }
    
    .receipt-title { font-size: 12pt; font-weight: 800; color: #281508; text-transform: uppercase; text-align: right; }
    .receipt-number { font-size: 10.5pt; font-weight: 700; color: #c67124; margin-top: 0.2rem; }

    /* Metadata details */
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.2rem; background: #fdf6ee; padding: 0.9rem; border-radius: 8px; border: 1px solid rgba(148,91,53,0.1); }
    .detail-item { font-size: 8.5pt; line-height: 1.5; }
    .detail-item span { text-transform: uppercase; font-size: 7pt; font-weight: 700; color: #b87a4a; display: block; margin-bottom: 0.15rem; }
    .detail-item strong { color: #281508; }

    /* Tables */
    .section-title { font-size: 9pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #945b35; border-bottom: 1px solid #ecc198; padding-bottom: 0.35rem; margin-bottom: 0.75rem; margin-top: 1rem; }
    
    table { width: 100%; border-collapse: collapse; margin-bottom: 1rem; }
    th { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.05em; color: #6b3d1e; font-weight: 700; padding: 0.5rem 0.65rem; background: #faf3ea; border-bottom: 1px solid #ecc198; text-align: left; }
    th:not(:first-child) { text-align: center; }
    td { font-size: 8.5pt; padding: 0.5rem 0.65rem; border-bottom: 1px solid rgba(148,91,53,0.06); }
    td:not(:first-child) { text-align: center; }
    
    .text-left { text-align: left !important; }
    .text-right { text-align: right !important; }
    .text-center { text-align: center !important; }

    .sub-badge { font-size: 7pt; padding: 1px 4px; border-radius: 3px; font-weight: bold; }
    .sb-napa { background: #fff3e0; color: #e65100; border: 1px solid #ffe0b2; }
    .sb-bonif { background: #e3f2fd; color: #1565c0; border: 1px solid #bbdefb; }

    /* Totals section */
    .totals-box { display: flex; justify-content: flex-end; align-items: center; border-top: 1.5px dashed #ecc198; padding-top: 0.6rem; margin-top: 0.6rem; }
    .totals-lbl { font-size: 9pt; font-weight: 700; color: #6b3d1e; text-transform: uppercase; margin-right: 1.5rem; }
    .totals-val { font-size: 13pt; font-weight: 800; color: #2e7d32; font-family: 'Fraunces', serif; }

    /* Signatures */
    .sign-section { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-top: 2rem; padding-top: 1.5rem; }
    .sign-line { border-top: 1px solid #b87a4a; text-align: center; font-size: 7.5pt; color: #6b3d1e; padding-top: 0.4rem; }

    /* Printing controls */
    .no-print { position: fixed; top: 1rem; right: 1rem; display: flex; gap: 0.5rem; z-index: 999; }
    .btn-print { background: #945b35; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
    .btn-print:hover { background: #c67124; }
    .btn-close { background: #555; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 0.8rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; }
    .btn-close:hover { background: #333; }

    @media print {
        .no-print { display: none !important; }
        body { color: #000; background: #fff; }
        .receipt-container { border: 1px solid #000; border-radius: 0; padding: 0; }
        .details-grid { background: #fff !important; border: 1px solid #000; }
        th { background: #eee !important; border-bottom: 1px solid #000; color: #000; }
        .totals-val { color: #000; }
    }
</style>
</head>
<body onload="window.print()">

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨 Imprimir / PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Cerrar</button>
</div>

<div class="receipt-container">
    <div class="receipt-header">
        <div style="display:flex; align-items:center; gap: 0.6rem;">
            <img src="../assets/img/logo.png" alt="Logo" class="brand-logo">
            <div>
                <div class="brand-name">BreadControl</div>
                <div class="brand-sub">Comprobante de Pago Digital</div>
            </div>
        </div>
        <div>
            <div class="receipt-title">Comprobante de Pago</div>
            <div class="receipt-number">N° PC-<?= str_pad($pedido['id_pedido'], 5, '0', STR_PAD_LEFT) ?></div>
        </div>
    </div>

    <div class="details-grid">
        <div class="detail-item">
            <span>Cliente / Pagador</span>
            <strong><?= htmlspecialchars($_SESSION['cliente_nombre']) ?></strong>
        </div>
        <div class="detail-item">
            <span>Fecha de Emisión</span>
            <strong><?= htmlspecialchars($fecha_generado) ?></strong>
        </div>
        <div class="detail-item">
            <span>Referencia del Pago</span>
            <strong><?= htmlspecialchars($pago_activo['referencia'] ?? 'Individual') ?></strong>
        </div>
        <div class="detail-item">
            <span>ID de Transacción</span>
            <strong><?= htmlspecialchars($pago_activo['wompi_transaction_id'] ?? 'Abono Manual') ?></strong>
        </div>
        <div class="detail-item">
            <span>Canal / Método</span>
            <strong><?= htmlspecialchars($pago_activo['wompi_payment_method'] ?? 'Nequi / Digital') ?></strong>
        </div>
        <div class="detail-item">
            <span>Estado de Pago</span>
            <strong style="color: #2e7d32; text-transform: uppercase;"><?= htmlspecialchars($pedido['estado_pago']) ?></strong>
        </div>
    </div>

    <!-- Si el pago incluye múltiples pedidos (pago consolidado) -->
    <?php if (count($pedidos_consolidados) > 1): ?>
        <div class="section-title">Pedidos consolidados incluidos en este pago</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left">Pedido ID</th>
                    <th class="text-left">Aprendiz / Solicitante</th>
                    <th>Entrega</th>
                    <th class="text-right">Monto Estimado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pedidos_consolidados as $pc): ?>
                <tr>
                    <td class="text-left" style="font-weight:700; color: #945b35;">#<?= str_pad($pc['id_pedido'], 4, '0', STR_PAD_LEFT) ?></td>
                    <td class="text-left"><?= htmlspecialchars($pc['nombre_aprendiz'] ?? '—') ?></td>
                    <td>
                        <?= formatearFechaEntrega($pc['fecha_entrega']) ?>
                    </td>
                    <td class="text-right">$<?= number_format($pc['total_estimado'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <!-- Si es un único pedido, listamos los productos del mismo -->
        <div class="section-title">Detalle de Productos (Pedido #<?= str_pad($pedido['id_pedido'], 4, '0', STR_PAD_LEFT) ?>)</div>
        <table>
            <thead>
                <tr>
                    <th class="text-left">Producto</th>
                    <th>Cant. Solicitada</th>
                    <th>Ñapa / Bonif.</th>
                    <th class="text-right">Total Unidades</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                <?php $total_und = $d['cantidad'] + $d['napa'] + $d['bonificacion']; ?>
                <tr>
                    <td class="text-left" style="font-weight: 600;"><?= htmlspecialchars($d['producto']) ?></td>
                    <td><?= (int)$d['cantidad'] ?> und</td>
                    <td>
                        <?php if ($d['napa'] > 0): ?>
                            <span class="sub-badge sb-napa">+<?= (int)$d['napa'] ?> ñapa</span>
                        <?php elseif ($d['bonificacion'] > 0): ?>
                            <span class="sub-badge sb-bonif">+<?= (int)$d['bonificacion'] ?> bonif.</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-right" style="font-weight: 700;"><?= $total_und ?> und</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <?php if (!empty($abonos)): ?>
        <div class="section-title">Historial de Transacciones / Abonos</div>
        <table style="font-size: 8pt; margin-bottom: 0.5rem;">
            <thead>
                <tr>
                    <th class="text-left">Fecha del Abono</th>
                    <th class="text-left">Método de Pago</th>
                    <th class="text-left">Observaciones / Nota</th>
                    <th class="text-right">Monto</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($abonos as $ab): ?>
                <tr>
                    <td class="text-left"><?= date('d/m/Y H:i', strtotime($ab['fecha_abono'])) ?></td>
                    <td class="text-left" style="font-weight: 600;"><?= htmlspecialchars($metodos_legibles[$ab['metodo_pago']] ?? $ab['metodo_pago']) ?></td>
                    <td class="text-left"><?= htmlspecialchars($ab['nota'] ?? 'Confirmación de pago exitosa') ?></td>
                    <td class="text-right" style="font-weight: 700; color: #2e7d32;">$<?= number_format($ab['monto'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="totals-box">
        <span class="totals-lbl">Total Abonado</span>
        <span class="totals-val">$<?= number_format($total_pagado ?: $pedido['total_estimado'], 0, ',', '.') ?> COP</span>
    </div>

    <div class="sign-section">
        <div class="sign-line">Firma del Encargado / Panadería</div>
        <div class="sign-line">Firma del Cliente / Pagador</div>
    </div>
</div>

</body>
</html>
