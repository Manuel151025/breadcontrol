# Ingresos del portal fuera de los reportes — análisis

**Fecha:** 2026-07-23 · **Rama:** `fix/flujo-pedido-pago` · **Estado:** análisis, NO implementado.

## Problema

Los reportes financieros (Finanzas, Cierre del día, Portada, Tablero) calculan ingresos y
utilidad leyendo la tabla **`venta`**. Los pedidos del portal viven en **`pedido_cliente`**.
Un pago del portal, por tanto, **no aparece como ingreso** en la utilidad.

---

## Hallazgos (con evidencia)

### a) ¿Un pedido del portal descuenta inventario en algún punto del ciclo?

**No, en ninguno.** Recorrido del ciclo completo:

| Paso | Función | Qué escribe | ¿Inventario / venta? |
|------|---------|-------------|----------------------|
| Creación | `PortalClienteModel::crearPedido` ([models/PortalClienteModel.php:664](../models/PortalClienteModel.php#L664)) | `pedido_cliente` + `pedido_cliente_detalle` | **No.** Solo valida precio con un SELECT; no toca `insumo`, `stock`, `venta` ni `consumo_lote`. |
| Aprobación | `aprobarPedidosInstructorLote` | `pedido_cliente.aprobado_instructor`, `fecha_entrega` | No. |
| Pago (habilitar) | `iniciarPagoConsolidado` / `PedidoClienteModel::habilitarPagoDigital` ([:197](../models/PedidoClienteModel.php#L197)) | `pago_pedido`, `pedido_cliente.id_pago_activo` | No. |
| Confirmación del cobro | `PedidoClienteModel::confirmarCobroTienda` ([:157](../models/PedidoClienteModel.php#L157)) / `registrarAbonoPago` ([:223](../models/PedidoClienteModel.php#L223)) | `pedido_cliente.estado/estado_pago`, `pago_pedido.estado`, `pago_abono` | **No** (verificado: no hay `INSERT INTO venta` ni `UPDATE insumo/stock` en esas funciones). |

Cómo se descuenta el inventario **realmente** en el sistema, para contraste:

- **Insumos (materia prima):** se consumen SOLO al registrar una **producción**
  (`ProduccionModel`: `INSERT INTO consumo_lote` FIFO en [ProduccionModel.php:290](../models/ProduccionModel.php#L290)/[:331](../models/ProduccionModel.php#L331) y `UPDATE insumo SET stock_actual = GREATEST(0, stock_actual - ?)` en [:343](../models/ProduccionModel.php#L343)). Ni la venta ni el pedido del portal consumen insumos.
- **Pan terminado:** **no existe un contador almacenado**. El "stock disponible hoy" se
  **calcula al vuelo** en `VentaModel::getStockDisponibleHoy` ([VentaModel.php:77](../models/VentaModel.php#L77)):

  ```
  disponible_hoy(categoría) = producido_hoy (produccion_precio)
                            − vendido_hoy   (venta.unidades_vendidas)
                            − vendido_hoy   (venta_detalle: cantidad+napa+bonificacion)
  ```

  Es decir, **solo los registros en `venta` / `venta_detalle` con fecha de hoy reducen el
  disponible**. `pedido_cliente` no entra en esa fórmula.

### b) Si no descuenta, ¿el pan del portal existe como ingreso o como salida de stock?

**Explícitamente: no existe ni como ingreso ni como salida de unidades terminadas.**
El pedido del portal se registra únicamente en `pedido_cliente`, un universo paralelo
desconectado de finanzas e inventario:

- **Ingreso:** los reportes leen `venta`; el portal nunca crea un `venta` → su pago no suma
  a la utilidad.
- **Stock de pan terminado:** la fórmula de `getStockDisponibleHoy` solo resta `venta`/
  `venta_detalle` → un pedido del portal no reduce el disponible. **Consecuencia secundaria:**
  el POS puede sobre-vender (mostrar como disponible pan que ya está comprometido a pedidos del
  portal del mismo día).

Matiz importante sobre el **costo**: los insumos del pan del portal **sí** están contabilizados,
pero de forma indirecta: se descuentan cuando se registra la **producción** del día (que es
independiente de si el pan luego se despacha por POS o por portal). Por tanto el efecto neto
sobre la utilidad reportada es un **sesgo a la baja**: el costo de insumos de TODO lo producido
se cuenta, pero el ingreso de lo despachado por el portal **no** se cuenta.

### c) ¿Qué hacen exactamente `confirmarCobroTienda` / `marcar_pagado`?

Solo cambian **estados** de pago y pedido. Nada de `venta` ni inventario.

- **`confirmarCobroTienda`** ([PedidoClienteModel.php:157](../models/PedidoClienteModel.php#L157), disparado por `confirmar_cobro_tienda` en [PedidoClienteController.php:51](../controllers/PedidoClienteController.php#L51)):
  `UPDATE pedido_cliente SET estado_pago='aprobado'` (+ `estado='confirmado'` si `wompi_confirmar_auto`)
  y `UPDATE pago_pedido SET estado='APPROVED'` para los pagos vinculados. Fin.
- **`marcar_pagado`** → **`registrarAbonoPago`** ([PedidoClienteModel.php:223](../models/PedidoClienteModel.php#L223), disparado en [PedidoClienteController.php:227](../controllers/PedidoClienteController.php#L227)):
  `INSERT INTO pago_abono`; recalcula y `UPDATE pago_pedido` (estado PARTIAL/APPROVED, monto);
  `UPDATE pedido_cliente SET estado_pago=...` y `estado='confirmado'`. Fin.

### d) ¿Existe ya una función reutilizable que cree registros en `venta`?

**Sí.** `VentaModel::registrarPedidoDetallado(?int $id_cliente, int $id_usuario, array $cart, array $bonif_items)`
([VentaModel.php:202](../models/VentaModel.php#L202)) toma un **carrito de variedades**
(`id_variedad`, `cantidad`) más bonificaciones/ñapa, revalida cantidad/precio/stock, y crea el
maestro `venta` (`tipo_salida='venta'`) + sus `venta_detalle`. También existen
`registrarVentaRapida` ([:174](../models/VentaModel.php#L174)) y `registrarVentaNueva` ([:615](../models/VentaModel.php#L615)) para otros modos.

Dato clave: **`pedido_cliente_detalle` y `venta_detalle` tienen la misma forma** — ambos guardan
`id_variedad, cantidad, napa, bonificacion, precio_unitario`; solo cambia el padre (`id_pedido`
vs `id_venta`). El mapeo pedido→venta es 1:1, así que reutilizar `registrarPedidoDetallado` (o un
hermano) es viable. La cuenta destino del pedido (`pedido_cliente.id_cliente`) mapea a
`venta.id_cliente`.

---

## Opciones (con costo, riesgo, migración, pedidos existentes y anti-doble-conteo)

### Opción A — Crear el `venta` al confirmar el cobro

Cuando el propietario confirma el cobro (en `confirmarCobroTienda` y/o `registrarAbonoPago` al
alcanzar 'aprobado'), generar el/los `venta`+`venta_detalle` de los pedidos confirmados,
reutilizando el patrón de `registrarPedidoDetallado`.

- **Archivos:** `PedidoClienteModel` (`confirmarCobroTienda`, `registrarAbonoPago`),
  `PedidoClienteController` (para pasar el `id_usuario` del propietario), y `VentaModel`
  (un método que arme el `venta` desde un `id_pedido`).
- **Migración:** **Sí.** Agregar `pedido_cliente.id_venta INT NULL` (FK a `venta`, `ON DELETE SET NULL`)
  para vincular el pedido con su venta y evitar doble conteo.
- **Pedidos existentes:** los ya confirmados antes del cambio quedarían sin `venta` (sin ingreso
  histórico). Decidir entre: (i) dejarlos como están, o (ii) un **backfill** que cree ventas
  retroactivas para los `pedido_cliente` en `estado_pago='aprobado'` sin `id_venta`, usando como
  `fecha_hora` la fecha del cobro/entrega (no `NOW()`, para no distorsionar los reportes por día).
- **Anti-doble-conteo:** guard por `pedido_cliente.id_venta` — si ya tiene venta, no crear otra.
  La creación de la venta debe ir en la **misma transacción** que el cambio de estado del cobro.
- **Riesgos:**
  - **La validación de stock de `registrarPedidoDetallado` es "de hoy" y LANZA si falta stock.**
    Un cobro se confirma días después del pedido; el "producido hoy" no corresponde al día de
    entrega. Reutilizarla tal cual causaría fallos o números sin sentido → hay que usar una
    variante **sin** validación de stock de hoy (registro en diferido). Riesgo medio-alto.
  - **Fecha de la venta:** si se usa `NOW()`, el ingreso cae el día del cobro, no el de la
    producción/entrega → descuadra la conciliación diaria. Probablemente usar `fecha_entrega`.
  - **`id_usuario`:** `registrarPedidoDetallado` exige el vendedor; en el cobro es el propietario
    (`usuarioActual()`).
  - **Doble descuento de stock a futuro:** si algún día el stock se hace persistente, cuidar que
    producción y venta-del-portal no se resten dos veces.
- **Costo:** medio (3-4 archivos + 1 migración + backfill opcional).

### Opción B — Alternativas

**B.1 — Venta en diferido con fecha coherente (variante recomendada de A).**
Igual que A, pero con un método nuevo dedicado `VentaModel::registrarVentaDesdePedidoPortal(int $id_pedido, int $id_usuario)`
que: lee `pedido_cliente` + `pedido_cliente_detalle`, arma `venta`/`venta_detalle` con
`fecha_hora = pedido.fecha_entrega`, `tipo_salida='venta'`, `id_cliente = pedido.id_cliente`, y
**omite** la validación de "stock de hoy" (el pan ya se produjo/entregó). Misma migración
(`id_venta`) y mismo guard de doble conteo que A.
- **Ventaja:** los reportes por día cuadran (ingreso el día de la entrega) y sin fricción de stock.
- **Costo/riesgo:** igual que A pero sin el choque con la validación de stock. Recomendada si se
  quiere un **único libro de ventas** que incluya el portal.

**B.2 — No duplicar en `venta`; unificar los reportes para que sumen también el portal.**
No se crea ningún `venta`. Se ajustan los reportes (vía `FinanzasHelper`, que ya centraliza la
utilidad, más `FinanzasModel`/`CierreModel`/`AuthModel`/`TableroModel`) para **sumar además**
`SUM(pedido_cliente.total_estimado) WHERE estado_pago='aprobado'` en el rango de fechas.
- **Migración:** **No.**
- **Pedidos existentes:** se cuentan automáticamente (se lee el estado actual, no se necesita
  backfill).
- **Anti-doble-conteo:** **inherente.** Se lee un **estado** (`estado_pago='aprobado'`), no se
  crea una fila. Confirmar un pedido dos veces no cambia el estado ni suma dos veces.
- **Ventaja:** cero riesgo de doble descuento de stock, no toca el POS ni el inventario.
- **Desventaja:** quedan **dos fuentes de ingreso** que hay que mantener sincronizadas en cada
  reporte; y el pan del portal **sigue sin reducir el stock disponible** del POS (la sobreventa
  del punto (b) persiste). Hay que definir la fecha del ingreso (`fecha_entrega` vs fecha de cobro).
- **Costo:** medio (varios reportes) · **Riesgo:** bajo.

---

## Recomendación

- Si el objetivo es **utilidad correcta con mínimo riesgo y sin doble conteo por diseño**: **B.2**
  (sumar el portal en los reportes). Es idempotente por naturaleza y no toca inventario ni POS.
- Si además se quiere **un único libro de ventas** y que el portal **reduzca el stock disponible**:
  **B.1** (crear la venta en diferido con `fecha_entrega`), asumiendo la migración `id_venta`, el
  guard de idempotencia y la decisión sobre el backfill.

En ambos casos conviene resolver aparte el punto (b) secundario: hoy el POS **no ve** el pan
comprometido a pedidos del portal, así que puede sobre-vender el stock del día.
