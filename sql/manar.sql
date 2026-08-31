-- MariaDB dump 10.19  Distrib 10.4.32-MariaDB, for Win64 (AMD64)
--
-- Host: 127.0.0.1    Database: mrp_manar
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
-- Table structure for table `accesos`
--

DROP TABLE IF EXISTS `accesos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `accesos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `id_perfil` int(10) unsigned NOT NULL,
  `id_item_menu` int(10) unsigned NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_accesos_perfil_item` (`id_perfil`,`id_item_menu`),
  KEY `idx_accesos_item` (`id_item_menu`),
  CONSTRAINT `fk_accesos_item_menu` FOREIGN KEY (`id_item_menu`) REFERENCES `item_menus` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_accesos_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfiles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `empresas`
--

DROP TABLE IF EXISTS `empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `empresas` (
  `id` varchar(36) NOT NULL,
  `nombre` varchar(50) NOT NULL,
  `empresa_wms` int(11) DEFAULT NULL,
  `sap_servidor` varchar(100) DEFAULT NULL,
  `sap_base` varchar(100) DEFAULT NULL,
  `sap_usuario` varchar(100) DEFAULT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `posicion` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `antes_insertar_empresas` BEFORE INSERT ON `empresas` FOR EACH ROW BEGIN
    SET NEW.id = UUID();
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `forecast`
--

DROP TABLE IF EXISTS `forecast`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa` varchar(50) NOT NULL,
  `version` varchar(20) NOT NULL,
  `codigo_cliente` varchar(20) NOT NULL,
  `nombre_cliente` varchar(256) NOT NULL,
  `fecha` date NOT NULL,
  `codigo_producto` varchar(20) NOT NULL,
  `nombre_producto` varchar(256) NOT NULL,
  `cantidad` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forecast_ajustes`
--

DROP TABLE IF EXISTS `forecast_ajustes`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast_ajustes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` varchar(36) DEFAULT NULL,
  `producto_codigo` varchar(50) NOT NULL,
  `iso_year` smallint(4) unsigned NOT NULL,
  `iso_week` tinyint(2) unsigned NOT NULL,
  `semana_inicio` date NOT NULL,
  `cantidad_ajustada` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fa_empresa_producto_semana` (`empresa_id`,`producto_codigo`,`iso_year`,`iso_week`),
  KEY `idx_fa_empresa_producto` (`empresa_id`,`producto_codigo`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forecast_backtest`
--

DROP TABLE IF EXISTS `forecast_backtest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast_backtest` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` varchar(36) DEFAULT NULL,
  `version_presupuesto` varchar(10) DEFAULT NULL,
  `familia` varchar(100) DEFAULT NULL,
  `sub_familia` varchar(100) DEFAULT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `semanas_evaluadas` smallint(4) unsigned DEFAULT NULL,
  `desde` varchar(10) DEFAULT NULL,
  `hasta` varchar(10) DEFAULT NULL,
  `suma_real` decimal(18,4) DEFAULT NULL,
  `suma_forecast` decimal(18,4) DEFAULT NULL,
  `factor` decimal(10,6) DEFAULT NULL,
  `bias_pct` decimal(10,4) DEFAULT NULL,
  `mape` decimal(10,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_bt_empresa_grupo` (`empresa_id`,`familia`,`sub_familia`),
  KEY `idx_bt_empresa` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=90 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `forecast_x_producto`
--

DROP TABLE IF EXISTS `forecast_x_producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `forecast_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` varchar(36) DEFAULT NULL,
  `version_presupuesto` varchar(10) DEFAULT NULL,
  `iso_year` smallint(4) unsigned NOT NULL,
  `iso_week` tinyint(2) unsigned NOT NULL,
  `semana_inicio` date NOT NULL,
  `familia` varchar(100) DEFAULT NULL,
  `sub_familia` varchar(100) DEFAULT NULL,
  `producto_codigo` varchar(50) NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,
  `demanda_forecast` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `forecast_grupo` decimal(18,4) DEFAULT NULL,
  `forecast_grupo_min` decimal(18,4) DEFAULT NULL,
  `forecast_grupo_max` decimal(18,4) DEFAULT NULL,
  `participacion` decimal(9,8) DEFAULT NULL,
  `metodo` varchar(20) DEFAULT NULL,
  `factor` decimal(10,6) DEFAULT NULL,
  `demanda_forecast_corr` decimal(15,4) DEFAULT NULL,
  `presupuesto_grupo` decimal(15,2) DEFAULT NULL,
  `venta_presupuestada` decimal(15,2) DEFAULT NULL,
  `usa_presupuesto` tinyint(1) NOT NULL DEFAULT 0,
  `semanas_historia` smallint(5) unsigned DEFAULT NULL,
  `calidad` varchar(10) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fxp_empresa_semana_producto` (`empresa_id`,`iso_year`,`iso_week`,`producto_codigo`),
  KEY `idx_fxp_semana` (`semana_inicio`),
  KEY `idx_fxp_grupo` (`familia`,`sub_familia`),
  KEY `idx_fxp_producto` (`producto_codigo`),
  KEY `idx_fxp_empresa` (`empresa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=46645 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `iconos`
--

DROP TABLE IF EXISTS `iconos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `iconos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(60) NOT NULL,
  `tipo` enum('bootstrap','personalizado') NOT NULL,
  `valor` varchar(60) NOT NULL,
  `archivo` varchar(255) DEFAULT NULL,
  `coloreable` tinyint(1) NOT NULL DEFAULT 1,
  `posicion` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_iconos_valor` (`valor`),
  KEY `idx_iconos_tipo` (`tipo`),
  KEY `idx_iconos_posicion` (`posicion`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `item_menus`
--

DROP TABLE IF EXISTS `item_menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `item_menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `menu_id` int(10) unsigned NOT NULL,
  `nombre` varchar(60) NOT NULL,
  `icono_id` int(10) unsigned DEFAULT NULL,
  `enlace` varchar(100) DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `posicion` int(10) unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_by` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_item_menus_menu` (`menu_id`),
  KEY `idx_item_menus_posicion` (`posicion`),
  KEY `idx_item_menus_icono` (`icono_id`),
  CONSTRAINT `fk_item_menus_icono` FOREIGN KEY (`icono_id`) REFERENCES `iconos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_item_menus_menu` FOREIGN KEY (`menu_id`) REFERENCES `menus` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `login_intentos`
--

DROP TABLE IF EXISTS `login_intentos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `login_intentos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip` varchar(45) NOT NULL,
  `usuario` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_ip_usuario_fecha` (`ip`,`usuario`,`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `menus`
--

DROP TABLE IF EXISTS `menus`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(30) NOT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `posicion` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `perfiles`
--

DROP TABLE IF EXISTS `perfiles`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `perfiles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nombre` varchar(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `created_by` char(36) DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  `updated_by` char(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perfiles_nombre` (`nombre`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `presupuesto_x_producto`
--

DROP TABLE IF EXISTS `presupuesto_x_producto`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuesto_x_producto` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `anio` smallint(4) unsigned NOT NULL,
  `mes` tinyint(2) unsigned NOT NULL,
  `familia` varchar(100) DEFAULT NULL,
  `sub_familia` varchar(100) DEFAULT NULL,
  `producto_codigo` varchar(50) NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,
  `presupuesto_neto` decimal(15,2) NOT NULL DEFAULT 0.00,
  `neto_pond_producto` decimal(15,2) DEFAULT NULL,
  `neto_pond_grupo` decimal(15,2) DEFAULT NULL,
  `cantidad_pond_producto` decimal(15,4) DEFAULT NULL,
  `participacion` decimal(9,8) DEFAULT NULL,
  `precio_unitario` decimal(15,4) DEFAULT NULL,
  `factor_cumplimiento` decimal(10,6) DEFAULT NULL,
  `venta_estimada` decimal(15,2) DEFAULT NULL,
  `cantidad_estimada` decimal(15,4) NOT NULL DEFAULT 0.0000,
  `cantidad_real` decimal(15,4) DEFAULT NULL,
  `diferencia` decimal(10,4) DEFAULT NULL,
  `alfa` decimal(4,3) unsigned NOT NULL DEFAULT 0.850,
  `ventana_meses` tinyint(2) unsigned NOT NULL DEFAULT 12,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pxp_periodo_producto` (`anio`,`mes`,`producto_codigo`),
  KEY `idx_pxp_periodo` (`anio`,`mes`),
  KEY `idx_pxp_grupo` (`familia`,`sub_familia`),
  KEY `idx_pxp_producto` (`producto_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `presupuestos`
--

DROP TABLE IF EXISTS `presupuestos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `presupuestos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `empresa_id` varchar(36) DEFAULT NULL,
  `version` varchar(10) DEFAULT NULL,
  `anio` smallint(4) unsigned DEFAULT NULL,
  `mes` tinyint(2) unsigned DEFAULT NULL,
  `canal` varchar(100) DEFAULT NULL,
  `sub_canal` varchar(100) DEFAULT NULL,
  `familia` varchar(100) DEFAULT NULL,
  `sub_familia` varchar(100) DEFAULT NULL,
  `venta` decimal(15,2) DEFAULT NULL,
  `mg_porcentaje` decimal(6,2) DEFAULT NULL,
  `mg_neto` decimal(15,2) DEFAULT NULL,
  `pp` decimal(15,4) DEFAULT NULL,
  `kg` decimal(15,4) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_presupuestos_empresa` (`empresa_id`),
  KEY `idx_presupuestos_empresa_version` (`empresa_id`,`version`)
) ENGINE=InnoDB AUTO_INCREMENT=42128 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sap_familias`
--

DROP TABLE IF EXISTS `sap_familias`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sap_familias` (
  `familia_codigo` smallint(6) NOT NULL,
  `familia_nombre` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`familia_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sap_productos_maestros`
--

DROP TABLE IF EXISTS `sap_productos_maestros`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sap_productos_maestros` (
  `producto_codigo` varchar(50) NOT NULL,
  `producto_nombre` varchar(200) DEFAULT NULL,
  `producto_familia_codigo` smallint(6) DEFAULT NULL,
  `producto_activo` char(1) DEFAULT NULL,
  PRIMARY KEY (`producto_codigo`),
  KEY `idx_spm_grupo` (`producto_familia_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sap_sync_log`
--

DROP TABLE IF EXISTS `sap_sync_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sap_sync_log` (
  `tabla` varchar(64) NOT NULL,
  `ultima_sync` datetime NOT NULL,
  `filas` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`tabla`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuario_empresas`
--

DROP TABLE IF EXISTS `usuario_empresas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuario_empresas` (
  `id_usuario` varchar(36) NOT NULL,
  `id_empresa` varchar(36) NOT NULL,
  `por_defecto` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id_usuario`,`id_empresa`),
  KEY `idx_ue_empresa` (`id_empresa`),
  CONSTRAINT `fk_ue_empresa` FOREIGN KEY (`id_empresa`) REFERENCES `empresas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ue_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `usuarios`
--

DROP TABLE IF EXISTS `usuarios`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `usuarios` (
  `id` varchar(36) NOT NULL,
  `usuario` varchar(50) NOT NULL,
  `nombres` varchar(128) NOT NULL,
  `apellidos` varchar(128) NOT NULL,
  `id_perfil` int(10) unsigned DEFAULT NULL,
  `estado` tinyint(1) NOT NULL DEFAULT 1,
  `password_hash` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_usuarios_perfil` (`id_perfil`),
  CONSTRAINT `fk_usuarios_perfil` FOREIGN KEY (`id_perfil`) REFERENCES `perfiles` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_unicode_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `antes_insertar_usuarios` BEFORE INSERT ON `usuarios` FOR EACH ROW BEGIN
    SET NEW.id = UUID();
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `ventas_historicas`
--

DROP TABLE IF EXISTS `ventas_historicas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ventas_historicas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nro_docto` int(10) unsigned DEFAULT NULL,
  `tipo_docto` smallint(5) unsigned DEFAULT NULL,
  `nota_venta` varchar(30) DEFAULT NULL,
  `cond_venta` varchar(60) DEFAULT NULL,
  `fecha_docto` date DEFAULT NULL,
  `rut_cliente` varchar(12) DEFAULT NULL,
  `razon_social` varchar(150) DEFAULT NULL,
  `tipo_cliente` varchar(40) DEFAULT NULL,
  `lista_precio` varchar(40) DEFAULT NULL,
  `cod_ejec` smallint(5) unsigned DEFAULT NULL,
  `ejec_comercial` varchar(80) DEFAULT NULL,
  `f_creacion` date DEFAULT NULL,
  `f_primera_venta` date DEFAULT NULL,
  `f_ultima_venta` date DEFAULT NULL,
  `cod_articulo` varchar(20) DEFAULT NULL,
  `descripcion_articulo` varchar(150) DEFAULT NULL,
  `grupo_articulo` varchar(60) DEFAULT NULL,
  `familia` varchar(60) DEFAULT NULL,
  `sub_familia` varchar(60) DEFAULT NULL,
  `proveedor` varchar(80) DEFAULT NULL,
  `cant_pedido` decimal(12,3) DEFAULT NULL,
  `pr_pedido` decimal(14,2) DEFAULT NULL,
  `subtotal_pedido` decimal(16,2) DEFAULT NULL,
  `cant_docto` decimal(12,3) DEFAULT NULL,
  `pr_base` decimal(14,2) DEFAULT NULL,
  `pct_descuento` decimal(7,2) DEFAULT NULL,
  `subtotal_docto` decimal(16,2) DEFAULT NULL,
  `pr_prom_pond` decimal(14,2) DEFAULT NULL,
  `costo_pr_pp` decimal(16,2) DEFAULT NULL,
  `ganancia_bruta` decimal(16,2) DEFAULT NULL,
  `pct_margen` decimal(7,2) DEFAULT NULL,
  `gramaje` decimal(10,3) DEFAULT NULL,
  `unid_x_caja` smallint(5) unsigned DEFAULT NULL,
  `sociedad` varchar(40) DEFAULT NULL,
  `venta_bruta` decimal(16,2) DEFAULT NULL,
  `descuento` decimal(16,2) DEFAULT NULL,
  `anio` smallint(4) unsigned DEFAULT NULL,
  `mes` tinyint(2) unsigned DEFAULT NULL,
  `pr_vta_prom` decimal(14,2) DEFAULT NULL,
  `version` varchar(8) DEFAULT NULL COMMENT 'formato YYYYV000 (ej. 2024V001)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` varchar(36) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `updated_by` varchar(36) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_vh_fecha` (`fecha_docto`),
  KEY `idx_vh_periodo` (`anio`,`mes`),
  KEY `idx_vh_cliente` (`rut_cliente`),
  KEY `idx_vh_articulo` (`cod_articulo`),
  KEY `idx_vh_docto` (`nro_docto`),
  KEY `idx_vh_version` (`version`)
) ENGINE=InnoDB AUTO_INCREMENT=52718 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
--
-- Data de las tablas de CONFIGURACIÓN (menús, ítems, iconos, perfiles, usuarios,
-- empresas, accesos, usuario_empresas). Las tablas que PROCESAN datos (presupuestos,
-- forecast_*, ventas_historicas, espejos SAP, logs) van SIN data: solo estructura.
--

INSERT INTO `accesos` VALUES (1,1,1,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(2,1,2,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(3,1,3,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(4,1,4,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(5,1,5,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(6,1,6,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(7,1,7,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(8,1,8,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(9,1,9,1,'2026-06-28 22:33:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(28,2,6,1,'2026-06-29 13:22:36','7bec296c-733d-11f1-9d9b-e89c256a6df4',NULL,NULL),(29,2,7,1,'2026-06-29 13:22:36','7bec296c-733d-11f1-9d9b-e89c256a6df4',NULL,NULL),(30,2,8,1,'2026-06-29 13:22:36','7bec296c-733d-11f1-9d9b-e89c256a6df4',NULL,NULL),(31,2,9,1,'2026-06-29 13:22:36','7bec296c-733d-11f1-9d9b-e89c256a6df4',NULL,NULL),(41,1,10,1,'2026-08-17 16:18:21','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(47,1,11,1,'2026-08-19 11:38:08','9e5eeb19-9be1-11f1-9260-e454e8877a9a','2026-08-30 21:38:31','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(64,1,12,1,'2026-08-30 21:38:31','9e5eeb19-9be1-11f1-9260-e454e8877a9a',NULL,NULL);
INSERT INTO `empresas` VALUES ('db2ebe1a-9be9-11f1-9260-e454e8877a9a','Manar Spa',1,'WPC-2024\\ESCDIRECTASQL','CLPRDMANAR','app_manar','empresa_6a8b1dd8731678.03852830.png',1,'2026-08-19 16:20:13','9e5eeb19-9be1-11f1-9260-e454e8877a9a','2026-08-23 16:20:40','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),('dc8d5040-9bef-11f1-9260-e454e8877a9a','Molderil Spa',2,'WPC-2024\\ESCDIRECTASQL','CLPRDVERTIENTES','app_manar','empresa_6a8b1de39893e2.62436342.png',2,'2026-08-19 17:03:12','9e5eeb19-9be1-11f1-9260-e454e8877a9a','2026-08-23 16:20:51','9e5eeb19-9be1-11f1-9260-e454e8877a9a');
INSERT INTO `iconos` VALUES (1,'Usuarios','bootstrap','people-fill',NULL,1,1,'2026-06-28 22:11:49',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(2,'Perfiles','bootstrap','person-badge',NULL,1,2,'2026-06-28 22:13:51',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(3,'Menús','bootstrap','segmented-nav',NULL,1,3,'2026-06-28 22:14:51',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(4,'Ítem Menús','bootstrap','menu-app-fill',NULL,1,4,'2026-06-28 22:15:33',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(5,'Grilla 3x3','bootstrap','grid-3x3-gap-fill',NULL,1,5,'2026-06-28 22:16:20',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(6,'Gráfico Positivo','bootstrap','graph-up-arrow',NULL,1,6,'2026-06-28 22:21:16',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(7,'Pago Efectivo','bootstrap','cash-coin',NULL,1,7,'2026-06-28 22:22:35',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(8,'Signo Dolar','bootstrap','currency-dollar',NULL,1,8,'2026-06-28 22:23:06',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(9,'SAP icono','personalizado','custom-sap-icono','custom-sap-icono.svg',1,10,'2026-06-28 22:24:50','2026-08-19 11:37:39','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(10,'Entra Sale','bootstrap','arrow-down-up',NULL,1,11,'2026-08-17 16:17:30','2026-08-19 11:37:39','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(11,'Edificio','bootstrap','building-fill',NULL,1,9,'2026-08-19 11:37:31','2026-08-19 11:37:39','9e5eeb19-9be1-11f1-9260-e454e8877a9a',NULL),(12,'Camión','bootstrap','truck-flatbed',NULL,1,12,'2026-08-30 21:38:12',NULL,'9e5eeb19-9be1-11f1-9260-e454e8877a9a',NULL);
INSERT INTO `item_menus` VALUES (1,1,'Usuarios',1,'usuarios',1,1,'2026-06-28 22:29:04',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(2,1,'Perfiles',2,'perfiles',1,2,'2026-06-28 22:29:24',NULL,'ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(3,1,'Menus',3,'menus',1,4,'2026-06-28 22:30:05','2026-08-19 11:40:14','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(4,1,'Ítem Menús',4,'item-menus',1,5,'2026-06-28 22:30:28','2026-08-19 11:40:14','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(5,1,'Iconos',5,'iconos',1,6,'2026-06-28 22:30:44','2026-08-19 11:40:14','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(6,2,'Generación de Forecast (En unidades)',6,'forecast',1,3,'2026-06-28 22:31:09','2026-08-21 14:16:17','ceae2b43-67ae-11f1-823d-e89c256a6df4','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(7,2,'Carga Presupuesto (Excel)',7,'presupuesto',1,2,'2026-06-28 22:31:26','2026-08-21 14:14:22','ceae2b43-67ae-11f1-823d-e89c256a6df4','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(8,2,'Ventas Históricas',8,'ventas-historicas',0,5,'2026-06-28 22:31:54','2026-08-21 14:23:16','ceae2b43-67ae-11f1-823d-e89c256a6df4',NULL),(9,2,'Datos ERP - WMS',9,'consultas-sap',1,1,'2026-06-28 22:32:29','2026-08-21 14:07:49','ceae2b43-67ae-11f1-823d-e89c256a6df4','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(10,2,'Pronóstico de Compra',10,'mrp',1,4,'2026-08-17 16:17:58','2026-08-21 14:23:16','ceae2b43-67ae-11f1-823d-e89c256a6df4','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(11,1,'Empresas',11,'empresas',1,3,'2026-08-19 11:36:20','2026-08-19 11:40:14','9e5eeb19-9be1-11f1-9260-e454e8877a9a','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),(12,3,'Proveedores',12,'proveedores',1,1,'2026-08-30 21:37:46','2026-08-30 21:38:23','9e5eeb19-9be1-11f1-9260-e454e8877a9a','9e5eeb19-9be1-11f1-9260-e454e8877a9a');
INSERT INTO `menus` VALUES (1,'Administración',1,1),(2,'Procesos',1,2),(3,'Consultas',1,3);
INSERT INTO `perfiles` VALUES (1,'Administrador','2026-06-28 13:55:33','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-06-28 13:55:40','ceae2b43-67ae-11f1-823d-e89c256a6df4'),(2,'Usuario Prueba','2026-06-28 13:56:02','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-06-28 22:01:58','ceae2b43-67ae-11f1-823d-e89c256a6df4');
INSERT INTO `usuario_empresas` VALUES ('9e5eeb19-9be1-11f1-9260-e454e8877a9a','db2ebe1a-9be9-11f1-9260-e454e8877a9a',1,'2026-08-23 16:07:10','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),('9e5eeb19-9be1-11f1-9260-e454e8877a9a','dc8d5040-9bef-11f1-9260-e454e8877a9a',0,'2026-08-23 16:07:10','9e5eeb19-9be1-11f1-9260-e454e8877a9a');
INSERT INTO `usuarios` VALUES ('9e5eeb19-9be1-11f1-9260-e454e8877a9a','admin','Administración','Manar',1,1,'$2y$10$n4Vt8XHeMYzjAUmeriAUd.p87ujxi27pXx5JfbPdDehYxCNJoVheK','2026-06-14 05:06:31','','2026-08-20 19:01:24','9e5eeb19-9be1-11f1-9260-e454e8877a9a'),('9e5ef670-9be1-11f1-9260-e454e8877a9a','orodriguez','Omar','Rodriguez',2,1,'????????????????????????????????????????????????????????????','2026-06-28 22:05:30','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-06-29 17:23:16','7bec296c-733d-11f1-9d9b-e89c256a6df4'),('9e5ef731-9be1-11f1-9260-e454e8877a9a','wvargas','william','vargas',2,1,'????????????????????????????????????????????????????????????','2026-06-28 22:55:23','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-06-29 00:30:20','ceae2b43-67ae-11f1-823d-e89c256a6df4'),('9e5ef790-9be1-11f1-9260-e454e8877a9a','dmaradona','Diego','Armando',1,1,'????????????????????????????????????????????????????????????','2026-06-28 23:24:22','ceae2b43-67ae-11f1-823d-e89c256a6df4','2026-08-20 19:00:33','9e5eeb19-9be1-11f1-9260-e454e8877a9a');

/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed
