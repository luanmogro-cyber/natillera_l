	DROP DATABASE IF EXISTS sistema_prestamos;
CREATE DATABASE sistema_prestamos CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sistema_prestamos;

CREATE TABLE roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(30) NOT NULL UNIQUE,
    nombre VARCHAR(50) NOT NULL,
    descripcion VARCHAR(150)
) ENGINE=InnoDB;

CREATE TABLE usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    rol_id INT UNSIGNED NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    nombre_completo VARCHAR(120) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_acceso DATETIME NULL,
    CONSTRAINT fk_usuarios_rol FOREIGN KEY (rol_id) REFERENCES roles(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE configuracion_entidad (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actualizado_por_usuario_id INT UNSIGNED NOT NULL,
    nombre_entidad VARCHAR(120) NOT NULL,
    identificacion_fiscal VARCHAR(30) NOT NULL UNIQUE,
    direccion VARCHAR(150) NOT NULL,
    telefono VARCHAR(30) NOT NULL,
    correo_contacto VARCHAR(100) NOT NULL,
    ruta_logo VARCHAR(255) NULL,
    moneda VARCHAR(10) NOT NULL DEFAULT 'COP',
    tasa_maxima_anual DECIMAL(8, 4) NOT NULL,
    tasa_mora_diaria DECIMAL(8, 4) NOT NULL,
    umbral_dias_mora_bloqueo INT UNSIGNED NOT NULL DEFAULT 30,
    dias_anticipacion_alerta INT UNSIGNED NOT NULL DEFAULT 3,
    frecuencia_respaldo_horas INT UNSIGNED NOT NULL DEFAULT 24,
    ruta_directorio_respaldos VARCHAR(255) NOT NULL,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_configuracion_usuario FOREIGN KEY (actualizado_por_usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE feriados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    configuracion_id INT UNSIGNED NOT NULL,
    registrado_por_usuario_id INT UNSIGNED NOT NULL,
    fecha DATE NOT NULL UNIQUE,
    descripcion VARCHAR(120) NOT NULL,
    recurrente_anual TINYINT(1) NOT NULL DEFAULT 0,
    CONSTRAINT fk_feriados_configuracion FOREIGN KEY (configuracion_id) REFERENCES configuracion_entidad(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_feriados_usuario FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tipos_documento (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(15) NOT NULL UNIQUE,
    nombre VARCHAR(60) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE clientes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tipo_documento_id INT UNSIGNED NOT NULL,
    registrado_por_usuario_id INT UNSIGNED NOT NULL,
    numero_documento VARCHAR(30) NOT NULL UNIQUE,
    nombres VARCHAR(80) NOT NULL,
    apellidos VARCHAR(80) NOT NULL,
    fecha_nacimiento DATE NOT NULL,
    telefono VARCHAR(25) NOT NULL,
    correo VARCHAR(100) NOT NULL UNIQUE,
    direccion VARCHAR(150) NOT NULL,
    ocupacion VARCHAR(80) NOT NULL,
    ingresos_mensuales DECIMAL(15, 2) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_clientes_nombre (nombres, apellidos),
    CONSTRAINT chk_clientes_ingresos CHECK (ingresos_mensuales >= 0),
    CONSTRAINT fk_clientes_tipo_documento FOREIGN KEY (tipo_documento_id) REFERENCES tipos_documento(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_clientes_usuario FOREIGN KEY (registrado_por_usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE metodos_amortizacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(60) NOT NULL,
    descripcion VARCHAR(150) NULL
) ENGINE=InnoDB;

CREATE TABLE periodicidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL,
    dias_equivalentes INT UNSIGNED NOT NULL
) ENGINE=InnoDB;

CREATE TABLE estados_prestamo (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE prestamos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo_prestamo VARCHAR(30) NOT NULL UNIQUE,
    cliente_id INT UNSIGNED NOT NULL,
    usuario_creador_id INT UNSIGNED NOT NULL,
    configuracion_id INT UNSIGNED NOT NULL,
    metodo_amortizacion_id INT UNSIGNED NOT NULL,
    periodicidad_id INT UNSIGNED NOT NULL,
    estado_prestamo_id INT UNSIGNED NOT NULL,
    monto_prestado DECIMAL(15, 2) NOT NULL,
    tasa_interes DECIMAL(8, 4) NOT NULL,
    modalidad_tasa ENUM('ANUAL', 'MENSUAL') NOT NULL,
    numero_cuotas INT UNSIGNED NOT NULL,
    tipo_comision_seguro ENUM('NINGUNO', 'PORCENTAJE', 'VALOR_FIJO') NOT NULL DEFAULT 'NINGUNO',
    valor_comision_seguro DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    tasa_mora_diaria_aplicada DECIMAL(8, 4) NOT NULL,
    fecha_registro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_desembolso DATE NOT NULL,
    total_intereses_proyectados DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_comisiones_proyectadas DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    total_pagar_proyectado DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    saldo_capital_actual DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    dias_mora_actual INT UNSIGNED NOT NULL DEFAULT 0,
    INDEX idx_prestamos_cliente (cliente_id),
    INDEX idx_prestamos_estado (estado_prestamo_id),
    CONSTRAINT chk_prestamos_monto CHECK (monto_prestado > 0),
    CONSTRAINT chk_prestamos_cuotas CHECK (numero_cuotas > 0),
    CONSTRAINT fk_prestamos_cliente FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_usuario FOREIGN KEY (usuario_creador_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_configuracion FOREIGN KEY (configuracion_id) REFERENCES configuracion_entidad(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_metodo FOREIGN KEY (metodo_amortizacion_id) REFERENCES metodos_amortizacion(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_periodicidad FOREIGN KEY (periodicidad_id) REFERENCES periodicidades(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prestamos_estado FOREIGN KEY (estado_prestamo_id) REFERENCES estados_prestamo(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE estados_cuota (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE cuotas_amortizacion (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    prestamo_id INT UNSIGNED NOT NULL,
    estado_cuota_id INT UNSIGNED NOT NULL,
    numero_cuota INT UNSIGNED NOT NULL,
    fecha_vencimiento_original DATE NOT NULL,
    fecha_vencimiento_ajustada DATE NOT NULL,
    capital_programado DECIMAL(15, 2) NOT NULL,
    interes_programado DECIMAL(15, 2) NOT NULL,
    comision_seguro_programado DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    cuota_total DECIMAL(15, 2) NOT NULL,
    saldo_capital_posterior DECIMAL(15, 2) NOT NULL,
    capital_pagado DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    interes_pagado DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    comision_seguro_pagado DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    mora_acumulada DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    mora_pagada DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    fecha_pago_completo DATETIME NULL,
    UNIQUE KEY uk_prestamo_cuota (prestamo_id, numero_cuota),
    INDEX idx_cuotas_vencimiento (fecha_vencimiento_ajustada, estado_cuota_id),
    CONSTRAINT fk_cuotas_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cuotas_estado FOREIGN KEY (estado_cuota_id) REFERENCES estados_cuota(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE tipos_pago (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE metodos_pago (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    codigo VARCHAR(20) NOT NULL UNIQUE,
    nombre VARCHAR(40) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE pagos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    numero_comprobante VARCHAR(30) NOT NULL UNIQUE,
    prestamo_id INT UNSIGNED NOT NULL,
    usuario_cajero_id INT UNSIGNED NOT NULL,
    tipo_pago_id INT UNSIGNED NOT NULL,
    metodo_pago_id INT UNSIGNED NOT NULL,
    fecha_pago DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    monto_total_recibido DECIMAL(15, 2) NOT NULL,
    monto_aplicado_mora DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    monto_aplicado_interes DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    monto_aplicado_comision DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    monto_aplicado_capital DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    saldo_capital_restante DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    observaciones VARCHAR(255) NULL,
    INDEX idx_pagos_prestamo (prestamo_id),
    INDEX idx_pagos_fecha (fecha_pago),
    CONSTRAINT chk_pagos_monto CHECK (monto_total_recibido > 0),
    CONSTRAINT fk_pagos_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_usuario FOREIGN KEY (usuario_cajero_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_tipo FOREIGN KEY (tipo_pago_id) REFERENCES tipos_pago(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_pagos_metodo FOREIGN KEY (metodo_pago_id) REFERENCES metodos_pago(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE detalle_pagos_cuota (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pago_id INT UNSIGNED NOT NULL,
    cuota_id INT UNSIGNED NOT NULL,
    abonado_mora DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    abonado_interes DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    abonado_comision DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    abonado_capital DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
    UNIQUE KEY uk_pago_cuota (pago_id, cuota_id),
    CONSTRAINT fk_detalle_pago FOREIGN KEY (pago_id) REFERENCES pagos(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_detalle_cuota FOREIGN KEY (cuota_id) REFERENCES cuotas_amortizacion(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE historial_respaldos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    configuracion_id INT UNSIGNED NOT NULL,
    ejecutado_por_usuario_id INT UNSIGNED NULL,
    fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo_ejecucion ENUM('AUTOMATICO', 'MANUAL', 'RESTAURACION') NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    tamano_bytes BIGINT UNSIGNED NOT NULL DEFAULT 0,
    estado ENUM('EXITOSO', 'FALLIDO') NOT NULL,
    mensaje_resultado VARCHAR(255) NULL,
    CONSTRAINT fk_respaldos_configuracion FOREIGN KEY (configuracion_id) REFERENCES configuracion_entidad(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_respaldos_usuario FOREIGN KEY (ejecutado_por_usuario_id) REFERENCES usuarios(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;