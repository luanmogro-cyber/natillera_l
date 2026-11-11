<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Actividad extends Model
{
    public function todas(): array
    {
        $actividades = $this->db->query(
            'SELECT actividades.id, actividades.nombre, actividades.fecha,
                    (SELECT COALESCE(SUM(valor), 0) FROM actividad_movimientos WHERE actividad_id = actividades.id AND tipo = "ingreso") AS ingresos,
                    (SELECT COALESCE(SUM(valor), 0) FROM actividad_movimientos WHERE actividad_id = actividades.id AND tipo = "gasto") AS gastos,
                    (SELECT COUNT(*) FROM actividad_participantes WHERE actividad_id = actividades.id) AS participantes
             FROM actividades
             ORDER BY actividades.fecha DESC, actividades.id DESC'
        )->fetchAll();

        $participantesStatement = $this->db->prepare(
            'SELECT socios.id, socios.nombre_completo
             FROM actividad_participantes
             INNER JOIN socios ON socios.id = actividad_participantes.socio_id
             WHERE actividad_participantes.actividad_id = :actividad_id
             ORDER BY socios.nombre_completo, socios.id'
        );
        foreach ($actividades as &$actividad) {
            $actividad['neto'] = (int) $actividad['ingresos'] - (int) $actividad['gastos'];
            $cantidad = (int) $actividad['participantes'];
            $participantesStatement->execute(['actividad_id' => (int) $actividad['id']]);
            $participantes = $participantesStatement->fetchAll();
            $repartible = max(0, (int) $actividad['neto']);
            $base = $cantidad > 0 ? intdiv($repartible, $cantidad) : 0;
            $residuo = $cantidad > 0 ? $repartible % $cantidad : 0;
            foreach ($participantes as $indice => &$participante) {
                $participante['reparto'] = $base + ($indice < $residuo ? 1 : 0);
            }
            unset($participante);
            $actividad['lista_participantes'] = $participantes;
        }
        unset($actividad);

        return $actividades;
    }

    public function participantes(): array
    {
        return $this->db->query(
            'SELECT socios.id, socios.nombre_completo
             FROM socios
             WHERE socios.activo = 1
             ORDER BY socios.nombre_completo, socios.id'
        )->fetchAll();
    }

    public function crear(string $nombre, string $fecha, array $socioIds): int
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare('INSERT INTO actividades (nombre, fecha) VALUES (:nombre, :fecha)');
            $statement->execute(['nombre' => $nombre, 'fecha' => $fecha]);
            $actividadId = (int) $this->db->lastInsertId();
            $participantStatement = $this->db->prepare(
                'INSERT INTO actividad_participantes (actividad_id, socio_id) VALUES (:actividad_id, :socio_id)'
            );
            foreach ($socioIds as $socioId) {
                $participantStatement->execute(['actividad_id' => $actividadId, 'socio_id' => $socioId]);
            }
            $this->db->commit();
            return $actividadId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function movimientos(int $actividadId): array
    {
        $statement = $this->db->prepare(
            'SELECT id, tipo, concepto, valor, created_at
             FROM actividad_movimientos
             WHERE actividad_id = :actividad_id
             ORDER BY id DESC'
        );
        $statement->execute(['actividad_id' => $actividadId]);
        return $statement->fetchAll();
    }

    public function agregarMovimiento(int $actividadId, string $tipo, string $concepto, int $valor): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO actividad_movimientos (actividad_id, tipo, concepto, valor)
             VALUES (:actividad_id, :tipo, :concepto, :valor)'
        );
        $statement->execute([
            'actividad_id' => $actividadId,
            'tipo' => $tipo,
            'concepto' => $concepto,
            'valor' => $valor,
        ]);
    }

    public function eliminarMovimiento(int $movimientoId): bool
    {
        $statement = $this->db->prepare('DELETE FROM actividad_movimientos WHERE id = :id');
        $statement->execute(['id' => $movimientoId]);
        return $statement->rowCount() > 0;
    }
}
