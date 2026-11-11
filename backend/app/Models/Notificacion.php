<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Notificacion extends Model
{
    public function recientes(): array
    {
        return $this->db->query(
            'SELECT notificaciones.id, notificaciones.titulo, notificaciones.mensaje,
                    notificaciones.socio_id, notificaciones.enviados, notificaciones.fallidos,
                    notificaciones.created_at, socios.nombre_completo AS socio_nombre
             FROM notificaciones
             LEFT JOIN socios ON socios.id = notificaciones.socio_id
             ORDER BY notificaciones.id DESC
             LIMIT 20'
        )->fetchAll();
    }

    public function guardar(
        string $titulo,
        string $mensaje,
        ?int $socioId,
        int $creadorId,
        int $enviados,
        int $fallidos
    ): void {
        $statement = $this->db->prepare(
            'INSERT INTO notificaciones
                (titulo, mensaje, socio_id, creador_id, enviados, fallidos)
             VALUES
                (:titulo, :mensaje, :socio_id, :creador_id, :enviados, :fallidos)'
        );
        $statement->execute([
            'titulo' => $titulo,
            'mensaje' => $mensaje,
            'socio_id' => $socioId,
            'creador_id' => $creadorId,
            'enviados' => $enviados,
            'fallidos' => $fallidos,
        ]);
    }

    public function marcarRecordatorio(int $reunionId, string $horario): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO recordatorios_enviados (reunion_id, horario)
             VALUES (:reunion_id, :horario)'
        );
        $statement->execute(['reunion_id' => $reunionId, 'horario' => $horario]);
    }

    public function recordatorioFueEnviado(int $reunionId, string $horario): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM recordatorios_enviados WHERE reunion_id = :reunion_id AND horario = :horario'
        );
        $statement->execute(['reunion_id' => $reunionId, 'horario' => $horario]);
        return $statement->fetchColumn() !== false;
    }
}
