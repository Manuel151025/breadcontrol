# 📖 Documentación de la API Interna y Arquitectura

Esta documentación detalla los componentes técnicos principales, funciones auxiliares, vistas de base de datos y la arquitectura MVC personalizada de **BreadControl**, facilitando la extensión de nuevas características sin depender de frameworks externos.

---

## ⚙️ 1. Configuración Central y Entorno

- **`config/env.php`**: Carga las variables de entorno del archivo `.env` en `$_ENV` y define la función `get_env($key, $default = null)` para acceder a ellas de forma segura.
- **`config/db.php`**: Inicializa una única conexión de base de datos compartida (PDO) con la función `getConexion()`. Configura el charset `utf8mb4` y la zona horaria de Colombia (`-05:00`).
- **`config/logger.php`**: Captura excepciones globales no controladas con `set_exception_handler`, registrando la traza de error en los archivos diarios de logs (`logs/app-YYYY-MM-DD.log`) y retornando una página HTML limpia o una respuesta JSON si la petición fue por AJAX/Fetch.

---

## 🧰 2. Funciones Auxiliares (`includes/funciones.php`)

El archivo [includes/funciones.php](file:///c:/xampp/htdocs/panaderia/includes/funciones.php) provee funciones reutilizables globales:

| Función | Parámetros | Retorno | Descripción |
|---------|------------|---------|-------------|
| `formatoPeso` | `float $valor` | `string` | Formatea un número como pesos colombianos (Ej: `$ 85.000`). |
| `formatoInteligente` | `float $valor` | `string` | Quita decimales innecesarios (Ej: `12.00` → `12`, `2.500` → `2,5`). |
| `limpiar` | `string $dato` | `string` | Sanitiza texto quitando etiquetas HTML y previniendo XSS. |
| `generarNumeroLote` | `string $prefijo` | `string` | Genera un código de lote único incremental (Ej: `HAR-2026-03-24-001`). |
| `getStockProducto` | `int $id_producto` | `float` | Retorna las unidades disponibles hoy de un producto usando la vista optimizada `v_stock_productos_hoy`. |
| `validarStockVenta` | `int $id_producto, int $cantidad` | `array` | Valida si hay stock suficiente para registrar una venta, retornando `['ok' => true/false]`. |

---

## 🛡️ 3. Seguridad y Sesiones (`includes/sesion.php`)

- **`requerirPropietario()`**: Asegura que el usuario tenga una sesión activa y cuente con el rol de `propietario`. De lo contrario, redirige al login.
- **`usuarioActual()`**: Devuelve un array con la información del usuario en sesión (`id_usuario`, `nombre`, `rol`).
- **Auto-logout**: Controla la inactividad del usuario (límite de 6 minutos) y destruye la sesión si expira.

---

## 🗄️ 4. Vistas de la Base de Datos

El sistema utiliza vistas para realizar cálculos agregados en tiempo real y evitar lecturas ineficientes en las tablas de transacciones:

1. **`v_stock_productos_hoy`**: Retorna el stock actual de productos del día haciendo `producido_hoy - vendido_hoy`.
2. **`v_margen_productos`**: Compara el precio de venta actual de cada producto contra el costo unitario de su última tanda de producción, calculando el porcentaje de ganancia (`margen_pct`).
3. **`v_insumos_alerta`**: Retorna los insumos activos cuyo stock actual está por debajo del punto de reposición.
4. **`v_inventario_actual`**: Retorna todos los insumos activos con cálculo del semáforo visual de stock (`critico`, `alerta`, `normal`) y estimación de días restantes según el consumo promedio diario.

---

## 📐 5. Patrón MVC Personalizado (Sin Framework)

Para mantener el código ordenado y escalable sin añadir frameworks, el proyecto utiliza un patrón MVC nativo:

```
Navegador (URL) ──▶modules/gastos/index.php (Front Controller del Módulo)
                                  │
                                  ▼
                   controllers/GastoController.php (Acciones y Validación)
                                  │
                  ┌───────────────┴───────────────┐
                  ▼                               ▼
       models/GastoModel.php (Consultas SQL)    views/gastos/index.php (HTML/JS)
```

### Cómo agregar un nuevo módulo en MVC:
1. **Punto de Entrada**: Crea un archivo `index.php` en `modules/tu_modulo/` que llame al controlador.
2. **Modelo**: Crea `models/TuModuloModel.php` para todas las queries SQL.
3. **Controlador**: Crea `controllers/TuModuloController.php` para procesar la lógica de negocio y cargar la vista.
4. **Vista**: Crea `views/tu_modulo/index.php` con el diseño HTML/CSS/JS.

---

## 🧪 6. Pruebas Automatizadas

El proyecto incluye un suite de pruebas nativo escrito en PHP puro para validar las reglas de negocio críticas:

- **Ejecutar pruebas**: Ejecuta en consola:
  ```bash
  php tests/test_runner.php
  ```
- **Ubicación de pruebas**: Todas las pruebas heredan el asertor básico de `tests/test_runner.php` y se ubican en la carpeta `tests/`.
