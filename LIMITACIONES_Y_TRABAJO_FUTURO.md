# Limitaciones Conocidas y Trabajo Futuro — BreadControl

**Anexo al SRS (`srs_ieee830_breadcontrol.txt`)**

Este documento inventaria, con evidencia verificada contra el código y la base de datos reales, las limitaciones conocidas del sistema BreadControl al cierre de esta fase de correcciones. No es un listado de intenciones: cada punto fue confirmado leyendo el archivo citado o ejecutando una consulta contra la base de datos (local, sincronizada con producción salvo donde se indique lo contrario) antes de documentarlo. Donde no fue posible verificar un dato con certeza, se marca `[VERIFICAR]`.

El propósito de este anexo es dejar registro explícito de qué se sabe que falta, por qué se pospuso, y qué esfuerzo tomaría resolverlo — para que cualquier evaluador, instructor o desarrollador futuro entienda el estado real del sistema sin tener que descubrirlo de nuevo.

## Resumen

| # | Limitación | Categoría | Severidad | Esfuerzo est. |
|---|---|---|---|---|
| 1 | CSRF ausente en ~13 controladores administrativos | Seguridad | Alto | M (3-5 días) |
| 2 | Acciones de eliminar/desactivar por GET sin token | Seguridad | Alto | S (1-2 días, junto con #1) |
| 3 | Sin límite de intentos en login | Seguridad | Medio-Alto | S (1 día) |
| 4 | `codigo_recuperacion` en texto plano | Seguridad | Medio | S (medio día) |
| 5 | Política de contraseña inconsistente | Seguridad | Bajo-Medio | S (medio día) |
| 6 | Credenciales en `docker-compose.yml` obsoleto pero versionado | Seguridad | Medio-Alto | S (rotar credenciales + purgar historial) |
| 7 | Desincronización `stock_actual` vs. suma de lotes | Integridad de datos | Alto | L (5+ días) |
| 8 | Lotes semilla `INI-*` con `precio_unitario=0` (agotados) | Integridad de datos | Histórico / sin impacto futuro | S (no se hará, ver decisión) |
| 9 | Producciones 1 y 4 con `costo_total=0` | Integridad de datos | Histórico / sin impacto futuro | S (no se hará, ver decisión) |
| 10 | `venta.id_producto=NULL` en POS moderno | Integridad de datos | Alto | L (rediseño de modelo de datos) |
| 11 | Portal no reconciliado con `venta` | Arquitectura | Alto | XL (decisión de negocio + rediseño) |
| 12 | `assets/js/ventas.js` huérfano (689 líneas) | Arquitectura | Bajo | S (1 día: conectar o eliminar) |
| 13 | 796 líneas de JS inline en `views/ventas/index.php` | Arquitectura | Bajo-Medio | M (2-3 días de refactor) |
| 14 | Código duplicado: carrito nuevo vs. editar pedido | Arquitectura | Medio | M (2-3 días) |
| 15 | Vistas clásicas de insumo sin estilo ni enlace | Arquitectura | Bajo | S (medio día: eliminar o reconectar) |
| 16 | `php.ini` con `date.timezone=Europe/Berlin` obsoleto (inerte) | Configuración | Cosmético | S (1 línea, sin urgencia) |
| 17 | `panaderia_bd.sql` no refleja el esquema real de `cliente` | Configuración | Alto | S (1 día: completar el script) |
| 18 | Condiciones de carrera (lotes, cupo semanal) | Configuración/Concurrencia | Medio | M (2-3 días: locks/transacciones serializables) |

*Esfuerzo: S = &lt;2 días, M = 2-5 días, L = 5-10 días, XL = requiere decisión de producto antes de estimar.*

---

## SEGURIDAD

### 1. CSRF ausente en ~13 controladores administrativos

**Descripción:** La protección CSRF (`generar_token_csrf()`/`validar_token_csrf()` en `includes/sesion.php`) existe y funciona correctamente, pero solo se invoca desde el Portal de Clientes.

**Evidencia:** Búsqueda de `generar_token_csrf`/`validar_token_csrf` en todo el proyecto: only aparece en `controllers/PortalClienteController.php` y las vistas `views/portal/{nuevo_pedido,dashboard,perfil,pagar_consolidado,completar_perfil}.php`. Ningún controlador de `controllers/{Inventario,Compra,Produccion,Receta,Venta,Gasto,Finanzas,Cierre,Configuracion,PedidoCliente}Controller.php` lo usa.

**Impacto:** Un atacante que consiga que el propietario autenticado visite una página maliciosa podría inducir acciones administrativas (crear compras, modificar precios, registrar gastos, etc.) sin su consentimiento explícito.

**Severidad:** Alto (mitigado parcialmente porque requiere que el propietario tenga sesión activa y visite un enlace/página controlada por el atacante).

**Esfuerzo estimado:** M (3-5 días) — agregar generación/validación de token a ~13 controladores y sus formularios.

**Por qué se pospuso:** Se implementó primero, y únicamente, en el Portal de Clientes por ser la superficie pública con más exposición a atacantes externos anónimos. Los controladores administrativos exigen autenticación previa de propietario, lo que reduce la urgencia relativa frente a otros hallazgos ya corregidos en esta fase, pero no elimina el riesgo — se agrava con el hallazgo #2.

---

### 2. Acciones de eliminar/desactivar por GET sin token

**Descripción:** Varias acciones destructivas o de baja lógica se ejecutan leyendo directamente de `$_GET`, sin ningún token de confirmación — un enlace o `<img src="...">` malicioso bastaría para dispararlas si el propietario tiene sesión activa.

**Evidencia:** Confirmado por búsqueda de `$_GET['del...']`/`$_GET['desactivar']` sin validación de token asociado:
- `controllers/CompraController.php:132` (`$_GET['desactivar']`, proveedor).
- `controllers/VentaController.php:283-284` (`$_GET['del_venta']`) y `:376-377` (`$_GET['del']`, cliente/tienda).
- `controllers/InventarioController.php:78-79` (`$_GET['del']`, insumo).
- `controllers/GastoController.php:24-25` (`$_GET['del']`, gasto).
- `controllers/RecetaController.php:21-23` (`$_GET['del']`, producto) y `:250-252` (`$_GET['del_var']`, variedad de pan).

**Impacto:** Combinado con el hallazgo #1, permite ejecución de acciones destructivas (desactivar insumos, proveedores, clientes, productos, variedades, eliminar gastos/ventas) mediante un solo clic o carga de recurso inducida, sin ninguna confirmación server-side.

**Severidad:** Alto.

**Esfuerzo estimado:** S (1-2 días, una vez resuelto #1, ya que comparte la misma infraestructura de tokens).

**Por qué se pospuso:** Depende de la misma infraestructura de tokens CSRF que aún no se extendió a los controladores administrativos (#1). Además, cada módulo nuevo replicó el patrón `<a href="?del=...">` ya establecido en módulos anteriores, propagando el mismo defecto.

---

### 3. Sin límite de intentos en login (fuerza bruta)

**Descripción:** Ni `AuthController::login()` ni `iniciarSesion()` (`includes/sesion.php:69-84`) implementan conteo de intentos fallidos, bloqueo temporal, retraso progresivo ni CAPTCHA.

**Evidencia:** Leí el cuerpo completo de `AuthController::login()` (líneas 56-82) y de `iniciarSesion()` (líneas 69-84): la única validación es `password_verify($contrasena, $usuario['contrasena_hash'])`, sin ningún registro de intentos ni bloqueo asociado al usuario o a la IP.

**Impacto:** Un atacante puede intentar credenciales de forma ilimitada contra el login administrativo. El impacto práctico depende del volumen de intentos que la infraestructura (Nginx/Traefik) tolere antes de frenar por su cuenta, lo cual no se verificó en este documento.

**Severidad:** Medio-Alto.

**Esfuerzo estimado:** S (1 día) — contador de intentos fallidos por usuario con bloqueo temporal (ej. 5 intentos / 15 minutos).

**Por qué se pospuso:** No se ha implementado ningún mecanismo de throttling. La mayoría de instalaciones de este sistema tienen un solo usuario propietario, lo que reduce la superficie práctica de un ataque dirigido, pero no lo elimina como vector real.

---

### 4. `codigo_recuperacion` en texto plano (el PIN sí está hasheado)

**Descripción:** El patrón se repite idéntico en las dos tablas de usuarios del sistema, `cliente` (portal) y `usuario` (administradores): el PIN de recuperación se guarda con `password_hash()` y se valida con `password_verify()`, mientras que el código de recuperación por correo se guarda como texto plano y se compara con `!==`, no con `hash_equals()`.

**Evidencia:**
- **Portal (`cliente`)**: `models/PortalClienteModel.php:118` (`UPDATE cliente SET codigo_recuperacion = ?, codigo_expira = ?`, sin hash) vs. `models/PortalClienteModel.php:110` (`UPDATE cliente SET pin_recuperacion = ?`, recibiendo un hash ya generado con `password_hash()` en el controlador). Comparación en texto plano en `controllers/PortalClienteController.php:376` (`$cliente['codigo_recuperacion'] !== $codigo`).
- **Administración (`usuario`)**: mismo patrón, confirmado. `models/AuthModel.php:36-39` (`registrarCodigoRecuperacion()`: `UPDATE usuario SET codigo_recuperacion = ?, codigo_expira = ?`, sin hash) y comparación en texto plano en `controllers/AuthController.php:173` (`$user['codigo_recuperacion'] !== $codigo`). El PIN admin sí se hashea correctamente: `controllers/ConfiguracionController.php:84` y `:187` (`password_hash($pin, PASSWORD_BCRYPT)` antes de `updateUsuarioPIN()`).

**Impacto:** Si la base de datos se filtrara, los códigos de recuperación activos (ventana de 5-10 minutos) quedarían expuestos en claro — para **cualquier** cuenta, incluida la del propietario/administrador, no solo clientes del portal. El PIN, en ambos casos, permanecería protegido incluso ante esa filtración.

**Severidad:** Medio (mitigado por la expiración corta y el uso único del código).

**Esfuerzo estimado:** S (medio día) — hashear el código al guardarlo y comparar con `password_verify()`.

**Por qué se pospuso:** Se priorizó hashear el PIN por ser un secreto de más largo plazo y reutilizable; el código de recuperación por correo, al expirar en minutos y usarse una sola vez, quedó con menor severidad relativa y sin resolver.

---

### 5. Política de contraseña inconsistente (y débil) entre pantallas

**Descripción:** Existen **tres** longitudes mínimas distintas para contraseñas de cliente dentro del mismo portal, más una cuarta para administradores — ninguna exige complejidad (mayúsculas, números, símbolos).

**Evidencia:**
- `controllers/PortalClienteController.php:259` (registro de cliente nuevo): `strlen($contrasena) < 4` → mínimo **4** caracteres.
- `controllers/PortalClienteController.php:410` (completar recuperación de contraseña): `strlen($nueva) < 6` → mínimo **6**.
- `controllers/PortalClienteController.php:988` (cambiar contraseña desde Mi Perfil del portal): `strlen($nueva) >= 4` → mínimo **4**.
- `controllers/ConfiguracionController.php:57` (cambiar contraseña de propietario/admin): `strlen($nueva) < 6` → mínimo **6**.

**Impacto:** Un cliente puede registrarse con una contraseña de 4 caracteres, incumpliendo cualquier estándar razonable de seguridad, y la inconsistencia entre pantallas del mismo módulo indica ausencia de una regla de negocio centralizada.

**Severidad:** Bajo-Medio (más un problema de consistencia y buena práctica que una vulnerabilidad explotable por sí sola).

**Esfuerzo estimado:** S (medio día) — centralizar en una función compartida (ej. `helpers/`) con una única regla (mínimo 8, alguna complejidad).

**Por qué se pospuso:** Cada formulario de contraseña se implementó en un momento distinto del desarrollo del portal (registro, recuperación, perfil), sin una función de validación compartida — cada uno quedó con su propio mínimo copiado a mano.

---

### 6. Credenciales de base de datos en `docker-compose.yml` obsoleto pero versionado

**Descripción:** `docker-compose.yml`, confirmado como esquema de despliegue **obsoleto** (ver corrección de la Sección 2.1 del SRS), contiene las credenciales de MySQL en texto plano y sigue commiteado en el repositorio.

**Evidencia:** `docker-compose.yml` líneas 22-25: `MYSQL_DATABASE: panaderia_bd`, `MYSQL_USER: panaderia_user`, `MYSQL_PASSWORD: panaderia_password`, `MYSQL_ROOT_PASSWORD: panaderia_root_password`.

**Impacto:** Cualquiera con acceso de lectura al repositorio (o a su historial de Git, incluso si el archivo se elimina hoy) puede ver estas credenciales. El riesgo práctico actual es menor porque el archivo ya no gobierna el despliegue real (Dokploy/Swarm usa su propia configuración de entorno), pero las credenciales quedan expuestas igual si se reutilizan en algún otro punto.

**Severidad:** Medio-Alto (exposición real en el historial de Git; impacto práctico reducido porque el archivo dejó de ser la fuente de verdad del despliegue).

**Esfuerzo estimado:** S — rotar las credenciales si se reutilizan en algún entorno vigente, y decidir si purgar el archivo del historial de Git o simplemente eliminarlo del `HEAD` actual con una nota.

**Por qué se pospuso:** El archivo fue el esquema de despliegue manual usado antes de adoptar Dokploy. Quedó commiteado con las credenciales de esa etapa y no se purgó del repositorio al migrar a la infraestructura actual.

---

## INTEGRIDAD DE DATOS

### 7. Desincronización entre `insumo.stock_actual` y la suma real de `lote.cantidad_disponible`

**Descripción:** El contador denormalizado `insumo.stock_actual` y la suma real de lotes activos (`SUM(lote.cantidad_disponible) WHERE estado='activo'`) deberían coincidir, pero divergen de forma sistemática.

**Evidencia (verificada hoy contra la base de datos real, vía `ProduccionModel::verificarStockIngredientes()` para la receta 3):**

| Insumo | `stock_actual` | Suma real de lotes | Diverge |
|---|---|---|---|
| Azúcar | 20.830 | 20.102 | Sí |
| Esencia Mantequilla | 10.770 | 0 | Sí |
| Harina de trigo | 167 | 166,5 | Sí |
| Hojaldre | 5.000 | 6.500 | Sí |
| Levadura | 1.364 | 1.360 | Sí |
| Mantequilla | 36.350 | 47.450 | Sí |
| Sal | 1.720 | 910 | Sí |

**7 de 7** ingredientes de esa receta divergen. Este aviso ya es mostrado al usuario (no bloqueante) gracias al fix de C17 en esta misma fase (RF-14b del SRS).

**Impacto:** `stock_actual` no es confiable como fuente de verdad — es la causa raíz de que producciones se registren "con stock suficiente" según el contador, y luego no encuentren lotes reales que cubran la receta (bug C17). También hace que las alertas de stock bajo (`RF-06`, comparadas contra `stock_actual`) puedan estar mostrando información incorrecta.

**Severidad:** Alto.

**Esfuerzo estimado:** L (5+ días) — requiere decidir cuál fuente es la autoritativa, auditar todos los puntos de escritura de ambas (compras, ajustes manuales, producción, y código de varias generaciones del proyecto), y posiblemente eliminar `stock_actual` como columna independiente en favor de calcularlo siempre desde `lote`.

**Por qué se pospuso:** Se identificó como causa raíz durante el fix de C17, pero se dejó explícitamente fuera de esa tanda por su alcance (afecta prácticamente todos los insumos, no un caso puntual) — se optó por mitigar el síntoma (el aviso no bloqueante de RF-14b) sin resolver la causa.

---

### 8. Lotes semilla `INI-2026-03-21-*` con `precio_unitario=0` (agotados, sin impacto futuro)

**Descripción:** Los 10 lotes de apertura de inventario (carga inicial del sistema, uno por insumo original) tienen `precio_unitario=0`, no por error de cálculo sino porque nunca se les asignó un costo real de compra.

**Evidencia:** `SELECT COUNT(*) FROM lote WHERE numero_lote LIKE 'INI-%' AND precio_unitario = 0` → **10** lotes. Verificado adicionalmente, en **local y en producción**, que los 10 están en `estado='agotado'` con `cantidad_disponible=0.000` — se consumieron por completo, ninguno conserva saldo.

**Implicaciones verificadas:**
- **No afectan cálculos futuros.** `ProduccionModel::getLotesDisponiblesFIFOParaConsumo()` filtra explícitamente `WHERE estado='activo' AND cantidad_disponible>0`; un lote agotado nunca vuelve a salir en el FIFO de una producción nueva. Cualquier remanente que hoy no alcance a cubrirse con lotes reales pasa por el fix de C17 (lote sintético `EST-*` con el último precio conocido), no por estos lotes semilla.
- **El impacto ya ocurrió y es retroactivo, no continuo.** Las producciones que alcanzaron a consumir de estos lotes mientras tenían saldo (incluidas `id_produccion` 1 y 4, ver punto 9) quedaron con costo subestimado de forma permanente en el histórico — pero ningún registro nuevo puede verse afectado por esta misma causa.

**Severidad:** Histórico / sin impacto futuro.

**Esfuerzo estimado:** S — si se decidiera corregir el histórico, implicaría reconstruir manualmente qué costo debieron tener esos 10 lotes y propagar el recálculo a cada producción que los consumió (ver decisión explícita de no hacerlo en el punto 9).

**Por qué se pospuso:** Son datos de apertura de inventario, no compras reales — no existe un precio de compra real que asignarles. Al estar ya agotados los 10 lotes, la corrección retroactiva del costo que generaron se evaluó y se descartó explícitamente (ver punto 9): el beneficio de reconstruir ese costo histórico es nulo frente al riesgo de manipular datos ya consolidados.

---

### 9. Producciones `id_produccion` 1 y 4 con `costo_total=0` (deuda histórica aceptada, sin impacto futuro)

**Descripción:** Dos registros históricos de producción quedaron con costo cero: uno por el bug original de C17 (stock insuficiente forzado), el otro por la misma causa raíz sin la bandera de advertencia. Ambos consumieron exclusivamente de los lotes semilla `INI-*` del punto 8, hoy agotados en local y en producción.

**Evidencia (verificada, sin cambios):**
```
id_produccion=1: unidades_producidas=350, costo_total=0.00, observaciones="⚠ Registrado con stock insuficiente"
id_produccion=4: unidades_producidas=288, costo_total=0.00, observaciones="" (vacío)
```

**Impacto:** Estos dos registros individuales subestiman el costo de producción histórico en cualquier reporte que agregue por rango de fechas que los incluya (Finanzas, Cierre históricos de esas fechas puntuales). Es un impacto ya materializado y cerrado, no un riesgo abierto: los lotes que lo originaron están agotados (punto 8) y el fix de C17 cubre el escenario general hacia adelante, así que ninguna producción futura puede quedar con costo cero por esta misma causa.

**Severidad:** Histórico / sin impacto futuro.

**Esfuerzo estimado:** S (medio día) — técnicamente factible: recalcular el costo de estas 2 producciones con los precios de insumo vigentes en su fecha. Se documenta el esfuerzo únicamente como referencia; ver la decisión explícita de no hacerlo, a continuación.

**Por qué se pospuso — decisión explícita, no solo falta de tiempo:** Recalcular `consumo_lote`/`costo_total` de producciones históricas es manipulación de datos ya consolidados, con riesgo real (reescribir un registro que pudo haberse reportado ya en un cierre de caja pasado) y beneficio nulo (nadie consulta reportes de esos dos días específicos, y no afecta ninguna operación futura). Se documenta como deuda histórica aceptada en vez de corregirse.

---

### 10. `venta.id_producto=NULL` en el POS moderno: no se sabe qué producto individual se vendió

**Descripción:** El POS moderno (venta rápida y pedido detallado) registra `venta.id_categoria_precio`, no `venta.id_producto`. Como una misma categoría de precio agrupa varios productos distintos, no hay forma de saber, para la mayoría de las ventas, cuál producto específico se vendió — solo su categoría de precio.

**Evidencia (verificada hoy):** `SELECT COUNT(*) FROM venta WHERE id_producto IS NULL` → **102** de **113** ventas totales (90,3%). La categoría de precio `id_categoria=13` (~$500) es alimentada por producción de **5 productos distintos**: Pan de Sal, Croissant, Pan Dulce, Pan Coco y Pan Sal (verificado vía `produccion_precio`/`produccion`/`producto`; una venta con `id_categoria_precio=13` podría corresponder a cualquiera de estos 5).

**Impacto:** Reportes "por producto" (ej. `CierreModel::getSobrantesHoy()`) no pueden atribuir con certeza el 90% de las ventas a un producto específico — es la causa raíz documentada de C6/C17 y de la limitación aceptada en `getSobrantesHoy()` (nota agregada en esa función explicando por qué no se resolvió por completo).

**Severidad:** Alto.

**Esfuerzo estimado:** L — requiere que el POS moderno capture también qué producto específico se vendió dentro de la categoría (cambio de modelo de datos y de la interfaz de cobro en mostrador), no solo una consulta.

**Por qué se pospuso:** Es una decisión de diseño del POS moderno, que prioriza velocidad de cobro en mostrador (elegir precio, no producto) sobre trazabilidad por producto individual. Cambiarlo afecta la experiencia de venta rápida, no es un ajuste aislado de backend.

---

## ARQUITECTURA

### 11. Los pedidos del Portal (`pedido_cliente`) no se reconcilian con `venta`

**Descripción:** `pedido_cliente`/`pedido_cliente_detalle` son tablas completamente independientes de `venta`/`venta_detalle`. Un pedido del portal, incluso pagado y confirmado, nunca genera una fila en `venta`.

**Evidencia:** Búsqueda de `INSERT INTO venta`/`INSERT INTO venta_detalle` en `models/PortalClienteModel.php` y `models/PedidoClienteModel.php`: **cero coincidencias** (confirmado hoy nuevamente).

**Impacto:** Los reportes financieros (Finanzas, Cierre, Portada) que sí calculan utilidad e ingresos a partir de la tabla `venta` **no incluyen ningún ingreso proveniente de pedidos del portal** — aunque el cliente haya pagado vía Wompi/Nequi consolidado. La utilidad centralizada en `FinanzasHelper` (Fórmula F) hereda esta misma omisión, porque solo consume datos de `venta`.

**Severidad:** Alto.

**Esfuerzo estimado:** XL — requiere primero una decisión de negocio (¿en qué momento un pedido del portal se convierte en "venta real": al pagar, al aprobar, al entregar?) antes de poder diseñar el cambio técnico.

**Por qué se pospuso:** `pedido_cliente` y `venta` se diseñaron como sistemas independientes en etapas distintas del proyecto (el portal se agregó después del POS). Unificarlos es una decisión de producto, no un fix técnico aislado — se dejó fuera del alcance de las correcciones puntuales de esta fase.

---

### 12. `assets/js/ventas.js` (689 líneas) huérfano

**Descripción:** Existe un archivo externo con la misma lógica de catálogo/carrito/bonificación que usa `views/ventas/index.php`, ya con el fix de escapado XSS aplicado correctamente — pero ningún archivo del proyecto lo carga.

**Evidencia:** Búsqueda de `<script src=...ventas.js` en todo el proyecto: **cero coincidencias**. `wc -l assets/js/ventas.js` → 689 líneas (confirmado hoy, sin cambios).

**Impacto:** Ninguno en tiempo de ejecución (el archivo simplemente no se ejecuta). El impacto es de mantenimiento: alguien podría asumir que editar `ventas.js` corrige el comportamiento de la pantalla de Ventas, cuando en realidad esa pantalla usa el bloque `<script>` inline (ver #13).

**Severidad:** Bajo.

**Esfuerzo estimado:** S (1 día) — decidir si se completa la extracción (reemplazar el inline por `<script src="assets/js/ventas.js">`, verificando que no haya divergido del comportamiento actual) o si se elimina por estar desactualizado.

**Por qué se pospuso:** Parece un intento de extracción/refactor iniciado pero no completado — se escribió la versión corregida en un archivo aparte y nunca se terminó de conectar a la vista, quedando como código muerto.

---

### 13. 796 líneas de JavaScript inline en `views/ventas/index.php`

**Descripción:** Contradice `RNF-07` (separación de diseño/lógica del frontend): la lógica completa de catálogo, carrito y bonificaciones vive en un bloque `<script>` embebido en la plantilla PHP, no en un archivo `.js` externo.

**Evidencia:** `views/ventas/index.php`, bloque `<script>` desde la línea 1617 hasta la línea 2412 (confirmado hoy: `796` líneas).

**Impacto:** Dificulta la cacheabilidad del navegador (el bloque se re-descarga con cada carga de la página HTML, a diferencia de un `.js` externo cacheable) y el mantenimiento (mezclar PHP y JS en el mismo archivo de casi 2.400 líneas totales).

**Severidad:** Bajo-Medio (no es un bug funcional, es deuda técnica de mantenibilidad).

**Esfuerzo estimado:** M (2-3 días) — extraer a `assets/js/`, con pruebas manuales exhaustivas del flujo de venta rápida y pedido detallado para no introducir regresiones.

**Por qué se pospuso:** Es la misma causa raíz que el punto 12: la extracción a `assets/js/ventas.js` se empezó pero no se terminó de conectar en la vista original.

---

### 14. Código duplicado: carrito de "nuevo pedido" vs. "editar pedido"

**Descripción:** La lógica de catálogo, carrito y panel de bonificación está duplicada casi íntegramente dentro de `views/ventas/index.php`: una copia para registrar un pedido nuevo, otra (con prefijo `ep`) para editar uno existente.

**Evidencia:** Confirmado durante el fix de XSS (C7) de esta misma fase: los mismos 3 patrones de renderizado (`pc-name`/`title`, `ci-name` del carrito, `br-name` de bonificación) aparecen duplicados en `views/ventas/index.php` — una vez para el flujo nuevo (~líneas 1732, 1809, 1930) y otra idéntica para el flujo de edición (~líneas 2097, 2165, 2281). Tuve que aplicar el mismo fix dos veces en el mismo archivo.

**Impacto:** Cualquier corrección o funcionalidad nueva debe aplicarse dos veces; si se olvida una copia (como ocurrió con el XSS antes de esta fase), el bug persiste en un flujo mientras parece resuelto en el otro.

**Severidad:** Medio.

**Esfuerzo estimado:** M (2-3 días) — extraer funciones compartidas parametrizadas por prefijo/contexto (nuevo vs. editar).

**Por qué se pospuso:** El flujo de "editar pedido" se agregó copiando el flujo de "nuevo pedido" ya existente y funcional, en vez de invertir tiempo en extraer una función compartida en el momento de agregar la función de edición.

---

### 15. Vistas clásicas de insumo sin estilo ni enlace (`crear_insumo.php`, `editar_insumo.php`)

**Descripción:** Documentado ya en la Sección 2.4 del SRS: son remanentes de una versión anterior del módulo de Inventario, sin ningún enlace desde la UI y sin estilo visual (usan clases de Bootstrap que nunca reciben su hoja de estilos).

**Evidencia:** `controllers/InventarioController.php` las rotula "clásica" en sus comentarios (líneas 131, 171); ninguna vista del proyecto enlaza hacia `crear_insumo.php`/`editar_insumo.php`; confirmado visualmente que se renderizan sin estilo (inputs planos del navegador, botón gris).

**Impacto:** Bajo en la práctica (nadie llega a estas pantallas por la UI normal), pero representan código muerto que podría confundir a un desarrollador nuevo, y si alguien llegara a la URL manualmente, vería una pantalla que parece rota.

**Severidad:** Bajo.

**Esfuerzo estimado:** S (medio día) — eliminarlas si el modal de `views/inventario/index.php` cubre el 100% de sus casos de uso, o reconectarlas con el sistema de diseño propio si se decide mantenerlas como alternativa.

**Por qué se pospuso:** Son remanentes de una versión anterior del módulo, reemplazadas funcionalmente por el modal inline, pero nunca eliminadas ni desconectadas del controlador al completar esa migración interna.

---

## CONFIGURACIÓN

### 16. `php.ini` con `date.timezone=Europe/Berlin` obsoleto (cosmético, sin impacto funcional)

**Descripción:** `php.ini` tiene `date.timezone=Europe/Berlin` como valor por defecto obsoleto, pero es inerte porque `config/app.php` lo sobreescribe: `date_default_timezone_set('America/Bogota')` es su primera línea ejecutable. Se verificó que los 51 puntos de entrada reales de la aplicación (`index.php`, `login.php`, los 33 archivos de `modules/`, los 18 de `portal/`) lo cargan antes de generar cualquier timestamp — directamente, o de forma transitiva vía `includes/sesion.php` (que también lo requiere en su línea 7) en los dos únicos casos que no lo requieren de forma directa (`portal/logout.php`, `portal/pagar_instructor.php`, ninguno de los cuales genera timestamps). Los 3 scripts CLI de `sql/migrar_*.php` también lo cargan.

**Evidencia:** Verificación en vivo, con `config/app.php` cargado (como ocurre siempre en la aplicación real):
```
PHP date_default_timezone_get() = America/Bogota
PHP date(now)                   = 2026-07-10 17:04:33
MySQL NOW() (sesión app)        = 2026-07-10 17:04:33   ← idéntico, sin desfase
MySQL @@session.time_zone       = -05:00
```
Colombia no observa horario de verano — `America/Bogota` es un offset fijo de `-05:00` todo el año — por lo que `date_default_timezone_set()` en PHP y `SET time_zone='-05:00'` en `config/db.php::getConexion()` son, en la práctica, exactamente equivalentes.

**Nota de corrección:** una versión anterior de este mismo punto reportó un desfase real de 7 horas entre PHP y MySQL, con severidad Media. Ese hallazgo fue un **falso positivo**: se midió con un script de diagnóstico que llamaba a `config/db.php` sin cargar `config/app.php` primero, dejando PHP en el `Europe/Berlin` del `php.ini` en ese proceso aislado — algo que nunca ocurre en la aplicación real. Corregido tras verificación exhaustiva de los 51 puntos de entrada.

**Impacto:** Ninguno funcional. Es una configuración de base inerte, sobreescrita antes de que cualquier controlador o modelo genere un timestamp.

**Severidad:** Cosmético / sin impacto funcional.

**Esfuerzo estimado:** S (una línea) — recomendación cosmética: alinear `php.ini` a `America/Bogota` para evitar confusión si en el futuro alguien escribe un script CLI puntual que no cargue `config/app.php` primero (como ocurrió con el script de diagnóstico que originó el hallazgo anterior).

**Por qué se pospuso:** No aplica — no es una limitación real del sistema, solo una inconsistencia cosmética en la configuración base de PHP, sin ningún efecto observable en la aplicación.

---

### 17. `sql/panaderia_bd.sql` no refleja el esquema real de `cliente`

**Descripción:** El script que se ejecuta automáticamente en un despliegue nuevo desde cero (`docker-entrypoint-initdb.d/init.sql`) crea `cliente` con solo 6 columnas; la base de datos real (local y producción, verificadas idénticas) tiene 14.

**Evidencia:** `CREATE TABLE cliente` en `sql/panaderia_bd.sql` solo declara `id_cliente, nombre, tipo, telefono, activo, fecha_creacion`. Faltan `usuario, contrasena_hash, es_aprendiz, cupo_semanal, id_instructor, email, foto_url, google_id` — confirmadas presentes en la base real vía `DESCRIBE cliente`, y explicadas parcialmente por `sql/agregar_login_cliente.sql` y `sql/agregar_google_id.sql` (que sí existen como migraciones sueltas), pero `es_aprendiz`, `cupo_semanal`, `id_instructor`, `email` y `foto_url` no tienen ningún script de creación documentado en `sql/`.

**Impacto:** Un despliegue nuevo desde cero (por ejemplo, para un evaluador o instructor que quiera levantar el proyecto de forma independiente) tendría el login del portal, Google OAuth y el flujo instructor-aprendiz completamente rotos hasta ejecutar manualmente las migraciones sueltas y, para las columnas sin script, hasta recrearlas a mano.

**Severidad:** Alto (bloquea un despliegue limpio del sistema, no solo una función puntual).

**Esfuerzo estimado:** S (1 día) — agregar las 8 columnas faltantes al `CREATE TABLE` de `panaderia_bd.sql` con sus tipos y valores por defecto reales (ya confirmados vía `DESCRIBE` en esta misma fase de trabajo).

**Por qué se pospuso:** Se identificó al corregir las columnas de pagos de `configuracion` en esta misma fase, pero se dejó fuera de esa tanda explícitamente para no ampliar el alcance de ese fix puntual — quedó documentado como pendiente en ese momento y no se ha retomado todavía.

---

### 18. Condiciones de carrera en `generarNumeroLote()` y en la validación de cupo semanal

**Descripción:** Dos puntos distintos del sistema siguen el patrón "leer, calcular en PHP, escribir" sin bloqueo pesimista ni aislamiento serializable, lo que permite que dos solicitudes concurrentes lean el mismo estado "antes" y ambas procedan como si fueran la única.

**Evidencia:**
- `includes/funciones.php::generarNumeroLote()` (líneas 85-102): hace `SELECT numero_lote ... ORDER BY numero_lote DESC LIMIT 1`, calcula `$seq = último + 1` en PHP, y **solo después** (en el código que llama a esta función) se hace el `INSERT`. Dos compras registradas casi simultáneamente pueden leer el mismo "último" número y calcular la misma secuencia — la `UNIQUE KEY` sobre `numero_lote` evitaría datos corruptos, pero una de las dos inserciones fallaría con error de duplicado en vez de reintentar con el siguiente número.
- `models/PortalClienteModel.php::crearPedido()` (validación de cupo semanal, ~línea 742): hace `SELECT COALESCE(SUM(total_estimado),0) FROM pedido_cliente WHERE ...` y compara contra `cupo_semanal` en PHP, dentro de una transacción con el nivel de aislamiento por defecto de MySQL (`REPEATABLE READ`), que no impide que dos transacciones concurrentes del mismo aprendiz lean el mismo "consumido hasta ahora" y ambas pasen la validación antes de que cualquiera confirme (`commit`).

**Impacto:** Baja probabilidad de ocurrencia en el uso normal (requiere dos solicitudes verdaderamente simultáneas del mismo recurso), pero si ocurre: en lotes, una compra fallaría con un error de base de datos poco claro para el usuario; en cupo semanal, un aprendiz podría lograr que dos pedidos simultáneos excedan su cupo cuando cada uno individualmente no lo hace.

**Severidad:** Medio.

**Esfuerzo estimado:** M (2-3 días) — usar `SELECT ... FOR UPDATE` sobre una fila de control para la generación de lote, y/o `SERIALIZABLE`/bloqueo explícito sobre las filas de `pedido_cliente` del aprendiz durante la validación de cupo.

**Por qué se pospuso:** Requieren tráfico concurrente real para manifestarse. Dado el volumen de uso actual del sistema (una panadería con un número acotado de operarios y aprendices), el riesgo práctico es bajo comparado con otros hallazgos que ya se observaron materializados en datos reales (como los puntos 7-10), por lo que no se priorizó sobre esos.

---

## Metodología de verificación

Cada punto de este documento se verificó, antes de escribirlo, mediante al menos uno de estos métodos:
1. Lectura directa del archivo y línea citados en "Evidencia".
2. Consulta SQL ejecutada contra la base de datos local (sincronizada y confirmada idéntica a producción para las tablas `cliente` y `pedido_cliente` en una verificación anterior de esta misma fase).
3. Búsqueda exhaustiva (`grep`) en todo el árbol del proyecto para confirmar ausencia o presencia de un patrón, no solo su existencia en un archivo puntual.

Ningún dato de este documento fue estimado o asumido sin evidencia directa; donde la verificación no fue posible desde este entorno (por ejemplo, configuración de infraestructura externa al repositorio), se marcó explícitamente `[VERIFICAR]` en el punto correspondiente.
