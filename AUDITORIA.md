# Auditoría de BreadControl

**Alcance:** los 148 archivos PHP del proyecto (config, includes, helpers, controllers, models, modules, views, portal, sql) — no se modificó ningún archivo, solo lectura y análisis.
**Metodología:** revisión manual completa de cada controlador y modelo, lectura íntegra de las vistas, verificación cruzada contra el esquema real en `sql/panaderia_bd.sql` y las migraciones sueltas de `sql/`, y pruebas de consistencia entre pantallas que muestran los mismos datos (utilidad, stock, ventas).

## Resumen ejecutivo

Los hallazgos más importantes, en orden de impacto:

1. **El esquema de base de datos no está versionado ni actualizado.** `sql/panaderia_bd.sql` (el único script que se ejecuta automáticamente al desplegar) no incluye las tablas de pedidos/pagos del portal ni varias columnas de `cliente` que el código usa activamente. Un despliegue Docker desde cero rompe el portal de clientes por completo. Ver **C1**.
2. **La protección CSRF existe pero solo se usa en el Portal de Clientes.** Las funciones `generar_token_csrf()`/`validar_token_csrf()` están implementadas correctamente y se usan en 6 puntos del portal — y en **cero** puntos de los ~13 controladores administrativos. Ver **C2**.
3. **Hay evidencia real de datos corruptos en producción** (`venta.id_venta = 154`, cantidad = 2147483647) causada por falta de validación de cantidades en el carrito de "pedido detallado". La misma ruta de código tampoco valida el precio enviado por el cliente ni el stock disponible. Ver **C3, C4**.
4. **XSS almacenado** vía el nombre de una variedad de pan, explotable contra cualquier cliente del portal que abra "Nuevo pedido". Ver **C7**.
5. **Existen cuatro fórmulas distintas de "utilidad"** en cuatro pantallas distintas (portada, Cierre del día, Finanzas, Tablero), que pueden mostrar cifras diferentes para el mismo día. Ver **L1**.
6. Credenciales de base de datos en texto plano dentro de `docker-compose.yml`, committeado al repositorio. Ver **C13**.

Se identificaron **62 hallazgos** (después de consolidar patrones repetidos): **18 críticos, 16 de lógica, 9 de frontend, 19 de inconsistencias.**

---

## 1. ERRORES CRÍTICOS

### C1 — Esquema de base de datos desactualizado; despliegue fresco rompe el portal
**Archivo:** `sql/panaderia_bd.sql`, `docker-compose.yml:28` · **Severidad:** crítico

`docker-compose.yml` monta únicamente `sql/panaderia_bd.sql` como `docker-entrypoint-initdb.d/init.sql`. Ese dump **no contiene** las tablas `pedido_cliente`, `pedido_cliente_detalle`, `pago_pedido`, `pago_abono` (viven en `sql/crear_pedidos.sql`, que nunca se ejecuta automáticamente), ni las columnas de `cliente` agregadas por migraciones sueltas (`usuario`, `contrasena_hash`, `google_id`, `cupo_semanal`, `id_instructor`), ni columnas que el código usa pero que **no tienen ningún script de creación en todo el repositorio**: `cliente.foto_url`, `cliente.es_aprendiz`, `cliente.notas`, `cliente.es_beneficiaria`, `pedido_cliente.mensaje_propietario`, `pedido_cliente.id_tienda_destino`. Un contenedor MySQL nuevo (volumen vacío) genera una base de datos que rompe con error SQL cualquier acción del portal de clientes y varias del back-office (gestión de tiendas, pedidos). El propio repositorio ya documenta este patrón de incidentes (`sql/agregar_login_cliente.sql:3`: *"la BD del VPS nunca tuvo estas columnas, solo la local las tenia"*).
**Solución:** generar un `mysqldump` completo y actualizado del esquema real, reemplazar `panaderia_bd.sql`, y automatizar la ejecución de cualquier migración pendiente como parte del build/deploy (o consolidar todas las migraciones en el dump base). Considerar una tabla de control de versiones de esquema.

### C2 — CSRF implementado solo en el Portal, ausente en todo el back-office administrativo
**Archivo:** todos los `controllers/*Controller.php` excepto `PortalClienteController.php` · **Severidad:** crítico

`includes/sesion.php` define `generar_token_csrf()`/`validar_token_csrf()` (token de 32 bytes, comparación con `hash_equals`, implementación correcta). Se usan en 6 puntos de `PortalClienteController.php` (`dashboard`, `nuevoPedido`, `perfil`, `completarPerfil`, `pagarConsolidado`, `exportarPedidosDashboard`) y verificablemente en **ningún otro controlador** (`grep -rn "validar_token_csrf(" controllers/` solo devuelve coincidencias en ese archivo). Esto significa que **todas** las acciones administrativas que cambian estado — registrar/editar/eliminar insumos, compras, producciones, recetas, ventas, gastos, cierres, configuración de pagos, gestión de pedidos — son vulnerables a CSRF: una página maliciosa visitada por el propietario mientras tiene sesión activa puede enviar esos formularios en su nombre.
**Solución:** agregar el campo oculto `csrf_token` (usando `generar_token_csrf()`) a todos los formularios POST administrativos y validar con `validar_token_csrf()` al inicio de cada acción de escritura, replicando el patrón ya usado en el portal.

### C3 — Carrito de "pedido detallado" sin límite de cantidad: causa raíz de datos corruptos ya presentes en producción
**Archivo:** `models/VentaModel.php:145-207` (`registrarPedidoDetallado`), `:212-281` (`editarPedidoDetallado`) · **Severidad:** crítico

`$total_und`/`$total_dinero` se calculan sumando directo los valores de `$cart`, que viene de `json_decode($_POST['carrito_json'])` — JSON controlado enteramente por el cliente, sin validar que `cantidad` sea un entero positivo ni con límite superior, antes de insertarlo en `venta.unidades_vendidas` (INT) y `venta.total_venta` (DECIMAL). Esto explica la fila real y actualmente presente en `sql/panaderia_bd.sql`: `venta.id_venta = 154` con `unidades_vendidas = 2147483647` (máximo INT32) y `total_venta = 9999999999.99` (máximo DECIMAL(12,2)) — valores que solo se alcanzan cuando MySQL trunca un INSERT fuera de rango.
**Solución:** validar `cantidad` de cada ítem del carrito (entero, > 0, límite razonable p. ej. ≤ 999) antes de sumar/guardar, igual que ya hace `models/PortalClienteModel.php::crearPedido()` (`if ($cant > 99) $cant = 99;`).

### C4 — Precio de "pedido detallado" (Ventas) confiado al cliente sin revalidar contra la base de datos
**Archivo:** `models/VentaModel.php:145-207, 212-281` · **Severidad:** crítico

El precio de cada ítem (`$item['precio']`) se toma literal del JSON del cliente y se usa para calcular `total_dinero`, sin compararlo contra `categoria_precio`/`variedad_pan`. Cualquier manipulación del `carrito_json` (herramientas de desarrollador, POST directo) permite cobrar un monto distinto al real. El propio proyecto demuestra que conoce el patrón correcto: `models/PortalClienteModel.php::crearPedido()` sí recalcula el precio real desde la base de datos (`SELECT cp.precio_unitario FROM variedad_pan vp JOIN categoria_precio cp ...`) antes de aceptar el carrito.
**Solución:** aplicar en `VentaModel` el mismo patrón de revalidación de precio que ya existe en `PortalClienteModel::crearPedido()`.

### C5 — "Pedido detallado" (Ventas) no valida stock disponible antes de vender
**Archivo:** `controllers/VentaController.php:170-254` (`guardar_pedido`, `editar_pedido`) · **Severidad:** crítico

A diferencia de "venta rápida" (que sí verifica `$und_fisicas > $stock_disponible`) y del módulo clásico `nuevaVenta()` (que llama a `validarStockVenta()`), el flujo de carrito por variedades no valida stock en ningún punto antes de registrar la venta, permitiendo sobreventa.
**Solución:** aplicar la misma validación de stock por categoría de precio que usa "venta rápida" antes de insertar.

### C6 — `getSobrantesHoy()` y `getDetalleVentas()` ignoran la mayoría de las ventas reales
**Archivo:** `models/CierreModel.php:154-173` (`getSobrantesHoy`), `models/FinanzasModel.php:183-198` (`getDetalleVentas`) · **Severidad:** crítico

Ambas consultas relacionan `venta` con `producto` vía `v.id_producto = p.id_producto`. La mayoría de las ventas registradas desde el POS actual (`views/ventas/index.php`, modos "venta rápida" y "pedido detallado") dejan `venta.id_producto` en `NULL` y solo llenan `id_categoria_precio` (confirmado en el código y en los datos reales del dump). Consecuencia: el panel **"Sin vender hoy" del Cierre del día sobreestima sistemáticamente el pan sobrante** (cuenta como no vendido lo que sí se vendió), y el **reporte PDF de Finanzas omite la mayoría de las ventas** en su detalle (`INNER JOIN producto` descarta cualquier fila con `id_producto NULL`).
**Solución:** usar `COALESCE(cp.nombre, p.nombre)` con `LEFT JOIN` sobre ambas relaciones, como ya hace correctamente `CierreModel::getVentasPorProducto()`.

### C7 — XSS almacenado vía nombre de variedad de pan, alcanza a todos los clientes del portal
**Archivo:** `views/portal/nuevo_pedido.php:379,407,444,530`; `views/ventas/index.php:1731,1808` (y su duplicado ~2093-2167); origen: `controllers/RecetaController.php:262-295` · **Severidad:** crítico

El nombre de la variedad (`variedad_pan.nombre`) se guarda sin ninguna sanitización al crearla (`RecetaController::variedades()`: `trim($_POST['nombre_variedad'])`, sin `htmlspecialchars` ni restricción de caracteres) y luego se inserta en `innerHTML` **sin escapar** en JavaScript tanto en el POS administrativo como, más grave, en `views/portal/nuevo_pedido.php`, accesible a **cualquier cliente registrado** (tiendas y aprendices). Un nombre de variedad con `<img src=x onerror=...>` ejecuta JavaScript arbitrario en el navegador de cualquiera que cargue el catálogo — pudiendo robar la sesión, extraer el token CSRF o actuar en nombre del cliente.
**Solución:** sanear `variedad_pan.nombre` al guardar y, sobre todo, escapar (`textContent` o una función tipo `escHtml()`, ya usada correctamente en `assets/js/produccion.js`) en cada punto donde se inyecta en el DOM.

### C8 — Subida de imagen de variedad sin validar contenido real
**Archivo:** `controllers/RecetaController.php:270-283, 303-314` · **Severidad:** alto

La validación de archivo solo revisa la extensión declarada por el navegador (`pathinfo(...,PATHINFO_EXTENSION)`), sin verificar el tipo MIME/contenido real (`getimagesize()`/`finfo`). Mitigado porque solo el propietario autenticado llega aquí y el nombre final es generado por el servidor, pero sigue siendo una validación de seguridad ausente.
**Solución:** validar con `getimagesize()` o `finfo_file()` antes de mover el archivo subido.

### C9 — Acciones de eliminar/desactivar disparadas por GET, sin token CSRF
**Archivo:** `controllers/InventarioController.php:78-87` (`?del=`), `controllers/CompraController.php:132-142` (`?desactivar=`), `controllers/VentaController.php:311-320` (`?del_venta=`), `controllers/GastoController.php:23-34` (`?del=`), `controllers/RecetaController.php:20-28,250-257` (`?del=`, `?del_var=`) · **Severidad:** alto

Desactivar/eliminar un registro es una acción de estado disparada por un `<a href="...?del=ID">`, sin token CSRF (agrava el hallazgo C2: incluso agregando CSRF de formulario, estas son peticiones GET, que por semántica HTTP deberían ser seguras/idempotentes). Un atacante puede forjar `<img src="…/index.php?del=5">`; si el propietario logueado la carga, el registro se desactiva sin su consentimiento. El `onclick="confirm(...)"` no protege contra esto porque solo se dispara con clics reales en la página.
**Solución:** mover estas acciones a POST con token CSRF.

### C10 — Manejo de errores: excepciones crudas expuestas al usuario final
**Archivo (representativo, patrón repetido en ~15 puntos):** `controllers/CompraController.php:59`, `controllers/CierreController.php:44`, `controllers/PedidoClienteController.php:59,207,233,246,259`, `controllers/PortalClienteController.php:1163`, `controllers/ConfiguracionController.php:43,67,89,141,232,245` · **Severidad:** medio-alto

Numerosos `catch (Exception $e)` concatenan `$e->getMessage()` directo en el mensaje mostrado al usuario (p. ej. `'Error al registrar la compra: ' . $e->getMessage()`), exponiendo potencialmente detalles internos (nombres de columnas, restricciones de BD) y dando una experiencia inconsistente frente a los puntos que sí usan mensajes genéricos.
**Solución:** loguear el detalle con `log_error($e)` (ya se hace en la mayoría de los casos) y mostrar siempre un mensaje genérico al usuario.

### C11 — Falta la extensión `mbstring` en la imagen Docker pese a uso extensivo de funciones `mb_*`
**Archivo:** `Dockerfile:4-8` · **Severidad:** alto (a verificar en el contenedor)

El código usa `mb_strtoupper`/`mb_substr`/`mb_strlen` en `controllers/ConfiguracionController.php`, `controllers/PortalClienteController.php`, `controllers/RecetaController.php`, `controllers/VentaController.php`, `views/finanzas/exportar_pdf.php`, `views/ventas/clientes.php`. El `Dockerfile` solo instala `pdo pdo_mysql zip` (`docker-php-ext-install pdo pdo_mysql zip`); la imagen base `php:8.2-apache` no trae `mbstring` compilado por defecto (a diferencia de XAMPP local, donde sí está habilitado). Riesgo de error fatal "Call to undefined function mb_strtoupper()" en el contenedor/VPS pero no en desarrollo local — el mismo patrón de bug "funciona en local, falla en producción" ya documentado para el esquema de BD.
**Solución:** verificar con `docker exec panaderia_app php -m | grep mbstring`; si falta, agregar `mbstring` a la línea `docker-php-ext-install`.

### C12 — Sin límite de intentos en login y en verificación de código/PIN de recuperación
**Archivo:** `controllers/AuthController.php::login()`, `::recuperarPin()` (paso 2); `controllers/PortalClienteController.php::login()`, `::recuperarPass()` (paso 2) · **Severidad:** alto

Ni el login administrativo ni el del portal limitan intentos fallidos (fuerza bruta de contraseña sin fricción). El código de recuperación de 6 dígitos (`codigo_recuperacion`) expira en 5-10 minutos pero tampoco tiene límite de intentos, y se compara con `!==` (no `hash_equals`) contra un valor guardado en texto plano — a diferencia del método PIN, que sí se guarda con `password_hash`/`password_verify`.
**Solución:** agregar contador de intentos fallidos con bloqueo temporal (por IP o usuario) en login, y un máximo de intentos por sesión de recuperación antes de invalidar el código.

### C13 — Credenciales de base de datos hardcodeadas en `docker-compose.yml`, committeadas al repositorio
**Archivo:** `docker-compose.yml:22-25` · **Severidad:** crítico

`MYSQL_PASSWORD: panaderia_password`, `MYSQL_ROOT_PASSWORD: panaderia_root_password`, `MYSQL_USER: panaderia_user` están en texto plano dentro de un archivo versionado en git (confirmado: el archivo está trackeado y el commit más reciente que lo modifica es `caf01f7 "Fix: normalizar despliegue Docker y bugs post-migracion a VPS"`).
**Solución:** mover a variables de entorno vía `.env` + `${VAR}` en el compose, y rotar la contraseña ya que quedó expuesta en el historial de git.

### C14 — `ConfiguracionModel::updateConfiguracion()` sin cláusula `WHERE`
**Archivo:** `models/ConfiguracionModel.php:54-57` · **Severidad:** medio-alto

`UPDATE configuracion SET ...` sin `WHERE`: actualiza todas las filas de la tabla. Funciona hoy porque se asume una única fila, pero es un patrón de riesgo clásico.
**Solución:** agregar `WHERE id_configuracion = 1` (o el identificador real) o `LIMIT 1`.

### C15 — Replay de webhook de Wompi puede deshacer una reversión manual de pago
**Archivo:** `includes/wompi.php::wompi_validar_firma_webhook()`, `models/PortalClienteModel.php::procesarWebhookWompi()` · **Severidad:** alto

La firma del webhook no valida que `timestamp` sea reciente. La idempotencia actual (`if ($pago['estado'] === $nuevo_estado) return true;`) protege contra el reenvío del mismo evento mientras el pago siga en ese estado, pero no protege si el propietario revirtió manualmente el pago (`revertirPagoDigital()` → `estado = 'VOIDED'`): un webhook antiguo válido de "aprobado" reenviado encontraría `estado='VOIDED' !== 'aprobado'`, se reprocesaría, insertaría un nuevo `pago_abono` y devolvería el pedido a `aprobado`, deshaciendo la reversión. Requiere que el atacante posea un payload de webhook válido y firmado capturado previamente — no es trivialmente explotable, pero el mecanismo es real.
**Solución:** incluir el `timestamp` del evento en la validación (rechazar eventos con más de N minutos de antigüedad) y/o registrar los `tx_id` ya procesados para rechazar duplicados exactos.

### C16 — Patrón de interpolación SQL directa (no parametrizada) repetido en el Portal
**Archivo:** `controllers/PortalClienteController.php:454,459,631,724,950,1358` · **Severidad:** alto

`"SELECT COUNT(*) FROM cliente WHERE es_aprendiz = 1 AND id_instructor = $cliente_id"` interpola la variable directo en el string SQL en vez de un parámetro preparado, repetido idéntico en 6 puntos. Hoy no es explotable porque `$cliente_id` siempre es `(int)$_SESSION['cliente_id']`, pero es el mismo anti-patrón que constituye riesgo de inyección SQL en general, y su repetición en 6 lugares aumenta la probabilidad de que una futura modificación lo vuelva explotable.
**Solución:** extraer un único método `esInstructor(int $clienteId): bool` parametrizado y reutilizarlo en los 6 puntos.

### C17 — Producción forzada con stock insuficiente calcula mal el costo
**Archivo:** `models/ProduccionModel.php:274-306` (`registrarProduccionConConsumos`) · **Severidad:** crítico

Cuando se fuerza una producción con stock insuficiente (`forzar_produccion=1`), el consumo FIFO solo genera costo por la cantidad que alcanzan los lotes reales; el remanente se descuenta igual de `insumo.stock_actual` pero **sin costo asociado**. Evidencia real: `produccion.id_produccion = 1` en el dump tiene observación *"⚠ Registrado con stock insuficiente"* y `costo_total = 0.00`. Este costo subestimado se propaga a `v_margen_productos`, Cierre del día y Finanzas, inflando la utilidad reportada.
**Solución:** al forzar producción, asignar al menos un costo estimado (último precio conocido del insumo) a la porción sin lote, o bloquear el registro y exigir una compra/ajuste primero.

### C18 — Precio de producto corrompido si llega con decimales (`str_replace` sobre input numérico)
**Archivo:** `controllers/RecetaController.php:63,124` (`precio_venta`), `controllers/GastoController.php:40,68` (`valor`) · **Severidad:** alto

`(float)str_replace(['.','$',' '], '', $_POST['precio_venta'])` elimina TODOS los puntos del valor recibido, asumiendo que el punto es siempre separador de miles. El campo es un `<input type="number">` nativo, que HTML5 siempre envía con punto como separador **decimal**. Si llega un valor con decimales (POST directo, o un cambio futuro del formulario que permita centavos), p. ej. "2500.50", el resultado es "250050" — una inflación de 100x. Con los `step` actuales normalmente no se generan decimales desde la UI, pero el bug queda latente.
**Solución:** no usar `str_replace` sobre un input numérico nativo; castear directo a float.

---

## 2. ERRORES DE LÓGICA

### L1 — Cuatro fórmulas distintas de "utilidad" en cuatro pantallas del sistema
**Archivo:** `models/AuthModel.php::getLandingStats()`, `models/CierreModel.php`/`controllers/CierreController.php`, `controllers/FinanzasController.php:44-49`, `models/TableroModel.php::getFinanzasMes()` · **Severidad:** crítico

- Portada pública y Cierre del día: `utilidad = ventas - costo_producción(consumo_lote) - gastos` (correcto, costeo FIFO real).
- Finanzas: `utilidad_bruta = ingresos - compras`, `utilidad_neta = ingresos - compras - gastos_op` (usa dinero gastado en compras, no lo realmente consumido).
- Tablero (primera pantalla tras iniciar sesión): `utilidad = ingresos - compras`, **sin restar gastos en absoluto**.

El dueño puede ver tres cifras de utilidad distintas para el mismo día en tres pantallas distintas del mismo sistema.
**Solución:** centralizar el cálculo de utilidad en una sola función reutilizada por las cuatro pantallas.

### L2 — Ajuste manual de inventario sin lote previo asigna costo cero
**Archivo:** `models/InventarioModel.php:192-208` (`registrarAjusteInventario`) · **Severidad:** alto

Cuando un ajuste aumenta el stock y no existe ningún lote activo para ese insumo, se crea un lote nuevo con `precio_unitario = 0` hardcodeado. Esto subestima `valor_inventario` y, cuando ese lote se consuma en producción (FIFO), subestima el costo de esa producción.
**Solución:** exigir un precio de referencia en el formulario de ajuste, o heredar el último `precio_unitario` conocido del insumo.

### L3 — Condición de carrera en generación de número de lote
**Archivo:** `includes/funciones.php::generarNumeroLote()`, usado desde `models/CompraModel.php::registrarCompra()` · **Severidad:** medio

Calcula el siguiente número de lote leyendo `MAX(numero_lote)` sin transacción/lock. Bajo compras concurrentes del mismo insumo en el mismo segundo, dos requests pueden calcular el mismo número; el segundo `INSERT` falla por la `UNIQUE KEY numero_lote`, revirtiendo toda la compra (y mostrando el error crudo de MySQL al usuario, ver C10).
**Solución:** usar `INSERT ... ON DUPLICATE` con reintento, o un bloqueo explícito (`SELECT ... FOR UPDATE`) sobre una tabla de secuencias.

### L4 — Distribución por categoría de precio puede desincronizar `costo_unitario` de `unidades_producidas`
**Archivo:** `models/ProduccionModel.php:308-332` · **Severidad:** medio

`costo_unitario` se calcula con el `$unidades` original (`costo_total / $unidades`), pero si la distribución por categoría de precio (`dist_precios`) suma un total distinto, `unidades_producidas` se sobreescribe con ese nuevo total **sin recalcular** `costo_unitario`. El JS (`checkDistTotal()`) exige que la suma coincida antes de habilitar el botón, pero el servidor nunca revalida esa igualdad — un POST directo puede dejar `costo_total ≠ costo_unitario × unidades_producidas` guardado.
**Solución:** revalidar en servidor que `array_sum($dist_precios) === $unidades`, o recalcular `costo_unitario` con el total real antes de guardar.

### L5 — Cupo semanal y abonos de pago verificados sin bloqueo de fila (condición de carrera)
**Archivo:** `models/PortalClienteModel.php::crearPedido()` (líneas 733-764), `models/PedidoClienteModel.php::registrarAbonoPago()` (líneas 219-268) · **Severidad:** medio

Ambas funciones leen un total acumulado (consumo semanal / abonos) y lo comparan contra un límite dentro de una transacción, pero sin `SELECT ... FOR UPDATE`. Dos pedidos o dos abonos casi simultáneos sobre el mismo recurso pueden leer el mismo total "antes" del commit del otro, permitiendo exceder el cupo semanal o dejar `pago_pedido.monto` desincronizado de la suma real de `pago_abono`.
**Solución:** usar `SELECT ... FOR UPDATE` sobre las filas relevantes antes de calcular el total.

### L6 — Sin validación de tope superior en cantidades/tandas en varios formularios administrativos
**Archivo:** `controllers/ProduccionController.php:102` (`num_tandas`, UI limita a 5 pero servidor no), `models/CompraModel.php::registrarCompra()` (sin tope en `cantidad`/`precio_bulto`/`num_bultos`) · **Severidad:** medio

El servidor solo garantiza mínimos (`max(1, ...)`), no máximos, dejando el control de magnitud enteramente al JavaScript. Mismo patrón que causó el dato corrupto de C3, aplicado aquí a Producción y Compras.
**Solución:** agregar validación de rango razonable en el Controller/Model antes de insertar, en línea con lo ya implementado en `PortalClienteModel::crearPedido()`.

### L7 — Duplicado de ingrediente en receta no se valida en servidor
**Archivo:** `controllers/RecetaController.php::editarReceta()` (líneas 169-214) · **Severidad:** medio

No valida que `id_insumo[]` no se repita en el mismo envío. La UI lo evita (el modal deshabilita insumos ya usados), pero un POST directo con el mismo insumo repetido crea dos filas `receta_ingrediente` para el mismo insumo, duplicando su descuento de stock/costo en cada producción futura.
**Solución:** deduplicar `id_insumo` en servidor antes de insertar, o agregar una restricción `UNIQUE(id_receta, id_insumo)`.

### L8 — `gasto.id_cierre_dia` y `venta.id_cierre_dia` nunca se completan
**Archivo:** `models/GastoModel.php::registrarGasto()`, `models/VentaModel.php` (todas las funciones de registro), `models/CierreModel.php::guardarCierre()` · **Severidad:** medio

Ambas columnas existen para vincular una venta/gasto con el cierre del día en que se consolidó, pero ningún INSERT/UPDATE revisado les asigna valor — quedan permanentemente `NULL`. Parece una funcionalidad de trazabilidad planeada pero nunca conectada.
**Solución:** completar el vínculo al confirmar el cierre, o eliminar las columnas si ya no son necesarias.

### L9 — Fallback de "Tienda ADSO" por nombre de texto libre
**Archivo:** `controllers/PortalClienteController.php:803-810` · **Severidad:** medio

Cuando un aprendiz sin instructor asignado pide "para ADSO", el destino por defecto se busca con `WHERE nombre LIKE '%Tienda ADSO%' AND tipo='tienda'`, dependiente de una convención de texto libre en `cliente.nombre`. Si esa tienda es renombrada o eliminada, el pedido queda enrutado silenciosamente al propio aprendiz como destino (fallback implícito), sin aviso.
**Solución:** usar un identificador de configuración explícito (p. ej. una fila de `configuracion`) en vez de buscar por nombre.

### L10 — Verificación de duplicado de nombre solo al crear, no al editar
**Archivo:** `controllers/VentaController.php::clientes()` (líneas 378-400) · **Severidad:** bajo-medio

`guardarCliente` valida nombre duplicado solo en la rama de creación; al editar una tienda existente no se revalida, permitiendo que dos tiendas activas terminen con el mismo nombre (la tabla `cliente` no tiene `UNIQUE KEY` en `nombre`, a diferencia de `insumo`/`proveedor`/`producto`).
**Solución:** aplicar la misma verificación de duplicado también en la rama de edición.

### L11 — `getConfiguracion()` asume que la tabla `configuracion` nunca está vacía
**Archivo:** `includes/funciones.php:73-81`, `models/ConfiguracionModel.php:47-49` · **Severidad:** bajo

`SELECT * FROM configuracion LIMIT 1` sin fallback; si la tabla está vacía (instalación nueva sin seed), devuelve `[]` y cualquier acceso posterior a `$config['clave']` genera un "Undefined array key" (warning, no fatal, pero indica falta de guarda).
**Solución:** verificar existencia de fila al inicializar la aplicación o usar `??` con valores por defecto en cada acceso.

### L12 — Bonificación/ñapa: fórmulas correctas pero mantenidas por separado en 3 implementaciones
**Archivo:** `controllers/VentaController.php` (venta rápida y editar_venta), `models/PortalClienteModel.php::crearPedido()` · **Severidad:** bajo (verificado correcto, riesgo de divergencia futura)

Las fórmulas (`$1.000/$5.000` para tiendas, `$500/$5.000` para mostrador/ñapa) están implementadas correctamente y de forma consistente entre sí, pero copiadas en al menos 3 lugares distintos en vez de una función compartida — cualquier cambio de regla de negocio debe aplicarse manualmente en cada copia.
**Solución:** extraer a una función/método común, por ejemplo ampliando `helpers/PedidoHelper.php`.

### L13 — `crearProducto`/`editarProducto` de Recetas no restringen "Unidad de producción" a las mismas opciones
**Archivo:** `views/recetas/crear_producto.php:112` vs `views/recetas/editar_producto.php:125` · **Severidad:** medio

Al **crear** un producto, el selector de "Unidad de producción" solo ofrece `unidad` (`foreach(['unidad'] as $u)`); al **editar**, ofrece `lata`, `carro` y `unidad`. La columna `producto.unidad_produccion` tiene como valor por defecto en el esquema `'carro'`, pero no hay forma de crear un producto nuevo con ese valor (o `lata`) desde el formulario de creación.
**Solución:** unificar las opciones del `<select>` en ambos formularios.

### L14 — Validación de nombre de insumo inconsistente entre las dos rutas de creación/edición
**Archivo:** `controllers/InventarioController.php:36` (`index()`/modal) vs `:138` (`crearInsumo()`, ruta clásica) · **Severidad:** medio-alto

El modal inline de `index()` rechaza nombres con dígitos (`preg_match('/[0-9]/', $nombre)`) y lo refuerza en el HTML; la ruta clásica `crearInsumo()` no tiene esa validación en ningún lado. Se puede crear un insumo con números en el nombre por una ruta y no por la otra (ver también I3 en Inconsistencias sobre la duplicación de estas dos rutas).
**Solución:** unificar la validación en el modelo o en un método compartido del controlador.

### L15 — Recuperación de contraseña administrativa y de portal: código guardado en texto plano
**Archivo:** `models/AuthModel.php:34-37`, `models/PortalClienteModel.php::registrarCodigoRecuperacion()` · **Severidad:** bajo-medio

El código de recuperación por correo (`codigo_recuperacion`) se guarda en texto plano y se compara con `!==` (no `hash_equals`), a diferencia del método PIN que sí guarda un hash bcrypt y compara con `password_verify`. Mitigado por la expiración de 5-10 minutos y la limpieza tras uso, pero es una inconsistencia de tratamiento de secretos dentro del mismo flujo.
**Solución:** hashear también el código de recuperación por correo antes de guardarlo.

### L16 — Registro de venta clásica (`nueva_venta.php`) y "pedido detallado" mantienen reglas de stock/bonificación en paralelo
**Archivo:** `controllers/VentaController.php::nuevaVenta()` vs `::index()` (venta rápida / pedido detallado) · **Severidad:** bajo

Tres flujos distintos para registrar una venta (venta rápida por categoría, pedido detallado por variedades, módulo clásico por producto), cada uno con su propia validación de stock implementada por separado — ya se manifestó como bug real en C5. No se corrige aquí de nuevo, se deja como nota de causa raíz de diseño.

---

## 3. FRONTEND

### F1 — Formularios administrativos sin protección CSRF visible
**Archivo:** todas las vistas de `views/inventario`, `views/compras`, `views/produccion`, `views/recetas`, `views/ventas`, `views/gastos`, `views/finanzas`, `views/cierre`, `views/configuracion`, `views/pedidos_clientes` · **Severidad:** alto (consecuencia directa de C2)

Ningún formulario POST de estas vistas incluye el campo oculto `csrf_token`, confirmando en el frontend el hallazgo C2.
**Solución:** agregar `<input type="hidden" name="csrf_token" value="<?= generar_token_csrf() ?>">` a cada formulario, junto con la validación server-side.

### F2 — Rutas "clásicas" de insumo activas pero sin ningún enlace en la interfaz
**Archivo:** `modules/inventario/crear_insumo.php`, `editar_insumo.php`, `desactivar_insumo.php` · **Severidad:** medio

Verificado por búsqueda en todo el repositorio: ningún `<a href>` ni `<form action>` en ninguna vista actual apunta a estos tres archivos — solo son alcanzables escribiendo la URL manualmente. Siguen totalmente funcionales (y con reglas de validación más débiles que el flujo modal vigente, ver L14), pero son código "fantasma" desde el punto de vista de la UI.
**Solución:** eliminarlas si el flujo modal las reemplaza por completo, o enlazarlas si se necesitan como alternativa.

### F3 — Mensajes de error (`$msg_err`/`$error`) impresos sin `htmlspecialchars` de forma inconsistente
**Archivo:** `views/auth/recuperar_pin.php:90`, `views/inventario/*.php`, `views/compras/*.php`, `views/ventas/*.php`, `views/gastos/index.php`, `views/cierre/index.php` (imprimen `<?= $msg_err ?>` directo) vs `views/auth/login.php:289`, `views/configuracion/pin.php:44` (usan `htmlspecialchars($error)`) · **Severidad:** medio

Hoy no es explotable porque el contenido de esos mensajes proviene siempre de strings fijos del controlador (algunos con HTML intencional como `<br>`), pero la inconsistencia es un riesgo latente: si en el futuro se interpola un valor de usuario dentro de `$msg_err` en una de las vistas que no escapan, se introduce XSS reflejado sin que sea obvio en el diff.
**Solución:** estandarizar: nunca imprimir `$msg_err` crudo; si se necesita HTML (como `<br>`), usar un array de líneas ya sanitizadas en vez de concatenar HTML libre en el controlador.

### F4 — Formulario de compra 100% dependiente de JavaScript, sin degradación
**Archivo:** `views/compras/index.php:303-408` · **Severidad:** bajo

Los campos visibles `vis-bultos`/`vis-cant-bolsa` no tienen atributo `name` (solo los hidden `inp-cantidad`/`inp-num-bultos`, poblados por JS, lo tienen). El hidden `cantidad` no tiene `value` por defecto. Si JavaScript falla o está deshabilitado, el formulario se puede enviar pero `cantidad` llega vacía y la validación de servidor (`$cantidad <= 0`) lo rechaza con un mensaje genérico que no explica la causa real.
**Solución:** agregar `name` a los campos visibles como respaldo, o al menos un mensaje de error específico para este caso.

### F5 — Botón "Manual de Usuario" puede apuntar a un PDF ausente en despliegues nuevos
**Archivo:** `views/auth/landing.php:391`, `views/auth/login.php:407`, `views/layouts/header.php:97` · **Severidad:** medio

Los PDFs (`assets/docs/Manual_BreadControl*.pdf`) están excluidos por `.gitignore` (`assets/docs/*.pdf`), por lo que no se despliegan automáticamente a un clon nuevo del repositorio o a un VPS recién provisionado; el botón devolvería 404 hasta que alguien suba el archivo manualmente.
**Solución:** quitar la exclusión de `.gitignore` para los PDFs finales del manual, o documentar el paso manual en el proceso de despliegue.

### F6 — Selector de "Unidad de producción" con opciones distintas entre crear y editar producto
**Archivo:** `views/recetas/crear_producto.php:110-115` vs `views/recetas/editar_producto.php:123-128` · **Severidad:** medio (duplica L13, ángulo de frontend)

Ver detalle de la causa en L13. Desde el frontend, esto se percibe como "no puedo crear un producto tipo carro/lata, solo puedo editarlo después".

### F7 — Input con comilla doble sobrante no cierra correctamente
**Archivo:** `views/recetas/editar_receta.php:246` · **Severidad:** bajo

`value="<?= ... ?>""></td>` tiene una comilla doble extra entre el cierre del atributo `value` y el `>` del tag. Los navegadores lo toleran (atributo inválido ignorado), pero es un resto de copiar y pegar que conviene limpiar.

### F8 — `precio_venta`/`cantidad_por_tanda` sin `htmlspecialchars` consistente al repoblar el formulario
**Archivo:** `views/recetas/editar_producto.php:133,141` · **Severidad:** bajo

Se imprimen como `<?= $producto['cantidad_por_tanda'] ?? 0 ?>` sin `htmlspecialchars`. No es explotable (son columnas numéricas), pero rompe la convención del resto del archivo, que sí escapa `nombre`.

### F9 — Saludo personalizado en login administrativo nunca muestra el nombre
**Archivo:** `controllers/AuthController.php:63`, `views/auth/login.php:335` · **Severidad:** bajo

`$nombre_saludo` se inicializa en `''` y nunca se asigna a un valor real en el controlador; el saludo JS ("¡Buenos días, NOMBRE!") nunca completa el nombre — degrada con gracia (muestra el saludo sin nombre) pero es una función visiblemente incompleta.
**Solución:** completar la asignación de `$nombre_saludo` (p. ej. tras un login fallido con usuario válido, o eliminar la variable si ya no aplica).

---

## 4. INCONSISTENCIAS

### I1 — `limpiar()` (htmlspecialchars de entrada) aplicado de forma inconsistente antes de guardar en BD
**Archivo:** `controllers/InventarioController.php:25` (`index()`, guarda texto crudo) vs `:138,184` (`crearInsumo()`/`editarInsumo()`, aplican `limpiar()` antes de guardar) · **Severidad:** alto

El mismo campo (`insumo.nombre`) queda almacenado en texto plano si se creó desde el modal, o con entidades HTML (`Sal &amp; Pimienta`) si se creó desde el formulario clásico — mismo dato, dos representaciones según la ruta usada, con riesgo de doble-escape si alguna vista vuelve a aplicar `htmlspecialchars()`.
**Solución:** nunca aplicar `htmlspecialchars` antes de guardar; escapar solo al momento de renderizar.

### I2 — `addslashes()` sin `htmlspecialchars()` para insertar datos en atributos `onclick`
**Archivo:** `views/recetas/variedades.php:126` (`addslashes($v['nombre'])` sin escapar HTML) · **Severidad:** alto (raíz de C7)

Contraste con `views/gastos/index.php:333`, que sí combina correctamente `htmlspecialchars(addslashes($g['descripcion']), ENT_QUOTES)` para el mismo propósito (insertar un valor de BD dentro de un atributo `onclick` que a su vez contiene una cadena JS). Confirma que el problema es inconsistencia de convención, no desconocimiento del patrón correcto.
**Solución:** usar siempre `htmlspecialchars(..., ENT_QUOTES)` además de (o en vez de) `addslashes()`, o pasar los datos vía atributos `data-*` en vez de construir la llamada JS inline.

### I3 — Dos implementaciones paralelas para crear/editar un insumo
**Archivo:** `views/inventario/index.php` (modal inline) vs `modules/inventario/crear_insumo.php` + `editar_insumo.php` (páginas clásicas) · **Severidad:** medio

Funcionalmente redundantes, con reglas de validación divergentes (ver L14) y con la ruta clásica ya huérfana de la UI (ver F2). Mantener dos implementaciones del mismo caso de uso duplica el trabajo de cualquier corrección futura.
**Solución:** eliminar una de las dos rutas (recomendado: retirar la clásica, ya no enlazada) y consolidar la validación.

### I4 — Columnas de `cliente`/`pedido_cliente` sin ningún rastro de creación en `sql/`
**Archivo:** `cliente.foto_url`, `cliente.es_aprendiz`, `cliente.notas`, `cliente.es_beneficiaria`, `pedido_cliente.mensaje_propietario`, `pedido_cliente.id_tienda_destino` · **Severidad:** alto (ver también C1)

A diferencia de `usuario`, `contrasena_hash`, `google_id`, `cupo_semanal`, `id_instructor` (que sí tienen su script de migración dedicado en `sql/`), estas seis columnas se usan activamente en el código (`models/ConfiguracionModel.php`, `models/VentaModel.php`, `models/PedidoClienteModel.php`, `models/PortalClienteModel.php`) sin que exista ningún `ALTER TABLE` correspondiente en el repositorio — evidencia de que se agregaron directo en la base de datos (probablemente vía phpMyAdmin) sin dejar rastro versionado.
**Solución:** documentar/migrar todas las columnas reales del esquema en vez de solo las que tuvieron script; ver solución consolidada en C1.

### I5 — Patrón de mensajes de error crudo vs. genérico (`$e->getMessage()`)
**Archivo:** ver lista completa en C10 · **Severidad:** medio

Mismo hallazgo que C10, incluido aquí también como muestra de mezcla de convenciones dentro del mismo tipo de bloque `catch` a lo largo de la aplicación.

### I6 — Política de contraseña inconsistente entre módulos
**Archivo:** `controllers/ConfiguracionController.php::perfil()` (admin, mínimo 6 al cambiar clave), `controllers/PortalClienteController.php::registro()` (mínimo 4), `::perfil()` (mínimo 4 al cambiar), `::recuperarPass()` (mínimo 6 al restablecer) · **Severidad:** alto

Un cliente del portal puede registrarse o cambiar su clave a solo 4 caracteres, pero si la olvida y la recupera, el propio sistema le exige mínimo 6 — inconsistencia interna, y una política débil en general para cuentas con acceso a pedidos y pagos.
**Solución:** unificar el mínimo (6+) en registro, cambio y recuperación, tanto en el portal como en el back-office.

### I7 — Archivo huérfano: `modules/produccion/registrar.php`
**Archivo:** `modules/produccion/registrar.php` · **Severidad:** bajo

Contenido completo: comentario *"Este archivo fue reemplazado por nueva_produccion.php"* + un `redirigir()`. Confirmado por búsqueda en todo el repositorio: ningún otro archivo lo referencia.
**Solución:** eliminarlo si no hay marcadores/enlaces externos apuntando a él.

### I8 — `portal/pagar_instructor.php` no sigue el patrón de enrutamiento del resto de la aplicación
**Archivo:** `portal/pagar_instructor.php` · **Severidad:** bajo

Todo el archivo es `require sesion.php; header('Location: dashboard.php'); exit;` — ninguna otra ruta de `portal/*.php` deja de instanciar `PortalClienteController`. Parece una función retirada sin limpiar.
**Solución:** verificar si algo enlaza a esta ruta; si no, eliminarla.

### I9 — `portal/logout.php` implementa lógica directa en vez de delegar al controlador
**Archivo:** `portal/logout.php` · **Severidad:** bajo

Hace `session_unset()`/`session_destroy()` directo en el archivo de rutas, rompiendo el patrón "front controller delgado + lógica en el Controller" que sigue el resto de la aplicación, incluido el logout administrativo (`AuthController::logout()`).
**Solución:** mover la lógica a un método `PortalClienteController::logout()` para mantener el patrón uniforme.

### I10 — `str_replace` para "limpiar" precios: mismo anti-patrón en 2 módulos
**Archivo:** ver C18 (Recetas y Gastos) · **Severidad:** alto

Mismo hallazgo que C18, incluido aquí como evidencia de que el anti-patrón se copió entre módulos en vez de existir una única función de parseo de moneda reutilizada.
**Solución:** crear una única función `parsearPesos(string $valor): float` correcta y usarla en todos los formularios que reciben montos.

### I11 — Vista de Ventas: lógica del carrito duplicada entre "nuevo pedido" y "editar pedido"
**Archivo:** `views/ventas/index.php` (funciones `loadCatalog`/`tapProduct`/`addToCartFromCard`/`renderCart` vs sus equivalentes `ep*`) · **Severidad:** medio

La lógica completa del carrito/catálogo (~200 líneas) está duplicada casi al carácter entre el flujo de creación y el modal de edición, duplicando también el bug de XSS de C7. Cualquier corrección debe aplicarse dos veces.
**Solución:** extraer una función parametrizable compartida.

### I12 — Tres implementaciones independientes de "es instructor" en el mismo controlador
**Archivo:** `controllers/PortalClienteController.php` (dashboard×2, detallePedido, nuevoPedido, perfil, exportarCarteraInstructor) · **Severidad:** medio (ver también C16)

La misma comprobación (¿esta tienda tiene aprendices a cargo?) se recalcula de forma casi idéntica en 6 lugares distintos del mismo archivo en vez de un método reutilizable, agravando el riesgo de inyección SQL de C16.

### I13 — Documentación (`README.md`, `srs_ieee830_breadcontrol.txt`) no fue verificada contra el código, pero convive con un esquema desactualizado
**Archivo:** `sql/panaderia_bd.sql` vs `sql/crear_pedidos.sql` + migraciones sueltas · **Severidad:** medio

No hay un único lugar que describa el esquema real y completo de la base de datos; un desarrollador nuevo que solo mire `panaderia_bd.sql` (el archivo con nombre más "oficial") tendría una imagen incompleta y desactualizada del sistema (ver C1, I4).

### I14 — Convención mixta Bootstrap vs. CSS custom por módulo
**Archivo:** `views/inventario/crear_insumo.php`/`editar_insumo.php` (clases `form-control`, `card`, `mb-3` de Bootstrap) vs. el resto de `views/inventario/*.php` y prácticamente todas las demás vistas (sistema de diseño custom con variables CSS `--c1`..`--c5`, clases `fl`, `btn-guardar`, etc.) · **Severidad:** bajo

Las dos vistas "clásicas" de insumo (ya señaladas como huérfanas en F2/I3) son las únicas que usan Bootstrap puro; el resto del sistema usa un design system propio. Consistente con que son remanentes de una versión anterior de la interfaz.

### I15 — `docker-compose.yml` usa `version: '3.8'`, sintaxis obsoleta en Docker Compose v2+
**Archivo:** `docker-compose.yml:2` · **Severidad:** bajo (cosmético)

Genera advertencia, no error, en versiones recientes de Docker Compose.
**Solución:** eliminar la clave `version` (ya no es necesaria en Compose v2+).

### I16 — Cobertura de pruebas concentrada casi enteramente en el Portal
**Archivo:** `tests/test_portal_rules.php` (370 líneas, muy completo: crédito, límite de 48h, cupo semanal, CSRF, horarios, aprobación en lote) vs `tests/test_batch_generation.php`/`test_sales_validation.php` (funciones puntuales de `includes/funciones.php`) · **Severidad:** bajo (observación de calidad, no bug)

No existe ninguna prueba para `VentaModel`, `ProduccionModel`, `CompraModel` ni `CierreModel`/`FinanzasModel` — precisamente donde se encontraron los hallazgos C3, C4, C6, C17 y L1. La única suite de pruebas robusta es la que exactamente NO tiene los bugs más graves encontrados en esta auditoría, lo que sugiere que las pruebas sí ayudaron a prevenir errores donde existen.
**Solución:** extender pruebas automatizadas a `VentaModel::registrarPedidoDetallado`, a las funciones de utilidad de `CierreModel`/`FinanzasModel`/`TableroModel` (para atrapar L1 automáticamente) y a `ProduccionModel::registrarProduccionConConsumos`.

### I17 — Doble mecanismo de configuración de pagos (Nequi manual vs. Wompi API) sin documentación cruzada
**Archivo:** `controllers/ConfiguracionController.php::pagos()` + `models/ConfiguracionModel.php` (link estático de Nequi, confirmación manual) vs `includes/wompi.php` + `portal/wompi_webhook.php` (integración API completa con firma y webhook) · **Severidad:** bajo (aclaración, no bug)

Coexisten dos sistemas de pago: uno manual (el propietario pega un link fijo de Nequi Negocios y confirma manualmente al recibir el dinero) y uno automático (Wompi API con webhook). Ambos escriben en las mismas tablas `pago_pedido`/`pago_abono`, lo cual es correcto, pero no hay ningún comentario en el código que aclare por qué coexisten dos mecanismos — dificulta el mantenimiento futuro.
**Solución:** documentar en el código (un comentario breve en `wompi.php` y en `ConfiguracionController::pagos()`) la relación entre ambos flujos.

### I18 — Mezcla de convenciones de nomenclatura en parámetros AJAX
**Archivo:** `assets/js/produccion.js:115` (`&unidades=' + tandas`, el parámetro se llama "unidades" pero transporta tandas) · **Severidad:** bajo

No es un bug funcional (el valor se interpreta consistentemente como tandas en `ProduccionModel::calcularLotesFIFO()`), pero el nombre del parámetro no coincide con su semántica real, lo cual es una trampa para quien mantenga este código a futuro.
**Solución:** renombrar el parámetro a `tandas`.

### I19 — Variable muerta en `crearPedido()` del portal
**Archivo:** `models/PortalClienteModel.php:799` (`$id_cli_real`) · **Severidad:** bajo

Se lee pero nunca se usa; la actualización posterior usa el parámetro de la función, no esta variable. Resto de un refactor.

---

## Notas finales

- **No se encontró** un caso claro de SQL injection directamente explotable: el proyecto usa `PDO::prepare()`/`execute()` de forma consistente en más del 95% de las consultas. Los puntos señalados (C16) son anti-patrones de interpolación directa que **hoy** no son explotables porque la variable interpolada siempre es un entero controlado por el servidor, pero se documentan porque es el tipo de código que se vuelve vulnerable con un cambio aparentemente inocuo.
- El módulo con mejores prácticas de seguridad de todo el proyecto es el **backend del Portal de Clientes** (`PortalClienteModel`/`PortalClienteController`): revalida precios y cantidades del lado servidor, filtra consistentemente por propiedad del recurso (`id_cliente`/`id_creador`) para evitar IDOR, valida firma de webhook antes de confiar en el payload, y es el único lugar del sistema que usa CSRF. Vale la pena usarlo como referencia al corregir los módulos administrativos (C2, C4, C5, L6).
