// @ts-check
const { test, expect } = require('@playwright/test');
const { CLAVE, entrarPortal } = require('./ayudas');

/**
 * El flujo para el que existe el portal: los aprendices piden pan a la cuenta
 * ADSO, el instructor aprueba los pedidos en lote y los paga todos juntos.
 *
 * Hasta aquí ninguna prueba lo recorría en un navegador, y al ponerse en el
 * lugar de cada uno aparecieron tres fallos que ninguna otra prueba podía ver:
 *
 *   1. Un aprendiz podía SOBRESCRIBIR el pedido de otro aprendiz cambiando el
 *      campo oculto `edit_id` del formulario. El pedido seguía a nombre del
 *      otro, con su cupo y su deuda, pero con lo que eligió el primero.
 *   2. Un pedido que el aprendiz ya había cancelado se podía aprobar desde un
 *      tablero del instructor que siguiera abierto, y el sistema respondía
 *      «Pedido aprobado y programado con éxito».
 *   3. Con el pago del instructor en curso, el aprendiz seguía viendo los
 *      botones Editar y Cancelar, que al pulsarlos solo devolvían un error.
 *
 * Cuentas: ver el bloque «Instructor ADSO» de sql/init/95_semilla_e2e.sql.
 */

const INSTRUCTOR = { usuario: 'e2e_instructor', clave: CLAVE };
const APRENDIZ_A = { usuario: 'e2e_aprendiz_a', clave: CLAVE };
const APRENDIZ_B = { usuario: 'e2e_aprendiz_b', clave: CLAVE };

/**
 * Fecha AAAA-MM-DD dentro de N días.
 *
 * Con margen de sobra frente a la regla de las 48 horas y frente al desfase
 * entre la zona del runner (UTC) y la del servidor (Colombia).
 * @param {number} dias
 */
function fechaDentroDe(dias) {
  return new Date(Date.now() + dias * 86400000).toISOString().slice(0, 10);
}

/**
 * Llena el carrito con una variedad a través de la interfaz, como lo haría
 * alguien de verdad, sin tocar el `carrito_json` a mano.
 * @param {import('@playwright/test').Page} page
 * @param {number} cantidad
 */
async function llenarCarrito(page, cantidad) {
  await page.goto('/portal/nuevo_pedido.php');
  await page.locator('.price-tab').first().click();
  const tarjeta = page.locator('.prod-card').first();
  await expect(tarjeta).toBeVisible();
  await tarjeta.locator('.pc-action').click();
  await tarjeta.locator('.pf-cant').fill(String(cantidad));
  await tarjeta.locator('.pf-add').click();
  await expect(page.locator('#cart-count')).toHaveText('1');
}

/**
 * El aprendiz pide para la cuenta ADSO, que es la opción por defecto: sin
 * fecha, porque la entrega la fija el instructor al aprobar.
 * @param {import('@playwright/test').Page} page
 * @param {number} cantidad
 */
async function pedirParaAdso(page, cantidad) {
  await llenarCarrito(page, cantidad);
  await expect(page.locator('#btn-adso')).toHaveClass(/active/);
  await page.locator('#form-pedido button[type="submit"]').click();
  await expect(page.locator('.msg-success')).toContainText('Pedido enviado');
}

/**
 * Id del pedido más reciente del tablero.
 * @param {import('@playwright/test').Page} page
 */
async function ultimoPedido(page) {
  await page.goto('/portal/dashboard.php');
  const href = await page.locator('a[href*="detalle_pedido.php?id="]').first().getAttribute('href');
  const m = /id=(\d+)/.exec(href || '');
  expect(m, 'el tablero debe enlazar el pedido recién creado').not.toBeNull();
  return Number(/** @type {RegExpExecArray} */ (m)[1]);
}

/**
 * Marca un pedido en el tablero del instructor y lo aprueba en lote.
 * @param {import('@playwright/test').Page} page
 * @param {number} idPedido
 */
async function aprobarEnLote(page, idPedido) {
  await page.locator(`input.chk-approve[value="${idPedido}"]`).check();
  await page.fill('#bulk-fecha', fechaDentroDe(5));
  await page.fill('#bulk-hora', '09:00');
  page.once('dialog', (d) => d.accept());
  await page.getByRole('button', { name: 'Aprobar Seleccionados' }).click();
  await page.waitForLoadState('domcontentloaded');
}

test.describe('Aprendiz e instructor', () => {

  test('el aprendiz pide, el instructor aprueba en lote y paga todo junto', async ({ page }) => {
    // ── Aprendiz: pide para ADSO ──
    await entrarPortal(page, APRENDIZ_A);
    await pedirParaAdso(page, 3);
    const id = await ultimoPedido(page);

    await page.goto(`/portal/detalle_pedido.php?id=${id}`);
    await expect(page.locator('body')).toContainText('Por definir');

    // ── Instructor: lo ve por aprobar, lo aprueba con fecha ──
    await page.context().clearCookies();
    await entrarPortal(page, INSTRUCTOR);
    await expect(page.locator(`input.chk-approve[value="${id}"]`)).toBeVisible();
    await aprobarEnLote(page, id);
    await expect(page.locator('body')).toContainText('Pedido aprobado y programado con éxito');
    await expect(page.locator(`input.chk-approve[value="${id}"]`)).toHaveCount(0);

    // ── Instructor: paga el consolidado por Nequi ──
    await page.goto('/portal/pagar_consolidado.php');
    await page.locator('button[name="generar_pago"]').click();
    await expect(page.locator('a.btn-pagar')).toHaveAttribute('href', 'https://checkout.wompi.co/l/E2E_PRUEBA');

    // ── Aprendiz: con el pago en curso ya no se le ofrece lo que no puede hacer ──
    // Antes veía Editar y Cancelar, y pulsarlos solo devolvía un error.
    await page.context().clearCookies();
    await entrarPortal(page, APRENDIZ_A);
    await page.goto(`/portal/detalle_pedido.php?id=${id}`);
    await expect(page.locator('.t-val')).toHaveText('$3.000');
    await expect(page.locator('a.btn-edit')).toHaveCount(0);
    await expect(page.locator('button.btn-cancel')).toHaveCount(0);
  });

  test('un aprendiz no puede sobrescribir el pedido de otro cambiando el formulario', async ({ page }) => {
    await entrarPortal(page, APRENDIZ_B);
    await pedirParaAdso(page, 2);
    const idDeB = await ultimoPedido(page);

    // A arma su propio carrito y, antes de enviarlo, pone en el campo oculto
    // `edit_id` el número del pedido de B. Es lo único que hace falta: el
    // número se adivina, porque los pedidos son consecutivos.
    await page.context().clearCookies();
    await entrarPortal(page, APRENDIZ_A);
    await llenarCarrito(page, 7);
    await page.locator('input[name="edit_id"]').evaluate((el, valor) => {
      /** @type {HTMLInputElement} */ (el).value = String(valor);
    }, idDeB);
    await page.locator('#form-pedido button[type="submit"]').click();
    await expect(page.locator('.msg-error')).toContainText('No puedes editar este pedido');

    // Y el pedido de B sigue siendo el que B pidió.
    await page.context().clearCookies();
    await entrarPortal(page, APRENDIZ_B);
    await page.goto(`/portal/detalle_pedido.php?id=${idDeB}`);
    await expect(page.locator('.t-val')).toHaveText('$2.000');
  });

  test('un pedido que el aprendiz ya canceló no se aprueba desde un tablero abierto', async ({ browser }) => {
    const aprendiz = await (await browser.newContext()).newPage();
    const instructor = await (await browser.newContext()).newPage();

    await entrarPortal(aprendiz, APRENDIZ_A);
    await pedirParaAdso(aprendiz, 2);
    const id = await ultimoPedido(aprendiz);

    // El instructor abre su tablero y ve el pedido por aprobar...
    await entrarPortal(instructor, INSTRUCTOR);
    await expect(instructor.locator(`input.chk-approve[value="${id}"]`)).toBeVisible();

    // ...mientras tanto, el aprendiz se arrepiente y lo cancela.
    await aprendiz.goto(`/portal/detalle_pedido.php?id=${id}`);
    aprendiz.once('dialog', (d) => d.accept());
    await aprendiz.locator('button.btn-cancel').click();
    await expect(aprendiz).toHaveURL(/msg=cancelado/);

    // El instructor, sin recargar, aprueba lo que tiene en pantalla.
    await aprobarEnLote(instructor, id);
    await expect(instructor.locator('body')).not.toContainText('Pedido aprobado y programado con éxito');
    await expect(instructor.locator('body')).toContainText('ya fueron procesados');

    // El pedido sigue cancelado, y con el motivo que le dio el aprendiz.
    await aprendiz.goto(`/portal/detalle_pedido.php?id=${id}`);
    await expect(aprendiz.locator('.msg-box')).toContainText('Cancelado por el cliente');

    await aprendiz.context().close();
    await instructor.context().close();
  });

});
