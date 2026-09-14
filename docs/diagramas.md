# Diagramas — BreadControl

Estos diagramas se dibujaron el **2026-09-14** a partir del esquema real
(`sql/init/01_esquema_base.sql`, consultado sobre una base recién creada con
`information_schema`) y del código de los modelos. GitHub los dibuja directamente.

**Si cambias el esquema o el flujo de pedidos, actualiza el diagrama en el mismo
PR.** Un diagrama desactualizado es peor que no tenerlo: se cree.

## Contenido

1. [Modelo entidad-relación](#1-modelo-entidad-relación)
2. [Ciclo de vida de un pedido](#2-ciclo-de-vida-de-un-pedido)
3. [Ciclo de vida del pago](#3-ciclo-de-vida-del-pago)
4. [Secuencia: aprendiz → instructor → panadería](#4-secuencia-aprendiz--instructor--panadería)
5. [Procesamiento por lotes en los pedidos](#5-procesamiento-por-lotes-en-los-pedidos)
6. [Integración continua](#6-integración-continua)

La arquitectura por capas está en el [README](../README.md#-arquitectura).

---

## 1. Modelo entidad-relación

La base tiene **29 tablas y 6 vistas**. Para que se pueda leer, el modelo se
divide en tres dominios; `categoria_precio`, `variedad_pan` y `usuario` aparecen
en más de uno porque los unen.

**Cómo se lee:**

- Línea **continua**: clave foránea real, la base la garantiza.
- Línea **punteada**: relación que el código usa pero que la base **no** garantiza
  (no hay clave foránea). Ver [la tabla del final](#relaciones-sin-clave-foránea).
- `||` exactamente uno · `|o` cero o uno · `o{` cero o muchos.

### 1.1 Portal: clientes, pedidos y pagos

```mermaid
erDiagram
    cliente ||--o{ pedido_cliente : "se le factura"
    cliente |o--o{ pedido_cliente : "lo crea"
    cliente ||--o{ codigo_aprendiz : "genera como instructor"
    cliente |o..o{ cliente : "es instructor de"
    configuracion |o..o| cliente : "señala la cuenta ADSO"
    pedido_cliente ||--o{ pedido_cliente_detalle : "contiene"
    variedad_pan ||--o{ pedido_cliente_detalle : "se pide en"
    categoria_precio ||--o{ variedad_pan : "fija el precio de"
    pedido_cliente |o--o{ pago_pedido : "es la referencia de"
    pago_pedido |o..o{ pedido_cliente : "cubre"
    pago_pedido ||--o{ pago_abono : "recibe"

    cliente {
        int id_cliente PK
        varchar nombre
        enum tipo "tienda o mostrador"
        varchar usuario UK
        varchar email UK
        tinyint es_aprendiz
        int id_instructor "sin FK"
        decimal cupo_semanal
    }
    codigo_aprendiz {
        int id_codigo PK
        int id_instructor FK
        varchar codigo
        datetime fecha_expira
        int usos_maximos
        int usos_actuales
        tinyint activo
    }
    configuracion {
        int id_config PK
        int id_cliente_adso "sin FK"
        varchar nequi_link_pago
        tinyint wompi_habilitado
    }
    pedido_cliente {
        int id_pedido PK
        int id_cliente FK "quien paga"
        int id_creador FK "quien pide"
        date fecha_entrega
        decimal total_estimado
        tinyint aprobado_instructor
        varchar estado
        varchar estado_pago
        int id_pago_activo "sin FK"
    }
    pedido_cliente_detalle {
        int id_detalle PK
        int id_pedido FK
        int id_variedad FK
        int cantidad
        decimal precio_unitario
        tinyint napa
        tinyint bonificacion
    }
    pago_pedido {
        int id_pago PK
        int id_pedido FK
        varchar referencia
        decimal monto
        varchar estado "PENDING PARTIAL APPROVED EXPIRED VOIDED"
        datetime fecha_expiracion
    }
    pago_abono {
        int id_abono PK
        int id_pago FK
        decimal monto
        varchar metodo_pago
    }
    variedad_pan {
        int id_variedad PK
        int id_categoria_precio FK
        varchar nombre
    }
    categoria_precio {
        int id_categoria PK
        varchar nombre
        decimal precio_unitario
    }
```

**Lo que más confunde de este dominio:** un pedido tiene **dos** clientes.
`id_cliente` es quien **paga** y `id_creador` quien **pide**. En un pedido normal
son la misma cuenta; en el de un aprendiz para ADSO, `id_cliente` es el instructor.
De ahí salen las dos reglas de autorización del portal: solo paga `id_cliente`, y
solo edita `id_creador` (`ReglasPortal::esAutorDelPedido`).

Un pago consolidado cubre varios pedidos: cada pedido lo apunta con
`id_pago_activo`, y el pago guarda en `id_pedido` solo el más antiguo, como
referencia.

### 1.2 Inventario y producción

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

> **«Lote» significa dos cosas en este proyecto.** Aquí es un **lote de insumo**
> (una entrada de harina con su precio, que se consume en orden FIFO). En los
> pedidos, «en lote» significa **procesar varios pedidos a la vez**
> ([sección 5](#5-procesamiento-por-lotes-en-los-pedidos)).

### 1.3 Ventas, caja y administración

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

Estas cinco relaciones las usa el código, pero la base no impide que apunten a una
fila inexistente. Se dejan anotadas porque son las primeras candidatas si algún día
se endurece la integridad del esquema.

| Columna | Apunta a | Qué pasa si queda huérfana |
|---|---|---|
| `cliente.id_instructor` | `cliente` | El aprendiz queda sin grupo. `quitarAprendiz` la pone a `NULL` explícitamente |
| `configuracion.id_cliente_adso` | `cliente` | `getClienteAdso()` lo detecta y el portal muestra un error claro en lugar de enviar pedidos a una cuenta que no existe |
| `pedido_cliente.id_pago_activo` | `pago_pedido` | El pedido parece tener un pago en curso que no existe |
| `lote.id_compra` | `compra` | Se pierde la trazabilidad de qué compra originó el lote |
| `venta.id_cliente` | `cliente` | La venta queda sin cliente asociado |

---

## 2. Ciclo de vida de un pedido

Un pedido del portal combina dos columnas: `estado` (lo que decide la panadería) y
`aprobado_instructor` (lo que decide el instructor, solo en pedidos de aprendiz).

```mermaid
stateDiagram-v2
    state "Por aprobar" as PorAprobar
    state "Pendiente" as Pendiente
    state "Confirmado" as Confirmado
    state "Rechazado" as Rechazado

    [*] --> PorAprobar : el aprendiz pide para ADSO
    [*] --> Pendiente : un cliente pide, o un aprendiz para su cuenta
    PorAprobar --> Pendiente : el instructor aprueba y fija la fecha
    PorAprobar --> Rechazado : el instructor rechaza, o el aprendiz cancela
    Pendiente --> PorAprobar : el aprendiz edita su pedido ADSO
    Pendiente --> Confirmado : la panadería confirma, o registra el cobro
    Pendiente --> Rechazado : la panadería rechaza, o el cliente cancela
    Confirmado --> Pendiente : la panadería lo reabre
    Rechazado --> Pendiente : la panadería lo reabre
```

| Estado del diagrama | `estado` | `aprobado_instructor` | Quién lo ve |
|---|---|---|---|
| Por aprobar | `pendiente` | `0` | El aprendiz y su instructor. La panadería **no** lo ve todavía |
| Pendiente | `pendiente` | `1` | Todos |
| Confirmado | `confirmado` | — | Todos |
| Rechazado | `rechazado` | — | Todos. `mensaje_propietario` dice quién lo retiró |

**Reglas que gobiernan las flechas** (todas en `helpers/ReglasPortal.php`):

- **Editar**: solo quien creó el pedido, si sigue pendiente y faltan más de 48 horas
  para la entrega. Si es un pedido ADSO, editarlo **vuelve a exigir** la aprobación
  del instructor y borra la fecha que había puesto.
- **Cancelar**: si sigue pendiente, salvo en las 48 horas previas a la entrega. Un
  pedido ya vencido vuelve a poder cancelarse (punto 30 del anexo de limitaciones).
- **Con un pago en curso**, el aprendiz no puede ni editar ni cancelar.
- **Aprobar o rechazar en lote** solo afecta a pedidos que siguen pendientes: un
  pedido que el aprendiz canceló mientras el instructor tenía el tablero abierto
  no se reactiva (punto 33).
- Un pedido ADSO se guarda con `fecha_entrega = 1000-01-01`, que la interfaz muestra
  como «Por definir» hasta que el instructor lo aprueba.

---

## 3. Ciclo de vida del pago

Hay **dos** columnas de estado con vocabularios distintos, y conviene no mezclarlas:
`pedido_cliente.estado_pago` (minúsculas) y `pago_pedido.estado` (mayúsculas,
definido en `includes/estados_pago.php`).

```mermaid
stateDiagram-v2
    state "no_aplica" as NoAplica
    state "pendiente · PENDING" as Pendiente
    state "parcial · PARTIAL" as Parcial
    state "aprobado · APPROVED" as Aprobado

    [*] --> NoAplica : se crea el pedido
    NoAplica --> Pendiente : se genera el pago consolidado
    Pendiente --> Parcial : la panadería registra un abono menor que el saldo
    Parcial --> Parcial : otro abono parcial
    Parcial --> Aprobado : los abonos cubren el total
    Pendiente --> Aprobado : abono completo, o cobro masivo de tienda
    Pendiente --> NoAplica : se retira el pago o se cancela el pedido, EXPIRED
    Parcial --> NoAplica : se retira o se revierte el pago
    Aprobado --> NoAplica : se revierte el pago, VOIDED
```

- **Nadie marca un pago como pagado desde el portal.** El cliente o el instructor
  solo **generan** el pago y reciben el enlace de Nequi. La panadería confirma cuando
  ve el dinero, registrando el abono en el back-office (`PedidoClienteModel::registrarAbonoPago`).
- Registrar un abono también **confirma** los pedidos que cubre (`estado = confirmado`).
- Un abono no puede superar el saldo pendiente, con un peso de tolerancia por redondeo.
- Generar un pago dos veces sobre los mismos pedidos no crea otro: se reutiliza el
  que sigue `PENDING`.

---

## 4. Secuencia: aprendiz → instructor → panadería

El recorrido completo, desde que el instructor invita a un aprendiz hasta que la
panadería confirma el dinero. Los pasos 5 a 12 son los que recorre
`e2e/tests/portal-aprendiz-instructor.spec.js`.

```mermaid
sequenceDiagram
    autonumber
    actor I as Instructor
    actor A as Aprendiz
    participant P as Portal
    participant BD as Base de datos
    actor D as Panadería
    participant N as Nequi

    I->>P: Genera un código de invitación
    A->>P: Canjea el código al registrarse o desde su perfil
    P->>BD: es_aprendiz = 1, id_instructor, cupo semanal de 20.000
    A->>P: Arma el carrito y pide para la cuenta ADSO
    P->>BD: Bloquea la fila del aprendiz y comprueba el cupo semanal
    P->>BD: pedido_cliente con id_cliente = instructor, id_creador = aprendiz, aprobado_instructor = 0
    Note over A,P: Mientras no se apruebe, el aprendiz puede editarlo o cancelarlo
    I->>P: Marca varios pedidos y los aprueba en lote con una fecha
    P->>BD: aprobado_instructor = 1 y fecha_entrega, solo en los que siguen pendientes
    D->>BD: Ve los pedidos aprobados y los prepara
    I->>P: Genera el pago consolidado
    P->>BD: pago_pedido PENDING y estado_pago pendiente en cada pedido
    P-->>I: Enlace de Nequi por el total
    I->>N: Paga
    D->>BD: Registra el abono al ver el dinero
    BD-->>P: estado_pago aprobado o parcial, y pedidos confirmados
```

---

## 5. Procesamiento por lotes en los pedidos

El flujo de pedidos está pensado para que nadie tenga que procesarlos uno por uno.
Estos son los puntos donde se opera sobre **varios pedidos a la vez**:

| Quién | Operación | Qué hace con el lote | Dónde |
|---|---|---|---|
| Instructor | **Aprobar en lote** | Aprueba los pedidos marcados y les fija la **misma** fecha de entrega (la hora se pide pero hoy se descarta: punto 34 del anexo) | `InstructorPortalTrait::aprobarPedidosInstructorLote` |
| Instructor | **Rechazar en lote** | Rechaza los pedidos marcados | `InstructorPortalTrait::rechazarPedidosInstructorLote` |
| Instructor | **Pago consolidado** | Junta todos los pedidos aprobados y sin pagar en **un solo pago** y un solo enlace de Nequi | `PortalPagoController::pagarConsolidado` |
| Panadería | **Registrar un abono** | Un abono se aplica al pago consolidado entero y confirma todos sus pedidos | `PedidoClienteModel::registrarAbonoPago` |
| Panadería | **Cambiar estado en lote** | Confirma, rechaza o reabre los pedidos marcados | `PedidoClienteModel::cambiarEstadoLote` |
| Panadería | **Confirmar cobro masivo** | Marca como pagados los pedidos de tienda seleccionados | `PedidoClienteModel::confirmarCobroTienda` |
| Tienda | **Reporte por fecha de entrega** | Agrupa por aprendiz y producto todos los pedidos de un mismo día | `PedidosPortalTrait::getReporteAgrupadoTienda` |
| Cliente / instructor | **Exportar seleccionados** | Genera un solo documento con los pedidos marcados | `PortalExportController` |

Las operaciones del instructor se validan **pedido por pedido** dentro del lote: si
uno no es de su grupo o ya no está pendiente, se excluye, y el mensaje cuenta solo
los que de verdad cambiaron. Las de la panadería no filtran así porque solo las
ejecuta el propietario, que puede actuar sobre cualquier pedido.

---

## 6. Integración continua

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
    PR --> E2E["Playwright<br/>29 recorridos y axe"]
    DIA --> CP["Cabeceras y cookies<br/>del sitio publicado"]

    SX & ST & UN & IN & CO & MU & CA & AU & CB & SN & E2E --> OK{"¿todo en verde?"}
    OK -->|sí| M["Fusionar en main"]
    M --> DK["Dokploy despliega<br/>en el VPS"]
```
