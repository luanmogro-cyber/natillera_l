<?php
declare(strict_types=1);

use Natillera\App\Models\Notificacion;
use Natillera\App\Services\PushService;

date_default_timezone_set('America/Bogota');
$horario = $argv[1] ?? '';
$horariosPermitidos = ['08:00', '12:00', '13:00'];
if (!in_array($horario, $horariosPermitidos, true)) {
    fwrite(STDERR, "Indica uno de estos horarios: 08:00, 12:00 o 13:00.\n");
    exit(2);
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Faltan dependencias. Ejecuta composer install.\n");
    exit(1);
}
require $autoload;

$db = require dirname(__DIR__) . '/config/database.php';
$hoy = date('Y-m-d');
$statement = $db->prepare(
    'SELECT id, hora, valor_cuota
     FROM reuniones
     WHERE fecha = :fecha
     ORDER BY hora, id'
);
$statement->execute(['fecha' => $hoy]);
$reuniones = $statement->fetchAll();
$notificaciones = new Notificacion($db);
$push = new PushService($db);

foreach ($reuniones as $reunion) {
    $reunionId = (int) $reunion['id'];
    if ($notificaciones->recordatorioFueEnviado($reunionId, $horario)) {
        continue;
    }

    $hora = substr((string) $reunion['hora'], 0, 5);
    $valor = '$' . number_format((int) $reunion['valor_cuota'], 0, ',', '.') . ' COP';
    $resultado = $push->enviarASocios([
        'title' => 'Hoy nos encontramos',
        'body' => "La reunión es a las {$hora}. Recuerda llevar tu cuota de {$valor}.",
        'url' => '/socio',
    ]);

    if ($resultado['intentados'] === 0 || $resultado['fallidos'] > 0) {
        throw new RuntimeException(
            "Recordatorio {$horario} de la reunión {$reunionId}: {$resultado['enviados']} enviados, {$resultado['fallidos']} fallidos."
        );
    }

    $notificaciones->marcarRecordatorio($reunionId, $horario);
    echo "Recordatorio {$horario} enviado a {$resultado['enviados']} dispositivos.\n";
}

if ($reuniones === []) {
    echo "No hay reuniones programadas para {$hoy}.\n";
}
