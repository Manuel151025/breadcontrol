<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';

requerirPropietario();
$pdo = getConexion();
$user = usuarioActual();

$msg_ok = $_GET['msg_ok'] ?? '';
$msg_err = $_GET['msg_err'] ?? '';

// ══ AJAX: detalle de un pedido (para editar) ══
if (isset($_GET['ajax_detalle_venta'])) {
  header('Content-Type: application/json');
  try {
    $id_v = (int) $_GET['id_venta'];
    $det = $pdo->prepare("
            SELECT vd.id_variedad, vd.cantidad, vd.napa,
                   COALESCE(vd.bonificacion,0) AS bonificacion,
                   vd.precio_unitario,
                   vp.nombre, vp.imagen, vp.id_categoria_precio
            FROM venta_detalle vd
            INNER JOIN variedad_pan vp ON vp.id_variedad=vd.id_variedad
            WHERE vd.id_venta=?
        ");
    $det->execute([$id_v]);
    $items = $det->fetchAll(PDO::FETCH_ASSOC);
    $venta = $pdo->prepare("SELECT id_cliente FROM venta WHERE id_venta=?");
    $venta->execute([$id_v]);
    $v_info = $venta->fetch();
    echo json_encode(['items' => $items, 'id_cliente' => $v_info['id_cliente'] ?? 0]);
  } catch (Exception $e) {
    echo json_encode(['items' => [], 'id_cliente' => 0]);
  }
  exit;
}

// ══ AJAX: TODAS las variedades (para bonificación) ══
if (isset($_GET['ajax_all_variedades'])) {
  header('Content-Type: application/json');
  try {
    $all = $pdo->query("
            SELECT vp.id_variedad, vp.nombre, vp.imagen, vp.id_categoria_precio,
                   cp.nombre AS cat_nombre, cp.precio_unitario
            FROM variedad_pan vp
            INNER JOIN categoria_precio cp ON cp.id_categoria = vp.id_categoria_precio
            WHERE vp.activo=1 ORDER BY cp.precio_unitario, vp.nombre
        ")->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($all);
  } catch (Exception $e) {
    echo json_encode([]);
  }
  exit;
}

// ══ AJAX: variedades por categoría ══
if (isset($_GET['ajax_variedades'])) {
  header('Content-Type: application/json');
  try {
    $id_cat = (int) $_GET['id_cat'];
    $vars = $pdo->prepare("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE id_categoria_precio=? AND activo=1 ORDER BY nombre");
    $vars->execute([$id_cat]);
    echo json_encode($vars->fetchAll(PDO::FETCH_ASSOC));
  } catch (Exception $e) {
    echo json_encode([]);
  }
  exit;
}

// ══ POST — Venta rápida (modo normal) ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_venta'])) {
  $id_cat = (int) ($_POST['id_categoria'] ?? 0);
  $cantidad = (int) ($_POST['cantidad'] ?? 0);
  $tipo_salida = $_POST['tipo_salida'] ?? 'venta';
  $id_cliente = (int) ($_POST['id_cliente'] ?? 0);
  $dar_napa = isset($_POST['dar_napa']) && $_POST['dar_napa'] === '1';
  $napa_cant = max(0, (int) ($_POST['napa_cantidad'] ?? 0));
  $precio_custom = (float) ($_POST['precio_custom'] ?? 0);

  if (!in_array($tipo_salida, ['venta', 'consumo_interno']))
    $tipo_salida = 'venta';
  if (!$id_cat && $precio_custom <= 0)
    $msg_err = 'Selecciona una categoría de precio.';
  elseif (!$id_cat && $precio_custom > 20000)
    $msg_err = 'El precio personalizado no puede superar los $20.000.';
  elseif ($cantidad <= 0)
    $msg_err = 'La cantidad debe ser mayor a 0.';
  elseif ($cantidad > 999)
    $msg_err = 'La cantidad máxima permitida es 999.';
  else {
    if ($id_cat) {
      $cat = $pdo->prepare("SELECT nombre, precio_unitario FROM categoria_precio WHERE id_categoria=?");
      $cat->execute([$id_cat]);
      $cat_data = $cat->fetch();
    }
    if (!$id_cat && $precio_custom > 0) {
      $cat_data = ['nombre' => 'Precio personalizado $' . number_format($precio_custom, 0, ',', '.'), 'precio_unitario' => $precio_custom];
    }
    if (!$cat_data) {
      $msg_err = 'Categoría no encontrada.';
    } else {
      $precio = (float) $cat_data['precio_unitario'];
      $total = ($tipo_salida === 'venta') ? round($precio * $cantidad, 2) : 0;

      $stock_disponible = 9999;
      if ($id_cat) {
        $stk = $pdo->prepare("SELECT COALESCE((SELECT SUM(pp.unidades) FROM produccion_precio pp WHERE pp.id_categoria_precio=?),0) - COALESCE((SELECT SUM(v.unidades_vendidas) FROM venta v WHERE v.id_categoria_precio=?),0) AS disponible");
        $stk->execute([$id_cat, $id_cat]);
        $stock_disponible = (int) $stk->fetchColumn();
      }

      $bonificacion = 0;
      $napa = 0;
      $und_fisicas = $cantidad;
      $total_venta_tmp = $precio * $cantidad;
      if ($tipo_salida === 'venta' && $id_cliente > 0) {
        $tc = $pdo->prepare("SELECT tipo FROM cliente WHERE id_cliente=?");
        $tc->execute([$id_cliente]);
        if ($tc->fetchColumn() === 'tienda') {
          // TIENDA: $1.000 de crédito por cada $5.000
          $credito = floor($total_venta_tmp / 5000) * 1000;
          $bonificacion = ($precio > 0) ? (int) floor($credito / $precio) : 0;
          $und_fisicas = $cantidad + $bonificacion;
        }
      } elseif ($tipo_salida === 'venta' && $id_cliente == 0) {
        // MOSTRADOR: $500 de crédito por cada $5.000
        $credito = floor($total_venta_tmp / 5000) * 500;
        $napa = ($precio > 0) ? (int) floor($credito / $precio) : 0;
        $und_fisicas = $cantidad + $napa;
      }

      if ($und_fisicas > $stock_disponible && $tipo_salida === 'venta' && $id_cat) {
        $msg_err = "Stock insuficiente.<br>Disponible: <strong>$stock_disponible</strong>.<br>Intentas sacar: <strong>$und_fisicas</strong>.";
      } else {
        try {
          $pdo->prepare("INSERT INTO venta (id_categoria_precio, tipo_salida, id_cliente, id_usuario, fecha_hora, unidades_vendidas, precio_unitario, total_venta, unidades_bonificacion) VALUES (?,?,?,?,NOW(),?,?,?,?)")
            ->execute([$id_cat ?: null, $tipo_salida, ($tipo_salida === 'venta' && $id_cliente > 0) ? $id_cliente : null, $user['id_usuario'], $und_fisicas, $precio, $total, $bonificacion + $napa]);

          $tipo_labels = ['venta' => 'Venta', 'bonificacion' => 'Bonificación', 'consumo_interno' => 'Consumo interno'];
          $tipo_icons = ['venta' => '💰', 'bonificacion' => '🎁', 'consumo_interno' => '🍞'];
          $msg_ok = $tipo_icons[$tipo_salida] . " <strong>" . $tipo_labels[$tipo_salida] . " registrada</strong><br>$cantidad unidades de " . htmlspecialchars($cat_data['nombre']);
          if ($bonificacion > 0)
            $msg_ok .= "<br>+ <strong>$bonificacion bonificadas 🏪</strong> = <strong>$und_fisicas entregadas</strong>";
          if ($napa > 0)
            $msg_ok .= "<br>+ <strong>$napa de ñapa 🎁</strong> = <strong>$und_fisicas entregadas</strong>";
          if ($tipo_salida === 'venta')
            $msg_ok .= "<br>Total: <strong>$" . number_format($total, 0, ',', '.') . "</strong>";
          header('Location: index.php?msg_ok=' . urlencode($msg_ok));
          exit;
        } catch (Exception $e) {
          $msg_err = 'Error: ' . $e->getMessage();
        }
      }
    }
  }
}

// ══ POST — Pedido detallado (carrito) ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_pedido'])) {
  $cart_json = $_POST['carrito_json'] ?? '[]';
  $id_cliente = (int) ($_POST['ped_cliente'] ?? 0);
  $dar_napa_ped = isset($_POST['ped_napa']) && $_POST['ped_napa'] === '1';
  $cart = json_decode($cart_json, true);

  if (empty($cart)) {
    $msg_err = 'El carrito está vacío.';
  } else {
    try {
      $total_und = 0;
      $total_dinero = 0;
      foreach ($cart as $item) {
        $total_und += (int) $item['cantidad'];
        $total_dinero += (int) $item['cantidad'] * (float) $item['precio'];
      }

      // Bonus global del panel de bonificación/ñapa
      $bonif_json = $_POST['bonif_json'] ?? '[]';
      $bonif_items = json_decode($bonif_json, true);
      $bonus_units = 0;
      if (!empty($bonif_items)) {
        foreach ($bonif_items as $bi)
          $bonus_units += (int) ($bi['cantidad'] ?? 0);
      }
      $und_totales = $total_und + $bonus_units;

      $pdo->prepare("INSERT INTO venta (tipo_salida, id_cliente, id_usuario, fecha_hora, unidades_vendidas, precio_unitario, total_venta, unidades_bonificacion) VALUES ('venta',?,?,NOW(),?,0,?,?)")
        ->execute([
          $id_cliente > 0 ? $id_cliente : null,
          $user['id_usuario'],
          $und_totales,
          $total_dinero,
          $bonus_units
        ]);
      $id_venta = $pdo->lastInsertId();

      foreach ($cart as $item) {
        $pdo->prepare("INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) VALUES (?,?,?,?,?,?)")
          ->execute([$id_venta, (int) $item['id_variedad'], (int) $item['cantidad'], 0, 0, (float) $item['precio']]);
      }

      // Guardar detalle de bonificación
      if (!empty($bonif_items)) {
        foreach ($bonif_items as $bi) {
          if ((int) ($bi['cantidad'] ?? 0) > 0) {
            $pdo->prepare("INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) VALUES (?,?,0,0,?,?)")
              ->execute([$id_venta, (int) $bi['id_variedad'], (int) $bi['cantidad'], (float) ($bi['precio'] ?? 0)]);
          }
        }
      }

      $msg_ok = "📋 <strong>Pedido detallado registrado</strong>"
        . "<br>" . count($cart) . " variedades · $total_und unidades cobradas";
      if ($bonus_units > 0)
        $msg_ok .= "<br>🎁 Bonificación/Ñapa: <strong>$bonus_units</strong> unidades de regalo";
      $msg_ok .= "<br>Total cobrado: <strong>$" . number_format($total_dinero, 0, ',', '.') . "</strong>";
      header('Location: index.php?msg_ok=' . urlencode($msg_ok));
      exit;
    } catch (Exception $e) {
      $msg_err = 'Error: ' . $e->getMessage();
    }
  }
}

// ══ POST — Editar pedido detallado ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_pedido'])) {
  $id_v = (int) ($_POST['edit_id_venta'] ?? 0);
  $cart_json = $_POST['edit_carrito_json'] ?? '[]';
  $id_cliente = (int) ($_POST['edit_ped_cliente'] ?? 0);
  $bonif_json_e = $_POST['edit_bonif_json'] ?? '[]';
  $cart = json_decode($cart_json, true);
  $bonif_items_e = json_decode($bonif_json_e, true);

  if ($id_v && !empty($cart)) {
    try {
      $total_und = 0;
      $total_dinero = 0;
      foreach ($cart as $item) {
        $total_und += (int) $item['cantidad'];
        $total_dinero += (int) $item['cantidad'] * (float) $item['precio'];
      }
      $bonus_units = 0;
      if (!empty($bonif_items_e)) {
        foreach ($bonif_items_e as $bi)
          $bonus_units += (int) ($bi['cantidad'] ?? 0);
      }
      $und_totales = $total_und + $bonus_units;

      // Update venta
      $pdo->prepare("UPDATE venta SET id_cliente=?, unidades_vendidas=?, total_venta=?, unidades_bonificacion=? WHERE id_venta=? AND DATE(fecha_hora)=CURDATE()")
        ->execute([
          $id_cliente > 0 ? $id_cliente : null,
          $und_totales,
          $total_dinero,
          $bonus_units,
          $id_v
        ]);

      // Borrar y reinsertar el detalle
      $pdo->prepare("DELETE FROM venta_detalle WHERE id_venta=?")->execute([$id_v]);
      foreach ($cart as $item) {
        $pdo->prepare("INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) VALUES (?,?,?,?,0,?)")
          ->execute([$id_v, (int) $item['id_variedad'], (int) $item['cantidad'], (int) ($item['napa'] ?? 0), (float) $item['precio']]);
      }
      // Guardar bonificación/ñapa
      if (!empty($bonif_items_e)) {
        foreach ($bonif_items_e as $bi) {
          if ((int) ($bi['cantidad'] ?? 0) > 0) {
            $pdo->prepare("INSERT INTO venta_detalle (id_venta, id_variedad, cantidad, napa, bonificacion, precio_unitario) VALUES (?,?,0,0,?,?)")
              ->execute([$id_v, (int) $bi['id_variedad'], (int) $bi['cantidad'], (float) ($bi['precio'] ?? 0)]);
          }
        }
      }

      $msg_ok = "📋 <strong>Pedido #$id_v actualizado</strong><br>" . count($cart) . " variedades · $total_und unidades · $" . number_format($total_dinero, 0, ',', '.');
      if ($bonus_units > 0) {
        $etiq = $id_cliente > 0 ? 'bonificación 🏪' : 'ñapa 🎁';
        $msg_ok .= "<br>+ <strong>$bonus_units</strong> de $etiq";
      }
      header('Location: index.php?msg_ok=' . urlencode($msg_ok));
      exit;
    } catch (Exception $e) {
      $msg_err = 'Error al editar: ' . $e->getMessage();
    }
  }
}

// ══ POST — Editar ══
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_venta'])) {
  $id_v = (int) ($_POST['id_venta'] ?? 0);
  $id_cat = (int) ($_POST['ev_categoria'] ?? 0);
  $cant = (int) ($_POST['ev_cantidad'] ?? 0);
  $tipo = $_POST['ev_tipo'] ?? 'venta';
  $id_cli = (int) ($_POST['ev_cliente'] ?? 0);
  $extra = max(0, (int) ($_POST['ev_extra_bonif'] ?? 0));     // unidades extras a sumar
  if (!in_array($tipo, ['venta', 'bonificacion', 'consumo_interno']))
    $tipo = 'venta';

  if ($id_v && $id_cat && $cant > 0) {
    $cat = $pdo->prepare("SELECT precio_unitario FROM categoria_precio WHERE id_categoria=?");
    $cat->execute([$id_cat]);
    $precio = (float) $cat->fetchColumn();
    $total = ($tipo === 'venta') ? round($precio * $cant, 2) : 0;

    $bonif_edit = 0;
    $und_edit = $cant;
    if ($tipo === 'venta') {
      if ($id_cli > 0) {
        // TIENDA: $1.000 de crédito por cada $5.000
        $tc = $pdo->prepare("SELECT tipo FROM cliente WHERE id_cliente=?");
        $tc->execute([$id_cli]);
        if ($tc->fetchColumn() === 'tienda') {
          $credito = floor(($precio * $cant) / 5000) * 1000;
          $bonif_calc = ($precio > 0) ? (int) floor($credito / $precio) : 0;
          // Sumamos las unidades extra que el usuario indicó (al cambiar de mostrador → tienda)
          $bonif_edit = $bonif_calc + $extra;
          $und_edit = $cant + $bonif_edit;
        }
      } else {
        // MOSTRADOR: $500 de crédito por cada $5.000 (ñapa automática)
        $credito = floor(($precio * $cant) / 5000) * 500;
        $bonif_edit = ($precio > 0) ? (int) floor($credito / $precio) : 0;
        $und_edit = $cant + $bonif_edit;
      }
    }

    $pdo->prepare("UPDATE venta SET id_categoria_precio=?, tipo_salida=?, id_cliente=?, unidades_vendidas=?, precio_unitario=?, total_venta=?, unidades_bonificacion=? WHERE id_venta=? AND DATE(fecha_hora)=CURDATE()")
      ->execute([$id_cat, $tipo, ($tipo === 'venta' && $id_cli > 0) ? $id_cli : null, $und_edit, $precio, $total, $bonif_edit, $id_v]);

    $msg_ok = "✏️ Registro <strong>#$id_v</strong> actualizado. <strong>$cant</strong> cobradas";
    if ($bonif_edit > 0) {
      $msg_ok .= ($id_cli > 0) ? " + <strong>$bonif_edit</strong> bonif. 🏪" : " + <strong>$bonif_edit</strong> ñapa 🎁";
      $msg_ok .= " = <strong>$und_edit</strong> entregadas";
    }
  }
}

// ══ DELETE ══
if (!empty($_GET['del_venta'])) {
  $pdo->prepare("DELETE FROM venta WHERE id_venta=? AND DATE(fecha_hora)=CURDATE()")->execute([(int) $_GET['del_venta']]);
  header('Location: index.php');
  exit;
}

// ── Datos ──
$categorias = $pdo->query("
    SELECT cp.*,
        COALESCE((SELECT SUM(pp.unidades) FROM produccion_precio pp WHERE pp.id_categoria_precio=cp.id_categoria),0) -
        COALESCE((SELECT SUM(v.unidades_vendidas) FROM venta v WHERE v.id_categoria_precio=cp.id_categoria),0) AS stock_hoy
    FROM categoria_precio cp WHERE cp.activo=1 ORDER BY cp.precio_unitario
")->fetchAll();

$clientes = $pdo->query("SELECT id_cliente, nombre FROM cliente WHERE activo=1 AND tipo='tienda' ORDER BY nombre")->fetchAll();

$registros_hoy = $pdo->query("
    SELECT v.id_venta, v.unidades_vendidas, v.precio_unitario, v.total_venta,
           v.tipo_salida, v.fecha_hora, v.id_categoria_precio, v.id_cliente,
           COALESCE(v.unidades_bonificacion,0) AS bonificacion,
           COALESCE(cp.nombre, CONCAT('Producto #', v.id_producto)) AS categoria,
           COALESCE(c.nombre, 'Mostrador') AS cliente
    FROM venta v
    LEFT JOIN categoria_precio cp ON cp.id_categoria=v.id_categoria_precio
    LEFT JOIN cliente c ON c.id_cliente=v.id_cliente
    WHERE DATE(v.fecha_hora)=CURDATE() ORDER BY v.fecha_hora DESC
")->fetchAll();

// IDs de ventas con detalle
$ventas_con_detalle = [];
try {
  $vcd = $pdo->query("SELECT DISTINCT id_venta FROM venta_detalle");
  if ($vcd)
    $ventas_con_detalle = $vcd->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) { /* tabla aún no creada */
}

$total_ventas = 0;
$und_ventas = 0;
$n_ventas = 0;
$und_bonif = 0;
$und_consumo = 0;
foreach ($registros_hoy as $r) {
  if ($r['tipo_salida'] === 'venta') {
    $total_ventas += $r['total_venta'];
    $und_ventas += $r['unidades_vendidas'];
    $n_ventas++;
  } elseif ($r['tipo_salida'] === 'bonificacion')
    $und_bonif++;
  else
    $und_consumo++;
}
$ventas_ayer = (float) $pdo->query("SELECT COALESCE(SUM(total_venta),0) FROM venta WHERE tipo_salida='venta' AND DATE(fecha_hora)=DATE_SUB(CURDATE(),INTERVAL 1 DAY)")->fetchColumn();
$diff_pct = $ventas_ayer > 0 ? round((($total_ventas - $ventas_ayer) / $ventas_ayer) * 100, 1) : null;

$page_title = 'Ventas';
require_once __DIR__ . '/../../views/layouts/header.php';
?>
<style>
  :root {
    --c1: #945b35;
    --c2: #c8956e;
    --c3: #c67124;
    --c4: #e4a565;
    --c5: #ecc198;
    --cbg: #faf3ea;
    --ccard: #fff;
    --clight: #fdf6ee;
    --ink: #281508;
    --ink2: #6b3d1e;
    --ink3: #b87a4a;
    --border: rgba(148, 91, 53, .12);
    --shadow: 0 1px 8px rgba(148, 91, 53, .09);
    --shadow2: 0 4px 20px rgba(148, 91, 53, .15);
    --nav-h: 64px;
  }

  @keyframes fadeUp {
    from {
      opacity: 0;
      transform: translateY(10px)
    }

    to {
      opacity: 1;
      transform: translateY(0)
    }
  }

  @keyframes gradAnim {
    0% {
      background-position: 0% 50%
    }

    50% {
      background-position: 100% 50%
    }

    100% {
      background-position: 0% 50%
    }
  }

  .page {
    margin-top: var(--nav-h);
    height: calc(100vh - var(--nav-h));
    overflow: hidden;
    display: grid;
    grid-template-rows: auto auto 1fr;
    gap: .7rem;
    padding: .75rem;
  }

  .wc-banner {
    background: linear-gradient(125deg, #6b3211 0%, #945b35 18%, #c67124 35%, #e4a565 50%, #c67124 65%, #945b35 80%, #6b3211 100%);
    background-size: 300% 300%;
    animation: gradAnim 8s ease infinite;
    border-radius: 14px;
    padding: .9rem 1.4rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: var(--shadow2);
    gap: 1rem;
    flex-wrap: wrap;
  }

  .wc-left {
    display: flex;
    align-items: center;
    gap: .9rem;
  }

  .wc-greeting {
    font-size: .65rem;
    text-transform: uppercase;
    letter-spacing: .2em;
    color: rgba(255, 255, 255, .65);
    margin-bottom: .15rem;
  }

  .wc-name {
    font-family: 'Fraunces', serif;
    font-size: 1.35rem;
    font-weight: 800;
    color: #fff;
    line-height: 1.1;
  }

  .wc-name em {
    font-style: italic;
    color: var(--c5);
  }

  .wc-sub {
    font-size: .72rem;
    color: rgba(255, 255, 255, .62);
    margin-top: .15rem;
  }

  .wc-pills {
    display: flex;
    gap: .55rem;
    flex-wrap: wrap;
  }

  .wc-pill {
    background: rgba(255, 255, 255, .14);
    border: 1px solid rgba(255, 255, 255, .2);
    border-radius: 10px;
    padding: .5rem .85rem;
    text-align: center;
    min-width: 72px;
  }

  .wc-pill-num {
    font-family: 'Fraunces', serif;
    font-size: 1.35rem;
    font-weight: 800;
    color: #fff;
    line-height: 1;
  }

  .wc-pill-lbl {
    font-size: .54rem;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: rgba(255, 255, 255, .58);
  }

  .wc-pill.ok {
    background: rgba(200, 255, 220, .2);
    border-color: rgba(200, 255, 220, .35);
  }

  .wc-pill.ok .wc-pill-num {
    color: #c8ffd8;
  }

  .topbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: .75rem;
    flex-wrap: wrap;
  }

  .mod-titulo {
    font-family: 'Fraunces', serif;
    font-size: 1.45rem;
    font-weight: 800;
    color: var(--ink);
    display: flex;
    align-items: center;
    gap: .5rem;
  }

  .mod-titulo i {
    color: var(--c3);
  }

  .top-actions {
    display: flex;
    gap: .5rem;
    align-items: center;
  }

  .btn-sec {
    background: var(--ccard);
    color: var(--ink2);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: .45rem .9rem;
    font-size: .82rem;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: .4rem;
    text-decoration: none;
    transition: all .2s;
    cursor: pointer;
    font-family: inherit;
  }

  .btn-sec:hover {
    background: var(--clight);
    border-color: var(--c3);
  }

  .btn-sec.active {
    background: rgba(198, 113, 36, .12);
    border-color: var(--c3);
    color: var(--c3);
  }

  .g-body {
    display: grid;
    grid-template-columns: 340px 1fr;
    gap: .7rem;
    min-height: 0;
  }

  .card {
    background: var(--ccard);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
    animation: fadeUp .45s ease both;
  }

  .ch {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: .8rem 1.1rem;
    flex-shrink: 0;
    border-bottom: 1px solid var(--border);
  }

  .ch-left {
    display: flex;
    align-items: center;
    gap: .5rem;
  }

  .ch-ico {
    width: 30px;
    height: 30px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
  }

  .ico-nar {
    background: rgba(198, 113, 36, .1);
    color: var(--c3);
  }

  .ico-grn {
    background: rgba(25, 135, 84, .1);
    color: #198754;
  }

  .ch-title {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .17em;
    color: var(--ink3);
  }

  .badge {
    display: inline-flex;
    align-items: center;
    font-size: .62rem;
    font-weight: 700;
    padding: .15rem .5rem;
    border-radius: 20px;
  }

  .b-neu {
    background: var(--clight);
    color: var(--c1);
    border: 1px solid var(--border);
  }

  .form-body {
    padding: .9rem 1.1rem;
    overflow-y: auto;
    flex: 1;
  }

  .fl {
    margin-bottom: .72rem;
  }

  .fl label {
    font-size: .63rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .14em;
    color: var(--ink3);
    display: block;
    margin-bottom: .28rem;
  }

  .fl input,
  .fl select {
    width: 100%;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: .45rem .75rem;
    font-size: .84rem;
    color: var(--ink);
    font-family: inherit;
    background: var(--clight);
    transition: border-color .2s;
    box-sizing: border-box;
  }

  .fl input:focus,
  .fl select:focus {
    outline: none;
    border-color: var(--c3);
    box-shadow: 0 0 0 3px rgba(198, 113, 36, .1);
  }

  .sec-sep {
    font-size: .6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .15em;
    color: var(--ink3);
    padding: .3rem 0;
    margin: .15rem 0 .4rem;
    border-bottom: 1px dashed var(--border);
  }

  .cat-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .4rem;
  }

  .cat-btn {
    border: 2px solid var(--border);
    border-radius: 10px;
    padding: .55rem .5rem;
    text-align: center;
    cursor: pointer;
    background: var(--clight);
    transition: all .2s;
    font-family: inherit;
  }

  .cat-btn:hover {
    border-color: var(--c3);
  }

  .cat-btn.active {
    border-color: var(--c3);
    background: rgba(198, 113, 36, .1);
    box-shadow: 0 0 0 3px rgba(198, 113, 36, .12);
  }

  .cat-btn .cat-price {
    font-family: 'Fraunces', serif;
    font-size: 1.1rem;
    font-weight: 800;
    color: var(--ink);
  }

  .cat-btn.active .cat-price {
    color: var(--c3);
  }

  .cat-btn .cat-stock {
    font-size: .6rem;
    font-weight: 700;
    margin-top: .15rem;
    padding: .1rem .35rem;
    border-radius: 10px;
    display: inline-block;
  }

  .stk-ok {
    color: #2e7d32;
    background: rgba(46, 125, 50, .1);
  }

  .stk-warn {
    color: #e65100;
    background: rgba(230, 81, 0, .1);
  }

  .stk-zero {
    color: #c62828;
    background: rgba(198, 40, 40, .1);
  }

  .fl-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .5rem;
  }

  .tipo-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: .35rem;
  }

  .tipo-btn {
    border: 2px solid var(--border);
    border-radius: 9px;
    padding: .45rem .3rem;
    text-align: center;
    cursor: pointer;
    background: var(--clight);
    transition: all .2s;
    font-family: inherit;
    font-size: .72rem;
    font-weight: 600;
    color: var(--ink2);
  }

  .tipo-btn.active {
    box-shadow: 0 0 0 3px rgba(0, 0, 0, .06);
  }

  .tipo-btn[data-tipo="venta"].active {
    border-color: #2e7d32;
    background: rgba(46, 125, 50, .08);
    color: #2e7d32;
  }

  .tipo-btn[data-tipo="bonificacion"].active {
    border-color: #1565c0;
    background: rgba(21, 101, 192, .08);
    color: #1565c0;
  }

  .tipo-btn[data-tipo="consumo_interno"].active {
    border-color: #e65100;
    background: rgba(230, 81, 0, .08);
    color: #e65100;
  }

  .tipo-btn i {
    display: block;
    font-size: 1.1rem;
    margin-bottom: .15rem;
  }

  .total-display {
    background: linear-gradient(135deg, rgba(46, 125, 50, .08), rgba(46, 125, 50, .03));
    border: 1px solid rgba(46, 125, 50, .2);
    border-radius: 10px;
    padding: .6rem .8rem;
    text-align: center;
    margin-bottom: .5rem;
  }

  .total-lbl {
    font-size: .6rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: #2e7d32;
  }

  .total-val {
    font-family: 'Fraunces', serif;
    font-size: 1.6rem;
    font-weight: 800;
    color: #1b5e20;
  }

  .total-und {
    font-size: .72rem;
    color: #388e3c;
    margin-top: .1rem;
  }

  .total-display.no-income {
    background: linear-gradient(135deg, rgba(21, 101, 192, .06), rgba(21, 101, 192, .02));
    border-color: rgba(21, 101, 192, .18);
  }

  .total-display.no-income .total-lbl {
    color: #1565c0;
  }

  .total-display.no-income .total-val {
    color: #1565c0;
  }

  .btn-guardar {
    width: 100%;
    background: linear-gradient(135deg, var(--c3), var(--c1));
    color: #fff;
    border: none;
    border-radius: 10px;
    padding: .7rem;
    font-size: .9rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    box-shadow: 0 4px 14px rgba(198, 113, 36, .3);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .4rem;
    transition: all .2s;
    margin-top: .3rem;
  }

  .btn-guardar:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(198, 113, 36, .4);
  }

  .msg-ok {
    background: #e8f5e9;
    border: 1px solid #a5d6a7;
    border-left: 4px solid #2e7d32;
    border-radius: 10px;
    padding: .7rem 1rem;
    font-size: .8rem;
    color: #1b5e20;
    font-weight: 600;
    margin-bottom: .65rem;
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    line-height: 1.5;
  }

  .msg-ok i {
    flex-shrink: 0;
    font-size: .95rem;
    margin-top: .12rem;
  }

  .msg-ok span {
    flex: 1;
  }

  .msg-err {
    background: #ffebee;
    border: 1px solid #ef9a9a;
    border-left: 4px solid #c62828;
    border-radius: 10px;
    padding: .7rem 1rem;
    font-size: .8rem;
    color: #c62828;
    font-weight: 600;
    margin-bottom: .65rem;
    display: flex;
    align-items: flex-start;
    gap: .5rem;
    line-height: 1.5;
  }

  .msg-err i {
    flex-shrink: 0;
    font-size: .95rem;
    margin-top: .12rem;
  }

  .msg-err span {
    flex: 1;
  }

  /* Mode toggle */
  .mode-toggle {
    display: flex;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
    margin-bottom: .7rem;
  }

  .mode-btn {
    flex: 1;
    padding: .5rem;
    text-align: center;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    border: none;
    background: var(--clight);
    color: var(--ink3);
    font-family: inherit;
    transition: all .2s;
  }

  .mode-btn.active {
    background: linear-gradient(135deg, var(--c3), var(--c1));
    color: #fff;
  }

  /* Catalog cards */
  .price-tabs {
    display: flex;
    gap: .3rem;
    margin-bottom: .6rem;
    overflow-x: auto;
    padding-bottom: .2rem;
  }

  .price-tab {
    padding: .4rem .7rem;
    border: 2px solid var(--border);
    border-radius: 9px;
    font-size: .78rem;
    font-weight: 700;
    cursor: pointer;
    background: var(--clight);
    color: var(--ink2);
    white-space: nowrap;
    font-family: 'Fraunces', serif;
    transition: all .2s;
  }

  .price-tab:hover {
    border-color: var(--c3);
  }

  .price-tab.active {
    border-color: var(--c3);
    background: rgba(198, 113, 36, .1);
    color: var(--c3);
  }

  .prod-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(115px, 1fr));
    gap: .5rem;
    margin-bottom: .6rem;
    max-height: 220px;
    overflow-y: auto;
    padding: .2rem;
  }

  .prod-card {
    border: 1.5px solid var(--border);
    border-radius: 11px;
    overflow: hidden;
    background: #fff;
    cursor: pointer;
    transition: all .25s;
    text-align: center;
  }

  .prod-card:hover {
    border-color: var(--c3);
    box-shadow: 0 3px 12px rgba(198, 113, 36, .12);
    transform: translateY(-2px);
  }

  .prod-card.in-cart {
    border-color: #2e7d32;
    background: rgba(46, 125, 50, .04);
  }

  .prod-card img {
    width: 100%;
    height: 80px;
    object-fit: cover;
    display: block;
  }

  .prod-card .pc-placeholder {
    width: 100%;
    height: 80px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, rgba(198, 113, 36, .06), rgba(198, 113, 36, .02));
    font-size: 2.2rem;
  }

  .prod-card .pc-name {
    padding: .3rem .4rem;
    font-size: .7rem;
    font-weight: 700;
    color: var(--ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .prod-card .pc-action {
    padding: 0 .4rem .4rem;
    font-size: .6rem;
    color: var(--c3);
    font-weight: 700;
    cursor: pointer;
  }

  .prod-card.in-cart .pc-action {
    color: #2e7d32;
  }

  .prod-card.expanded {
    border-color: var(--c3);
    box-shadow: 0 4px 16px rgba(198, 113, 36, .2);
    transform: translateY(-2px);
    z-index: 2;
  }

  .prod-card .pc-form {
    display: none;
    padding: .4rem;
    border-top: 1px solid var(--border);
    background: var(--clight);
  }

  .prod-card.expanded .pc-form {
    display: block;
  }

  .pc-form .pf-row {
    display: flex;
    align-items: center;
    gap: .3rem;
    margin-bottom: .3rem;
  }

  .pc-form .pf-row label {
    font-size: .55rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--ink3);
    min-width: 32px;
  }

  .pc-form .pf-row input {
    width: 55px;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .25rem;
    text-align: center;
    font-size: .82rem;
    font-family: 'Fraunces', serif;
    font-weight: 700;
    background: #fff;
  }

  .pc-form .pf-row input:focus {
    outline: none;
    border-color: var(--c3);
  }

  .pc-form .pf-add {
    width: 100%;
    padding: .35rem;
    border: none;
    border-radius: 7px;
    background: linear-gradient(135deg, var(--c3), var(--c1));
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    cursor: pointer;
    font-family: inherit;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: .25rem;
    transition: all .15s;
  }

  .pc-form .pf-add:hover {
    transform: scale(1.02);
    box-shadow: 0 2px 8px rgba(198, 113, 36, .3);
  }

  /* Cart */
  .cart-section {
    border-top: 1px dashed var(--border);
    padding-top: .6rem;
    margin-top: .4rem;
  }

  .cart-title {
    font-size: .62rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .14em;
    color: var(--ink3);
    margin-bottom: .4rem;
    display: flex;
    align-items: center;
    gap: .35rem;
  }

  .cart-title .cart-badge {
    background: var(--c3);
    color: #fff;
    font-size: .55rem;
    padding: .1rem .4rem;
    border-radius: 10px;
  }

  .cart-empty {
    text-align: center;
    padding: .7rem;
    font-size: .75rem;
    color: var(--ink3);
    border: 1.5px dashed var(--border);
    border-radius: 9px;
  }

  .cart-list {
    display: flex;
    flex-direction: column;
    gap: .3rem;
    max-height: 180px;
    overflow-y: auto;
  }

  .cart-item {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .4rem .5rem;
    border: 1px solid var(--border);
    border-radius: 9px;
    background: var(--clight);
    animation: fadeUp .2s ease;
  }

  .cart-item img {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
  }

  .cart-item .ci-ph {
    width: 32px;
    height: 32px;
    border-radius: 6px;
    background: rgba(198, 113, 36, .08);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .9rem;
    flex-shrink: 0;
  }

  .cart-item .ci-info {
    flex: 1;
    min-width: 0;
  }

  .cart-item .ci-name {
    font-size: .72rem;
    font-weight: 700;
    color: var(--ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  .cart-item .ci-price {
    font-size: .6rem;
    color: var(--ink3);
  }

  .cart-item .ci-fields {
    display: flex;
    gap: .3rem;
    align-items: center;
  }

  .cart-item .ci-fields input {
    width: 46px;
    border: 1px solid var(--border);
    border-radius: 6px;
    padding: .2rem;
    text-align: center;
    font-size: .78rem;
    font-family: 'Fraunces', serif;
    font-weight: 700;
  }

  .cart-item .ci-fields input:focus {
    outline: none;
    border-color: var(--c3);
  }

  .cart-item .ci-fields label {
    font-size: .48rem;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--ink3);
  }

  .cart-item .ci-sub {
    font-family: 'Fraunces', serif;
    font-size: .72rem;
    font-weight: 700;
    color: #2e7d32;
    min-width: 55px;
    text-align: right;
  }

  .cart-item .ci-del {
    width: 22px;
    height: 22px;
    border-radius: 5px;
    border: 1px solid rgba(198, 40, 40, .2);
    background: none;
    color: #c62828;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .65rem;
    flex-shrink: 0;
  }

  .cart-item .ci-del:hover {
    background: rgba(198, 40, 40, .08);
  }

  .cart-total {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: .5rem .6rem;
    margin-top: .4rem;
    background: linear-gradient(135deg, rgba(46, 125, 50, .08), rgba(46, 125, 50, .03));
    border: 1px solid rgba(46, 125, 50, .2);
    border-radius: 9px;
    font-size: .8rem;
    font-weight: 700;
    color: #1b5e20;
  }

  .cart-total .ct-big {
    font-family: 'Fraunces', serif;
    font-size: 1.1rem;
  }

  .bonif-row {
    display: flex;
    align-items: center;
    gap: .4rem;
    padding: .3rem .4rem;
    border-radius: 7px;
    margin-bottom: .2rem;
    background: rgba(21, 101, 192, .04);
  }

  .bonif-row img {
    width: 28px;
    height: 28px;
    border-radius: 5px;
    object-fit: cover;
    flex-shrink: 0;
  }

  .bonif-row .br-ph {
    width: 28px;
    height: 28px;
    border-radius: 5px;
    background: rgba(21, 101, 192, .1);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: .7rem;
    flex-shrink: 0;
  }

  .bonif-row .br-name {
    flex: 1;
    font-size: .72rem;
    font-weight: 600;
    color: var(--ink);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    min-width: 0;
  }

  .bonif-row .br-cat {
    font-size: .55rem;
    color: #64b5f6;
    font-weight: 700;
  }

  .bonif-row input {
    width: 45px;
    border: 1px solid rgba(21, 101, 192, .2);
    border-radius: 5px;
    padding: .2rem;
    text-align: center;
    font-size: .78rem;
    font-family: 'Fraunces', serif;
    font-weight: 700;
    background: #fff;
    flex-shrink: 0;
  }

  .bonif-row input:focus {
    outline: none;
    border-color: #1565c0;
  }

  /* Table */
  .tbl-wrap {
    overflow-y: auto;
    flex: 1;
    min-height: 0;
  }

  .gt {
    width: 100%;
    border-collapse: collapse;
  }

  .gt th {
    font-size: .61rem;
    text-transform: uppercase;
    letter-spacing: .1em;
    color: var(--ink3);
    font-weight: 700;
    padding: .5rem .9rem;
    background: var(--clight);
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 0;
    z-index: 1;
  }

  .gt td {
    font-size: .82rem;
    color: var(--ink);
    padding: .5rem .9rem;
    border-bottom: 1px solid rgba(148, 91, 53, .05);
    vertical-align: middle;
  }

  .gt tr:hover td {
    background: rgba(250, 243, 234, .5);
  }

  .gt tfoot td {
    background: var(--clight);
    padding: .6rem .9rem;
    font-weight: 800;
    border-top: 1.5px solid var(--border);
  }

  .tag-tipo {
    font-size: .6rem;
    font-weight: 700;
    padding: .15rem .45rem;
    border-radius: 20px;
    display: inline-flex;
    align-items: center;
    gap: .25rem;
  }

  .tag-venta {
    background: #e8f5e9;
    color: #2e7d32;
    border: 1px solid #a5d6a7;
  }

  .tag-bonif {
    background: #e3f2fd;
    color: #1565c0;
    border: 1px solid #90caf9;
  }

  .tag-consumo {
    background: #fff3e0;
    color: #e65100;
    border: 1px solid #ffcc80;
  }

  .btn-act {
    width: 28px;
    height: 28px;
    border-radius: 7px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border);
    background: var(--clight);
    color: var(--ink3);
    font-size: .82rem;
    cursor: pointer;
    text-decoration: none;
    transition: all .15s;
  }

  .btn-edit {
    color: #1565c0;
    background: rgba(21, 101, 192, .08);
  }

  .btn-edit:hover {
    background: #1565c0;
    color: #fff;
  }

  .btn-del {
    color: #c62828;
    background: rgba(198, 40, 40, .06);
  }

  .btn-del:hover {
    background: #c62828;
    color: #fff;
  }

  .empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: .5rem;
    padding: 3rem 1rem;
    color: var(--ink3);
    font-size: .82rem;
    text-align: center;
    flex: 1;
  }

  .empty i {
    font-size: 2.2rem;
    opacity: .3;
  }

  @media(max-width:900px) {
    .page {
      height: auto;
      overflow: visible;
      margin-top: 60px;
      padding: .5rem;
    }

    .g-body {
      grid-template-columns: 1fr;
    }

    .wc-pills {
      gap: .35rem;
    }
  }
</style>

<div class="page">
  <div class="wc-banner">
    <div class="wc-left">
      <div>
        <div class="wc-greeting">Panadería BreadControl</div>
        <div class="wc-name">Módulo de <em>Ventas</em></div>
        <div class="wc-sub">Registro de salidas del día · <?= date('d/m/Y') ?></div>
      </div>
    </div>
    <div class="wc-pills">
      <div class="wc-pill <?= $total_ventas > 0 ? 'ok' : '' ?>">
        <div class="wc-pill-num">$<?= number_format($total_ventas / 1000, 1) ?>k</div>
        <div class="wc-pill-lbl">Ventas</div>
      </div>
      <div class="wc-pill">
        <div class="wc-pill-num"><?= $und_ventas ?></div>
        <div class="wc-pill-lbl">Und.</div>
      </div>
      <?php if ($diff_pct !== null): ?>
        <div class="wc-pill <?= $diff_pct >= 0 ? 'ok' : '' ?>">
          <div class="wc-pill-num"><?= ($diff_pct >= 0 ? '+' : '') . $diff_pct ?>%</div>
          <div class="wc-pill-lbl">vs ayer</div>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="topbar">
    <div class="mod-titulo"><i class="bi bi-bag-fill"></i> Ventas</div>
    <div class="top-actions">
      <a href="clientes.php" class="btn-sec"><i class="bi bi-shop"></i> Tiendas</a>
      <a href="<?= APP_URL ?>/modules/recetas/variedades.php" class="btn-sec"><i class="bi bi-list-stars"></i>
        Variedades</a>
    </div>
  </div>

  <div class="g-body">
    <!-- ══ PANEL IZQUIERDO ══ -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-nar"><i class="bi bi-cart-plus-fill"></i></div><span class="ch-title">Nueva
            salida</span>
        </div>
      </div>
      <div class="form-body">
        <?php if ($msg_ok): ?>
          <div class="msg-ok"><i class="bi bi-check-circle-fill"></i><span><?= $msg_ok ?></span></div><?php endif; ?>
        <?php if ($msg_err): ?>
          <div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i><span><?= $msg_err ?></span></div>
        <?php endif; ?>

        <!-- Mode toggle -->
        <div class="mode-toggle">
          <button type="button" class="mode-btn active" id="mode-rapido" onclick="switchMode('rapido')"><i
              class="bi bi-lightning-charge-fill"></i> Venta rápida</button>
          <button type="button" class="mode-btn" id="mode-detalle" onclick="switchMode('detalle')"><i
              class="bi bi-cart4"></i> Detallar pedido</button>
        </div>

        <!-- ══ MODO RÁPIDO ══ -->
        <div id="panel-rapido">
          <form method="POST" id="form-venta">
            <input type="hidden" name="id_categoria" id="inp-cat" value="">
            <input type="hidden" name="tipo_salida" id="inp-tipo" value="venta">
            <input type="hidden" name="precio_custom" id="inp-precio-custom" value="0">
            <div class="sec-sep">¿Qué tipo de pan?</div>
            <div class="fl">
              <div class="cat-grid">
                <?php foreach ($categorias as $c):
                  $stk = max(0, (int) $c['stock_hoy']);
                  $stk_class = $stk > 20 ? 'stk-ok' : ($stk > 0 ? 'stk-warn' : 'stk-zero');
                  ?>
                  <div class="cat-btn" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
                    data-stock="<?= $stk ?>" onclick="selCat(this)">
                    <div class="cat-price">$<?= number_format($c['precio_unitario'], 0, ',', '.') ?></div>
                    <div class="cat-stock <?= $stk_class ?>"><?= $stk ?> disp.</div>
                  </div>
                <?php endforeach; ?>
              </div>
              <div style="margin-top:.4rem;">
                <div class="cat-btn" id="cat-custom" onclick="toggleCustom()">
                  <div class="cat-price" style="font-size:.85rem;">✏️ Otro precio</div>
                </div>
                <div id="custom-input" style="display:none;margin-top:.35rem;">
                  <input type="number" id="inp-custom-precio" min="100" max="20000" step="100" placeholder="Ej: 1500"
                    style="width:100%;border:1px solid var(--border);border-radius:9px;padding:.5rem;font-size:.9rem;font-family:'Fraunces',serif;font-weight:700;text-align:center;background:var(--clight);"
                    oninput="setCustomPrice()">
                </div>
              </div>
            </div>
            <div class="sec-sep">¿Cuánto?</div>
            <div class="fl-row">
              <div class="fl"><label>Cantidad</label><input type="number" id="inp-cantidad" name="cantidad" min="1" max="999"
                  step="1" placeholder="Ej: 40" oninput="if(this.value>999)this.value=999;calcTotal()"></div>
              <div class="fl"><label>O monto total ($)</label><input type="number" id="inp-monto" min="1" step="1" maxlength="5"
                  placeholder="Ej: 20000" oninput="if(this.value.length>5)this.value=this.value.slice(0,5);calcFromMonto()"></div>
            </div>
            <div class="total-display" id="total-box" style="display:none;">
              <div class="total-lbl" id="total-lbl">Total a cobrar</div>
              <div class="total-val" id="total-val">$0</div>
              <div class="total-und" id="total-und">0 unidades</div>
            </div>
            <div class="sec-sep">Tipo de salida</div>
            <div class="fl">
              <div class="tipo-grid">
                <div class="tipo-btn active" data-tipo="venta" onclick="selTipo(this)"><i class="bi bi-cash-coin"></i>
                  Venta</div>
                <div class="tipo-btn" data-tipo="consumo_interno" onclick="selTipo(this)"><i class="bi bi-cup-hot"></i>
                  Consumo</div>
              </div>
            </div>
            <div id="wrap-cliente">
              <div class="fl"><label>Cliente</label>
                <select name="id_cliente" id="sel-cliente" onchange="calcTotal()">
                  <option value="0">Mostrador</option>
                  <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre']) ?> 🏪
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Bonificación tienda (auto) -->
            <div id="bonif-preview"
              style="display:none;background:rgba(21,101,192,.06);border:1px solid rgba(21,101,192,.18);border-radius:10px;padding:.5rem .75rem;margin-bottom:.5rem;font-size:.78rem;color:#1565c0;text-align:center;">
              🏪 Tienda: +<strong id="bonif-cant">0</strong> unidades bonificadas = <strong id="bonif-total">0</strong>
              entregadas
            </div>
            <!-- Ñapa mostrador (auto) -->
            <div id="napa-preview"
              style="display:none;background:rgba(46,125,50,.06);border:1px solid rgba(46,125,50,.18);border-radius:10px;padding:.5rem .75rem;margin-bottom:.5rem;font-size:.78rem;color:#2e7d32;text-align:center;">
              🎁 Ñapa: +<strong id="napa-auto-cant">0</strong> unidades de regalo
            </div>
            <!-- Hidden inputs for form POST -->
            <input type="hidden" name="dar_napa" value="1" id="chk-napa-hidden">
            <input type="hidden" name="napa_cantidad" id="inp-napa" value="0">
            <button type="submit" name="guardar_venta" class="btn-guardar"><i class="bi bi-check-lg"></i>
              Registrar</button>
          </form>
        </div>

        <!-- ══ MODO DETALLE (CARRITO) ══ -->
        <div id="panel-detalle" style="display:none;">
          <form method="POST" id="form-pedido">
            <input type="hidden" name="carrito_json" id="carrito-json" value="[]">

            <div class="sec-sep">Selecciona el precio</div>
            <div class="price-tabs" id="price-tabs">
              <?php foreach ($categorias as $c): ?>
                <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
                  onclick="selPriceTab(this)">
                  $<?= number_format($c['precio_unitario'], 0, ',', '.') ?>
                </div>
              <?php endforeach; ?>
            </div>

            <div class="sec-sep">Toca un pan para agregarlo</div>
            <div id="prod-catalog">
              <div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio
                arriba</div>
            </div>

            <div class="cart-section">
              <div class="cart-title">🛒 Carrito <span class="cart-badge" id="cart-count">0</span></div>
              <div id="cart-body">
                <div class="cart-empty">Agrega productos desde el catálogo</div>
              </div>
              <div id="cart-total-bar" style="display:none;">
                <div class="cart-total">
                  <span>Cobrados: <strong id="ct-und">0</strong> · Ñapa: <strong id="ct-napa">0</strong></span>
                  <span class="ct-big">$<span id="ct-total">0</span></span>
                </div>
              </div>
            </div>

            <!-- Bonificación tienda / Ñapa mostrador -->
            <div id="bonif-panel" style="display:none;margin-top:.5rem;">
              <div id="bonif-card"
                style="border-radius:10px;padding:.7rem .85rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
                <div
                  style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;flex-wrap:wrap;gap:.3rem;">
                  <span id="bonif-titulo"
                    style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪
                    Bonificación tienda</span>
                  <span id="bonif-credito-lbl" style="font-size:.78rem;font-weight:700;color:#1565c0;">Crédito:
                    <strong>$<span id="bonif-credito">0</span></strong></span>
                </div>
                <div style="font-size:.68rem;color:#1565c0;margin-bottom:.4rem;" id="bonif-hint">
                  Escoge qué panes quiere la tienda. Por defecto $500 (2 panes c/$5.000).
                </div>
                <div id="bonif-varieties" style="max-height:180px;overflow-y:auto;margin-bottom:.4rem;">
                  <div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Cargando variedades...
                  </div>
                </div>
                <div id="bonif-status"
                  style="font-size:.75rem;font-weight:700;text-align:center;padding:.3rem;border-radius:7px;"></div>
              </div>
            </div>
            <input type="hidden" name="bonif_json" id="bonif-json" value="[]">

            <div style="margin-top:.6rem;">
              <div class="sec-sep">Cliente</div>
              <div class="fl">
                <select name="ped_cliente" id="ped-cliente">
                  <option value="0">Mostrador</option>
                  <?php foreach ($clientes as $cl): ?>
                    <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre']) ?> 🏪
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <button type="submit" name="guardar_pedido" class="btn-guardar" id="btn-pedido" disabled>
              <i class="bi bi-bag-check-fill"></i> Registrar pedido
            </button>
          </form>
        </div>
      </div>
    </div>

    <!-- ══ TABLA ══ -->
    <div class="card">
      <div class="ch">
        <div class="ch-left">
          <div class="ch-ico ico-grn"><i class="bi bi-list-ul"></i></div><span class="ch-title">Registros de hoy</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
          <span class="badge b-neu"><?= count($registros_hoy) ?></span>
        </div>
      </div>
      <?php if (empty($registros_hoy)): ?>
        <div class="empty"><i class="bi bi-bag-x"></i><strong>Sin registros hoy</strong></div>
      <?php else: ?>
        <div class="tbl-wrap">
          <form method="POST" action="exportar_excel.php" id="form-exportar" target="_blank">
            <div
              style="padding: 0.5rem 1rem; border-bottom: 1px solid var(--border); background: var(--clight); display:flex; gap: 0.5rem; justify-content: flex-end;">
              <button type="submit" class="btn-sec" style="font-size:0.75rem; padding: 0.3rem 0.6rem;"><i
                  class="bi bi-file-earmark-excel-fill" style="color:#2e7d32;"></i> Exportar a Excel</button>
            </div>
            <table class="gt">
              <thead>
                <tr>
                  <th style="width:30px;"><input type="checkbox" id="chk-all"
                      onclick="document.querySelectorAll('.chk-export').forEach(c => c.checked = this.checked)"></th>
                  <th>Hora</th>
                  <th>Tipo</th>
                  <th>Categoría</th>
                  <th style="text-align:center">Und.</th>
                  <th style="text-align:right">Total</th>
                  <th>Cliente</th>
                  <th style="text-align:center">Bonif.</th>
                  <th style="text-align:center">—</th>
                </tr>
              </thead>
              <tbody>
                <?php
                $tt_map = ['venta' => ['tag-venta', 'bi-cash-coin', 'Venta'], 'bonificacion' => ['tag-bonif', 'bi-gift', 'Bonif.'], 'consumo_interno' => ['tag-consumo', 'bi-cup-hot', 'Consumo']];
                foreach ($registros_hoy as $r):
                  $tt = $tt_map[$r['tipo_salida']] ?? $tt_map['venta'];
                  ?>
                  <tr>
                    <td><input type="checkbox" name="exportar_ids[]" value="<?= $r['id_venta'] ?>" class="chk-export"
                        checked></td>
                    <td style="color:var(--ink3);font-size:.75rem;white-space:nowrap">
                      <?= date('h:i a', strtotime($r['fecha_hora'])) ?></td>
                    <td><span class="tag-tipo <?= $tt[0] ?>"><i class="bi <?= $tt[1] ?>"></i> <?= $tt[2] ?></span></td>
                    <td style="font-weight:600"><?= htmlspecialchars($r['categoria'] ?? 'Pedido detallado') ?></td>
                    <td style="text-align:center;font-family:'Fraunces',serif;font-weight:700;">
                      <?= $r['unidades_vendidas'] ?></td>
                    <td
                      style="text-align:right;font-weight:700;font-family:'Fraunces',serif;<?= $r['tipo_salida'] !== 'venta' ? 'color:var(--ink3)' : 'color:#1b5e20' ?>">
                      <?= $r['tipo_salida'] === 'venta' ? '$' . number_format($r['total_venta'], 0, ',', '.') : '—' ?></td>
                    <td style="font-size:.78rem;color:var(--ink3)">
                      <?= $r['tipo_salida'] === 'venta' ? htmlspecialchars($r['cliente']) : '—' ?></td>
                    <td style="text-align:center;font-size:.78rem;">
                      <?php if ($r['bonificacion'] > 0): ?>      <?= $r['cliente'] !== 'Mostrador' ? '<span style="color:#1565c0;font-weight:700;">+' . $r['bonificacion'] . ' 🏪</span>' : '<span style="color:#c67124;font-weight:700;">+' . $r['bonificacion'] . ' 🎁</span>' ?>    <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                      <div style="display:flex;gap:.25rem;justify-content:center;">
                        <?php $tiene_detalle = in_array($r['id_venta'], $ventas_con_detalle); ?>
                        <?php if ($tiene_detalle): ?>
                          <button type="button" class="btn-act btn-edit" title="Editar"
                            onclick="editarPedido(<?= $r['id_venta'] ?>)"><i class="bi bi-pencil"></i></button>
                        <?php else: ?>
                          <button type="button" class="btn-act btn-edit" title="Editar"
                            onclick="abrirEdit(<?= $r['id_venta'] ?>,<?= $r['id_categoria_precio'] ?? 0 ?>,'<?= $r['tipo_salida'] ?>',<?= $r['unidades_vendidas'] ?>,<?= $r['id_cliente'] ?? 0 ?>,<?= (int) $r['bonificacion'] ?>)"><i
                              class="bi bi-pencil"></i></button>
                        <?php endif; ?>
                        <a href="?del_venta=<?= $r['id_venta'] ?>" class="btn-act btn-del" title="Eliminar"
                          onclick="return confirm('¿Eliminar?')"><i class="bi bi-trash3"></i></a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
              <tfoot>
                <tr>
                  <td colspan="4" style="font-weight:700;">Total ventas</td>
                  <td style="text-align:center;font-family:'Fraunces',serif;"><?= $und_ventas ?></td>
                  <td style="text-align:right;font-family:'Fraunces',serif;font-size:1rem;color:#1b5e20;">
                    $<?= number_format($total_ventas, 0, ',', '.') ?></td>
                  <td colspan="3"></td>
                </tr>
              </tfoot>
            </table>
          </form>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Modal editar -->
<div id="modal-edit"
  style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div
    style="background:#fff;border-radius:14px;padding:1.4rem;width:90%;max-width:420px;box-shadow:0 12px 40px rgba(0,0,0,.2);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
      <strong><i class="bi bi-pencil-square" style="color:var(--c3);"></i> Editar</strong>
      <button onclick="cerrarEdit()"
        style="background:none;border:none;font-size:1.2rem;cursor:pointer;">&times;</button>
    </div>
    <form method="POST">
      <input type="hidden" name="id_venta" id="ev-id">
      <input type="hidden" name="ev_cliente_anterior" id="ev-cli-prev" value="0">
      <input type="hidden" name="ev_unid_anteriores" id="ev-und-prev" value="0">
      <div style="margin-bottom:.6rem;"><label style="font-size:.7rem;font-weight:700;">Categoría</label>
        <select name="ev_categoria" id="ev-cat" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <?php foreach ($categorias as $c): ?>
            <option value="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>">
              $<?= number_format($c['precio_unitario'], 0, ',', '.') ?></option><?php endforeach; ?>
        </select>
      </div>
      <div style="margin-bottom:.6rem;"><label style="font-size:.7rem;font-weight:700;">Cantidad cobrada</label>
        <input type="number" name="ev_cantidad" id="ev-cant" min="1" max="999" required oninput="if(this.value>999)this.value=999;evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;box-sizing:border-box;">
      </div>
      <div style="margin-bottom:.6rem;"><label style="font-size:.7rem;font-weight:700;">Tipo</label>
        <select name="ev_tipo" id="ev-tipo" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="venta">Venta</option>
          <option value="bonificacion">Bonificación</option>
          <option value="consumo_interno">Consumo</option>
        </select>
      </div>
      <div style="margin-bottom:.6rem;"><label style="font-size:.7rem;font-weight:700;">Cliente</label>
        <select name="ev_cliente" id="ev-cli" onchange="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="0" data-tipo="mostrador">Mostrador</option>
          <?php foreach ($clientes as $cl): ?>
            <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre']) ?> 🏪</option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Previa dinámica de bonificación / ñapa -->
      <div id="ev-preview"
        style="display:none;margin-bottom:.7rem;border-radius:9px;padding:.55rem .7rem;font-size:.76rem;text-align:center;line-height:1.4;">
      </div>

      <!-- Ajuste extra (cuando faltan unidades al cambiar cliente) -->
      <div id="ev-extra-wrap"
        style="display:none;margin-bottom:.7rem;padding:.55rem .7rem;border:1px dashed rgba(21,101,192,.35);background:rgba(21,101,192,.05);border-radius:9px;">
        <label
          style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:#1565c0;display:block;margin-bottom:.25rem;">Agregar
          unidades extra (bonificación)</label>
        <div style="font-size:.7rem;color:#1565c0;margin-bottom:.3rem;" id="ev-extra-hint">Antes era mostrador. Si
          quieres entregar más panes ahora que es tienda, súmalos aquí.</div>
        <input type="number" name="ev_extra_bonif" id="ev-extra" min="0" value="0" oninput="evRecalc()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;box-sizing:border-box;text-align:center;font-family:'Fraunces',serif;font-weight:700;">
      </div>

      <button type="submit" name="editar_venta"
        style="width:100%;padding:.55rem;background:var(--c3);color:#fff;border:none;border-radius:9px;font-weight:700;cursor:pointer;font-family:inherit;"><i
          class="bi bi-check-lg"></i> Guardar</button>
    </form>
  </div>
</div>

<!-- Modal editar pedido detallado -->
<div id="modal-edit-pedido"
  style="display:none;position:fixed;inset:0;z-index:2000;background:rgba(0,0,0,.5);align-items:center;justify-content:center;overflow-y:auto;">
  <div
    style="background:#fff;border-radius:14px;padding:1.2rem;width:95%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 12px 40px rgba(0,0,0,.25);margin:1rem auto;">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem;">
      <strong style="font-size:1rem;"><i class="bi bi-pencil-square" style="color:var(--c3)"></i> Editar pedido
        detallado</strong>
      <button onclick="cerrarEditPedido()"
        style="background:none;border:none;font-size:1.3rem;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" id="form-edit-pedido">
      <input type="hidden" name="edit_id_venta" id="ep-id">
      <input type="hidden" name="edit_carrito_json" id="ep-carrito-json" value="[]">

      <div
        style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin-bottom:.4rem;">
        Selecciona precio</div>
      <div class="price-tabs" id="ep-price-tabs" style="margin-bottom:.6rem;">
        <?php foreach ($categorias as $c): ?>
          <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>"
            onclick="epSelPrice(this)">
            $<?= number_format($c['precio_unitario'], 0, ',', '.') ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div id="ep-catalog" style="margin-bottom:.5rem;">
        <div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio</div>
      </div>

      <div
        style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin:.4rem 0;">
        🛒 Carrito <span class="cart-badge" id="ep-cart-count">0</span></div>
      <div id="ep-cart-body">
        <div class="cart-empty">Agrega productos</div>
      </div>
      <div id="ep-cart-total" style="display:none;" class="cart-total">
        <span>Cobrados: <strong id="ep-ct-und">0</strong> · Ñapa: <strong id="ep-ct-napa">0</strong></span>
        <span class="ct-big">$<span id="ep-ct-total">0</span></span>
      </div>

      <!-- Panel de bonificación/ñapa para edición -->
      <div id="ep-bonif-panel" style="display:none;margin-top:.5rem;">
        <div id="ep-bonif-card"
          style="border-radius:10px;padding:.6rem .75rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
          <div
            style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.4rem;flex-wrap:wrap;gap:.25rem;">
            <span id="ep-bonif-titulo"
              style="font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪
              Bonificación tienda</span>
            <span style="font-size:.74rem;font-weight:700;color:#1565c0;">Crédito: <strong>$<span
                  id="ep-bonif-credito">0</span></strong></span>
          </div>
          <div id="ep-bonif-hint" style="font-size:.66rem;color:#1565c0;margin-bottom:.35rem;">Escoge qué panes entregar
            como bonificación.</div>
          <div id="ep-bonif-varieties" style="max-height:150px;overflow-y:auto;margin-bottom:.35rem;">
            <div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Cargando...</div>
          </div>
          <div id="ep-bonif-status"
            style="font-size:.72rem;font-weight:700;text-align:center;padding:.25rem;border-radius:7px;"></div>
        </div>
      </div>
      <input type="hidden" name="edit_bonif_json" id="ep-bonif-json" value="[]">

      <div style="margin-top:.6rem;">
        <div
          style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--ink3);margin-bottom:.3rem;">
          Cliente</div>
        <select name="edit_ped_cliente" id="ep-cliente" onchange="epCheckBonif()"
          style="width:100%;padding:.4rem;border:1px solid var(--border);border-radius:8px;">
          <option value="0" data-tipo="mostrador">Mostrador</option>
          <?php foreach ($clientes as $cl): ?>
            <option value="<?= $cl['id_cliente'] ?>" data-tipo="tienda"><?= htmlspecialchars($cl['nombre']) ?> 🏪</option>
          <?php endforeach; ?>
        </select>
      </div>

      <button type="submit" name="editar_pedido" class="btn-guardar" id="ep-btn-save" style="margin-top:.7rem;"
        disabled>
        <i class="bi bi-check-lg"></i> Guardar cambios
      </button>
    </form>
  </div>
</div>

<script>
  var appUrl = '<?= APP_URL ?>';
  var precioSel = 0, stockSel = 0;
  var cart = [];

  // ══ MODE TOGGLE ══
  function switchMode(mode) {
    document.getElementById('mode-rapido').classList.toggle('active', mode === 'rapido');
    document.getElementById('mode-detalle').classList.toggle('active', mode === 'detalle');
    document.getElementById('panel-rapido').style.display = mode === 'rapido' ? 'block' : 'none';
    document.getElementById('panel-detalle').style.display = mode === 'detalle' ? 'block' : 'none';
  }

  // ══ QUICK MODE ══
  function toggleCustom() { var ci = document.getElementById('custom-input'); var cc = document.getElementById('cat-custom'); if (ci.style.display === 'none') { ci.style.display = 'block'; cc.classList.add('active'); document.querySelectorAll('.cat-btn:not(#cat-custom)').forEach(function (b) { b.classList.remove('active') }); document.getElementById('inp-custom-precio').focus(); } else { ci.style.display = 'none'; cc.classList.remove('active'); } }
  function setCustomPrice() { var val = parseFloat(document.getElementById('inp-custom-precio').value) || 0; if (val > 20000) { val = 20000; document.getElementById('inp-custom-precio').value = 20000; } if (val > 0) { document.getElementById('inp-cat').value = ''; document.getElementById('inp-precio-custom').value = val; precioSel = val; stockSel = 9999; calcTotal(); } }
  function selCat(el) { document.querySelectorAll('.cat-btn').forEach(function (b) { b.classList.remove('active') }); el.classList.add('active'); document.getElementById('custom-input').style.display = 'none'; document.getElementById('inp-precio-custom').value = '0'; document.getElementById('inp-cat').value = el.dataset.id; precioSel = parseFloat(el.dataset.precio); stockSel = parseInt(el.dataset.stock) || 0; calcTotal(); }
  function toggleNapa() { var chk = document.getElementById('chk-napa'); document.getElementById('napa-input').style.display = chk.checked ? 'block' : 'none'; calcTotal(); }
  function selTipo(el) { document.querySelectorAll('.tipo-btn').forEach(function (b) { b.classList.remove('active') }); el.classList.add('active'); var tipo = el.dataset.tipo; document.getElementById('inp-tipo').value = tipo; document.getElementById('wrap-cliente').style.display = tipo === 'venta' ? 'block' : 'none'; document.getElementById('napa-preview').style.display = 'none'; document.getElementById('inp-napa').value = 0; calcTotal(); }
  var _updating = false;
  // === Núcleo: dada la cantidad ya fijada, recalcula total, ñapa y bonificación ===
  function recalcPreview() {
    var cant = parseInt(document.getElementById('inp-cantidad').value) || 0;
    var box = document.getElementById('total-box');
    var bp = document.getElementById('bonif-preview');
    var np = document.getElementById('napa-preview');
    if (!(cant > 0 && precioSel > 0)) {
      box.style.display = 'none';
      bp.style.display = 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = 0;
      return;
    }
    var tipo = document.getElementById('inp-tipo').value;
    var total = tipo === 'venta' ? cant * precioSel : 0;
    document.getElementById('total-val').textContent = tipo === 'venta' ? '$' + total.toLocaleString('es-CO') : '$0';
    document.getElementById('total-und').textContent = cant + ' und × $' + precioSel.toLocaleString('es-CO');
    box.style.display = 'block';
    // Solo aplica ñapa/bonificación si es venta
    var sel = document.getElementById('sel-cliente');
    var opt = sel ? sel.options[sel.selectedIndex] : null;
    if (tipo === 'venta' && opt && opt.dataset.tipo === 'tienda') {
      var tv = cant * precioSel;
      var cr = Math.floor(tv / 5000) * 1000;
      var na = (precioSel > 0) ? Math.floor(cr / precioSel) : 0;
      document.getElementById('bonif-cant').textContent = na;
      document.getElementById('bonif-total').textContent = (cant + na);
      bp.style.display = (na > 0) ? 'block' : 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = na;
    } else if (tipo === 'venta' && (!opt || opt.value === '0')) {
      bp.style.display = 'none';
      var tv = cant * precioSel;
      var cr = Math.floor(tv / 5000) * 500;
      var na = (precioSel > 0) ? Math.floor(cr / precioSel) : 0;
      if (na > 0) { document.getElementById('napa-auto-cant').textContent = na; np.style.display = 'block'; } else { np.style.display = 'none'; }
      document.getElementById('inp-napa').value = na;
    } else {
      bp.style.display = 'none';
      np.style.display = 'none';
      document.getElementById('inp-napa').value = 0;
    }
  }
  function calcTotal() {
    if (_updating) return; _updating = true;
    // Si cambió la cantidad, sincronizo el monto
    var cant = parseInt(document.getElementById('inp-cantidad').value) || 0;
    if (cant > 0 && precioSel > 0) { document.getElementById('inp-monto').value = cant * precioSel; }
    recalcPreview();
    _updating = false;
  }
  function calcFromMonto() {
    if (_updating) return; _updating = true;
    var monto = parseInt(document.getElementById('inp-monto').value) || 0;
    if (precioSel > 0 && monto > 0) {
      var cant = Math.floor(monto / precioSel);
      document.getElementById('inp-cantidad').value = cant;
    }
    recalcPreview();
    _updating = false;
  }

  // ══ DETAIL MODE (CATALOG + CART) ══
  var currentPrice = 0;
  var currentCatId = 0;
  var catalogVars = [];

  function selPriceTab(el) {
    document.querySelectorAll('.price-tab').forEach(function (t) { t.classList.remove('active') });
    el.classList.add('active');
    currentCatId = parseInt(el.dataset.id);
    currentPrice = parseFloat(el.dataset.precio);
    loadCatalog(currentCatId);
  }

  function loadCatalog(catId) {
    var catalog = document.getElementById('prod-catalog');
    catalog.innerHTML = '<div style="text-align:center;padding:.8rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
    fetch('index.php?ajax_variedades=1&id_cat=' + catId)
      .then(function (r) { return r.json() })
      .then(function (vars) {
        catalogVars = vars;
        if (vars.length === 0) {
          catalog.innerHTML = '<div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Sin variedades para este precio.<br><a href="' + appUrl + '/modules/recetas/variedades.php" style="color:var(--c3);font-weight:600;">Crear variedades</a></div>';
          return;
        }
        var html = '<div class="prod-grid">';
        vars.forEach(function (v) {
          var inCart = cart.find(function (x) { return x.id_variedad == v.id_variedad });
          var cls = inCart ? 'prod-card in-cart' : 'prod-card';
          var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
          html += '<div class="' + cls + '" id="pcard-' + v.id_variedad + '">'
            + '<div onclick="tapProduct(' + v.id_variedad + ')">'
            + imgHtml
            + '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>'
            + '<div class="pc-name" title="' + v.nombre + '">' + v.nombre + '</div>'
            + '</div>'
            + '<div class="pc-form">'
            + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="999" value="1" onclick="event.stopPropagation()" oninput="if(this.value>999)this.value=999"></div>'
            + '<button type="button" class="pf-add" onclick="event.stopPropagation();addToCartFromCard(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
            + '</div>'
            + '</div>';
        });
        html += '</div>';
        catalog.innerHTML = html;
      });
  }

  function tapProduct(idVar) {
    if (cart.find(function (x) { return x.id_variedad == idVar })) return;
    // Toggle expanded state
    var pcard = document.getElementById('pcard-' + idVar);
    if (!pcard) return;
    // Close all other expanded cards
    document.querySelectorAll('.prod-card.expanded').forEach(function (c) { if (c !== pcard) c.classList.remove('expanded'); });
    pcard.classList.toggle('expanded');
    if (pcard.classList.contains('expanded')) {
      var inp = pcard.querySelector('.pf-cant');
      if (inp) { inp.value = 1; inp.focus(); inp.select(); }
    }
  }

  function addToCartFromCard(idVar) {
    var v = catalogVars.find(function (x) { return x.id_variedad == idVar });
    if (!v) return;
    var pcard = document.getElementById('pcard-' + idVar);
    var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
    if (cant <= 0) return;
    cart.push({ id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: currentPrice, cantidad: cant, napa: 0, catId: currentCatId });
    pcard.classList.remove('expanded');
    pcard.classList.add('in-cart');
    pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
    renderCart();
  }

  function removeFromCart(idVar) {
    cart = cart.filter(function (x) { return x.id_variedad != idVar });
    var pcard = document.getElementById('pcard-' + idVar);
    if (pcard) {
      pcard.classList.remove('in-cart');
      pcard.classList.remove('expanded');
      var action = pcard.querySelector('.pc-action');
      if (action) action.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Agregar';
    }
    renderCart();
  }

  function renderCart() {
    var body = document.getElementById('cart-body');
    var countEl = document.getElementById('cart-count');
    var totalBar = document.getElementById('cart-total-bar');
    var btnPedido = document.getElementById('btn-pedido');
    countEl.textContent = cart.length;

    if (cart.length === 0) {
      body.innerHTML = '<div class="cart-empty">Agrega productos desde el catálogo</div>';
      totalBar.style.display = 'none';
      btnPedido.disabled = true;
      document.getElementById('carrito-json').value = '[]';
      return;
    }

    var html = '<div class="cart-list">';
    var totalUnd = 0, totalNapa = 0, totalDinero = 0;
    cart.forEach(function (item) {
      var sub = item.cantidad * item.precio;
      totalUnd += item.cantidad;
      totalNapa += item.napa;
      totalDinero += sub;
      var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
      html += '<div class="cart-item">'
        + imgHtml
        + '<div class="ci-info"><div class="ci-name">' + item.nombre + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>'
        + '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" max="999" value="' + item.cantidad + '" onchange="updateCartItem(' + item.id_variedad + ',\'cantidad\',this.value)" oninput="if(this.value>999)this.value=999"></div>'
        + '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>'
        + '<button type="button" class="ci-del" onclick="removeFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
    });
    html += '</div>';
    body.innerHTML = html;

    document.getElementById('ct-und').textContent = totalUnd;
    document.getElementById('ct-napa').textContent = totalNapa;
    document.getElementById('ct-total').textContent = totalDinero.toLocaleString('es-CO');
    totalBar.style.display = 'block';
    btnPedido.disabled = false;
    document.getElementById('carrito-json').value = JSON.stringify(cart);
    checkBonifPanel();
  }

  function updateCartItem(idVar, field, val) {
    var item = cart.find(function (x) { return x.id_variedad == idVar });
    if (item) { item[field] = Math.max(1, parseInt(val) || 1); }
    renderCart();
  }

  // ══ BONIFICACIÓN TIENDA / ÑAPA MOSTRADOR ══
  var allVarieties = [];
  var bonifCredito = 0;   // crédito disponible en PESOS
  var bonifMode = 'tienda'; // 'tienda' o 'mostrador'
  var bonifModeAnterior = '';
  var bonifLoaded = false;

  function checkBonifPanel() {
    var sel = document.getElementById('ped-cliente');
    var opt = sel.options[sel.selectedIndex];
    var panel = document.getElementById('bonif-panel');
    var card = document.getElementById('bonif-card');
    var titulo = document.getElementById('bonif-titulo');
    var hint = document.getElementById('bonif-hint');

    if (cart.length === 0) {
      panel.style.display = 'none';
      bonifCredito = 0;
      document.getElementById('bonif-json').value = '[]';
      return;
    }

    // Calcular total cobrado en pesos
    var totalDinero = 0;
    cart.forEach(function (it) { totalDinero += it.cantidad * it.precio; });

    var modoAnterior = bonifMode;
    if (opt && opt.dataset.tipo === 'tienda') {
      // TIENDA: $1.000 de crédito por cada $5.000
      bonifMode = 'tienda';
      bonifCredito = Math.floor(totalDinero / 5000) * 1000;
      card.style.background = 'rgba(21,101,192,.06)';
      card.style.borderColor = 'rgba(21,101,192,.18)';
      titulo.style.color = '#1565c0';
      titulo.innerHTML = '🏪 Bonificación tienda';
      hint.style.color = '#1565c0';
      hint.innerHTML = 'Escoge qué panes quiere la tienda. Regla: $1.000 de crédito por cada $5.000 vendidos.';
    } else {
      // MOSTRADOR: $500 de crédito por cada $5.000
      bonifMode = 'mostrador';
      bonifCredito = Math.floor(totalDinero / 5000) * 500;
      card.style.background = 'rgba(198,113,36,.06)';
      card.style.borderColor = 'rgba(198,113,36,.2)';
      titulo.style.color = '#c67124';
      titulo.innerHTML = '🎁 Ñapa mostrador';
      hint.style.color = '#c67124';
      hint.innerHTML = 'Escoge qué pan(es) le das de ñapa. Regla: $500 de crédito por cada $5.000 vendidos.';
    }

    document.getElementById('bonif-credito').textContent = bonifCredito.toLocaleString('es-CO');

    if (bonifCredito <= 0) {
      panel.style.display = 'none';
      document.getElementById('bonif-json').value = '[]';
      return;
    }

    panel.style.display = 'block';
    if (!bonifLoaded) {
      loadAllVarieties();
    } else if (modoAnterior !== bonifMode) {
      // Cambió de tienda↔mostrador: re-render para refrescar colores y reiniciar valores
      renderBonifVarieties();
    } else {
      updateBonifStatus();
    }
  }

  function loadAllVarieties() {
    fetch('index.php?ajax_all_variedades=1')
      .then(function (r) { return r.json() })
      .then(function (vars) {
        allVarieties = vars;
        bonifLoaded = true;
        renderBonifVarieties();
      });
  }

  function renderBonifVarieties() {
    var container = document.getElementById('bonif-varieties');
    if (allVarieties.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Sin variedades registradas</div>';
      return;
    }
    var html = '';
    var currentCat = '';
    // Color secundario según modo
    var col = (bonifMode === 'tienda') ? '#1565c0' : '#c67124';
    var colSoft = (bonifMode === 'tienda') ? '#64b5f6' : '#e4a565';
    allVarieties.forEach(function (v) {
      if (v.cat_nombre !== currentCat) {
        currentCat = v.cat_nombre;
        html += '<div style="font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:' + colSoft + ';padding:.25rem .2rem .1rem;margin-top:.2rem;">' + currentCat + ' · $' + parseFloat(v.precio_unitario).toLocaleString('es-CO') + '</div>';
      }
      var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="br-ph">🍞</div>';
      html += '<div class="bonif-row">'
        + imgHtml
        + '<span class="br-name">' + v.nombre + '</span>'
        + '<input type="number" min="0" value="0" data-bonif-id="' + v.id_variedad + '" data-bonif-precio="' + v.precio_unitario + '" oninput="updateBonifStatus(this)">'
        + '</div>';
    });
    container.innerHTML = html;
    updateBonifStatus();
  }

  function updateBonifStatus(changedInput) {
    var inputs = document.querySelectorAll('#bonif-varieties [data-bonif-id]');

    // Calcular cuánto gastan los OTROS inputs (sin el que se está editando)
    if (changedInput) {
      var gastadoOtros = 0;
      inputs.forEach(function(inp) {
        if (inp !== changedInput) {
          var v2 = parseInt(inp.value) || 0;
          var p2 = parseFloat(inp.dataset.bonifPrecio) || 0;
          if (v2 > 0) gastadoOtros += v2 * p2;
        }
      });
      var pr = parseFloat(changedInput.dataset.bonifPrecio) || 0;
      var maxUnidades = pr > 0 ? Math.floor((bonifCredito - gastadoOtros) / pr) : 0;
      if (maxUnidades < 0) maxUnidades = 0;
      var cur = parseInt(changedInput.value) || 0;
      if (cur > maxUnidades) changedInput.value = maxUnidades;
      if (cur < 0) changedInput.value = 0;
      // Limpiar ceros extra (ej: "00" → "0")
      if (changedInput.value === '' || parseInt(changedInput.value) === 0) changedInput.value = 0;
    }

    var gastado = 0;
    var totalUnd = 0;
    var items = [];
    inputs.forEach(function (inp) {
      var val = parseInt(inp.value) || 0;
      var pr = parseFloat(inp.dataset.bonifPrecio) || 0;
      if (val > 0) {
        gastado += val * pr;
        totalUnd += val;
        items.push({ id_variedad: parseInt(inp.dataset.bonifId), cantidad: val, precio: pr });
      }
    });

    var status = document.getElementById('bonif-status');
    var pesosGastado = '$' + gastado.toLocaleString('es-CO');
    var pesosDisp = '$' + bonifCredito.toLocaleString('es-CO');
    if (gastado === bonifCredito) {
      status.textContent = '✅ ' + pesosGastado + '/' + pesosDisp + ' · ' + totalUnd + ' unid.';
      status.style.background = 'rgba(46,125,50,.1)';
      status.style.color = '#2e7d32';
    } else if (gastado > bonifCredito) {
      status.textContent = '⚠️ ' + pesosGastado + '/' + pesosDisp + ' — te pasas $' + (gastado - bonifCredito).toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)';
      status.style.color = '#c62828';
    } else {
      status.textContent = '📝 ' + pesosGastado + '/' + pesosDisp + ' — quedan $' + (bonifCredito - gastado).toLocaleString('es-CO');
      status.style.background = 'rgba(21,101,192,.08)';
      status.style.color = '#1565c0';
    }

    document.getElementById('bonif-json').value = JSON.stringify(items);
  }

  // Hook: cuando cambia el cliente, verificar bonificación
  document.getElementById('ped-cliente').addEventListener('change', checkBonifPanel);

  // ══ EDIT PEDIDO DETALLADO ══
  var epCart = [];
  var epCatalogVars = [];
  var epCurrentPrice = 0;
  var epCurrentCatId = 0;

  function editarPedido(idVenta) {
    epCart = [];
    document.getElementById('ep-id').value = idVenta;
    document.getElementById('modal-edit-pedido').style.display = 'flex';
    // Reset bonif panel
    epBonifLoaded = false;
    document.getElementById('ep-bonif-panel').style.display = 'none';
    document.getElementById('ep-bonif-json').value = '[]';
    // Load existing items
    fetch('index.php?ajax_detalle_venta=1&id_venta=' + idVenta)
      .then(function (r) { return r.json() })
      .then(function (data) {
        // Separar carrito (cantidad>0) de bonificaciones (cantidad=0 y bonificacion>0)
        var bonifPrellenado = {};
        data.items.forEach(function (item) {
          var cant = parseInt(item.cantidad) || 0;
          var bonif = parseInt(item.bonificacion) || 0;
          if (cant > 0) {
            epCart.push({
              id_variedad: parseInt(item.id_variedad),
              nombre: item.nombre,
              imagen: item.imagen,
              precio: parseFloat(item.precio_unitario),
              cantidad: cant,
              napa: parseInt(item.napa) || 0,
              catId: parseInt(item.id_categoria_precio)
            });
          } else if (bonif > 0) {
            bonifPrellenado[parseInt(item.id_variedad)] = bonif;
          }
        });
        document.getElementById('ep-cliente').value = data.id_cliente || 0;
        epRenderCart();
        // Después de cargar el carrito, espero a que el panel cargue y pre-lleno
        var fillBonif = function () {
          Object.keys(bonifPrellenado).forEach(function (idv) {
            var inp = document.querySelector('#ep-bonif-varieties [data-ep-bonif-id="' + idv + '"]');
            if (inp) inp.value = bonifPrellenado[idv];
          });
          epUpdateBonifStatus();
        };
        if (epBonifLoaded) { fillBonif(); }
        else {
          // Esperar a que carguen las variedades
          var tries = 0;
          var iv = setInterval(function () {
            tries++;
            if (epBonifLoaded || tries > 30) {
              clearInterval(iv);
              fillBonif();
            }
          }, 100);
        }
      });
  }

  function cerrarEditPedido() {
    document.getElementById('modal-edit-pedido').style.display = 'none';
    epCart = [];
    document.getElementById('ep-bonif-panel').style.display = 'none';
    document.getElementById('ep-bonif-json').value = '[]';
    epBonifMode = '';
    epBonifModeAnt = '';
  }
  document.getElementById('modal-edit-pedido').addEventListener('click', function (e) { if (e.target === this) cerrarEditPedido() });

  function epSelPrice(el) {
    document.querySelectorAll('#ep-price-tabs .price-tab').forEach(function (t) { t.classList.remove('active') });
    el.classList.add('active');
    epCurrentCatId = parseInt(el.dataset.id);
    epCurrentPrice = parseFloat(el.dataset.precio);
    epLoadCatalog(epCurrentCatId);
  }

  function epLoadCatalog(catId) {
    var catalog = document.getElementById('ep-catalog');
    catalog.innerHTML = '<div style="text-align:center;padding:.5rem;font-size:.78rem;color:var(--ink3);">Cargando...</div>';
    fetch('index.php?ajax_variedades=1&id_cat=' + catId)
      .then(function (r) { return r.json() })
      .then(function (vars) {
        epCatalogVars = vars;
        if (vars.length === 0) {
          catalog.innerHTML = '<div style="text-align:center;padding:.6rem;font-size:.78rem;color:var(--ink3);">Sin variedades</div>';
          return;
        }
        var html = '<div class="prod-grid" style="max-height:160px;">';
        vars.forEach(function (v) {
          var inCart = epCart.find(function (x) { return x.id_variedad == v.id_variedad });
          var cls = inCart ? 'prod-card in-cart' : 'prod-card';
          var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="pc-placeholder">🍞</div>';
          html += '<div class="' + cls + '" id="ep-pcard-' + v.id_variedad + '">'
            + '<div onclick="epTapProduct(' + v.id_variedad + ')">'
            + imgHtml
            + '<div class="pc-action">' + (inCart ? '✅ En carrito' : '<i class="bi bi-plus-circle-fill"></i> Agregar') + '</div>'
            + '<div class="pc-name" title="' + v.nombre + '">' + v.nombre + '</div>'
            + '</div>'
            + '<div class="pc-form">'
            + '<div class="pf-row"><label>Cant.</label><input type="number" class="pf-cant" min="1" max="999" value="1" onclick="event.stopPropagation()" oninput="if(this.value>999)this.value=999"></div>'
            + '<button type="button" class="pf-add" onclick="event.stopPropagation();epAddToCart(' + v.id_variedad + ')"><i class="bi bi-cart-plus"></i> Al carrito</button>'
            + '</div>'
            + '</div>';
        });
        html += '</div>';
        catalog.innerHTML = html;
      });
  }

  function epTapProduct(idVar) {
    if (epCart.find(function (x) { return x.id_variedad == idVar })) return;
    var pcard = document.getElementById('ep-pcard-' + idVar);
    if (!pcard) return;
    document.querySelectorAll('#ep-catalog .prod-card.expanded').forEach(function (c) { if (c !== pcard) c.classList.remove('expanded') });
    pcard.classList.toggle('expanded');
    if (pcard.classList.contains('expanded')) {
      var inp = pcard.querySelector('.pf-cant');
      if (inp) { inp.value = 1; inp.focus(); }
    }
  }

  function epAddToCart(idVar) {
    var v = epCatalogVars.find(function (x) { return x.id_variedad == idVar });
    if (!v) return;
    var pcard = document.getElementById('ep-pcard-' + idVar);
    var cant = parseInt(pcard.querySelector('.pf-cant').value) || 0;
    if (cant <= 0) return;
    epCart.push({ id_variedad: idVar, nombre: v.nombre, imagen: v.imagen, precio: epCurrentPrice, cantidad: cant, napa: 0, catId: epCurrentCatId });
    pcard.classList.remove('expanded');
    pcard.classList.add('in-cart');
    pcard.querySelector('.pc-action').innerHTML = '✅ En carrito';
    epRenderCart();
  }

  function epRemoveFromCart(idVar) {
    epCart = epCart.filter(function (x) { return x.id_variedad != idVar });
    var pcard = document.getElementById('ep-pcard-' + idVar);
    if (pcard) { pcard.classList.remove('in-cart'); pcard.classList.remove('expanded'); var a = pcard.querySelector('.pc-action'); if (a) a.innerHTML = '<i class="bi bi-plus-circle-fill"></i> Agregar'; }
    epRenderCart();
  }

  function epRenderCart() {
    var body = document.getElementById('ep-cart-body');
    var countEl = document.getElementById('ep-cart-count');
    var totalBar = document.getElementById('ep-cart-total');
    var btn = document.getElementById('ep-btn-save');
    countEl.textContent = epCart.length;

    if (epCart.length === 0) {
      body.innerHTML = '<div class="cart-empty">Agrega productos</div>';
      totalBar.style.display = 'none';
      btn.disabled = true;
      document.getElementById('ep-carrito-json').value = '[]';
      return;
    }

    var html = '<div class="cart-list">';
    var totalUnd = 0, totalNapa = 0, totalDinero = 0;
    epCart.forEach(function (item) {
      var sub = item.cantidad * item.precio;
      totalUnd += item.cantidad; totalNapa += item.napa; totalDinero += sub;
      var imgHtml = item.imagen ? '<img src="' + appUrl + '/' + item.imagen + '">' : '<div class="ci-ph">🍞</div>';
      html += '<div class="cart-item">'
        + imgHtml
        + '<div class="ci-info"><div class="ci-name">' + item.nombre + '</div><div class="ci-price">$' + item.precio.toLocaleString('es-CO') + (item.napa > 0 ? ' · 🎁+' + item.napa : '') + '</div></div>'
        + '<div class="ci-fields"><label>Cant.</label><input type="number" min="1" max="999" value="' + item.cantidad + '" onchange="epUpdateItem(' + item.id_variedad + ',\'cantidad\',this.value)" oninput="if(this.value>999)this.value=999"></div>'
        + '<div class="ci-sub">$' + sub.toLocaleString('es-CO') + '</div>'
        + '<button type="button" class="ci-del" onclick="epRemoveFromCart(' + item.id_variedad + ')"><i class="bi bi-x-lg"></i></button>'
        + '</div>';
    });
    html += '</div>';
    body.innerHTML = html;

    document.getElementById('ep-ct-und').textContent = totalUnd;
    document.getElementById('ep-ct-napa').textContent = totalNapa;
    document.getElementById('ep-ct-total').textContent = totalDinero.toLocaleString('es-CO');
    totalBar.style.display = 'flex';
    btn.disabled = false;
    document.getElementById('ep-carrito-json').value = JSON.stringify(epCart);
    epCheckBonif();
  }

  function epUpdateItem(idVar, field, val) {
    var item = epCart.find(function (x) { return x.id_variedad === idVar });
    if (item) { item[field] = Math.max(1, parseInt(val) || 1); }
    epRenderCart();
  }

  // ══ BONIFICACIÓN/ÑAPA del MODAL editar pedido ══
  var epBonifCredito = 0;
  var epBonifMode = 'tienda';
  var epBonifModeAnt = '';
  var epBonifLoaded = false;
  var epAllVarieties = [];

  function epCheckBonif() {
    var sel = document.getElementById('ep-cliente');
    if (!sel) return;
    var opt = sel.options[sel.selectedIndex];
    var panel = document.getElementById('ep-bonif-panel');
    var card = document.getElementById('ep-bonif-card');
    var titulo = document.getElementById('ep-bonif-titulo');
    var hint = document.getElementById('ep-bonif-hint');

    if (epCart.length === 0) {
      panel.style.display = 'none';
      epBonifCredito = 0;
      document.getElementById('ep-bonif-json').value = '[]';
      return;
    }

    var totalDinero = 0;
    epCart.forEach(function (it) { totalDinero += it.cantidad * it.precio; });

    var modoAnt = epBonifMode;
    if (opt && opt.dataset.tipo === 'tienda') {
      epBonifMode = 'tienda';
      epBonifCredito = Math.floor(totalDinero / 5000) * 1000;
      card.style.background = 'rgba(21,101,192,.06)';
      card.style.borderColor = 'rgba(21,101,192,.18)';
      titulo.style.color = '#1565c0';
      titulo.innerHTML = '🏪 Bonificación tienda';
      hint.style.color = '#1565c0';
      hint.innerHTML = 'Tienda: $1.000 de crédito por cada $5.000. Escoge los panes.';
    } else {
      epBonifMode = 'mostrador';
      epBonifCredito = Math.floor(totalDinero / 5000) * 500;
      card.style.background = 'rgba(198,113,36,.06)';
      card.style.borderColor = 'rgba(198,113,36,.2)';
      titulo.style.color = '#c67124';
      titulo.innerHTML = '🎁 Ñapa mostrador';
      hint.style.color = '#c67124';
      hint.innerHTML = 'Mostrador: $500 de crédito por cada $5.000. Escoge la(s) ñapa(s).';
    }

    document.getElementById('ep-bonif-credito').textContent = epBonifCredito.toLocaleString('es-CO');

    if (epBonifCredito <= 0) {
      panel.style.display = 'none';
      document.getElementById('ep-bonif-json').value = '[]';
      return;
    }

    panel.style.display = 'block';
    if (!epBonifLoaded) {
      epLoadAllVarieties();
    } else if (modoAnt !== epBonifMode && modoAnt !== '') {
      epRenderBonifVars();
    } else {
      epUpdateBonifStatus();
    }
  }

  function epLoadAllVarieties() {
    fetch('index.php?ajax_all_variedades=1')
      .then(function (r) { return r.json() })
      .then(function (vars) {
        epAllVarieties = vars;
        epBonifLoaded = true;
        epRenderBonifVars();
      });
  }

  function epRenderBonifVars() {
    var container = document.getElementById('ep-bonif-varieties');
    if (epAllVarieties.length === 0) {
      container.innerHTML = '<div style="text-align:center;padding:.4rem;font-size:.72rem;color:#64b5f6;">Sin variedades</div>';
      return;
    }
    var html = '';
    var currentCat = '';
    var colSoft = (epBonifMode === 'tienda') ? '#64b5f6' : '#e4a565';
    epAllVarieties.forEach(function (v) {
      if (v.cat_nombre !== currentCat) {
        currentCat = v.cat_nombre;
        html += '<div style="font-size:.55rem;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:' + colSoft + ';padding:.2rem .15rem .05rem;margin-top:.15rem;">' + currentCat + ' · $' + parseFloat(v.precio_unitario).toLocaleString('es-CO') + '</div>';
      }
      var imgHtml = v.imagen ? '<img src="' + appUrl + '/' + v.imagen + '">' : '<div class="br-ph">🍞</div>';
      html += '<div class="bonif-row">'
        + imgHtml
        + '<span class="br-name">' + v.nombre + '</span>'
        + '<input type="number" min="0" value="0" data-ep-bonif-id="' + v.id_variedad + '" data-ep-bonif-precio="' + v.precio_unitario + '" oninput="epUpdateBonifStatus()">'
        + '</div>';
    });
    container.innerHTML = html;
    epUpdateBonifStatus();
  }

  function epUpdateBonifStatus() {
    var inputs = document.querySelectorAll('#ep-bonif-varieties [data-ep-bonif-id]');
    var gastado = 0;
    var totalUnd = 0;
    var items = [];
    inputs.forEach(function (inp) {
      var val = parseInt(inp.value) || 0;
      var pr = parseFloat(inp.dataset.epBonifPrecio) || 0;
      if (val > 0) {
        gastado += val * pr;
        totalUnd += val;
        items.push({ id_variedad: parseInt(inp.dataset.epBonifId), cantidad: val, precio: pr });
      }
    });
    var status = document.getElementById('ep-bonif-status');
    var pg = '$' + gastado.toLocaleString('es-CO');
    var pd = '$' + epBonifCredito.toLocaleString('es-CO');
    if (gastado === epBonifCredito) {
      status.textContent = '✅ ' + pg + '/' + pd + ' · ' + totalUnd + ' unid.';
      status.style.background = 'rgba(46,125,50,.1)'; status.style.color = '#2e7d32';
    } else if (gastado > epBonifCredito) {
      status.textContent = '⚠️ ' + pg + '/' + pd + ' — te pasas $' + (gastado - epBonifCredito).toLocaleString('es-CO');
      status.style.background = 'rgba(198,40,40,.1)'; status.style.color = '#c62828';
    } else {
      status.textContent = '📝 ' + pg + '/' + pd + ' — quedan $' + (epBonifCredito - gastado).toLocaleString('es-CO');
      status.style.background = 'rgba(21,101,192,.08)'; status.style.color = '#1565c0';
    }
    document.getElementById('ep-bonif-json').value = JSON.stringify(items);
  }

  // ══ EDIT MODAL ══
  function abrirEdit(id, cat, tipo, cantTotal, cli, bonifPrev) {
    bonifPrev = parseInt(bonifPrev) || 0;
    cantTotal = parseInt(cantTotal) || 0;
    // En BD las "unidades_vendidas" incluyen la bonificación. Restamos para mostrar las cobradas.
    var cantCobradas = Math.max(1, cantTotal - bonifPrev);
    document.getElementById('ev-id').value = id;
    document.getElementById('ev-cat').value = cat;
    document.getElementById('ev-cant').value = cantCobradas;
    document.getElementById('ev-tipo').value = tipo;
    document.getElementById('ev-cli').value = cli;
    document.getElementById('ev-cli-prev').value = cli;
    document.getElementById('ev-und-prev').value = cantCobradas;
    document.getElementById('ev-extra').value = 0;
    document.getElementById('ev-extra').disabled = false;
    document.getElementById('modal-edit').style.display = 'flex';
    evRecalc();
  }
  function cerrarEdit() { document.getElementById('modal-edit').style.display = 'none'; }
  document.getElementById('modal-edit').addEventListener('click', function (e) { if (e.target === this) cerrarEdit() });

  // Recalcula la previa de bonificación/ñapa en el modal edit
  function evRecalc() {
    var preview = document.getElementById('ev-preview');
    var extraWrap = document.getElementById('ev-extra-wrap');
    var extraHint = document.getElementById('ev-extra-hint');
    var tipo = document.getElementById('ev-tipo').value;
    var catSel = document.getElementById('ev-cat');
    var precio = parseFloat(catSel.options[catSel.selectedIndex].dataset.precio) || 0;
    var cant = parseInt(document.getElementById('ev-cant').value) || 0;
    var cliSel = document.getElementById('ev-cli');
    var cliOpt = cliSel.options[cliSel.selectedIndex];
    var esTienda = cliOpt && cliOpt.dataset.tipo === 'tienda';
    var cliPrev = parseInt(document.getElementById('ev-cli-prev').value) || 0;

    // Solo aplica para tipo 'venta'
    if (tipo !== 'venta' || cant <= 0 || precio <= 0) {
      preview.style.display = 'none';
      extraWrap.style.display = 'none';
      return;
    }

    var total = cant * precio;
    if (esTienda) {
      var credito = Math.floor(total / 5000) * 1000;
      var und = (precio > 0) ? Math.floor(credito / precio) : 0;
      preview.style.display = 'block';
      preview.style.background = 'rgba(21,101,192,.08)';
      preview.style.color = '#1565c0';
      preview.style.border = '1px solid rgba(21,101,192,.2)';
      preview.innerHTML = '🏪 <strong>Tienda</strong> · Crédito: <strong>$' + credito.toLocaleString('es-CO') + '</strong> = <strong>' + und + '</strong> unidad(es) de bonificación<br><small>Total entregado: <strong>' + (cant + und) + '</strong> panes</small>';
      // Si antes era mostrador (cliPrev==0) y ahora es tienda → mostrar campo extra
      if (cliPrev === 0) {
        extraWrap.style.display = 'block';
        extraHint.innerHTML = 'Antes era <strong>mostrador</strong>. Ahora es <strong>tienda</strong>: la bonificación es mayor. Aquí puedes <strong>agregar</strong> unidades extra para completar.';
      } else {
        extraWrap.style.display = 'none';
        document.getElementById('ev-extra').value = 0;
      }
    } else {
      // Mostrador
      var credito = Math.floor(total / 5000) * 500;
      var und = (precio > 0) ? Math.floor(credito / precio) : 0;
      preview.style.display = 'block';
      preview.style.background = 'rgba(198,113,36,.08)';
      preview.style.color = '#c67124';
      preview.style.border = '1px solid rgba(198,113,36,.22)';
      preview.innerHTML = '🎁 <strong>Mostrador</strong> · Crédito: <strong>$' + credito.toLocaleString('es-CO') + '</strong> = <strong>' + und + '</strong> de ñapa<br><small>Total entregado: <strong>' + (cant + und) + '</strong> panes</small>';
      // Si antes era tienda y ahora pasa a mostrador → avisar que se le restan las unidades extra a la tienda
      if (cliPrev > 0) {
        extraWrap.style.display = 'block';
        extraWrap.style.borderColor = 'rgba(198,113,36,.4)';
        extraWrap.style.background = 'rgba(198,113,36,.05)';
        extraHint.style.color = '#c67124';
        extraHint.innerHTML = '⚠️ Antes era <strong>tienda</strong>. Al pasar a <strong>mostrador</strong>, las unidades extra de bonificación se descuentan automáticamente (la tienda ya no se las lleva).';
        document.getElementById('ev-extra').value = 0;
        document.getElementById('ev-extra').disabled = true;
      } else {
        extraWrap.style.display = 'none';
        document.getElementById('ev-extra').disabled = false;
      }
    }
  }
</script>

<?php include __DIR__ . '/../../views/layouts/footer.php'; ?>