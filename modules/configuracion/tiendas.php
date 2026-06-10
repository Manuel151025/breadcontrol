<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/funciones.php';

requerirPropietario();
$pdo  = getConexion();
$user = usuarioActual();

$msg_ok = '';
$msg_err = '';

// ============================================================
//  Acciones POST
// ============================================================

// (1) Crear nueva tienda beneficiaria
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_tienda'])) {
    $nombre   = trim($_POST['nombre'] ?? '');
    $telefono = preg_replace('/\D/', '', $_POST['telefono'] ?? '');
    if (strlen($telefono) > 15) {
        $telefono = substr($telefono, 0, 15);
    }

    if (mb_strlen($nombre) < 3) {
        $msg_err = 'El nombre de la tienda debe tener al menos 3 caracteres.';
    } elseif (mb_strlen($nombre) > 100) {
        $msg_err = 'El nombre de la tienda no puede superar los 100 caracteres.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cliente (nombre, tipo, telefono, activo, es_beneficiaria, fecha_creacion)
                VALUES (?, 'tienda', ?, 1, 1, NOW())
            ");
            $stmt->execute([$nombre, $telefono ?: null]);
            $msg_ok = 'Tienda beneficiaria creada correctamente.';
        } catch (Exception $e) {
            $msg_err = 'Error al crear: ' . $e->getMessage();
        }
    }
}

// (2) Marcar / desmarcar como beneficiaria un cliente existente
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_beneficiaria'])) {
    $id_cliente = (int)($_POST['id_cliente'] ?? 0);
    $accion = $_POST['toggle_beneficiaria'] === 'marcar' ? 1 : 0;
    try {
        $stmt = $pdo->prepare("UPDATE cliente SET es_beneficiaria = ? WHERE id_cliente = ?");
        $stmt->execute([$accion, $id_cliente]);
        $msg_ok = $accion ? 'Tienda marcada como beneficiaria.' : 'Tienda desmarcada.';
    } catch (Exception $e) {
        $msg_err = 'Error: ' . $e->getMessage();
    }
}

// ============================================================
//  Datos para la vista
// ============================================================
$beneficiarias = $pdo->query("
    SELECT c.*,
      (SELECT COUNT(*) FROM pedido_cliente WHERE id_tienda_destino = c.id_cliente) AS total_pedidos_destino
    FROM cliente c
    WHERE c.es_beneficiaria = 1 AND c.activo = 1
    ORDER BY c.nombre
")->fetchAll();

// Clientes que podrian ser beneficiarias (tipo tienda, activos, no beneficiarias)
$candidatos = $pdo->query("
    SELECT id_cliente, nombre, telefono
    FROM cliente
    WHERE tipo = 'tienda' AND activo = 1 AND es_beneficiaria = 0
    ORDER BY nombre
")->fetchAll();

$page_title = 'Tiendas Beneficiarias';
require_once __DIR__ . '/../../views/layouts/header.php';
?>
<style>
  :root{--c1:#945b35;--c2:#c8956e;--c3:#c67124;--c4:#e4a565;--c5:#ecc198;--cbg:#faf3ea;--ccard:#fff;--clight:#fdf6ee;--ink:#281508;--ink2:#6b3d1e;--ink3:#b87a4a;--border:rgba(148,91,53,.12);--shadow:0 1px 8px rgba(148,91,53,.09);--shadow2:0 4px 20px rgba(148,91,53,.15);--nav-h:64px;}
  *,*::before,*::after{box-sizing:border-box;}
  body{background:var(--cbg);color:var(--ink);font-family:'Plus Jakarta Sans',sans-serif;}

  @keyframes gradAnim{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}

  .page{margin-top:var(--nav-h);padding:1rem 1.1rem 2rem;}
  .pf-header{max-width:1000px;margin:0 auto 1.2rem;background:linear-gradient(125deg,#6b3211 0%,#945b35 18%,#c67124 35%,#e4a565 50%,#c67124 65%,#945b35 80%,#6b3211 100%);background-size:300% 300%;animation:gradAnim 8s ease infinite;border-radius:14px;padding:1rem 1.4rem;color:#fff;box-shadow:var(--shadow2);}
  .pf-header .pf-tag{font-size:.62rem;font-weight:700;text-transform:uppercase;letter-spacing:.2em;color:rgba(255,255,255,.7);margin-bottom:.25rem;}
  .pf-header h1{font-family:'Fraunces',serif;font-size:1.6rem;font-weight:800;color:#fff;margin:0;}
  .pf-header h1 em{font-style:italic;color:var(--c5);}
  .pf-header p{font-size:.78rem;color:rgba(255,255,255,.78);margin-top:.3rem;}

  .pf-container{max-width:1000px;margin:0 auto;display:grid;grid-template-columns:1.5fr 1fr;gap:1.2rem;}
  @media(max-width:850px){.pf-container{grid-template-columns:1fr;}}

  .pf-card{background:var(--ccard);border:1px solid var(--border);border-radius:14px;box-shadow:var(--shadow);padding:1.4rem;}
  .pf-card h2{font-family:'Fraunces',serif;font-size:1.15rem;color:var(--c1);margin-bottom:.3rem;display:flex;align-items:center;gap:.5rem;}
  .pf-subtitle{font-size:.82rem;color:var(--ink3);margin-bottom:1.2rem;}

  .pf-form-group{margin-bottom:1rem;}
  .pf-form-group label{display:block;font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--ink3);margin-bottom:.4rem;}
  .pf-form-group input{width:100%;padding:.75rem;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:.92rem;}
  .pf-form-group input:focus{outline:none;border-color:var(--c3);}

  .pf-btn{display:flex;align-items:center;justify-content:center;gap:.5rem;background:linear-gradient(135deg,var(--c3),var(--c1));color:#fff;border:none;border-radius:10px;padding:.85rem;font-size:.92rem;font-weight:700;cursor:pointer;transition:all .2s;width:100%;}
  .pf-btn:hover{transform:translateY(-2px);box-shadow:0 6px 18px rgba(198,113,36,.3);}

  .pf-btn-sm{padding:.4rem .8rem;font-size:.78rem;border-radius:8px;border:none;cursor:pointer;display:inline-flex;align-items:center;gap:.3rem;}
  .pf-btn-marcar{background:#e8f5e9;color:#1b5e20;border:1px solid #a5d6a7;}
  .pf-btn-marcar:hover{background:#c8e6c9;}
  .pf-btn-desmarcar{background:#ffebee;color:#c62828;border:1px solid #ef9a9a;}
  .pf-btn-desmarcar:hover{background:#ffcdd2;}

  .msg-ok{background:#e8f5e9;border:1px solid #a5d6a7;border-left:3px solid #2e7d32;border-radius:10px;padding:.7rem 1rem;font-size:.85rem;color:#1b5e20;font-weight:600;margin-bottom:1rem;}
  .msg-err{background:#ffebee;border:1px solid #ef9a9a;border-left:3px solid #c62828;border-radius:10px;padding:.7rem 1rem;font-size:.85rem;color:#c62828;margin-bottom:1rem;}

  .tienda-item{display:flex;justify-content:space-between;align-items:center;padding:.7rem 1rem;background:var(--clight);border:1px solid var(--border);border-radius:10px;margin-bottom:.6rem;}
  .tienda-info .nombre{font-weight:700;color:var(--ink);font-size:.92rem;}
  .tienda-info .meta{font-size:.72rem;color:var(--ink3);margin-top:.15rem;}
  .tienda-info .badge{background:#e3f2fd;color:#1565c0;padding:.1rem .45rem;border-radius:6px;font-size:.65rem;font-weight:700;margin-left:.4rem;text-transform:uppercase;letter-spacing:.05em;}

  .vacio{text-align:center;padding:1.5rem;color:var(--ink3);font-size:.85rem;background:var(--clight);border-radius:10px;border:1px dashed var(--border);}
  .vacio i{display:block;font-size:2rem;margin-bottom:.4rem;color:var(--c4);}

  .pf-back{display:inline-flex;align-items:center;gap:.4rem;color:var(--ink2);text-decoration:none;font-size:.82rem;font-weight:600;padding:.5rem .9rem;border-radius:10px;border:1px solid var(--border);background:var(--ccard);margin-bottom:1rem;}
  .pf-back:hover{background:var(--clight);border-color:var(--c3);color:var(--c1);}

  .help-box{background:#fff8e1;border-left:3px solid #ffb300;padding:.9rem 1rem;border-radius:10px;font-size:.82rem;color:#856404;line-height:1.5;margin-top:1rem;}
  .help-box strong{color:#5d4d10;}
</style>

<div class="page">
    <div class="pf-header">
        <div class="pf-tag">Configuración</div>
        <h1>Tiendas <em>Beneficiarias</em></h1>
        <p>Marca qué clientes son tiendas reales del barrio elegibles para recibir pedidos de aprendices.</p>
    </div>

    <div style="max-width:1000px;margin:0 auto;">
        <a href="<?= APP_URL ?>/modules/configuracion/perfil.php" class="pf-back">
            <i class="bi bi-arrow-left"></i> Volver a mi perfil
        </a>
    </div>

    <?php if ($msg_ok): ?>
        <div style="max-width:1000px;margin:0 auto;"><div class="msg-ok"><i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($msg_ok) ?></div></div>
    <?php endif; ?>
    <?php if ($msg_err): ?>
        <div style="max-width:1000px;margin:0 auto;"><div class="msg-err"><i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($msg_err) ?></div></div>
    <?php endif; ?>

    <div class="pf-container">
        <!-- Columna izquierda: lista de beneficiarias actuales + candidatos -->
        <div class="pf-card">
            <h2>
                <i class="bi bi-shop" style="color:var(--c3);"></i>
                Tiendas elegibles (<?= count($beneficiarias) ?>)
            </h2>
            <p class="pf-subtitle">Estas tiendas aparecen en el selector cuando un aprendiz hace un pedido.</p>

            <?php if (empty($beneficiarias)): ?>
                <div class="vacio">
                    <i class="bi bi-shop"></i>
                    Aún no hay tiendas beneficiarias.<br>
                    Crea una nueva o marca clientes existentes desde la lista de abajo.
                </div>
            <?php else: ?>
                <?php foreach ($beneficiarias as $b): ?>
                    <div class="tienda-item">
                        <div class="tienda-info">
                            <div class="nombre">
                                <?= htmlspecialchars($b['nombre']) ?>
                                <?php if ($b['total_pedidos_destino'] > 0): ?>
                                    <span class="badge"><?= $b['total_pedidos_destino'] ?> pedido<?= $b['total_pedidos_destino']>1?'s':'' ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="meta">
                                <?php if ($b['telefono']): ?><i class="bi bi-telephone"></i> <?= htmlspecialchars($b['telefono']) ?> · <?php endif; ?>
                                Registrada el <?= date('d/m/Y', strtotime($b['fecha_creacion'])) ?>
                            </div>
                        </div>
                        <form method="post" style="margin:0;">
                            <input type="hidden" name="id_cliente" value="<?= $b['id_cliente'] ?>">
                            <button type="submit" name="toggle_beneficiaria" value="desmarcar" class="pf-btn-sm pf-btn-desmarcar" onclick="return confirm('¿Quitar como tienda beneficiaria?')">
                                <i class="bi bi-x-circle"></i> Quitar
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (!empty($candidatos)): ?>
                <h3 style="font-family:'Fraunces',serif;font-size:1rem;color:var(--ink2);margin-top:1.5rem;margin-bottom:.4rem;">
                    Clientes existentes que puedes marcar
                </h3>
                <p class="pf-subtitle">Marca como beneficiaria a una tienda que ya tienes registrada.</p>
                <div style="max-height:260px;overflow-y:auto;">
                    <?php foreach ($candidatos as $c): ?>
                        <div class="tienda-item">
                            <div class="tienda-info">
                                <div class="nombre"><?= htmlspecialchars($c['nombre']) ?></div>
                                <div class="meta">
                                    <?php if ($c['telefono']): ?><i class="bi bi-telephone"></i> <?= htmlspecialchars($c['telefono']) ?><?php endif; ?>
                                </div>
                            </div>
                            <form method="post" style="margin:0;">
                                <input type="hidden" name="id_cliente" value="<?= $c['id_cliente'] ?>">
                                <button type="submit" name="toggle_beneficiaria" value="marcar" class="pf-btn-sm pf-btn-marcar">
                                    <i class="bi bi-check-circle"></i> Marcar
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna derecha: crear nueva tienda -->
        <div>
            <form method="post" class="pf-card">
                <h2>
                    <i class="bi bi-plus-circle" style="color:var(--c3);"></i>
                    Crear tienda nueva
                </h2>
                <p class="pf-subtitle">Agrega una tienda nueva al sistema y márcala como beneficiaria automáticamente.</p>

                <div class="pf-form-group">
                    <label>Nombre de la tienda</label>
                    <input type="text" name="nombre" required placeholder="Ej: Tienda ADSO Florencia" maxlength="100">
                </div>
                <div class="pf-form-group">
                    <label>Teléfono (opcional)</label>
                    <input type="text" name="telefono" placeholder="3001234567" maxlength="20">
                </div>

                <button type="submit" name="crear_tienda" class="pf-btn">
                    <i class="bi bi-shop-window"></i> Crear y marcar como beneficiaria
                </button>
            </form>

            <div class="help-box">
                <strong><i class="bi bi-info-circle"></i> ¿Cómo funciona?</strong><br>
                Cuando un cliente se registra en el portal y marca "Soy aprendiz SENA", al hacer un pedido verá un selector con todas las tiendas beneficiarias activas. El pedido quedará registrado a su nombre (quién pidió) y al nombre de la tienda elegida (a nombre de quién).
            </div>
        </div>
    </div>
</div>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const telInput = document.querySelector('input[name="telefono"]');
    if (telInput) {
        telInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
        telInput.addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
            if (this.value.length > 15) this.value = this.value.slice(0, 15);
        });
    }
});
</script>
</body></html>