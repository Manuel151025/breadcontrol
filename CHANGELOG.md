# Changelog — BreadControl

Todos los cambios notables del proyecto se documentan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

## [1.6.1] — 2026-08-03

### Cambiado
- Primera reducción del baseline de nivel 9-10: **886 → 854**. Se declararon
  las formas exactas de fila (`@phpstan-type`) en `PedidosPortalTrait` y
  `CuentaClienteTrait`, con los tipos verificados contra la base real
  (`INT` → `int`, `DECIMAL`/`SUM()` → `string`).

### Añadido
- `CONTRIBUTING.md` documenta cómo seguir reduciendo el baseline: tabla de
  equivalencias SQL → PHP y las dos trampas que cuesta descubrir — PHPStan
  no resuelve intersecciones en los alias de tipo (el alias queda
  inservible en silencio) y escribir la etiqueta dentro del texto en prosa
  del comentario rompe el análisis.

### Nota
- Reducir el resto es trabajo incremental de largo plazo: aproximadamente
  dos tercios de las ocurrencias restantes están en controladores que
  consultan la base directamente, y requieren mover esas consultas al
  modelo antes de poder tiparlas. No condiciona el funcionamiento ni la
  cobertura de pruebas del sistema.

## [1.6.0] — 2026-08-03

### Cambiado
- **PHPStan sube al nivel 10, el máximo de la herramienta.**
  - Los niveles **7 y 8 pasan limpios sin excepciones**: se corrigieron ~30
    problemas reales (`strtotime()` que puede devolver `false` pasado a
    `date()`, `max()` sobre arrays potencialmente vacíos, accesos a filas
    SQL sin comprobar que existan, `preg_replace` recibiendo un array
    manipulado desde el formulario, `session_name()`/`json_encode()` con
    retorno `false`).
  - `redirigir()` y `cerrarSesion()` se declaran `: never`, lo que además
    permitió a PHPStan entender el flujo real y eliminar varios falsos
    positivos de acceso a datos.
  - Los niveles 9-10 quedan activos con `phpstan-baseline.neon`: las 886
    ocurrencias heredadas (casi todas filas SQL `array<string, mixed>`
    pasadas a funciones tipadas) están registradas y contadas, y **todo
    código nuevo debe ser nivel-10 limpio o el CI falla** — verificado
    introduciendo una violación de prueba.

### Añadido
- Exclusión documentada del único falso positivo estructural:
  `PDO::prepare()/query()` declaran `PDOStatement|false`, pero el proyecto
  abre la conexión con `ERRMODE_EXCEPTION`, así que esa rama es inalcanzable.

## [1.5.0] — 2026-08-03

### Cambiado
- **PHPStan sube al nivel 6 (máxima exigencia de tipos)**: los 257 hallazgos
  resueltos en 44 archivos — 28 propiedades tipadas, 91 tipos de retorno,
  4 parámetros y 134 anotaciones PHPDoc `array<...>` en iterables. Los
  métodos que retornan `fetch()` conservan su tipo por PHPDoc
  (`array<string, mixed>|false`) porque un tipo nativo sería incorrecto
  en tiempo de ejecución.

## [1.4.0] — 2026-08-03

### Añadido
- 11 pruebas de integración para compras y cierre del día: merma del 6% en
  harina con lote FIFO, variación de precio en `historial_precio`, agregados
  del cierre aislados por fecha futura y upsert de `guardarCierre`
  (122 pruebas, 248 aserciones en total).

### Cambiado
- CSS/JS extraídos de cuatro vistas más del back-office:
  `recetas/editar_receta.php` (491→173), `pedidos_clientes/index.php`
  (490→313), `cierre/index.php` (449→333) y `gastos/index.php` (442→306).

### Decidido
- PHPStan permanece en nivel 5: el nivel 6 exige 257 correcciones
  (mayormente anotaciones PHPDoc) con retorno bajo; se pospone.

## [1.3.0] — 2026-08-03

### Añadido
- 13 pruebas de integración para el núcleo financiero: cálculo FIFO de lotes,
  registro de producción con costeo real (incluido el lote sintético `EST-`
  para remanentes sin lote) y ventas rápidas con stock del día
  (111 pruebas, 212 aserciones en total).

### Cambiado
- PHPStan sube al **nivel 5** (5 hallazgos de tipos corregidos).
- CSS/JS extraídos de tres vistas más: `portal/detalle_pedido.php`
  (672→472), `finanzas/exportar_pdf.php` (614→290) y
  `portal/nuevo_pedido.php` (609→173, su carrito JS ahora vive en
  `assets/js/portal_nuevo_pedido.js`).

## [1.2.0] — 2026-08-03

### Cambiado
- `PortalClienteModel` (1.298 líneas, 62 métodos) dividido por responsabilidad
  en 5 traits bajo `models/portal/` (cuenta, catálogo, pedidos, pagos e
  instructor). La clase pública sigue siendo una sola — controladores y
  pruebas no cambian — pero cada archivo es pequeño y cohesivo.

## [1.1.0] — 2026-08-03

### Cambiado
- `PortalClienteController` (1.651 líneas, 21 métodos) dividido en 5
  controladores por responsabilidad bajo `controllers/portal/` con una base
  común (`PortalControllerBase`): autenticación/cuenta, pedidos, pago
  consolidado, gestión de instructor y exportaciones. Los 17 puntos de
  entrada de `portal/` ahora instancian el controlador que les corresponde.

## [1.0.0] — 2026-08-03

Primera versión estable formalizada.

### Añadido
- Suite de pruebas con **PHPUnit 11**: 98 pruebas (unitarias + integración con
  transacción y rollback) que cubren autenticación, reglas de negocio del portal,
  validaciones, persistencia y manejo de errores.
- **Integración continua** con GitHub Actions: sintaxis PHP, PHPStan (nivel 4),
  suite unitaria y suite de integración contra MySQL 8.
- Esquema de base de datos versionado (`sql/init/01_esquema_base.sql`) — la BD
  ahora es reconstruible desde un clon del repositorio.
- `helpers/ReglasPortal.php`: fuente única de las reglas de negocio del portal
  (crédito/ñapa, límite de gestión de 48 h, bloqueo por pago del instructor,
  cupo semanal, horario de entrega).
- Licencia **MIT**, guía de contribución (`CONTRIBUTING.md`) y metadatos del
  proyecto en `composer.json`.

### Cambiado
- Vistas extensas divididas por responsabilidad: CSS/JS de Ventas, Compras y
  Dashboard del portal extraídos a `assets/` (p. ej. `views/ventas/index.php`
  pasó de 2.424 a 476 líneas).
- Las variables de entorno reales tienen prioridad sobre `.env` (12-factor).

### Funcionalidad existente consolidada en esta versión
- Inventario FIFO con alertas, producción con costeo real, ventas con
  bonificación/ñapa, recetas, compras, finanzas con PDF, gastos y cierre de caja.
- Portal de clientes con registro tradicional y Google OAuth.
- Flujo educativo aprendiz–instructor con cupo semanal y aprobación de pedidos.
- Pagos con Nequi (link manual) y consolidación de saldos.
