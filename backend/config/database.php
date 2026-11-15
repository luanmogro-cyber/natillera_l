<?php
declare(strict_types=1);

// ─── Cargar variables de entorno desde .env local si existe ─────────────────
$envPath = __DIR__ . '/../.env';
if (is_file($envPath)) {
    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            if (!array_key_exists($key, $_ENV) && !array_key_exists($key, $_SERVER)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

// ─── Función auxiliar para resolver variables de entorno en cualquier plataforma ──
$firstEnv = static function (array $keys, ?string $default = null): ?string {
    foreach ($keys as $key) {
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return (string) $val;
        }
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return (string) $_ENV[$key];
        }
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return (string) $_SERVER[$key];
        }
    }
    return $default;
};

// ─── Detección de conexión: URL completa (Railway / Render) o variables individuales ─
$dbUrl = $firstEnv(['MYSQL_URL', 'MYSQL_PRIVATE_URL', 'MYSQL_PUBLIC_URL', 'DATABASE_URL']);

if ($dbUrl !== null && str_starts_with($dbUrl, 'mysql')) {
    $parts    = parse_url($dbUrl) ?: [];
    $dbHost   = $parts['host'] ?? '127.0.0.1';
    $dbPort   = isset($parts['port']) ? (string) $parts['port'] : '3306';
    $dbName   = ltrim((string) ($parts['path'] ?? '/natillera_l'), '/');
    $dbUser   = isset($parts['user']) ? urldecode($parts['user']) : 'root';
    $dbPass   = isset($parts['pass']) ? urldecode($parts['pass']) : '';
} else {
    $dbHost = $firstEnv(['MYSQLHOST', 'MYSQL_HOST', 'DB_HOST'], '127.0.0.1');
    $dbPort = $firstEnv(['MYSQLPORT', 'MYSQL_PORT', 'DB_PORT'], '3306');
    $dbName = $firstEnv(['MYSQLDATABASE', 'MYSQL_DATABASE', 'DB_NAME'], 'railway');
    $dbUser = $firstEnv(['MYSQLUSER', 'MYSQL_USER', 'DB_USER'], 'root');
    $dbPass = $firstEnv(['MYSQLPASSWORD', 'MYSQL_PASSWORD', 'DB_PASS', 'DB_PASSWORD'], '');
}

$dbCharset = $firstEnv(['DB_CHARSET'], 'utf8mb4');

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset={$dbCharset}";

try {
    $db = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$dbCharset} COLLATE utf8mb4_unicode_ci",
    ]);
} catch (PDOException $e) {
    throw new PDOException(
        "Error al conectar con la base de datos en [{$dbHost}:{$dbPort} / {$dbName}]. " .
        "Verifica que el servicio MySQL en Railway este creado y vinculado. Detalle: " . $e->getMessage(),
        (int) $e->getCode()
    );
}

// ─── Crear tablas si no existen ───────────────────────────────────────────────
$db->exec("
CREATE TABLE IF NOT EXISTS socios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    numero INT NULL,
    nombre_completo VARCHAR(255) NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    socio_id INT UNIQUE,
    nombre VARCHAR(100) NOT NULL,
    correo VARCHAR(255) UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('Presidente','Tesorera','Secretaria Actividades','Secretaria General','Socio') NOT NULL,
    activo TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_usuarios_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE SET NULL,
    UNIQUE KEY usuarios_nombre_unico (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS reuniones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fecha DATE NOT NULL,
    hora TIME NOT NULL,
    valor_cuota INT NOT NULL DEFAULT 0,
    concepto VARCHAR(255) NOT NULL DEFAULT 'Cuota regular',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS llamado_lista (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reunion_id INT NOT NULL,
    socio_id INT NOT NULL,
    pago_cuota TINYINT(1) NOT NULL DEFAULT 0,
    ahorro_voluntario INT NOT NULL DEFAULT 0,
    autoprestamo TINYINT(1) NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_llamado (reunion_id, socio_id),
    CONSTRAINT fk_llamado_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones(id) ON DELETE CASCADE,
    CONSTRAINT fk_llamado_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS creditos_socio (
    socio_id INT PRIMARY KEY,
    limite INT NOT NULL DEFAULT 2000000,
    tasa_mensual_bp INT NOT NULL DEFAULT 1000,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_creditos_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS prestamos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    socio_id INT NOT NULL,
    prestatario_tercero VARCHAR(255),
    monto INT NOT NULL,
    saldo_capital INT NOT NULL DEFAULT 0,
    tasa_mensual_bp INT NOT NULL,
    fecha_entrega DATE NOT NULL,
    plazo_meses INT NOT NULL DEFAULT 1,
    fecha_vencimiento DATE,
    fecha_liquidacion DATE,
    interes_recibido INT NOT NULL DEFAULT 0,
    mora_recibida INT NOT NULL DEFAULT 0,
    estado ENUM('pendiente','activo','pagado','rechazado') NOT NULL DEFAULT 'pendiente',
    aprobado_por INT NULL,
    fecha_aprobacion DATETIME NULL,
    observacion_aprobacion VARCHAR(255) NULL,
    foto_evidencia VARCHAR(255),
    firma_evidencia VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prestamos_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS prestamo_pagos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    prestamo_id INT NOT NULL,
    registrado_por INT NOT NULL,
    fecha_pago DATE NOT NULL,
    tipo_pago ENUM('capital','interes','mora','mixto','liquidacion') NOT NULL DEFAULT 'mixto',
    abono_capital INT NOT NULL DEFAULT 0,
    abono_interes INT NOT NULL DEFAULT 0,
    abono_mora INT NOT NULL DEFAULT 0,
    total_pagado INT NOT NULL,
    observacion VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_pp_prestamo FOREIGN KEY (prestamo_id) REFERENCES prestamos(id) ON DELETE CASCADE,
    CONSTRAINT fk_pp_usuario FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS actividades (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    fecha DATE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS actividad_participantes (
    actividad_id INT NOT NULL,
    socio_id INT NOT NULL,
    PRIMARY KEY (actividad_id, socio_id),
    CONSTRAINT fk_ap_actividad FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE,
    CONSTRAINT fk_ap_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS actividad_movimientos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    actividad_id INT NOT NULL,
    tipo ENUM('ingreso','gasto') NOT NULL,
    concepto VARCHAR(255) NOT NULL,
    valor INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_am_actividad FOREIGN KEY (actividad_id) REFERENCES actividades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS liquidaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ano INT NOT NULL UNIQUE,
    total_ahorros INT NOT NULL DEFAULT 0,
    total_intereses INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS liquidacion_socios (
    liquidacion_id INT NOT NULL,
    socio_id INT NOT NULL,
    ahorro_base INT NOT NULL DEFAULT 0,
    utilidad_repartida INT NOT NULL DEFAULT 0,
    PRIMARY KEY (liquidacion_id, socio_id),
    CONSTRAINT fk_ls_liq FOREIGN KEY (liquidacion_id) REFERENCES liquidaciones(id) ON DELETE CASCADE,
    CONSTRAINT fk_ls_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh TEXT NOT NULL,
    auth TEXT NOT NULL,
    content_encoding ENUM('aes128gcm','aesgcm') NOT NULL DEFAULT 'aes128gcm',
    expiration_time BIGINT,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_endpoint (endpoint(512)),
    CONSTRAINT fk_push_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS notificaciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(255) NOT NULL,
    mensaje TEXT NOT NULL,
    socio_id INT,
    creador_id INT NOT NULL,
    enviados INT NOT NULL DEFAULT 0,
    fallidos INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_noti_socio FOREIGN KEY (socio_id) REFERENCES socios(id) ON DELETE SET NULL,
    CONSTRAINT fk_noti_creador FOREIGN KEY (creador_id) REFERENCES usuarios(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$db->exec("
CREATE TABLE IF NOT EXISTS recordatorios_enviados (
    reunion_id INT NOT NULL,
    horario VARCHAR(50) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reunion_id, horario),
    CONSTRAINT fk_rec_reunion FOREIGN KEY (reunion_id) REFERENCES reuniones(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

// ─── Inicialización y sincronización de socios y cuentas base ────────────────
try {
    $hashComun = '$2y$10$EawTIxsCzRBhwcSBrCopE.4CWY.COx3.4WNqhvO8tq4ZXfcgrw3tq'; // Hash estándar para contraseña '123456'

    // 1. Asegurar que la cuenta de Presidente tenga siempre la misma contraseña ('123456')
    $stmtPres = $db->prepare("UPDATE usuarios SET password_hash = :hash WHERE LOWER(nombre) = 'presidente'");
    $stmtPres->execute(['hash' => $hashComun]);

    // 2. Socios base requeridos
    $sociosBase = [
        ['numero' => 1, 'nombre' => 'Presidente'],
        ['numero' => 2, 'nombre' => 'tesorera'],
        ['numero' => 3, 'nombre' => 'secretaria general'],
        ['numero' => 4, 'nombre' => 'secretaria actividades'],
        ['numero' => 5, 'nombre' => 'socio 1'],
        ['numero' => 6, 'nombre' => 'socio 2'],
    ];

    $findSocio = $db->prepare('SELECT id, numero FROM socios WHERE LOWER(TRIM(nombre_completo)) = LOWER(:nombre) LIMIT 1');
    $insertSocio = $db->prepare('INSERT INTO socios (numero, nombre_completo, activo) VALUES (:numero, :nombre, 1)');
    $updateSocioNum = $db->prepare('UPDATE socios SET numero = :numero WHERE id = :id AND (numero IS NULL OR numero != :numero)');

    foreach ($sociosBase as $sb) {
        $findSocio->execute(['nombre' => $sb['nombre']]);
        $row = $findSocio->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $insertSocio->execute(['numero' => $sb['numero'], 'nombre' => $sb['nombre']]);
        } elseif ($row['numero'] === null || (int)$row['numero'] !== $sb['numero']) {
            $updateSocioNum->execute(['numero' => $sb['numero'], 'id' => (int)$row['id']]);
        }
    }

    // 3. Cuentas de usuario base vinculadas a sus respectivos socios
    $cuentasBase = [
        ['nombre' => 'Presidente',             'rol' => 'Presidente',            'socio' => 'Presidente'],
        ['nombre' => 'tesorera',               'rol' => 'Tesorera',              'socio' => 'tesorera'],
        ['nombre' => 'secretaria general',     'rol' => 'Secretaria General',    'socio' => 'secretaria general'],
        ['nombre' => 'secretaria actividades', 'rol' => 'Secretaria Actividades', 'socio' => 'secretaria actividades'],
        ['nombre' => 'Socio1',                 'rol' => 'Socio',                 'socio' => 'socio 1'],
        ['nombre' => 'Socio2',                 'rol' => 'Socio',                 'socio' => 'socio 2'],
    ];

    $findUser = $db->prepare('SELECT id, socio_id, password_hash FROM usuarios WHERE LOWER(TRIM(nombre)) = LOWER(:nombre) LIMIT 1');
    $insertUser = $db->prepare('INSERT INTO usuarios (socio_id, nombre, password_hash, rol, activo) VALUES (:socio_id, :nombre, :hash, :rol, 1)');
    $updateUser = $db->prepare('UPDATE usuarios SET socio_id = :socio_id, password_hash = :hash WHERE id = :id');

    foreach ($cuentasBase as $cb) {
        $findSocio->execute(['nombre' => $cb['socio']]);
        $socioRow = $findSocio->fetch(PDO::FETCH_ASSOC);
        $socioId = $socioRow ? (int)$socioRow['id'] : null;

        $findUser->execute(['nombre' => $cb['nombre']]);
        $userRow = $findUser->fetch(PDO::FETCH_ASSOC);

        if (!$userRow) {
            $insertUser->execute([
                'socio_id' => $socioId,
                'nombre'   => $cb['nombre'],
                'hash'     => $hashComun,
                'rol'      => $cb['rol'],
            ]);
        } else {
            $needsUpdate = false;
            if ($socioId !== null && (int)$userRow['socio_id'] !== $socioId) {
                $needsUpdate = true;
            }
            if ($userRow['password_hash'] !== $hashComun) {
                $needsUpdate = true;
            }
            if ($needsUpdate) {
                $updateUser->execute([
                    'socio_id' => $socioId ?? $userRow['socio_id'],
                    'hash'     => $hashComun,
                    'id'       => (int)$userRow['id'],
                ]);
            }
        }
    }

    // 4. Asegurar límites de crédito para todos los socios activos
    $db->exec('INSERT IGNORE INTO creditos_socio (socio_id, limite, tasa_mensual_bp) SELECT id, 2000000, 1000 FROM socios WHERE activo = 1');
} catch (\Throwable $e) {
    error_log('Error en auto-seed de base de datos: ' . $e->getMessage());
}

return $db;