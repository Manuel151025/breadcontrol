-- ============================================================
-- Migracion: email unico en cliente (causa raiz de cuentas duplicadas por Google)
-- Fecha: 2026-07-24
--
-- El registro tradicional no guardaba email, asi que al entrar con Google no habia
-- clave compartida y se creaba una cuenta duplicada. Se hace obligatorio el email en
-- el registro (en el codigo) y se agrega un indice UNICO sobre cliente.email.
--
-- Los NULL NO cuentan para un indice UNIQUE en MySQL/MariaDB (varios NULL son validos),
-- asi que las 47 cuentas historicas sin email (que no usan el portal) NO se rompen; solo
-- se prohiben dos cuentas con el MISMO correo no nulo.
--
-- Requisito: no debe haber emails NO NULL duplicados antes de correr esto (en produccion
-- solo hay 1 cuenta con email). Si los hubiera, el ALTER falla y hay que limpiarlos.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez en una BD existente.
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `cliente`
  ADD UNIQUE KEY `uq_cliente_email` (`email`);

SET SQL_SAFE_UPDATES = 1;
