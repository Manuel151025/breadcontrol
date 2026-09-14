// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * Recuperación de acceso por PIN, en el back-office y en el portal.
 *
 * Esta prueba existe por una vulnerabilidad real. El paso que verifica el PIN no
 * tenía límite de intentos: un fallo solo decía «PIN incorrecto» y dejaba volver a
 * probar, sin tope ni caducidad. El PIN son 6 dígitos —un millón de combinaciones—
 * y al acertar se fija una contraseña nueva, así que era una vía directa para tomar
 * la cuenta del propietario o la de cualquier cliente, incluida la del instructor.
 * Y el primer paso ayudaba a elegir víctima: decía si la cuenta existía y qué método
 * de recuperación tenía.
 *
 * Cada portal usa cuentas propias para cada escenario (ver 95_semilla_e2e.sql): la
 * de bloqueo queda bloqueada 15 minutos y no puede compartirse con la de
 * recuperación correcta sin que el resultado dependa del orden de las pruebas.
 *
 * Ojo con el límite por IP (20 fallos en 15 minutos, contando login y
 * recuperación): toda la suite corre desde la misma dirección. Por eso cada prueba
 * registra los mínimos fallos imprescindibles.
 */

const PIN = '246810';
const PIN_MALO = '000000';
const NUEVA_CLAVE = 'NuevaClave2026';

/**
 * Teclea un PIN en las seis casillas del paso 2.
 * @param {import('@playwright/test').Page} page
 * @param {string} pin
 */
async function teclearPin(page, pin) {
  const casillas = page.locator('.pin-digit');
  await expect(casillas).toHaveCount(6);
  for (let i = 0; i < 6; i++) {
    await casillas.nth(i).fill(pin[i]);
  }
}

/**
 * Configuración de cada portal: rutas, selectores y textos que difieren.
 */
const PORTALES = [
  {
    nombre: 'back-office',
    ruta: '/recuperar_pin.php',
    cuentaOk: 'e2e_recupera_ok',
    cuentaBloqueo: 'e2e_recupera_bloqueo',
    cuentaSinPin: 'e2e_propietario',
    campoNueva: '#p1',
    campoConfirma: '#p2',
    botonCambiar: 'button[name="cambiar_clave"]',
  },
  {
    nombre: 'portal de clientes',
    ruta: '/portal/recuperar_pass.php',
    cuentaOk: 'e2e_cli_recupera_ok',
    cuentaBloqueo: 'e2e_cli_recupera_bloqueo',
    cuentaSinPin: 'e2e_cliente',
    campoNueva: 'input[name="nueva"]',
    campoConfirma: 'input[name="confirm"]',
    botonCambiar: 'button[name="cambiar_pass"]',
  },
];

for (const portal of PORTALES) {
  test.describe(`Recuperación de acceso · ${portal.nombre}`, () => {

    /**
     * Paso 1 con el método PIN.
     * @param {import('@playwright/test').Page} page
     * @param {string} usuario
     */
    async function pedirPin(page, usuario) {
      await page.goto(portal.ruta);
      await page.fill('#usuario', usuario);
      await page.click('#m-pin');
      await page.click('button[name="verificar_usuario"]');
    }

    test('con el PIN correcto se llega a fijar una contraseña nueva', async ({ page }) => {
      // Va primera a propósito: si el límite de intentos se hubiera puesto de más,
      // lo que se rompería es justo esto, que el usuario legítimo recupere su cuenta.
      await pedirPin(page, portal.cuentaOk);
      await teclearPin(page, PIN);
      await page.click('button[name="verificar_codigo"]');

      await expect(page.locator(portal.campoNueva)).toBeVisible();
      await page.fill(portal.campoNueva, NUEVA_CLAVE);
      await page.fill(portal.campoConfirma, NUEVA_CLAVE);
      await page.click(portal.botonCambiar);

      await expect(page.locator('.msg-ok')).toBeVisible();
    });

    test('el primer paso no revela si la cuenta existe ni si tiene PIN', async ({ page }) => {
      // Una cuenta real SIN PIN y una cuenta que no existe tienen que llevar al
      // mismo sitio. Antes, la primera decía «no tiene PIN configurado» y la
      // segunda «Usuario no encontrado»: con eso se enumeraban cuentas y se sabía
      // cuáles atacar por PIN.
      await pedirPin(page, portal.cuentaSinPin);
      await expect(page.locator('.pin-digit')).toHaveCount(6);
      const textoReal = (await page.locator('.msg-err').count()) ? await page.locator('.msg-err').innerText() : '';

      await page.goto(portal.ruta + '?reiniciar=1');
      await pedirPin(page, 'cuenta_que_no_existe_jamas');
      await expect(page.locator('.pin-digit')).toHaveCount(6);
      const textoInexistente = (await page.locator('.msg-err').count()) ? await page.locator('.msg-err').innerText() : '';

      expect(textoInexistente).toBe(textoReal);

      // Y un PIN incorrecto sobre una cuenta inexistente falla con el mismo mensaje
      // que sobre una real: nada en la respuesta distingue un caso del otro.
      await teclearPin(page, PIN_MALO);
      await page.click('button[name="verificar_codigo"]');
      await expect(page.locator('.msg-err')).toContainText('PIN incorrecto');
    });

    test('cinco PIN incorrectos bloquean la cuenta, aunque luego se acierte', async ({ page }) => {
      await pedirPin(page, portal.cuentaBloqueo);

      for (let i = 0; i < 5; i++) {
        await teclearPin(page, PIN_MALO);
        await page.click('button[name="verificar_codigo"]');
        await expect(page.locator('.msg-err')).toContainText('PIN incorrecto');
      }

      // La prueba de verdad: el sexto intento lleva el PIN CORRECTO y aun así se
      // rechaza. Si solo se comprobara que los PIN malos fallan, pasaría también
      // sin ningún bloqueo.
      await teclearPin(page, PIN);
      await page.click('button[name="verificar_codigo"]');
      await expect(page.locator('.msg-err')).toContainText('Demasiados intentos');
      await expect(page.locator(portal.campoNueva)).toHaveCount(0);

      // Y volver a empezar no reinicia el contador: vive en base de datos y por
      // cuenta, no en la sesión. Descartar la cookie tampoco serviría.
      await page.goto(portal.ruta + '?reiniciar=1');
      await pedirPin(page, portal.cuentaBloqueo);
      await teclearPin(page, PIN);
      await page.click('button[name="verificar_codigo"]');
      await expect(page.locator('.msg-err')).toContainText('Demasiados intentos');
      await expect(page.locator(portal.campoNueva)).toHaveCount(0);
    });

  });
}

test('en el back-office, «Volver» desde el paso 2 regresa al paso 1', async ({ page }) => {
  // El botón mandaba a la misma página sin cerrar la recuperación, y como la
  // sesión seguía viva la página volvía a abrir el paso 2: no hacía nada.
  await page.goto('/recuperar_pin.php');
  await page.fill('#usuario', 'cuenta_para_volver');
  await page.click('#m-pin');
  await page.click('button[name="verificar_usuario"]');
  await expect(page.locator('.pin-digit')).toHaveCount(6);

  await page.click('.btn-back');
  await expect(page.locator('#usuario')).toBeVisible();
  await expect(page.locator('.pin-digit')).toHaveCount(0);
});
