// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * ════════════════════════════════════════════════════════════
 *  Configuracion de Playwright — BreadControl
 *
 *  Estas pruebas cubren el hueco que ninguna otra herramienta del proyecto
 *  alcanza: controllers/ esta al 0,0 % de cobertura y views/ tiene 12.346
 *  lineas que jamas ejecuta una prueba. No es que las pruebas de PHPUnit esten
 *  mal escritas: es que un controlador hace header(), exit y renderiza una
 *  plantilla, y eso solo se comprueba de verdad con un navegador.
 *
 *  Corren SIEMPRE contra una instancia efimera, nunca contra produccion: estas
 *  pruebas crean y modifican datos.
 * ════════════════════════════════════════════════════════════
 */
module.exports = defineConfig({
  testDir: './tests',

  // Sin paralelismo entre archivos: comparten una sola base de datos, y dos
  // recorridos escribiendo a la vez producirian fallos que no son del codigo.
  fullyParallel: false,
  workers: 1,

  // En CI, un `test.only` olvidado haria pasar la suite ejecutando una prueba.
  forbidOnly: !!process.env.CI,

  // Un reintento en CI. No es para tapar pruebas inestables —si una falla
  // siempre, sigue fallando— sino para no dar por rota una rama por un tiempo
  // de espera agotado en un runner cargado.
  retries: process.env.CI ? 1 : 0,

  // El reportero 'github' no es adorno: publica cada fallo como anotacion de
  // Actions, y una anotacion se puede consultar sin credenciales del
  // repositorio, mientras que el registro de la ejecucion no. Sin el, saber que
  // recorrido fallo obliga a abrir el registro a mano o a descargar el informe.
  reporter: process.env.CI
    ? [['list'], ['github'], ['html', { outputFolder: 'informe', open: 'never' }]]
    : [['list']],

  use: {
    baseURL: process.env.E2E_BASE_URL || 'http://127.0.0.1:8099',
    // Rastro y captura solo cuando algo falla: en verde no aportan y ocupan.
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
    actionTimeout: 10000,
    navigationTimeout: 20000,
  },

  // Solo Chromium. La aplicacion no usa nada especifico de motor y multiplicar
  // por tres navegadores triplicaria el tiempo del CI sin encontrar mas fallos.
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
