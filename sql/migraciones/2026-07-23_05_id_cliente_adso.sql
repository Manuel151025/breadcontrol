-- ============================================================
-- Migracion: enrutamiento ADSO por id (no por nombre)
-- Fecha: 2026-07-23
-- Hallazgo: habia DOS clientes que coincidian con LIKE '%Tienda ADSO%':
--   · id 5  "Tienda ADSO 3142784" (activo=0, sin usuario, con ventas historicas)
--   · id 45 "Tienda ADSO" (activo=1, usuario 'tiendaadso') = cuenta del instructor
-- La busqueda por nombre podia caer en la cuenta equivocada. Se agrega una clave
-- explicita en configuracion y el codigo la lee por id.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez en una BD existente.
-- (En una BD nueva la columna la crea sql/init/02_extensiones_flujo.sql; el valor
--  concreto se configura por entorno.)
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `configuracion`
  ADD COLUMN `id_cliente_adso` INT NULL DEFAULT NULL;

-- Cuenta del instructor ADSO en produccion (id 45, "Tienda ADSO", activa).
UPDATE `configuracion` SET `id_cliente_adso` = 45 WHERE `id_config` = 1;

SET SQL_SAFE_UPDATES = 1;
