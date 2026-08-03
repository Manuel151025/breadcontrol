# Changelog — BreadControl

Todos los cambios notables del proyecto se documentan en este archivo.
El formato sigue [Keep a Changelog](https://keepachangelog.com/es/1.1.0/)
y el versionado sigue [SemVer](https://semver.org/lang/es/).

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
