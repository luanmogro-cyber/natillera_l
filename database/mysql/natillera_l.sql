-- Natillera La Semilla: Base de datos completa para producción y desarrollo
-- Compatible con Railway, Render, Docker, Laragon y MySQL Workbench
-- Fecha de generación: 2026-11-15 22:04:34
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- --------------------------------------------------------
-- Estructura de tabla: `socios`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `socios` (
  `id` int NOT NULL AUTO_INCREMENT,
  `numero` int DEFAULT NULL,
  `nombre_completo` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  CONSTRAINT `socios_chk_1` CHECK ((`activo` in (0,1)))
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `socios`
INSERT INTO `socios` (`id`, `numero`, `nombre_completo`, `activo`) VALUES
(4, 1, 'Presidente', 1),
(10, 5, 'socio 1', 1),
(11, 6, 'socio 2', 1),
(12, 3, 'secretaria general', 1),
(13, 2, 'tesorera', 1),
(14, 4, 'secretaria actividades', 1);

-- --------------------------------------------------------
-- Estructura de tabla: `usuarios`
-- --------------------------------------------------------
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
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `usuarios`
INSERT INTO `usuarios` (`id`, `socio_id`, `nombre`, `correo`, `password_hash`, `rol`, `activo`, `created_at`) VALUES
(1, 4, 'Presidente', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Presidente', 1, '2026-10-19 20:49:29'),
(10, 12, 'secretaria general', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Secretaria General', 1, '2026-10-29 09:37:47'),
(11, 13, 'tesorera', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Tesorera', 1, '2026-10-29 09:37:47'),
(12, 14, 'secretaria actividades', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Secretaria Actividades', 1, '2026-10-29 09:37:47'),
(13, 10, 'Socio1', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Socio', 1, '2026-10-29 09:37:47'),
(14, 11, 'Socio2', NULL, '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq', 'Socio', 1, '2026-10-29 09:37:47');

-- --------------------------------------------------------
-- Estructura de tabla: `reuniones`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `reuniones` (
  `id` int NOT NULL AUTO_INCREMENT,
  `fecha` date NOT NULL,
  `hora` time NOT NULL,
  `valor_cuota` int NOT NULL,
  `concepto` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Cuota regular',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `reuniones_chk_1` CHECK ((`valor_cuota` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `reuniones`
INSERT INTO `reuniones` (`id`, `fecha`, `hora`, `valor_cuota`, `concepto`, `created_at`) VALUES
(10, '2026-11-01', '18:00:00', 65000, 'Cuota + Ronda', '2026-10-29 09:04:14'),
(11, '2026-10-30', '18:00:00', 55000, 'Cuota regular', '2026-10-29 09:05:09'),
(12, '2026-11-12', '18:00:00', 60000, 'Cuota + Rifa', '2026-11-11 11:19:03');

-- --------------------------------------------------------
-- Estructura de tabla: `llamado_lista`
-- --------------------------------------------------------
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
) ENGINE=InnoDB AUTO_INCREMENT=43 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `llamado_lista`
INSERT INTO `llamado_lista` (`id`, `reunion_id`, `socio_id`, `pago_cuota`, `ahorro_voluntario`, `autoprestamo`, `updated_at`) VALUES
(1, 10, 4, 1, 0, 0, '2026-11-11 12:09:29'),
(2, 10, 10, 1, 0, 0, '2026-11-11 12:09:29'),
(3, 10, 11, 0, 0, 1, '2026-11-11 12:09:29'),
(4, 12, 4, 1, 0, 0, '2026-11-11 11:59:30'),
(5, 12, 13, 0, 0, 1, '2026-11-11 11:59:30'),
(6, 12, 12, 1, 0, 0, '2026-11-11 11:59:30'),
(7, 12, 14, 1, 0, 0, '2026-11-11 11:59:30'),
(8, 12, 10, 1, 0, 0, '2026-11-11 11:59:30'),
(9, 12, 11, 1, 0, 0, '2026-11-11 11:59:30'),
(38, 10, 13, 0, 0, 1, '2026-11-11 12:09:29'),
(39, 10, 12, 1, 0, 0, '2026-11-11 12:09:29'),
(40, 10, 14, 0, 0, 1, '2026-11-11 12:09:29');

-- --------------------------------------------------------
-- Estructura de tabla: `creditos_socio`
-- --------------------------------------------------------
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

-- Datos para la tabla `creditos_socio`
INSERT INTO `creditos_socio` (`socio_id`, `limite`, `tasa_mensual_bp`, `updated_at`) VALUES
(4, 600000, 1000, '2026-11-11 12:22:53'),
(10, 2000000, 1000, '2026-11-15 17:03:50'),
(11, 2000000, 1000, '2026-11-11 11:19:14'),
(12, 2000000, 1000, '2026-11-15 17:03:50'),
(13, 2000000, 1000, '2026-11-11 01:52:29'),
(14, 2000000, 1000, '2026-11-15 17:03:50');

-- --------------------------------------------------------
-- Estructura de tabla: `prestamos`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prestamos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `socio_id` int NOT NULL,
  `prestatario_tercero` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `monto` int NOT NULL,
  `saldo_capital` int NOT NULL DEFAULT '0',
  `tasa_mensual_bp` int NOT NULL,
  `fecha_entrega` date NOT NULL,
  `plazo_meses` int NOT NULL DEFAULT '1',
  `fecha_vencimiento` date DEFAULT NULL,
  `fecha_liquidacion` date DEFAULT NULL,
  `interes_recibido` int NOT NULL DEFAULT '0',
  `mora_recibida` int NOT NULL DEFAULT '0',
  `estado` enum('pendiente','activo','pagado','rechazado') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente',
  `aprobado_por` int DEFAULT NULL,
  `fecha_aprobacion` datetime DEFAULT NULL,
  `observacion_aprobacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `foto_evidencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firma_evidencia` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_prestamos_socio` (`socio_id`),
  CONSTRAINT `fk_prestamos_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT,
  CONSTRAINT `prestamos_chk_1` CHECK ((`monto` > 0)),
  CONSTRAINT `prestamos_chk_2` CHECK (((`tasa_mensual_bp` >= 0) and (`tasa_mensual_bp` <= 5000))),
  CONSTRAINT `prestamos_chk_3` CHECK ((`interes_recibido` >= 0))
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `prestamos`
INSERT INTO `prestamos` (`id`, `socio_id`, `prestatario_tercero`, `monto`, `saldo_capital`, `tasa_mensual_bp`, `fecha_entrega`, `plazo_meses`, `fecha_vencimiento`, `fecha_liquidacion`, `interes_recibido`, `mora_recibida`, `estado`, `aprobado_por`, `fecha_aprobacion`, `observacion_aprobacion`, `foto_evidencia`, `firma_evidencia`, `created_at`) VALUES
(1, 4, NULL, 200001, 200001, 1000, '2026-10-29', 1, '2026-11-29', NULL, 20000, 0, 'activo', NULL, NULL, NULL, NULL, NULL, '2026-10-29 09:03:03'),
(2, 10, NULL, 500001, 0, 1000, '2026-10-29', 1, '2026-11-29', '2026-11-11', 50000, 0, 'pagado', NULL, NULL, NULL, NULL, '/uploads/prestamos/firma_20261111_014915_37dae47d.png', '2026-10-29 09:53:47');

-- --------------------------------------------------------
-- Estructura de tabla: `prestamo_pagos`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `prestamo_pagos` (
  `id` int NOT NULL AUTO_INCREMENT,
  `prestamo_id` int NOT NULL,
  `registrado_por` int NOT NULL,
  `fecha_pago` date NOT NULL,
  `tipo_pago` enum('capital','interes','mora','mixto','liquidacion') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'mixto',
  `abono_capital` int NOT NULL DEFAULT '0',
  `abono_interes` int NOT NULL DEFAULT '0',
  `abono_mora` int NOT NULL DEFAULT '0',
  `total_pagado` int NOT NULL,
  `observacion` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_pp_prestamo` (`prestamo_id`),
  KEY `fk_pp_usuario` (`registrado_por`),
  CONSTRAINT `fk_pp_prestamo` FOREIGN KEY (`prestamo_id`) REFERENCES `prestamos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pp_usuario` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `prestamo_pagos`
INSERT INTO `prestamo_pagos` (`id`, `prestamo_id`, `registrado_por`, `fecha_pago`, `tipo_pago`, `abono_capital`, `abono_interes`, `abono_mora`, `total_pagado`, `observacion`, `created_at`) VALUES
(1, 1, 11, '2026-11-11', 'interes', 0, 20000, 0, 20000, NULL, '2026-11-11 11:47:08'),
(3, 2, 1, '2026-11-11', 'liquidacion', 500001, 50000, 0, 550001, 'Liquidación total del préstamo', '2026-11-11 13:25:20');

-- --------------------------------------------------------
-- Estructura de tabla: `actividades`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actividades` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `fecha` date NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `actividades`
INSERT INTO `actividades` (`id`, `nombre`, `fecha`, `created_at`) VALUES
(1, 'tamalada', '2026-11-11', '2026-11-11 01:50:57'),
(2, 'venta de pollo', '2026-11-11', '2026-11-11 11:52:45'),
(3, 'tamalada', '2026-11-12', '2026-11-12 11:38:04');

-- --------------------------------------------------------
-- Estructura de tabla: `actividad_participantes`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `actividad_participantes` (
  `actividad_id` int NOT NULL,
  `socio_id` int NOT NULL,
  PRIMARY KEY (`actividad_id`,`socio_id`),
  KEY `fk_ap_socio` (`socio_id`),
  CONSTRAINT `fk_ap_actividad` FOREIGN KEY (`actividad_id`) REFERENCES `actividades` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ap_socio` FOREIGN KEY (`socio_id`) REFERENCES `socios` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `actividad_participantes`
INSERT INTO `actividad_participantes` (`actividad_id`, `socio_id`) VALUES
(1, 4),
(2, 4),
(3, 4),
(1, 10),
(2, 10),
(3, 10),
(3, 11),
(1, 13),
(2, 13),
(2, 14),
(3, 14);

-- --------------------------------------------------------
-- Estructura de tabla: `actividad_movimientos`
-- --------------------------------------------------------
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Datos para la tabla `actividad_movimientos`
INSERT INTO `actividad_movimientos` (`id`, `actividad_id`, `tipo`, `concepto`, `valor`, `created_at`) VALUES
(1, 2, 'gasto', 'compra de aliños', 50000, '2026-11-11 11:53:26');

-- --------------------------------------------------------
-- Estructura de tabla: `liquidaciones`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Estructura de tabla: `liquidacion_socios`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Estructura de tabla: `push_subscriptions`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Estructura de tabla: `notificaciones`
-- --------------------------------------------------------
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

-- --------------------------------------------------------
-- Estructura de tabla: `recordatorios_enviados`
-- --------------------------------------------------------
CREATE TABLE IF NOT EXISTS `recordatorios_enviados` (
  `reunion_id` int NOT NULL,
  `horario` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`reunion_id`,`horario`),
  CONSTRAINT `fk_rec_reunion` FOREIGN KEY (`reunion_id`) REFERENCES `reuniones` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
