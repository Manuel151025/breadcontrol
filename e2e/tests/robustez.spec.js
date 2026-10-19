// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarPortal, entrarBackOffice } = require('./ayudas');

/**
 * Lo que pasa cuando alguien usa la aplicación con prisa o sin cuidado: doble
 * clic, F5 sobre una pantalla de resultado, filas que se borran a medias.
 *
 * Cada caso corresponde a un fallo real encontrado al probar el sistema como lo
 * prueban los compañeros de clase, «tocando lo que no deben»:
 *
 *   1. F5 sobre «¡Pedido Confirmado!» (o un doble clic en Enviar) creaba otro
 *      pedido idéntico y consumía cupo del aprendiz.
 *   2. En la receta, la marca de «aplica merma» caía en el ingrediente
 *      equivocado: una casilla sin marcar no se envía, y las casillas sin
 *      índice se emparejaban por posición con los ingredientes.
 *   3. F5 sobre el aviso de una compra registrada la registraba otra vez:
 *      otro lote, el stock y el gasto del mes duplicados.
 */

/**
 * Letras al azar: los nombres de insumo no admiten dígitos.
 * @param {number} n
 */
function letras(n) {
  const abc = 'abcdefghijklmnopqrstuvwxyz';
  let s = '';
  for (let i = 0; i < n; i++) s += abc[Math.floor(Math.random() * abc.length)];
  return s;
}

/**
 * Cuántos pedidos lista el tablero del portal (cada uno muestra «#NNNN»).
 * @param {import('@playwright/test').Page} page
 */
async function contarPedidos(page) {
  await page.goto('/portal/dashboard.php');
  const texto = await page.locator('body').innerText();
  return (texto.match(/#\s?\d{4}/g) || []).length;
}

test.describe('Uso desordenado', () => {

  test('doble clic y F5 al enviar un pedido crean un solo pedido', async ({ page }) => {
    await entrarPortal(page, CUENTAS.cliente);
    const antes = await contarPedidos(page);

    await page.goto('/portal/nuevo_pedido.php');
    await page.locator('.price-tab').first().click();
    const tarjeta = page.locator('.prod-card').first();
    await expect(tarjeta).toBeVisible();
    await tarjeta.locator('.pc-action').click();
    await tarjeta.locator('.pf-cant').fill('2');
    await tarjeta.locator('.pf-add').click();
    await expect(page.locator('#cart-count')).toHaveText('1');

    let envios = 0;
    page.on('request', (r) => {
      if (r.method() === 'POST' && r.url().includes('nuevo_pedido.php')) envios++;
    });

    await page.locator('#btn-pedido').dblclick();
    await expect(page.locator('.msg-success')).toBeVisible();
    expect(envios, 'el doble clic no debe enviar el formulario dos veces').toBe(1);

    // F5 sobre la pantalla de resultado reenvía el mismo formulario.
    await page.reload();
    await expect(page.locator('.msg-success')).toBeVisible();

    expect(await contarPedidos(page)).toBe(antes + 1);
  });

  test('la merma de la receta queda en el ingrediente que se marcó', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);

    // Dos insumos propios, para que cada fila tenga un ingrediente distinto.
    const nombres = ['Harina merma ' + letras(8), 'Sal merma ' + letras(8)];
    for (const nombre of nombres) {
      await page.goto('/modules/inventario/index.php');
      await page.fill('#nombre', nombre);
      await page.selectOption('#unidad_medida', 'kg');
      await page.fill('#stock_actual', '10');
      await page.locator('button[name="guardar_insumo"]').click();
      await page.waitForLoadState('domcontentloaded');
    }

    await page.goto('/modules/recetas/index.php');
    const enlace = await page.locator('a[href*="editar_receta.php?id="]').first().getAttribute('href');
    const idProducto = Number(/id=(\d+)/.exec(enlace || '')?.[1]);
    expect(idProducto).toBeGreaterThan(0);
    await page.goto('/modules/recetas/editar_receta.php?id=' + idProducto);

    /**
     * Añade una fila, elige el insumo por nombre y le pone cantidad y merma.
     * @param {string} nombre
     * @param {boolean} merma
     */
    const agregar = async (nombre, merma) => {
      await page.locator('.btn-agregar').click();
      await page.fill('#modal-buscar', nombre);
      await page.locator('.ins-card', { hasText: nombre }).click();
      const fila = page.locator('.fila-ing').last();
      await fila.locator('.inp-cant').fill('100');
      if (merma) await fila.locator('.chk-merma').check();
      return fila.getAttribute('data-id');
    };

    // Se marca solo el SEGUNDO: con el emparejamiento por posición, la marca
    // acababa en la primera fila de la tabla.
    const idSinMerma = await agregar(nombres[1], false);
    const idConMerma = await agregar(nombres[0], true);

    await page.locator('#form-receta button[type="submit"]').click();
    await expect(page).toHaveURL(/recetas\/index\.php\?ok=1/);

    await page.goto('/modules/recetas/editar_receta.php?id=' + idProducto);
    await expect(page.locator(`.fila-ing[data-id="${idConMerma}"] .chk-merma`)).toBeChecked();
    await expect(page.locator(`.fila-ing[data-id="${idSinMerma}"] .chk-merma`)).not.toBeChecked();
  });

  test('F5 tras registrar una compra no la registra otra vez', async ({ page }) => {
    await entrarBackOffice(page, CUENTAS.propietario);

    // La semilla no trae proveedores: se crea uno por la interfaz.
    await page.goto('/modules/compras/proveedores.php');
    await page.fill('#nombre', 'Proveedor E2E ' + letras(6));
    await page.locator('form .btn-guardar').first().click();
    await page.waitForLoadState('domcontentloaded');

    await page.goto('/modules/compras/index.php');

    // El formulario de compra se completa con su propio JavaScript; aquí basta
    // con comprobar el patrón Post-Redirect-Get directamente sobre el servidor.
    const token = await page.locator('input[name="csrf_token"]').first().inputValue();
    // Insumo y proveedor se eligen con tarjetas; su id está en data-id.
    const insumo = await page.locator('.mcard[data-id]').first().getAttribute('data-id').catch(() => null);
    const proveedor = await page.locator('.pcard[data-id]').first().getAttribute('data-id').catch(() => null);
    test.skip(!insumo || !proveedor, 'La base no tiene insumos o proveedores para comprar.');

    // Hace dos días o más, y nunca domingo (no se compra en domingo). Con dos
    // días de margen da igual que el runner esté en UTC y el servidor en
    // Colombia: la fecha nunca queda «en el futuro» para el servidor. Se arma
    // con los componentes locales, no con toISOString(), que la pasa a UTC y
    // por la noche la corre al día siguiente.
    const fecha = new Date(Date.now() - 2 * 86400000);
    if (fecha.getDay() === 0) fecha.setDate(fecha.getDate() - 1);
    const dd = (n) => String(n).padStart(2, '0');
    const fechaTexto = `${fecha.getFullYear()}-${dd(fecha.getMonth() + 1)}-${dd(fecha.getDate())}`;
    const respuesta = await page.request.post('/modules/compras/index.php', {
      form: {
        csrf_token: token, guardar_compra: '1', id_insumo: String(insumo), id_proveedor: String(proveedor),
        fecha_compra: fechaTexto, cantidad: '5', num_bultos: '1', precio_bulto: '10000',
      },
      maxRedirects: 0,
    });
    // Redirige en vez de pintar el resultado: un F5 repite el GET, no el POST.
    // Si no redirige, el mensaje de la prueba muestra el aviso de error.
    const aviso = respuesta.status() === 302 ? '' : ((await respuesta.text()).match(/class="msg-err"[\s\S]*?<\/span>/) || [''])[0];
    expect(respuesta.status(), aviso.replace(/<[^>]+>/g, ' ')).toBe(302);
    expect(respuesta.headers()['location']).toMatch(/index\.php$/);

    // El aviso se ve una sola vez, al llegar; recargar ya no lo repite.
    await page.goto('/modules/compras/index.php');
    await expect(page.locator('.msg-ok')).toContainText('Compra registrada');
    await page.reload();
    await expect(page.locator('.msg-ok')).toHaveCount(0);
  });

});
