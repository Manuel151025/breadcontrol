# 🍞 BreadControl

**Sistema de Gestión Integral para Panaderías Artesanales**

BreadControl es una aplicación web diseñada específicamente para digitalizar y optimizar la operación diaria de panaderías artesanales colombianas. Desde el control de inventario hasta el cierre de caja y el portal de pedidos para clientes, todo en un solo lugar.

> 🌐 **Demo en vivo:** [breadcontrol.manuelcardenas.online](https://breadcontrol.manuelcardenas.online)

[![CI](https://github.com/Manuel151025/breadcontrol/actions/workflows/ci.yml/badge.svg)](https://github.com/Manuel151025/breadcontrol/actions/workflows/ci.yml)
[![Licencia: MIT](https://img.shields.io/badge/licencia-MIT-green.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-%E2%89%A5%208.2-777bb4.svg)](composer.json)
[![Versión](https://img.shields.io/badge/versi%C3%B3n-1.6.2-blue.svg)](CHANGELOG.md)

---

## 📋 Tabla de Contenido

- [Características](#-características)
- [Módulos](#-módulos)
- [Tecnologías](#-tecnologías)
- [Arquitectura](#-arquitectura)
- [Instalación](#-instalación)
- [Configuración inicial en un despliegue nuevo](#️-configuración-inicial-en-un-despliegue-nuevo)
- [Estructura del Proyecto](#-estructura-del-proyecto)
- [Base de Datos](#-base-de-datos)
- [Pruebas](#-pruebas)
- [Integración Continua](#-integración-continua)
- [Seguridad](#-seguridad)
- [Contribuir](#-contribuir)
- [Autor](#-autor)
- [Licencia](#-licencia)

---

## ✨ Características

- **Inventario en tiempo real** con alertas de stock bajo y método FIFO para insumos.
- **Ajustes de inventario** automáticos y transaccionales con re-cálculo de lotes activos.
- **Producción inteligente** con descuento automático de insumos, costeo real por tanda y distribución por categoría de precio.
- **Ventas por categoría de precio** ($500, $1,000, $2,000, $3,000, $5,000) con precio personalizado.
- **Carrito tipo MercadoLibre** para detallar pedidos grandes con fotos de variedades de pan.
- **Bonificación 20% automática** para tiendas con distribución detallada por variedad.
- **Ñapa configurable** por variedad en pedidos detallados.
- **Tres tipos de salida:** Venta (genera ingreso), Bonificación (pan regalado), Consumo interno (empleados).
- **Control de merma** del 6% automático para harina de trigo en las compras ingresadas.
- **Cierre del día** con observaciones que aparecen como banner al día siguiente en el tablero principal.
- **Portal del Cliente** con registro tradicional o Google OAuth (Google Login).
- **Flujo Educativo Aprendiz-Instructor:** Control estricto de cupo semanal para aprendices y portal del instructor para aprobar solicitudes y consolidar pagos.
- **Pagos con Nequi (link manual):** Consolidación de saldos de pedidos en un solo link de pago de Nequi Negocios; la panadería confirma el recibo manualmente desde el back-office.
- **Finanzas** con gráficos, KPIs y exportación a PDF.
- **Clima en tiempo real** integrado con la API de Open-Meteo.
- **Responsive** — funciona en PC, tablet y celular de manera fluida.
- **Auto-logout** por inactividad (6 minutos).

---

## 📦 Módulos

| # | Módulo | Descripción |
|---|--------|-------------|
| 1 | **Tablero** | KPIs del día, gráfico de ventas de 7 días, clima, acciones rápidas, banner de observaciones |
| 2 | **Inventario** | CRUD de insumos, alertas de stock bajo, barras visuales de nivel, ajuste manual y eliminación masiva |
| 3 | **Producción** | Registro por tandas, descuento FIFO de lotes, costeo real, distribución por categoría de precio |
| 4 | **Ventas** | Venta rápida + carrito detallado, bonificación automática para tiendas (20%), ñapas, consumo interno |
| 5 | **Recetas** | Catálogo de productos, ingredientes por receta, variedades de pan con imagen y vigencias |
| 6 | **Compras** | Registro simplificado por bolsas, lotes FIFO automáticos, alerta de variación de precio >5% |
| 7 | **Finanzas** | Ingresos vs compras, utilidad bruta/neta, margen, gráficos mensuales/anuales, exportar PDF |
| 8 | **Gastos** | Registro de gastos operativos diarios por categorías (servicios, compras, otros) |
| 9 | **Cierre del día** | Cuadre de caja, observaciones para el tablero al día siguiente, historial de cierres |
| 10 | **Portal del Cliente** | Registro con Google OAuth / tradicional, solicitud de pedidos y visualización de saldos |
| 11 | **Flujo Educativo (Aprendiz-Instructor)** | Control de cupo semanal para aprendices, portal de instructor para aprobación de pedidos y cobro de cartera |
| 12 | **Pagos con Nequi (manual)** | Consolidación de saldos en un solo link de pago de Nequi Negocios; el propietario confirma el recibo desde el back-office |

**Módulos adicionales:**
- **Perfil de usuario** — Datos personales, cambiar contraseña, configurar PIN de recuperación
- **Recuperar contraseña** — Por correo electrónico (PHPMailer SMTP) o código PIN de 6 dígitos
- **Gestión de clientes y tiendas** — Clientes tipo tienda con bonificación y contacto
- **Variedades de pan** — CRUD con imagen para detallar pedidos del carrito

---

## 🛠 Tecnologías

| Capa | Tecnología |
|------|-----------|
| **Backend** | PHP 8 (MVC modular y orientado a objetos) |
| **Base de datos** | MySQL 8 |
| **Frontend** | HTML5, CSS3 (custom, sin framework), JavaScript vanilla |
| **Iconos** | Bootstrap Icons |
| **Fuentes** | Google Fonts (Fraunces, Plus Jakarta Sans, Playfair Display, DM Sans) |
| **Gráficos** | Chart.js (finanzas), CSS bars (tablero) |
| **Email** | PHPMailer 6.9 (SMTP SSL/TLS) |
| **Clima** | API Open-Meteo |
| **Pagos** | Nequi Negocios (link de pago estático; confirmación manual del propietario) |
| **Autenticación externa** | Google API Client (OAuth 2.0) |
| **Hosting** | Hostinger (PHP + MySQL) |
| **Dependencias** | Composer |
| **Pruebas** | PHPUnit 11 (unitarias + integración) |
| **Análisis estático** | PHPStan **nivel 10** (niveles 1-8 corregidos; 9-10 con baseline) |
| **CI** | GitHub Actions (5 verificaciones por push/PR) |
| **Gestión** | Jira (Scrum), GitHub |

---

## 🏗 Arquitectura

### Componentes y responsabilidades

```mermaid
flowchart TD
    BO["<b>modules/</b> · 11 módulos<br/>Puntos de entrada del back-office"]
    PT["<b>portal/</b> · 17 páginas<br/>Puntos de entrada del portal"]

    CBO["<b>controllers/</b> · 12 controladores<br/>Inventario · Producción · Ventas<br/>Compras · Finanzas · Cierre · …"]
    CPT["<b>controllers/portal/</b> · 6 clases<br/>Base · Auth · Pedido<br/>Pago · Instructor · Export"]

    RP["<b>ReglasPortal</b><br/>crédito y ñapa · límite de 48 h<br/>cupo semanal · horario de entrega"]
    PH["<b>PedidoHelper</b><br/>total esperado y deuda"]
    FH["<b>FinanzasHelper</b><br/>costo real de producción · utilidad<br/>ingresos del portal en reportes"]
    SG["<b>Seguridad</b><br/>política de contraseña<br/>hashing del código de recuperación<br/>umbrales de bloqueo"]

    MBO["<b>models/</b> · 13 modelos<br/>Consultas preparadas por entidad"]
    MPT["<b>PortalClienteModel</b><br/>fachada de 5 traits:<br/>Cuenta · Catálogo · Pedidos<br/>Pagos · Instructor"]
    MIL["<b>IntentoLoginModel</b><br/>intentos fallidos de acceso"]

    SES["<b>includes/sesion.php</b><br/>sesión · guardián CSRF<br/>autorización · IP de origen"]
    CFG["<b>config/</b><br/>entorno · conexión PDO · logger"]

    VW["<b>views/</b><br/>plantillas HTML sin cálculos"]
    AS["<b>assets/</b><br/>CSS y JS externos"]

    BD[("MySQL 8")]

    BO --> CBO
    PT --> CPT
    CBO --> MBO
    CPT --> MPT
    CBO --> RP
    CBO --> FH
    CBO --> SG
    CPT --> RP
    CPT --> PH
    CPT --> SG
    CBO --> MIL
    CPT --> MIL
    MIL --> SG
    MBO --> RP
    MBO --> FH
    MPT --> RP
    MBO --> BD
    MPT --> BD
    MIL --> BD
    CBO --> VW
    CPT --> VW
    VW --> AS
    CBO -.-> SES
    CPT -.-> SES
    MBO -.-> CFG
    MPT -.-> CFG
```

**Cómo se lee este diagrama:** las flechas continuas son dependencias de uso; las punteadas, servicios de infraestructura que atraviesan todas las capas. Los helpers (`ReglasPortal`, `FinanzasHelper`, `Seguridad`) son **funciones puras**: no tocan la base ni la sesión, y por eso se prueban directamente. `IntentoLoginModel` es la excepción deliberada dentro de la seguridad — necesita persistir los intentos, porque un contador en sesión lo esquiva quien borre su cookie.

| Capa | Responsabilidad única | Lo que **no** puede hacer |
|---|---|---|
| **Puntos de entrada** (`modules/`, `portal/`) | Recibir la petición e instanciar su controlador | Contener lógica: son 10 líneas cada uno |
| **Controladores** | Validar la entrada, orquestar el flujo y elegir la vista | Consultar la base de datos directamente |
| **Reglas de negocio** (`helpers/`) | Decidir según las reglas del negocio. Funciones **puras**: sin base de datos ni sesión | Leer o escribir datos |
| **Modelos** (`models/`) | Único punto de acceso a la base, siempre con consultas preparadas | Imprimir HTML o redirigir |
| **Vistas** (`views/`) | Presentar datos ya calculados y escaparlos con `htmlspecialchars` | Calcular reglas de negocio |
| **Infraestructura** (`config/`, `includes/`) | Sesión, guardián CSRF, conexión, registro de errores y correo | Conocer reglas del dominio |

**Por qué las reglas viven aparte:** `ReglasPortal` es la fuente única del crédito/ñapa, el límite de 48 horas, el cupo semanal y el horario de entrega; `Seguridad` lo es de la política de contraseña y del tratamiento de los códigos de recuperación. Antes esas reglas estaban duplicadas —hasta en cinco sitios en el caso del portal, y en cuatro mínimos de contraseña distintos— con copias que se desviaban entre sí. Al ser funciones puras se prueban directamente, sin base de datos: son las que sostienen buena parte de las 151 pruebas.

**Dónde vive la protección CSRF:** el guardián `requerir_csrf()` se invoca **una vez por método de controlador que procesa POST, antes de resolver qué acción se pidió**. Es una decisión de diseño, no un detalle: colocarlo dentro de cada rama `if (isset($_POST['accion']))` habría dejado la protección a merced de que quien añada una acción nueva se acuerde de repetirla.

### Infraestructura y servicios externos

```mermaid
flowchart LR
    NAV["Navegador"] <--> APP["PHP 8.2<br/>BreadControl"]
    APP <--> BD[("MySQL 8")]
    APP --> SG["SendGrid<br/>correo"]
    APP --> GO["Google OAuth 2.0<br/>acceso de clientes"]
    APP --> OM["Open-Meteo<br/>clima"]
    APP --> NQ["Nequi Negocios<br/>enlace de pago"]
    GH["GitHub Actions<br/>5 verificaciones"] -.->|"despliegue"| DK["Dokploy · VPS<br/>Docker"]
    DK -.-> APP
```

**Patrón:** MVC modular. Cada módulo del back-office y cada página del portal entra por un archivo mínimo que delega en su controlador.

**Método de inventario:** FIFO (*First In, First Out*) — los lotes más antiguos se consumen primero, de forma transaccional y con costeo real por lote.

**Producción:** las unidades producidas se distribuyen por categoría de precio, lo que permite calcular el stock disponible del día como *producido − vendido*.

---

## 🚀 Instalación

### Requisitos
- PHP 8.0 o superior
- MySQL 8.0 o superior
- Servidor web (Apache/Nginx/Hostinger)

### Pasos

1. **Clonar el repositorio e instalar dependencias**
   ```bash
   git clone https://github.com/Manuel151025/breadcontrol.git
   cd breadcontrol
   composer install
   ```

2. **Configurar la base de datos**

   Para un **clon fresco del repositorio** basta con el esquema versionado:
   ```bash
   mysql -u root panaderia_bd < sql/init/01_esquema_base.sql
   ```

   El esquema se compone del dump base **más** las extensiones del portal/flujo de
   pedidos. Ejecuta los scripts **en este orden**:

   1. `sql/panaderia_bd.sql` — dump base (tablas de inventario, producción, ventas, etc.).
   2. `sql/init/02_extensiones_flujo.sql` — columnas del portal en `cliente` + tablas
      `pedido_cliente`, `pedido_cliente_detalle`, `pago_pedido`, `pago_abono` + foreign keys.
      **Solo para bases nuevas/vacías.**

   Para una base de datos **ya existente** (p. ej. el VPS) no uses el paso 2; aplica en
   su lugar los scripts incrementales de `sql/migraciones/` (ver más abajo).

   **Con Docker:** `docker-compose.yml` monta ambos scripts en `docker-entrypoint-initdb.d`
   (`01_base.sql` y `02_extensiones.sql`) y MySQL los ejecuta en orden automáticamente al
   crear un contenedor con volumen vacío — un despliegue fresco levanta el esquema completo
   sin pasos manuales.

   **Migraciones incrementales** (para bases ya desplegadas, en orden por fecha):
   - `sql/migraciones/2026-07-23_01_normalizar_estado_pago_pedido.sql`
   - `sql/migraciones/2026-07-23_02_foreign_keys_flujo_pedido_pago.sql`
   - `sql/migraciones/2026-07-23_03_default_estado_pago_no_aplica.sql`
   - `sql/migraciones/2026-07-23_04_codigo_aprendiz.sql`
   - `sql/migraciones/2026-07-23_05_id_cliente_adso.sql`
   - `sql/migraciones/2026-07-23_06_aprobado_instructor_default_0.sql`

3. **Configurar entorno y credenciales**
   - Crear un archivo `.env` en la raíz (usando `.env.example` como base) y completar:
     * `APP_ENV` (`local` o `production`) y `APP_URL`
     * `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
     * `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
     * `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL`
   - Las variables de entorno **reales** (del sistema, Docker o CI) tienen prioridad sobre `.env`.
   - El link de pago de Nequi se configura desde la app (Configuración → Pagos), no por `.env`.

4. **Acceder al sistema**
   - Abrir en el navegador: `https://tu-dominio.com/login.php`

---

## ⚙️ Configuración inicial en un despliegue nuevo

Además de crear el esquema, un despliegue nuevo necesita estos datos en la tabla
`configuracion` y en `cliente`. **Sin ellos el flujo de pago y el de aprendices no
avanzan** (aunque la app no se rompe: muestra los avisos de "no configurado").

### Pago digital (Nequi) — requisito para cobrar pedidos del portal
Se configura desde **Configuración → Pagos** en el back-office, o directo en la BD:

- `configuracion.nequi_link_pago` — el enlace de pago de Nequi Negocios de la panadería
  (algo como `https://checkout.nequi.wompi.co/l/VPOS_xxxxxxxx`).
- `configuracion.wompi_habilitado = 1` — interruptor del pago digital.

Si falta cualquiera de las dos, el portal muestra correctamente *"La panadería aún no ha
habilitado los pagos digitales"* y **el pago no se puede generar** (no se crea el registro
en `pago_pedido` ni se muestra el enlace).

### Cuenta del instructor ADSO — requisito para el flujo aprendiz→instructor
- `configuracion.id_cliente_adso` — el **id** del cliente que actúa como instructor
  (en producción, `45`). El enrutamiento de los pedidos de aprendiz y la capacidad de
  instructor se resuelven por este id, **nunca por nombre ni por tipo**. Si la clave falta
  o apunta a una cuenta inexistente o inactiva, el portal falla con un mensaje claro (no en
  silencio).
- La **cuenta instructor debe existir, estar activa (`activo = 1`) y tener `usuario` +
  `contrasena_hash`** para poder iniciar sesión en el portal y generar códigos.

### Vinculación de aprendices — por código, no manual
- Los aprendices **se vinculan canjeando el código** que genera el instructor desde la
  pantalla **"Mis aprendices"** (al registrarse o luego desde su perfil). No hay asignación
  manual de instructor: el código es la única vía.

> **Nota sobre las columnas `wompi_*` — NO son código muerto, no borrarlas.**
> `configuracion.wompi_habilitado` es el interruptor del pago digital, y
> `pago_pedido.wompi_link_url` / `wompi_link_id` almacenan el **enlace de pago de Nequi
> Negocios**, que está alojado en `checkout.nequi.wompi.co` (Nequi corre sobre la
> infraestructura de Wompi/Bancolombia). Lo que se retiró fue la *integración por API +
> webhook* de Wompi (que era código muerto); el almacenamiento del enlace estático de Nequi
> sigue en uso.

---

## 📁 Estructura del Proyecto

```
BreadControl/
├── config/
│   ├── app.php              # Configuración general (URL, sesión, timezone)
│   ├── db.php               # Conexión PDO a MySQL
│   ├── env.php              # Carga de variables de entorno (.env)
│   └── logger.php           # Gestor de logs de errores
│
├── controllers/             # Controladores MVC
│   ├── AuthController.php
│   ├── CompraController.php
│   ├── FinanzasController.php
│   ├── portal/              # Controladores del portal por responsabilidad
│   │   ├── PortalControllerBase.php      # Sesión, autorización y canje de código
│   │   ├── PortalAuthController.php      # Login, Google OAuth, registro, perfil
│   │   ├── PortalPedidoController.php    # Dashboard, detalle, crear/cancelar pedido
│   │   ├── PortalPagoController.php      # Pago consolidado (Nequi)
│   │   ├── PortalInstructorController.php# Grupo de aprendices y cupos
│   │   └── PortalExportController.php    # Exportaciones (Excel/PDF)
│   └── ...
│
├── models/                  # Modelos de base de datos
│   ├── AuthModel.php
│   ├── InventarioModel.php
│   ├── IntentoLoginModel.php    # Intentos fallidos de acceso (anti fuerza bruta)
│   ├── PortalClienteModel.php   # Fachada única del portal (usa los traits)
│   ├── portal/              # Traits del modelo del portal por responsabilidad
│   │   ├── CuentaClienteTrait.php    # Registro, perfil, contraseña, Google
│   │   ├── CatalogoPortalTrait.php   # Variedades, productos, categorías
│   │   ├── PedidosPortalTrait.php    # Pedidos, reportes y exportaciones
│   │   ├── PagosPortalTrait.php      # Pagos consolidados y abonos
│   │   └── InstructorPortalTrait.php # Aprendices, códigos, cupos, aprobaciones
│   └── ...
│
├── helpers/                 # Lógica de negocio pura y reutilizable
│   ├── ReglasPortal.php     # Reglas del portal (crédito, 48h, cupo, horario)
│   ├── PedidoHelper.php     # Totales y deudas de pedidos
│   ├── FinanzasHelper.php   # Costo de producción, utilidad e ingresos del portal
│   └── Seguridad.php        # Política de contraseña, hashing del código, umbrales
│
├── includes/
│   ├── sesion.php           # Sesión, guardián CSRF, auto-logout, IP de origen
│   ├── funciones.php        # Helpers (formato, lote FIFO, stock dinámico)
│   └── mailer.php           # Enlace SMTP con PHPMailer
│
├── modules/                 # Puntos de entrada (Entrypoints) por módulo
│   ├── tablero/             # Dashboard principal
│   ├── inventario/          # Gestión de insumos y ajustes
│   ├── produccion/          # Registro de producción y distribución
│   ├── ventas/              # Ventas rápidas y carrito detallado
│   └── ...
│
├── portal/                  # Portal público de clientes, aprendices e instructores
│   ├── index.php            # Login de portal y Google OAuth callback
│   ├── dashboard.php        # Panel de pedidos del cliente
│   ├── nuevo_pedido.php     # Carrito de compras y cupo semanal
│   └── pagar_consolidado.php # Registro del pago y enlace de Nequi
│
├── views/                   # Vistas HTML/CSS/JS organizadas por entidad
│   ├── layouts/             # Cabecera, Navbar y Pie de página comunes
│   ├── inventario/          # Plantillas de CRUD e historial
│   ├── portal/              # Vistas de pedidos y abonos
│   └── ...
│
├── assets/
│   ├── css/                 # Hojas de estilo estructuradas por módulo
│   ├── js/                  # Scripts de interacción del frontend
│   └── img/                 # Recursos gráficos y fotos de variedades
│
├── sql/                     # Migraciones y scripts de base de datos
│   ├── init/                # Esquema base versionado + semilla de CI
│   └── migraciones/         # Scripts incrementales por fecha
│
├── tests/                   # Suite de pruebas PHPUnit
│   ├── Unit/                # Unitarias (sin base de datos)
│   └── Integration/         # Integración (transacción + rollback)
│
├── .github/workflows/       # Integración continua (ci.yml)
├── composer.json            # Metadatos, dependencias y scripts de prueba
├── phpunit.xml              # Configuración de PHPUnit
├── phpstan.neon.dist        # Configuración de PHPStan (nivel 10)
├── login.php                # Inicio de sesión del personal
├── logout.php               # Cierre de sesión
├── recuperar_pin.php        # Recuperación de clave por PIN
├── index.php                # Landing page pública
├── CHANGELOG.md             # Historial de versiones
├── CONTRIBUTING.md          # Guía de contribución
├── LICENSE                  # Licencia MIT
└── README.md
```

---

## 🗃 Base de Datos

### Tablas principales (20+)

| Tabla | Descripción |
|-------|-------------|
| `usuario` | Usuarios del sistema con contraseñas bcrypt, rol y PIN de recuperación |
| `insumo` | Insumos de producción con stock actual y punto de reposición |
| `lote` | Lotes de insumos FIFO con cantidad disponible y precio de entrada |
| `producto` | Catálogo de productos (unidades por tanda) |
| `receta` | Recetas vigentes vinculadas a productos |
| `receta_ingrediente` | Ingredientes por receta con cantidad por tanda y flag de merma |
| `produccion` | Registro histórico de producciones diarias con costeo real |
| `produccion_precio` | Distribución de unidades producidas por categoría de precio |
| `consumo_lote` | Registro de consumo detallado por lote para costeo FIFO |
| `categoria_precio` | Rangos de precios parametrizables para venta rápida |
| `variedad_pan` | Subproductos o variedades de pan con foto |
| `venta` | Registro maestro de ventas (mostrador, tiendas, consumos internos) |
| `venta_detalle` | Detalle estructurado de pedidos por variedad |
| `cliente` | Registro de clientes (mostrador, tiendas, aprendices e instructores) |
| `pedido_cliente` | Solicitudes de pedidos creadas por clientes/aprendices con estado de pedido/pago |
| `pedido_cliente_detalle` | Detalle variedad por variedad de los pedidos de clientes |
| `pago_pedido` | Registro del pago consolidado (link de Nequi) con estado, monto y expiración |
| `pago_abono` | Abonos reales registrados a deudas de pedidos de clientes |
| `proveedor` | Proveedores de insumos y datos de contacto |
| `compra` | Registro de compras con lotes autogenerados |
| `historial_precio` | Registro de variación de precios por insumo y proveedor |
| `gasto` | Egresos operativos diarios del propietario |
| `cierre_dia` | Cuadre de caja diario con utilidades y sugerencias |

---

## 🧪 Pruebas

El proyecto usa **PHPUnit 11** con dos suites (151 pruebas, 291 aserciones):

| Suite | Qué cubre | Requiere BD |
|-------|-----------|-------------|
| **Unitarias** | Reglas de negocio del portal ([ReglasPortal](helpers/ReglasPortal.php)), reglas de seguridad ([Seguridad](helpers/Seguridad.php): política de contraseña y hashing del código de recuperación), helpers de pedidos y finanzas, funciones de formato/sanitización, CSRF, sesión y contraseñas | No |
| **Integración** | AuthModel (login, recuperación), PortalClienteModel (aprobación/rechazo en lote), validación de stock de ventas y generación de lotes, límite de intentos de inicio de sesión e ingresos del portal en los reportes | Sí (MySQL) |

```bash
composer install           # una sola vez
composer test              # suite completa
composer test:unit         # solo unitarias (no necesitan MySQL)
composer test:integracion  # solo integración
vendor/bin/phpstan analyse # análisis estático (nivel 10)
```

- Las pruebas de integración corren dentro de una **transacción con rollback**:
  nunca dejan datos en la base.
- Sin MySQL disponible, la suite de integración **se omite** en lugar de fallar.
- Para apuntar a otra base: `DB_NAME=otra_bd composer test` (las variables de
  entorno reales tienen prioridad sobre `.env`).

---

## 🔁 Integración Continua

Cada push y pull request dispara el workflow [`ci.yml`](.github/workflows/ci.yml)
con 5 verificaciones independientes:

1. **Sintaxis PHP** — `php -l` sobre todos los archivos del proyecto.
2. **Análisis estático** — PHPStan nivel 10 sobre `config/`, `controllers/`,
   `helpers/`, `includes/` y `models/`. Los niveles 1-8 están corregidos en el
   código; los niveles 9-10 se exigen a todo código nuevo, mientras las
   ocurrencias heredadas quedan inventariadas en `phpstan-baseline.neon`
   (834 y bajando; ver [CONTRIBUTING.md](CONTRIBUTING.md) para reducirlas).
3. **Pruebas unitarias** — suite `Unitarias` de PHPUnit.
4. **Pruebas de integración** — suite `Integracion` contra un servicio MySQL 8.0
   real, creado desde el esquema versionado (`sql/init/01_esquema_base.sql`) más
   la semilla mínima (`sql/init/90_semilla_ci.sql`).
5. **Seguridad (Snyk)** — vulnerabilidades conocidas en las dependencias y
   análisis estático de seguridad (SAST) del código. Requiere el secreto
   `SNYK_TOKEN` en el repositorio; si no está configurado, el job se omite
   sin romper el CI. Solo bloquea ante hallazgos de severidad *high*.

---

## 🔒 Seguridad

- **Contraseñas cifradas** con `password_hash()` (bcrypt), bajo una política única
  ([Seguridad](helpers/Seguridad.php)): mínimo 8 caracteres con letra y número.
- **Recuperación segura por PIN** mediante hash bcrypt temporal en el perfil. El
  código de 6 dígitos enviado por correo también se guarda hasheado.
- **Freno a la fuerza bruta** en el inicio de sesión del back-office y del portal:
  5 intentos fallidos por cuenta y 20 por IP en 15 minutos (tabla `intento_login`).
- **Consultas preparadas** (PDO bind parameters) con emulación de prepares desactivada.
- **Prevención XSS** escapando todas las salidas del DOM mediante `htmlspecialchars()`.
- **Prevención CSRF** con inyección y verificación de tokens en todas las peticiones
  POST de mutación de datos, tanto en el portal como en el back-office. El guardián
  (`requerir_csrf()`) se aplica una vez por método de controlador, **antes** de mirar
  qué acción se pidió, de modo que una rama nueva no puede quedar sin proteger.
- **Auto-cierre de sesión** automático por inactividad tras 6 minutos.
- **Configuración de sesión** con atributos `HttpOnly`, `SameSite=Lax` y cookies HTTPS seguras.
- **Soft delete** — los datos críticos se marcan como inactivos en lugar de eliminarse de la BD para conservar referencias.

---

## 📊 Análisis Financiero

| Concepto | Valor |
|----------|-------|
| Inversión total desarrollo | $6,700,000 COP |
| Costo operativo mensual | $62,000 COP |
| Ahorro estimado mensual | $1,154,000 COP |
| Retorno de inversión (ROI) | 5.8 meses |
| Licencias de software | $0 (100% open source) |

---

## 🤝 Contribuir

Las contribuciones son bienvenidas. Lee la [guía de contribución](CONTRIBUTING.md)
para conocer el flujo de trabajo, los estándares de código y commits, y cómo
correr las pruebas antes de abrir un Pull Request. En resumen:

1. Crea una rama desde `master` (`fix/...`, `feat/...`).
2. Acompaña tus cambios con pruebas.
3. Verifica en local: `composer test` + `vendor/bin/phpstan analyse`.
4. Abre el PR: el CI debe pasar en verde sus 4 verificaciones.

El historial de versiones vive en [CHANGELOG.md](CHANGELOG.md)
(versión actual: **1.6.2**, sincronizada con `APP_VERSION` en `config/app.php`).

---

## 👨‍💻 Autor

**Manuel Cardenas Suarez**

- 🎓 SENA — Tecnólogo en Análisis y Desarrollo de Software (ADSO)
- 📍 Florencia, Caquetá, Colombia
- 🔗 GitHub: [@Manuel151025](https://github.com/Manuel151025)

---

## 📄 Licencia

Distribuido bajo la **licencia MIT** — ver el archivo [LICENSE](LICENSE) para el
texto completo. Puedes usar, modificar y distribuir el código citando la autoría.

Este proyecto nació como trabajo académico para el programa **Tecnólogo en
Análisis y Desarrollo de Software** del **SENA** (Servicio Nacional de
Aprendizaje), Centro de Formación Agroindustrial La Angostura, Florencia, Caquetá.

---

<p align="center">
  <strong>BreadControl</strong> · Tu panadería merece ser digital 🍞
</p>
