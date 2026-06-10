<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';

requerirPropietario();
$pdo  = getConexion();
$user = usuarioActual();

// ══════════════════════════════════════════════════════════════
//  ENDPOINT AJAX — devuelve ingredientes + lotes FIFO en JSON
//  Llamado: ?ajax_lotes=1&id_producto=X&unidades=Y
// ══════════════════════════════════════════════════════════════
if (isset($_GET['ajax_lotes'])) {
    header('Content-Type: application/json');
    $id_prod  = (int)($_GET['id_producto'] ?? 0);
    $unidades = max(1, (int)($_GET['unidades'] ?? 1));

    if (!$id_prod) { echo json_encode(['error' => 'Sin producto']); exit; }

    $r = $pdo->prepare("SELECT id_receta FROM receta WHERE id_producto=? AND es_vigente=1 LIMIT 1");
    $r->execute([$id_prod]);
    $id_receta = $r->fetchColumn();
    if (!$id_receta) { echo json_encode(['error' => 'sin_receta']); exit; }

    $stmt = $pdo->prepare("
        SELECT ri.id_insumo, ri.cantidad AS cant_por_unidad, ri.aplica_merma,
               i.nombre, i.unidad_medida, i.stock_actual
        FROM receta_ingrediente ri
        INNER JOIN insumo i ON i.id_insumo = ri.id_insumo
        WHERE ri.id_receta = ?
        ORDER BY i.nombre
    ");
    $stmt->execute([$id_receta]);
    $ingredientes = $stmt->fetchAll();

    $resultado    = [];
    $hay_faltante = false;

    // cantidad receta = por tanda → multiplicar por tandas (no por unidades individuales)
    foreach ($ingredientes as $ing) {
        $cant_necesaria = $ing['cant_por_unidad'] * $unidades; // $unidades aquí = tandas (enviado desde JS)

        $stmt2 = $pdo->prepare("
            SELECT id_lote, numero_lote, fecha_ingreso, cantidad_disponible, precio_unitario
            FROM lote
            WHERE id_insumo = ? AND estado = 'activo' AND cantidad_disponible > 0
            ORDER BY fecha_ingreso ASC
        ");
        $stmt2->execute([$ing['id_insumo']]);
        $lotes = $stmt2->fetchAll();

        $total_lotes = array_sum(array_column($lotes, 'cantidad_disponible'));
        $stock_actual = (float)$ing['stock_actual'];

        // ── REGLA CENTRAL ──────────────────────────────────────────────────────
        // stock_actual ES la verdad de lo que hay físicamente en bodega.
        // Puede ser mayor que total_lotes si el stock se editó manualmente desde
        // Inventario (sin pasar por Compras). Siempre usamos stock_actual.
        $total_disponible = $stock_actual;
        $alcanza = $total_disponible >= $cant_necesaria;
        if (!$alcanza) $hay_faltante = true;

        // Detectar si hay stock sin lote (editado manualmente)
        $stock_sin_lote = max(0, $stock_actual - $total_lotes); // parte del stock sin trazabilidad
        $hay_stock_manual = $stock_sin_lote > 0;

        $lotes_a_usar = [];

        // Primero consumir de lotes FIFO
        $restante = min($cant_necesaria, $stock_actual); // no pedir más de lo que hay
        foreach ($lotes as $lote) {
            if ($restante <= 0) break;
            $consumir = min((float)$lote['cantidad_disponible'], $restante);
            $lotes_a_usar[] = [
                'id_lote'         => $lote['id_lote'],
                'numero_lote'     => $lote['numero_lote'],
                'fecha_ingreso'   => date('d/m/Y', strtotime($lote['fecha_ingreso'])),
                'disponible'      => (float)$lote['cantidad_disponible'],
                'a_consumir'      => round($consumir, 4),
                'precio_unitario' => (float)$lote['precio_unitario'],
                'es_mas_antiguo'  => count($lotes_a_usar) === 0,
                'sin_lote'        => false,
            ];
            $restante -= $consumir;
        }

        // Si queda restante, es stock manual (sin lote)
        if ($restante > 0 && $hay_stock_manual) {
            $lotes_a_usar[] = [
                'id_lote'         => null,
                'numero_lote'     => 'MANUAL',
                'fecha_ingreso'   => 'Editado en Inventario',
                'disponible'      => $stock_sin_lote,
                'a_consumir'      => round($restante, 4),
                'precio_unitario' => 0,
                'es_mas_antiguo'  => count($lotes_a_usar) === 0,
                'sin_lote'        => true,
            ];
        } elseif (empty($lotes) && $stock_actual > 0) {
            // Sin lotes en absoluto — todo el stock es manual
            $lotes_a_usar[] = [
                'id_lote'         => null,
                'numero_lote'     => 'MANUAL',
                'fecha_ingreso'   => 'Editado en Inventario',
                'disponible'      => $stock_actual,
                'a_consumir'      => round(min($cant_necesaria, $stock_actual), 4),
                'precio_unitario' => 0,
                'es_mas_antiguo'  => true,
                'sin_lote'        => true,
            ];
        }

        $resultado[] = [
            'id_insumo'        => $ing['id_insumo'],
            'nombre'           => $ing['nombre'],
            'unidad_medida'    => $ing['unidad_medida'],
            'cant_necesaria'   => round($cant_necesaria, 4),
            'total_disponible' => round($total_disponible, 4),
            'alcanza'          => $alcanza,
            'aplica_merma'     => (bool)$ing['aplica_merma'],
            'lotes_a_usar'     => $lotes_a_usar,
            'hay_stock_manual' => $hay_stock_manual || empty($lotes),
        ];
    }

    echo json_encode([
        'ok'           => true,
        'hay_faltante' => $hay_faltante,
        'ingredientes' => $resultado,
        'id_receta'    => $id_receta,
    ]);
    exit;
}

// ══════════════════════════════════════════════════════════════
//  POST — Registrar produccion + consumo FIFO de lotes
// ══════════════════════════════════════════════════════════════
$msg_ok  = '';
$msg_err = '';
if (!empty($_SESSION['msg_ok_prod'])) {
    $msg_ok = $_SESSION['msg_ok_prod'];
    unset($_SESSION['msg_ok_prod']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar'])) {
    $id_prod  = (int)($_POST['id_producto']         ?? 0);
    $tandas   = max(1, (int)($_POST['num_tandas']    ?? 1));
    $fecha    =       $_POST['fecha_produccion']     ?? date('Y-m-d');
    $obs      = trim($_POST['observaciones']         ?? '');

    // Calcular unidades desde tandas × cantidad_por_tanda
    $sp_prod = $pdo->prepare("SELECT cantidad_por_tanda FROM producto WHERE id_producto=?");
    $sp_prod->execute([$id_prod]);
    $cant_por_tanda = (float)($sp_prod->fetchColumn() ?: 1);
    $unidades = (int)round($tandas * $cant_por_tanda);

    if (!$id_prod)        $msg_err = 'Selecciona un producto.';
    elseif ($unidades<=0) $msg_err = 'Las unidades producidas deben ser mayor a 0.';
    elseif (empty($fecha) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $msg_err = 'La fecha de producción no es válida.';
    elseif ($fecha > date('Y-m-d')) $msg_err = 'No se puede registrar producción con fecha futura.';
    elseif ($fecha < date('Y-m-d', strtotime('-7 days'))) $msg_err = 'La fecha de producción no puede ser de hace más de 7 días.';
    else {
        $r = $pdo->prepare("SELECT id_receta FROM receta WHERE id_producto=? AND es_vigente=1 LIMIT 1");
        $r->execute([$id_prod]);
        $id_receta = $r->fetchColumn() ?: null;

        if (!$id_receta) {
            $msg_err = 'Este producto no tiene receta vigente. Créala primero en <a href="../recetas/index.php">Recetas</a>.';
        } else {
            $stmt = $pdo->prepare("
                SELECT ri.id_insumo, ri.cantidad AS cant_por_unidad, ri.aplica_merma,
                       i.nombre, i.unidad_medida
                FROM receta_ingrediente ri
                INNER JOIN insumo i ON i.id_insumo = ri.id_insumo
                WHERE ri.id_receta = ?
            ");
            $stmt->execute([$id_receta]);
            $ingredientes = $stmt->fetchAll();

            // Verificar stock suficiente
            // stock_actual es la fuente de verdad — incluye stock manual (editado en Inventario)
            $errores_stock = [];
            foreach ($ingredientes as $ing) {
                $cant_necesaria = $ing['cant_por_unidad'] * $tandas;

                $stmt2 = $pdo->prepare("SELECT stock_actual FROM insumo WHERE id_insumo=?");
                $stmt2->execute([$ing['id_insumo']]);
                $disponible = (float)$stmt2->fetchColumn();

                if ($disponible < $cant_necesaria) {
                    $errores_stock[] = "Falta <strong>{$ing['nombre']}</strong>: necesitas "
                        . formatoInteligente($cant_necesaria) . " {$ing['unidad_medida']}"
                        . ", solo hay " . formatoInteligente($disponible) . " {$ing['unidad_medida']}.";
                }
            }

            $forzar = !empty($_POST['forzar_produccion']);
            if (!empty($errores_stock) && !$forzar) {
                $msg_err = '<div style="text-align:left;width:100%;">'
                         . '<div style="margin-bottom:.5rem;font-weight:700;">Stock insuficiente para producir:</div>'
                         . '<ul style="margin:0;padding-left:1.5rem;line-height:1.6;font-size:.82rem;">'
                         . '<li>' . implode('</li><li>', $errores_stock) . '</li>'
                         . '</ul>'
                         . '<form method="post" style="margin-top:.8rem" id="form-forzar">'
                         . '<input type="hidden" name="id_producto" value="' . $id_prod . '">'
                         . '<input type="hidden" name="num_tandas" value="' . $tandas . '">'
                         . '<input type="hidden" name="fecha_produccion" value="' . htmlspecialchars($fecha) . '">'
                         . '<input type="hidden" name="observaciones" value="' . htmlspecialchars($obs) . '">'
                         . '<input type="hidden" name="forzar_produccion" value="1">'
                         . '<input type="hidden" name="guardar" value="1">'
                         . '<button type="submit" style="background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;border:none;border-radius:9px;padding:.55rem 1.1rem;font-size:.83rem;font-weight:700;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:.4rem;">'
                         . '<i class="bi bi-exclamation-triangle-fill"></i> Ya saqué los ingredientes — registrar con lo que hay'
                         . '</button></form></div>';
                // Forzar color verde a este error especifico para que combine
                $msg_err_class = "msg-ok";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Insertar produccion
                    // Combinar la fecha elegida con la hora actual
                    $fecha_hora = $fecha . ' ' . date('H:i:s');
                    $pdo->prepare("
                        INSERT INTO produccion
                            (id_producto, id_receta, id_usuario, cantidad_tandas,
                             fecha_produccion, observaciones, unidades_producidas, costo_total, costo_unitario)
                        VALUES (?,?,?,?,?,?,?,0,0)
                    ")->execute([$id_prod,$id_receta,$user['id_usuario'],$tandas,$fecha_hora,
                            $obs . ($forzar ? ($obs ? ' | ' : '') . '⚠ Registrado con stock insuficiente' : ''),
                            $unidades]);
                    $id_produccion = (int)$pdo->lastInsertId();

                    $costo_total = 0.0;

                    // 2. Consumir lotes FIFO por cada ingrediente
                    // cantidad receta = por tanda → multiplicar por $tandas
                    foreach ($ingredientes as $ing) {
                        $cant_necesaria = $ing['cant_por_unidad'] * $tandas;
                        $restante = $cant_necesaria;

                        $lotes_stmt = $pdo->prepare("
                            SELECT id_lote, cantidad_disponible, precio_unitario
                            FROM lote
                            WHERE id_insumo=? AND estado='activo' AND cantidad_disponible>0
                            ORDER BY fecha_ingreso ASC
                        ");
                        $lotes_stmt->execute([$ing['id_insumo']]);
                        $lotes = $lotes_stmt->fetchAll();

                        // Consumir lotes FIFO primero
                        foreach ($lotes as $lote) {
                            if ($restante <= 0) break;
                            $consumir = min((float)$lote['cantidad_disponible'], $restante);
                            $costo    = round($consumir * (float)$lote['precio_unitario'], 2);
                            $costo_total += $costo;

                            $pdo->prepare("
                                INSERT INTO consumo_lote (id_produccion, id_lote, cantidad_consumida, cantidad_con_merma, costo_consumo)
                                VALUES (?,?,?,?,?)
                            ")->execute([$id_produccion, $lote['id_lote'], $consumir, $consumir, $costo]);

                            $nueva_disp   = round((float)$lote['cantidad_disponible'] - $consumir, 4);
                            $nuevo_estado = $nueva_disp <= 0 ? 'agotado' : 'activo';
                            $pdo->prepare("
                                UPDATE lote SET cantidad_disponible=?, estado=? WHERE id_lote=?
                            ")->execute([$nueva_disp, $nuevo_estado, $lote['id_lote']]);

                            $restante -= $consumir;
                        }

                        // Siempre descontar stock_actual (cubre lotes + stock manual)
                        $pdo->prepare("
                            UPDATE insumo SET stock_actual = GREATEST(0, stock_actual - ?) WHERE id_insumo=?
                        ")->execute([$ing['cant_por_unidad'] * $tandas, $ing['id_insumo']]);
                    }

                    // 3. Actualizar costos en la produccion
                    $costo_unit = $unidades > 0 ? round($costo_total / $unidades, 4) : 0;
                    $pdo->prepare("
                        UPDATE produccion SET costo_total=?, costo_unitario=? WHERE id_produccion=?
                    ")->execute([$costo_total, $costo_unit, $id_produccion]);

                    // Insertar distribución por categoría de precio
                    $cats_dist = $_POST['dist'] ?? [];
                    $total_real = 0;
                    foreach ($cats_dist as $id_cat => $und_cat) {
                        $und_cat = (int)$und_cat;
                        if ($und_cat > 0) {
                            $pdo->prepare("INSERT INTO produccion_precio (id_produccion, id_categoria_precio, unidades) VALUES (?,?,?)")
                                ->execute([$id_produccion, (int)$id_cat, $und_cat]);
                            $total_real += $und_cat;
                        }
                    }
                    // Actualizar unidades_producidas con el total real distribuido
                    if ($total_real > 0 && $total_real != $unidades) {
                        $pdo->prepare("UPDATE produccion SET unidades_producidas=? WHERE id_produccion=?")
                            ->execute([$total_real, $id_produccion]);
                        $unidades = $total_real;
                    }

                    $pdo->commit();

                    $np = $pdo->prepare("SELECT nombre FROM producto WHERE id_producto=?");
                    $np->execute([$id_prod]);
                    $nombre_prod = $np->fetchColumn();

                    $_SESSION['msg_ok_prod'] = "Producción registrada: <strong>{$tandas} tanda(s)</strong> de <strong>" . htmlspecialchars($nombre_prod) . "</strong>"
                            . "<br><strong>{$unidades} unidades</strong> producidas"
                            . "<br>Costo total: <strong>$" . number_format($costo_total,0,',','.') . "</strong>"
                            . "<br>Costo por unidad: <strong>$" . number_format($costo_unit,0,',','.') . "</strong>"
                            . "<br>Lotes descontados correctamente.";
                    header('Location: nueva_produccion.php');
                    exit;

                } catch (Exception $e) {
                    $pdo->rollBack();
                    $msg_err = 'Error al registrar. Intenta de nuevo. (' . $e->getMessage() . ')';
                }
            }
        }
    }
}

// Productos activos con indicador de receta
// Categorías de precio para distribución
$categorias_precio = $pdo->query("SELECT * FROM categoria_precio WHERE activo=1 ORDER BY precio_unitario")->fetchAll();

$productos = $pdo->query("
    SELECT p.id_producto, p.nombre, p.cantidad_por_tanda,
           (SELECT COUNT(*) FROM receta WHERE id_producto=p.id_producto AND es_vigente=1) AS tiene_receta
    FROM producto p WHERE p.activo=1 ORDER BY p.nombre
")->fetchAll();

// Producciones de hoy
$prod_hoy = $pdo->query("
    SELECT pr.unidades_producidas, pr.fecha_produccion, pr.observaciones,
           pr.costo_total, pr.costo_unitario, p.nombre AS producto
    FROM produccion pr
    INNER JOIN producto p ON p.id_producto=pr.id_producto
    WHERE DATE(pr.fecha_produccion)='" . date('Y-m-d') . "'
    ORDER BY pr.fecha_produccion DESC
")->fetchAll();

$total_hoy = array_sum(array_column($prod_hoy,'unidades_producidas'));
$costo_hoy = array_sum(array_column($prod_hoy,'costo_total'));


// ── Observación del último cierre ──────────────────────────────
$obs_cierre = $pdo->query("
    SELECT sugerencia_produccion, fecha FROM cierre_dia 
    WHERE sugerencia_produccion IS NOT NULL AND sugerencia_produccion != '' 
    ORDER BY fecha DESC LIMIT 1
")->fetch();

$page_title = 'Nueva Producción';
require_once __DIR__ . '/../../views/layouts/header.php';
?>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/produccion.css">


<div class="page">


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

  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreakControl</div>
        <div class="wc-name">Nueva <em>Producción</em></div>
        <div class="wc-sub">Consumo FIFO de lotes automático · <?= date('d/m/Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_hoy > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num"><?= $total_hoy ?></div>
        <div class="wc-pill-lbl">Unidades hoy</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= count($prod_hoy) ?></div>
        <div class="wc-pill-lbl">Registros</div>
      </div>
      <div class="wc-pill <?= $costo_hoy > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($costo_hoy/1000,1) ?>k</div>
        <div class="wc-pill-lbl">Costo hoy</div>
      </div>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-fire"></i> Nueva producción</div>
    <a href="index.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
  </div>

  <div class="g-body">

    <!-- FORMULARIO -->
    <div class="card">
      <div class="ch">
        <div class="ch-left"><div class="ch-ico ico-nar"><i class="bi bi-pencil-fill"></i></div><span class="ch-title">Registrar producción</span></div>
      </div>
      <div class="form-body">

        <?php if ($msg_ok): ?>
        <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div>
        <?php endif; ?>
        <?php if ($msg_err): ?>
        <div class="<?= !empty($msg_err_class) ? $msg_err_class : 'msg-err' ?>"><i class="bi bi-<?= !empty($msg_err_class) ? 'info-circle-fill' : 'exclamation-circle-fill' ?>"></i> <?= $msg_err ?></div>
        <?php endif; ?>

        <form method="post" id="form-prod">

          <div class="fl">
            <label>Producto</label>
            <select name="id_producto" id="sel-prod" required onchange="cargarLotes()">
              <option value="">— Seleccionar producto —</option>
              <?php foreach ($productos as $p): ?>
              <option value="<?= $p['id_producto'] ?>"
                data-tanda="<?= (int)$p['cantidad_por_tanda'] ?>"
                <?= (isset($_POST['id_producto']) && $_POST['id_producto']==$p['id_producto']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($p['nombre']) ?>
                (<?= (int)$p['cantidad_por_tanda'] ?> und/tanda)
                <?= !$p['tiene_receta'] ? ' ⚠ sin receta' : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="fl">
            <label>N° de tandas a producir</label>
            <div class="und-ctrl">
              <button type="button" class="und-btn" onclick="changeUnd(-1)">−</button>
              <input type="number" name="num_tandas" id="inp-und" class="und-inp"
                     min="1" max="5" value="<?= (int)($_POST['num_tandas'] ?? 1) ?>"
                     required oninput="cargarLotes()">
              <button type="button" class="und-btn" onclick="changeUnd(1)">+</button>
            </div>
            <!-- Preview total unidades -->
            <div id="preview-unidades" style="margin-top:.35rem;font-size:.78rem;color:var(--ink3);display:none;">
              = <span id="preview-unidades-val" style="font-family:'Fraunces',serif;font-weight:800;color:var(--c3);font-size:.92rem;"></span>
              <span id="preview-unidades-lbl"> panes disponibles para venta</span>
            </div>
          </div>



          <!-- DISTRIBUCIÓN POR PRECIO -->
          <div id="panel-distribucion" class="fl" style="display:none;">
            <label><i class="bi bi-grid-3x3-gap" style="color:var(--c3)"></i> ¿Cuántos de cada precio?</label>
            <div style="background:var(--clight);border:1px solid var(--border);border-radius:10px;padding:.7rem .85rem;">
              <div style="font-size:.7rem;color:var(--ink3);margin-bottom:.5rem;">
                Se esperan <strong id="dist-total-label">0</strong> unidades. Escribe cuántas salieron realmente de cada precio:
              </div>
              <?php foreach ($categorias_precio as $cp): ?>
              <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.4rem;">
                <span style="font-size:.8rem;font-weight:700;color:var(--ink);min-width:65px;">$<?= number_format($cp['precio_unitario'],0,',','.') ?></span>
                <input type="number" name="dist[<?= $cp['id_categoria'] ?>]" 
                       class="dist-input" data-cat="<?= $cp['id_categoria'] ?>"
                       min="0" step="1" value="0" oninput="checkDistTotal()"
                       style="width:80px;border:1px solid var(--border);border-radius:8px;padding:.35rem .5rem;font-size:.88rem;font-family:'Fraunces',serif;font-weight:700;text-align:center;background:#fff;">
                <span style="font-size:.7rem;color:var(--ink3);">unidades</span>
              </div>
              <?php endforeach; ?>
              <div id="dist-status" style="margin-top:.5rem;padding:.4rem .6rem;border-radius:8px;font-size:.75rem;font-weight:700;text-align:center;"></div>
            </div>
          </div>

          <!-- PANEL DE LOTES FIFO -->
          <div id="panel-lotes" class="fl" style="display:none;">
            <label><i class="bi bi-boxes" style="color:var(--c3)"></i> Guía de lotes a usar (más antiguos primero)</label>
            <div class="lotes-panel">
              <div class="lotes-hdr">
                <span class="lotes-hdr-ttl"><i class="bi bi-sort-up"></i> Orden FIFO — saca estos lotes del estante</span>
                <span id="badge-lotes" class="badge b-neu"></span>
              </div>
              <div id="lotes-contenido">
                <div class="lotes-loading"><i class="bi bi-arrow-repeat" style="animation:spin .8s linear infinite;display:inline-block"></i> Cargando…</div>
              </div>
            </div>
          </div>

          <div class="fl">
            <label>Fecha</label>
            <input type="date" name="fecha_produccion"
                   min="<?= date('Y-m-d', strtotime('-7 days')) ?>"
                   max="<?= date('Y-m-d') ?>"
                   value="<?= htmlspecialchars($_POST['fecha_produccion'] ?? date('Y-m-d'), ENT_QUOTES) ?? date('Y-m-d') ?>">
          </div>

          <div class="fl">
            <label>Observaciones <span style="font-weight:400;text-transform:none;font-size:.7rem;">(opcional)</span></label>
            <textarea name="observaciones" placeholder="Ej: levadura reducida, horneado doble…"><?= htmlspecialchars($_POST['observaciones'] ?? '') ?></textarea>
          </div>

          <button type="submit" name="guardar" class="btn-guardar" id="btn-guardar">
            <i class="bi bi-check-lg"></i> Registrar y descontar lotes
          </button>

        </form>
      </div>
    </div>

    <!-- TABLA DE HOY -->
    <div class="card">
      <div class="ch">
        <div class="ch-left"><div class="ch-ico ico-grn"><i class="bi bi-clock-history"></i></div><span class="ch-title">Producción de hoy</span></div>
        <span class="badge b-grn"><?= $total_hoy ?> unidades · $<?= number_format($costo_hoy,0,',','.') ?></span>
      </div>
      <?php if (empty($prod_hoy)): ?>
      <div class="empty"><i class="bi bi-basket"></i><strong>Sin registros aún</strong><span>Los registros de hoy aparecen aquí</span></div>
      <?php else: ?>
      <div class="tbl-wrap">
        <table class="gt">
          <thead>
            <tr>
              <th>Hora</th>
              <th>Producto</th>
              <th style="text-align:center">Unids.</th>
              <th style="text-align:right">Costo total</th>
              <th style="text-align:right">C/unidad</th>
              <th>Observ.</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($prod_hoy as $pr): ?>
          <tr>
            <td style="color:var(--ink3);font-size:.75rem"><?= date('H:i', strtotime($pr['fecha_produccion'])) ?></td>
            <td style="font-weight:600"><?= htmlspecialchars($pr['producto']) ?></td>
            <td style="text-align:center;font-weight:800;font-family:'Fraunces',serif;font-size:1.05rem;color:var(--c3)"><?= $pr['unidades_producidas'] ?></td>
            <td style="text-align:right;color:#1b5e20;font-weight:600;font-size:.8rem">$<?= number_format($pr['costo_total'],0,',','.') ?></td>
            <td style="text-align:right;color:var(--ink3);font-size:.75rem">$<?= number_format($pr['costo_unitario'],0,',','.') ?></td>
            <td style="color:var(--ink3);font-size:.76rem"><?= htmlspecialchars($pr['observaciones'] ?? '—') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr>
              <td colspan="2" style="text-align:right;font-size:.75rem;text-transform:uppercase;letter-spacing:.08em;color:var(--ink2)">Total hoy</td>
              <td style="text-align:center;font-family:'Fraunces',serif;color:#2e7d32"><?= $total_hoy ?></td>
              <td style="text-align:right;color:#1b5e20">$<?= number_format($costo_hoy,0,',','.') ?></td>
              <td colspan="2"></td>
            </tr>
          </tfoot>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/produccion.js"></script>

</body></html>