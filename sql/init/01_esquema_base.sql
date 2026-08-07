-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: localhost    Database: panaderia_bd
-- ------------------------------------------------------
-- Server version	10.4.32-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `ajuste_inventario`
--

DROP TABLE IF EXISTS `ajuste_inventario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ajuste_inventario` (
  `id_ajuste` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `cantidad_antes` decimal(12,3) NOT NULL,
  `cantidad_despues` decimal(12,3) NOT NULL,
  `diferencia` decimal(12,3) NOT NULL COMMENT 'Calculado: despues - antes',
  `motivo` varchar(255) NOT NULL,
  `fecha_ajuste` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_ajuste`),
  KEY `fk_ajuste_insumo` (`id_insumo`),
  KEY `fk_ajuste_usuario` (`id_usuario`),
  CONSTRAINT `fk_ajuste_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo` (`id_insumo`),
  CONSTRAINT `fk_ajuste_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de correcciones manuales de stock';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `alerta`
--

DROP TABLE IF EXISTS `alerta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `alerta` (
  `id_alerta` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `tipo` enum('stock_bajo','margen_riesgo','precio_subio','caja_baja') NOT NULL,
  `modulo_origen` varchar(50) NOT NULL COMMENT 'Ej: inventario, finanzas, compras',
  `mensaje` text NOT NULL,
  `estado` enum('activa','atendida','archivada') NOT NULL DEFAULT 'activa',
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `fecha_atencion` datetime DEFAULT NULL,
  `accion_tomada` text DEFAULT NULL,
  PRIMARY KEY (`id_alerta`),
  KEY `idx_alerta_estado` (`estado`,`fecha_generacion`),
  KEY `idx_alerta_usuario` (`id_usuario`,`estado`),
  CONSTRAINT `fk_alerta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centro de alertas del sistema — todas las notificaciones';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `categoria_precio`
--

DROP TABLE IF EXISTS `categoria_precio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `categoria_precio` (
  `id_categoria` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `precio_unitario` decimal(12,2) NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_categoria`)
) ENGINE=InnoDB AUTO_INCREMENT=18 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorías de precio para ventas (Pan $500, Pan $2000, etc.)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cierre_dia`
--

DROP TABLE IF EXISTS `cierre_dia`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cierre_dia` (
  `id_cierre` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha` date NOT NULL,
  `total_ingresos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `total_gastos` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_produccion` decimal(12,2) NOT NULL DEFAULT 0.00,
  `utilidad_bruta` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'total_ingresos - costo_produccion',
  `utilidad_neta` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'utilidad_bruta - total_gastos',
  `sugerencia_produccion` text DEFAULT NULL,
  PRIMARY KEY (`id_cierre`),
  UNIQUE KEY `fecha` (`fecha`),
  KEY `fk_cierre_usuario` (`id_usuario`),
  KEY `idx_cierre_fecha` (`fecha`),
  CONSTRAINT `fk_cierre_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resumen financiero de cada día de operación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cliente`
--

DROP TABLE IF EXISTS `cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cliente` (
  `id_cliente` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `tipo` enum('tienda','mostrador') NOT NULL DEFAULT 'tienda',
  `telefono` varchar(20) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `usuario` varchar(50) DEFAULT NULL,
  `contrasena_hash` varchar(255) DEFAULT NULL,
  `es_aprendiz` tinyint(1) NOT NULL DEFAULT 0,
  `cupo_semanal` decimal(10,2) NOT NULL DEFAULT 20000.00,
  `id_instructor` int(11) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `foto_url` varchar(255) DEFAULT NULL,
  `google_id` varchar(100) DEFAULT NULL,
  `notas` varchar(255) DEFAULT NULL,
  `es_beneficiaria` tinyint(1) NOT NULL DEFAULT 0,
  `pin_recuperacion` varchar(255) DEFAULT NULL,
  `codigo_recuperacion` varchar(255) DEFAULT NULL,
  `codigo_expira` datetime DEFAULT NULL,
  `fecha_aprendiz` datetime DEFAULT NULL,
  PRIMARY KEY (`id_cliente`),
  UNIQUE KEY `google_id` (`google_id`),
  UNIQUE KEY `usuario` (`usuario`),
  UNIQUE KEY `uq_cliente_email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=54 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Clientes fijos (tiendas) y mostrador';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `codigo_aprendiz`
--

DROP TABLE IF EXISTS `codigo_aprendiz`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `codigo_aprendiz` (
  `id_codigo` int(11) NOT NULL AUTO_INCREMENT,
  `id_instructor` int(11) NOT NULL,
  `codigo` varchar(16) NOT NULL,
  `fecha_expira` datetime DEFAULT NULL,
  `usos_maximos` int(11) DEFAULT NULL,
  `usos_actuales` int(11) NOT NULL DEFAULT 0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_codigo`),
  UNIQUE KEY `uq_codigo_aprendiz` (`codigo`),
  KEY `id_instructor` (`id_instructor`),
  CONSTRAINT `fk_codigo_instructor` FOREIGN KEY (`id_instructor`) REFERENCES `cliente` (`id_cliente`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `compra`
--

DROP TABLE IF EXISTS `compra`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `compra` (
  `id_compra` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `cantidad` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,4) NOT NULL,
  `total_pagado` decimal(12,2) NOT NULL COMMENT 'cantidad * precio_unitario',
  `fecha_compra` datetime NOT NULL DEFAULT current_timestamp(),
  `variacion_precio_pct` decimal(7,2) NOT NULL DEFAULT 0.00 COMMENT '% de variación respecto a la compra anterior',
  PRIMARY KEY (`id_compra`),
  KEY `fk_compra_proveedor` (`id_proveedor`),
  KEY `fk_compra_usuario` (`id_usuario`),
  KEY `idx_compra_insumo` (`id_insumo`,`fecha_compra`),
  CONSTRAINT `fk_compra_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo` (`id_insumo`),
  CONSTRAINT `fk_compra_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`),
  CONSTRAINT `fk_compra_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=49 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro histórico de todas las compras de insumos';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `configuracion`
--

DROP TABLE IF EXISTS `configuracion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `configuracion` (
  `id_config` int(11) NOT NULL AUTO_INCREMENT,
  `margen_minimo_pct` decimal(5,2) NOT NULL DEFAULT 30.00 COMMENT 'Porcentaje mínimo de ganancia aceptable',
  `dias_stock_seguridad` int(11) NOT NULL DEFAULT 3 COMMENT 'Días mínimos de stock antes de alertar',
  `pct_merma_harina` decimal(5,2) NOT NULL DEFAULT 6.00 COMMENT 'Porcentaje de merma aplicado a la harina',
  `alerta_variacion_precio` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Variación % de precio que dispara alerta',
  `base_minima_caja` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT 'Saldo mínimo de caja recomendado',
  `nequi_link_pago` varchar(255) DEFAULT NULL,
  `nequi_titular` varchar(100) DEFAULT NULL,
  `wompi_habilitado` tinyint(1) NOT NULL DEFAULT 0,
  `wompi_confirmar_auto` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_actualizacion` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `id_cliente_adso` int(11) DEFAULT NULL,
  PRIMARY KEY (`id_config`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Parámetros globales del negocio — solo una fila';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `consumo_lote`
--

DROP TABLE IF EXISTS `consumo_lote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `consumo_lote` (
  `id_consumo` int(11) NOT NULL AUTO_INCREMENT,
  `id_lote` int(11) NOT NULL,
  `id_produccion` int(11) NOT NULL,
  `cantidad_consumida` decimal(12,4) NOT NULL COMMENT 'Cantidad real sin merma',
  `cantidad_con_merma` decimal(12,4) NOT NULL COMMENT 'Cantidad descontada incluyendo merma',
  `costo_consumo` decimal(12,2) NOT NULL COMMENT 'cantidad_con_merma * precio_unitario_lote',
  `fecha_consumo` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_consumo`),
  KEY `fk_cl_lote` (`id_lote`),
  KEY `fk_cl_produccion` (`id_produccion`),
  CONSTRAINT `fk_cl_lote` FOREIGN KEY (`id_lote`) REFERENCES `lote` (`id_lote`),
  CONSTRAINT `fk_cl_produccion` FOREIGN KEY (`id_produccion`) REFERENCES `produccion` (`id_produccion`)
) ENGINE=InnoDB AUTO_INCREMENT=363 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Trazabilidad FIFO: lotes consumidos por producción';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `gasto`
--

DROP TABLE IF EXISTS `gasto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gasto` (
  `id_gasto` int(11) NOT NULL AUTO_INCREMENT,
  `id_cierre_dia` int(11) DEFAULT NULL,
  `id_usuario` int(11) NOT NULL,
  `categoria` enum('compra','servicio','otro') NOT NULL,
  `descripcion` varchar(255) NOT NULL,
  `valor` decimal(12,2) NOT NULL,
  `fecha_gasto` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_gasto`),
  KEY `fk_gasto_cierre_dia` (`id_cierre_dia`),
  KEY `fk_gasto_usuario` (`id_usuario`),
  CONSTRAINT `fk_gasto_cierre_dia` FOREIGN KEY (`id_cierre_dia`) REFERENCES `cierre_dia` (`id_cierre`),
  CONSTRAINT `fk_gasto_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de todos los gastos del negocio por día';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `historial_precio`
--

DROP TABLE IF EXISTS `historial_precio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `historial_precio` (
  `id_historial` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `id_proveedor` int(11) NOT NULL,
  `id_compra` int(11) NOT NULL,
  `precio` decimal(12,4) NOT NULL,
  `variacion_pct` decimal(7,2) NOT NULL DEFAULT 0.00,
  `fecha_registro` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_historial`),
  KEY `fk_hp_proveedor` (`id_proveedor`),
  KEY `fk_hp_compra` (`id_compra`),
  KEY `idx_historial_insumo` (`id_insumo`,`id_proveedor`,`fecha_registro`),
  CONSTRAINT `fk_hp_compra` FOREIGN KEY (`id_compra`) REFERENCES `compra` (`id_compra`),
  CONSTRAINT `fk_hp_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo` (`id_insumo`),
  CONSTRAINT `fk_hp_proveedor` FOREIGN KEY (`id_proveedor`) REFERENCES `proveedor` (`id_proveedor`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historial de precios para análisis de variación';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `insumo`
--

DROP TABLE IF EXISTS `insumo`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `insumo` (
  `id_insumo` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `unidad_medida` enum('kg','g','L','ml','unidad') NOT NULL,
  `es_harina` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = se aplica merma del 6%',
  `stock_actual` decimal(12,3) NOT NULL DEFAULT 0.000,
  `punto_reposicion` decimal(12,3) NOT NULL DEFAULT 0.000 COMMENT 'Stock mínimo antes de generar alerta',
  `consumo_promedio_diario` decimal(12,3) NOT NULL DEFAULT 0.000 COMMENT 'Se actualiza automáticamente',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_insumo`),
  UNIQUE KEY `nombre` (`nombre`),
  KEY `idx_insumo_activo` (`activo`,`stock_actual`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materias primas e insumos de la panadería';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `intento_login`
--

DROP TABLE IF EXISTS `intento_login`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `intento_login` (
  `id_intento` int(11) NOT NULL AUTO_INCREMENT,
  `ambito` enum('admin','portal') NOT NULL,
  `identificador` varchar(150) NOT NULL COMMENT 'Nombre de usuario tecleado en el intento',
  `ip` varchar(45) DEFAULT NULL COMMENT 'IPv4 o IPv6 de origen',
  `fecha` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_intento`),
  KEY `idx_intento_cuenta` (`ambito`,`identificador`,`fecha`),
  KEY `idx_intento_ip` (`ip`,`fecha`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Intentos fallidos de inicio de sesion (anti fuerza bruta)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `lote`
--

DROP TABLE IF EXISTS `lote`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `lote` (
  `id_lote` int(11) NOT NULL AUTO_INCREMENT,
  `id_insumo` int(11) NOT NULL,
  `id_compra` int(11) DEFAULT NULL COMMENT 'NULL si es lote de apertura inicial',
  `numero_lote` varchar(30) NOT NULL COMMENT 'Ej: HAR-2026-02-25-001',
  `cantidad_inicial` decimal(12,3) NOT NULL,
  `cantidad_disponible` decimal(12,3) NOT NULL,
  `precio_unitario` decimal(12,4) NOT NULL COMMENT 'Precio por unidad de medida al momento de la compra',
  `fecha_ingreso` datetime NOT NULL DEFAULT current_timestamp(),
  `estado` enum('activo','agotado') NOT NULL DEFAULT 'activo',
  PRIMARY KEY (`id_lote`),
  UNIQUE KEY `numero_lote` (`numero_lote`),
  KEY `idx_lote_insumo_estado` (`id_insumo`,`estado`,`fecha_ingreso`) COMMENT 'Clave para algoritmo FIFO',
  CONSTRAINT `fk_lote_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo` (`id_insumo`)
) ENGINE=InnoDB AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lotes de insumos ordenados por fecha para FIFO';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pago_abono`
--

DROP TABLE IF EXISTS `pago_abono`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pago_abono` (
  `id_abono` int(11) NOT NULL AUTO_INCREMENT,
  `id_pago` int(11) NOT NULL,
  `monto` decimal(10,2) NOT NULL,
  `metodo_pago` varchar(50) NOT NULL,
  `nota` varchar(255) DEFAULT NULL,
  `fecha_abono` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_abono`),
  KEY `id_pago` (`id_pago`),
  CONSTRAINT `fk_abono_pago` FOREIGN KEY (`id_pago`) REFERENCES `pago_pedido` (`id_pago`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pago_pedido`
--

DROP TABLE IF EXISTS `pago_pedido`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_pago`),
  KEY `id_pedido` (`id_pedido`),
  CONSTRAINT `fk_pago_pedido` FOREIGN KEY (`id_pedido`) REFERENCES `pedido_cliente` (`id_pedido`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedido_cliente`
--

DROP TABLE IF EXISTS `pedido_cliente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `pedido_cliente` (
  `id_pedido` int(11) NOT NULL AUTO_INCREMENT,
  `id_cliente` int(11) NOT NULL,
  `id_creador` int(11) DEFAULT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_solicitud` datetime NOT NULL DEFAULT current_timestamp(),
  `total_estimado` decimal(10,2) NOT NULL DEFAULT 0.00,
  `aprobado_instructor` tinyint(1) NOT NULL DEFAULT 0,
  `estado` varchar(20) NOT NULL DEFAULT 'pendiente',
  `estado_pago` varchar(20) NOT NULL DEFAULT 'no_aplica',
  `id_pago_activo` int(11) DEFAULT NULL,
  `mensaje_propietario` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_pedido`),
  KEY `id_cliente` (`id_cliente`),
  KEY `id_creador` (`id_creador`),
  CONSTRAINT `fk_ped_cliente` FOREIGN KEY (`id_cliente`) REFERENCES `cliente` (`id_cliente`),
  CONSTRAINT `fk_ped_creador` FOREIGN KEY (`id_creador`) REFERENCES `cliente` (`id_cliente`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `pedido_cliente_detalle`
--

DROP TABLE IF EXISTS `pedido_cliente_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produccion`
--

DROP TABLE IF EXISTS `produccion`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produccion` (
  `id_produccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_receta` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL,
  `cantidad_tandas` decimal(5,1) NOT NULL DEFAULT 1.0,
  `observaciones` varchar(255) DEFAULT NULL,
  `unidades_producidas` int(11) NOT NULL,
  `costo_total` decimal(12,2) NOT NULL DEFAULT 0.00,
  `costo_unitario` decimal(12,4) NOT NULL DEFAULT 0.0000,
  `fecha_produccion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_produccion`),
  KEY `fk_prod_producto` (`id_producto`),
  KEY `fk_prod_receta` (`id_receta`),
  KEY `fk_prod_usuario` (`id_usuario`),
  KEY `idx_produccion_fecha` (`fecha_produccion`),
  CONSTRAINT `fk_prod_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`),
  CONSTRAINT `fk_prod_receta` FOREIGN KEY (`id_receta`) REFERENCES `receta` (`id_receta`),
  CONSTRAINT `fk_prod_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Registro de cada corrida de producción';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `produccion_precio`
--

DROP TABLE IF EXISTS `produccion_precio`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `produccion_precio` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_produccion` int(11) NOT NULL,
  `id_categoria_precio` int(11) NOT NULL,
  `unidades` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `fk_pp_produccion` (`id_produccion`),
  KEY `fk_pp_categoria` (`id_categoria_precio`),
  KEY `idx_prodprecio_cat` (`id_categoria_precio`),
  CONSTRAINT `fk_pp_categoria` FOREIGN KEY (`id_categoria_precio`) REFERENCES `categoria_precio` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_pp_produccion` FOREIGN KEY (`id_produccion`) REFERENCES `produccion` (`id_produccion`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Distribución de unidades producidas por categoría de precio';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `producto`
--

DROP TABLE IF EXISTS `producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `producto` (
  `id_producto` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `categoria` enum('sal','dulce','especial') NOT NULL DEFAULT 'sal',
  `precio_venta` decimal(12,2) NOT NULL DEFAULT 0.00,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `unidad_produccion` varchar(20) NOT NULL DEFAULT 'carro',
  `cantidad_por_tanda` decimal(10,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_producto`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Productos terminados que se venden en la panadería';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proveedor`
--

DROP TABLE IF EXISTS `proveedor`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proveedor` (
  `id_proveedor` int(11) NOT NULL AUTO_INCREMENT,
  `nombre` varchar(100) NOT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `tipo_entrega` enum('domicilio','recogida','visita') NOT NULL DEFAULT 'domicilio',
  `dias_visita` varchar(100) DEFAULT NULL,
  `dias_entrega_promedio` decimal(4,1) NOT NULL DEFAULT 1.0,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_proveedor`),
  UNIQUE KEY `nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proveedores de materias primas';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `proyeccion_caja`
--

DROP TABLE IF EXISTS `proyeccion_caja`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `proyeccion_caja` (
  `id_proyeccion` int(11) NOT NULL AUTO_INCREMENT,
  `id_usuario` int(11) NOT NULL,
  `fecha_generacion` datetime NOT NULL DEFAULT current_timestamp(),
  `semana_proyectada` date NOT NULL COMMENT 'Lunes de la semana proyectada',
  `ingreso_proyectado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `gasto_proyectado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `saldo_proyectado` decimal(12,2) NOT NULL DEFAULT 0.00,
  `alerta_caja_baja` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_proyeccion`),
  KEY `fk_proy_usuario` (`id_usuario`),
  CONSTRAINT `fk_proy_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Proyecciones semanales de flujo de caja';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receta`
--

DROP TABLE IF EXISTS `receta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receta` (
  `id_receta` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) NOT NULL,
  `id_usuario` int(11) NOT NULL COMMENT 'Propietario que la creó',
  `version` int(11) NOT NULL DEFAULT 1,
  `es_vigente` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Solo una receta vigente por producto',
  `es_ajuste_temporal` tinyint(1) NOT NULL DEFAULT 0,
  `fecha_ajuste_temporal` date DEFAULT NULL COMMENT 'Fecha en que aplica el ajuste temporal',
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  `descripcion` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_receta`),
  KEY `fk_receta_producto` (`id_producto`),
  KEY `fk_receta_usuario` (`id_usuario`),
  CONSTRAINT `fk_receta_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`),
  CONSTRAINT `fk_receta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versiones de recetas — permite historial y ajustes temporales';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `receta_ingrediente`
--

DROP TABLE IF EXISTS `receta_ingrediente`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `receta_ingrediente` (
  `id_receta_ing` int(11) NOT NULL AUTO_INCREMENT,
  `id_receta` int(11) NOT NULL,
  `id_insumo` int(11) NOT NULL,
  `cantidad` decimal(12,4) NOT NULL COMMENT 'Cantidad por unidad de producto',
  `unidad` varchar(20) NOT NULL,
  `aplica_merma` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = se suma el % de merma al descontar',
  `notas` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_receta_ing`),
  KEY `fk_ri_receta` (`id_receta`),
  KEY `fk_ri_insumo` (`id_insumo`),
  CONSTRAINT `fk_ri_insumo` FOREIGN KEY (`id_insumo`) REFERENCES `insumo` (`id_insumo`),
  CONSTRAINT `fk_ri_receta` FOREIGN KEY (`id_receta`) REFERENCES `receta` (`id_receta`)
) ENGINE=InnoDB AUTO_INCREMENT=159 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ingredientes y cantidades de cada versión de receta';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario`
--

DROP TABLE IF EXISTS `usuario`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario` (
  `id_usuario` int(11) NOT NULL AUTO_INCREMENT,
  `nombre_usuario` varchar(50) NOT NULL,
  `nombre_completo` varchar(100) NOT NULL,
  `correo_electronico` varchar(150) DEFAULT NULL,
  `telefono` varchar(20) DEFAULT NULL,
  `contrasena_hash` varchar(255) NOT NULL,
  `pin_recuperacion` varchar(255) DEFAULT NULL,
  `codigo_recuperacion` varchar(255) DEFAULT NULL,
  `codigo_expira` datetime DEFAULT NULL,
  `rol` enum('propietario','empleado') NOT NULL DEFAULT 'empleado',
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_usuario`),
  UNIQUE KEY `nombre_usuario` (`nombre_usuario`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Usuarios con acceso al sistema';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `v_insumos_alerta`
--

DROP TABLE IF EXISTS `v_insumos_alerta`;
/*!50001 DROP VIEW IF EXISTS `v_insumos_alerta`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_insumos_alerta` AS SELECT
 1 AS `id_insumo`,
  1 AS `nombre`,
  1 AS `unidad_medida`,
  1 AS `es_harina`,
  1 AS `stock_actual`,
  1 AS `punto_reposicion`,
  1 AS `consumo_promedio_diario`,
  1 AS `activo`,
  1 AS `fecha_creacion` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_inventario_actual`
--

DROP TABLE IF EXISTS `v_inventario_actual`;
/*!50001 DROP VIEW IF EXISTS `v_inventario_actual`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_inventario_actual` AS SELECT
 1 AS `id_insumo`,
  1 AS `nombre`,
  1 AS `unidad_medida`,
  1 AS `es_harina`,
  1 AS `stock_actual`,
  1 AS `punto_reposicion`,
  1 AS `consumo_promedio_diario`,
  1 AS `dias_restantes`,
  1 AS `semaforo` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_lotes_fifo`
--

DROP TABLE IF EXISTS `v_lotes_fifo`;
/*!50001 DROP VIEW IF EXISTS `v_lotes_fifo`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_lotes_fifo` AS SELECT
 1 AS `id_lote`,
  1 AS `id_insumo`,
  1 AS `nombre_insumo`,
  1 AS `numero_lote`,
  1 AS `cantidad_disponible`,
  1 AS `precio_unitario`,
  1 AS `fecha_ingreso` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_margen_productos`
--

DROP TABLE IF EXISTS `v_margen_productos`;
/*!50001 DROP VIEW IF EXISTS `v_margen_productos`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_margen_productos` AS SELECT
 1 AS `id_producto`,
  1 AS `nombre`,
  1 AS `categoria`,
  1 AS `precio_venta`,
  1 AS `costo_unitario`,
  1 AS `margen_pct` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_resumen_financiero_30d`
--

DROP TABLE IF EXISTS `v_resumen_financiero_30d`;
/*!50001 DROP VIEW IF EXISTS `v_resumen_financiero_30d`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_resumen_financiero_30d` AS SELECT
 1 AS `fecha`,
  1 AS `total_ingresos`,
  1 AS `costo_produccion`,
  1 AS `total_gastos`,
  1 AS `utilidad_bruta`,
  1 AS `utilidad_neta` */;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `v_stock_productos_hoy`
--

DROP TABLE IF EXISTS `v_stock_productos_hoy`;
/*!50001 DROP VIEW IF EXISTS `v_stock_productos_hoy`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `v_stock_productos_hoy` AS SELECT
 1 AS `id_producto`,
  1 AS `nombre`,
  1 AS `unidad_produccion`,
  1 AS `producido_hoy`,
  1 AS `vendido_hoy`,
  1 AS `stock_actual` */;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `variedad_pan`
--

DROP TABLE IF EXISTS `variedad_pan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `variedad_pan` (
  `id_variedad` int(11) NOT NULL AUTO_INCREMENT,
  `id_categoria_precio` int(11) NOT NULL,
  `nombre` varchar(100) NOT NULL,
  `imagen` varchar(255) DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT 1,
  `fecha_creacion` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id_variedad`),
  KEY `fk_variedad_categoria` (`id_categoria_precio`),
  CONSTRAINT `fk_variedad_categoria` FOREIGN KEY (`id_categoria_precio`) REFERENCES `categoria_precio` (`id_categoria`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Variedades de pan por categoría de precio (para detallar pedidos grandes)';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `venta`
--

DROP TABLE IF EXISTS `venta`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venta` (
  `id_venta` int(11) NOT NULL AUTO_INCREMENT,
  `id_producto` int(11) DEFAULT NULL,
  `id_categoria_precio` int(11) DEFAULT NULL,
  `tipo_salida` enum('venta','bonificacion','consumo_interno') NOT NULL DEFAULT 'venta',
  `id_cierre_dia` int(11) DEFAULT NULL,
  `id_cliente` int(11) DEFAULT NULL,
  `id_usuario` int(11) DEFAULT NULL,
  `fecha_hora` datetime NOT NULL DEFAULT current_timestamp(),
  `unidades_vendidas` int(11) NOT NULL DEFAULT 0,
  `precio_unitario` decimal(12,2) NOT NULL,
  `total_venta` decimal(12,2) NOT NULL COMMENT 'unidades_vendidas * precio_unitario',
  `unidades_sobrantes` int(11) NOT NULL DEFAULT 0,
  `unidades_bonificacion` int(11) DEFAULT 0,
  PRIMARY KEY (`id_venta`),
  KEY `fk_venta_producto` (`id_producto`),
  KEY `idx_venta_cierre` (`id_cierre_dia`),
  KEY `fk_venta_cliente` (`id_cliente`),
  KEY `fk_venta_usuario` (`id_usuario`),
  KEY `fk_venta_categoria` (`id_categoria_precio`),
  KEY `idx_venta_cat_precio` (`id_categoria_precio`),
  KEY `idx_venta_fecha` (`fecha_hora`),
  CONSTRAINT `fk_venta_categoria` FOREIGN KEY (`id_categoria_precio`) REFERENCES `categoria_precio` (`id_categoria`) ON UPDATE CASCADE,
  CONSTRAINT `fk_venta_cierre_dia` FOREIGN KEY (`id_cierre_dia`) REFERENCES `cierre_dia` (`id_cierre`),
  CONSTRAINT `fk_venta_producto` FOREIGN KEY (`id_producto`) REFERENCES `producto` (`id_producto`),
  CONSTRAINT `fk_venta_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuario` (`id_usuario`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=161 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ventas diarias por producto';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `venta_detalle`
--

DROP TABLE IF EXISTS `venta_detalle`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `venta_detalle` (
  `id_detalle` int(11) NOT NULL AUTO_INCREMENT,
  `id_venta` int(11) NOT NULL,
  `id_variedad` int(11) NOT NULL,
  `cantidad` int(11) NOT NULL DEFAULT 0,
  `napa` int(11) NOT NULL DEFAULT 0,
  `bonificacion` int(11) NOT NULL DEFAULT 0,
  `precio_unitario` decimal(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detalle`),
  KEY `fk_detalle_venta` (`id_venta`),
  KEY `fk_detalle_variedad` (`id_variedad`),
  KEY `idx_ventadet_venta` (`id_venta`),
  CONSTRAINT `fk_detalle_variedad` FOREIGN KEY (`id_variedad`) REFERENCES `variedad_pan` (`id_variedad`) ON UPDATE CASCADE,
  CONSTRAINT `fk_detalle_venta` FOREIGN KEY (`id_venta`) REFERENCES `venta` (`id_venta`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=243 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalle por variedad de pan en pedidos grandes';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Final view structure for view `v_insumos_alerta`
--

/*!50001 DROP VIEW IF EXISTS `v_insumos_alerta`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = latin1 */;
/*!50001 SET character_set_results     = latin1 */;
/*!50001 SET collation_connection      = latin1_swedish_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_insumos_alerta` AS select `insumo`.`id_insumo` AS `id_insumo`,`insumo`.`nombre` AS `nombre`,`insumo`.`unidad_medida` AS `unidad_medida`,`insumo`.`es_harina` AS `es_harina`,`insumo`.`stock_actual` AS `stock_actual`,`insumo`.`punto_reposicion` AS `punto_reposicion`,`insumo`.`consumo_promedio_diario` AS `consumo_promedio_diario`,`insumo`.`activo` AS `activo`,`insumo`.`fecha_creacion` AS `fecha_creacion` from `insumo` where `insumo`.`stock_actual` <= `insumo`.`punto_reposicion` and `insumo`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_inventario_actual`
--

/*!50001 DROP VIEW IF EXISTS `v_inventario_actual`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_inventario_actual` AS select `i`.`id_insumo` AS `id_insumo`,`i`.`nombre` AS `nombre`,`i`.`unidad_medida` AS `unidad_medida`,`i`.`es_harina` AS `es_harina`,`i`.`stock_actual` AS `stock_actual`,`i`.`punto_reposicion` AS `punto_reposicion`,`i`.`consumo_promedio_diario` AS `consumo_promedio_diario`,case when `i`.`consumo_promedio_diario` > 0 then round(`i`.`stock_actual` / `i`.`consumo_promedio_diario`,1) else NULL end AS `dias_restantes`,case when `i`.`stock_actual` <= `i`.`punto_reposicion` then 'critico' when `i`.`stock_actual` <= `i`.`punto_reposicion` * 1.5 then 'alerta' else 'normal' end AS `semaforo` from `insumo` `i` where `i`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_lotes_fifo`
--

/*!50001 DROP VIEW IF EXISTS `v_lotes_fifo`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_lotes_fifo` AS select `l`.`id_lote` AS `id_lote`,`l`.`id_insumo` AS `id_insumo`,`i`.`nombre` AS `nombre_insumo`,`l`.`numero_lote` AS `numero_lote`,`l`.`cantidad_disponible` AS `cantidad_disponible`,`l`.`precio_unitario` AS `precio_unitario`,`l`.`fecha_ingreso` AS `fecha_ingreso` from (`lote` `l` join `insumo` `i` on(`i`.`id_insumo` = `l`.`id_insumo`)) where `l`.`estado` = 'activo' and `l`.`cantidad_disponible` > 0 order by `l`.`id_insumo`,`l`.`fecha_ingreso` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_margen_productos`
--

/*!50001 DROP VIEW IF EXISTS `v_margen_productos`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_margen_productos` AS select `p`.`id_producto` AS `id_producto`,`p`.`nombre` AS `nombre`,`p`.`categoria` AS `categoria`,`p`.`precio_venta` AS `precio_venta`,coalesce(`latest_prod`.`costo_unitario`,0) AS `costo_unitario`,case when `p`.`precio_venta` > 0 and coalesce(`latest_prod`.`costo_unitario`,0) > 0 then round((`p`.`precio_venta` - coalesce(`latest_prod`.`costo_unitario`,0)) / `p`.`precio_venta` * 100,2) else NULL end AS `margen_pct` from (`producto` `p` left join (select `pr`.`id_producto` AS `id_producto`,`pr`.`costo_unitario` AS `costo_unitario` from (`produccion` `pr` join (select `produccion`.`id_producto` AS `id_producto`,max(`produccion`.`id_produccion`) AS `max_id` from `produccion` group by `produccion`.`id_producto`) `latest` on(`pr`.`id_produccion` = `latest`.`max_id`))) `latest_prod` on(`latest_prod`.`id_producto` = `p`.`id_producto`)) where `p`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_resumen_financiero_30d`
--

/*!50001 DROP VIEW IF EXISTS `v_resumen_financiero_30d`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_resumen_financiero_30d` AS select `cd`.`fecha` AS `fecha`,`cd`.`total_ingresos` AS `total_ingresos`,`cd`.`costo_produccion` AS `costo_produccion`,`cd`.`total_gastos` AS `total_gastos`,`cd`.`utilidad_bruta` AS `utilidad_bruta`,`cd`.`utilidad_neta` AS `utilidad_neta` from `cierre_dia` `cd` where `cd`.`fecha` >= curdate() - interval 30 day order by `cd`.`fecha` desc */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `v_stock_productos_hoy`
--

/*!50001 DROP VIEW IF EXISTS `v_stock_productos_hoy`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_unicode_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013  SQL SECURITY DEFINER */
/*!50001 VIEW `v_stock_productos_hoy` AS select `p`.`id_producto` AS `id_producto`,`p`.`nombre` AS `nombre`,`p`.`unidad_produccion` AS `unidad_produccion`,coalesce(`prod`.`producido_hoy`,0) AS `producido_hoy`,coalesce(`vent`.`vendido_hoy`,0) AS `vendido_hoy`,coalesce(`prod`.`producido_hoy`,0) - coalesce(`vent`.`vendido_hoy`,0) AS `stock_actual` from ((`producto` `p` left join (select `produccion`.`id_producto` AS `id_producto`,sum(`produccion`.`unidades_producidas`) AS `producido_hoy` from `produccion` where cast(`produccion`.`fecha_produccion` as date) = curdate() group by `produccion`.`id_producto`) `prod` on(`prod`.`id_producto` = `p`.`id_producto`)) left join (select `venta`.`id_producto` AS `id_producto`,sum(`venta`.`unidades_vendidas`) AS `vendido_hoy` from `venta` where cast(`venta`.`fecha_hora` as date) = curdate() group by `venta`.`id_producto`) `vent` on(`vent`.`id_producto` = `p`.`id_producto`)) where `p`.`activo` = 1 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-08-03 12:12:22
