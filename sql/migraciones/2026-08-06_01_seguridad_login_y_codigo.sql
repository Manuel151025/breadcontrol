-- ============================================================
-- Migracion: endurecimiento del inicio de sesion y del codigo de recuperacion
-- Fecha: 2026-08-06
--
-- Dos cambios acoplados a la tanda de seguridad:
--
-- 1) `codigo_recuperacion` pasa a guardarse HASHEADO (bcrypt), igual que el PIN.
--    Un hash bcrypt ocupa 60 caracteres y la columna era varchar(10), asi que hay
--    que ensancharla ANTES de desplegar el codigo nuevo o el UPDATE truncaria el
--    hash y ningun codigo validaria. Aplica a las dos tablas de usuarios:
--    `usuario` (back-office) y `cliente` (portal).
--
--    Los codigos vigentes en el momento de correr esto quedan en texto plano y ya
--    no validaran: expiran solos en 5-10 minutos y quien este a mitad del flujo
--    solo tiene que pedir uno nuevo. Por eso se limpian aqui explicitamente.
--
-- 2) Nueva tabla `intento_login`: registra los intentos fallidos para bloquear
--    temporalmente por cuenta (5 en 15 min) y por IP (20 en 15 min). Se persiste
--    en base de datos y no en sesion porque un atacante controla su cookie.
--    El propio codigo purga las filas de mas de un dia.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez en una BD existente.
-- ============================================================

SET SQL_SAFE_UPDATES = 0;

ALTER TABLE `usuario`
  MODIFY `codigo_recuperacion` varchar(255) DEFAULT NULL;

ALTER TABLE `cliente`
  MODIFY `codigo_recuperacion` varchar(255) DEFAULT NULL;

UPDATE `usuario` SET `codigo_recuperacion` = NULL, `codigo_expira` = NULL
  WHERE `codigo_recuperacion` IS NOT NULL;

UPDATE `cliente` SET `codigo_recuperacion` = NULL, `codigo_expira` = NULL
  WHERE `codigo_recuperacion` IS NOT NULL;

CREATE TABLE IF NOT EXISTS `intento_login` (
  `id_intento` int(11) NOT NULL AUTO_INCREMENT,
  `ambito` enum('admin','portal') NOT NULL,
  `identificador` varchar(150) NOT NULL COMMENT 'Nombre de usuario tecleado en el intento',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 o IPv6 de origen',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_intento`),
  KEY `idx_intento_cuenta` (`ambito`, `identificador`, `fecha`),
  KEY `idx_intento_ip` (`ip`, `fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Intentos fallidos de inicio de sesion (anti fuerza bruta)';

SET SQL_SAFE_UPDATES = 1;
