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

    public function balanceDetalles(): array
    {
        // 1. Detalle de intereses y mora recaudados
        $interesesPagos = $this->db->query(
            'SELECT pp.id, pp.fecha_pago, pp.abono_interes, pp.abono_mora, pp.total_pagado, pp.observacion,
                    s.id AS socio_id, s.numero AS socio_numero, s.nombre_completo AS socio_nombre,
                    p.id AS prestamo_id, p.monto AS prestamo_monto
             FROM prestamo_pagos pp
             INNER JOIN prestamos p ON p.id = pp.prestamo_id
             INNER JOIN socios s ON s.id = p.socio_id
             WHERE (pp.abono_interes > 0 OR pp.abono_mora > 0)
             ORDER BY pp.fecha_pago DESC, pp.id DESC'
        )->fetchAll();

        $interesesLegacy = $this->db->query(
            'SELECT p.id, COALESCE(p.fecha_liquidacion, p.fecha_entrega) AS fecha_pago,
                    p.interes_recibido AS abono_interes, p.mora_recibida AS abono_mora,
                    (p.interes_recibido + p.mora_recibida) AS total_pagado,
                    "Liquidación de préstamo" AS observacion,
                    s.id AS socio_id, s.numero AS socio_numero, s.nombre_completo AS socio_nombre,
                    p.id AS prestamo_id, p.monto AS prestamo_monto
             FROM prestamos p
             INNER JOIN socios s ON s.id = p.socio_id
             WHERE (p.interes_recibido > 0 OR p.mora_recibida > 0)
               AND NOT EXISTS (SELECT 1 FROM prestamo_pagos pp WHERE pp.prestamo_id = p.id)
             ORDER BY fecha_pago DESC, p.id DESC'
        )->fetchAll();

        $interesesDetalle = array_merge($interesesPagos, $interesesLegacy);

        // 2. Detalle de préstamos activos (capital colocado)
        $capitalCalle = $this->db->query(
            'SELECT p.id, p.monto, p.saldo_capital, p.tasa_mensual_bp, p.fecha_entrega, p.fecha_vencimiento,
                    p.interes_recibido, p.mora_recibida,
                    s.id AS socio_id, s.numero AS socio_numero, s.nombre_completo AS socio_nombre,
                    CASE
                        WHEN p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento < CURDATE() THEN 1
                        ELSE 0
                    END AS en_mora,
                    CASE
                        WHEN p.fecha_vencimiento IS NOT NULL AND p.fecha_vencimiento < CURDATE()
                        THEN DATEDIFF(CURDATE(), p.fecha_vencimiento)
                        ELSE 0
                    END AS dias_mora
             FROM prestamos p
             INNER JOIN socios s ON s.id = p.socio_id
             WHERE p.estado = "activo"
             ORDER BY en_mora DESC, p.fecha_vencimiento ASC'
        )->fetchAll();

        // 3. Detalle de cuotas obligatorias por reunión
        $cuotasDetalle = $this->db->query(
            'SELECT r.id, r.fecha, r.hora, r.concepto, r.valor_cuota,
                    COUNT(CASE WHEN ll.pago_cuota = 1 THEN 1 END) AS total_socios_pagaron,
                    COALESCE(SUM(CASE WHEN ll.pago_cuota = 1 THEN r.valor_cuota ELSE 0 END), 0) AS total_recaudado
             FROM reuniones r
             LEFT JOIN llamado_lista ll ON ll.reunion_id = r.id
             GROUP BY r.id, r.fecha, r.hora, r.concepto, r.valor_cuota
             HAVING total_recaudado > 0
             ORDER BY r.fecha DESC'
        )->fetchAll();

        // 4. Detalle de ahorro voluntario acumulado por socio
        $ahorroDetalle = $this->db->query(
            'SELECT s.id, s.numero, s.nombre_completo,
                    COALESCE(SUM(ll.ahorro_voluntario), 0) AS total_ahorrado,
                    COUNT(CASE WHEN ll.ahorro_voluntario > 0 THEN 1 END) AS quincenas_aportadas
             FROM socios s
             LEFT JOIN llamado_lista ll ON ll.socio_id = s.id
             WHERE s.activo = 1
             GROUP BY s.id, s.numero, s.nombre_completo
             HAVING total_ahorrado > 0
             ORDER BY total_ahorrado DESC, s.numero ASC'
        )->fetchAll();

        // 5. Detalle de actividades especiales
        $actividadesDetalle = $this->db->query(
            'SELECT a.id, a.nombre, a.fecha,
                    COALESCE((SELECT SUM(valor) FROM actividad_movimientos WHERE actividad_id = a.id AND tipo = "ingreso"), 0) AS ingresos,
                    COALESCE((SELECT SUM(valor) FROM actividad_movimientos WHERE actividad_id = a.id AND tipo = "gasto"), 0) AS gastos,
                    (SELECT COUNT(*) FROM actividad_participantes WHERE actividad_id = a.id) AS participantes
             FROM actividades a
             ORDER BY a.fecha DESC'
        )->fetchAll();

        foreach ($actividadesDetalle as &$act) {
            $act['neto'] = (int) $act['ingresos'] - (int) $act['gastos'];
        }
        unset($act);

        return [
            'intereses' => $interesesDetalle,
            'capital_calle' => $capitalCalle,
            'cuotas' => $cuotasDetalle,
            'ahorro' => $ahorroDetalle,
            'actividades' => $actividadesDetalle,
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
