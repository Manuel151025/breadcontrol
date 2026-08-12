# Changelog — BreadControl

Todos los cambios notables del proyecto se documentan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

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
