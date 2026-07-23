-- ============================================================
-- Migracion: registro de aprendices por codigo del instructor
-- Fecha: 2026-07-23
-- Feature: el instructor (cuenta Tienda ADSO) genera un codigo; el aprendiz lo
--          canjea al registrarse o desde su perfil y queda vinculado sin que el
--          instructor confirme uno por uno.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez sobre una BD existente.
-- (En una BD nueva estas estructuras las crea sql/init/02_extensiones_flujo.sql.)
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

-- Fecha en que el cliente se vinculo como aprendiz (para la lista "Mis aprendices").
ALTER TABLE `cliente`
  ADD COLUMN `fecha_aprendiz` datetime DEFAULT NULL;

-- Codigos de invitacion que emite cada instructor.
CREATE TABLE IF NOT EXISTS `codigo_aprendiz` (
  `id_codigo`      int(11)      NOT NULL AUTO_INCREMENT,
  `id_instructor`  int(11)      NOT NULL,
  `codigo`         varchar(16)  NOT NULL,
  `fecha_expira`   datetime     DEFAULT NULL,               -- NULL = sin expiracion
  `usos_maximos`   int(11)      DEFAULT NULL,               -- NULL = sin limite de usos
  `usos_actuales`  int(11)      NOT NULL DEFAULT 0,
  `activo`         tinyint(1)   NOT NULL DEFAULT 1,
  `fecha_creacion` datetime     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_codigo`),
  UNIQUE KEY `uq_codigo_aprendiz` (`codigo`),
  KEY `id_instructor` (`id_instructor`),
  CONSTRAINT `fk_codigo_instructor` FOREIGN KEY (`id_instructor`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET SQL_SAFE_UPDATES = 1;
