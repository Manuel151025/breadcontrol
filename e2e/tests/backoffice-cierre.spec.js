// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarBackOffice } = require('./ayudas');

/**
 * Cierre de caja del dia.
 *
 * Es la operacion que consolida las ventas de la jornada, y CierreController
 * tampoco tiene ninguna prueba. Se comprueba la transicion de estado —de dia
 * abierto a CERRADO— y no solo que la pagina responda.
 *
 * Por orden alfabetico este archivo corre ANTES que backoffice-venta.spec.js,
 * de modo que hoy cierra una caja sin ventas. Se deja asi a proposito y no se
 * fuerza el orden: encadenar pruebas para que una dependa del estado que deja
 * otra las vuelve fragiles y dificiles de leer por separado. Cerrar una caja
 * con ventas dentro merece su propia prueba, con la venta creada en ella misma.
 */
test.describe('Cierre de caja', () => {

  test('confirmar el cierre deja el dia marcado como cerrado', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);
    await page.goto('/modules/cierre/index.php');

    // Antes de confirmar, el dia NO puede figurar como cerrado. Comprobarlo es
    // lo que hace significativa la asercion posterior: sin esto, un texto fijo
    // en la plantilla haria pasar la prueba sin que nada ocurriera.
    await expect(page.locator('body')).not.toContainText(/cerrad[oa]/i);

    await page.locator('button[name="confirmar_cierre"]').click();
    await page.waitForLoadState('domcontentloaded');

    await expect(page.locator('body')).toContainText(/cerrad[oa]/i);
  });

  test('el cierre exige sesion de propietario', async ({ page }) => {
    await page.goto('/modules/cierre/index.php');
    await expect(page.locator('#inp-clave')).toBeVisible();
  });

});
