-- ============================================================
-- sql/init/02_extensiones_flujo.sql
-- Extensiones de esquema que el dump base sql/panaderia_bd.sql NO incluye:
--   * columnas del portal en `cliente` (login, aprendiz/instructor, google)
--   * tablas del flujo pedido/pago (pedido_cliente, *_detalle, pago_pedido, pago_abono)
--   * foreign keys de la cadena y default correcto de estado_pago
--
-- Se ejecuta AUTOMATICAMENTE por Docker (docker-entrypoint-initdb.d) despues del
-- dump base, SOLO en un volumen de datos vacio (primer arranque del contenedor).
-- Resuelve E1/E2/C1: antes un despliegue Docker fresco levantaba una BD sin estas
-- columnas/tablas y el portal de clientes reventaba con "Unknown column/table".
--
-- IMPORTANTE: este archivo asume una BD RECIEN creada por el dump base (las columnas
-- y tablas NO existen todavia). Para una BD ya existente NO uses este archivo: aplica
-- los scripts de sql/migraciones/ y sql/agregar_*.sql, que estan pensados para eso.
--
-- Portable MySQL 8.0 (VPS) / MariaDB 10.4.
-- ============================================================

-- 1. Columnas del portal que faltan en `cliente` -------------------------------
ALTER TABLE `cliente`
  ADD COLUMN `usuario`         varchar(50)    DEFAULT NULL,
  ADD COLUMN `contrasena_hash` varchar(255)   DEFAULT NULL,
  ADD COLUMN `es_aprendiz`     tinyint(1)     NOT NULL DEFAULT 0,
  ADD COLUMN `cupo_semanal`    decimal(10,2)  NOT NULL DEFAULT 20000.00,
  ADD COLUMN `id_instructor`   int(11)        DEFAULT NULL,
  ADD COLUMN `email`           varchar(150)   DEFAULT NULL,
  ADD COLUMN `foto_url`        varchar(255)   DEFAULT NULL,
  ADD COLUMN `google_id`       varchar(100)   DEFAULT NULL,
  ADD UNIQUE KEY `uq_cliente_usuario` (`usuario`),
  ADD UNIQUE KEY `uq_cliente_google` (`google_id`),
  ADD KEY `fk_cliente_instructor` (`id_instructor`),
  ADD CONSTRAINT `fk_cliente_instructor` FOREIGN KEY (`id_instructor`) REFERENCES `cliente` (`id_cliente`) ON DELETE SET NULL;

-- 2. Tablas del flujo pedido -> detalle -> pago -> abono -----------------------
CREATE TABLE `pedido_cliente` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_creador` int(11) DEFAULT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_estimado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aprobado_instructor` tinyint(1) NOT NULL DEFAULT 1,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `estado_pago` varchar(20) NOT NULL DEFAULT 'no_aplica',
  `id_pago_activo` int(11) DEFAULT NULL,
  `mensaje_propietario` varchar(255) DEFAULT NULL,
  `id_tienda_destino` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_creador` (`id_creador`),
  KEY `id_tienda_destino` (`id_tienda_destino`),
  CONSTRAINT `fk_ped_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_ped_creador` FOREIGN KEY (`id_creador`) REFERENCES `cliente` (`id_cliente`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pedido_cliente_detalle` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_variedad` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `napa` tinyint(1) NOT NULL DEFAULT 0,
  `bonificacion` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_detalle`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_variedad` (`id_variedad`),
  CONSTRAINT `fk_det_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedido_cliente` (`id_pedido`) ON DELETE CASCADE,
  CONSTRAINT `fk_det_variedad` FOREIGN KEY (`id_variedad`) REFERENCES `variedad_pan` (`id_variedad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pago_pedido` (
  `id_pago` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) DEFAULT NULL,
  `referencia` varchar(100) DEFAULT NULL,
  `wompi_link_id` varchar(100) DEFAULT NULL,
  `wompi_link_url` varchar(255) DEFAULT NULL,
  `monto` decimal(10,2) NOT NULL,
  `monto_centavos` int(11) NOT NULL,
  `estado` varchar(20) NOT NULL DEFAULT 'PENDING',
  `fecha_expiracion` datetime DEFAULT NULL,
  `fecha_pago` datetime DEFAULT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `metodo_pago` varchar(50) DEFAULT NULL,
  `fecha_creacion` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_pago`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedido_cliente` (`id_pedido`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `pago_abono` (
  `id_abono` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `fecha_abono` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_abono`),
  KEY `id_pago` (`id_pago`),
  CONSTRAINT `fk_abono_pago` FOREIGN KEY (`id_pago`) REFERENCES `pago_pedido` (`id_pago`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
