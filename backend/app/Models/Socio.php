<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Socio extends Model
{
    public function cantidad(): int
    {
        return (int) $this->db->query('SELECT COUNT(*) FROM socios WHERE activo = 1')->fetchColumn();
    }

    public function activos(): array
    {
        $statement = $this->db->query(
            'SELECT socios.id, socios.numero, socios.nombre_completo, COALESCE(usuarios.rol, "Socio") AS rol
             FROM socios
             LEFT JOIN usuarios ON usuarios.socio_id = socios.id
             WHERE socios.activo = 1
             ORDER BY COALESCE(socios.numero, 999999) ASC, socios.id ASC'
        );

        return $statement->fetchAll();
    }

    public function recientes(): array
    {
        $statement = $this->db->query(
            'SELECT socios.id, socios.numero, socios.nombre_completo, socios.activo,
                    usuarios.rol AS usuario_rol, usuarios.nombre AS usuario_nombre
             FROM socios
             LEFT JOIN usuarios ON usuarios.socio_id = socios.id
             ORDER BY socios.activo DESC, COALESCE(socios.numero, 999999) ASC, socios.id ASC'
        );

        return $statement->fetchAll();
    }

    public function sinCuenta(): array
    {
        $statement = $this->db->query(
            'SELECT socios.id, socios.numero, socios.nombre_completo
             FROM socios
             LEFT JOIN usuarios ON usuarios.socio_id = socios.id
             WHERE socios.activo = 1 AND usuarios.id IS NULL
             ORDER BY COALESCE(socios.numero, 999999) ASC, socios.id ASC'
        );

        return $statement->fetchAll();
    }

    public function siguienteNumero(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(numero), 0) FROM socios')->fetchColumn() + 1;
    }

    public function numeroExiste(int $numero, ?int $excluirSocioId = null): bool
    {
        if ($excluirSocioId !== null) {
            $stmt = $this->db->prepare('SELECT 1 FROM socios WHERE activo = 1 AND numero = :num AND id <> :id LIMIT 1');
            $stmt->execute(['num' => $numero, 'id' => $excluirSocioId]);
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM socios WHERE activo = 1 AND numero = :num LIMIT 1');
            $stmt->execute(['num' => $numero]);
        }
        return $stmt->fetchColumn() !== false;
    }

    public function actualizarNumero(int $socioId, int $numero): void
    {
        $stmt = $this->db->prepare('UPDATE socios SET numero = :numero WHERE id = :id');
        $stmt->execute(['numero' => $numero, 'id' => $socioId]);
    }

    public function crearVarios(array $nombres, ?int $numeroInicial = null): int
    {
        $statement = $this->db->prepare('INSERT INTO socios (numero, nombre_completo) VALUES (:numero, :nombre)');
        $creados = 0;
        $num = $numeroInicial !== null && $numeroInicial > 0 ? $numeroInicial : $this->siguienteNumero();
        $this->db->beginTransaction();
        try {
            foreach ($nombres as $nombre) {
                while ($this->numeroExiste($num)) {
                    $num++;
                }
                $statement->execute(['numero' => $num, 'nombre' => $nombre]);
                $num++;
                $creados++;
            }
            $this->db->commit();
        } catch (\Throwable $error) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $error;
        }

        return $creados;
    }

    public function resumenPersonal(int $socioId): array
    {
        $stmtTotales = $this->db->prepare(
            'SELECT
                COALESCE(SUM(CASE WHEN lista.pago_cuota = 1 THEN reuniones.valor_cuota ELSE 0 END), 0) AS cuotas,
                COALESCE(SUM(lista.ahorro_voluntario), 0) AS ahorro_voluntario,
                COUNT(DISTINCT lista.reunion_id) AS reuniones_registradas
             FROM llamado_lista AS lista
             INNER JOIN reuniones ON reuniones.id = lista.reunion_id
             WHERE lista.socio_id = :socio_id'
        );
        $stmtTotales->execute(['socio_id' => $socioId]);
        $resumen = $stmtTotales->fetch() ?: [];

        $cuotas = (int) ($resumen['cuotas'] ?? 0);
        $ahorroVoluntario = (int) ($resumen['ahorro_voluntario'] ?? 0);

        // Total reuniones del grupo
        $totalReuniones = (int) $this->db->query('SELECT COUNT(*) FROM reuniones')->fetchColumn();

        // Reuniones donde pagó cuota (asistió)
        $stmtAsist = $this->db->prepare(
            'SELECT COUNT(*) FROM llamado_lista WHERE socio_id = :socio_id AND pago_cuota = 1'
        );
        $stmtAsist->execute(['socio_id' => $socioId]);
        $reunionesAsistidas = (int) $stmtAsist->fetchColumn();

        // Historial completo de reuniones
        $stmtHistorial = $this->db->prepare(
            'SELECT reuniones.id, reuniones.fecha, reuniones.hora, reuniones.concepto, reuniones.valor_cuota,
                    COALESCE(lista.pago_cuota, 0) AS pago_cuota,
                    COALESCE(lista.ahorro_voluntario, 0) AS ahorro_voluntario,
                    COALESCE(lista.autoprestamo, 0) AS autoprestamo,
                    lista.id AS registro_id
             FROM reuniones
             LEFT JOIN llamado_lista AS lista ON lista.reunion_id = reuniones.id AND lista.socio_id = :socio_id
             ORDER BY reuniones.fecha DESC, reuniones.id DESC'
        );
        $stmtHistorial->execute(['socio_id' => $socioId]);
        $historialReuniones = $stmtHistorial->fetchAll();

        // Préstamos del socio
        $prestamoModel = new Prestamo($this->db);
        $prestamos = array_values(array_filter($prestamoModel->listar($socioId), static fn(array $p): bool => in_array($p['estado'], ['activo','pagado'], true)));
        $totalPrestado = 0;
        $saldoPrestamosActivo = 0;
        $totalInteresesPagados = 0;
        $totalMoraPagada = 0;

        foreach ($prestamos as &$p) {
            $totalPrestado += (int) $p['monto'];
            if ($p['estado'] === 'activo') {
                $saldoPrestamosActivo += (int) $p['saldo_capital'];
            }
            $totalInteresesPagados += (int) $p['interes_recibido'];
            $totalMoraPagada += (int) ($p['mora_recibida'] ?? 0);
            $p['pagos'] = $prestamoModel->pagosPorPrestamo((int) $p['id']);
        }
        unset($p);

        // Actividades del socio
        $stmtAct = $this->db->prepare(
            'SELECT actividades.id, actividades.nombre, actividades.fecha,
                    (SELECT COALESCE(SUM(valor), 0) FROM actividad_movimientos WHERE actividad_id = actividades.id AND tipo = "ingreso") AS ingresos,
                    (SELECT COALESCE(SUM(valor), 0) FROM actividad_movimientos WHERE actividad_id = actividades.id AND tipo = "gasto") AS gastos,
                    (SELECT COUNT(*) FROM actividad_participantes WHERE actividad_id = actividades.id) AS participantes
             FROM actividades
             INNER JOIN actividad_participantes ON actividad_participantes.actividad_id = actividades.id
             WHERE actividad_participantes.socio_id = :socio_id
             ORDER BY actividades.fecha DESC, actividades.id DESC'
        );
        $stmtAct->execute(['socio_id' => $socioId]);
        $actividades = $stmtAct->fetchAll();

        foreach ($actividades as &$act) {
            $neto = (int) $act['ingresos'] - (int) $act['gastos'];
            $cant = max(1, (int) $act['participantes']);
            $act['neto'] = $neto;
            $act['reparto'] = $neto > 0 ? (int) floor($neto / $cant) : 0;
        }
        unset($act);

        return [
            'cuotas' => $cuotas,
            'ahorro_voluntario' => $ahorroVoluntario,
            'total_ahorrado' => $cuotas + $ahorroVoluntario,
            'reuniones_registradas' => (int) ($resumen['reuniones_registradas'] ?? 0),
            'total_reuniones' => $totalReuniones,
            'reuniones_asistidas' => $reunionesAsistidas,
            'porcentaje_asistencia' => $totalReuniones > 0 ? round(($reunionesAsistidas / $totalReuniones) * 100) : 0,
            'historial_reuniones' => $historialReuniones,
            'prestamos' => $prestamos,
            'total_prestado' => $totalPrestado,
            'saldo_prestamos_activo' => $saldoPrestamosActivo,
            'total_intereses_pagados' => $totalInteresesPagados,
            'total_mora_pagada' => $totalMoraPagada,
            'actividades' => $actividades,
        ];
    }
}
