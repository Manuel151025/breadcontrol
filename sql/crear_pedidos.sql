-- sql/crear_pedidos.sql
-- Este script crea las tablas del módulo de Pedidos y Cobros que faltaban en el volcado original.

CREATE TABLE IF NOT EXISTS `pedido_cliente` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_creador` int(11) DEFAULT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_estimado` decimal(10,2) NOT NULL DEFAULT '0.00',
  `aprobado_instructor` tinyint(1) NOT NULL DEFAULT '1',
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `estado_pago` varchar(20) NOT NULL DEFAULT 'pendiente',
  `id_pago_activo` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_creador` (`id_creador`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pedido_cliente_detalle` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_pedido` int(11) NOT NULL,
  `id_variedad` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `precio_unitario` decimal(10,2) NOT NULL DEFAULT '0.00',
  `napa` tinyint(1) NOT NULL DEFAULT '0',
  `bonificacion` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id_detalle`),
  KEY `id_pedido` (`id_pedido`),
  KEY `id_variedad` (`id_variedad`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pago_pedido` (
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
  PRIMARY KEY (`id_pago`),
  KEY `id_pedido` (`id_pedido`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pago_abono` (
  `id_abono` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `fecha_abono` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_abono`),
  KEY `id_pago` (`id_pago`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
