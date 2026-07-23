-- ============================================================
-- Migracion: DEFAULT de pedido_cliente.estado_pago = 'no_aplica'
-- Fecha: 2026-07-23
-- Hallazgo: E3 (el DEFAULT era 'pendiente', asi un pedido recien creado parecia
--           tener un pago pendiente aunque no exista ningun pago_pedido)
--
-- 'no_aplica' = sin pago asociado; 'pendiente' se asigna solo cuando existe un
-- pago_pedido. Las consultas del flujo ya tratan ambos igual, asi que el cambio
-- no altera comportamiento; solo corrige la semantica del estado inicial.
--
-- Portable MariaDB 10.4 / MySQL 8.0.
-- ============================================================

ALTER TABLE pedido_cliente
  MODIFY estado_pago VARCHAR(20) NOT NULL DEFAULT 'no_aplica';

-- Nota: NO se reescriben filas existentes. Un pedido antiguo con estado_pago
-- 'pendiente' e id_pago_activo NULL es equivalente a 'no_aplica' para todas las
-- consultas del flujo; reescribirlo no aporta y aumenta el riesgo. Si se desea
-- normalizar de todos modos, ejecutar (opcional):
--   SET SQL_SAFE_UPDATES = 0;
--   UPDATE pedido_cliente SET estado_pago = 'no_aplica'
--     WHERE estado_pago = 'pendiente' AND id_pago_activo IS NULL;
--   SET SQL_SAFE_UPDATES = 1;
