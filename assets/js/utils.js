// ============================================================
//  Configuracion publicada por la vista en <body data-*>
//
//  Sustituye a los bloques de script que definian `var appUrl` en cada vista.
//  Mientras exista un solo bloque de script sin src, la CSP necesita
//  'unsafe-inline' en script-src, y eso deja abierta la via mas comun de XSS
//  (punto 22 del anexo). Un atributo data-* no es codigo ejecutable.
//
//  Se mantiene el nombre global `appUrl` a proposito: ventas.js, produccion.js
//  y portal_nuevo_pedido.js ya lo usan en decenas de sitios, y renombrarlo
//  ahora mezclaria dos cambios sin relacion en la misma revision.
// ============================================================
var appUrl = (document.body && document.body.dataset.appUrl) || '';

function escHtml(str) {
  return String(str == null ? '' : str).replace(/[&<>"']/g, function(c) {
    return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
  });
}
