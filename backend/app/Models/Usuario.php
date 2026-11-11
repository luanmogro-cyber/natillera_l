<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Usuario extends Model
{
    public function cantidad(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
    }

    public function crear(string $nombre, string $password, string $rol, ?int $socioIdExistente = null, ?int $numeroSocio = null): void
    {
        $this->db->beginTransaction();
        try {
            if ($socioIdExistente !== null && $socioIdExistente > 0) {
                $socioId = $socioIdExistente;
            } else {
                $checkStmt = $this->db->prepare('SELECT id FROM socios WHERE nombre_completo = :nombre LIMIT 1');
                $checkStmt->execute(['nombre' => $nombre]);
                $existingSocioId = $checkStmt->fetchColumn();

                if ($existingSocioId !== false) {
                    $socioId = (int) $existingSocioId;
                } else {
                    $num = $numeroSocio !== null && $numeroSocio > 0
                        ? $numeroSocio
                        : ((int) $this->db->query('SELECT COALESCE(MAX(numero), 0) FROM socios')->fetchColumn() + 1);
                    $socioStatement = $this->db->prepare(
                        'INSERT INTO socios (numero, nombre_completo, activo) VALUES (:numero, :nombre, 1)'
                    );
                    $socioStatement->execute(['numero' => $num, 'nombre' => $nombre]);
                    $socioId = (int) $this->db->lastInsertId();
                }
            }

            $statement = $this->db->prepare(
                'INSERT INTO usuarios (socio_id, nombre, password_hash, rol)
                 VALUES (:socio_id, :nombre, :password_hash, :rol)'
            );
            $statement->execute([
                'socio_id' => $socioId,
                'nombre' => $nombre,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'rol' => $rol,
            ]);
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function crearPresidenteInicial(string $nombre, string $password): int|false
    {
        $this->db->beginTransaction();
        try {
            if ($this->cantidad() > 0) {
                $this->db->rollBack();
                return false;
            }

            $num = (int) $this->db->query('SELECT COALESCE(MAX(numero), 0) FROM socios')->fetchColumn() + 1;
            $socioStatement = $this->db->prepare(
                'INSERT INTO socios (numero, nombre_completo) VALUES (:numero, :nombre)'
            );
            $socioStatement->execute(['numero' => $num, 'nombre' => $nombre]);
            $socioId = (int) $this->db->lastInsertId();

            $statement = $this->db->prepare(
                'INSERT INTO usuarios (socio_id, nombre, password_hash, rol)
                 VALUES (:socio_id, :nombre, :password_hash, "Presidente")'
            );
            $statement->execute([
                'socio_id' => $socioId,
                'nombre' => $nombre,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $usuarioId = (int) $this->db->lastInsertId();
            $this->db->commit();

            return $usuarioId;
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }
    }

    public function buscarPorNombre(string $nombre): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, socio_id, nombre, password_hash, rol
             FROM usuarios
             WHERE nombre = :nombre AND activo = 1'
        );
        $statement->execute(['nombre' => trim($nombre)]);
        $usuario = $statement->fetch();

        return $usuario === false ? null : $usuario;
    }

    public function nombreExiste(string $nombre): bool
    {
        $statement = $this->db->prepare(
            'SELECT 1 FROM usuarios WHERE nombre = :nombre LIMIT 1'
        );
        $statement->execute(['nombre' => trim($nombre)]);

        return $statement->fetchColumn() !== false;
    }

    public function todos(): array
    {
        $statement = $this->db->query(
            'SELECT usuarios.id, usuarios.nombre, usuarios.rol, usuarios.activo,
                    socios.numero AS socio_numero, socios.nombre_completo AS socio_nombre
             FROM usuarios
             LEFT JOIN socios ON socios.id = usuarios.socio_id
             ORDER BY usuarios.activo DESC, COALESCE(socios.numero, 999999) ASC, usuarios.nombre, usuarios.id'
        );

        return $statement->fetchAll();
    }
}
