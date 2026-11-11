<?php
declare(strict_types=1);

$environmentFile = dirname(__DIR__) . '/.env';
$fileValues = [];
if (is_file($environmentFile)) {
    $fileValues = parse_ini_file($environmentFile, false, INI_SCANNER_RAW);
    if ($fileValues === false) {
        throw new RuntimeException('No se pudo leer la configuración de VAPID en .env.');
    }
}

$readValue = static function (string $name) use ($fileValues): string {
    $systemValue = getenv($name);
    return $systemValue !== false ? $systemValue : (string) ($fileValues[$name] ?? '');
};

return [
    'subject' => $readValue('NATILLERA_VAPID_SUBJECT') ?: 'mailto:admin@localhost',
    'publicKey' => $readValue('NATILLERA_VAPID_PUBLIC_KEY'),
    'privateKey' => $readValue('NATILLERA_VAPID_PRIVATE_KEY'),
];
