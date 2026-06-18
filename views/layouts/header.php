<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/sesion.php';
$user = usuarioActual();

$current = $_SERVER['REQUEST_URI'];
function navActive($path) {
    global $current;
    return strpos($current, $path) !== false ? 'on' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
  <title><?= $page_title ?? 'BreadControl' ?> — BreadControl</title>
  <link rel="icon" type="image/png" href="<?= APP_URL ?>/assets/img/logo.png">
  <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,wght@0,600;0,800;1,600&family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/responsive.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/main.css" rel="stylesheet">
</head>
<body>

<nav id="main-nav">
  <!-- LOGO -->
  <a href="<?= APP_URL ?>/modules/tablero/index.php" class="n-logo">
    <img src="<?= APP_URL ?>/assets/img/logo.png"
         onerror="this.style.display='none'"
         alt="BreadControl" class="n-logo-img">
    <div>
      <div class="n-logo-name">BreadControl</div>
      <div class="n-logo-sub">Sistema de gestión</div>
    </div>
  </a>

  <div class="n-sep"></div>

  <!-- MENÚ -->
  <div class="n-menu" id="n-menu">
    <a href="<?= APP_URL ?>/modules/tablero/index.php"    class="n-item <?= navActive('/tablero') ?>"><i class="bi bi-speedometer2"></i><span class="n-lbl">Tablero</span></a>
    <a href="<?= APP_URL ?>/modules/inventario/index.php" class="n-item <?= navActive('/inventario') ?>"><i class="bi bi-box-seam-fill"></i><span class="n-lbl">Inventario</span></a>
    <a href="<?= APP_URL ?>/modules/produccion/index.php" class="n-item <?= navActive('/produccion') ?>"><i class="bi bi-fire"></i><span class="n-lbl">Producción</span></a>
    <a href="<?= APP_URL ?>/modules/ventas/index.php"     class="n-item <?= navActive('/ventas') ?>"><i class="bi bi-bag-fill"></i><span class="n-lbl">Ventas</span></a>
    <a href="<?= APP_URL ?>/modules/pedidos_clientes/index.php" class="n-item <?= navActive('/pedidos_clientes') ?>"><i class="bi bi-inbox-fill"></i><span class="n-lbl">Pedidos</span></a>
    <a href="<?= APP_URL ?>/modules/recetas/index.php"    class="n-item <?= navActive('/recetas') ?>"><i class="bi bi-journal-richtext"></i><span class="n-lbl">Recetas</span></a>
    <a href="<?= APP_URL ?>/modules/compras/index.php"    class="n-item <?= navActive('/compras') ?>"><i class="bi bi-cart-fill"></i><span class="n-lbl">Compras</span></a>
    <a href="<?= APP_URL ?>/modules/finanzas/index.php"   class="n-item <?= navActive('/finanzas') ?>"><i class="bi bi-cash-stack"></i><span class="n-lbl">Finanzas</span></a>
    <a href="<?= APP_URL ?>/modules/gastos/index.php"     class="n-item <?= navActive('/gastos') ?>"><i class="bi bi-receipt-cutoff"></i><span class="n-lbl">Gastos</span></a>
    <a href="<?= APP_URL ?>/modules/cierre/index.php"     class="n-item <?= navActive('/cierre') ?>"><i class="bi bi-moon-stars-fill"></i><span class="n-lbl">Cierre del día</span></a>
    <!-- Solo visible en mobile: config + ciudad + logout -->
    <div class="n-menu-sep"></div>
    <a href="<?= APP_URL ?>/modules/configuracion/pin.php" class="n-menu-ciudad" style="text-decoration:none;">
      <i class="bi bi-key-fill"></i><span>Configurar PIN</span>
    </a>
    <button class="n-menu-ciudad" onclick="abrirModalCiudad()">
      <i class="bi bi-geo-alt-fill"></i><span>Cambiar ciudad</span>
    </button>
    <a href="<?= APP_URL ?>/logout.php" class="n-menu-logout">
      <i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span>
    </a>
  </div>

  <!-- DERECHA -->
  <div class="n-right">
    <span class="n-clock" id="nc">--:--</span>

    <!-- Botón ciudad (desktop) -->
    <button class="n-ciudad-btn" onclick="abrirModalCiudad()" title="Cambiar ciudad">
      <i class="bi bi-geo-alt-fill"></i>
      <span id="ciudad-lbl">Florencia</span>
    </button>

    <!-- Usuario (click va a perfil) -->
    <a href="<?= APP_URL ?>/modules/configuracion/perfil.php" class="n-user" style="text-decoration:none;cursor:pointer;" title="Mi Perfil">
      <div class="n-avatar"><?= strtoupper(substr($user['nombre'], 0, 1)) ?></div>
      <div>
        <div class="n-uname"><?= htmlspecialchars($user['nombre']) ?></div>
        <div class="n-urole">Propietario</div>
      </div>
    </a>

    <!-- Logout (desktop) -->
    <a href="<?= APP_URL ?>/logout.php" class="n-logout" title="Cerrar sesión">
      <i class="bi bi-box-arrow-right"></i>
    </a>

    <!-- Hamburguesa -->
    <button class="n-hamburger" id="n-ham" aria-label="Menú">
      <i class="bi bi-list" id="ham-ico"></i>
    </button>
  </div>
</nav>

<!-- Manual de Usuario -->
<a href="<?= APP_URL ?>/assets/docs/Manual_BreadControl.pdf" target="_blank" class="btn-manual" title="Manual de Usuario">
  <i class="bi bi-book-half"></i>
  <span class="manual-tooltip">Manual de Usuario</span>
</a>



<!-- MODAL CIUDAD -->
<div id="modal-ciudad" class="modal-ciudad-overlay">
  <div class="modal-ciudad-box">
    <div class="modal-ciudad-head">
      <span><i class="bi bi-geo-alt-fill"></i> Seleccionar ciudad</span>
      <button class="modal-ciudad-close" onclick="cerrarModalCiudad()" aria-label="Cerrar">✕</button>
    </div>
    <div class="modal-ciudad-search">
      <input type="text" id="ciudad-buscar" placeholder="Buscar ciudad o departamento…" oninput="filtrarCiudades(this.value)">
    </div>
    <div class="modal-ciudad-list" id="ciudad-lista"></div>
  </div>
</div>

  <script>
    window.BREADCONTROL_CONFIG = {
      appUrl: '<?= APP_URL ?>',
      logoutUrl: '<?= APP_URL ?>/logout.php'
    };
  </script>
  <script src="<?= APP_URL ?>/assets/js/main.js" defer></script>
