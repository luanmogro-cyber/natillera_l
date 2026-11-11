<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Reunion extends Model
{
    public function cantidad(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM reuniones')->fetchColumn();
    }

    public function todas(): array
    {
        $statement = $this->db->query(
            'SELECT id, fecha, hora, valor_cuota, concepto
             FROM reuniones
             ORDER BY fecha DESC, hora DESC, id DESC'
        );

        return $statement->fetchAll();
    }

    public function proxima(): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, fecha, hora, valor_cuota, concepto
             FROM reuniones
             WHERE fecha >= :hoy
             ORDER BY fecha, hora, id
             LIMIT 1'
        );
        $statement->execute(['hoy' => date('Y-m-d')]);
        $reunion = $statement->fetch();

        return $reunion === false ? null : $reunion;
    }

    public function crear(string $fecha, string $hora, int $valorCuota, string $concepto): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO reuniones (fecha, hora, valor_cuota, concepto)
             VALUES (:fecha, :hora, :valor_cuota, :concepto)'
        );
        $statement->execute([
            'fecha' => $fecha,
            'hora' => $hora,
            'valor_cuota' => $valorCuota,
            'concepto' => $concepto,
        ]);
    }

    public function buscar(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, fecha, hora, valor_cuota, concepto
             FROM reuniones
             WHERE id = :id'
        );
        $statement->execute(['id' => $id]);
        $reunion = $statement->fetch();

        return $reunion === false ? null : $reunion;
    }
}
