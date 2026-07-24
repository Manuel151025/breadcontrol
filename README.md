# 🍞 BreadControl

**Sistema de Gestión Integral para Panaderías Artesanales**

BreadControl es una aplicación web diseñada específicamente para digitalizar y optimizar la operación diaria de panaderías artesanales colombianas. Desde el control de inventario hasta el cierre de caja y el portal de pedidos para clientes, todo en un solo lugar.

> 🌐 **Demo en vivo:** [breadcontrol.manuelcardenas.online](https://breadcontrol.manuelcardenas.online)

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
- [Seguridad](#-seguridad)
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
| **Gestión** | Jira (Scrum), GitHub |

---

## 🏗 Arquitectura

```
┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│   Navegador  │────▶│   PHP 8      │────▶│   MySQL 8    │
│  (Frontend)  │◀────│  (Backend)   │◀────│    (BD)      │
└──────────────┘     └──────────────┘     └──────────────┘
       │                    │
       │              ┌─────┴──────────────┐
       │              │  PHPMailer (SMTP)  │
       │              │  Google OAuth SDK  │
       │              └────────────────────┘
       │
   ┌───┴──────────┐
   │ Open-Meteo   │
   │ Nequi (link) │
   └──────────────┘
```

**Patrón:** Arquitectura MVC modular (config, includes, controllers, models, views, modules).

**Principios de Limpieza SOLID y Frontend:**
- **Responsabilidad Única (SRP):** Las vistas no realizan cálculos matemáticos de negocio (como balances de deuda o de consolidación de pedidos), delegando estas responsabilidades a componentes auxiliares en el backend como [PedidoHelper](file:///c:/xampp/htdocs/panaderia/helpers/PedidoHelper.php).
- **Separación de Diseño y Lógica:** Se evita el código "inline". Los estilos CSS de diagramación y scripts interactivos de interacción con el DOM son extraídos hacia recursos estáticos organizados en `/assets/css/` (ej. `main.css`, `pedidos.css`) y `/assets/js/` (ej. `main.js`, `pedidos.js`).

**Método de inventario:** FIFO (First In, First Out) — los lotes más antiguos de ingredientes se consumen primero de forma transaccional.

**Producción:** Las unidades producidas se distribuyen por categoría de precio para el control exacto de stock disponible al vender.

---

## 🚀 Instalación

### Requisitos
- PHP 8.0 o superior
- MySQL 8.0 o superior
- Servidor web (Apache/Nginx/Hostinger)

### Pasos

1. **Clonar el repositorio**
   ```bash
   git clone https://github.com/Manuel151025/BreadControl.git
   ```

2. **Configurar la base de datos**

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

3. **Configurar conexión y entorno**
   - Crear y editar el archivo `config/db.php` con los datos de tu servidor:
     ```php
     $host = 'localhost';
     $db   = 'tu_base_de_datos';
     $user = 'tu_usuario';
     $pass = 'tu_contraseña';
     ```

4. **Configurar la aplicación**
   - Configurar variables de URL en `config/app.php`:
     ```php
     define('APP_URL', 'https://tu-dominio.com');
     ```

5. **Configurar credenciales SMTP y Google OAuth**
   - Crear un archivo `.env` en la raíz (usando `.env.example` como base) y completar las credenciales:
     * `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
     * `SMTP_HOST`, `SMTP_PORT`, `SMTP_USER`, `SMTP_PASS`
     * `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URL`
   - El link de pago de Nequi se configura desde la app (Configuración → Pagos), no por `.env`.

6. **Acceder al sistema**
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
│   ├── PortalClienteController.php
│   └── ...
│
├── models/                  # Modelos de base de datos
│   ├── AuthModel.php
│   ├── InventarioModel.php
│   ├── PortalClienteModel.php
│   └── ...
│
├── includes/
│   ├── sesion.php           # Control de sesión, CSRF y auto-logout
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
├── login.php                # Inicio de sesión del personal
├── logout.php               # Cierre de sesión
├── recuperar_pin.php        # Recuperación de clave por PIN
├── index.php                # Landing page pública
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

## 🔒 Seguridad

- **Contraseñas cifradas** con `password_hash()` (bcrypt).
- **Recuperación segura por PIN** mediante hash bcrypt temporal en el perfil.
- **Consultas preparadas** (PDO bind parameters) con emulación de prepares desactivada.
- **Prevención XSS** escapando todas las salidas del DOM mediante `htmlspecialchars()`.
- **Prevención CSRF** con inyección y verificación de tokens en todas las peticiones POST de mutación de datos.
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

## 👨‍💻 Autor

**Manuel Cardenas Suarez**

- 🎓 SENA — Tecnólogo en Análisis y Desarrollo de Software (ADSO)
- 📍 Florencia, Caquetá, Colombia
- 🔗 GitHub: [@Manuel151025](https://github.com/Manuel151025)

---

## 📄 Licencia

Este proyecto fue desarrollado como trabajo académico para el programa **Tecnólogo en Análisis y Desarrollo de Software** del **SENA** (Servicio Nacional de Aprendizaje), Centro de Formación Agroindustrial La Angostura, Florencia, Caquetá.

Uso exclusivamente educativo y demostrativo.

---

<p align="center">
  <strong>BreadControl</strong> · Tu panadería merece ser digital 🍞
</p>
