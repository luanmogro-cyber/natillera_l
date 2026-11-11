<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class LlamadoLista extends Model
{
    public function porReunion(int $reunionId): array
    {
        $statement = $this->db->prepare(
            'SELECT socio_id, pago_cuota, ahorro_voluntario, autoprestamo
             FROM llamado_lista
             WHERE reunion_id = :reunion_id'
        );
        $statement->execute(['reunion_id' => $reunionId]);

        $registros = [];
        foreach ($statement->fetchAll() as $registro) {
            $registros[(int) $registro['socio_id']] = $registro;
        }

        return $registros;
    }

    public function reunionesGuardadasIds(): array
    {
        $rows = $this->db->query('SELECT DISTINCT reunion_id FROM llamado_lista')->fetchAll();
        return array_map(static fn(array $r): int => (int) $r['reunion_id'], $rows);
    }

    public function siguienteReunionPendiente(?int $excluirReunionId = null): ?int
    {
        $sql = 'SELECT r.id
                FROM reuniones r
                LEFT JOIN (SELECT DISTINCT reunion_id FROM llamado_lista) l ON l.reunion_id = r.id
                WHERE l.reunion_id IS NULL';
        $params = [];
        if ($excluirReunionId !== null) {
            $sql .= ' AND r.id <> :excluir';
            $params['excluir'] = $excluirReunionId;
        }
        $sql .= ' ORDER BY r.fecha ASC, r.hora ASC, r.id ASC LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $id = $stmt->fetchColumn();
        return $id !== false ? (int) $id : null;
    }

    public function guardarLote(int $reunionId, array $registros): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO llamado_lista
                (reunion_id, socio_id, pago_cuota, ahorro_voluntario, autoprestamo, updated_at)
             VALUES
                (:reunion_id, :socio_id, :pago_cuota, :ahorro_voluntario, :autoprestamo, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                pago_cuota = VALUES(pago_cuota),
                ahorro_voluntario = VALUES(ahorro_voluntario),
                autoprestamo = VALUES(autoprestamo),
                updated_at = CURRENT_TIMESTAMP'
        );

        $this->db->beginTransaction();
        try {
            foreach ($registros as $registro) {
                $statement->execute([
                    'reunion_id' => $reunionId,
                    'socio_id' => $registro['socio_id'],
                    'pago_cuota' => $registro['pago_cuota'] ? 1 : 0,
                    'ahorro_voluntario' => $registro['ahorro_voluntario'],
                    'autoprestamo' => $registro['autoprestamo'] ? 1 : 0,
                ]);
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }
}
