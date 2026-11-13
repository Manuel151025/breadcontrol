# Diagramas — BreadControl

Estos diagramas se dibujaron el **2026-09-14** a partir del esquema real
(`sql/init/01_esquema_base.sql`, consultado sobre una base recién creada con
`information_schema`) y del código de los modelos. GitHub los dibuja directamente.

**Si cambias el esquema, actualiza el diagrama en el mismo PR.** Un diagrama desactualizado es peor que no tenerlo: se cree.

## Contenido

1. [Modelo entidad-relación](#1-modelo-entidad-relación)
2. [Integración continua](#2-integración-continua)

La arquitectura por capas está en el [README](../README.md#-arquitectura).

---

## 1. Modelo entidad-relación

La base tiene **24 tablas y 6 vistas**. Para que se pueda leer, el modelo se
divide en dos dominios; `categoria_precio`, `variedad_pan` y `usuario` aparecen
en más de uno porque los unen.

**Cómo se lee:**

- Línea **continua**: clave foránea real, la base la garantiza.
- Línea **punteada**: relación que el código usa pero que la base **no** garantiza
  (no hay clave foránea). Ver [la tabla del final](#relaciones-sin-clave-foránea).
- `||` exactamente uno · `|o` cero o uno · `o{` cero o muchos.

### 1.1 Inventario y producción

Casi todas las tablas operativas guardan `id_usuario` (quién registró). Para que el
diagrama se lea, esa relación solo se dibuja en `compra` y `produccion`.

```mermaid
erDiagram
    proveedor ||--o{ compra : "surte"
    insumo ||--o{ compra : "se compra en"
    usuario ||--o{ compra : "registra"
    insumo ||--o{ lote : "entra por"
    compra |o..o{ lote : "origina"
    compra ||--o{ historial_precio : "deja"
    insumo ||--o{ historial_precio : "varía en"
    proveedor ||--o{ historial_precio : "cotiza en"
    insumo ||--o{ ajuste_inventario : "se ajusta en"
    producto ||--o{ receta : "tiene versiones"
    receta ||--o{ receta_ingrediente : "lleva"
    insumo ||--o{ receta_ingrediente : "se usa en"
    producto ||--o{ produccion : "se produce en"
    receta ||--o{ produccion : "se aplica en"
    usuario ||--o{ produccion : "registra"
    produccion ||--o{ consumo_lote : "consume"
    lote ||--o{ consumo_lote : "se consume en"
    produccion ||--o{ produccion_precio : "se reparte en"
    categoria_precio ||--o{ produccion_precio : "recibe unidades de"

    insumo {
        int id_insumo PK
        varchar nombre
        enum unidad_medida
        tinyint es_harina
        decimal stock_actual
        decimal punto_reposicion
    }
    lote {
        int id_lote PK
        int id_insumo FK
        int id_compra "sin FK"
        decimal cantidad_disponible
        decimal precio_unitario
        enum estado
    }
    compra {
        int id_compra PK
        int id_insumo FK
        int id_proveedor FK
        int id_usuario FK
        decimal cantidad
        decimal precio_unitario
        decimal variacion_precio_pct
    }
    proveedor {
        int id_proveedor PK
        varchar nombre
    }
    historial_precio {
        int id_historial PK
        int id_compra FK
        int id_insumo FK
        int id_proveedor FK
        decimal precio
        decimal variacion_pct
    }
    ajuste_inventario {
        int id_ajuste PK
        int id_insumo FK
        decimal cantidad_antes
        decimal cantidad_despues
        varchar motivo
    }
    producto {
        int id_producto PK
        varchar nombre
        decimal cantidad_por_tanda
    }
    receta {
        int id_receta PK
        int id_producto FK
        int version
        tinyint es_vigente
    }
    receta_ingrediente {
        int id_receta_ing PK
        int id_receta FK
        int id_insumo FK
        decimal cantidad
        tinyint aplica_merma
    }
    produccion {
        int id_produccion PK
        int id_producto FK
        int id_receta FK
        int id_usuario FK
        decimal cantidad_tandas
        int unidades_producidas
        decimal costo_unitario
    }
    consumo_lote {
        int id_consumo PK
        int id_lote FK
        int id_produccion FK
        decimal cantidad_con_merma
        decimal costo_consumo
    }
    produccion_precio {
        int id PK
        int id_produccion FK
        int id_categoria_precio FK
        int unidades
    }
    categoria_precio {
        int id_categoria PK
        decimal precio_unitario
    }
    usuario {
        int id_usuario PK
        varchar nombre_usuario
        enum rol
    }
```

**FIFO:** una producción descuenta los insumos de los lotes más antiguos primero, y
cada descuento queda en `consumo_lote` con su costo. Así el costo de una producción
es el de los lotes que de verdad consumió, no un precio promedio.

### 1.2 Ventas, caja y administración

```mermaid
erDiagram
    categoria_precio |o--o{ venta : "da el precio de"
    producto |o--o{ venta : "se vende en"
    usuario |o--o{ venta : "registra"
    cierre_dia |o--o{ venta : "cierra"
    cliente |o..o{ venta : "compra en"
    venta ||--o{ venta_detalle : "se detalla en"
    variedad_pan ||--o{ venta_detalle : "se vende en"
    categoria_precio ||--o{ variedad_pan : "fija el precio de"
    usuario ||--o{ cierre_dia : "hace"
    cierre_dia |o--o{ gasto : "incluye"
    usuario ||--o{ gasto : "registra"
    usuario ||--o{ proyeccion_caja : "genera"
    usuario ||--o{ alerta : "recibe"

    venta {
        int id_venta PK
        enum tipo_salida "venta, bonificación o consumo interno"
        int id_categoria_precio FK
        int id_producto FK
        int id_cierre_dia FK
        int id_cliente "sin FK"
        int id_usuario FK
        int unidades_vendidas
        decimal total_venta
    }
    venta_detalle {
        int id_detalle PK
        int id_venta FK
        int id_variedad FK
        int cantidad
        int napa
        int bonificacion
    }
    cierre_dia {
        int id_cierre PK
        int id_usuario FK
        date fecha
        decimal total_ingresos
        decimal utilidad_neta
    }
    gasto {
        int id_gasto PK
        int id_cierre_dia FK
        int id_usuario FK
        enum categoria
        decimal valor
    }
    proyeccion_caja {
        int id_proyeccion PK
        int id_usuario FK
        date semana_proyectada
        decimal saldo_proyectado
    }
    alerta {
        int id_alerta PK
        int id_usuario FK
        enum tipo
        enum estado
    }
    categoria_precio {
        int id_categoria PK
        decimal precio_unitario
    }
    variedad_pan {
        int id_variedad PK
        varchar nombre
    }
    producto {
        int id_producto PK
        varchar nombre
    }
    usuario {
        int id_usuario PK
        enum rol
    }
    cliente {
        int id_cliente PK
        varchar nombre
    }
```

### Tablas sin relaciones y vistas

| Objeto | Para qué |
|---|---|
| `intento_login` | Intentos fallidos de acceso y de recuperación (`ambito`, `identificador`, `ip`, `fecha`). No referencia cuentas a propósito: también registra intentos contra usuarios que no existen |
| `migracion` | Qué migraciones tiene aplicadas esta base. La crea `sql/migraciones/2026-08-20_01_control_migraciones.sql`, no el esquema base |
| `v_inventario_actual`, `v_insumos_alerta`, `v_lotes_fifo` | Stock, alertas y lotes disponibles en orden FIFO |
| `v_margen_productos`, `v_resumen_financiero_30d`, `v_stock_productos_hoy` | Margen por producto, finanzas de 30 días y stock del día |

### Relaciones sin clave foránea

Estas dos relaciones las usa el código, pero la base no impide que apunten a una
fila inexistente. Se dejan anotadas porque son las primeras candidatas si algún día
se endurece la integridad del esquema.

| Columna | Apunta a | Qué pasa si queda huérfana |
|---|---|---|
| `lote.id_compra` | `compra` | Se pierde la trazabilidad de qué compra originó el lote |
| `venta.id_cliente` | `cliente` | La venta queda sin cliente asociado |

---

## 2. Integración continua

Once verificaciones en cada push y pull request, más la comprobación diaria del
sitio publicado. El detalle de lo que exige cada una está en el
[README](../README.md#-integración-continua) y el razonamiento de sus umbrales en
[`estrategia_pruebas.md`](estrategia_pruebas.md).

```mermaid
flowchart LR
    EV(["push o pull request"])
    PR(["pull request hacia main"])
    DIA(["cada día"])

    EV --> SX["Sintaxis PHP<br/>8.2 y 8.3"]
    EV --> ST["PHPStan<br/>nivel 10"]
    EV --> UN["Pruebas unitarias<br/>8.2 y 8.3"]
    EV --> IN["Integración con MySQL 8<br/>8.2 y 8.3"]
    EV --> CO["Cobertura PCOV<br/>suelo del 12 %"]
    EV --> MU["Mutación Infection<br/>MSI mínimo 69 %"]
    EV --> CA["PHPMD y PHPMetrics"]
    EV --> AU["composer audit<br/>Gitleaks · Semgrep"]
    EV --> CB["Cabeceras de seguridad<br/>en la imagen Docker"]
    EV --> SN["Snyk<br/>si hay token"]
    PR --> E2E["Playwright<br/>18 recorridos y axe"]
    DIA --> CP["Cabeceras y cookies<br/>del sitio publicado"]

    SX & ST & UN & IN & CO & MU & CA & AU & CB & SN & E2E --> OK{"¿todo en verde?"}
    OK -->|sí| M["Fusionar en main"]
    M --> DK["Dokploy despliega<br/>en el VPS"]
```
