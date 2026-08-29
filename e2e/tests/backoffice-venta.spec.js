// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarBackOffice } = require('./ayudas');

/**
 * Punto de venta del back-office.
 *
 * VentaController es la clase con peor indice de mantenibilidad del proyecto
 * (24,2, complejidad 145 en 403 lineas logicas) y esta al 0 % de cobertura. Este
 * recorrido es lo unico que lo ejecuta.
 *
 * La comprobacion no es que aparezca un texto en pantalla, sino que las unidades
 * disponibles BAJEN en la cantidad vendida. Es una asercion sobre la logica de
 * negocio: si la venta se pinta pero no descuenta del inventario, la prueba
 * falla, que es justo el fallo que importa.
 */
test.describe('Punto de venta', () => {

  /**
   * Unidades disponibles que muestra la categoria de precio.
   * @param {import('@playwright/test').Page} page
   */
  const disponibles = async (page) => {
    const texto = await page.locator('.cat-btn').first().innerText();
    const m = texto.match(/(\d+)\s*disp/);
    return m ? parseInt(m[1], 10) : null;
  };

  test('registrar una venta descuenta las unidades disponibles', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);
    await page.goto('/modules/ventas/index.php');

    const antes = await disponibles(page);
    expect(antes).not.toBeNull();
    // Si la semilla no dejara produccion del dia, la categoria saldria con 0 y
    // el recorrido fallaria por falta de datos en vez de por un defecto. Se
    // comprueba explicitamente para que el motivo del fallo no se confunda.
    expect(antes).toBeGreaterThan(5);

    // El POS abre en modo "Venta rapida": elegir precio, cantidad y registrar.
    await page.locator('.cat-btn').first().click();
    await expect(page.locator('.cat-btn').first()).toHaveClass(/active/);

    await page.fill('#inp-cantidad', '5');
    await page.locator('button[name="guardar_venta"]').click();
    await page.waitForLoadState('domcontentloaded');

    // La venta tiene que haber salido del inventario del dia.
    expect(await disponibles(page)).toBe(antes - 5);
  });

  test('el punto de venta exige sesion de propietario', async ({ page }) => {
    await page.goto('/modules/ventas/index.php');
    await expect(page.locator('#inp-clave')).toBeVisible();
  });

});
