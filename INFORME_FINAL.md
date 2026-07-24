# Informe final — Correcciones del flujo pedido/pago

**Rama:** `fix/flujo-pedido-pago` (desde `master`).
**Fecha:** 2026-07-23.
**Dominio de producción:** `https://breadcontrol.manuelcardenas.online`.
**Alcance:** flujo aprendiz → instructor → pago (portal + back-office) + registro de aprendices por código del instructor.
**Backup previo:** `backups/panaderia_bd_pre_fix.sql` (dump de la BD local antes de los ALTER; no se versiona).

Todos los cambios se validaron con `php -l` (sin errores), grep de referencias muertas (0), y suites de pruebas contra BD aisladas (flujo de pago 18/18, registro de aprendices 27/27, restricción de instructor 8/8), más verificación de que los reportes financieros dan el mismo total antes y después (neta = $3.213.465,05 idéntica en `master` y en la rama).

---

## 0. Resultado del despliegue en producción (2026-07-23)

La rama **ya está desplegada en producción** (Dokploy apuntando a `fix/flujo-pedido-pago`) y el flujo fue probado de punta a punta con éxito.

**Migraciones aplicadas — las 6 corrieron sin errores en la BD de producción**, en orden:
1. `2026-07-23_01_normalizar_estado_pago_pedido.sql`
2. `2026-07-23_02_foreign_keys_flujo_pedido_pago.sql`
3. `2026-07-23_03_default_estado_pago_no_aplica.sql`
4. `2026-07-23_04_codigo_aprendiz.sql`
5. `2026-07-23_05_id_cliente_adso.sql`
6. `2026-07-23_06_aprobado_instructor_default_0.sql`

**Configuración que faltaba en producción y se cargó a mano** (no venía en el despliegue):
- `configuracion.nequi_link_pago` — el enlace de pago de Nequi Negocios.
- `configuracion.wompi_habilitado = 1` — el interruptor del pago digital.

Estas **dos son requisito para que el pago digital funcione**. Sin ellas, el portal muestra correctamente el aviso *"La panadería aún no ha habilitado los pagos digitales"* (ver `pagarConsolidado`: `$pago_configurado = !empty(nequi_link_pago) && !empty(wompi_habilitado)`), pero **el flujo no avanza**: no se puede generar el pago ni mostrar el enlace de Nequi. `id_cliente_adso = 45` quedó cargado por la migración 05.

**Prueba de humo — los 4 pasos verificados en producción:**
1. El aprendiz arma su pedido → se enruta a la cuenta **Tienda ADSO (id 45)** por id (`configuracion.id_cliente_adso`), no por nombre.
2. El instructor aprueba el pedido pendiente.
3. El instructor paga → queda **traza en `pago_pedido`** (registro del pago con auditoría del pagador).
4. **Prueba negativa OK:** el aprendiz **no** puede pagar el pedido dirigido al instructor, ni siquiera entrando por URL directa (bloqueado server-side por la regla de pago por `id_cliente`).

---

## 1. Qué se corrigió, por hallazgo y commit

| Hallazgo | Descripción de la corrección | Commit |
|---|---|---|
| **D1 (Wompi)** | Retirada la integración Wompi (API + webhook): eliminados `includes/wompi.php`, `portal/wompi_webhook.php`, el método `wompiWebhook()` y `procesarWebhookWompi()`. Eliminada la ruta huérfana `portal/pagar_instructor.php` (G1). Limpiadas referencias a columnas Wompi inexistentes en el recibo PDF. Con esto **C2/C15 (replay de webhook) desaparecen de raíz**. | `23f5e3e` |
| **A1 / D2 / D5** | `getPedidosPendientesPago` filtra **exclusivamente por `id_cliente`** (nunca por `id_creador`); exige `aprobado_instructor=1` solo cuando `id_cliente != id_creador`. La vista `detalle_pedido.php` renderiza los botones de pago y el link de Nequi solo si `$puede_pagar` (id_cliente == sesión). `pagarConsolidado` valida server-side que todos los pedidos del lote facturen al usuario (defensa en profundidad). | `b8a05e7` |
| **B3** | Enum canónico `EstadoPagoPedido` (MAYÚSCULAS) en `includes/estados_pago.php`. Corregido el `WHERE` de `confirmarCobroTienda` (antes comparaba `'pendiente'` y nunca casaba con `'PENDING'`). Centralizados todos los literales de `pago_pedido.estado`. Migración de normalización de datos históricos. | `0deeb8a` |
| **D3** | El botón "Ir a Nequi" del dashboard es ahora un `<form method=post>` con CSRF que registra el pago en `pago_pedido` **antes** de mostrar el enlace. POST-Redirect-GET, idempotencia (reutiliza el pago PENDING existente), link siempre desde `configuracion.nequi_link_pago`, y auditoría del pagador en la nota. | `1d4c55a` |
| **E1** | 6 foreign keys en `pedido_cliente / *_detalle / pago_pedido / pago_abono` con reglas `ON DELETE` (CASCADE/SET NULL/RESTRICT). Migración con limpieza previa de huérfanos. Corregida la exclusión de `sql/migraciones/` en `.gitignore`. | `a00d810` |
| **C3 / D1 (concurrencia)** | `registrarAbonoPago` rechaza un abono que exceda el saldo pendiente y bloquea la fila del pago (`FOR UPDATE`). `crearPedido` bloquea la fila del aprendiz al validar el cupo semanal. | `1a510e2` |
| **B2 / E3** | Eliminado el estado fantasma `'cancelado'` de las consultas que lo excluían. `estado_pago` inicial pasa a `'no_aplica'` (default de esquema + INSERT explícito). | `86831b4` |
| **F3 / C5 / A2-C16** | `getConfiguracion()` maneja el `false` de `fetch()`. POST-Redirect-GET en el back-office (`verPedido`). Detección de instructor extraída a `contarAprendices()` con prepared statement (elimina 6 interpolaciones directas de `$cliente_id`). | `e59e347` |
| **E2 / C1 / I15** | `sql/init/02_extensiones_flujo.sql` + `docker-compose.yml` (monta base + extensiones en initdb) hacen que un despliegue Docker fresco levante el esquema completo. Eliminada la clave `version` obsoleta. README documenta el orden. | `4e4a5e9` |

---

## 2. Qué NO se pudo corregir (o se dejó fuera a propósito)

- **D6 — estado `entregado`:** fuera de alcance por decisión explícita. El ciclo termina en `confirmado + estado_pago='aprobado'`.
- **Ingresos de pedidos del portal en los reportes financieros:** los reportes (Finanzas/Cierre/Portada) leen de la tabla `venta`, no de `pedido_cliente`, así que un pago consolidado del portal **no aparece** como ingreso en la utilidad. Es una limitación preexistente ya documentada en `LIMITACIONES_Y_TRABAJO_FUTURO.md`; corregirla implica unificar dos fuentes de ingreso y quedaba fuera del alcance de esta sesión (habría cambiado los reportes, que se pidió mantener intactos).
- **Selección parcial de pedidos en el modal del instructor:** el pago consolida **todos** los pedidos pendientes del pagador. Permitir pagar solo un subconjunto exigiría extender `pagarConsolidado` para aceptar una lista de IDs (mayor superficie); se difirió y se aclaró en el texto de la UI.

---

## 3. Decisiones tomadas ante ambigüedades (opción más conservadora)

1. **Columnas `wompi_*` de la BD conservadas.** Los links de Nequi Negocios se alojan en `checkout.wompi.co`, así que `pago_pedido.wompi_link_url/id` almacenan legítimamente el link estático de Nequi, y `configuracion.wompi_habilitado/confirmar_auto` son el toggle de pago digital. Renombrarlas en todo el esquema era mayor superficie y riesgo sin beneficio. Se retiró solo el **código** de la integración (API + webhook).
2. **`habilitar_pago` (paso 8 de la orden) es una acción del back-office, no del portal.** El guard por `id_cliente` aplica a acciones del portal (`pagarConsolidado`); `habilitar_pago` vive en `PedidoClienteController` bajo `requerirPropietario`, donde el propietario está autorizado para todos los pedidos. Se aplicó el guard donde corresponde (portal) y se dejó intacta la autorización del propietario.
3. **Filas `estado_pago='pendiente'` existentes no se reescriben.** Son equivalentes a `'no_aplica'` para todas las consultas del flujo; reescribir aumentaba el riesgo sin aportar. El script deja la instrucción opcional comentada.
4. **`pedido_cliente.id_pago_activo` sin FK.** Añadirla creaba una dependencia circular con `pago_pedido.id_pedido`; se limpia por lógica en la app (como ya se hacía).
5. **`.gitignore` tenía `*.sql`**, que descartaba silenciosamente todas las migraciones. Se añadieron excepciones `!sql/migraciones/*.sql` y `!sql/init/*.sql`.
6. **Cambios de inventario preexistentes (ajenos):** al empezar había cambios sin commitear de inventario (`InventarioController`, `InventarioModel`, `inventario.css`, `ajuste.php`, `reconciliar_inventario.php`). Se guardaron en `git stash` (`stash@{0}`) y **no se tocaron**; siguen disponibles para retomarlos.

---

## 4. Archivos SQL y orden de ejecución

**Base de datos NUEVA / vacía** (incluye Docker automático):
1. `sql/panaderia_bd.sql` (dump base)
2. `sql/init/02_extensiones_flujo.sql` (columnas del portal + tablas del flujo + FKs)

En Docker esto es automático: `docker-compose.yml` los monta como `01_base.sql` y `02_extensiones.sql` en `docker-entrypoint-initdb.d` y MySQL los ejecuta en orden al crear el contenedor con volumen vacío.

**Base de datos EXISTENTE** (VPS, local ya migrada) — ejecutar en orden, con MySQL Workbench o CLI:
1. `sql/migraciones/2026-07-23_01_normalizar_estado_pago_pedido.sql`
2. `sql/migraciones/2026-07-23_02_foreign_keys_flujo_pedido_pago.sql`
3. `sql/migraciones/2026-07-23_03_default_estado_pago_no_aplica.sql`
4. `sql/migraciones/2026-07-23_04_codigo_aprendiz.sql`
5. `sql/migraciones/2026-07-23_05_id_cliente_adso.sql` (fija `id_cliente_adso = 45`)
6. `sql/migraciones/2026-07-23_06_aprobado_instructor_default_0.sql`

Todas llevan `SET SQL_SAFE_UPDATES = 0/1` donde aplica y son idempotentes o de una sola ejecución (la de FKs trae su bloque de rollback comentado). **Las 6 ya fueron aplicadas y verificadas en local y en producción.**

---

## 5. Despliegue en producción — EJECUTADO ✅

Ver la sección **0** para el detalle. Resumen de lo realizado (Dokploy apuntando a
`fix/flujo-pedido-pago`):

1. ✅ Despliegue de la rama (el código se monta como volumen; PHP/CSS aplican sin rebuild).
2. ✅ Las **6 migraciones de `sql/migraciones/`** aplicadas en orden, sin errores. NO se usó
   `sql/init/02_extensiones_flujo.sql` (ese es solo para BD nueva/vacía).
3. ✅ Configuración cargada a mano en `configuracion`: `nequi_link_pago`, `wompi_habilitado = 1`
   (`id_cliente_adso = 45` vino en la migración 05).
4. ✅ Prueba de humo de punta a punta (4 pasos, incluida la prueba negativa) — ver sección 0.

**Pendiente (lo hace el dueño manualmente):** merge de `fix/flujo-pedido-pago` → `master`.

---

## 6. Riesgos residuales conocidos

- **UX del modal del instructor:** los checkboxes de selección ya no acotan el pago (se paga todo lo pendiente). El texto se aclaró, pero un usuario podría esperar que la selección parcial se respete.
- **Ingresos del portal fuera de los reportes financieros:** un pago del portal no se refleja en la utilidad (los reportes leen `venta`, el portal vive en `pedido_cliente`). Análisis completo y opciones en [`docs/ingresos_portal_analisis.md`](docs/ingresos_portal_analisis.md). Consecuencia secundaria: el POS no ve el pan comprometido a pedidos del portal (posible sobreventa del stock del día).
- **`sql/init/02_extensiones_flujo.sql` asume BD vacía:** re-ejecutarlo sobre una BD que ya tiene esas columnas/tablas falla (por diseño, para no correrlo por error en producción). Para BD existente van las migraciones incrementales.
- **`id_pago_activo` sin integridad referencial:** se limpia por lógica en la app; un borrado manual directo de un `pago_pedido` podría dejar el puntero colgando (mitigado porque `pago_abono` cascada y la app no borra pagos físicamente).
- **Filas antiguas con `estado_pago='pendiente'` sin pago asociado:** conviven con las nuevas `'no_aplica'`; inocuo para las consultas actuales.
- **El enrutamiento a la cuenta ADSO ya NO es por nombre** (resuelto): se lee `configuracion.id_cliente_adso` por id, con fallo visible si la clave falta/apunta a una cuenta inexistente o inactiva. La regla de pago depende de que ese id (y por tanto el `id_cliente` del pedido) esté bien configurado; si se apuntara a la cuenta equivocada, la regla de quién paga heredaría ese error — por eso la clave se valida (existe + activa) antes de usarse.
