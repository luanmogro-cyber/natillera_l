<?php
declare(strict_types=1);

// ─── Conexión a MySQL ─────────────────────────────────────────────────────────
$db = new PDO("mysql:host=127.0.0.1;port=3306;dbname=natillera_l;charset=utf8mb4", "root", "root", [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// ─── Directorio de salida ─────────────────────────────────────────────────────
$exportDir = dirname(__DIR__, 2) . '/database';
if (!is_dir($exportDir) && !mkdir($exportDir, 0775, true)) {
    throw new RuntimeException("No se pudo crear el directorio $exportDir");
}

$fecha    = date('Y-m-d_H-i-s');
$filename = $exportDir . "/natillera_l_{$fecha}.sql";
$out      = fopen($filename, 'w');

// ─── Cabecera del dump ────────────────────────────────────────────────────────
fwrite($out, "-- ============================================================\n");
fwrite($out, "-- Base de datos: natillera_l\n");
fwrite($out, "-- Proyecto:      La Semilla — Natillera comunitaria\n");
fwrite($out, "-- Generado:      " . date('Y-m-d H:i:s') . "\n");
fwrite($out, "-- Motor:         MySQL 9.2 / utf8mb4_unicode_ci\n");
fwrite($out, "-- ============================================================\n\n");

fwrite($out, "SET NAMES utf8mb4;\n");
fwrite($out, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

fwrite($out, "-- Crear base de datos si no existe\n");
fwrite($out, "CREATE DATABASE IF NOT EXISTS `natillera_l`\n");
fwrite($out, "  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\n");
fwrite($out, "USE `natillera_l`;\n\n");

// ─── Obtener lista de tablas en orden correcto ────────────────────────────────
// Orden que respeta las foreign keys (padres primero)
$tablesOrdered = [
    'socios',
    'usuarios',
    'reuniones',
    'llamado_lista',
    'creditos_socio',
    'prestamos',
    'prestamo_pagos',
    'actividades',
    'actividad_participantes',
    'actividad_movimientos',
    'liquidaciones',
    'liquidacion_socios',
    'push_subscriptions',
    'notificaciones',
    'recordatorios_enviados',
];

// ─── Por cada tabla: DROP + CREATE + datos ─────────────────────────────────────
foreach ($tablesOrdered as $table) {
    fwrite($out, "-- ------------------------------------------------------------\n");
    fwrite($out, "-- Tabla: $table\n");
    fwrite($out, "-- ------------------------------------------------------------\n");

    // DROP
    fwrite($out, "DROP TABLE IF EXISTS `$table`;\n");

    // CREATE (obtener el DDL real de MySQL)
    $create = $db->query("SHOW CREATE TABLE `$table`")->fetch();
    $ddl    = $create['Create Table'] ?? $create[1] ?? '';
    fwrite($out, $ddl . ";\n\n");

    // Datos
    $rows = $db->query("SELECT * FROM `$table`")->fetchAll();
    if (count($rows) === 0) {
        fwrite($out, "-- (sin datos)\n\n");
        continue;
    }

    $columns = '`' . implode('`, `', array_keys($rows[0])) . '`';
    fwrite($out, "INSERT INTO `$table` ($columns) VALUES\n");

    $lines = [];
    foreach ($rows as $row) {
        $values = array_map(function ($val) use ($db) {
            if ($val === null)             return 'NULL';
            if (is_int($val))              return (string) $val;
            if (is_float($val))            return (string) $val;
            // Escapar string
            return $db->quote((string) $val);
        }, array_values($row));
        $lines[] = '(' . implode(', ', $values) . ')';
    }

    fwrite($out, implode(",\n", $lines) . ";\n\n");
}

// ─── Pie del dump ─────────────────────────────────────────────────────────────
fwrite($out, "SET FOREIGN_KEY_CHECKS = 1;\n");
fwrite($out, "-- ============================================================\n");
fwrite($out, "-- Fin del dump — natillera_l\n");
fwrite($out, "-- ============================================================\n");

fclose($out);

$size = round(filesize($filename) / 1024, 1);
echo "✅ Dump generado correctamente" . PHP_EOL;
echo "📄 Archivo: $filename" . PHP_EOL;
echo "📦 Tamaño:  {$size} KB" . PHP_EOL;
echo PHP_EOL;
echo "Para importar en MySQL Workbench:" . PHP_EOL;
echo "  1. Abre MySQL Workbench" . PHP_EOL;
echo "  2. Server → Data Import → Import from Self-Contained File" . PHP_EOL;
echo "  3. Selecciona el archivo .sql generado" . PHP_EOL;
echo "  4. Clic en 'Start Import'" . PHP_EOL;
