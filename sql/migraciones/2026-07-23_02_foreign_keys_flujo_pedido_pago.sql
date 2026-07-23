-- ============================================================
-- Migracion: foreign keys de la cadena pedido -> detalle -> pago -> abono
-- Fecha: 2026-07-23
-- Hallazgo: E1 (cero foreign keys en el flujo; borrar cliente/pedido/pago
--           dejaba filas huerfanas y id_pago_activo apuntando a la nada)
--
-- Portable MariaDB 10.4 (local) y MySQL 8.0 (VPS Docker): NO usa IF NOT EXISTS.
-- Ejecutar UNA sola vez. Si un constraint ya existe, primero dropearlo (ver bloque
-- comentado al final) y volver a correr.
--
-- Nota: pedido_cliente.id_pago_activo NO recibe FK a proposito, para evitar una
-- dependencia circular con pago_pedido.id_pedido (se limpia por logica en la app).
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

-- 1. Limpieza defensiva de huerfanos (en local: 0 filas; el VPS puede tener alguna)
DELETE d FROM pedido_cliente_detalle d
  LEFT JOIN pedido_cliente p ON d.id_pedido = p.id_pedido
  WHERE p.id_pedido IS NULL;

DELETE a FROM pago_abono a
  LEFT JOIN pago_pedido pp ON a.id_pago = pp.id_pago
  WHERE pp.id_pago IS NULL;

UPDATE pedido_cliente p
  LEFT JOIN cliente c ON p.id_creador = c.id_cliente
  SET p.id_creador = NULL
  WHERE p.id_creador IS NOT NULL AND c.id_cliente IS NULL;

UPDATE pago_pedido pp
  LEFT JOIN pedido_cliente p ON pp.id_pedido = p.id_pedido
  SET pp.id_pedido = NULL
  WHERE pp.id_pedido IS NOT NULL AND p.id_pedido IS NULL;

-- 2. Crear las foreign keys
ALTER TABLE pedido_cliente_detalle
  ADD CONSTRAINT fk_det_pedido   FOREIGN KEY (id_pedido)   REFERENCES pedido_cliente(id_pedido) ON DELETE CASCADE,
  ADD CONSTRAINT fk_det_variedad FOREIGN KEY (id_variedad) REFERENCES variedad_pan(id_variedad);

ALTER TABLE pedido_cliente
  ADD CONSTRAINT fk_ped_cliente FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
  ADD CONSTRAINT fk_ped_creador FOREIGN KEY (id_creador) REFERENCES cliente(id_cliente) ON DELETE SET NULL;

ALTER TABLE pago_abono
  ADD CONSTRAINT fk_abono_pago FOREIGN KEY (id_pago) REFERENCES pago_pedido(id_pago) ON DELETE CASCADE;

ALTER TABLE pago_pedido
  ADD CONSTRAINT fk_pago_pedido FOREIGN KEY (id_pedido) REFERENCES pedido_cliente(id_pedido) ON DELETE SET NULL;

SET SQL_SAFE_UPDATES = 1;

-- Verificacion:
-- SELECT CONSTRAINT_NAME, TABLE_NAME, REFERENCED_TABLE_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL
--   AND TABLE_NAME IN ('pedido_cliente','pedido_cliente_detalle','pago_pedido','pago_abono');

-- ------------------------------------------------------------
-- Rollback / re-ejecucion (dropear antes de volver a crear):
-- ALTER TABLE pedido_cliente_detalle DROP FOREIGN KEY fk_det_pedido, DROP FOREIGN KEY fk_det_variedad;
-- ALTER TABLE pedido_cliente DROP FOREIGN KEY fk_ped_cliente, DROP FOREIGN KEY fk_ped_creador;
-- ALTER TABLE pago_abono DROP FOREIGN KEY fk_abono_pago;
-- ALTER TABLE pago_pedido DROP FOREIGN KEY fk_pago_pedido;
-- ------------------------------------------------------------
