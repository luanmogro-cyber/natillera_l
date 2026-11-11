<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use Natillera\Core\Model;

final class Liquidacion extends Model
{
    public function balanceGeneral(): array
    {
        $cuotas = (int) $this->db->query(
            'SELECT COALESCE(SUM(reuniones.valor_cuota), 0)
             FROM llamado_lista
             INNER JOIN reuniones ON reuniones.id = llamado_lista.reunion_id
             WHERE llamado_lista.pago_cuota = 1'
        )->fetchColumn();

        $ahorroVoluntario = (int) $this->db->query(
            'SELECT COALESCE(SUM(ahorro_voluntario), 0) FROM llamado_lista'
        )->fetchColumn();

        $interesesYMora = (int) $this->db->query(
            'SELECT COALESCE(SUM(total_val), 0) FROM (
                SELECT COALESCE(SUM(abono_interes + abono_mora), 0) AS total_val FROM prestamo_pagos
                UNION ALL
                SELECT COALESCE(SUM(p.interes_recibido + p.mora_recibida), 0) AS total_val
                FROM prestamos p
                WHERE NOT EXISTS (SELECT 1 FROM prestamo_pagos pp WHERE pp.prestamo_id = p.id)
            ) AS rendimientos'
        )->fetchColumn();

        $actividadesNeto = (int) $this->db->query(
            'SELECT COALESCE(SUM(CASE WHEN tipo = "ingreso" THEN valor ELSE -valor END), 0)
             FROM actividad_movimientos'
        )->fetchColumn();

        $capitalEnCalle = (int) $this->db->query(
            'SELECT COALESCE(SUM(CASE WHEN saldo_capital > 0 THEN saldo_capital ELSE monto END), 0)
             FROM prestamos
             WHERE estado = "activo"'
        )->fetchColumn();

        $prestamosPendientes = (int) $this->db->query(
            'SELECT COUNT(*) FROM prestamos WHERE estado = "pendiente"'
        )->fetchColumn();

        $prestamosEnMora = (int) $this->db->query(
            'SELECT COUNT(*) FROM prestamos
             WHERE estado = "activo" AND fecha_vencimiento IS NOT NULL AND fecha_vencimiento < CURDATE()'
        )->fetchColumn();

        $totalPatrimonio = $cuotas + $ahorroVoluntario + $interesesYMora + $actividadesNeto;

        return [
            'cuotas_obligatorias' => $cuotas,
            'ahorro_voluntario' => $ahorroVoluntario,
            'intereses_mora' => $interesesYMora,
            'actividades_neto' => $actividadesNeto,
            'total_patrimonio' => $totalPatrimonio,
            'capital_en_calle' => $capitalEnCalle,
            'caja_disponible' => $totalPatrimonio - $capitalEnCalle,
            'prestamos_pendientes' => $prestamosPendientes,
            'prestamos_en_mora' => $prestamosEnMora,
        ];
    }

    public function resumenAnual(int $ano): array
    {
        $desde = sprintf('%04d-01-01', $ano);
        $hasta = sprintf('%04d-01-01', $ano + 1);

        $statement = $this->db->prepare(
            'SELECT socios.id, socios.numero, socios.nombre_completo,
                    COALESCE((
                        SELECT SUM(CASE WHEN lista.pago_cuota = 1 THEN reuniones.valor_cuota ELSE 0 END + lista.ahorro_voluntario)
                        FROM llamado_lista AS lista
                        INNER JOIN reuniones ON reuniones.id = lista.reunion_id
                        WHERE lista.socio_id = socios.id
                          AND reuniones.fecha >= :desde_reuniones
                          AND reuniones.fecha < :hasta_reuniones
                    ), 0) AS ahorro_base,
                    (
                        COALESCE((
                            SELECT SUM(pp.abono_interes + pp.abono_mora)
                            FROM prestamo_pagos AS pp
                            INNER JOIN prestamos AS p ON p.id = pp.prestamo_id
                            WHERE p.socio_id = socios.id
                              AND pp.fecha_pago >= :desde_pagos
                              AND pp.fecha_pago < :hasta_pagos
                        ), 0)
                        +
                        COALESCE((
                            SELECT SUM(p.interes_recibido + p.mora_recibida)
                            FROM prestamos AS p
                            WHERE p.socio_id = socios.id
                              AND p.estado = "pagado"
                              AND p.fecha_liquidacion >= :desde_prestamos
                              AND p.fecha_liquidacion < :hasta_prestamos
                              AND NOT EXISTS (SELECT 1 FROM prestamo_pagos pp WHERE pp.prestamo_id = p.id)
                        ), 0)
                    ) AS intereses_generados
             FROM socios
             ORDER BY COALESCE(socios.numero, 999999) ASC, socios.id ASC'
        );
        $statement->execute([
            'desde_reuniones' => $desde,
            'hasta_reuniones' => $hasta,
            'desde_pagos' => $desde,
            'hasta_pagos' => $hasta,
            'desde_prestamos' => $desde,
            'hasta_prestamos' => $hasta,
        ]);
        $socios = $statement->fetchAll();
        foreach ($socios as &$socio) {
            $socio['ahorro_base'] = (int) $socio['ahorro_base'];
            $socio['intereses_generados'] = (int) $socio['intereses_generados'];
        }
        unset($socio);

        return $socios;
    }

    public function guardarCierre(int $ano, int $totalAhorros, int $totalIntereses, array $reparto): void
    {
        $this->db->beginTransaction();
        try {
            $statement = $this->db->prepare(
                'INSERT INTO liquidaciones (ano, total_ahorros, total_intereses)
                 VALUES (:ano, :total_ahorros, :total_intereses)'
            );
            $statement->execute([
                'ano' => $ano,
                'total_ahorros' => $totalAhorros,
                'total_intereses' => $totalIntereses,
            ]);
            $liquidacionId = (int) $this->db->lastInsertId();
            $detalle = $this->db->prepare(
                'INSERT INTO liquidacion_socios (liquidacion_id, socio_id, ahorro_base, utilidad_repartida)
                 VALUES (:liquidacion_id, :socio_id, :ahorro_base, :utilidad_repartida)'
            );
            foreach ($reparto as $socio) {
                $detalle->execute([
                    'liquidacion_id' => $liquidacionId,
                    'socio_id' => $socio['id'],
                    'ahorro_base' => $socio['ahorro_base'],
                    'utilidad_repartida' => $socio['utilidad_repartida'],
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

    public function cierre(int $ano): ?array
    {
        $statement = $this->db->prepare(
            'SELECT id, ano, total_ahorros, total_intereses, created_at
             FROM liquidaciones
             WHERE ano = :ano'
        );
        $statement->execute(['ano' => $ano]);
        $cierre = $statement->fetch();
        if ($cierre === false) {
            return null;
        }

        $detalle = $this->db->prepare(
            'SELECT socios.numero, socios.nombre_completo, liquidacion_socios.ahorro_base,
                    liquidacion_socios.utilidad_repartida
             FROM liquidacion_socios
             INNER JOIN socios ON socios.id = liquidacion_socios.socio_id
             WHERE liquidacion_socios.liquidacion_id = :liquidacion_id
             ORDER BY COALESCE(socios.numero, 999999) ASC, socios.id ASC'
        );
        $detalle->execute(['liquidacion_id' => (int) $cierre['id']]);
        $cierre['socios'] = $detalle->fetchAll();

        return $cierre;
    }
}
