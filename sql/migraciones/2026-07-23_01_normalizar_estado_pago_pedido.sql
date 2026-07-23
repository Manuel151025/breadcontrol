-- ============================================================
-- Migracion: normalizar pago_pedido.estado al enum canonico en MAYUSCULAS
-- Fecha: 2026-07-23
-- Hallazgo: B3 (doble vocabulario de estados de pago)
--
-- Antes coexistian valores en minusculas (escritos por el webhook de Wompi, ya
-- retirado) y en MAYUSCULAS (back-office). Esta migracion unifica todo a
-- MAYUSCULAS: PENDING / APPROVED / PARTIAL / EXPIRED / VOIDED.
--
-- NO toca pedido_cliente.estado_pago (columna distinta, vocabulario en minusculas
-- del que depende la UI: no_aplica/pendiente/parcial/aprobado/rechazado/expirado).
--
-- Ejecutable en MySQL Workbench o CLI. Idempotente (correr 2 veces no hace dano).
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

UPDATE pago_pedido SET estado = 'PENDING'  WHERE estado IN ('pendiente', 'pending', 'PENDIENTE');
UPDATE pago_pedido SET estado = 'APPROVED' WHERE estado IN ('aprobado', 'approved', 'APROBADO');
UPDATE pago_pedido SET estado = 'PARTIAL'  WHERE estado IN ('parcial', 'partial');
UPDATE pago_pedido SET estado = 'EXPIRED'  WHERE estado IN ('expirado', 'expired');
UPDATE pago_pedido SET estado = 'VOIDED'   WHERE estado IN ('anulado', 'voided', 'rechazado', 'declined', 'DECLINED');

SET SQL_SAFE_UPDATES = 1;

-- Verificacion (debe devolver solo valores del enum en MAYUSCULAS):
-- SELECT estado, COUNT(*) FROM pago_pedido GROUP BY estado;
