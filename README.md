# 🍞 BreadControl

**Sistema de Gestión Integral para Panaderías Artesanales**

BreadControl es una aplicación web diseñada específicamente para digitalizar y optimizar la operación diaria de panaderías artesanales colombianas. Desde el control de inventario hasta el cierre de caja y el portal de pedidos para clientes, todo en un solo lugar.

> 🌐 **Demo en vivo:** [breadcontrol.adso.pro](https://breadcontrol.adso.pro)

---

## 📋 Tabla de Contenido

- [Características](#-características)
- [Módulos](#-módulos)
- [Tecnologías](#-tecnologías)
- [Arquitectura](#-arquitectura)
- [Instalación](#-instalación)
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
- **Integración de Pagos (Wompi):** Pagos de saldos de pedidos de forma consolidada mediante PSE o Nequi con webhook idempotente.
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
| 12 | **Pasarela de Pagos (Wompi)** | Pagos unificados/consolidados por PSE o Nequi, webhook idempotente para abonos y conciliación automática |

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
| **Pagos** | Pasarela Wompi de Bancolombia (Widget + Webhook) |
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
   │ Wompi Widget │
   └──────────────┘
```

**Patrón:** Arquitectura MVC modular (config, includes, controllers, models, views, modules).

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
   - Crear una base de datos en MySQL
   - Importar el archivo `sql/panaderia_bd.sql`

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
     * `WOMPI_PUBLIC_KEY`, `WOMPI_PRIVATE_KEY`, `WOMPI_INTEGRITY_KEY`

6. **Acceder al sistema**
   - Abrir en el navegador: `https://tu-dominio.com/login.php`

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
│   └── wompi_webhook.php    # Callback de aprobación de pasarela Wompi
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
| `pago_pedido` | Referencias de pago vinculadas a la pasarela Wompi con estado y expiración |
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
