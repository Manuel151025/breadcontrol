// @ts-check
const { test, expect } = require('@playwright/test');
const AxeBuilder = require('@axe-core/playwright').default;
const { CUENTAS, entrarBackOffice, entrarPortal } = require('./ayudas');

/**
 * Accesibilidad automatizada (axe-core) sobre las pantallas que ya recorre la
 * suite.
 *
 * Cierra con evidencia el punto 27 del anexo de limitaciones, que estaba en
 * «parcialmente resuelto» sin nada que lo midiera. La primera medición
 * (2026-09-01, nueve pantallas, criterios WCAG 2.0/2.1 nivel A y AA) encontró
 * **solo dos reglas incumplidas**, lo cual dice bastante del trabajo previo: no
 * hay ni una violación de `button-name`, `label` ni `aria-*`.
 *
 *   - `link-name` × 1  → corregido en esta misma tanda (la flecha de la portada).
 *   - `color-contrast` × 75 → 17 combinaciones de color distintas.
 *
 * axe comprueba lo que una máquina puede comprobar. No sustituye probar con
 * teclado y lector de pantalla: una página puede pasar axe entera y seguir
 * siendo inservible sin ratón.
 */

/**
 * Reglas que NO bloquean, con el motivo.
 *
 * `color-contrast` es un problema de PALETA, no de marcado: son 17
 * combinaciones de color repartidas por todo el sistema, algunas de texto
 * decorativo casi invisible (1,02:1). Arreglarlo es revisar el diseño visual
 * —una decisión con dueño— y no cabe en una prueba automática.
 *
 * Se tolera pero no se oculta: el desglose está en el punto 27 del anexo, y
 * cualquier regla NUEVA que aparezca sí rompe el CI.
 */
const REGLAS_TOLERADAS = ['color-contrast'];

/**
 * Analiza la página actual y falla si aparece una violación no tolerada.
 * @param {import('@playwright/test').Page} page
 * @param {string} pantalla
 */
async function revisar(page, pantalla) {
  const resultado = await new AxeBuilder({ page })
    .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
    .analyze();

  const bloqueantes = resultado.violations.filter((v) => !REGLAS_TOLERADAS.includes(v.id));

  const detalle = bloqueantes
    .map((v) => `  [${v.impact}] ${v.id}: ${v.help}\n    ${v.nodes.map((n) => n.target.join(' ')).join('\n    ')}`)
    .join('\n');

  expect(
    bloqueantes.map((v) => v.id),
    `Violaciones de accesibilidad nuevas en «${pantalla}»:\n${detalle}`
  ).toEqual([]);
}

test.describe('Accesibilidad', () => {

  test('las pantallas públicas no tienen violaciones nuevas', async ({ page }) => {
    for (const [pantalla, ruta] of [
      ['portada',          '/'],
      ['acceso',           '/login.php'],
      ['portal · acceso',  '/portal/index.php'],
      ['portal · registro','/portal/registro.php'],
    ]) {
      await page.goto(ruta);
      await revisar(page, pantalla);
    }
  });

  test('las pantallas del back-office no tienen violaciones nuevas', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);
    for (const [pantalla, ruta] of [
      ['tablero',       '/modules/tablero/index.php'],
      ['punto de venta','/modules/ventas/index.php'],
      ['cierre de caja','/modules/cierre/index.php'],
    ]) {
      await page.goto(ruta);
      await revisar(page, pantalla);
    }
  });

  test('las pantallas del portal no tienen violaciones nuevas', async ({ page }) => {
    await entrarPortal(page, CUENTAS.cliente);
    for (const [pantalla, ruta] of [
      ['portal · tablero',      '/portal/dashboard.php'],
      ['portal · nuevo pedido', '/portal/nuevo_pedido.php'],
    ]) {
      await page.goto(ruta);
      await revisar(page, pantalla);
    }
  });

});
