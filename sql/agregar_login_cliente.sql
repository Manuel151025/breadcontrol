-- Agrega soporte de login tradicional (usuario/contrasena) al portal de clientes
-- Requerido por models/PortalClienteModel.php e includes/sesion.php
-- (la BD del VPS nunca tuvo estas columnas, solo la local las tenia)

ALTER TABLE `cliente`
  ADD COLUMN `usuario` VARCHAR(50) NULL DEFAULT NULL AFTER `fecha_creacion`,
  ADD COLUMN `contrasena_hash` VARCHAR(255) NULL DEFAULT NULL AFTER `usuario`,
  ADD UNIQUE KEY `usuario` (`usuario`);
