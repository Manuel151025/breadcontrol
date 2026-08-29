// @ts-check
const { expect } = require('@playwright/test');

/**
 * Cuentas sembradas por sql/init/95_semilla_e2e.sql.
 *
 * La contrasena esta escrita aqui a proposito y no en una variable de entorno:
 * es una credencial de una base efimera que se crea y se destruye con el CI, y
 * esconderla solo dificultaria entender la prueba. Ninguna de estas cuentas
 * existe fuera de ese entorno.
 */
const CLAVE = 'PruebaE2E2026';

const CUENTAS = {
  propietario: { usuario: 'e2e_propietario', clave: CLAVE },
  empleado:    { usuario: 'e2e_empleado',    clave: CLAVE },
  cliente:     { usuario: 'e2e_cliente',     clave: CLAVE },
};

/**
 * Entra al back-office y deja el navegador en el tablero.
 * @param {import('@playwright/test').Page} page
 * @param {{usuario: string, clave: string}} cuenta
 */
async function entrarBackOffice(page, cuenta) {
  await page.goto('/login.php');
  await page.fill('#usuario', cuenta.usuario);
  await page.fill('#inp-clave', cuenta.clave);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/modules\/tablero/, { timeout: 20000 });
}

/**
 * Entra al portal de clientes y deja el navegador en el tablero del portal.
 * @param {import('@playwright/test').Page} page
 * @param {{usuario: string, clave: string}} cuenta
 */
async function entrarPortal(page, cuenta) {
  await page.goto('/portal/index.php');
  await page.fill('[name="usuario"]', cuenta.usuario);
  await page.fill('[name="contrasena"]', cuenta.clave);
  await page.click('button[type="submit"]');
  await expect(page).toHaveURL(/portal\/dashboard/, { timeout: 20000 });
}

module.exports = { CUENTAS, CLAVE, entrarBackOffice, entrarPortal };
