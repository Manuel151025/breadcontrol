// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarBackOffice } = require('./ayudas');

/**
 * Resaltado de la seccion activa en el menu.
 *
 * Esta prueba existe por un fallo concreto: navActive() leia una variable con
 * `global $current`, pero la asignacion ocurria dentro del metodo del
 * controlador que incluye la plantilla, no en el ambito global. Recibia null, y
 * el resaltado no funciono NUNCA. Ademas, strpos(null, ...) emitia un aviso de
 * obsolescencia que se imprimia dentro del atributo class.
 *
 * Es justo la clase de defecto que ninguna otra herramienta del proyecto podia
 * ver: PHPStan no analiza las vistas, PHPUnit no renderiza plantillas, y a
 * simple vista la pagina se ve bien. Hacia falta un navegador.
 */
test.describe('Navegacion del back-office', () => {

  const SECCIONES = [
    { ruta: '/modules/ventas/index.php',     etiqueta: 'Ventas' },
    { ruta: '/modules/inventario/index.php', etiqueta: 'Inventario' },
    { ruta: '/modules/cierre/index.php',     etiqueta: 'Cierre del día' },
  ];

  test('cada seccion resalta su propio elemento del menu, y solo el suyo', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);

    for (const seccion of SECCIONES) {
      await page.goto(seccion.ruta);
      const activos = await page.locator('.n-item.on').allInnerTexts();
      // "y solo el suyo" no es un adorno: cuando el aviso de obsolescencia se
      // colaba en el atributo class, la palabra "on" de «on line 10» convertia
      // los DIEZ elementos del menu en coincidencias. Comprobar solo que exista
      // alguno habria dado la prueba por buena con el fallo presente.
      expect(activos.map((t) => t.trim())).toEqual([seccion.etiqueta]);
    }
  });

  test('el menu no imprime avisos de PHP dentro de sus atributos', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);
    await page.goto('/modules/ventas/index.php');

    const clases = await page.locator('.n-item').evaluateAll(
      (els) => els.map((e) => e.getAttribute('class') || '')
    );
    for (const clase of clases) {
      expect(clase).not.toMatch(/Deprecated|Warning|Notice|Fatal error/i);
    }
  });

});
