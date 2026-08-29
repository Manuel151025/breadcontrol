// @ts-check
const { test, expect } = require('@playwright/test');
const { CUENTAS, entrarPortal } = require('./ayudas');

/**
 * Crear un pedido desde el portal.
 *
 * Es el recorrido con mas piezas moviles del sistema: pestana de precio que
 * carga el catalogo por AJAX, carrito que se construye en JavaScript y viaja en
 * un campo oculto, y un POST que valida CSRF antes de guardar. Nada de eso lo
 * toca PHPUnit: PortalPedidoController esta al 0 % de cobertura.
 *
 * Se interactua con la interfaz de verdad —pulsar la pestana, anadir al
 * carrito— en vez de rellenar el campo `carrito_json` a mano. Rellenarlo
 * saltaria justo el JavaScript que se quiere probar y la prueba pasaria aunque
 * el carrito estuviera roto.
 */
test.describe('Pedido desde el portal', () => {

  test('un cliente crea un pedido y aparece en su tablero', async ({ page }) => {
    await entrarPortal(page, CUENTAS.cliente);

    // Cuantos pedidos hay ANTES. Comparar el antes con el despues es lo unico
    // que hace significativa la comprobacion final: el tablero lista tambien el
    // catalogo disponible, asi que buscar el nombre del producto pasaria
    // igualmente sin haber creado nada. (Comprobado: sobre una base recien
    // sembrada, el nombre del pan ya aparece en el tablero.)
    const contarPedidos = async () => {
      const texto = await page.locator('body').innerText();
      return (texto.match(/#\s?\d{4}/g) || []).length;
    };
    await page.goto('/portal/dashboard.php');
    const pedidosAntes = await contarPedidos();

    await page.goto('/portal/nuevo_pedido.php');

    // 1. Elegir precio: hasta aqui el catalogo esta vacio a proposito.
    await expect(page.locator('#prod-catalog')).toContainText('Selecciona un precio');
    await page.locator('.price-tab').first().click();

    // 2. El catalogo llega por AJAX.
    const tarjeta = page.locator('.prod-card').first();
    await expect(tarjeta).toBeVisible();

    // 3. Abrir la tarjeta y anadir al carrito.
    await tarjeta.locator('.pc-action').click();
    await tarjeta.locator('.pf-cant').fill('3');
    await tarjeta.locator('.pf-add').click();

    // 4. El carrito debe reflejarlo. Es la comprobacion que distingue "el JS
    //    funciona" de "el formulario se envio igualmente".
    await expect(page.locator('#cart-count')).toHaveText('1');

    // 5. Enviar el pedido.
    await page.locator('#form-pedido button[type="submit"]').click();
    await page.waitForLoadState('domcontentloaded');

    // 6. Queda registrado: el tablero muestra un pedido mas que antes.
    await page.goto('/portal/dashboard.php');
    expect(await contarPedidos()).toBe(pedidosAntes + 1);
  });

  test('no se puede enviar un pedido con el carrito vacio', async ({ page }) => {
    await entrarPortal(page, CUENTAS.cliente);
    await page.goto('/portal/nuevo_pedido.php');

    const enviar = page.locator('#form-pedido button[type="submit"]');
    // El boton nace deshabilitado; si algun dia deja de estarlo, el servidor
    // tiene que rechazarlo igualmente y esta prueba lo dira.
    await expect(enviar).toBeDisabled();
  });

});
