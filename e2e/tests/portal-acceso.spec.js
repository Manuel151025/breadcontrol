// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarPortal } = require('./ayudas');

/**
 * Acceso y registro en el Portal de Clientes.
 *
 * PortalAuthController es la clase peor puntuada del proyecto (indice de
 * mantenibilidad 25,5, complejidad 159 en 500 lineas logicas) y no la ejecuta
 * ninguna prueba de PHPUnit. Estos recorridos son lo unico que la toca.
 */
test.describe('Portal de clientes', () => {

  test('un cliente sembrado entra a su tablero', async ({ page }) => {
    await entrarPortal(page, CUENTAS.cliente);
  });

  test('una contrasena incorrecta no deja pasar al portal', async ({ page }) => {
    await page.goto('/portal/index.php');
    await page.fill('[name="usuario"]', CUENTAS.cliente.usuario);
    await page.fill('[name="contrasena"]', 'contrasena-que-no-es');
    await page.click('button[type="submit"]');

    await expect(page.locator('[name="contrasena"]')).toBeVisible();
    await expect(page).not.toHaveURL(/portal\/dashboard/);
  });

  test('sin sesion no se entra al tablero del portal', async ({ page }) => {
    await page.goto('/portal/dashboard.php');
    // requireCliente() devuelve a index.php. Se exige ver el formulario, no
    // solo no estar en el tablero.
    await expect(page.locator('[name="contrasena"]')).toBeVisible();
  });

  test('se puede registrar una cuenta nueva y entrar con ella', async ({ page }) => {
    // Usuario distinto en cada ejecucion: la columna es UNIQUE, y reutilizarlo
    // haria fallar la segunda pasada por un motivo que no es el que se prueba.
    const sufijo = Date.now().toString().slice(-8);
    const usuario = `e2e_nuevo_${sufijo}`;

    await page.goto('/portal/registro.php');
    await page.fill('[name="nombre"]', `Cliente nuevo ${sufijo}`);
    await page.fill('[name="usuario"]', usuario);
    await page.fill('[name="contrasena"]', 'PruebaE2E2026');
    await page.fill('[name="email"]', `${usuario}@ejemplo.test`);
    await page.fill('[name="telefono"]', '3001112233');
    await page.click('button[type="submit"]');

    // El registro deja la cuenta creada; el acceso posterior es lo que prueba
    // que quedo utilizable de verdad y no a medias.
    await page.goto('/portal/index.php');
    await page.fill('[name="usuario"]', usuario);
    await page.fill('[name="contrasena"]', 'PruebaE2E2026');
    await page.click('button[type="submit"]');

    await expect(page).not.toHaveURL(/portal\/index/);
  });

});
