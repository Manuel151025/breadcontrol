# Limitaciones Conocidas y Trabajo Futuro — BreadControl

**Anexo al SRS (`srs_ieee830_breadcontrol.txt`)** · **Última verificación: 2026-08-06**

Este documento inventaria, con evidencia verificada contra el código y la base de datos reales, las limitaciones conocidas del sistema BreadControl. No es un listado de intenciones: cada punto fue confirmado leyendo el archivo citado o ejecutando una consulta contra la base de datos antes de documentarlo.

El propósito de este anexo es dejar registro explícito de qué se sabe que falta, por qué se pospuso, y qué esfuerzo tomaría resolverlo — para que cualquier evaluador, instructor o desarrollador futuro entienda el estado real del sistema sin tener que descubrirlo de nuevo.

**Los puntos resueltos NO se borran de este documento**: se marcan como resueltos con la evidencia de su corrección. Un anexo que solo enumera lo pendiente oculta la mitad de la historia, y quien lo lea dos veces necesita saber qué cambió entre una lectura y otra.

## Resumen

| # | Limitación | Categoría | Severidad | Estado |
|---|---|---|---|---|
| 1 | CSRF ausente en los controladores administrativos | Seguridad | Alto | ✅ **Resuelto** (2026-08-06) |
| 2 | Acciones de eliminar/desactivar por GET sin token | Seguridad | Alto | ✅ **Resuelto** |
| 3 | Sin límite de intentos en login | Seguridad | Medio-Alto | ✅ **Resuelto** (2026-08-06) |
| 4 | `codigo_recuperacion` en texto plano | Seguridad | Medio | ✅ **Resuelto** (2026-08-06) |
| 5 | Política de contraseña inconsistente | Seguridad | Bajo-Medio | ✅ **Resuelto** (2026-08-06) |
| 6 | Credenciales en `docker-compose.yml` versionado | Seguridad | Medio-Alto | ✅ **Resuelto en HEAD** (2026-08-06); quedan en el historial |
| 7 | Desincronización `stock_actual` vs. suma de lotes | Integridad de datos | Alto | ⬜ Abierto — L (5+ días) |
| 8 | Lotes semilla `INI-*` con `precio_unitario=0` (agotados) | Integridad de datos | Histórico | ⬜ No se corregirá (decisión) |
| 9 | Producciones 1 y 4 con `costo_total=0` | Integridad de datos | Histórico | ⬜ No se corregirá (decisión) |
| 10 | `venta.id_producto=NULL` en POS moderno | Integridad de datos | Alto | ⬜ Abierto — L (rediseño de datos) |
| 11 | Ingresos del portal fuera de los reportes | Arquitectura | Alto | ✅ **Resuelto** (2026-08-06, opción B.2) |
| 12 | `assets/js/ventas.js` huérfano (689 líneas) | Arquitectura | Bajo | ✅ **Resuelto** (v1.0.0) |
| 13 | 796 líneas de JS inline en `views/ventas/index.php` | Arquitectura | Bajo-Medio | ✅ **Resuelto** (v1.0.0) |
| 14 | Código duplicado: carrito nuevo vs. editar pedido | Arquitectura | Medio | ⬜ Abierto — M (2-3 días) |
| 15 | Vistas clásicas de insumo sin estilo ni enlace | Arquitectura | Bajo | ⬜ Abierto — S (medio día) |
| 16 | `php.ini` con `date.timezone=Europe/Berlin` (inerte) | Configuración | Cosmético | ⬜ Abierto — S (1 línea) |
| 17 | El despliegue limpio no reflejaba el esquema real | Configuración | Alto | ✅ **Resuelto** (2026-08-06) |
| 18 | Condiciones de carrera (lotes, cupo semanal) | Concurrencia | Medio | 🟡 Parcial — falta el número de lote |
| 19 | Columna huérfana `pedido_cliente.id_tienda_destino` | Arquitectura | Bajo | ✅ **Resuelto** (2026-08-06) |
| 20 | Los mensajes de confirmación nunca se muestran | Usabilidad | Bajo-Medio | ⬜ Abierto — S |
| 21 | HSTS, versión de Nginx y cabeceras de proxy | Seguridad | Medio | ✅ **Resuelto** (2026-08-14/15) |
| 22 | `unsafe-inline` en la CSP: 157 manejadores en línea | Seguridad | Medio | ⬜ Abierto — L |
| 23 | Respaldos: probados, pero en el mismo servidor | Continuidad | Medio | 🟡 Parcial — falta copia externa |
| 24 | El registro de errores se borra en cada despliegue | Operación | Medio | ⬜ Abierto — S |
| 25 | PHP 8.2 sin parches de seguridad desde 2027 | Mantenimiento | Medio | ⬜ Abierto — S-M (antes de nov 2026) |
| 26 | La fecha «sin definir» es un valor mágico (`1000-01-01`) | Diseño de datos | Bajo | ⬜ Abierto — M |

*Esfuerzo: S = &lt;2 días, M = 2-5 días, L = 5-10 días, XL = requiere decisión de producto antes de estimar.*

---

## SEGURIDAD

### 1. ✅ CSRF ausente en los controladores administrativos — RESUELTO (2026-08-06)

**Era:** la protección CSRF (`generar_token_csrf()`/`validar_token_csrf()` en `includes/sesion.php`) existía y funcionaba, pero solo se invocaba desde el Portal de Clientes. Ningún controlador de `controllers/{Inventario,Compra,Produccion,Receta,Venta,Gasto,Cierre,Configuracion,Auth}Controller.php` la usaba, así que un atacante que consiguiera que el propietario autenticado visitara una página maliciosa podía inducir acciones administrativas sin su consentimiento.

**Cómo se resolvió:** en dos tandas.

1. Una primera tanda (commits `5ad93cc` y `2986150`) protegió las acciones destructivas y la pantalla de pedidos-clientes.
2. La tanda del 2026-08-06 cubrió **todos** los formularios restantes: se añadieron dos ayudantes en `includes/sesion.php` —`requerir_csrf($url_error)` y `campo_csrf()`— y se aplicó el guardián **una vez por cada método de controlador que procesa POST**, antes de mirar qué acción se pidió. Ese orden es deliberado: una rama `if (isset($_POST['nueva_accion']))` que se agregue mañana queda protegida sin que nadie tenga que acordarse. Los 30 formularios del back-office incluyen ahora `<?= campo_csrf() ?>`.

**Evidencia:** `grep -c 'REQUEST_METHOD.*POST'` frente a `grep -c 'requerir_csrf\|validar_token_csrf'` en `controllers/` da cobertura completa. Verificado además en ejecución contra el servidor local: un POST a `login.php` sin token responde con el aviso de token inválido y **no** llega a validar credenciales; el mismo POST con un token válido de la sesión sí las valida.

**Convención resultante:** el fallo de token redirige a la pantalla de origen con `?err=csrf`, que cada controlador traduce a un aviso visible. Se eligió sobre el sistema de mensajes flash de `redirigir()` porque **ninguna vista llama a `mostrarMensaje()`**, de modo que aquellos mensajes nunca se mostraban (ver punto 20).

---

### 2. ✅ Acciones de eliminar/desactivar por GET sin token — RESUELTO

**Era:** varias acciones destructivas se ejecutaban leyendo `$_GET['del']`/`$_GET['desactivar']`, así que un `<img src="...">` malicioso bastaba para dispararlas si el propietario tenía sesión activa.

**Cómo se resolvió:** las 7 acciones se convirtieron a POST con token, mediante el ayudante compartido `includes/boton_eliminar.php`, que genera el formulario con su `csrf_token` en lugar del antiguo `<a href="?del=...">`.

**Evidencia:** `grep -rn "\$_GET\['\(del\|del_venta\|del_var\|desactivar\)'\]" controllers/` → **cero coincidencias** (verificado 2026-08-06).

---

### 3. ✅ Sin límite de intentos en login (fuerza bruta) — RESUELTO (2026-08-06)

**Era:** ni `AuthController::login()` ni el login del portal contaban intentos fallidos: se podían probar credenciales de forma ilimitada.

**Cómo se resolvió:** nueva tabla `intento_login` y `models/IntentoLoginModel.php`. Umbrales en `helpers/Seguridad.php`: **5 fallos por cuenta** y **20 por IP**, ambos en una ventana de **15 minutos**. Se aplica al login administrativo y al del portal, con ámbitos separados (`admin`/`portal`) para que agotar los intentos en uno no cierre el otro. Un inicio de sesión correcto borra los intentos previos de esa cuenta.

**Decisiones no obvias:**
- Los intentos se persisten en **base de datos, no en sesión**: un atacante controla su propia cookie, así que un contador en `$_SESSION` se esquiva descartándola.
- El umbral por IP es más alto que el de cuenta a propósito: varias personas legítimas pueden compartir una misma IP de salida, mientras que el umbral por cuenta no depende de la red.
- El mensaje de bloqueo es el mismo se acierte o no la contraseña; si distinguiera, revelaría qué nombres de usuario existen.
- La IP se lee de `X-Forwarded-For` (en producción la app corre detrás de Traefik). Esa cabecera es falsificable por quien alcance el contenedor directamente, por lo que el bloqueo por IP es una **salvaguarda secundaria**: la defensa principal es el bloqueo por cuenta.

**Evidencia:** `tests/Integration/IntentoLoginModelTest.php` (6 pruebas: umbral, ventana, aislamiento de ámbitos, limpieza tras éxito, bloqueo por IP con usuarios rotados). Verificado además en ejecución: el 6.º intento seguido contra el login real responde "Demasiados intentos".

---

### 4. ✅ `codigo_recuperacion` en texto plano — RESUELTO (2026-08-06)

**Era:** el PIN de recuperación se guardaba con `password_hash()`, pero el código de 6 dígitos enviado por correo se guardaba **en claro** y se comparaba con `!==`, tanto en `cliente` (portal) como en `usuario` (administradores).

**Cómo se resolvió:** `Seguridad::hashCodigoRecuperacion()` y `Seguridad::verificarCodigoRecuperacion()` (bcrypt + `password_verify`). El código en claro solo viaja al correo; en la base queda el hash.

**Detalle de migración que costaba descubrir:** la columna era `varchar(10)` y un hash bcrypt ocupa 60 caracteres, así que **había que ensancharla antes** de desplegar el código nuevo o el `UPDATE` habría truncado el hash silenciosamente y ningún código habría validado jamás. La migración `sql/migraciones/2026-08-06_01_seguridad_login_y_codigo.sql` la lleva a `varchar(255)` en ambas tablas y limpia los códigos vigentes (expiraban en 5-10 minutos; quien estuviera a mitad del flujo solo tenía que pedir uno nuevo).

**Evidencia:** `tests/Unit/SeguridadTest.php` comprueba que el valor guardado no es el código, que dos hashes del mismo código difieren (sal aleatoria) y que una cuenta sin código pendiente nunca verifica.

---

### 5. ✅ Política de contraseña inconsistente — RESUELTO (2026-08-06)

**Era:** cuatro longitudes mínimas distintas copiadas a mano —4 en el registro del portal, 4 en su perfil, 6 en las dos pantallas de recuperación y 6 en el cambio de clave del propietario—, ninguna con requisito de complejidad.

**Cómo se resolvió:** `Seguridad::validarContrasena()` es la regla única: **mínimo 8 caracteres, con al menos una letra y un número**. La usan las 5 pantallas que fijan contraseña, y las vistas leen el mínimo de `Seguridad::CONTRASENA_MIN` en su `minlength` y en el texto de ayuda, de modo que cambiar la constante cambia formulario y validación a la vez.

**Alcance:** solo afecta a contraseñas nuevas o cambiadas. Las cuentas existentes siguen entrando con la suya; la política se les aplicará la próxima vez que la cambien.

**Evidencia:** `tests/Unit/SeguridadTest.php` fija la regla, incluidos los casos "los 4 de antes" y "los 6 de antes", que ahora deben rechazarse.

---

### 6. ✅ Credenciales de base de datos en `docker-compose.yml` — RESUELTO EN HEAD (2026-08-06)

**Era:** `docker-compose.yml` traía `MYSQL_USER`, `MYSQL_PASSWORD` y `MYSQL_ROOT_PASSWORD` escritos en claro y versionados.

**Cómo se resolvió:** las cuatro variables se leen ahora del `.env` (que Compose carga solo), con la sintaxis `${VAR:?mensaje}`: si no están definidas, Compose **falla con ese mensaje** en lugar de arrancar con una contraseña que cualquiera puede leer en el repositorio. La plantilla quedó documentada en `.env.example`.

**Lo que NO se hizo, y por qué:** las credenciales antiguas **siguen en el historial de Git**. Purgarlas exigiría reescribir la historia de una rama compartida (y de la etiqueta `historia-inicial`), lo que rompería cualquier clon existente. El riesgo práctico es bajo —eran las de un `docker-compose` de desarrollo que nunca gobernó el despliegue real, que usa Dokploy con su propia configuración— pero conviene tenerlo escrito: **si alguna de esas contraseñas se reutiliza en algún entorno vigente, hay que rotarla**, porque eliminarla de `HEAD` no la borra del historial.

---

## INTEGRIDAD DE DATOS

### 7. ⬜ Desincronización entre `insumo.stock_actual` y la suma real de `lote.cantidad_disponible`

**Descripción:** el contador denormalizado `insumo.stock_actual` y la suma real de lotes activos (`SUM(lote.cantidad_disponible) WHERE estado='activo'`) deberían coincidir, pero divergen de forma sistemática.

**Evidencia (consulta ejecutada el 2026-08-06 contra la base real):** **17 de 22 insumos** activos divergen.

**Impacto:** `stock_actual` no es confiable como fuente de verdad — es la causa raíz de que producciones se registren "con stock suficiente" según el contador y luego no encuentren lotes reales que cubran la receta (bug C17). También hace que las alertas de stock bajo (`RF-06`, comparadas contra `stock_actual`) puedan mostrar información incorrecta.

**Severidad:** Alto. **Esfuerzo:** L (5+ días) — requiere decidir cuál fuente es la autoritativa, auditar todos los puntos de escritura de ambas (compras, ajustes manuales, producción, código de varias generaciones) y posiblemente eliminar `stock_actual` en favor de calcularlo siempre desde `lote`.

**Por qué sigue abierto:** se identificó como causa raíz durante el fix de C17 y se dejó fuera por su alcance (afecta a casi todos los insumos, no a un caso puntual); se mitigó el síntoma con el aviso no bloqueante de RF-14b. En la revisión del 2026-08-06 se decidió explícitamente no abordarlo en esa tanda para no mezclar un cambio de modelo de datos con una tanda de seguridad.

---

### 8. ⬜ Lotes semilla `INI-2026-03-21-*` con `precio_unitario=0` — NO SE CORREGIRÁ (decisión)

**Evidencia (2026-08-06, sin cambios):** 10 lotes de apertura con `precio_unitario=0`, **todos en `estado='agotado'` con `cantidad_disponible=0`**.

**Por qué no se corrige:** son datos de apertura de inventario, no compras reales: no existe un precio de compra real que asignarles. Al estar los 10 agotados, no pueden afectar a ningún cálculo futuro — `getLotesDisponiblesFIFOParaConsumo()` filtra por `estado='activo' AND cantidad_disponible>0`. El impacto ya ocurrió y es retroactivo (ver punto 9).

---

### 9. ⬜ Producciones `id_produccion` 1 y 4 con `costo_total=0` — NO SE CORREGIRÁ (decisión)

**Evidencia (2026-08-06, sin cambios):** 2 producciones con `costo_total=0`, ambas consumieron exclusivamente de los lotes semilla del punto 8.

**Por qué no se corrige — decisión explícita, no falta de tiempo:** recalcular `consumo_lote`/`costo_total` de producciones históricas es manipular datos ya consolidados, con riesgo real (reescribir un registro que pudo reportarse en un cierre de caja pasado) y beneficio nulo (no afecta ninguna operación futura; el fix de C17 cubre el escenario general hacia adelante). Se documenta como deuda histórica aceptada.

---

### 10. ⬜ `venta.id_producto=NULL` en el POS moderno

**Evidencia (2026-08-06, sin cambios):** **102 de 113** ventas (90,3%) tienen `id_producto` NULL, porque el POS moderno registra `id_categoria_precio` y una categoría agrupa varios productos distintos.

**Impacto:** los reportes "por producto" no pueden atribuir con certeza el 90% de las ventas a un producto específico (causa raíz documentada de C6/C17 y de la limitación aceptada en `getSobrantesHoy()`).

**Severidad:** Alto. **Esfuerzo:** L — el POS tendría que capturar también qué producto específico se vendió dentro de la categoría: cambio de modelo de datos **y** de la interfaz de cobro en mostrador.

**Por qué sigue abierto:** es una decisión de diseño del POS, que prioriza la velocidad de cobro (elegir precio, no producto) sobre la trazabilidad por producto. Cambiarlo afecta la experiencia de venta rápida, no es un ajuste aislado de backend.

---

## ARQUITECTURA

### 11. ✅ Los ingresos del Portal quedaban fuera de los reportes — RESUELTO (2026-08-06, opción B.2)

**Era:** `pedido_cliente` y `venta` son tablas independientes y un pedido del portal **nunca** genera una fila en `venta` (verificado de nuevo: cero `INSERT INTO venta` en los modelos del portal). Como los reportes financieros calculan ingresos desde `venta`, el dinero cobrado por el portal no sumaba a la utilidad. Y como el costo de esos panes **sí** se contabiliza (se descuenta al registrar la producción del día), la utilidad reportada tenía un **sesgo sistemático a la baja**.

**Cómo se resolvió:** `FinanzasHelper::ingresosPortalEnRango()` suma `pedido_cliente.total_estimado` de los pedidos con `estado_pago='aprobado'`, y se incorpora a los cinco puntos que calculan ingresos: portada (`AuthModel`), cierre del día (`CierreModel`, hoy y ayer), finanzas por rango (`FinanzasModel`), tablero (`TableroModel`, hoy/ayer/mes) y el resumen diario de `GastoModel`.

**Por qué B.2 y no crear una venta espejo:** se eligió sumar el **estado** del pedido en vez de insertar una fila en `venta`. Es idempotente por diseño —leer un estado no puede contar doble, confirmar un cobro dos veces no duplica el ingreso— y no toca inventario ni el POS, así que no arriesga un doble descuento de stock. La alternativa (crear la venta en diferido) exigía migración, guardián de idempotencia y una decisión sobre el backfill de los pedidos ya confirmados.

**La fecha del ingreso es `fecha_entrega`**, no la del cobro: así el ingreso cae el mismo día que la producción que lo costeó y el cierre diario cuadra.

**Contrapartidas asumidas, que siguen abiertas:**
- Quedan **dos fuentes de ingreso** que hay que sumar en cada reporte nuevo. Por eso la consulta vive en `FinanzasHelper` y no copiada en cada modelo: un reporte futuro que olvide llamarla volverá a tener el sesgo.
- **El POS sigue sin ver el pan comprometido a pedidos del portal**, así que puede sobre-vender el stock del día. Esto lo resolvería la opción B.1 (venta en diferido), descartada por ahora.

**Evidencia:** `tests/Integration/IngresosPortalTest.php` (solo suma los cobrados; el ingreso cae en el día de entrega; leer dos veces no duplica; el cierre del día incluye el portal).

---

### 12. ✅ `assets/js/ventas.js` huérfano — RESUELTO (v1.0.0)

El archivo ya no es código muerto: `views/ventas/index.php:478` lo carga con `<script src="...assets/js/ventas.js">`. La extracción que estaba a medias se completó en la tanda de refactor de la v1.0.0.

---

### 13. ✅ 796 líneas de JavaScript inline en `views/ventas/index.php` — RESUELTO (v1.0.0)

La vista pasó de 2.424 a **480 líneas** y su JS vive en `assets/js/ventas.js` (cacheable por el navegador), cumpliendo `RNF-07`.

---

### 14. ⬜ Código duplicado: carrito de "nuevo pedido" vs. "editar pedido"

**Descripción:** la lógica de catálogo, carrito y panel de bonificación está duplicada casi íntegramente: una copia para registrar un pedido nuevo y otra (con prefijo `ep`) para editar uno existente. Tras la extracción del punto 13, la duplicación vive en `assets/js/ventas.js` en vez de en la vista, pero **sigue ahí**.

**Impacto:** cualquier corrección debe aplicarse dos veces; si se olvida una copia (como ocurrió con el XSS antes de su fix), el bug persiste en un flujo mientras parece resuelto en el otro.

**Severidad:** Medio. **Esfuerzo:** M (2-3 días) — extraer funciones compartidas parametrizadas por prefijo/contexto.

---

### 15. ⬜ Vistas clásicas de insumo sin estilo ni enlace (`crear_insumo.php`, `editar_insumo.php`)

**Evidencia (2026-08-06):** siguen existiendo y `InventarioController` las rotula "clásica" y las carga (`:181` y `:235`), pero **ninguna vista del proyecto enlaza a ellas**: el modal de `views/inventario/index.php` cubre su función.

**Impacto:** bajo en la práctica; es código muerto que puede confundir. Ambas recibieron su token CSRF en la tanda del 2026-08-06 para no dejar formularios sin protección mientras existan.

**Severidad:** Bajo. **Esfuerzo:** S (medio día) — eliminarlas junto con sus dos métodos de controlador. Se propuso al propietario en la revisión del 2026-08-06 y **decidió no incluirlas** en esa tanda.

---

## CONFIGURACIÓN

### 16. ⬜ `php.ini` con `date.timezone=Europe/Berlin` (cosmético, sin impacto funcional)

Es inerte: `config/app.php` ejecuta `date_default_timezone_set('America/Bogota')` como primera línea, y los 51 puntos de entrada lo cargan antes de generar cualquier timestamp. Colombia no observa horario de verano, así que PHP (`America/Bogota`) y MySQL (`SET time_zone='-05:00'`) son equivalentes.

**Nota:** el archivo `php.ini` **no está versionado en el repositorio**; es el del XAMPP local. La recomendación (alinearlo a `America/Bogota`) aplica al entorno de desarrollo, para evitar confundir a quien escriba un script CLI que no cargue `config/app.php` — exactamente el falso positivo que originó una versión anterior de este punto.

---

### 17. ✅ El despliegue limpio no reflejaba el esquema real — RESUELTO (2026-08-06)

**Era:** `docker-compose.yml` inicializaba la base con `sql/panaderia_bd.sql`, que crea `cliente` con solo 6 columnas frente a las 14 reales. Un despliegue nuevo desde cero —justo lo que haría un evaluador— tenía el login del portal, Google OAuth y el flujo instructor-aprendiz rotos.

**Cómo se resolvió:** el `initdb` apunta ahora a `sql/init/01_esquema_base.sql`, el esquema real versionado (generado con `mysqldump --no-data` de producción, y ya usado por el CI), que incluye las 14 columnas de `cliente` y todas las tablas del flujo de pedidos.

**Trampa que conviene dejar escrita:** **no** se monta también `sql/init/02_extensiones_flujo.sql`. Ese script parte del dump antiguo y añade esas mismas columnas y tablas con `ALTER TABLE ADD COLUMN` y `CREATE TABLE` **sin** `IF NOT EXISTS`, así que sobre el 01 falla por duplicado y el contenedor **aborta la inicialización**. Sigue siendo útil solo para una base creada con el dump viejo.

---

## CONCURRENCIA

### 18. 🟡 Condiciones de carrera — PARCIALMENTE RESUELTO

**Resuelto:** la validación de **cupo semanal** ya bloquea la fila del creador con `SELECT ... FOR UPDATE` (`models/portal/PedidosPortalTrait.php:352`), de modo que dos pedidos simultáneos del mismo aprendiz no pueden pasar ambos la validación. El canje de código de aprendiz usa el mismo patrón sobre la fila del código.

**Abierto:** `includes/funciones.php::generarNumeroLote()` (líneas 101-118) sigue con el patrón "leer, calcular en PHP, escribir": hace `SELECT ... ORDER BY numero_lote DESC LIMIT 1`, calcula `último + 1` y solo después se inserta. Dos compras casi simultáneas pueden calcular la misma secuencia; la `UNIQUE KEY` evita datos corruptos, pero una de las dos inserciones falla con un error de duplicado poco claro en vez de reintentar con el siguiente número.

**Severidad:** Medio (requiere concurrencia real para manifestarse). **Esfuerzo:** S-M — reintento ante duplicado, o `SELECT ... FOR UPDATE` sobre una fila de control. Propuesto en la revisión del 2026-08-06 y **no incluido** en esa tanda por decisión del propietario.

---

### 19. ✅ Columna huérfana `pedido_cliente.id_tienda_destino` — RESUELTO (2026-08-06)

**Era:** la columna se creó para la funcionalidad "Tiendas Beneficiarias", pero **ningún punto del código la escribía**: valía NULL siempre. Su único lector era una subconsulta en `ConfiguracionModel::getTiendasBeneficiarias()` que mostraba un contador de pedidos por tienda, y que por tanto marcaba 0 para todas, siempre.

**Cómo se resolvió:** se eliminaron la subconsulta muerta, el contador de la vista y la columna (`sql/migraciones/2026-08-06_02_eliminar_id_tienda_destino.sql`). El destinatario real de un pedido es `id_cliente`; `id_creador` es quien lo armó. Detalle en `docs/id_tienda_destino.md`.

**Orden obligatorio del despliegue:** primero el código, después la migración. Al revés, la pantalla de Tiendas Beneficiarias falla entre el `ALTER` y el despliegue.

---

### 20. ⬜ Los mensajes flash de `redirigir()` nunca se muestran

**Descubierto el 2026-08-06** al elegir cómo avisar de un token CSRF inválido.

**Descripción:** `redirigir($url, $tipo, $mensaje)` (`includes/funciones.php:32`) guarda el mensaje en `$_SESSION['mensaje_texto']`, y `mostrarMensaje()` (`:42`) lo renderiza. Pero **ninguna vista llama a `mostrarMensaje()`** (`grep -rn 'mostrarMensaje' views/` → cero coincidencias), así que todos esos mensajes se escriben en la sesión y nunca se muestran; la siguiente pantalla simplemente aparece sin explicación.

**Impacto:** confirmaciones y errores que el código cree estar comunicando ("Proveedor desactivado", "Insumo creado correctamente") se pierden. No hay pérdida de datos: la acción sí se ejecuta.

**Severidad:** Bajo-Medio (usabilidad). **Esfuerzo:** S — llamar a `mostrarMensaje()` en el layout `views/layouts/header.php`, revisando que el HTML que genera (clases de Bootstrap `alert`) encaje con el sistema de diseño propio, que no usa Bootstrap.

**Por qué no se resolvió en esta tanda:** por eso los avisos de CSRF usan la convención `?err=csrf`, que sí se muestra. Arreglar el sistema flash haría aparecer de golpe decenas de mensajes hoy invisibles en pantallas que no se han revisado, y eso merece su propia tanda con revisión visual.

---

### 21. ✅ Controles del servidor web (R-02, R-05 y cabeceras de proxy) — RESUELTOS

**Origen:** informe técnico externo del 2026-08-12. Nueve de sus once recomendaciones se resolvieron en la v1.8.0 dentro del repositorio; estas dos vivían en el Nginx del VPS y se aplicaron directamente allí, **dentro del bloque `server` de BreadControl** y nunca en `nginx.conf`: el servidor aloja 24 sitios, varios de otras personas, y un cambio global los habría afectado a todos.

**✅ HSTS (R-02).** `add_header Strict-Transport-Security "max-age=31536000" always;`. Se desplegó primero con `max-age=300` y se subió a un año solo después de comprobar la renovación del certificado con `certbot renew --dry-run`. El orden importa: mientras HSTS está activo el navegador **se niega** a usar HTTP en el dominio, así que un certificado que no renueve deja el sitio inaccesible sin opción de continuar, y ese plazo corto era la red de seguridad mientras se verificaba.

**✅ Versión de Nginx (R-05).** `server_tokens off;`. Las respuestas ahora publican `Server: nginx`, sin versión. La de PHP ya se había ocultado con `expose_php=Off` en el Dockerfile.

**➕ HTTP/2**, que no estaba en el informe. El `listen 443 ssl` no lo tenía, así que el navegador abría hasta seis conexiones separadas y pagaba un apretón TLS (~0,3 s) en cada una. Con `listen 443 ssl http2` todo viaja multiplexado por una sola conexión.

**✅ El reenvío de `X-Forwarded-*` — RESUELTO el 2026-08-15.**

Al corregir R-01 se supuso que Nginx no reenviaba `X-Forwarded-Proto`. **Era falso**: su bloque `server` sí lo envía. El problema estaba un salto más adelante — Nginx entrega a **Traefik** por HTTP en el puerto 9080, y Traefik descartaba esas cabeceras y las reescribía con su propia visión de la conexión.

Ese comportamiento es correcto por defecto, y conviene entender por qué: `X-Forwarded-For` es texto que cualquiera puede escribir en una petición. Si Traefik creyera cualquier valor entrante, un atacante podría declararse en la IP que quisiera y burlar cualquier control basado en dirección. Traefik solo la respeta si se le dice explícitamente de quién fiarse.

**Corrección aplicada** en `/etc/dokploy/traefik/traefik.yml`, punto de entrada `web`:
```yaml
entryPoints:
  web:
    address: :80
    forwardedHeaders:
      trustedIPs:
        - "127.0.0.1/32"
        - "172.16.0.1/32"     # puerta del puente por defecto
        - "172.16.11.1/32"    # la observada en produccion
```

Se declaran las tres **puertas de enlace del propio host** y no rangos amplios: son direcciones del servidor, no de contenedores vecinos, así que ningún proyecto ajeno del VPS puede falsificar la cabecera. Se incluyen las tres porque Traefik está conectado a varias redes y la ruta podría cambiar; si una deja de existir, la confianza sigue vigente por otra.

**Verificación:** un intento de acceso fallido desde una IP pública conocida quedó registrado con esa misma IP (`181.51.91.8`), mientras que el intento equivalente del día anterior había quedado como `172.16.11.1`.

**Detalle operativo que conviene recordar:** a diferencia de Nginx, que tiene `nginx -t` para validar antes de aplicar, la configuración estática de Traefik solo se comprueba al arrancar, y un error deja **los 24 sitios del VPS** caídos hasta corregirlo. El procedimiento seguro que se usó: copia de seguridad, preparar el cambio en `/tmp`, validar el YAML con `python3 -c "import yaml; yaml.safe_load(...)"`, revisar el `diff`, y solo entonces reemplazar y reiniciar.

**Fragilidad conocida:** Traefik está conectado a `bot-n8n-kleywu`, una red de otro proyecto del VPS, y de ahí sale la dirección `172.16.11.1`. Si ese proyecto desaparece, la ruta cambiará a otra de las puertas declaradas. Si aun así dejara de funcionar, el síntoma es benigno —la columna `ip` de `intento_login` vuelve a mostrar direcciones internas— y se corrige añadiendo la nueva puerta.

---

### 22. ⬜ `unsafe-inline` en la política de seguridad de contenido

**Descubierto al implementar R-03 (v1.8.0).**

**Descripción:** la CSP está activa y bloquea cualquier script, hoja de estilo, tipografía o conexión de un origen no declarado. Pero conserva `'unsafe-inline'` en `script-src` y `style-src`, lo que deja abierta la vía más común de XSS: un script inyectado *dentro* del propio HTML.

**Evidencia (medida sobre `views/`):** 157 manejadores en línea (`onclick`, `oninput`, …), 31 bloques `<script>` sin `src` y 779 atributos `style`.

**Impacto:** la CSP protege contra la carga de recursos externos maliciosos, pero no contra la ejecución de código inyectado en la página. Es media protección, y conviene no confundirla con protección completa.

**Severidad:** Media. **Esfuerzo:** L — extraer 157 manejadores a archivos `.js` con `addEventListener`, mover los 31 bloques a archivos externos y sustituir los `style` en línea por clases. Alternativamente, firmar cada bloque con un `nonce` por petición, que es menos trabajo pero obliga a tocar todas las vistas igual.

**Por qué se pospuso:** quitarlo sin hacer ese trabajo rompería la interfaz por completo. Se prefirió una CSP activa e imperfecta —que ya bloquea el origen externo— sobre no tener ninguna.

---

### 23. 🟡 Respaldos: existen y están probados, pero viven en el mismo servidor

**Punto C11 del informe técnico.** Hasta el 2026-08-15 **no había ningún procedimiento de respaldo**: solo dos volcados sueltos hechos a mano, sin automatización, sin rotación y sin que nadie hubiera probado restaurarlos. Era el único hueco de la lista que podía costar datos reales.

**Resuelto:** `sql/respaldo_breadcontrol.sh` (volcado comprimido, verificado y rotado: 7 diarias y 4 semanales) más el procedimiento completo en `docs/respaldos.md`. La restauración **se probó de verdad** el 2026-08-15: se volcó la base, se restauró en una base nueva y coincidieron las 35 tablas y los conteos de las 7 tablas principales.

**Lo que queda:** los archivos se guardan en el propio VPS. Eso cubre los accidentes probables —una consulta mal escrita, una migración equivocada— pero **no la pérdida del servidor**: si el VPS desaparece, se van los datos y los respaldos juntos. La corrección más barata es descargar el último respaldo al equipo propio de vez en cuando (`scp`, un minuto al mes); la completa, una copia automática a almacenamiento externo.

**Severidad:** Media. **Esfuerzo:** S.

---

### 24. ⬜ El registro de errores se borra en cada despliegue

**Descubierto el 2026-08-12** mientras se diagnosticaba el fallo del reporte por aprendiz.

**Descripción:** `logs/` vive dentro del contenedor de la aplicación, y Dokploy crea un contenedor nuevo en cada despliegue. El registro de errores **se pierde entero** cada vez que se publica una versión.

**Evidencia:** tras desplegar la v1.7.2, `ls -la /var/www/html/logs/` mostraba únicamente el `.htaccess`; las excepciones registradas antes del despliegue habían desaparecido.

**Impacto:** el fallo del 2026-08-12 se diagnosticó en dos minutos gracias a la traza guardada. Si hubiera ocurrido poco antes de un despliegue, esa evidencia no existiría y el diagnóstico habría partido de cero. Tampoco hay rotación: mientras el contenedor vive, el archivo crece sin límite.

**Severidad:** Media (afecta a la capacidad de diagnosticar, no al funcionamiento). **Esfuerzo:** S — montar `logs/` como volumen persistente en la configuración del servicio en Dokploy, y añadir rotación.

**Por qué sigue abierto:** es configuración de la infraestructura, no del repositorio.

---

### 25. ⬜ PHP 8.2 deja de recibir parches de seguridad el 31 de diciembre de 2026

**Detectado el 2026-08-15** al elaborar el calendario de mantenimiento (`docs/mantenimiento.md`, punto C13 del informe).

**Descripción:** el `Dockerfile` fija `php:8.2-apache`. PHP 8.2 sale de soporte de seguridad al terminar 2026: a partir de esa fecha, una vulnerabilidad descubierta en el intérprete **no recibe corrección**, y nada en el sistema avisa de ello.

**Impacto:** faltan unos cuatro meses. No es urgente hoy, pero sí tiene fecha, y es de los problemas que se descubren tarde porque no producen ningún síntoma visible.

**Severidad:** Media (creciente con el tiempo). **Esfuerzo:** S-M — cambiar el `FROM` del `Dockerfile` a `php:8.3-apache` o `php:8.4-apache`, correr las 154 pruebas y PHPStan en nivel 10 con la nueva imagen, y verificar en local antes de publicar. Esa red de pruebas es justamente lo que permite hacer el salto con confianza en vez de a ciegas.

**Relacionado:** `MySQL 8.0` alcanzó su fin de vida en abril de 2026 según el calendario de Oracle. Conviene **verificarlo** —esa fecha está en el límite de lo comprobable desde aquí— y evaluar el paso a MySQL 8.4 LTS, con respaldo previo y probando el esquema: la diferencia de `only_full_group_by` entre motores ya rompió una pantalla en producción una vez.

---

### 26. ⬜ La fecha «sin definir» se representa con un valor mágico

**Descubierto el 2026-08-15**, a raíz de un fallo real que provocó (ver más abajo).

**Descripción:** cuando un aprendiz pide para la cuenta ADSO, la entrega la fija el instructor al aprobar, no el aprendiz al pedir. Hasta entonces, `pedido_cliente.fecha_entrega` guarda `1000-01-01`, una fecha imposible que significa «todavía sin definir».

**Por qué importa:** obliga a que **cada** pieza que lea esa columna conozca la convención. Hoy son cuatro y todas la respetan:

| Consumidor | Cómo lo trata |
|---|---|
| `formatearFechaEntrega()` | muestra «Por definir» |
| `ReglasPortal::entregaSinDefinir()` | permite gestionar el pedido |
| `getPedidosPendientesPago()` | exige `aprobado_instructor = 1` antes de cobrar |
| Panel de cobro del propietario | solo lista pedidos aprobados |

**El fallo que ya causó:** la regla de las 48 horas leía esa fecha como «entrega vencida» y bloqueaba la edición y la cancelación **justo mientras el pedido esperaba aprobación** — la ventana en la que el aprendiz aún podía cambiar de opinión sin molestar a nadie. Para modificarlo tenía que pedirle al instructor que lo rechazara. Corregido el 2026-08-15 con cuatro pruebas que fijan el caso.

**La alternativa limpia** es `fecha_entrega NULL`: hace imposible ignorar el caso, porque cualquier código que la lea sin comprobarlo falla de inmediato en vez de calcular mal en silencio. Un valor mágico, en cambio, se cuela sin que nada avise — como acaba de ocurrir.

**Severidad:** Baja hoy (los cuatro consumidores la respetan), creciente con cada consumidor nuevo. **Esfuerzo:** M — migración de la columna a nullable, más ajustar esos cuatro puntos y las consultas que filtran por rango de fechas.

---

## Metodología de verificación

Cada punto de este documento se verificó, antes de escribirlo, mediante al menos uno de estos métodos:

1. Lectura directa del archivo y línea citados en "Evidencia".
2. Consulta SQL ejecutada contra la base de datos local (sincronizada con producción).
3. Búsqueda exhaustiva (`grep`) en todo el árbol del proyecto para confirmar ausencia o presencia de un patrón.
4. Para los puntos resueltos en 2026-08-06: además, ejecución real contra el servidor local (rechazo de POST sin token, bloqueo al sexto intento de login) y la suite de 151 pruebas con PHPStan en nivel 10 limpio.
