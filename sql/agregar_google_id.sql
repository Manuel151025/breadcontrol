-- Agrega soporte de login con Google al portal de clientes
-- Requerido por models/PortalClienteModel.php (getClienteByGoogleId, vincularGoogleId, registrarClienteGoogle)

ALTER TABLE `cliente`
  ADD COLUMN `google_id` VARCHAR(100) NULL DEFAULT NULL AFTER `foto_url`,
  ADD UNIQUE KEY `google_id` (`google_id`);
