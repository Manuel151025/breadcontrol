<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nuevo Pedido — BreadControl</title>
    <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/portal_nuevo_pedido.css">
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
        <!-- ══ BANNER PROPIETARIO ══ -->
        <div class="wc-banner">
            <div class="wc-left">
                <div>
                    <div class="wc-greeting">Panadería BreadControl</div>
                    <div class="wc-name">Armar <em>Pedido</em></div>
                    <div class="wc-sub">Selecciona los productos y cantidades para tu pedido</div>
                </div>
            </div>
        </div>

        <div class="topbar">
            <div class="mod-titulo"><i class="bi bi-cart-plus"></i> <?= $ped_edit ? 'Editar Pedido #' . str_pad($ped_edit['id_pedido'], 4, '0', STR_PAD_LEFT) : 'Armar Pedido' ?></div>
            <a href="dashboard.php" class="btn-back"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        
        <div class="card">
            <?php if ($error): ?><div class="msg-error"><i class="bi bi-exclamation-triangle-fill"></i> <?= $error ?></div><?php endif; ?>
            <?php if ($success): ?>
                <div class="msg-success">
                    <i class="bi bi-check-circle-fill" style="font-size:3rem; margin-bottom:1rem; display:block;"></i>
                    <h3 style="font-family:'Fraunces',serif; font-size:1.5rem; margin-bottom:.5rem;">¡Pedido Confirmado!</h3>
                    <p><?= $success ?></p>
                    <a href="dashboard.php" class="btn-submit" style="margin: 1.5rem auto 0; text-decoration:none; width:fit-content;">Ver mis pedidos</a>
                </div>
            <?php else: ?>
            
            <div class="panel-izq">
                <div class="sec-sep">1. Selecciona el precio</div>
                <div class="price-tabs" id="price-tabs">
                  <?php foreach ($categorias as $c): ?>
                  <div class="price-tab" data-id="<?= $c['id_categoria'] ?>" data-precio="<?= $c['precio_unitario'] ?>" onclick="selPriceTab(this)">
                    $<?= number_format($c['precio_unitario'],0,',','.') ?>
                  </div>
                  <?php endforeach; ?>
                </div>

                <div class="sec-sep">2. Toca un pan para agregarlo</div>
                <div id="prod-catalog">
                  <div style="text-align:center;padding:1rem;font-size:.78rem;color:var(--ink3);">Selecciona un precio arriba</div>
                </div>
            </div>
            
            <div class="panel-der">
                <form method="post" id="form-pedido">
                    <input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">
                    <input type="hidden" name="carrito_json" id="carrito-json" value="[]">
                    <input type="hidden" name="bonif_json" id="bonif-json" value="[]">
                    <input type="hidden" name="edit_id" value="<?= $ped_edit['id_pedido'] ?? 0 ?>">
                    
                    <?php if ($es_aprendiz): ?>
                    <div class="ap-toggle-wrap">
                        <div class="ap-toggle-label">Este pedido es para:</div>
                        <div class="ap-toggle">
                            <button type="button" class="ap-opt adso-btn <?= $pedido_para_actual === 'adso' ? 'active' : '' ?>" id="btn-adso" onclick="setPedidoPara('adso')">
                                <i class="bi bi-building"></i> Cuenta ADSO
                            </button>
                            <button type="button" class="ap-opt personal-btn <?= $pedido_para_actual === 'personal' ? 'active' : '' ?>" id="btn-personal" onclick="setPedidoPara('personal')">
                                <i class="bi bi-person"></i> Mi cuenta
                            </button>
                        </div>
                        <div class="ap-toggle-hint" id="ap-hint" style="color:<?= $pedido_para_actual === 'adso' ? '#1565c0' : 'var(--c3)' ?>;">
                            <?= $pedido_para_actual === 'adso'
                                ? 'Tu pedido se cargará a la cuenta del instructor ADSO.'
                                : 'Tu pedido se cargará a tu propia cuenta y lo pagas tú directamente.' ?>
                        </div>
                        <input type="hidden" name="pedido_para" id="pedido_para_input" value="<?= $pedido_para_actual ?>">
                    </div>
                    <?php endif; ?>

                    <div id="delivery-datetime-section" style="display: <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? 'none' : 'grid' ?>; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                        <div>
                            <label style="font-size: .75rem; font-weight: 700; color: var(--ink3); text-transform: uppercase; display:block; margin-bottom:.3rem;">Fecha de entrega</label>
                            <input type="date" name="fecha_entrega" id="inp_fecha_entrega" min="<?= $min_fecha ?>" max="<?= date('Y-m-d', strtotime('+3 months')) ?>" value="<?= $ped_edit ? $edit_fecha : $min_fecha ?>" <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? '' : 'required' ?> style="width: 100%; padding: .6rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size:.9rem; color: var(--ink);">
                        </div>
                        <div>
                            <label style="font-size: .75rem; font-weight: 700; color: var(--ink3); text-transform: uppercase; display:block; margin-bottom:.3rem;">Hora de entrega</label>
                            <input type="time" name="hora_entrega" id="inp_hora_entrega" min="07:00" max="20:00" value="<?= $ped_edit ? $edit_hora : $hora_sugerida ?>" <?= ($es_aprendiz && $pedido_para_actual === 'adso') ? '' : 'required' ?> style="width: 100%; padding: .6rem; border: 1px solid var(--border); border-radius: 8px; font-family: inherit; font-size:.9rem; color: var(--ink);">
                        </div>
                    </div>

                    <div class="cart-section">
                        <div class="cart-title">🛒 Carrito <span class="cart-badge" id="cart-count">0</span></div>
                        <div id="cart-body"><div class="cart-empty">Agrega productos</div></div>
                        <div id="cart-total-bar" style="display:none;">
                            <div class="cart-total">
                                <span>Cobrados: <strong id="ct-und">0</strong></span>
                                <span class="ct-big">$<span id="ct-total">0</span></span>
                            </div>
                        </div>
                        
                        <!-- Bonificación tienda / Ñapa mostrador -->
                        <div id="bonif-panel" style="display:none;margin-top:.5rem;">
                            <div id="bonif-card" style="border-radius:10px;padding:.7rem .85rem;border:1px solid rgba(21,101,192,.18);background:rgba(21,101,192,.06);">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:.5rem;flex-wrap:wrap;gap:.3rem;">
                                    <span id="bonif-titulo" style="font-size:.65rem;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:#1565c0;">🏪 Bonificación tienda</span>
                                    <span id="bonif-credito-lbl" style="font-size:.78rem;font-weight:700;color:#1565c0;">Crédito: <strong>$<span id="bonif-credito">0</span></strong></span>
                                </div>
                                <div style="font-size:.68rem;color:#1565c0;margin-bottom:.4rem;" id="bonif-hint"></div>
                                <div id="bonif-varieties" style="max-height:180px;overflow-y:auto;margin-bottom:.4rem;">
                                    <div style="text-align:center;padding:.5rem;font-size:.75rem;color:#64b5f6;">Cargando...</div>
                                </div>
                                <div id="bonif-status" style="font-size:.75rem;font-weight:700;text-align:center;padding:.3rem;border-radius:7px;"></div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" name="guardar_pedido" class="btn-submit" id="btn-pedido" disabled>
                        <i class="bi bi-send-fill"></i> Enviar Pedido
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="<?= APP_URL ?>/assets/js/utils.js"></script>
    <script>
    // Configuración generada por PHP para portal_nuevo_pedido.js
    var appUrl = '<?= APP_URL ?>';
    var cart = <?= json_encode($cart_preload) ?>;
    var bonifPreload = <?= json_encode($bonif_preload) ?>;
    var catalogVars = [];
    var currentPrice = 0;
    var currentCatId = 0;
    var esAprendiz = <?= $es_aprendiz ? 'true' : 'false' ?>;
    var esTienda = <?= ($es_tienda && !$es_aprendiz) ? 'true' : 'false' ?>;
    var bonifCredito = 0;
    var bonifLoaded = false;
    var allVarieties = [];
    </script>
    <script src="<?= APP_URL ?>/assets/js/portal_nuevo_pedido.js"></script>
</body>
</html>
