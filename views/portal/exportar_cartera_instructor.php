<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de Cartera — Grupo ADSO</title>
<style>
    @page { margin: 1.5cm 1.8cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #281508; background: #fff; line-height: 1.4; }

    /* Header styling */
    .header-container { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px double #c67124; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .brand-section { display: flex; align-items: center; gap: 0.8rem; }
    .brand-logo { width: 50px; height: 50px; border-radius: 50%; border: 2px solid #945b35; }
    .brand-name { font-size: 16pt; font-weight: 800; color: #945b35; font-family: 'Fraunces', serif; }
    .brand-sub { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.15em; color: #b87a4a; }
    
    .meta-section { text-align: right; font-size: 9pt; color: #6b3d1e; line-height: 1.6; }
    .meta-section strong { color: #945b35; }

    .doc-title { font-size: 14pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #281508; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 0.5rem; }
    
    /* Stats grid */
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .kpi-card { border: 1px solid rgba(148,91,53,0.2); border-radius: 8px; padding: 0.8rem 1rem; background: #fdf6ee; }
    .kpi-val { font-size: 13pt; font-weight: 800; color: #945b35; }
    .kpi-lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.08em; color: #b87a4a; font-weight: 700; margin-top: 0.1rem; }

    /* Table styling */
    table { width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; }
    th { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.08em; color: #6b3d1e; font-weight: 700; padding: 0.6rem 0.8rem; background: #faf3ea; border-bottom: 2px solid #ecc198; text-align: left; }
    td { font-size: 8.5pt; padding: 0.6rem 0.8rem; border-bottom: 1px solid rgba(148,91,53,0.1); vertical-align: middle; }
    tr:last-child td { border-bottom: none; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .apr-name { font-weight: 700; color: #281508; }
    .apr-info { font-size: 7.5pt; color: #b87a4a; margin-top: 0.05rem; }
    
    .badge-deuda { font-size: 8pt; font-weight: 700; color: #d32f2f; background: #ffebee; border: 1px solid #ffcdd2; padding: 0.1rem 0.4rem; border-radius: 4px; display: inline-block; }
    .badge-ok { font-size: 8pt; font-weight: 700; color: #2e7d32; background: #e8f5e9; border: 1px solid #c8e6c9; padding: 0.1rem 0.4rem; border-radius: 4px; display: inline-block; }
    .badge-none { font-size: 8pt; color: #b87a4a; font-weight: 500; }

    /* Footer */
    .footer { margin-top: 2rem; border-top: 1px solid #ecc198; padding-top: 0.8rem; text-align: center; font-size: 8pt; color: #b87a4a; }

    /* Buttons styling - hidden on print */
    .no-print { position: fixed; top: 1rem; right: 1rem; display: flex; gap: 0.5rem; z-index: 999; }
    .btn-print { background: #945b35; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 1rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; transition: background 0.2s; }
    .btn-print:hover { background: #c67124; }
    .btn-close { background: #555; color: #fff; border: none; border-radius: 6px; padding: 0.5rem 0.8rem; font-size: 0.82rem; font-weight: 700; cursor: pointer; }
    .btn-close:hover { background: #333; }

    @media print {
        .no-print { display: none !important; }
        body { color: #000; background: #fff; }
        .kpi-card { background: #fff !important; border: 1px solid #000; }
        th { background: #eee !important; border-bottom: 2px solid #000; color: #000; }
        td { border-bottom: 1px solid #ccc; }
        .badge-deuda { background: transparent !important; border: none; color: #000; font-weight: bold; padding: 0; }
        .badge-ok { background: transparent !important; border: none; color: #000; padding: 0; }
    }
</style>
</head>
<body onload="window.print()">

<div class="no-print">
    <button class="btn-print" onclick="window.print()">🖨 Imprimir / Guardar PDF</button>
    <button class="btn-close" onclick="window.close()">✕ Cerrar</button>
</div>

<div class="header-container">
    <div class="brand-section">
        <img src="../assets/img/logo.png" alt="BreadControl Logo" class="brand-logo">
        <div>
            <div class="brand-name">BreadControl</div>
            <div class="brand-sub">Gestión de Aprendices ADSO</div>
        </div>
    </div>
    <div class="meta-section">
        <strong>Instructor:</strong> <?= htmlspecialchars($nombre_instructor) ?><br>
        <strong>Fecha:</strong> <?= htmlspecialchars($fecha_generado) ?><br>
        <strong>Estado:</strong> Cartera General de Aprendices
    </div>
</div>

<div class="doc-title">Reporte de Cartera por Aprendiz</div>

<div class="kpi-grid">
    <div class="kpi-card">
        <div class="kpi-val">$<?= number_format($resumen_fin['pendiente_total'] ?? 0, 0, ',', '.') ?> COP</div>
        <div class="kpi-lbl">Cartera Pendiente Total</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val"><?= count($aprendices) ?></div>
        <div class="kpi-lbl">Total Aprendices</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val">$<?= number_format($resumen_fin['total_mes'] ?? 0, 0, ',', '.') ?> COP</div>
        <div class="kpi-lbl">Consumido este Mes</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>Aprendiz</th>
            <th>Contacto</th>
            <th class="text-center">Cant. Pedidos</th>
            <th class="text-right">Total Histórico</th>
            <th class="text-right">Consumo Semanal / Cupo</th>
            <th class="text-right">Deuda Pendiente</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($aprendices)): ?>
        <tr>
            <td colspan="6" class="text-center" style="padding: 2rem; color: #6b3d1e;">No hay aprendices registrados.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($aprendices as $a): ?>
        <tr>
            <td>
                <div class="apr-name"><?= htmlspecialchars($a['nombre']) ?></div>
            </td>
            <td>
                <div class="apr-info"><?= htmlspecialchars($a['email'] ? $a['email'] : ($a['telefono'] ? $a['telefono'] : 'Sin contacto')) ?></div>
            </td>
            <td class="text-center" style="font-weight: 600;"><?= (int)$a['total_pedidos'] ?></td>
            <td class="text-right">$<?= number_format($a['total_comprado'], 0, ',', '.') ?></td>
            <td class="text-right">
                $<?= number_format($a['consumido_semana'] ?? 0, 0, ',', '.') ?> / $<?= number_format($a['cupo_semanal'] ?? 20000, 0, ',', '.') ?>
            </td>
            <td class="text-right">
                <?php if ($a['total_pedidos'] == 0): ?>
                    <span class="badge-none">Sin pedidos</span>
                <?php elseif ($a['saldo_pendiente'] > 0): ?>
                    <span class="badge-deuda">$<?= number_format($a['saldo_pendiente'], 0, ',', '.') ?></span>
                <?php else: ?>
                    <span class="badge-ok">Al día</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    BreadControl · Reporte de Cartera de Aprendices ADSO · Generado automáticamente el <?= htmlspecialchars($fecha_generado) ?>
</div>

</body>
</html>
