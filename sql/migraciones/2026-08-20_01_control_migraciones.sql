-- ============================================================
-- Migracion: tabla de control de versiones de esquema
-- Fecha: 2026-08-20
--
-- Hasta hoy no habia forma de preguntarle a la base que migraciones tenia
-- aplicadas. Para responder "¿esta produccion al dia?" habia que exportar la
-- estructura de los dos lados y compararla a mano; el 2026-08-20 eso costo
-- cuatro comandos, dos idas y vueltas y una falsa alarma (dos tablas parecian
-- distintas y solo cambiaba el orden de las columnas, porque MySQL 8 y MariaDB
-- ordenan el guion bajo al reves).
--
-- Es el hallazgo C1 de la auditoria de julio.
--
-- Con esta tabla, la pregunta se responde con:
--     php scripts/migraciones.php
--
-- SE PUEDE EJECUTAR EN CUALQUIER BASE YA EXISTENTE. Las nueve migraciones
-- anteriores se dan por aplicadas a proposito: todas lo estan tanto en local
-- como en produccion, y un despliegue nuevo parte de sql/init/01_esquema_base.sql,
-- que es un volcado del esquema real y ya las lleva dentro.
--
-- Portable MariaDB 10.4 / MySQL 8.0. Ejecutar UNA sola vez.
-- ============================================================

CREATE TABLE IF NOT EXISTS `migracion` (
  `id_migracion` int(11) NOT NULL AUTO_INCREMENT,
  `archivo`      varchar(255) NOT NULL COMMENT 'Nombre del archivo en sql/migraciones/',
  -- Nulo en las heredadas: se dieron por aplicadas sin poder verificar con que
  -- contenido. En las que se registren de aqui en adelante guarda el MD5 del
  -- archivo, lo que permite avisar si alguien edita una migracion ya aplicada.
  `checksum`     char(32) DEFAULT NULL COMMENT 'MD5 del archivo al aplicarse',
  `aplicada_en`  datetime NOT NULL DEFAULT current_timestamp(),
  `nota`         varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_migracion`),
  UNIQUE KEY `uk_migracion_archivo` (`archivo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Que migraciones tiene aplicadas esta base de datos';

-- Las nueve anteriores, mas esta misma.
-- INSERT IGNORE para que volver a ejecutar el archivo no falle ni duplique.
INSERT IGNORE INTO `migracion` (`archivo`, `checksum`, `nota`) VALUES
  ('2026-07-23_01_normalizar_estado_pago_pedido.sql',   NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-23_02_foreign_keys_flujo_pedido_pago.sql',  NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-23_03_default_estado_pago_no_aplica.sql',   NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-23_04_codigo_aprendiz.sql',                 NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-23_05_id_cliente_adso.sql',                 NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-23_06_aprobado_instructor_default_0.sql',   NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-07-24_01_email_unico_cliente.sql',             NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-08-06_01_seguridad_login_y_codigo.sql',        NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-08-06_02_eliminar_id_tienda_destino.sql',      NULL, 'Heredada: aplicada antes de existir este control'),
  ('2026-08-20_01_control_migraciones.sql',             NULL, 'Se registra a si misma');

SELECT CONCAT('Control de migraciones listo: ', COUNT(*), ' registradas.') AS resultado FROM `migracion`;
