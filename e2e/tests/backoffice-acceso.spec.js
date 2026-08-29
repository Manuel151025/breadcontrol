// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarBackOffice } = require('./ayudas');

/**
 * Acceso al back-office.
 *
 * Comprueba en caliente lo que las pruebas estructurales solo ven en el codigo:
 * que la guarda de sesion echa de verdad a quien no ha entrado, y que un fallo
 * de acceso no revela si la cuenta existe.
 */
test.describe('Acceso al back-office', () => {

  test('un propietario con credenciales validas llega al tablero', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);
    await expect(page).toHaveURL(/modules\/tablero/);
  });

  test('una contrasena incorrecta no deja pasar', async ({ page }) => {
    await page.goto('/login.php');
    await page.fill('#usuario', CUENTAS.propietario.usuario);
    await page.fill('#inp-clave', 'contrasena-que-no-es');
    await page.click('button[type="submit"]');

    // Asercion positiva: no basta con "no llego al tablero" —eso lo cumpliria
    // tambien un 404—, tiene que seguir viendose el formulario de acceso.
    await expect(page.locator('#inp-clave')).toBeVisible();
    await expect(page).not.toHaveURL(/modules\/tablero/);
  });

  test('el mensaje de error no revela si la cuenta existe', async ({ page }) => {
    // Es el punto C05 del informe tecnico: el mensaje debe ser el mismo tanto si
    // el usuario existe como si no. Si difieren, un atacante puede enumerar
    // cuentas validas antes de intentar adivinar contrasenas.
    const mensaje = async (usuario) => {
      await page.goto('/login.php');
      await page.fill('#usuario', usuario);
      await page.fill('#inp-clave', 'contrasena-que-no-es');
      await page.click('button[type="submit"]');
      await page.waitForLoadState('domcontentloaded');
      return (await page.locator('body').innerText()).toLowerCase();
    };

    const conCuentaReal = await mensaje(CUENTAS.propietario.usuario);
    const conCuentaFalsa = await mensaje('cuenta_que_no_existe_jamas');

    // No se compara el texto entero —puede traer la hora o el clima— sino que
    // ninguno de los dos delate la diferencia.
    for (const texto of [conCuentaReal, conCuentaFalsa]) {
      expect(texto).not.toContain('no existe');
      expect(texto).not.toContain('usuario no encontrado');
      expect(texto).not.toContain('no registrado');
    }
  });

  test('sin sesion no se entra a un modulo del back-office', async ({ page }) => {
    await page.goto('/modules/ventas/index.php');
    // requerirPropietario() redirige al login. Se exige ver el formulario de
    // acceso, no solo "no estar en ventas": un error del servidor tambien
    // cumpliria esa condicion sin que la guarda hubiera hecho nada.
    await expect(page.locator('#inp-clave')).toBeVisible();
    await expect(page).not.toHaveURL(/modules\/ventas/);
  });

});
