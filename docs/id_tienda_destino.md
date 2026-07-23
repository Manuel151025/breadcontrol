# Columna `pedido_cliente.id_tienda_destino` — análisis y propuesta

**Fecha:** 2026-07-23 · **Rama:** `fix/flujo-pedido-pago`

## Qué es

`pedido_cliente.id_tienda_destino` (`int(11) NULL`, con índice `KEY id_tienda_destino`)
se definió junto con la tabla de pedidos (`sql/crear_pedidos.sql` / `sql/init/02_extensiones_flujo.sql`).
Aparenta registrar la "tienda destino" de un pedido, en el contexto de la funcionalidad
**Tiendas Beneficiarias** (columna `cliente.es_beneficiaria`).

## Qué código la ESCRIBE

**Ninguno.** No existe ningún `INSERT` ni `UPDATE` que asigne `id_tienda_destino` en todo
el proyecto (verificado con `grep -rn id_tienda_destino --include=*.php`). En la práctica la
columna es **siempre `NULL`** para todos los pedidos.

- `PortalClienteModel::crearPedido()` — el único punto que inserta pedidos — no la toca.

## Qué código la LEE

**Un solo lugar:** `models/ConfiguracionModel.php::getTiendasBeneficiarias()`, en una
subconsulta correlacionada:

```sql
SELECT c.*,
  (SELECT COUNT(*) FROM pedido_cliente WHERE id_tienda_destino = c.id_cliente) AS total_pedidos_destino
FROM cliente c
WHERE c.es_beneficiaria = 1 AND c.activo = 1
ORDER BY c.nombre
```

Como nadie escribe la columna, `total_pedidos_destino` **da 0 para toda tienda beneficiaria,
siempre**. La pantalla admin "Tiendas Beneficiarias" (`views/configuracion/tiendas.php`) muestra
ese contador, que por tanto nunca refleja nada real.

## ¿Duplica a `id_cliente`?

**Funcionalmente hoy, no duplica nada: está muerta** (siempre NULL). El destinatario real —
quien recibe y paga el pedido — es `id_cliente` (con `id_creador` = quien lo armó). La feature
de pagos y el flujo aprendiz→instructor se apoyan por completo en `id_cliente`/`id_creador`
(ver la regla única de pago), **no** en `id_tienda_destino`.

Conceptualmente `id_tienda_destino` iba a ser algo **distinto** de `id_cliente`: la *tienda
beneficiaria* a la que se "dona" o dirige un pedido, que no es necesariamente quien paga. Pero
ese vínculo nunca se implementó del lado de escritura, así que quedó como el resto incompleto de
la funcionalidad "Tiendas Beneficiarias".

## Estado: **SIN USO (columna huérfana del lado de escritura)**

## Propuesta

Dos caminos según se quiera o no la funcionalidad de tiendas beneficiarias:

### Opción A (recomendada si NO se va a completar la feature): eliminar la columna
Deja el esquema honesto y evita confusión. Requiere **dos cambios acoplados** (ambos o ninguno):

1. Quitar la subconsulta muerta en `ConfiguracionModel::getTiendasBeneficiarias()` (sustituir
   `total_pedidos_destino` por `0 AS total_pedidos_destino`, o eliminar el campo y su uso en la vista).
2. Ejecutar la migración de borrado:

```sql
SET SQL_SAFE_UPDATES = 0;
-- Requiere haber quitado antes la subconsulta que la lee (paso 1), o la vista fallara.
ALTER TABLE `pedido_cliente` DROP COLUMN `id_tienda_destino`;
SET SQL_SAFE_UPDATES = 1;
```

> No se incluye esta migración en `sql/migraciones/` a propósito: borrar una columna es
> irreversible y va acoplada a un cambio de código; queda como decisión explícita del dueño.

### Opción B (si SÍ se quiere la feature de pedidos a tienda beneficiaria): completarla
Cablear un punto de escritura (p. ej. al crear un pedido, permitir elegir una tienda beneficiaria
destino y guardar su id en `id_tienda_destino`) y decidir la semántica frente a `id_cliente`
(¿quién paga, quién recibe?). Es trabajo de producto fuera del alcance del flujo actual.

**Recomendación:** Opción A. Hoy la columna solo aporta un contador que siempre marca 0.
