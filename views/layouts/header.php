<?php
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/sesion.php';
require_once __DIR__ . '/../../includes/boton_eliminar.php';
$user = usuarioActual();

/**
 * Devuelve 'on' si la peticion actual pertenece a la seccion indicada, para
 * marcar el elemento activo del menu.
 *
 * Antes leia una variable con `global $current`, y NUNCA funcionaba. El motivo
 * es sutil: `$current = $_SERVER['REQUEST_URI']` se asignaba en el cuerpo de
 * este archivo, pero los controladores lo incluyen desde DENTRO de un metodo
 * (por ejemplo TableroController::index() hace require_once de esta plantilla).
 * En ese caso la asignacion crea una variable local de ese metodo, no una
 * global, asi que el `global $current` de aqui no encontraba nada y recibia
 * null.
 *
 * Las consecuencias eran dos, y ninguna saltaba a la vista:
 *
 *   1. Ningun elemento del menu recibia la clase 'on': el resaltado de la
 *      seccion activa no ha funcionado nunca.
 *   2. strpos(null, ...) emite un aviso de obsolescencia en PHP 8.1+, y como
 *      esta llamada esta dentro de un atributo class="", el aviso se imprimia
 *      DENTRO del HTML. En produccion no se ve porque display_errors esta
 *      apagado, pero se registraba en cada carga de pagina y filtraba la ruta
 *      absoluta del servidor.
 *
 * Se lee $_SERVER directamente en vez de reintroducir una global: una funcion
 * que depende de una variable que alguien debe recordar definir en el ambito
 * correcto es precisamente lo que fallo.
 */
function navActive(string $path): string {
    $actual = $_SERVER['REQUEST_URI'] ?? '';
    return is_string($actual) && str_contains($actual, $path) ? 'on' : '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $page_title ?? 'BreadControl' ?> — BreadControl</title>
  <link rel="icon" type="image/png" sizes="32x32" href="<?= APP_URL ?>/assets/img/favicon-32.png">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/fuentes.css?v=<?= APP_VERSION ?>">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/bootstrap-icons.css?v=<?= APP_VERSION ?>">
<link href="<?= APP_URL ?>/assets/css/responsive.css?v=<?= APP_VERSION ?>" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/main.css?v=<?= APP_VERSION ?>" rel="stylesheet">
</head>
<?php
// La configuracion que necesita el JavaScript viaja en atributos data-* del
// <body>, no en un bloque de script incrustado. El motivo es la CSP: mientras
// exista un solo bloque de script sin src, script-src necesita 'unsafe-inline', y eso
// deja abierta la via mas comun de XSS (ver punto 22 del anexo). Un atributo
// data-* no es codigo, asi que ninguna politica tiene que permitirlo.
//
// Este <body> lo comparten TODAS las pantallas del back-office, asi que basta
// declararlo una vez aqui.
?>
<body data-app-url="<?= htmlspecialchars(APP_URL, ENT_QUOTES) ?>"
      data-logout-url="<?= htmlspecialchars(APP_URL . '/logout.php', ENT_QUOTES) ?>"
      data-weather-url="<?= htmlspecialchars((string) get_env('API_OPEN_METEO_URL', 'https://api.open-meteo.com/v1/forecast'), ENT_QUOTES) ?>">

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
        <div class="n-uname"><?= htmlspecialchars($user['nombre'] ?? '') ?></div>
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

<?php
// Aviso de la acción anterior ("Insumo creado", "Proveedor desactivado"...).
// Va aquí, en el layout, y no en cada vista: así ninguna pantalla se queda sin
// confirmar lo que acaba de hacer. mostrarMensaje() consume el aviso al leerlo.
if (function_exists('mostrarMensaje')) {
    echo mostrarMensaje();
}
?>

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

  <script src="<?= APP_URL ?>/assets/js/main.js?v=<?= APP_VERSION ?>" defer></script>
