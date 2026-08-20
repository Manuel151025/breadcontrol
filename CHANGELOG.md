# Changelog — BreadControl

Todos los cambios notables del proyecto se documentan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

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
