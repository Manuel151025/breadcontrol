<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de Producción — <?= date('d/m/Y', strtotime($fecha_fil)) ?></title>
<style>
    @page { margin: 1.5cm 1.8cm; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #281508; background: #fff; line-height: 1.4; }

    /* Header styling */
    .header-container { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px double #945b35; padding-bottom: 1rem; margin-bottom: 1.5rem; }
    .brand-section { display: flex; align-items: center; gap: 0.8rem; }
    .brand-logo { width: 50px; height: 50px; border-radius: 50%; border: 2.5px solid #945b35; }
    .brand-name { font-size: 16pt; font-weight: 800; color: #945b35; font-family: 'Fraunces', serif; }
    .brand-sub { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.15em; color: #b87a4a; }
    
    .meta-section { text-align: right; font-size: 9pt; color: #6b3d1e; line-height: 1.6; }
    .meta-section strong { color: #945b35; }

    .doc-title { font-size: 14pt; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #281508; margin-bottom: 1.2rem; }

    /* KPI box */
    .kpi-row { display: flex; gap: 1.5rem; margin-bottom: 1.5rem; }
    .kpi-card { flex: 1; border: 1px solid rgba(148,91,53,0.2); border-radius: 8px; padding: 0.8rem 1.2rem; background: #faf3ea; }
    .kpi-val { font-size: 14pt; font-weight: 800; color: #945b35; }
    .kpi-lbl { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.08em; color: #6b3d1e; font-weight: 700; margin-top: 0.1rem; }

    /* Table styling */
    table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; }
    th { font-size: 7.5pt; text-transform: uppercase; letter-spacing: 0.08em; color: #6b3d1e; font-weight: 700; padding: 0.7rem 0.9rem; background: #faf3ea; border-bottom: 2px solid #ecc198; text-align: left; }
    td { font-size: 8.5pt; padding: 0.7rem 0.9rem; border-bottom: 1px solid rgba(148,91,53,0.1); vertical-align: middle; }
    
    .text-right { text-align: right; }
    .text-center { text-align: center; }
    
    .tanda-badge { font-weight: 800; font-size: 9.5pt; color: #c67124; background: rgba(198,113,36,0.08); padding: 0.15rem 0.55rem; border-radius: 4px; border: 1px solid rgba(198,113,36,0.15); display: inline-block; }

    /* Footer */
    .footer { margin-top: 2rem; border-top: 1px solid #ecc198; padding-top: 0.8rem; text-align: center; font-size: 8pt; color: #b87a4a; }

    /* Controls - hidden on print */
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
        .tanda-badge { background: transparent !important; border: none; color: #000; padding: 0; }
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
        <img src="../../assets/img/logo.png" alt="BreadControl Logo" class="brand-logo">
        <div>
            <div class="brand-name">BreadControl</div>
            <div class="brand-sub">Panel de Administración</div>
        </div>
    </div>
    <div class="meta-section">
        <strong>Reporte:</strong> Producción Diaria<br>
        <strong>Fecha Producción:</strong> <?= date('d/m/Y', strtotime($fecha_fil)) ?><br>
        <strong>Generado:</strong> <?= htmlspecialchars($fecha_generado) ?>
    </div>
</div>

<div class="doc-title">Reporte de Producción de Panes</div>

<div class="kpi-row">
    <div class="kpi-card">
        <div class="kpi-val"><?= (int)$total_tandas ?></div>
        <div class="kpi-lbl">Total Tandas Producidas</div>
    </div>
    <div class="kpi-card">
        <div class="kpi-val"><?= count($producciones) ?></div>
        <div class="kpi-lbl">Registros / Tipos de Pan</div>
    </div>
</div>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Producto / Variedad</th>
            <th class="text-center">Tandas</th>
            <th>Operario</th>
            <th>Hora Registro</th>
            <th>Observaciones / Notas</th>
        </tr>
    </thead>
    <tbody>
    <?php if (empty($producciones)): ?>
        <tr>
            <td colspan="6" class="text-center" style="padding: 2rem; color: #6b3d1e;">No hay registros de producción para esta fecha.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($producciones as $pr): ?>
        <tr>
            <td><span style="font-weight: 700; color: #6b3d1e;">#<?= $pr['id_produccion'] ?></span></td>
            <td>
                <strong><?= htmlspecialchars($pr['producto']) ?></strong><br>
                <span style="font-size: 7.5pt; color: #b87a4a;"><?= htmlspecialchars($pr['unidad_produccion']) ?></span>
            </td>
            <td class="text-center"><span class="tanda-badge"><?= (int)$pr['cantidad_tandas'] ?></span></td>
            <td><?= htmlspecialchars($pr['operario'] ?? '—') ?></td>
            <td><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></td>
            <td style="font-size: 8pt; color: #6b3d1e;">
                <?= $pr['observaciones'] ? htmlspecialchars($pr['observaciones']) : '—' ?>
            </td>
        </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<div class="footer">
    BreadControl · Reporte Oficial de Producción Diaria · Generado automáticamente el <?= htmlspecialchars($fecha_generado) ?>
</div>

</body>
</html>
