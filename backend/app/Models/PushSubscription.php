<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Minishlink\WebPush\Subscription;
use Natillera\Core\Model;

final class PushSubscription extends Model
{
    public function guardar(int $usuarioId, array $subscription): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO push_subscriptions (usuario_id, endpoint, p256dh, auth, content_encoding, expiration_time)
             VALUES (:usuario_id, :endpoint, :p256dh, :auth, :content_encoding, :expiration_time)
             ON DUPLICATE KEY UPDATE
                usuario_id = VALUES(usuario_id),
                p256dh = VALUES(p256dh),
                auth = VALUES(auth),
                content_encoding = VALUES(content_encoding),
                expiration_time = VALUES(expiration_time)'
        );
        $statement->execute([
            'usuario_id' => $usuarioId,
            'endpoint' => $subscription['endpoint'],
            'p256dh' => $subscription['keys']['p256dh'],
            'auth' => $subscription['keys']['auth'],
            'content_encoding' => $subscription['contentEncoding'] ?? 'aes128gcm',
            'expiration_time' => ($subscription['expirationTime'] ?? null) === null
                ? null
                : (int) ($subscription['expirationTime'] / 1000),
        ]);
    }

    public function destinatarios(?int $socioId = null, ?int $excluirUsuarioId = null): array
    {
        $sql = 'SELECT push_subscriptions.id, push_subscriptions.usuario_id,
                       push_subscriptions.endpoint, push_subscriptions.p256dh,
                       push_subscriptions.auth, push_subscriptions.content_encoding
                FROM push_subscriptions
                INNER JOIN usuarios ON usuarios.id = push_subscriptions.usuario_id
                WHERE usuarios.activo = 1';
        $parameters = [];
        if ($excluirUsuarioId !== null) {
            $sql .= ' AND usuarios.id != :excluir_usuario_id';
            $parameters['excluir_usuario_id'] = $excluirUsuarioId;
        }
        if ($socioId !== null) {
            $sql .= ' AND usuarios.socio_id = :socio_id';
            $parameters['socio_id'] = $socioId;
        }

        $statement = $this->db->prepare($sql);
        $statement->execute($parameters);
        $rows = $statement->fetchAll();

        return array_map(
            static fn(array $row): array => [
                'id' => (int) $row['id'],
                'subscription' => Subscription::create([
                    'endpoint' => $row['endpoint'],
                    'publicKey' => $row['p256dh'],
                    'authToken' => $row['auth'],
                    'contentEncoding' => $row['content_encoding'],
                ]),
            ],
            $rows
        );
    }

    public function eliminar(int $id): void
    {
        $statement = $this->db->prepare('DELETE FROM push_subscriptions WHERE id = :id');
        $statement->execute(['id' => $id]);
    }

    public function eliminarDeUsuario(int $usuarioId, string $endpoint): void
    {
        $statement = $this->db->prepare(
            'DELETE FROM push_subscriptions
             WHERE usuario_id = :usuario_id AND endpoint = :endpoint'
        );
        $statement->execute(['usuario_id' => $usuarioId, 'endpoint' => $endpoint]);
    }
}
