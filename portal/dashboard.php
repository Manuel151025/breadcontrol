<?php
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../config/db.php';
session_start();

if (!isset($_SESSION['cliente_id'])) {
    header('Location: index.php');
    exit;
}

$pdo        = getConexion();
$cliente_id = (int)$_SESSION['cliente_id'];

$stmt_cli = $pdo->prepare("SELECT * FROM cliente WHERE id_cliente = ?");
$stmt_cli->execute([$cliente_id]);
$cliente_info = $stmt_cli->fetch(PDO::FETCH_ASSOC);
$es_tienda    = ($cliente_info['tipo'] === 'tienda');

// ── Detectar si es instructor ADSO (tiene pedidos de aprendices a su nombre) ──
$es_instructor   = false;
$resumen_fin     = [];
$aprendices      = [];
$total_reg       = 0;

if ($es_tienda) {
    $chk = $pdo->prepare("SELECT COUNT(*) FROM pedido_cliente WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ? LIMIT 1");
    $chk->execute([$cliente_id, $cliente_id]);
    $es_instructor = (int)$chk->fetchColumn() > 0;
}

if ($es_instructor) {
    // Resumen financiero global
    $sf = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN estado != 'rechazado' AND MONTH(fecha_solicitud)=MONTH(NOW()) AND YEAR(fecha_solicitud)=YEAR(NOW()) THEN total_estimado ELSE 0 END), 0) AS total_mes,
            COUNT(DISTINCT id_creador) AS aprendices_activos,
            COUNT(*) AS total_pedidos
        FROM pedido_cliente
        WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ?
    ");
    $sf->execute([$cliente_id, $cliente_id]);
    $resumen_fin = $sf->fetch(PDO::FETCH_ASSOC);

    // 1. Obtener el total comprado histórico de los aprendices (excluyendo rechazados)
    $stmt_comprado = $pdo->prepare("
        SELECT COALESCE(SUM(total_estimado), 0) 
        FROM pedido_cliente 
        WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ? AND estado != 'rechazado'
    ");
    $stmt_comprado->execute([$cliente_id, $cliente_id]);
    $total_comprado = (float)$stmt_comprado->fetchColumn();

    // 2. Obtener el saldo pendiente real (calculando saldo restante de los consolidados/individuales)
    $stmt_pends = $pdo->prepare("
        SELECT id_pedido, total_estimado, id_pago_activo
        FROM pedido_cliente
        WHERE id_cliente = ? AND id_creador IS NOT NULL AND id_creador != ? AND estado != 'rechazado'
          AND (estado_pago IS NULL OR estado_pago IN ('pendiente', 'no_aplica', 'parcial'))
    ");
    $stmt_pends->execute([$cliente_id, $cliente_id]);
    $pedidos_pends = $stmt_pends->fetchAll(PDO::FETCH_ASSOC);

    $pedidos_por_pago = [];
    foreach ($pedidos_pends as $p) {
        $pago_id = !empty($p['id_pago_activo']) ? (int)$p['id_pago_activo'] : 0;
        $pedidos_por_pago[$pago_id][] = $p;
    }

    $pendiente_total_real = 0.0;
    foreach ($pedidos_por_pago as $pago_id => $grupo_pedidos) {
        $suma_grupo = 0.0;
        foreach ($grupo_pedidos as $p) {
            $suma_grupo += (float)$p['total_estimado'];
        }
        if ($pago_id === 0) {
            $pendiente_total_real += $suma_grupo;
        } else {
            $stmt_ab = $pdo->prepare("SELECT COALESCE(SUM(monto), 0) FROM pago_abono WHERE id_pago = ?");
            $stmt_ab->execute([$pago_id]);
            $abonado = (float)$stmt_ab->fetchColumn();
            
            $deficit = $suma_grupo - $abonado;
            if ($deficit > 0) {
                $pendiente_total_real += $deficit;
            }
        }
    }

    // Inyectar el saldo pendiente real en el resumen financiero
    $resumen_fin['pendiente_total'] = $pendiente_total_real;

    // Resumen por aprendiz
    $sa = $pdo->prepare("
        SELECT
            c.id_cliente,
            c.nombre,
            c.telefono,
            c.email,
            c.foto_url,
            COUNT(p.id_pedido) AS total_pedidos,
            COALESCE(SUM(CASE WHEN p.estado != 'rechazado' THEN p.total_estimado ELSE 0 END), 0) AS total_comprado,
            COALESCE(SUM(CASE WHEN p.estado != 'rechazado' AND p.estado_pago IN ('pendiente','no_aplica') THEN p.total_estimado ELSE 0 END), 0) AS saldo_pendiente,
            MAX(p.fecha_solicitud) AS ultimo_pedido,
            COALESCE(SUM(CASE WHEN p.estado = 'pendiente' THEN 1 ELSE 0 END), 0) AS sin_confirmar
        FROM cliente c
        LEFT JOIN pedido_cliente p ON p.id_creador = c.id_cliente AND p.id_cliente = ?
        WHERE c.es_aprendiz = 1 AND c.activo = 1
        GROUP BY c.id_cliente, c.nombre, c.telefono, c.email, c.foto_url
        ORDER BY saldo_pendiente DESC, total_comprado DESC
    ");
    $sa->execute([$cliente_id]);
    $aprendices = $sa->fetchAll(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT COUNT(*) FROM cliente WHERE es_aprendiz = 1 AND activo = 1");
    $st->execute();
    $total_reg = (int)$st->fetchColumn();
}

// Datos Nequi para el instructor
$nequi_config = [];
$pedidos_pago_instructor = [];
if ($es_instructor) {
    $nc = $pdo->query("SELECT nequi_link_pago, nequi_titular, wompi_habilitado FROM configuracion LIMIT 1");
    $nequi_config = $nc->fetch(PDO::FETCH_ASSOC) ?: [];

    if ($resumen_fin['pendiente_total'] > 0) {
        $sp = $pdo->prepare("
            SELECT p.id_pedido, p.total_estimado, p.fecha_entrega, p.fecha_solicitud,
                   c.nombre AS nombre_creador
            FROM pedido_cliente p
            LEFT JOIN cliente c ON p.id_creador = c.id_cliente
            WHERE p.id_cliente = ?
              AND p.id_creador IS NOT NULL AND p.id_creador != ?
              AND p.estado != 'rechazado'
              AND p.estado_pago IN ('pendiente','no_aplica')
            ORDER BY p.fecha_entrega ASC
        ");
        $sp->execute([$cliente_id, $cliente_id]);
        $pedidos_pago_instructor = $sp->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ── Variedades disponibles (para el modal) ──
$sv = $pdo->prepare("SELECT id_variedad, nombre, imagen FROM variedad_pan WHERE activo = 1 ORDER BY nombre ASC");
$sv->execute();
$variedades = $sv->fetchAll(PDO::FETCH_ASSOC);

// ── Filtros ──
$f_estado    = trim($_GET['estado']      ?? '');
$f_orden     = trim($_GET['orden']       ?? 'recientes');
$f_aprendiz  = (int)($_GET['aprendiz_id'] ?? 0);
$f_variedad  = (int)($_GET['variedad_id'] ?? 0);
$nombre_variedad = '';
if ($f_variedad) {
    foreach ($variedades as $v) {
        if ($v['id_variedad'] === $f_variedad) { $nombre_variedad = $v['nombre']; break; }
    }
}

if ($f_aprendiz && $es_instructor) {
    $where_sql = "WHERE p.id_cliente = ? AND p.id_creador = ?";
    $params    = [$cliente_id, $f_aprendiz];
    // Nombre del aprendiz filtrado
    $sa2 = $pdo->prepare("SELECT nombre FROM cliente WHERE id_cliente = ?");
    $sa2->execute([$f_aprendiz]);
    $nombre_filtro = $sa2->fetchColumn() ?: 'Aprendiz';
} else {
    $where_sql = "WHERE (p.id_cliente = ? OR p.id_creador = ?)";
    $params    = [$cliente_id, $cliente_id];
    $nombre_filtro = '';
}

if ($f_estado) {
    $where_sql .= " AND p.estado = ?";
    $params[]   = $f_estado;
}

$order_sql = match($f_orden) {
    'antiguos' => "ORDER BY p.fecha_solicitud ASC",
    'entrega'  => "ORDER BY p.fecha_entrega ASC, p.fecha_solicitud DESC",
    default    => "ORDER BY p.fecha_solicitud DESC",
};

$join_variedad = '';
if ($f_variedad) {
    $join_variedad = "INNER JOIN pedido_cliente_detalle pcd ON pcd.id_pedido = p.id_pedido AND pcd.id_variedad = ?";
    $params[] = $f_variedad;
}

$stmt = $pdo->prepare("
    SELECT p.*, c.nombre AS nombre_creador
    FROM pedido_cliente p
    LEFT JOIN cliente c ON p.id_creador = c.id_cliente
    $join_variedad
    $where_sql $order_sql
    LIMIT 50
");
$stmt->execute($params);
$mis_pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Saldo pendiente propio
$ss = $pdo->prepare("SELECT SUM(total_estimado) FROM pedido_cliente WHERE id_cliente = ? AND estado != 'rechazado' AND (estado_pago IN ('pendiente','no_aplica') OR estado_pago IS NULL)");
$ss->execute([$cliente_id]);
$saldo_pendiente = (float)$ss->fetchColumn();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mi Tablero — BreadControl</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        :root {
            --c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;
            --cbg:#faf3ea;--ccard:#ffffff;--clight:#fdf6ee;
            --ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;
            --border:rgba(148,91,53,.12);
            --shadow:0 1px 8px rgba(148,91,53,.09);
            --shadow2:0 4px 20px rgba(148,91,53,.15);
            --nav-h:64px;
        }
        *,*::before,*::after{margin:0;padding:0;box-sizing:border-box;}
        html,body{width:100%;max-width:100%;overflow-x:hidden;font-family:'Plus Jakarta Sans',sans-serif;background:var(--cbg);color:var(--ink);-webkit-text-size-adjust:100%;}

        /* ── NAV ── */
        nav{position:fixed;top:0;left:0;right:0;z-index:900;height:var(--nav-h);background:linear-gradient(100deg,var(--c1) 0%,var(--c3) 55%,var(--c4) 100%);display:flex;align-items:center;justify-content:space-between;padding:0 1rem;box-shadow:0 3px 24px rgba(100,40,10,.35);}
        nav::after{content:'';position:absolute;bottom:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,rgba(255,255,255,.45),transparent);}
        .n-logo{display:flex;align-items:center;gap:.65rem;text-decoration:none;padding:.18rem .6rem .18rem .15rem;border-radius:12px;transition:background .2s;}
        .n-logo:hover{background:rgba(255,255,255,.12);}
        .n-logo-img{width:42px;height:42px;border-radius:50%;object-fit:cover;border:2.5px solid rgba(255,255,255,.6);box-shadow:0 2px 10px rgba(80,30,5,.45);}
        .n-logo-name{font-family:'Fraunces',serif;font-size:1.12rem;font-weight:800;color:#fff;line-height:1.1;text-shadow:0 1px 6px rgba(80,30,5,.35);}
        .n-logo-sub{font-size:.5rem;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.6);}
        .n-right{display:flex;align-items:center;gap:.55rem;flex-shrink:0;}
        .n-user{display:flex;align-items:center;gap:.45rem;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.22);border-radius:22px;padding:.26rem .75rem .26rem .3rem;text-decoration:none;}
        .n-avatar{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.28);border:1.5px solid rgba(255,255,255,.45);display:flex;align-items:center;justify-content:center;font-size:.8rem;color:#fff;font-weight:800;flex-shrink:0;overflow:hidden;}
        .n-avatar img{width:100%;height:100%;object-fit:cover;}
        .n-uname{font-size:.78rem;color:#fff;font-weight:700;}
        .n-urole{font-size:.55rem;color:rgba(255,255,255,.62);text-transform:uppercase;letter-spacing:.1em;}
        .n-logout{width:34px;height:34px;border-radius:8px;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.2);color:rgba(255,255,255,.85);font-size:1rem;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all .2s;}
        .n-logout:hover{background:rgba(220,53,69,.35);color:#fff;}

        /* ── LAYOUT ── */
        @keyframes fadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:translateY(0)}}
        @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
        .page{margin-top:var(--nav-h);padding:1rem;width:100%;max-width:1100px;margin-left:auto;margin-right:auto;display:flex;flex-direction:column;gap:.8rem;animation:fadeUp .4s ease both;}

        /* ── BANNER ── */
        .wc-banner{background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);background-size:300% 300%;animation:gradAnim 8s ease infinite;border-radius:14px;padding:.9rem 1.4rem;display:flex;align-items:center;justify-content:space-between;box-shadow:var(--shadow2);gap:1rem;flex-wrap:wrap;}
        .wc-left{display:flex;align-items:center;gap:.9rem;}
        .wc-greeting{font-size:.65rem;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.65);margin-bottom:.15rem;}
        .wc-name{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1.1;}
        .wc-name em{font-style:italic;color:var(--c5);}
        .wc-sub{font-size:.72rem;color:rgba(255,255,255,.62);margin-top:.15rem;}
        .wc-pills{display:flex;gap:.55rem;flex-wrap:wrap;}
        .wc-pill{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.2);border-radius:10px;padding:.5rem .85rem;text-align:center;min-width:68px;}
        .wc-pill-num{font-family:'Fraunces',serif;font-size:1.35rem;font-weight:800;color:#fff;line-height:1;}
        .wc-pill-lbl{font-size:.54rem;text-transform:uppercase;letter-spacing:.12em;color:rgba(255,255,255,.58);}

        /* ── STAT CARDS (instructor) ── */
        .stat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:.75rem;}
        .stat-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:1.1rem 1.2rem;display:flex;flex-direction:column;gap:.3rem;position:relative;overflow:hidden;}
        .stat-card::before{content:'';position:absolute;top:0;left:0;right:0;height:3px;}
        .stat-card.red::before{background:linear-gradient(90deg,#ef4444,#f87171);}
        .stat-card.green::before{background:linear-gradient(90deg,#22c55e,#4ade80);}
        .stat-card.blue::before{background:linear-gradient(90deg,#3b82f6,#60a5fa);}
        .stat-card.orange::before{background:linear-gradient(90deg,var(--c3),var(--c4));}
        .stat-icon{font-size:1.5rem;margin-bottom:.2rem;}
        .stat-icon.red{color:#ef4444;}
        .stat-icon.green{color:#22c55e;}
        .stat-icon.blue{color:#3b82f6;}
        .stat-icon.orange{color:var(--c3);}
        .stat-val{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;color:var(--ink);line-height:1;}
        .stat-lbl{font-size:.7rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink3);font-weight:700;}
        .stat-sub{font-size:.72rem;color:var(--ink3);margin-top:.1rem;}

        /* ── APRENDICES TABLE ── */
        .section-header{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;margin:.3rem 0;}
        .section-title{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
        .section-title i{color:var(--c3);}
        .search-box{display:flex;align-items:center;gap:.5rem;background:var(--ccard);border:1px solid var(--border);border-radius:10px;padding:.45rem .8rem;}
        .search-box input{border:none;outline:none;font-family:inherit;font-size:.85rem;background:transparent;color:var(--ink);width:180px;}
        .search-box i{color:var(--ink3);}

        .card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);display:flex;flex-direction:column;overflow:hidden;}
        .ch{display:flex;align-items:center;justify-content:space-between;padding:.8rem 1.1rem;border-bottom:1px solid var(--border);background:var(--clight);}
        .ch-left{display:flex;align-items:center;gap:.5rem;}
        .ch-ico{width:30px;height:30px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;background:rgba(198,113,36,.1);color:var(--c3);}
        .ch-title{font-size:.68rem;font-weight:700;text-transform:uppercase;letter-spacing:.17em;color:var(--ink3);}

        .tbl-wrap{overflow-y:auto;overflow-x:auto;width:100%;}
        .gt{width:100%;border-collapse:collapse;}
        .gt th{font-size:.61rem;text-transform:uppercase;letter-spacing:.1em;color:var(--ink3);font-weight:700;padding:.8rem 1rem;background:var(--clight);border-bottom:1px solid var(--border);text-align:left;white-space:nowrap;}
        .gt td{font-size:.81rem;color:var(--ink);padding:.8rem 1rem;border-bottom:1px solid rgba(148,91,53,.05);vertical-align:middle;}
        .gt tr:last-child td{border-bottom:none;}
        .gt tr:hover td{background:rgba(250,243,234,.5);}

        /* ── APRENDIZ ROW ── */
        .apr-avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-family:'Fraunces',serif;font-size:1rem;font-weight:800;color:#fff;flex-shrink:0;overflow:hidden;}
        .apr-avatar img{width:100%;height:100%;object-fit:cover;}
        .apr-name-wrap{display:flex;align-items:center;gap:.65rem;}
        .apr-name{font-weight:700;color:var(--ink);}
        .apr-contact{font-size:.72rem;color:var(--ink3);margin-top:.1rem;}
        .badge-pendiente{display:inline-flex;align-items:center;gap:.3rem;background:rgba(239,68,68,.1);color:#dc2626;border:1px solid rgba(239,68,68,.25);border-radius:20px;padding:.2rem .65rem;font-size:.72rem;font-weight:700;}
        .badge-ok{display:inline-flex;align-items:center;gap:.3rem;background:rgba(34,197,94,.1);color:#16a34a;border:1px solid rgba(34,197,94,.2);border-radius:20px;padding:.2rem .65rem;font-size:.72rem;font-weight:700;}
        .badge-sin-pedidos{display:inline-flex;align-items:center;gap:.3rem;background:rgba(148,91,53,.08);color:var(--ink3);border:1px solid var(--border);border-radius:20px;padding:.2rem .65rem;font-size:.72rem;font-weight:600;}

        /* ── ESTADO Y BOTONES ── */
        .estado{font-size:.62rem;font-weight:700;padding:.2rem .6rem;border-radius:20px;text-transform:uppercase;letter-spacing:.1em;display:inline-block;}
        .e-pendiente{background:rgba(255,167,38,.1);color:#e65100;border:1px solid rgba(255,167,38,.35);}
        .e-confirmado{background:rgba(76,175,80,.1);color:#2e7d32;border:1px solid rgba(76,175,80,.25);}
        .e-rechazado{background:rgba(229,57,53,.1);color:#c62828;border:1px solid rgba(229,57,53,.2);}
        .btn-ver{background:var(--clight);color:var(--ink2);border:1px solid var(--border);padding:.45rem .9rem;border-radius:9px;text-decoration:none;font-size:.78rem;font-weight:600;display:inline-block;transition:all .2s;}
        .btn-ver:hover{border-color:var(--c3);color:var(--c3);background:rgba(198,113,36,.06);}
        .btn-filtrar{background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;padding:.45rem .9rem;border-radius:9px;font-size:.78rem;font-weight:700;cursor:pointer;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;transition:all .2s;}
        .btn-filtrar:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(198,113,36,.3);}
        .btn-primary{display:inline-flex;align-items:center;gap:.4rem;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;padding:.65rem 1.2rem;border-radius:10px;font-size:.88rem;font-weight:700;text-decoration:none;transition:all .2s;box-shadow:0 4px 14px rgba(198,113,36,.3);border:none;}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(198,113,36,.4);color:#fff;}

        .topbar{display:flex;align-items:center;justify-content:space-between;gap:.75rem;flex-wrap:wrap;width:100%;}
        .mod-titulo{font-family:'Fraunces',serif;font-size:1.3rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
        .mod-titulo i{color:var(--c3);}

        .filter-card{background:var(--ccard);border:1px solid var(--border);border-radius:12px;padding:1rem;}
        .filter-grid{display:grid;grid-template-columns:1fr 1fr auto auto;gap:.7rem;align-items:end;}
        .filter-group label{display:block;font-size:.65rem;font-weight:700;text-transform:uppercase;color:var(--ink3);margin-bottom:.3rem;}
        .filter-input{width:100%;padding:.55rem;border:1px solid var(--border);border-radius:8px;font-size:.85rem;font-family:inherit;background:var(--clight);}
        .btn-filter{background:var(--c3);color:#fff;border:none;padding:.6rem 1rem;border-radius:8px;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:.4rem;transition:all .2s;font-size:.85rem;}
        .btn-filter:hover{background:var(--c1);transform:translateY(-1px);}
        .btn-clear{background:var(--ccard);color:var(--ink3);border:1px solid var(--border);padding:.6rem 1rem;border-radius:8px;font-weight:600;cursor:pointer;text-decoration:none;display:flex;align-items:center;gap:.4rem;transition:all .2s;font-size:.85rem;}
        .btn-clear:hover{background:var(--clight);color:var(--c1);}

        .btn-search-pan {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.6rem;
            background: linear-gradient(135deg, var(--c4), var(--c3));
            color: #fff;
            border: none;
            padding: 0.65rem 1.6rem;
            border-radius: 30px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(198, 113, 36, 0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }
        .btn-search-pan::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, var(--c3), var(--c1));
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 1;
        }
        .btn-search-pan:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(198, 113, 36, 0.35);
        }
        .btn-search-pan:hover::before {
            opacity: 1;
        }
        .btn-search-pan:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(198, 113, 36, 0.2);
        }
        .btn-search-pan > * {
            position: relative;
            z-index: 2;
        }
        .btn-search-pan:hover i {
            animation: wiggle 0.5s ease infinite alternate;
        }
        .badge-variedad-filtro {
            background: rgba(255, 255, 255, 0.25);
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: 20px;
            padding: 0.15rem 0.6rem;
            font-size: 0.72rem;
            font-weight: 600;
            margin-left: 0.35rem;
            display: inline-block;
            vertical-align: middle;
            color: #fff;
        }
        @keyframes wiggle {
            0% { transform: rotate(-8deg); }
            100% { transform: rotate(8deg); }
        }

        .filtro-activo{display:flex;align-items:center;gap:.6rem;background:#fffbeb;border:1px solid #fbbf24;border-radius:10px;padding:.55rem 1rem;font-size:.82rem;font-weight:600;color:#92400e;}
        .filtro-activo a{color:#92400e;text-decoration:none;font-size:1rem;display:flex;align-items:center;}
        .filtro-activo a:hover{color:#dc2626;}

        .empty-state{text-align:center;padding:3rem 1rem;color:var(--ink3);}
        .empty-state i{font-size:3rem;margin-bottom:1rem;opacity:.5;display:block;}

        /* ── BULK EXPORT ── */
        .bulk-bar-dash{display:none;align-items:center;gap:.7rem;background:#e8eaf6;border:1px solid #9fa8da;border-radius:10px;padding:.6rem 1rem;flex-wrap:wrap;}
        .bulk-bar-dash.visible{display:flex;}
        .bulk-info{font-size:.85rem;font-weight:700;color:#283593;flex:1;}
        .btn-exp-dash{display:inline-flex;align-items:center;gap:.4rem;padding:.48rem .95rem;border-radius:9px;font-size:.8rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;transition:all .2s;}
        .btn-exp-excel{background:#1d6f42;color:#fff;}
        .btn-exp-excel:hover{background:#155634;}
        .btn-exp-pdf{background:#c62828;color:#fff;}
        .btn-exp-pdf:hover{background:#b71c1c;}

        /* ── MODAL PAN ── */
        .modal-backdrop{position:fixed;inset:0;background:rgba(40,21,8,.55);backdrop-filter:blur(4px);z-index:1000;display:flex;align-items:center;justify-content:center;padding:1rem;opacity:0;pointer-events:none;transition:opacity .25s ease;}
        .modal-backdrop.open{opacity:1;pointer-events:all;}
        .modal-box{background:#fff;border-radius:18px;box-shadow:0 20px 60px rgba(40,21,8,.25);width:100%;max-width:540px;max-height:85vh;display:flex;flex-direction:column;transform:translateY(20px);transition:transform .25s ease;overflow:hidden;}
        .modal-backdrop.open .modal-box{transform:translateY(0);}
        .modal-head{display:flex;align-items:center;justify-content:space-between;padding:1.1rem 1.3rem;border-bottom:1px solid var(--border);background:var(--clight);flex-shrink:0;}
        .modal-head h2{font-family:'Fraunces',serif;font-size:1.15rem;font-weight:800;color:var(--ink);display:flex;align-items:center;gap:.5rem;}
        .modal-head h2 i{color:var(--c3);}
        .modal-close{width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:var(--ccard);color:var(--ink3);display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:1.1rem;transition:all .2s;}
        .modal-close:hover{background:#fee2e2;border-color:#fca5a5;color:#dc2626;}
        .modal-search{padding:.9rem 1.2rem;border-bottom:1px solid var(--border);flex-shrink:0;}
        .modal-search-wrap{display:flex;align-items:center;gap:.6rem;background:var(--clight);border:1px solid var(--border);border-radius:10px;padding:.55rem .9rem;}
        .modal-search-wrap:focus-within{border-color:var(--c3);box-shadow:0 0 0 3px rgba(198,113,36,.12);}
        .modal-search-wrap i{color:var(--ink3);font-size:1rem;flex-shrink:0;}
        .modal-search-wrap input{border:none;outline:none;font-family:inherit;font-size:.9rem;background:transparent;color:var(--ink);width:100%;}
        .modal-body{overflow-y:auto;padding:1rem 1.2rem;display:flex;flex-direction:column;gap:.5rem;}
        .var-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:.6rem;}
        .var-btn{display:flex;flex-direction:column;align-items:center;gap:.5rem;padding:.85rem .5rem;border-radius:12px;border:1.5px solid var(--border);background:var(--clight);cursor:pointer;transition:all .2s;text-align:center;text-decoration:none;}
        .var-btn:hover,.var-btn.selected{border-color:var(--c3);background:rgba(198,113,36,.07);transform:translateY(-2px);box-shadow:0 4px 14px rgba(198,113,36,.18);}
        .var-btn.selected{border-width:2px;}
        .var-img{width:52px;height:52px;border-radius:10px;object-fit:cover;background:var(--border);}
        .var-img-placeholder{width:52px;height:52px;border-radius:10px;background:linear-gradient(135deg,var(--c3),var(--c4));display:flex;align-items:center;justify-content:center;font-size:1.5rem;}
        .var-nombre{font-size:.78rem;font-weight:700;color:var(--ink2);line-height:1.3;}
        .modal-footer{padding:.85rem 1.2rem;border-top:1px solid var(--border);background:var(--clight);flex-shrink:0;display:flex;gap:.6rem;}
        .btn-modal-limpiar{flex:1;padding:.65rem;border-radius:10px;border:1px solid var(--border);background:var(--ccard);color:var(--ink3);font-family:inherit;font-size:.85rem;font-weight:600;cursor:pointer;transition:all .2s;}
        .btn-modal-limpiar:hover{background:#fee2e2;color:#dc2626;border-color:#fca5a5;}
        .btn-modal-aplicar{flex:2;padding:.65rem;border-radius:10px;border:none;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;transition:all .2s;}
        .btn-modal-aplicar:hover{transform:translateY(-1px);box-shadow:0 4px 14px rgba(198,113,36,.3);}
        .btn-modal-aplicar:disabled{opacity:.5;cursor:not-allowed;transform:none;}
        .no-results{text-align:center;padding:2rem;color:var(--ink3);font-size:.85rem;}

        /* ── RESPONSIVE ── */
        @media(max-width:900px){.stat-grid{grid-template-columns:repeat(2,1fr);}}
        @media(max-width:768px){
            nav{padding:.4rem .7rem;height:auto;min-height:56px;}
            .n-logo-name{font-size:.92rem;}
            .n-logo-sub{display:none;}
            .n-logo-img{width:32px;height:32px;}
            .n-avatar{width:28px;height:28px;font-size:.7rem;}
            .n-uname{max-width:70px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
            .n-urole{display:none;}
            .n-logout{display:none;}
            .page{padding:.5rem;gap:.6rem;}
            .wc-banner{padding:.75rem 1rem;}
            .wc-name{font-size:1.1rem;}
            .stat-grid{grid-template-columns:repeat(2,1fr);gap:.5rem;}
            .stat-val{font-size:1.3rem;}
            .topbar{flex-direction:column;align-items:stretch;}
            .btn-primary{justify-content:center;width:100%;}
            .gt thead{display:none;}
            .gt,.gt tbody,.gt tr,.gt td{display:block;width:100%;}
            .gt tr{margin:0 0 .6rem;border-bottom:1px solid var(--border);padding:.6rem;position:relative;}
            .gt td{border:none;padding:.4rem 0;display:flex;justify-content:space-between;align-items:center;border-bottom:1px solid rgba(148,91,53,.05);font-size:.8rem;}
            .gt td:last-child{border-bottom:none;padding-top:.6rem;}
            .gt td::before{content:attr(data-label);font-size:.6rem;font-weight:800;text-transform:uppercase;color:var(--ink3);}
            .gt td:first-child{font-size:1rem;border-bottom:1px solid var(--border);margin-bottom:.4rem;padding-bottom:.5rem;}
            .filter-grid{grid-template-columns:1fr;}
            .btn-filter,.btn-clear{justify-content:center;width:100%;}
            .search-box input{width:120px;}
        }
        @media(max-width:480px){.stat-grid{grid-template-columns:1fr 1fr;}}
    </style>
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
                    <img src="<?= htmlspecialchars($cliente_info['foto_url']) ?>" alt="avatar">
                <?php else: ?>
                    <?= strtoupper(substr($_SESSION['cliente_nombre'], 0, 1)) ?>
                <?php endif; ?>
            </div>
            <div>
                <div class="n-uname"><?= htmlspecialchars($_SESSION['cliente_nombre']) ?></div>
                <div class="n-urole"><?= $es_instructor ? 'Instructor' : 'Cliente' ?></div>
            </div>
        </a>
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

    <?php if (!empty($_GET['epago'])): ?>
    <div style="background:#ffebee;border:1px solid #ef9a9a;border-left:4px solid #c62828;border-radius:10px;padding:.85rem 1.1rem;font-size:.85rem;color:#c62828;display:flex;align-items:center;gap:.6rem;">
        <i class="bi bi-exclamation-triangle-fill"></i>
        <?= htmlspecialchars($_GET['epago']) ?>
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

    <!-- ══ BARRA DE PROGRESO DE PAGO (Instructor) ══ -->
    <?php
    $pct_pagado = 100.0;
    $total_pagado = 0.0;
    if ($total_comprado > 0) {
        $total_pagado = $total_comprado - $pendiente_total_real;
        $pct_pagado = ($total_pagado / $total_comprado) * 100;
        if ($pct_pagado < 0) $pct_pagado = 0.0;
        if ($pct_pagado > 100) $pct_pagado = 100.0;
    }
    ?>
    <div class="card" style="margin-top:.8rem; padding:1.2rem 1.4rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.6rem; flex-wrap:wrap; gap:.5rem;">
            <span style="font-size:.78rem; text-transform:uppercase; letter-spacing:.08em; color:var(--ink3); font-weight:700;">
                <i class="bi bi-shield-check"></i> Estado de Cartera del Grupo ADSO
            </span>
            <span style="font-family:'Fraunces',serif; font-size:1.15rem; font-weight:800; color:#1b5e20;">
                <?= number_format($pct_pagado, 1, ',', '.') ?>% Recaudado
            </span>
        </div>
        
        <!-- Contenedor de la barra -->
        <div style="width:100%; height:12px; background:rgba(148,91,53,.08); border-radius:10px; overflow:hidden; position:relative; border:1px solid var(--border);">
            <div style="width:<?= $pct_pagado ?>%; height:100%; background:linear-gradient(90deg, var(--c4) 0%, var(--c3) 50%, #2e7d32 100%); border-radius:10px; transition:width .6s cubic-bezier(0.4, 0, 0.2, 1);"></div>
        </div>
        
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.6rem; font-size:.78rem; color:var(--ink2); flex-wrap:wrap; gap:.5rem;">
            <span>Abonado: <strong>$<?= number_format($total_pagado, 0, ',', '.') ?> COP</strong></span>
            <span>Pendiente: <strong style="color:#c62828;">$<?= number_format($pendiente_total_real, 0, ',', '.') ?> COP</strong></span>
            <span>Total Histórico: <strong>$<?= number_format($total_comprado, 0, ',', '.') ?> COP</strong></span>
        </div>
    </div>

    <!-- ══ TARJETA PAGO INSTRUCTOR ══ -->
    <?php if ($es_instructor && $resumen_fin['pendiente_total'] > 0): ?>
    <div style="background:#fff;border:1.5px solid rgba(239,68,68,.25);border-radius:14px;box-shadow:var(--shadow);padding:1.1rem 1.4rem;display:flex;align-items:center;justify-content:space-between;gap:1rem;flex-wrap:wrap;">
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
            <span style="font-size:.72rem;color:var(--ink3);"><?= count($aprendices) ?> registrados</span>
        </div>
        <div class="tbl-wrap">
            <table class="gt" id="tabla-aprendices">
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <th>Pedidos</th>
                        <th>Total Comprado</th>
                        <th>Saldo Pendiente</th>
                        <th>Último Pedido</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($aprendices)): ?>
                    <tr><td colspan="6">
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
                    <tr class="fila-aprendiz" data-nombre="<?= strtolower(htmlspecialchars($a['nombre'])) ?>">
                        <td data-label="Aprendiz">
                            <div class="apr-name-wrap">
                                <div class="apr-avatar" style="background:<?= $color ?>;">
                                    <?php if (!empty($a['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($a['foto_url']) ?>" alt="">
                                    <?php else: ?>
                                        <?= strtoupper(substr($a['nombre'], 0, 1)) ?>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="apr-name"><?= htmlspecialchars($a['nombre']) ?></div>
                                    <div class="apr-contact">
                                        <?= $a['telefono'] ? htmlspecialchars($a['telefono']) : ($a['email'] ? htmlspecialchars($a['email']) : '—') ?>
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
                Pedidos de <?= htmlspecialchars($nombre_filtro) ?>
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
        Mostrando pedidos de <strong><?= htmlspecialchars($nombre_filtro) ?></strong>
        <a href="dashboard.php" title="Quitar filtro"><i class="bi bi-x-circle-fill"></i></a>
    </div>
    <?php endif; ?>

    <div class="filter-card">
        <form method="GET" class="filter-grid" id="form-filtros">
            <?php if ($f_aprendiz): ?>
                <input type="hidden" name="aprendiz_id" value="<?= $f_aprendiz ?>">
            <?php endif; ?>
            <?php if ($f_variedad): ?>
                <input type="hidden" name="variedad_id" id="hdn-variedad" value="<?= $f_variedad ?>">
            <?php else: ?>
                <input type="hidden" name="variedad_id" id="hdn-variedad" value="">
            <?php endif; ?>
            <div class="filter-group">
                <label>Estado</label>
                <select name="estado" class="filter-input">
                    <option value="">Todos</option>
                    <option value="pendiente"  <?= $f_estado==='pendiente' ?'selected':'' ?>>Pendiente</option>
                    <option value="confirmado" <?= $f_estado==='confirmado'?'selected':'' ?>>Confirmado</option>
                    <option value="rechazado"  <?= $f_estado==='rechazado' ?'selected':'' ?>>Rechazado</option>
                </select>
            </div>
            <div class="filter-group">
                <label>Ordenar por</label>
                <select name="orden" class="filter-input">
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
            Mostrando pedidos con <strong><?= htmlspecialchars($nombre_variedad) ?></strong>
            <a href="dashboard.php<?= $f_aprendiz ? '?aprendiz_id='.$f_aprendiz : '' ?>" title="Quitar filtro"><i class="bi bi-x-circle-fill"></i></a>
        </div>
        <?php endif; ?>

        <div style="margin-top:.75rem;padding-top:.75rem;border-top:1px solid var(--border);display:flex;justify-content:center;">
            <button type="button" class="btn-search-pan" onclick="abrirModalPan()">
                <i class="bi bi-basket2"></i> <span>Buscar por tipo de pan</span>
                <?php if ($nombre_variedad): ?>
                    <span class="badge-variedad-filtro"><?= htmlspecialchars($nombre_variedad) ?></span>
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
                        <td data-label="Creado por" style="font-weight:600;"><?= htmlspecialchars($p['nombre_creador'] ?? 'Yo') ?></td>
                        <td data-label="Para Entregar" style="font-weight:600;color:var(--c1);"><?= date('d/m/Y', strtotime($p['fecha_entrega'])) ?></td>
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
            <div>
                <div style="font-family:'Fraunces',serif;font-size:1.05rem;font-weight:800;color:var(--ink);">
                    <i class="bi bi-cash-coin" style="color:#2e7d32;"></i> Pagar por Nequi
                </div>
                <div style="font-size:.72rem;color:var(--ink3);margin-top:.1rem;">Selecciona los pedidos que vas a transferir ahora</div>
            </div>
            <button type="button" onclick="cerrarModalPagoInstructor()" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--border);background:#fff;color:var(--ink3);cursor:pointer;font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                <i class="bi bi-x-lg"></i>
            </button>
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
        <label style="display:flex;align-items:center;gap:.75rem;padding:.7rem .85rem;border-radius:10px;border:1.5px solid var(--border);cursor:pointer;background:#fff;">
            <input type="checkbox" class="chk-instr"
                   data-monto="<?= (float)$pp['total_estimado'] ?>"
                   checked
                   onchange="recalcularTotalInstructor()"
                   style="width:17px;height:17px;accent-color:#2e7d32;flex-shrink:0;">
            <div style="flex:1;min-width:0;">
                <div style="font-weight:700;color:var(--ink);font-size:.85rem;">
                    Pedido #<?= str_pad($pp['id_pedido'], 4, '0', STR_PAD_LEFT) ?>
                    <?php if (!empty($pp['nombre_creador'])): ?>
                        <span style="font-weight:500;color:var(--ink3);font-size:.75rem;">· <?= htmlspecialchars($pp['nombre_creador']) ?></span>
                    <?php endif; ?>
                </div>
                <div style="font-size:.71rem;color:var(--ink3);">
                    Entrega: <?= date('d/m/Y', strtotime($pp['fecha_entrega'])) ?>
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
            <a id="btn-ir-nequi" href="<?= htmlspecialchars($nequi_config['nequi_link_pago'] ?? '#') ?>" target="_blank" rel="noopener"
               style="flex:2;padding:.72rem;border-radius:10px;border:none;background:linear-gradient(135deg,#2e7d32,#1b5e20);color:#fff;font-family:inherit;font-size:.85rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:.4rem;text-decoration:none;">
                <i class="bi bi-phone-fill"></i> Ir a Nequi
                <?php if (!empty($nequi_config['nequi_titular'])): ?>
                    <span style="opacity:.7;font-weight:400;font-size:.72rem;">· <?= htmlspecialchars($nequi_config['nequi_titular']) ?></span>
                <?php endif; ?>
            </a>
        </div>

        <div style="padding:.6rem 1.2rem 1rem;font-size:.72rem;color:var(--ink3);text-align:center;line-height:1.5;">
            <i class="bi bi-info-circle"></i> Transfiere el total seleccionado por Nequi. El propietario confirmará el recibo.
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ══ MODAL BUSCAR POR PAN ══ -->
<div class="modal-backdrop" id="modal-pan" onclick="cerrarModalPan(event)">
    <div class="modal-box">
        <div class="modal-head">
            <h2><i class="bi bi-basket2-fill"></i> Buscar por tipo de pan</h2>
            <button class="modal-close" onclick="cerrarModalPan()"><i class="bi bi-x-lg"></i></button>
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
                   data-nombre="<?= strtolower(htmlspecialchars($v['nombre'])) ?>"
                   onclick="seleccionarVariedad(<?= $v['id_variedad'] ?>, event)">
                    <?php if (!empty($v['imagen'])): ?>
                        <img src="<?= APP_URL ?>/assets/img/panes/<?= htmlspecialchars($v['imagen']) ?>" alt="" class="var-img">
                    <?php else: ?>
                        <div class="var-img-placeholder">🍞</div>
                    <?php endif; ?>
                    <span class="var-nombre"><?= htmlspecialchars($v['nombre']) ?></span>
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
