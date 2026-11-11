-- Natillera La Semilla: schema and data export for MySQL Workbench
-- Source database: natillera_l
-- Exported: 2026-10-22 21:21:43 UTC
-- Sensitive backup: includes account password hashes and application data.
CREATE DATABASE IF NOT EXISTS `natillera_l_import` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `natillera_l_import`;
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- Table: actividad_movimientos
CREATE TABLE IF NOT EXISTS `actividad_movimientos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `actividad_id` int NOT NULL,
  `tipo` enum('ingreso','gasto') COLLATE utf8mb4_unicode_ci NOT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `valor` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_am_actividad` (`actividad_id`),
  CONSTRAINT `fk_am_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `actividad_movimientos_chk_1` CHECK ((`valor` > 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: actividad_participantes
CREATE TABLE IF NOT EXISTS `actividad_participantes` (
  `actividad_id` int NOT NULL,
  `socio_id` int NOT NULL,
  PRIMARY KEY (`actividad_id`,`socio_id`),
  KEY `fk_ap_socio` (`socio_id`),
  CONSTRAINT `fk_ap_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: actividades
CREATE TABLE IF NOT EXISTS `actividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: creditos_socio
CREATE TABLE IF NOT EXISTS `creditos_socio` (
  `socio_id` int NOT NULL,
  `limite` int NOT NULL DEFAULT '2000000',
  `tasa_mensual_bp` int NOT NULL DEFAULT '1000',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`socio_id`),
  CONSTRAINT `fk_creditos_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE CASCADE,
  CONSTRAINT `creditos_socio_chk_1` CHECK ((`limite` >= 0)),
  CONSTRAINT `creditos_socio_chk_2` CHECK (((`tasa_mensual_bp` >= 0) and (`tasa_mensual_bp` <= 5000)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: liquidacion_socios
CREATE TABLE IF NOT EXISTS `liquidacion_socios` (
  `liquidacion_id` int NOT NULL,
  `socio_id` int NOT NULL,
  `ahorro_base` int NOT NULL,
  `utilidad_repartida` int NOT NULL,
  PRIMARY KEY (`liquidacion_id`,`socio_id`),
  KEY `fk_ls_socio` (`socio_id`),
  CONSTRAINT `fk_ls_liq` FOREIGN KEY (`liquidacion_id`) REFERENCES `liquidaciones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ls_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `liquidacion_socios_chk_1` CHECK ((`ahorro_base` >= 0)),
  CONSTRAINT `liquidacion_socios_chk_2` CHECK ((`utilidad_repartida` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: liquidaciones
CREATE TABLE IF NOT EXISTS `liquidaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ano` int NOT NULL,
  `total_ahorros` int NOT NULL,
  `total_intereses` int NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ano` (`ano`),
  CONSTRAINT `liquidaciones_chk_1` CHECK ((`total_ahorros` >= 0)),
  CONSTRAINT `liquidaciones_chk_2` CHECK ((`total_intereses` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: llamado_lista
CREATE TABLE IF NOT EXISTS `llamado_lista` (
  `id` int NOT NULL AUTO_INCREMENT,
  `reunion_id` int NOT NULL,
  `socio_id` int NOT NULL,
  `pago_cuota` tinyint(1) NOT NULL DEFAULT '0',
  `ahorro_voluntario` int NOT NULL DEFAULT '0',
  `autoprestamo` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_llamado` (`reunion_id`,`socio_id`),
  KEY `fk_llamado_socio` (`socio_id`),
  CONSTRAINT `fk_llamado_reunion` FOREIGN KEY (`reunion_id`) REFERENCES `reuniones` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_llamado_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `llamado_lista_chk_1` CHECK ((`pago_cuota` in (0,1))),
  CONSTRAINT `llamado_lista_chk_2` CHECK ((`ahorro_voluntario` >= 0)),
  CONSTRAINT `llamado_lista_chk_3` CHECK ((`autoprestamo` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: notificaciones
CREATE TABLE IF NOT EXISTS `notificaciones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titulo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `mensaje` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `socio_id` int DEFAULT NULL,
  `creador_id` int NOT NULL,
  `enviados` int NOT NULL DEFAULT '0',
  `fallidos` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_noti_socio` (`socio_id`),
  KEY `fk_noti_creador` (`creador_id`),
  CONSTRAINT `fk_noti_creador` FOREIGN KEY (`creador_id`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_noti_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: prestamos
CREATE TABLE IF NOT EXISTS `prestamos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `socio_id` int NOT NULL,
  `prestatario_tercero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` int NOT NULL,
  `tasa_mensual_bp` int NOT NULL,
  `fecha_entrega` date NOT NULL,
  `fecha_liquidacion` date DEFAULT NULL,
  `interes_recibido` int NOT NULL DEFAULT '0',
  `estado` enum('activo','pagado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'activo',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prestamos_socio` (`socio_id`),
  CONSTRAINT `fk_prestamos_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `prestamos_chk_1` CHECK ((`monto` > 0)),
  CONSTRAINT `prestamos_chk_2` CHECK (((`tasa_mensual_bp` >= 0) and (`tasa_mensual_bp` <= 5000))),
  CONSTRAINT `prestamos_chk_3` CHECK ((`interes_recibido` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: push_subscriptions
CREATE TABLE IF NOT EXISTS `push_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `usuario_id` int NOT NULL,
  `endpoint` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `p256dh` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `auth` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `content_encoding` enum('aes128gcm','aesgcm') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'aes128gcm',
  `expiration_time` bigint DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_endpoint` (`endpoint`(512)),
  KEY `fk_push_usuario` (`usuario_id`),
  CONSTRAINT `fk_push_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: recordatorios_enviados
CREATE TABLE IF NOT EXISTS `recordatorios_enviados` (
  `reunion_id` int NOT NULL,
  `horario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reunion_id`,`horario`),
  CONSTRAINT `fk_rec_reunion` FOREIGN KEY (`reunion_id`) REFERENCES `reuniones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: reuniones
CREATE TABLE IF NOT EXISTS `reuniones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `valor_cuota` int NOT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cuota regular',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `reuniones_chk_1` CHECK ((`valor_cuota` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Exported rows: 0

-- Table: socios
CREATE TABLE IF NOT EXISTS `socios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre_completo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  CONSTRAINT `socios_chk_1` CHECK ((`activo` in (0,1)))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `socios` (`id`, `nombre_completo`, `activo`) VALUES (4, 'Presidente', 1);
-- Exported rows: 1

-- Table: usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `socio_id` int DEFAULT NULL,
  `nombre` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `correo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rol` enum('Presidente','Tesorera','Secretaria Actividades','Secretaria General','Socio') COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `usuarios_nombre_unico` (`nombre`),
  UNIQUE KEY `socio_id` (`socio_id`),
  UNIQUE KEY `correo` (`correo`),
  CONSTRAINT `fk_usuarios_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE SET NULL,
  CONSTRAINT `usuarios_chk_1` CHECK ((`activo` in (0,1)))
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO `usuarios` (`id`, `socio_id`, `nombre`, `correo`, `password_hash`, `rol`, `activo`, `created_at`) VALUES (1, 4, 'Presidente', NULL, '$2y$10$IaoGT1y.uDVrnMVuQPYb0uD3JKAijEjlIChm.L7lfHNfdyl40klbm', 'Presidente', 1, '2026-10-19 20:49:29');
-- Exported rows: 1

SET FOREIGN_KEY_CHECKS = 1;
