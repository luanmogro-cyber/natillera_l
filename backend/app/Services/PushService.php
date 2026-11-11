<?php
declare(strict_types=1);

namespace Natillera\App\Services;

use Minishlink\WebPush\WebPush;
use Natillera\App\Models\PushSubscription;
use PDO;
use RuntimeException;

final class PushService
{
    public function __construct(private PDO $db)
    {
    }

    public function enviarASocios(array $payload, ?int $socioId = null, ?int $excluirUsuarioId = null): array
    {
        $this->validarConfiguracionOpenSsl();
        $config = require dirname(__DIR__, 2) . '/config/vapid_keys.php';
        if ($config['publicKey'] === '' || $config['privateKey'] === '') {
            throw new RuntimeException('Configura las claves VAPID en el archivo .env antes de enviar notificaciones.');
        }

        $suscripciones = (new PushSubscription($this->db))->destinatarios($socioId, $excluirUsuarioId);
        if ($suscripciones === []) {
            return ['intentados' => 0, 'enviados' => 0, 'fallidos' => 0, 'vencidos' => 0];
        }

        $webPush = new WebPush([
            'VAPID' => [
                'subject' => $config['subject'],
                'publicKey' => $config['publicKey'],
                'privateKey' => $config['privateKey'],
            ],
        ]);
        $subscriptions = new PushSubscription($this->db);
        foreach ($suscripciones as $destinatario) {
            $webPush->queueNotification(
                $destinatario['subscription'],
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                ['TTL' => 300, 'urgency' => 'normal']
            );
        }

        $enviados = 0;
        $fallidos = 0;
        $vencidos = 0;
        foreach ($webPush->flush() as $report) {
            if ($report->isSuccess()) {
                $enviados++;
                continue;
            }

            $fallidos++;
            if ($report->isSubscriptionExpired()) {
                $vencidos++;
                $url = $report->getEndpoint();
                foreach ($suscripciones as $destinatario) {
                    if ($destinatario['subscription']->getEndpoint() === $url) {
                        $subscriptions->eliminar($destinatario['id']);
                        break;
                    }
                }
            }
        }

        return [
            'intentados' => count($suscripciones),
            'enviados' => $enviados,
            'fallidos' => $fallidos,
            'vencidos' => $vencidos,
        ];
    }

    private function validarConfiguracionOpenSsl(): void
    {
        if (
            PHP_OS_FAMILY !== 'Windows'
            || (getenv('OPENSSL_CONF') !== false && getenv('OPENSSL_CONF') !== '')
        ) {
            return;
        }

        $opensslConfig = dirname((string) php_ini_loaded_file())
            . DIRECTORY_SEPARATOR . 'extras'
            . DIRECTORY_SEPARATOR . 'ssl'
            . DIRECTORY_SEPARATOR . 'openssl.cnf';
        if (is_file($opensslConfig)) {
            throw new RuntimeException(
                'OpenSSL no tiene OPENSSL_CONF configurado en Windows. Define esa variable con la ruta '
                . 'extras/ssl/openssl.cnf de tu PHP de Laragon y reinicia Laragon antes de enviar notificaciones.'
            );
        }
    }
}
