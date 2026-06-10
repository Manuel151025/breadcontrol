# 🍞 BreadControl

**Sistema de Gestión Integral para Panaderías Artesanales**

BreadControl es una aplicación web diseñada específicamente para digitalizar y optimizar la operación diaria de panaderías artesanales colombianas. Desde el control de inventario hasta el cierre de caja, todo en un solo lugar.

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

- **Inventario en tiempo real** con alertas de stock bajo y método FIFO para insumos
- **Producción inteligente** con descuento automático de insumos y distribución por categoría de precio
- **Ventas por categoría de precio** ($500, $1,000, $2,000, $3,000, $5,000) con precio personalizado
- **Carrito tipo MercadoLibre** para detallar pedidos grandes con fotos de variedades de pan
- **Bonificación 20% automática** para tiendas con distribución detallada por variedad
- **Ñapa configurable** por variedad en pedidos detallados
- **Tres tipos de salida:** Venta (genera ingreso), Bonificación (pan regalado), Consumo interno (empleados)
- **Control de merma** del 6% automático para harina de trigo
- **Cierre del día** con observaciones que aparecen como banner al día siguiente
- **Finanzas** con gráficos, KPIs y exportación a PDF
- **Clima en tiempo real** integrado con API Open-Meteo
- **Responsive** — funciona en PC, tablet y celular
- **Auto-logout** por inactividad (6 minutos)

---

## 📦 Módulos

| # | Módulo | Descripción |
|---|--------|-------------|
| 1 | **Tablero** | KPIs del día, gráfico de ventas 7 días, clima, acciones rápidas, banner de observaciones |
| 2 | **Inventario** | CRUD de insumos, alertas de stock bajo, barras visuales de nivel, eliminación masiva |
| 3 | **Producción** | Registro por tandas, descuento FIFO, distribución por categoría de precio, forzar con stock insuficiente |
| 4 | **Ventas** | Venta rápida + carrito detallado, bonificación tiendas, ñapa, consumo interno |
| 5 | **Recetas** | Catálogo de productos, ingredientes por receta, variedades de pan con imagen |
| 6 | **Compras** | Registro con lotes (MAN-YYYY-MM-DD-NNN), merma automática, alerta de variación de precio >5% |
| 7 | **Finanzas** | Ingresos vs compras, utilidad, margen, gráficos por mes/año, exportar PDF |
| 8 | **Gastos** | Registro de gastos operativos del día, editar y eliminar |
| 9 | **Cierre del día** | Cuadre de caja, observaciones para el día siguiente, historial de cierres |

**Módulos adicionales:**
- **Perfil de usuario** — Datos personales, cambiar contraseña, PIN de recuperación
- **Recuperar contraseña** — Por correo electrónico (PHPMailer SMTP) o PIN de 6 dígitos
- **Gestión de tiendas** — Clientes tipo tienda con bonificación automática
- **Variedades de pan** — CRUD con imagen para detallar pedidos grandes

---

## 🛠 Tecnologías

| Capa | Tecnología |
|------|-----------|
| **Backend** | PHP 8 (procedural) |
| **Base de datos** | MySQL 8 |
| **Frontend** | HTML5, CSS3 (custom, sin framework), JavaScript vanilla |
| **Iconos** | Bootstrap Icons |
| **Fuentes** | Google Fonts (Fraunces, Plus Jakarta Sans, Playfair Display, DM Sans) |
| **Gráficos** | Chart.js (finanzas), CSS bars (tablero) |
| **Email** | PHPMailer 6.9 (SMTP) |
| **Clima** | API Open-Meteo |
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
       │              ┌─────┴─────┐
       │              │  PHPMailer │
       │              │   (SMTP)   │
       │              └───────────┘
       │
  ┌────┴────┐
  │Open-Meteo│
  │  (Clima) │
  └─────────┘
```

**Patrón:** Procedural PHP con separación en módulos (config, includes, modules, views).

**Método de inventario:** FIFO (First In, First Out) — los lotes más antiguos se consumen primero.

**Producción:** Las unidades producidas se distribuyen por categoría de precio para control de stock en ventas.

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

3. **Configurar conexión**
   - Editar `config/db.php` con los datos de tu servidor:
     ```php
     $host = 'localhost';
     $db   = 'tu_base_de_datos';
     $user = 'tu_usuario';
     $pass = 'tu_contraseña';
     ```

4. **Configurar la aplicación**
   - Editar `config/app.php` con la URL de tu proyecto:
     ```php
     define('APP_URL', 'https://tu-dominio.com');
     ```

5. **Configurar email (opcional)**
   - Crear `config/email.php` con los datos SMTP:
     ```php
     define('SMTP_HOST', 'smtp.hostinger.com');
     define('SMTP_PORT', 465);
     define('SMTP_USUARIO', 'noreply@tu-dominio.com');
     define('SMTP_PASSWORD', 'tu_contraseña');
     ```

6. **Instalar PHPMailer (opcional)**
   - Ejecutar `instalar_phpmailer.php` en el navegador
   - O descargar manualmente desde [PHPMailer GitHub](https://github.com/PHPMailer/PHPMailer)

7. **Acceder al sistema**
   - Abrir en el navegador: `https://tu-dominio.com/login.php`

---

## 📁 Estructura del Proyecto

```
BreadControl/
├── config/
│   ├── app.php              # Configuración general (URL, sesión, timezone)
│   ├── db.php               # Conexión PDO a MySQL
│   └── email.php            # Configuración SMTP (opcional)
│
├── includes/
│   ├── sesion.php           # Control de sesión y auto-logout
│   ├── funciones.php        # Funciones auxiliares (formato, redirección)
│   └── PHPMailer/           # Librería PHPMailer (SMTP)
│
├── modules/
│   ├── tablero/             # Dashboard principal
│   ├── inventario/          # Gestión de insumos
│   ├── produccion/          # Registro de producción
│   ├── ventas/              # Ventas rápidas + carrito detallado
│   ├── recetas/             # Productos, recetas y variedades
│   ├── compras/             # Registro de compras con lotes
│   ├── finanzas/            # Reportes financieros
│   ├── gastos/              # Gastos operativos
│   ├── cierre/              # Cierre del día
│   ├── configuracion/       # Perfil de usuario
│   └── proveedores/         # Gestión de proveedores
│
├── views/
│   └── layouts/
│       ├── header.php       # Navbar con reloj, clima, ciudad
│       └── footer.php       # Pie de página y scripts
│
├── assets/
│   ├── css/                 # Estilos (inline en cada módulo)
│   ├── img/                 # Imágenes del sistema
│   │   └── variedades/      # Fotos de variedades de pan
│   └── docs/                # Manual de usuario PDF
│
├── sql/                     # Scripts de migración SQL
├── login.php                # Inicio de sesión
├── logout.php               # Cierre de sesión
├── recuperar_pin.php        # Recuperar contraseña (email/PIN)
├── index.php                # Landing page
└── README.md
```

---

## 🗃 Base de Datos

### Tablas principales (17+)

| Tabla | Descripción |
|-------|-------------|
| `usuario` | Usuarios del sistema con bcrypt, PIN, correo |
| `insumo` | Insumos con stock, punto de reposición, merma |
| `lote` | Lotes FIFO con cantidad disponible y precio |
| `producto` | Productos (Pan de Sal, Pan Grande, etc.) |
| `receta` | Recetas vinculadas a productos |
| `receta_ingrediente` | Ingredientes por receta con cantidad |
| `produccion` | Producciones diarias por tandas |
| `produccion_precio` | Distribución de producción por categoría de precio |
| `consumo_lote` | Registro de consumo FIFO por lote |
| `categoria_precio` | Categorías de precio ($500, $1,000, etc.) |
| `variedad_pan` | Tipos de pan por categoría con imagen |
| `venta` | Registro de ventas/bonificaciones/consumo |
| `venta_detalle` | Detalle de pedidos grandes por variedad |
| `cliente` | Clientes (mostrador y tiendas) |
| `proveedor` | Proveedores de insumos |
| `compra` | Compras con lote generado |
| `historial_precio` | Variación de precios por proveedor |
| `gasto` | Gastos operativos diarios |
| `cierre_dia` | Cierre diario con observaciones |

---

## 🔒 Seguridad

- **Contraseñas cifradas** con `password_hash()` (bcrypt)
- **Consultas preparadas** (PDO) contra SQL injection
- **Protección XSS** con `htmlspecialchars()` en todas las salidas
- **Auto-cierre de sesión** por inactividad (6 minutos)
- **Sesiones configuradas** con `httponly`, `samesite` y duración de 8 horas
- **Soft delete** — los datos nunca se eliminan permanentemente
- **HTTPS** con certificado SSL
- **Recuperación segura** — código temporal de 5 minutos o PIN bcrypt

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
