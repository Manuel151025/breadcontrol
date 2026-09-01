# Changelog — BreadControl

Todos los cambios notables del proyecto se documentan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

## [Sin publicar]

### Añadido

- **PHP 8.3 entra en la matriz del CI, en modo informativo.** La compilación,
  las pruebas unitarias y las de integración corren ahora sobre **8.2 y 8.3**.

  El motivo tiene fecha: **PHP 8.2 deja de recibir parches de seguridad el 31 de
  diciembre de 2026**. La migración no es opcional; lo que sí es una decisión es
  cuándo. Ver qué se rompe hoy convierte esa fecha en información en lugar de en
  un susto de diciembre — y ahora hay con qué verlo: 213 pruebas, 16 recorridos
  de navegador y mutación midiendo si esas pruebas verifican de verdad.

- **Y bloquea desde el mismo día.** Entró en modo informativo y **pasó limpio a
  la primera** en las tres ramas, así que no había motivo para dejarlo de
  adorno: a partir de aquí, una regresión que rompa 8.3 no entra.

  El plan era mantenerlo informativo mientras hiciera falta —un CI en rojo
  permanente enseña a ignorarlo, y este proyecto ya pasó quince días así—, pero
  no hizo falta ni una ejecución.

  **Con esto, el único punto pendiente que tenía fecha deja de ser un riesgo**:
  migrar a 8.3 pasa a ser un cambio de versión en el `Dockerfile` cuando se
  decida, con 213 pruebas y 16 recorridos vigilando que siga funcionando.

  `fail-fast: false` para ver siempre las dos ramas: saber que 8.3 falla no debe
  costar el resultado de 8.2, ni al revés.

- **Solo tres de los once jobs llevan matriz.** Matrizarlos todos doblaría el
  tiempo del CI sin aportar señal nueva: lo que se quiere saber es si el código
  *corre* en 8.3, y eso lo dicen la sintaxis y las dos suites. La cobertura, la
  mutación o el análisis de seguridad no cambian de respuesta por la versión del
  intérprete.

---

### Cambiado

- **El cupo semanal de un aprendiz pasa de un tope de $100.000 a $20.000.**

  Los $20.000 eran hasta ahora el valor **inicial** —`cupo_semanal DEFAULT
  20000.00` en el esquema, y el mismo importe al canjear el código de
  aprendiz—, mientras que el tope real era `ReglasPortal::CUPO_MAXIMO = 100000`.
  Un instructor podía subir el cupo hasta cinco veces el valor de partida. Ahora
  el cupo es un tope fijo, no un punto de partida ajustable hacia arriba.

- **El número vive en un solo sitio.** Antes se repetía a mano en cuatro:
  la constante, el mensaje de error (`'... entre $0 y $100.000 COP.'`) y el
  `max` de los dos formularios —incluido un recorte en JavaScript que lo
  mencionaba dos veces más—. Todos derivan ya de `ReglasPortal::CUPO_MAXIMO`,
  que es como se evita que vuelvan a decir cosas distintas.

- Los casos límite de la prueba se calculan también desde la constante, **con
  una excepción deliberada**: una aserción ancla el valor en 20.000. Si todo se
  derivara, devolver el tope a $100.000 no rompería ninguna prueba y el cambio
  de regla pasaría inadvertido.

- **Nota de operación:** bajar el tope no reescribe los datos existentes. Si
  algún aprendiz ya tenía un cupo por encima de $20.000, lo conserva —la
  validación solo actúa al guardar— pero su instructor no podrá volver a
  guardarlo. Para saber si hay alguno:

  ```sql
  SELECT id_cliente, nombre, cupo_semanal
  FROM cliente
  WHERE es_aprendiz = 1 AND cupo_semanal > 20000;
  ```

---

### Corregido

- **Un pedido vencido dejaba al cliente atrapado con él.** Si la fecha de entrega
  pasaba sin que nadie confirmara ni rechazara el pedido, el aprendiz **no podía
  retirarlo**: `puedeGestionarPedido()` exigía que faltaran 48 horas para la
  entrega, y esa misma regla gobernaba editar **y** cancelar.

  Ahora son dos reglas, porque no tienen el mismo límite:

  - **Editar** sigue exigiendo 48 horas de margen. Un pedido para una fecha que
    ya pasó no se edita: se cierra.
  - **Cancelar** se bloquea solo en las 48 horas **previas** a la entrega, cuando
    el pan puede estar ya en producción. Pasada la fecha ese motivo desaparece.

  La nueva `puedeCancelarPedido()` reutiliza `dentroDeLimite48h()`, que describe
  exactamente la ventana que debe bloquear: no vencido y a menos de 48 horas.

- Las tres pruebas se comprobaron contra la regla anterior antes de dar el
  cambio por bueno: la del pedido vencido falla con ella.

### Documentación

- **Nuevo punto 30 del anexo de limitaciones**, con el análisis completo del
  ciclo de vida de los pedidos. Lo que se resolvió, lo que sigue abierto —falta
  un estado que describa «se acabó el plazo sin que nadie decidiera»— y las dos
  consecuencias medidas por separado:

  - El **cupo semanal no se ve afectado**: el consumo filtra por
    `fecha_solicitud` dentro de la semana en curso, así que un pedido viejo no
    bloquea cupo futuro.
  - El **saldo pendiente sí**: `calcularSaldoPendiente()` filtra por
    `aprobado_instructor = 1` **sin ninguna condición de fecha**, de modo que un
    pedido aprobado y nunca entregado contaría como deuda indefinidamente.

  La consulta contra producción devolvió **cero filas**, y conviene ser preciso
  sobre qué prueba eso: que hoy no hay ningún caso, no que no pueda haberlo.
  Funciona porque la gente atiende los pedidos a tiempo, no porque el sistema lo
  garantice.

---

## [1.11.0] — 2026-09-01

Dos días dedicados a que el proyecto pueda demostrar lo que afirma. El CI pasó
de cinco verificaciones —y quince días en rojo sin que nadie lo notara— a once,
cada una comprobada por su capacidad de fallar antes de ponerla a bloquear.

Por el camino aparecieron tres defectos que ninguna herramienta anterior podía
ver: una prueba que llevaba catorce días sin poder ejecutarse, el resaltado del
menú que no ha funcionado nunca, y el redondeo del costeo sin verificar porque
todos los datos de prueba eran números redondos.


### Añadido

- **Las cabeceras de seguridad ya no dependen de que nadie las borre.**
  `scripts/verificar_cabeceras.sh` interroga una respuesta HTTP y comprueba, una
  por una, las correcciones R-01 a R-05 del informe técnico del 2026-08-12: la
  cookie de sesión con `Secure`, `HttpOnly` y `SameSite`; la CSP y sus
  directivas que de verdad cierran algo (`frame-ancestors`, `base-uri`,
  `object-src 'none'`, `form-action`); las cuatro cabeceras defensivas; la
  ausencia de `X-Powered-By`; HSTS de al menos un año; y que el servidor no
  publique su versión.

  El motivo es que hasta ahora esas once correcciones vivían en archivos de
  configuración —`.htaccess`, `Dockerfile` y el bloque `server` del Nginx del
  VPS— y **nada** impedía revertirlas por descuido. El `.htaccess` se edita por
  motivos de caché y compresión, no de seguridad, y una llave mal puesta
  desactiva el bloque entero sin que falle nada visible. Ya ocurrió algo de esa
  familia: la cookie salía sin `Secure` porque Traefik descartaba
  `X-Forwarded-Proto`, y se descubrió mirando la respuesta a mano.

  Se ejecuta en dos sitios, porque las correcciones viven en dos capas:

  - **En cada push** (job `cabeceras` del CI). Construye la imagen real,
    la levanta contra MySQL y verifica lo que aporta la aplicación. Corre con
    `APP_ENV=production` sobre HTTP plano a propósito: así comprueba que el
    atributo `Secure` lo sigue decidiendo el entorno y no la petición, que es
    exactamente la corrección de R-01 y lo que un cambio futuro podría deshacer
    sin querer.
  - **A diario y a mano** (flujo `Cabeceras en producción`). HSTS y
    `server_tokens off` no están en este repositorio: se escribieron a mano en
    el Nginx del VPS, que aloja 24 sitios. Este flujo es lo único que se daría
    cuenta si desaparecieran. Conviene lanzarlo tras tocar Nginx, Traefik o
    Dokploy.

  El `'unsafe-inline'` de la CSP se reporta como **aviso, no como fallo**: es el
  punto 22 de LIMITACIONES, una deuda ya decidida y documentada. Que aparezca en
  cada ejecución la mantiene a la vista sin romper el CI por algo que no es una
  regresión.

### Corregido

- **El CI llevaba rojo desde el 2026-08-12 y nadie lo veía.** La suite de
  integración fallaba en todas las ramas por `ReportePortalTest`, y la última
  ejecución en verde databa del 11 de agosto: catorce días en los que el CI
  seguía ahí, seguía ejecutándose y ya no informaba de nada.

  `setUp()` tomaba la categoría de precio con un `SELECT ... LIMIT 1`. En
  desarrollo funciona porque la base local tiene datos reales; en el CI la base
  nace del esquema más `90_semilla_ci.sql`, que solo siembra un insumo y un
  producto. `categoria_precio` está vacía, la consulta devolvía `false`, se
  insertaba como `0` y la clave foránea lo rechazaba con el error 1452.

  Lo grave no era la prueba caída, sino cuál: existe para vigilar el fallo de
  `only_full_group_by` que ya rompió el detalle de pedido en producción con
  MySQL 8. Reventaba en `setUp()`, antes de ninguna aserción, así que en esos
  catorce días no llegó a comprobarlo ni una vez. Un guardia que no vigila es
  peor que ninguno: ocupa el sitio del que sí lo haría.

  Ahora la prueba crea su propia categoría, como ya hacían `VentaModelTest` y
  `ProduccionRegistroTest`, y la transacción la revierte al terminar.

---

### Mantenimiento

- **`actions/checkout` sube de v4 a v7** en los siete pasos de los dos flujos.
  GitHub avisaba en cada ejecución de que la acción apunta a Node.js 20, ya
  retirado, y que la estaba forzando sobre Node.js 24. Hoy no rompía nada, pero
  el día que retiren esa compatibilidad fallarían los siete jobs a la vez: el
  `checkout` es el primer paso de todos, así que no habría ni uno que sobreviva.

  Se saltan tres versiones mayores, y las tres traían cambios de ruptura que se
  comprobaron uno a uno contra este repositorio antes de tocar nada:

  - **v5** pasa a Node 24 y exige un runner ≥ 2.327.1. Los siete jobs corren en
    `ubuntu-latest`, alojado por GitHub y siempre al día.
  - **v6** deja de guardar las credenciales en `.git/config` y las mueve a un
    archivo aparte. Ningún paso usa `GITHUB_TOKEN`, hace `push` ni lee esa
    configuración, así que no hay nada que dependa de dónde estén.
  - **v7** bloquea el checkout de una PR venida de un fork en
    `pull_request_target` y `workflow_run`. Aquí solo se usan `push`,
    `pull_request`, `schedule` y `workflow_dispatch`.

  Tampoco hay submódulos, que es la otra vía por la que estos saltos suelen
  doler.

---

### Pruebas

- **Inventario ejecutable de la autorización de los controladores**
  (`tests/Unit/AutorizacionControladoresTest.php`). Recorre con `token_get_all`
  los 53 métodos públicos de `controllers/` y `controllers/portal/` y exige que
  cada uno llame a una guarda —`requerirPropietario`, `requerirLogin` o
  `requireCliente`— o esté declarado público a propósito **con su motivo
  escrito**.

  Es una prueba estructural, no funcional, y conviene saber por qué: las
  guardas terminan en `exit`, así que invocarlas desde PHPUnit mataría el
  proceso de pruebas. Pero el riesgo real nunca fue que una guarda existente
  dejara de funcionar: es que alguien añada un endpoint y se olvide de ponerla.
  Eso es justo lo que ahora rompe el CI.

  El recuento de partida, medido y no supuesto: **53 métodos públicos, 49 con
  guarda y 4 públicos a propósito** (`landing`, `login`, `recuperarPin` y
  `logout`). No había ningún endpoint desprotegido.

- **Queda fijado qué alcanza el rol `empleado`**: tres endpoints de los 33 del
  back-office (`CompraController::proveedores`, `InventarioController::ajuste`
  y `ProduccionController::detalle`); los otros 30 exigen propietario. No se
  cambia nada de ese reparto, solo se deja escrito: el `enum` de `usuario` pone
  `empleado` por omisión, así que hoy un usuario nuevo nace sin acceso a casi
  nada, y hay que decidir si el rol se implementa o se retira. Con la prueba
  puesta, esa decisión aparecerá en el diff en vez de ocurrir sin que nadie la
  vea.

- Las cinco pruebas se comprobaron **por su capacidad de fallar**, no solo de
  pasar: un endpoint sin guarda, una guarda degradada de propietario a login y
  una entrada muerta en la lista de excepciones. Las tres se detectan. Es la
  lección del `ReportePortalTest` de esta misma tanda, que llevaba catorce días
  sin poder fallar y por eso no vigilaba nada.

---

### Medición

- **Primera medición de cobertura del proyecto: 12,07 %** (607 de 5030
  sentencias). Nuevo job `cobertura` con PCOV que corre las dos suites juntas
  contra MySQL, publica el informe HTML como artefacto y falla si la cobertura
  baja del 12 %.

  El número es bajo y conviene decir por qué sin adornarlo: las 204 pruebas son
  de modelos y helpers, y el `<source>` de `phpunit.xml` incluye también
  `controllers/`, que ninguna prueba toca. El desglose por capa que imprime
  `scripts/verificar_cobertura.php` lo deja a la vista, junto con los diez
  archivos peor cubiertos, que es la lista por la que hay que empezar.

  El 12 % es un **suelo, no una meta**: existe para que la cobertura no baje sin
  que nadie se entere. Subirlo conforme se escriban pruebas es parte del
  trabajo, no una promesa vaga.

- El porcentaje se publica además como **anotación de GitHub Actions**. La
  salida de un paso solo se lee abriendo el registro y con credenciales del
  repositorio; una anotación aparece en el resumen de la ejecución y en la
  pestaña de la PR, y se consulta sin ellas.

- Durante esa primera medición se descubrió y corrigió un fallo del propio
  script: recortaba las rutas del informe buscando el literal `/panaderia/`, que
  en el runner de GitHub —donde el repositorio se clona en
  `/home/runner/work/breadcontrol/breadcontrol/`— no aparece. La ruta quedaba
  absoluta, la capa salía como cadena vacía y el desglose se reducía a una fila
  sin nombre: el global era correcto, pero el reparto no decía nada. Ahora se
  recorta por el directorio de trabajo y **el script se niega a imprimir un
  desglose cuyas capas no haya sabido deducir**, en vez de presentar como buena
  una tabla vacía.

---

### Seguridad

- **Tres comprobaciones de seguridad que ya no dependen de un token.** El job de
  Snyk se salta entero si `SNYK_TOKEN` no está configurado, así que hasta ahora
  el CI podía pasar en verde sin haber mirado ni una sola vez las dependencias,
  los secretos ni el código. El nuevo job `auditoria-seguridad` corre siempre:

  - `composer audit --locked` — avisos publicados sobre las versiones del
    `composer.lock`, sin necesidad de instalar `vendor/`.
  - **Gitleaks** sobre el **historial completo**, no solo sobre el árbol: un
    secreto borrado en un commit posterior sigue publicado y sigue siendo válido
    hasta que se rote.
  - **Semgrep** (`p/php` + `p/owasp-top-ten`), que cubre lo mismo que el
    `snyk code` opcional.

  Las tres se midieron contra el repositorio **antes** de ponerlas a bloquear,
  para no estrenar el job en rojo: `composer audit` limpio, Gitleaks limpio
  sobre los 140 commits del historial, y Semgrep con 5 hallazgos de severidad
  ERROR.

- **Los 5 hallazgos de Semgrep se revisaron uno a uno y son falsos positivos.**
  Cuatro son `echo json_encode(...)` en endpoints AJAX: la regla asume salida
  HTML y aplicar `htmlentities` ahí **corrompería** el JSON que espera el
  navegador. El quinto es `<?= (int)($_POST['num_tandas'] ?? 1) ?>`, y un entero
  no transporta XSS.

  Por eso Semgrep compara contra la rama base en vez de silenciar la regla:
  apagarla habría apagado también los XSS de verdad. Verificado inyectando un
  `echo $_GET['x']` — lo detecta y sale con código 2.

- **Anotado, sin cambiar:** `config/app.php` construye `APP_URL` desde
  `$_SERVER['HTTP_HOST']` cuando falta `APP_URL` en el entorno. En producción
  está definida, así que esa rama está muerta y el propio comentario del archivo
  ya lo advierte. Queda como riesgo latente: si algún día faltara esa variable,
  el sistema construiría URLs a partir de una cabecera que controla el cliente.

---

### Calidad

- **PHPMD con línea base, igual que PHPStan.** PHPStan comprueba que los tipos
  cuadren; no ve que un método de 400 líneas con complejidad ciclomática 145 sea
  imposible de mantener. El nuevo job `calidad` mide eso y **falla solo ante
  hallazgos nuevos**: los 109 actuales quedan registrados en
  `phpmd.baseline.xml`. Arreglarlos exige partir los controladores, que es un
  refactor con su propio riesgo; lo que no puede pasar es que la deuda siga
  creciendo mientras tanto.

- **El conjunto de reglas es propio, y cada exclusión está comprobada contra el
  código.** Ejecutar `codesize`, `design`, `unusedcode` y `naming` enteros da
  **573 hallazgos, de los que 464 son ruido** para esta arquitectura. Un informe
  con esa proporción de falsos positivos no se lee: se ignora, y entonces
  tampoco se ven los 109 que sí importan.

  - `UnusedLocalVariable` (304) — **no** es código muerto: los controladores
    asignan variables y luego hacen `require` de la vista, que es donde se usan.
    PHPMD no sigue los `include`. Verificado con `$total_insumos`,
    `$insumos_bajos` y `$prod_hoy` de `AuthController:41-43`, que aparecen en
    tres vistas distintas.
  - `ExitExpression` (71) — es el patrón deliberado del proyecto: las guardas de
    autorización terminan en `header()` + `exit`.
  - `UnusedPrivateMethod` (1) — falso positivo: `ExportadorCsv::formatear` se
    invoca con `array_map([self::class, 'formatear'])`, que PHPMD no reconoce.
  - `DevelopmentCodeFragment` (1) — falso positivo: es
    `print_r($error, true)`, que **devuelve** la cadena para el registro en vez
    de imprimirla.
  - `ShortVariable` / `LongVariable` (87) — estilo, no defectos.

- **PHPMetrics publica el mapa de mantenibilidad** como artefacto. No bloquea: no
  hay umbral honesto que poner al índice de un proyecto que ya existe. Lo que
  aporta es decidir **qué** refactorizar primero, y eso se mira.

  El diagnóstico es inequívoco. Las diez clases peor puntuadas son **todas
  controladores**, y por debajo de 65 se considera difícil de mantener:

  | Clase | MI | Complejidad | Líneas lógicas | Métodos |
  |---|---|---|---|---|
  | `VentaController` | 24,2 | 145 | 403 | 5 |
  | `PortalAuthController` | 25,5 | 159 | 500 | 7 |
  | `PortalPedidoController` | 27,6 | 133 | 386 | 4 |
  | `RecetaController` | 27,8 | 105 | 304 | 6 |

  El patrón se repite: de cuatro a siete métodos cargando entre 300 y 500 líneas
  lógicas cada uno. Cada método enruta, valida, atiende AJAX, persiste y
  renderiza. Es responsabilidad única rota de manual, y ahora está medido en vez
  de intuido.

- **Anotado, sin cambiar:** `PagosPortalTrait::iniciarPagoConsolidado` recibe
  `$cliente_id` y **no lo usa**. No es un fallo de seguridad —se comprobó: el
  controlador obtiene los pedidos con `getPedidosPendientesPago($cliente_id)`,
  que filtra por `id_cliente`, así que la propiedad se garantiza aguas arriba—,
  pero la firma promete un acotamiento que el método no hace, y eso puede
  engañar a quien la lea.

- `composer calidad` y `composer metricas` para ejecutarlo en local.

---

### Mutación

- **Primera medición de la calidad de las pruebas: MSI del código cubierto
  65 %.** La cobertura dice qué líneas *ejecuta* una prueba; no dice si la prueba
  se enteraría de que esa línea cambió. Infection lo comprueba alterando el
  código —un `>` por un `>=`, una condición invertida, una línea borrada— y
  mirando si alguna prueba falla.

  **639 mutantes generados, 421 muertos, 218 escapados.** De las mutaciones sobre
  código que las pruebas **sí ejecutan**, un 35 % pasa inadvertido: son pruebas
  que recorren la lógica sin verificarla.

- **Dónde está concentrado**, que es lo accionable:

  | Archivo | Mutantes escapados |
  |---|---|
  | `ProduccionModel.php` | 106 |
  | `InstructorPortalTrait.php` | 40 |
  | `CompraModel.php` | 21 |
  | `IntentoLoginModel.php` | 17 |

  `ProduccionModel` concentra casi la mitad él solo. Es el archivo por el que
  empezar a reforzar aserciones.

- **Se limita a `helpers/` y `models/` a propósito.** Ahí vive la lógica que hace
  perder dinero si falla —FIFO de lotes, cuadre de caja, saldos del instructor—
  y es lo único con cobertura suficiente para que el resultado signifique algo.
  `controllers/` está al 0,0 %: mutarlo produciría mutantes vivos por definición
  y no informaría de nada. El problema allí no es la calidad de las pruebas, es
  que no hay.

- Se usa `--min-covered-msi` y no `--min-msi`: con un 12 % de cobertura global,
  el MSI global mediría sobre todo lo que **no** está probado, y eso ya lo dice
  el job de cobertura. Lo que aporta aquí es la calidad de las pruebas que
  existen. El 65 % es un suelo, no una meta.

- Tarda **1 m 37 s** con 4 hilos, así que cabe en cada push sin volver lento el
  CI. Verificado que el umbral bloquea: con `--min-covered-msi=70` sale 1, con
  65 sale 0.

- `composer mutacion` para ejecutarlo en local.

---

### Extremo a extremo

- **14 recorridos con Playwright**, que ejecutan por primera vez el 60 % del
  proyecto que ninguna prueba tocaba: `controllers/` estaba al 0,0 % de
  cobertura y `views/` tiene 12.346 líneas que jamás pasaban por una prueba. Un
  controlador hace `header()`, `exit` y renderiza una plantilla; eso solo se
  comprueba de verdad con un navegador.

  | Recorrido | Qué verifica |
  |---|---|
  | Acceso al back-office | Entrada válida, contraseña incorrecta, que el error no revele si la cuenta existe (C05 del informe) y que sin sesión no se entre a un módulo |
  | Punto de venta | Registrar una venta **descuenta las unidades disponibles** |
  | Cierre de caja | La transición de día abierto a `CERRADO` |
  | Portal | Acceso, registro de cuenta nueva y entrada con ella |
  | Pedido del portal | Pestaña de precio → catálogo por AJAX → carrito en JS → pedido creado |

  Corren en PRs hacia `main` y tardan **25 segundos**.

- **Las aserciones son sobre la lógica, no sobre el texto en pantalla.** La venta
  no comprueba que aparezca un mensaje: comprueba que las unidades disponibles
  bajen exactamente en lo vendido. Si la venta se pintara pero no descontara del
  inventario, la prueba falla.

  Esto salió de un error propio que conviene dejar escrito: la primera versión
  de la prueba del pedido buscaba el nombre del producto en el tablero del
  cliente. **Pasaba sin haber creado ningún pedido**, porque el tablero lista
  también el catálogo disponible. Se comprobó contra una base recién sembrada y
  se cambió por contar pedidos antes y después.

- **Se usa la interfaz real, no los campos ocultos.** Tanto el POS como el
  pedido del portal construyen el carrito en JavaScript y lo envían en un
  `carrito_json`. Rellenar ese campo a mano habría saltado justo el JavaScript
  que no tiene ninguna prueba.

- **Nueva semilla `sql/init/95_semilla_e2e.sql`**: cuentas de propietario,
  empleado y cliente del portal, más el catálogo y la producción del día que el
  POS necesita para tener stock. La producción se fecha con `NOW()` y no con una
  constante: una semilla con la fecha fijada funcionaría el día que se escribe y
  se rompería sola al siguiente.

- **Nunca contra producción.** Estas pruebas crean y modifican datos; corren
  contra una instancia efímera levantada en el runner.

- **La semilla fija su propia zona horaria.** `config/db.php` ejecuta
  `SET time_zone = '-05:00'` en cada conexión, así que para la aplicación
  `CURDATE()` es la fecha de Colombia; la semilla, en cambio, la carga el cliente
  `mysql`, que usa la del servidor —UTC en el runner de GitHub—.

  Entre las 00:00 y las 05:00 UTC, esas dos fechas no coinciden: la producción se
  sembraba con la fecha del día siguiente y la consulta de stock del punto de
  venta no la encontraba, de modo que el recorrido fallaba por falta de datos y
  no por un defecto. Ocurrió en la primera ejecución en CI, a las 03:17 UTC; en
  local no se veía porque el servidor de desarrollo ya está en la zona de
  Colombia.

- **Los fallos se publican como anotaciones** (reportero `github` de Playwright).
  El registro de una ejecución solo se lee con credenciales del repositorio; una
  anotación, no. Sin eso, saber qué recorrido falló obliga a abrir el registro a
  mano.

---

### Corregido (interfaz)

- **El resaltado de la sección activa del menú no ha funcionado nunca.**
  `navActive()` leía una variable con `global $current`, pero la asignación
  `$current = $_SERVER['REQUEST_URI']` ocurre en el cuerpo de
  `views/layouts/header.php`, y los controladores incluyen esa plantilla **desde
  dentro de un método**. En ese caso la asignación crea una variable local de ese
  método, no una global, así que la función recibía `null`.

  Dos consecuencias, y ninguna saltaba a la vista:

  1. Ningún elemento del menú recibía la clase `on`.
  2. `strpos(null, ...)` emite un aviso de obsolescencia en PHP 8.1+, y como la
     llamada está dentro de un atributo `class=""`, **el aviso se imprimía dentro
     del HTML**, con la ruta absoluta del servidor incluida. En producción no se
     ve porque `display_errors` está apagado, pero se registraba en cada carga.

  Se lee `$_SERVER` directamente en vez de reintroducir una global: una función
  que depende de una variable que alguien debe recordar definir en el ámbito
  correcto es precisamente lo que falló.

- **Dos recorridos de Playwright lo protegen.** Uno exige que cada sección
  resalte su propio elemento **y solo el suyo**; el otro, que ningún atributo del
  menú contenga avisos de PHP. Los dos se comprobaron contra el código defectuoso
  antes de dar el arreglo por bueno: fallan.

  El «y solo el suyo» no es un adorno. Con el fallo presente, la palabra `on` de
  «*on line 10*» —parte del aviso incrustado— convertía los **diez** elementos
  del menú en coincidencias. Una prueba que solo comprobara «hay alguno activo»
  habría pasado con el defecto delante.

  Es la clase de fallo que ninguna otra herramienta del proyecto podía ver:
  PHPStan no analiza las vistas, PHPUnit no renderiza plantillas, y a simple
  vista la página se ve bien.

---

### Pruebas reforzadas

- **`ProduccionModel` pasa de 45 % a 56 % de MSI**, y el global del proyecto de
  **65,9 % a 69,5 %**. El suelo del CI sube de 65 a 69 en consecuencia. Nueve
  pruebas nuevas, 22 mutantes muertos.

- **La causa era que todos los datos de prueba eran números redondos.** Las
  pruebas usaban precios de $100 y $200 con cantidades de 10 y 20, de modo que
  `round()`, `floor()` y `ceil()` daban idéntico resultado y cambiar los
  decimales de precisión tampoco alteraba nada: la lógica de redondeo del costeo
  **no llegaba a ejercerse nunca**. Con un precio de $33,333 sí decide el valor.

- **Lo que ahora queda verificado y antes no:**

  | Comportamiento | Por qué importa |
  |---|---|
  | El costo por lote se redondea a 2 decimales | Es el valor que alimenta los informes de finanzas |
  | El costo unitario se redondea a 4 | Arrastraría toda la cola decimal sin él |
  | Forzar deja constancia en las observaciones | Distingue un costeo fiable de uno estimado |
  | Sin nota propia, el aviso no lleva separador colgando | — |
  | El lote sintético se nombra `EST-{producción}-{insumo}` | Ese formato es lo que evita colisiones en una columna con clave única |
  | El aviso de descuadre entre `stock_actual` y la suma de lotes | Es el **punto 7 de este anexo**: un problema conocido cuyo detector no probaba nadie |
  | Un descuadre por debajo de la milésima **no** se reporta | Los decimales de coma flotante no deben producir avisos falsos |
  | El plan FIFO redondea `a_consumir` a 4 decimales | Con cantidades enteras nunca se ejercía |

- La prueba del descuadre viene acompañada de su negativa —cuando stock y lotes
  cuadran, no hay aviso—, porque sin ella un detector que avisara **siempre**
  habría pasado igual.

---

### Documentación

- **La documentación decía cifras que ya no eran ciertas.** El README declaraba
  «151 pruebas, 291 aserciones» y «5 verificaciones» de CI; la realidad son
  **213 pruebas, 397 aserciones y 16 recorridos de navegador**, con **11
  verificaciones**. También decía 834 ocurrencias en el baseline de PHPStan
  cuando son 474, y la cabecera de `ci.yml` describía cuatro jobs y «PHPStan
  nivel 3» —es nivel 10—.

  Para un proyecto que se entrega, esto pesa más que la deuda técnica: quien lo
  evalúa abre el README antes que el código.

- **Nuevo [`docs/estrategia_pruebas.md`](docs/estrategia_pruebas.md).** Qué ve
  cada capa y qué no, por qué el suelo de cobertura es 12 y el de mutación 69,
  qué mirar cuando cada verificación falla, y —explícitamente— **lo que estas
  pruebas no cubren**: carga, restauración de respaldos, accesibilidad, el pago
  consolidado y la compatibilidad con otras versiones de PHP.

- **`CONTRIBUTING.md`** incorpora `composer mutacion`, `composer calidad` y el
  procedimiento completo de Playwright, con el aviso de apuntar siempre a una
  base desechable: esas pruebas crean y modifican datos.

- **`AUDITORIA.md` queda marcada como fotografía histórica** del 8 de julio y
  remite al anexo de limitaciones para el estado vivo. No se marcan estados
  dentro de ella a propósito: mantener dos inventarios vivos garantiza que
  acaben contradiciéndose, y entonces ninguno sirve.

---

## [1.10.0] — 2026-08-20

### Añadido

- **Control de versiones de esquema.** Nueva tabla `migracion` y
  `scripts/migraciones.php`: la base guarda constancia de qué migraciones tiene
  aplicadas, y `php scripts/migraciones.php` lo responde en un comando. Es el
  hallazgo C1 de la auditoría de julio.

  Hasta ahora `sql/migraciones/` acumulaba nueve archivos sin que nada registrara
  cuáles se habían ejecutado. Responder «¿está producción al día?» obligaba a
  exportar la estructura de los dos lados y compararla a mano; ese mismo día
  costó cuatro comandos, dos idas y vueltas por SSH y una falsa alarma —dos
  tablas parecían distintas y solo cambiaba el orden de las columnas, porque
  MySQL 8 ordena el guion bajo antes que las letras y MariaDB después—.

  Avisa además de una migración **alterada** (el archivo cambió después de
  aplicarse, y editarla no vuelve a ejecutarla) y de una **huérfana** (registrada
  pero sin archivo, por un borrado o un renombrado).

  **No las aplica, a propósito:** en MySQL el DDL hace *commit* implícito, así
  que una migración que falle en su tercer `ALTER` deja hechos los dos primeros
  sin vuelta atrás automática. Aplicarlas de una en una, sabiendo dónde se quedó
  si algo falla, es más seguro que un automatismo que promete atomicidad y no
  puede darla.

  Las nueve anteriores se dan por aplicadas con checksum nulo y se muestran como
  «heredadas»: nadie puede saber con qué contenido se aplicaron en su momento, y
  fingir un checksum sería peor que admitirlo.

---

## [1.9.1] — 2026-08-20

### Operación

- **Los registros de errores sobreviven al despliegue.** Vivían dentro del
  contenedor, y Dokploy crea uno nuevo cada vez que se publica: el registro se
  perdía entero. La ruta pasa a ser configurable con `APP_LOG_PATH` y en el
  servidor apunta a `/var/log/breadcontrol`, montado como volumen Docker.
- **Se mueven fuera de la carpeta pública, no se monta un volumen sobre
  `logs/`.** Ese directorio está bajo el `DocumentRoot` y solo lo protege un
  `.htaccess`; montar un volumen encima lo dejaría vacío y las trazas —con rutas
  internas y datos del sistema— pasarían a ser legibles desde el navegador. Se
  habría ganado persistencia a cambio de publicarlas.
- **Retención de 30 días.** Ya había un archivo por día, así que ninguno crecía
  sin control; lo que crecía era su número, con registros de tres meses
  acumulados. La limpieza corre al estrenar el archivo del día, no en cada
  escritura, y va silenciada entera: registrar un fallo jamás debe provocar otro.

### Detalles que lo habrían hecho fallar en silencio

- El `Dockerfile` crea `/var/log/breadcontrol` con dueño `www-data` a propósito:
  Docker hace que un volumen vacío herede el propietario del directorio que
  cubre. Sin ese paso el volumen habría nacido de root, Apache no habría podido
  escribir, y nadie se habría enterado, porque lo que no se puede registrar es
  precisamente el error.
- Un `APP_LOG_PATH` presente pero vacío se trata como ausente: `get_env()`
  devuelve la cadena vacía si la clave existe, y sin esa comprobación los
  registros habrían acabado en la raíz del disco.

### Pruebas

- 199 en total (7 nuevas), incluida la que garantiza que la limpieza no se lleva
  por delante el `.htaccess` que impide leer los registros desde la web.

---

## [1.9.0] — 2026-08-17

Tanda nacida de dos preguntas del propietario —«¿el resumen por aprendiz
cuadra?» y «¿me metes datos de prueba?»— que acabaron destapando defectos
latentes. Ninguno se había manifestado todavía en producción; casi todos
esperaban a la primera condición que los disparara.

### Seguridad

- **XSS almacenado en los avisos de inventario.** Tres mensajes metían el
  nombre del insumo y la unidad de medida en HTML sin escapar. Eran inofensivos
  mientras nadie los mostraba; al arreglar el sistema de avisos (ver abajo) un
  insumo llamado `<script>…` habría ejecutado su contenido al confirmarlo.
  Escapados con `htmlspecialchars`, como ya hacían los otros ocho.
- **Cancelar un pedido del portal exigía solo visitar una URL.** Ahora requiere
  POST con token CSRF.

### Corregido

- **Los mensajes de confirmación nunca llegaban a la pantalla.** `redirigir()`
  los guardaba y `mostrarMensaje()` sabía pintarlos, pero ninguna vista la
  llamaba: los 11 avisos del sistema («Insumo creado», «Proveedor desactivado»)
  se escribían en la sesión y se descartaban. Ahora los muestra el layout, con
  estilos propios en vez de las clases de Bootstrap que el proyecto no carga.
- **El tablero del instructor no cuadraba consigo mismo.** El saldo pendiente
  se calculaba dos veces con reglas distintas: el KPI incluía los abonos
  parciales y los restaba, la tabla los ignoraba por completo. Además, un
  pedido cancelado seguía contando como pedido pero no como dinero, y
  desactivar a un aprendiz escondía su deuda dejándola dentro del total.
  Una sola definición (`calcularSaldoPendiente`) alimenta ahora el KPI, la
  tabla y el PDF de cartera.
- **La segunda compra de un insumo con tilde fallaba para siempre.** El código
  del lote se recortaba con `substr`, que corta *bytes*: «Azúcar» perdía medio
  carácter y producía un prefijo inválido que no encontraba sus propios lotes,
  así que la secuencia reiniciaba en 001 y chocaba con la anterior. Ni
  reintentando se salía, porque el número calculado era siempre el mismo.
- **Dos compras simultáneas podían calcular el mismo número de lote.** La clave
  única evitaba datos corruptos, pero la segunda compra moría con un error de
  duplicado y había que capturarla entera de nuevo. Ahora reintenta, y solo
  ante ese choque: una llave foránea rota falla de inmediato en vez de
  esconderse tras cinco intentos.
- **La regla del domingo miraba el día de hoy, no la fecha de la compra.** El
  proveedor no entrega en domingo, pero anotar el lunes la compra del sábado
  siempre debió poderse; y en domingo el sistema rechazaba incluso las compras
  fechadas en día hábil.
- **«Ver pedidos» parecía recargar la página sin hacer nada.** El filtro
  siempre funcionó: la lista quedaba tan por debajo del pliegue que el
  navegador dejaba al usuario arriba del todo. Ahora salta a la sección.

### Accesibilidad

- **Cinco controles centrales eran `<div onclick>`**, que no recibe foco ni
  responde a Enter: registrar una venta era imposible sin ratón —ni elegir
  precio, ni cambiar entre Venta y Consumo—, igual que elegir insumo o
  proveedor en compras. Convertidos a `<button>`, con `aria-pressed` para que
  un lector de pantalla anuncie cuál está elegido.
- Los selectores de compras se desactivaban el domingo con
  `pointer-events:none`, que solo desactiva el ratón: con el teclado se seguían
  pudiendo abrir.

### Añadido

- **Generador de datos de demostración** (`scripts/sembrar_datos_demo.php`): un
  mes de compras, producción, ventas y gastos para que los informes tengan algo
  que mostrar. No inserta filas sueltas, llama a los mismos modelos que la
  aplicación, así que lotes, stock, FIFO y costos salen consistentes por
  construcción. Solo corre en local y es reversible por manifiesto de ids.

### Pruebas

- De 158 a 192 pruebas. Las nuevas fijan el reparto proporcional de un pago
  compartido entre varios aprendices, el cuadre del tablero contra base real,
  el prefijo de lote con nombres acentuados y la regla del domingo. Las de
  compras dejaron de saltarse los domingos.

---

## [1.8.0] — 2026-08-12

Implementación del informe técnico externo del 2026-08-12 (recomendaciones
R-01 a R-11). Los once hallazgos se verificaron uno por uno contra el sistema
real antes de tocar nada: todos eran ciertos. Nueve quedan resueltos; dos
dependen del servidor web y están documentados en el anexo de limitaciones.

### Seguridad

- **La cookie de sesión ya viaja con `Secure`.** El código sí calculaba el
  atributo leyendo `X-Forwarded-Proto`, pero la cadena Nginx→Traefik no reenvía
  esa cabecera, así que PHP se creía sirviendo por HTTP. Ahora decide el
  entorno (`APP_ENV`), que no depende del proxy.
- **Sesión endurecida**: `use_strict_mode` contra la fijación de sesión,
  identificador nuevo al autenticar —back-office, portal y acceso con Google— y
  borrado de la cookie al cerrar sesión, que antes sobrevivía a la destrucción
  de la sesión en el servidor.
- **Política de seguridad de contenido (CSP) activa**: ningún script, hoja,
  tipografía o conexión puede venir de un origen no declarado.
- **Cabeceras defensivas**: `X-Content-Type-Options`, `Referrer-Policy`,
  `Permissions-Policy` y `X-Frame-Options`.
- **La versión de PHP deja de anunciarse** (`expose_php=Off` y `X-Powered-By`
  retirada).

### Accesibilidad

- **121 etiquetas asociadas a su campo** mediante `for`/`id`; otras 10 ya eran
  correctas porque envuelven al control. En la pantalla de variedades los
  identificadores incluyen la categoría: ese formulario se repite por cada
  precio y un `id` duplicado hace que pulsar cualquier etiqueta enfoque siempre
  el primer campo de la página.
- **Se retira `maximum-scale=1`** de 6 vistas: impedía ampliar con los gestos
  del navegador, justo lo que necesita una persona con baja visión.
- **25 botones de solo icono** reciben nombre accesible.

### Navegación y difusión

- Las secciones de la portada reservan espacio para la barra fija y el ancla se
  recoloca tras la carga: abrir `/#modulos` ya deja el título a la vista.
- `description`, `canonical`, Open Graph, Twitter Card y `favicon.ico` en su
  ruta convencional. El icono de pestaña pasa de un PNG de 50 KB a uno de 32 px.
- **El sitio se declara público**: se indexa la portada y se bloquea la
  operación interna, con `sitemap.xml`. El propio archivo deja escrito que
  `robots.txt` no es un control de acceso.

### Cambiado

- La portada decía «Software 100% local · Sin internet». El núcleo operativo sí
  puede instalarse en local, pero el clima y el acceso con Google requieren
  conexión: ahora lo dice con precisión. Un dato exacto vale más que un eslogan.

### Nota

- El informe no podía ver, por ser una revisión externa sin credenciales, que su
  punto C05 —límite de intentos y mensaje que no revela si un usuario existe— ya
  estaba resuelto desde la v1.7.0.
- **HSTS (R-02) y ocultar la versión de Nginx (R-05) se aplicaron el 2026-08-14**
  en el propio servidor, dentro del bloque `server` de BreadControl y nunca en
  `nginx.conf` —el VPS aloja 24 sitios, varios de otras personas—. HSTS se
  desplegó primero con `max-age=300` y se subió a un año solo tras comprobar la
  renovación del certificado con `certbot renew --dry-run`. De paso se activó
  **HTTP/2**, que no estaba en el informe: el `listen 443 ssl` no lo tenía y el
  navegador abría hasta seis conexiones con su apretón TLS cada una.
- **El reenvío de `X-Forwarded-*` quedó resuelto el 2026-08-15.** La suposición
  inicial era falsa: Nginx **sí** las envía; era Traefik quien las descartaba por
  no tener a nadie declarado como proxy de confianza. Se añadieron las tres
  puertas de enlace del host a `forwardedHeaders.trustedIPs` del punto de entrada
  `web`. Verificado: un intento de acceso fallido desde una IP pública conocida
  ahora se registra con esa IP, donde el día anterior quedaba una dirección
  interna de Docker. El bloqueo por IP vuelve a medir lo que dice medir.
- Sigue abierto `unsafe-inline` en la CSP, porque el proyecto tiene 157
  manejadores en línea y 31 bloques de script incrustados (punto 22 del anexo).

## [1.7.3] — 2026-08-12

Las imágenes de fondo y el logo tardaban en aparecer al entrar. Se midió cada
causa antes de tocar nada, y la mayor no era el peso de las imágenes.

### Rendimiento

- **Tipografías e iconos autohospedados.** La causa principal: cada pantalla
  pedía su hoja de estilos a `fonts.googleapis.com` y a `cdn.jsdelivr.net`, y
  ambas **bloquean el primer pintado** — el navegador no dibuja nada, ni el
  fondo ni el logo, hasta recibirlas. Medido contra producción: **1,42 s** para
  traer 1,6 KB de Google Fonts (casi todo resolución DNS y apretón de manos
  TLS) y **1,19 s** para los iconos, frente a 0,55 s que tarda el servidor
  propio en responder la página entera. Ahora se sirven desde el mismo dominio,
  ya conectado, y con caché de un año.
- **Bootstrap JS eliminado**: 80 KB desde un tercer dominio, en todas las
  páginas del back-office, **sin usarse** — no hay un solo atributo `data-bs-*`
  ni una llamada a su API en el proyecto.
- **El logo pesaba 1.161 KB** (1024×1024) y se muestra a 56 px como máximo.
  Redimensionado a 192 px: **50 KB**, con el mismo nombre de archivo para no
  tocar los quince sitios que lo enlazan.
- **Caché y compresión**: el servidor no enviaba ninguna de las dos, así que
  cada navegación volvía a pedir cada archivo. Las hojas de estilo viajan ahora
  gzipeadas (12,6 KB → 3,5 KB) y se cachean un año; para que eso sea seguro,
  los 31 enlaces a CSS/JS propios llevan `?v=APP_VERSION` y una versión nueva
  invalida la caché sola.
- **OPcache activado.** Estaba desactivado explícitamente, de modo que PHP
  releía y recompilaba cada archivo del proyecto en cada petición.
- **Módulos de Apache habilitados** (`deflate`, `expires`, `headers`): sin
  ellos, las reglas de rendimiento del `.htaccess` quedaban dentro de bloques
  `<IfModule>` que no se cumplían nunca.
- Fondos recomprimidos a 1600 px: 241 → 172 KB y 219 → 158 KB, sin pérdida
  visible.

### Corregido

- **Los manuales de usuario daban 404.** La interfaz los enlaza desde cuatro
  sitios, pero `.gitignore` excluía `assets/docs/*.pdf` como «documentos
  generados» — no los genera ningún script, están escritos a mano. La auditoría
  del 2026-07-08 ya lo había registrado y seguía sin corregirse.

### Nota

- El sitio se sirve por **HTTP/1.1**, que limita al navegador a seis conexiones
  en paralelo y obliga a un apretón de manos TLS por cada una (~0,3 s). Activar
  HTTP/2 en Nginx es un cambio de una línea del lado del servidor, fuera de
  este repositorio; queda anotado en `LIMITACIONES_Y_TRABAJO_FUTURO.md`.

## [1.7.2] — 2026-08-12

Dos fallos encontrados probando el sistema en vivo con usuarios reales.

### Corregido

- **El detalle de un pedido de tienda respondía «Ocurrió un error inesperado».**
  La consulta del reporte por aprendiz seleccionaba `p.id_pedido` y
  `p.total_estimado` sin incluirlas en el `GROUP BY`. **MySQL 8 —el motor de
  producción— rechaza la consulta entera** por `only_full_group_by`, mientras que
  MariaDB —el motor de desarrollo— la aceptaba devolviendo un valor arbitrario en
  silencio. Ninguna de las dos columnas se usaba: ni la vista del detalle ni la
  exportación las leen, y en un reporte agrupado por aprendiz su valor no
  significaba nada cuando ese aprendiz tenía varios pedidos para la misma fecha.
  Se eliminaron del `SELECT`; el reporte devuelve exactamente lo mismo que antes.
- **El portal rechazaba pedidos válidos con «la fecha y hora de entrega no pueden
  ser en el pasado».** El campo de hora venía con `08:00` fijo, así que cualquier
  cliente que entrara después de las ocho de la mañana y enviara el pedido sin
  tocar ese campo era rechazado sin haber hecho nada mal. Ahora el formulario
  propone la próxima hora en punto dentro del horario de atención y, si ya no
  queda margen hoy, la apertura del día siguiente.

### Pruebas

- Nueva `ReportePortalTest` (3 pruebas, 154 en total): ejecuta la consulta del
  reporte de verdad. El CI corre contra **MySQL 8**, el mismo motor de producción,
  así que una reaparición del patrón vuelve a fallar ahí y no en la pantalla del
  usuario. La consulta corregida se verificó además contra la base real.

### Nota sobre el entorno

- Desarrollo usa **MariaDB** y producción **MySQL 8**, y no aplican
  `only_full_group_by` de la misma forma: MySQL 8 deduce dependencias funcionales
  a través del `JOIN` (acepta `c.nombre` agrupando por una columna unida a su
  clave primaria) y MariaDB no. Auditadas las 12 consultas agrupadas del sistema
  contra el motor real: la del reporte del portal era **la única** realmente
  inválida; las demás funcionan y no se tocaron.

## [1.7.1] — 2026-08-07

### Seguridad

- **Open Redirect cerrado en el borrado de ventas rápidas.** Tras el POST, el
  navegador saltaba a `r.url` —la URL que devolvía el servidor— así que un dato
  remoto llegaba directo a `window.location`. Hoy no era explotable (el
  controlador solo redirige a rutas relativas), pero la seguridad dependía de esa
  suposición y no del código. Ahora se lee **solo** si la respuesta trae
  `?err=csrf` y se navega a una ruta **literal**, de modo que ningún dato remoto
  alcanza `window.location`. Se conserva el aviso de token inválido, que es la
  única información que esa URL aportaba.

### Nota sobre el análisis de seguridad

- Snyk pasa de **10 a 9** hallazgos MEDIUM (0 HIGH, el CI sigue en verde). Los 9
  restantes son falsos positivos de XSS: el SAST no reconoce `escHtml()`, el
  sanitizador propio de `assets/js/utils.js`, por el que sí pasa todo valor que
  llega a `innerHTML` — verificado leyendo cada punto marcado, no asumido. El
  comentario del workflow se corrigió, porque afirmaba que *todos* los MEDIUM
  eran falsos positivos y el Open Redirect no lo era.
- Dependencias (`snyk test`): 3 analizadas, cero vulnerabilidades.

## [1.7.0] — 2026-08-06

Tanda de seguridad y cierre de decisiones pendientes. Cierra 9 de las
limitaciones del anexo `LIMITACIONES_Y_TRABAJO_FUTURO.md`, que se reescribió
por completo contra el código real: varias que seguían listadas como abiertas
ya estaban resueltas desde la v1.0.0 y el documento no lo reflejaba.

### Seguridad

- **CSRF en todo el back-office.** Antes solo el Portal de Clientes validaba el
  token; los ~13 controladores administrativos no lo hacían. Se añadieron
  `requerir_csrf()` y `campo_csrf()` en `includes/sesion.php` y el guardián se
  aplica **una vez por método de controlador que procesa POST**, antes de mirar
  qué acción se pidió: así una rama nueva queda protegida sin que nadie tenga
  que acordarse. 30 formularios incluyen ahora el token.
- **Límite de intentos en el inicio de sesión** (administrativo y del portal):
  5 fallos por cuenta y 20 por IP en 15 minutos, con tabla `intento_login`.
  Se persiste en base de datos y no en sesión porque un atacante controla su
  propia cookie. El mensaje de bloqueo no distingue si la contraseña era
  correcta, para no revelar qué nombres de usuario existen.
- **El código de recuperación por correo se guarda hasheado** (bcrypt), igual
  que el PIN. Antes se guardaba en claro y se comparaba con `!==`.
- **Política de contraseña única**: mínimo 8 caracteres con al menos una letra
  y un número, en `helpers/Seguridad.php`. Antes había cuatro mínimos distintos
  (4, 4, 6 y 6) copiados a mano en cada pantalla. Solo afecta a contraseñas
  nuevas o cambiadas.
- **`docker-compose.yml` ya no versiona las credenciales de MySQL**: se leen del
  `.env` y el arranque falla con un mensaje claro si faltan, en vez de usar una
  contraseña que cualquiera puede leer en el repositorio.

### Corregido

- **Los ingresos del Portal de Clientes ya cuentan en los reportes.** Los pedidos
  del portal nunca generan una fila en `venta`, así que su dinero no sumaba a la
  utilidad, mientras que el costo de ese pan sí se contabilizaba al registrar la
  producción: la utilidad tenía un sesgo sistemático a la baja.
  `FinanzasHelper::ingresosPortalEnRango()` los suma en portada, cierre del día,
  finanzas por rango, tablero y resumen diario. Se suma el **estado** del pedido
  en lugar de crear una venta espejo, de modo que el anti-doble-conteo es
  inherente; la fecha del ingreso es `fecha_entrega`, para que cuadre con la
  producción que lo costeó.
- **Un despliegue limpio con Docker ya levanta el esquema real.** `initdb`
  apuntaba al dump antiguo, que crea `cliente` con 6 de sus 14 columnas y dejaba
  el login del portal, Google OAuth y el flujo instructor-aprendiz rotos. Ahora
  usa `sql/init/01_esquema_base.sql`, el mismo esquema versionado que usa el CI.

### Eliminado

- **Columna `pedido_cliente.id_tienda_destino`** y la subconsulta que la leía.
  Ningún punto del código la escribía: el contador de pedidos por tienda
  beneficiaria marcaba 0 para todas, siempre. Decisión del propietario
  documentada en `docs/id_tienda_destino.md`.

### Pruebas

- 151 pruebas (antes 122): `SeguridadTest` (política y hashing),
  `IntentoLoginModelTest` (umbrales, ventana, aislamiento de ámbitos) e
  `IngresosPortalTest` (solo cuenta lo cobrado, cae en el día de entrega, leer
  dos veces no duplica).
- Verificación en ejecución contra el servidor real, no solo estática: un POST
  sin token es rechazado antes de validar credenciales, y el sexto intento
  seguido de login responde "Demasiados intentos".
- PHPStan sigue limpio en nivel 10; el baseline heredado baja de 854 a **834**
  al eliminarse comprobaciones manuales de `$_POST` en favor de `post_texto()`,
  que devuelve siempre una cadena (un formulario manipulado puede enviar un
  array donde se espera texto).

### Migraciones (aplicar EN ORDEN en el VPS, después de desplegar el código)

1. `sql/migraciones/2026-08-06_01_seguridad_login_y_codigo.sql` — ensancha
   `codigo_recuperacion` a `varchar(255)` en `usuario` y `cliente` (un hash
   bcrypt no cabe en los 10 caracteres anteriores; sin esto, **ningún código de
   recuperación validaría**) y crea la tabla `intento_login`.
2. `sql/migraciones/2026-08-06_02_eliminar_id_tienda_destino.sql` — elimina la
   columna huérfana.

## [1.6.2] — 2026-08-03

### Añadido
- **Diagrama de componentes y responsabilidades** en el README (Mermaid, que
  GitHub renderiza de forma nativa), con la tabla de qué hace cada capa y —
  más importante— qué **no** le corresponde hacer: los controladores no
  consultan la base, las reglas de negocio no leen datos, las vistas no
  calculan. Atiende la recomendación de validar la arquitectura mediante un
  diagrama de componentes.
- Segundo diagrama de infraestructura y servicios externos (SendGrid, Google
  OAuth, Open-Meteo, Nequi) incluyendo la cadena CI → despliegue.

### Corregido
- La sección de arquitectura describía los assets con archivos de ejemplo que
  ya no reflejaban la estructura real.

## [1.6.1] — 2026-08-03

### Cambiado
- Primera reducción del baseline de nivel 9-10: **886 → 854**. Se declararon
  las formas exactas de fila (`@phpstan-type`) en `PedidosPortalTrait` y
  `CuentaClienteTrait`, con los tipos verificados contra la base real
  (`INT` → `int`, `DECIMAL`/`SUM()` → `string`).

### Añadido
- `CONTRIBUTING.md` documenta cómo seguir reduciendo el baseline: tabla de
  equivalencias SQL → PHP y las dos trampas que cuesta descubrir — PHPStan
  no resuelve intersecciones en los alias de tipo (el alias queda
  inservible en silencio) y escribir la etiqueta dentro del texto en prosa
  del comentario rompe el análisis.

### Nota
- Reducir el resto es trabajo incremental de largo plazo: aproximadamente
  dos tercios de las ocurrencias restantes están en controladores que
  consultan la base directamente, y requieren mover esas consultas al
  modelo antes de poder tiparlas. No condiciona el funcionamiento ni la
  cobertura de pruebas del sistema.

## [1.6.0] — 2026-08-03

### Cambiado
- **PHPStan sube al nivel 10, el máximo de la herramienta.**
  - Los niveles **7 y 8 pasan limpios sin excepciones**: se corrigieron ~30
    problemas reales (`strtotime()` que puede devolver `false` pasado a
    `date()`, `max()` sobre arrays potencialmente vacíos, accesos a filas
    SQL sin comprobar que existan, `preg_replace` recibiendo un array
    manipulado desde el formulario, `session_name()`/`json_encode()` con
    retorno `false`).
  - `redirigir()` y `cerrarSesion()` se declaran `: never`, lo que además
    permitió a PHPStan entender el flujo real y eliminar varios falsos
    positivos de acceso a datos.
  - Los niveles 9-10 quedan activos con `phpstan-baseline.neon`: las 886
    ocurrencias heredadas (casi todas filas SQL `array<string, mixed>`
    pasadas a funciones tipadas) están registradas y contadas, y **todo
    código nuevo debe ser nivel-10 limpio o el CI falla** — verificado
    introduciendo una violación de prueba.

### Añadido
- Exclusión documentada del único falso positivo estructural:
  `PDO::prepare()/query()` declaran `PDOStatement|false`, pero el proyecto
  abre la conexión con `ERRMODE_EXCEPTION`, así que esa rama es inalcanzable.

## [1.5.0] — 2026-08-03

### Cambiado
- **PHPStan sube al nivel 6 (máxima exigencia de tipos)**: los 257 hallazgos
  resueltos en 44 archivos — 28 propiedades tipadas, 91 tipos de retorno,
  4 parámetros y 134 anotaciones PHPDoc `array<...>` en iterables. Los
  métodos que retornan `fetch()` conservan su tipo por PHPDoc
  (`array<string, mixed>|false`) porque un tipo nativo sería incorrecto
  en tiempo de ejecución.

## [1.4.0] — 2026-08-03

### Añadido
- 11 pruebas de integración para compras y cierre del día: merma del 6% en
  harina con lote FIFO, variación de precio en `historial_precio`, agregados
  del cierre aislados por fecha futura y upsert de `guardarCierre`
  (122 pruebas, 248 aserciones en total).

### Cambiado
- CSS/JS extraídos de cuatro vistas más del back-office:
  `recetas/editar_receta.php` (491→173), `pedidos_clientes/index.php`
  (490→313), `cierre/index.php` (449→333) y `gastos/index.php` (442→306).

### Decidido
- PHPStan permanece en nivel 5: el nivel 6 exige 257 correcciones
  (mayormente anotaciones PHPDoc) con retorno bajo; se pospone.

## [1.3.0] — 2026-08-03

### Añadido
- 13 pruebas de integración para el núcleo financiero: cálculo FIFO de lotes,
  registro de producción con costeo real (incluido el lote sintético `EST-`
  para remanentes sin lote) y ventas rápidas con stock del día
  (111 pruebas, 212 aserciones en total).

### Cambiado
- PHPStan sube al **nivel 5** (5 hallazgos de tipos corregidos).
- CSS/JS extraídos de tres vistas más: `portal/detalle_pedido.php`
  (672→472), `finanzas/exportar_pdf.php` (614→290) y
  `portal/nuevo_pedido.php` (609→173, su carrito JS ahora vive en
  `assets/js/portal_nuevo_pedido.js`).

## [1.2.0] — 2026-08-03

### Cambiado
- `PortalClienteModel` (1.298 líneas, 62 métodos) dividido por responsabilidad
  en 5 traits bajo `models/portal/` (cuenta, catálogo, pedidos, pagos e
  instructor). La clase pública sigue siendo una sola — controladores y
  pruebas no cambian — pero cada archivo es pequeño y cohesivo.

## [1.1.0] — 2026-08-03

### Cambiado
- `PortalClienteController` (1.651 líneas, 21 métodos) dividido en 5
  controladores por responsabilidad bajo `controllers/portal/` con una base
  común (`PortalControllerBase`): autenticación/cuenta, pedidos, pago
  consolidado, gestión de instructor y exportaciones. Los 17 puntos de
  entrada de `portal/` ahora instancian el controlador que les corresponde.

## [1.0.0] — 2026-08-03

Primera versión estable formalizada.

### Añadido
- Suite de pruebas con **PHPUnit 11**: 98 pruebas (unitarias + integración con
  transacción y rollback) que cubren autenticación, reglas de negocio del portal,
  validaciones, persistencia y manejo de errores.
- **Integración continua** con GitHub Actions: sintaxis PHP, PHPStan (nivel 4),
  suite unitaria y suite de integración contra MySQL 8.
- Esquema de base de datos versionado (`sql/init/01_esquema_base.sql`) — la BD
  ahora es reconstruible desde un clon del repositorio.
- `helpers/ReglasPortal.php`: fuente única de las reglas de negocio del portal
  (crédito/ñapa, límite de gestión de 48 h, bloqueo por pago del instructor,
  cupo semanal, horario de entrega).
- Licencia **MIT**, guía de contribución (`CONTRIBUTING.md`) y metadatos del
  proyecto en `composer.json`.

### Cambiado
- Vistas extensas divididas por responsabilidad: CSS/JS de Ventas, Compras y
  Dashboard del portal extraídos a `assets/` (p. ej. `views/ventas/index.php`
  pasó de 2.424 a 476 líneas).
- Las variables de entorno reales tienen prioridad sobre `.env` (12-factor).

### Funcionalidad existente consolidada en esta versión
- Inventario FIFO con alertas, producción con costeo real, ventas con
  bonificación/ñapa, recetas, compras, finanzas con PDF, gastos y cierre de caja.
- Portal de clientes con registro tradicional y Google OAuth.
- Flujo educativo aprendiz–instructor con cupo semanal y aprobación de pedidos.
- Pagos con Nequi (link manual) y consolidación de saldos.
