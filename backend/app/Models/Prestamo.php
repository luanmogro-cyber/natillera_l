<?php
declare(strict_types=1);

namespace Natillera\App\Models;

use DateTimeImmutable;
use Natillera\Core\Model;

final class Prestamo extends Model
{
    public function listar(?int $socioId = null): array
    {
        $sql = 'SELECT prestamos.id, prestamos.socio_id, prestamos.prestatario_tercero,
                       prestamos.monto, prestamos.saldo_capital, prestamos.tasa_mensual_bp,
                       prestamos.fecha_entrega, prestamos.plazo_meses, prestamos.fecha_vencimiento,
                       prestamos.fecha_liquidacion, prestamos.interes_recibido, prestamos.mora_recibida,
                       prestamos.estado, prestamos.foto_evidencia, prestamos.firma_evidencia,
                       prestamos.aprobado_por, prestamos.fecha_aprobacion, prestamos.observacion_aprobacion,
                       socios.nombre_completo AS socio_nombre
                FROM prestamos
                INNER JOIN socios ON socios.id = prestamos.socio_id';

        if ($socioId !== null) {
            $sql .= ' WHERE prestamos.socio_id = :socio_id';
        }

        $sql .= ' ORDER BY CASE prestamos.estado
                              WHEN "pendiente" THEN 0
                              WHEN "activo"    THEN 1
                              ELSE 2
                           END,
                           prestamos.fecha_entrega DESC, prestamos.id DESC';

        $statement = $this->db->prepare($sql);
        if ($socioId !== null) {
            $statement->execute(['socio_id' => $socioId]);
        } else {
            $statement->execute();
        }

        $prestamos = $statement->fetchAll();
        $hoy = new DateTimeImmutable('today');

        foreach ($prestamos as &$prestamo) {
            $this->enriquecerPrestamo($prestamo, $hoy);
        }
        unset($prestamo);

        return $prestamos;
    }

    public function buscar(int $id): ?array
    {
        $statement = $this->db->prepare(
            'SELECT prestamos.*, socios.nombre_completo AS socio_nombre
             FROM prestamos
             INNER JOIN socios ON socios.id = prestamos.socio_id
             WHERE prestamos.id = :id'
        );
        $statement->execute(['id' => $id]);
        $prestamo = $statement->fetch();
        if ($prestamo === false) {
            return null;
        }

        $this->enriquecerPrestamo($prestamo, new DateTimeImmutable('today'));
        $prestamo['pagos'] = $this->pagosPorPrestamo($id);

        return $prestamo;
    }

    private function enriquecerPrestamo(array &$prestamo, DateTimeImmutable $hoy): void
    {
        // Para préstamos pendientes no calcular mora/intereses completos
        if (($prestamo['estado'] ?? '') === 'pendiente') {
            $monto = (int) $prestamo['monto'];
            $prestamo['saldo_capital']       = $monto;
            $prestamo['interes_estimado']    = 0;
            $prestamo['interes_pendiente']   = 0;
            $prestamo['en_mora']             = false;
            $prestamo['dias_retraso']        = 0;
            $prestamo['meses_mora']          = 0;
            $prestamo['mora_estimada']       = 0;
            $prestamo['mora_pendiente']      = 0;
            $prestamo['total_a_liquidar']    = 0;
            $prestamo['meses_ordinarios']    = 0;
            if (empty($prestamo['plazo_meses'])) {
                $prestamo['plazo_meses'] = 1;
            }
            if (empty($prestamo['fecha_vencimiento'])) {
                $fechaEntrega = new DateTimeImmutable($prestamo['fecha_entrega']);
                $prestamo['fecha_vencimiento'] = $fechaEntrega->modify('+' . $prestamo['plazo_meses'] . ' month')->format('Y-m-d');
            }
            return;
        }

        $monto = (int) $prestamo['monto'];
        $saldoCapital = (int) ($prestamo['saldo_capital'] ?: $monto);
        $prestamo['saldo_capital'] = $saldoCapital;
        $tasaBp = (int) $prestamo['tasa_mensual_bp'];
        $plazoMeses = max(1, (int) ($prestamo['plazo_meses'] ?: 1));
        $prestamo['plazo_meses'] = $plazoMeses;

        $fechaEntrega = new DateTimeImmutable($prestamo['fecha_entrega']);
        if (empty($prestamo['fecha_vencimiento'])) {
            $fechaVenc = $fechaEntrega->modify("+{$plazoMeses} month");
            $prestamo['fecha_vencimiento'] = $fechaVenc->format('Y-m-d');
        } else {
            $fechaVenc = new DateTimeImmutable($prestamo['fecha_vencimiento']);
        }

        $fechaCorte = $prestamo['estado'] === 'pagado' && !empty($prestamo['fecha_liquidacion'])
            ? new DateTimeImmutable($prestamo['fecha_liquidacion'])
            : $hoy;

        // Meses ordinarios transcurridos (mínimo 1 si ya inició)
        $mesesOrdinarios = max(1, $this->calcularMeses($fechaEntrega, $fechaCorte));
        $prestamo['meses_ordinarios'] = $mesesOrdinarios;

        // Interés ordinario estimado (sobre el saldo de capital)
        $interesEstimado = (int) round(($saldoCapital * $tasaBp / 10000) * $mesesOrdinarios);
        $prestamo['interes_estimado'] = $interesEstimado;
        $interesRecibido = (int) $prestamo['interes_recibido'];
        $prestamo['interes_pendiente'] = max(0, $interesEstimado - $interesRecibido);

        // Cálculo de mora: 10% mensual sobre el monto original si pasó la fecha de vencimiento
        if ($prestamo['estado'] === 'activo' && $fechaCorte > $fechaVenc) {
            $diasRetraso = $fechaVenc->diff($fechaCorte)->days;
            // Cada mes cumplido (o fracción del primer mes pasado el vencimiento) cobra 10%
            $mesesMora = max(1, (int) ceil($diasRetraso / 30));
            $moraEstimada = (int) round(($monto * 10 / 100) * $mesesMora);
            $prestamo['en_mora'] = true;
            $prestamo['dias_retraso'] = $diasRetraso;
            $prestamo['meses_mora'] = $mesesMora;
            $prestamo['mora_estimada'] = $moraEstimada;
        } else {
            $prestamo['en_mora'] = false;
            $prestamo['dias_retraso'] = 0;
            $prestamo['meses_mora'] = 0;
            $prestamo['mora_estimada'] = 0;
        }

        $moraRecibida = (int) ($prestamo['mora_recibida'] ?? 0);
        $prestamo['mora_pendiente'] = max(0, $prestamo['mora_estimada'] - $moraRecibida);

        if ($prestamo['estado'] === 'activo') {
            $prestamo['total_a_liquidar'] = $saldoCapital + $prestamo['interes_pendiente'] + $prestamo['mora_pendiente'];
        } else {
            $prestamo['total_a_liquidar'] = 0;
        }
    }

    public function datosCredito(int $socioId): array
    {
        $statement = $this->db->prepare(
            'SELECT limite, tasa_mensual_bp FROM creditos_socio WHERE socio_id = :socio_id'
        );
        $statement->execute(['socio_id' => $socioId]);
        $configuracion = $statement->fetch();

        return $configuracion === false
            ? ['limite' => 2000000, 'tasa_mensual_bp' => 1000]
            : ['limite' => (int) $configuracion['limite'], 'tasa_mensual_bp' => (int) $configuracion['tasa_mensual_bp']];
    }

    public function saldoActivo(int $socioId): int
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(CASE WHEN saldo_capital > 0 THEN saldo_capital ELSE monto END), 0)
             FROM prestamos WHERE socio_id = :socio_id AND estado = "activo"'
        );
        $statement->execute(['socio_id' => $socioId]);

        return (int) $statement->fetchColumn();
    }

    public function saldoComprometido(int $socioId, ?int $excluirPrestamoId = null): int
    {
        $sql = 'SELECT COALESCE(SUM(CASE WHEN saldo_capital > 0 THEN saldo_capital ELSE monto END), 0)
                FROM prestamos
                WHERE socio_id = :socio_id AND estado IN ("activo", "pendiente")';
        $params = ['socio_id' => $socioId];
        if ($excluirPrestamoId !== null) {
            $sql .= ' AND id <> :excluir_id';
            $params['excluir_id'] = $excluirPrestamoId;
        }
        $statement = $this->db->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function crear(
        int $socioId,
        ?string $tercero,
        int $monto,
        int $tasaBp,
        string $fecha,
        int $plazoMeses = 1,
        ?string $fotoEvidencia = null,
        ?string $firmaEvidencia = null
    ): int {
        $fechaEntrega = new DateTimeImmutable($fecha);
        $fechaVencimiento = $fechaEntrega->modify("+{$plazoMeses} month")->format('Y-m-d');

        $statement = $this->db->prepare(
             'INSERT INTO prestamos (socio_id, prestatario_tercero, monto, saldo_capital, tasa_mensual_bp,
                                    fecha_entrega, plazo_meses, fecha_vencimiento, foto_evidencia, firma_evidencia, estado)
              VALUES (:socio_id, :prestatario_tercero, :monto, :saldo_capital, :tasa_mensual_bp,
                      :fecha_entrega, :plazo_meses, :fecha_vencimiento, :foto_evidencia, :firma_evidencia, "pendiente")'
        );
        $statement->execute([
            'socio_id' => $socioId,
            'prestatario_tercero' => $tercero,
            'monto' => $monto,
            'saldo_capital' => $monto,
            'tasa_mensual_bp' => $tasaBp,
            'fecha_entrega' => $fecha,
            'plazo_meses' => $plazoMeses,
            'fecha_vencimiento' => $fechaVencimiento,
            'foto_evidencia' => $fotoEvidencia,
            'firma_evidencia' => $firmaEvidencia,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function aprobar(int $prestamoId, int $usuarioId, string $observacion = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE prestamos
             SET estado = \'activo\',
                 aprobado_por = :aprobado_por,
                 fecha_aprobacion = NOW(),
                 observacion_aprobacion = :obs
             WHERE id = :id AND estado = \'pendiente\''
        );
        $stmt->execute([
            'aprobado_por' => $usuarioId,
            'obs'          => $observacion !== '' ? $observacion : null,
            'id'           => $prestamoId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function rechazar(int $prestamoId, int $usuarioId, string $observacion = ''): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE prestamos
             SET estado = \'rechazado\',
                 aprobado_por = :aprobado_por,
                 fecha_aprobacion = NOW(),
                 observacion_aprobacion = :obs
             WHERE id = :id AND estado = \'pendiente\''
        );
        $stmt->execute([
            'aprobado_por' => $usuarioId,
            'obs'          => $observacion !== '' ? $observacion : null,
            'id'           => $prestamoId,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function registrarPago(
        int $prestamoId,
        int $usuarioId,
        string $fechaPago,
        int $abonoCapital,
        int $abonoInteres,
        int $abonoMora,
        string $observacion = ''
    ): array {
        $totalPagado = $abonoCapital + $abonoInteres + $abonoMora;
        if ($totalPagado <= 0) {
            return ['ok' => false, 'error' => 'El valor total del pago debe ser mayor a cero.'];
        }

        $prestamo = $this->buscar($prestamoId);
        if ($prestamo === null || $prestamo['estado'] !== 'activo') {
            return ['ok' => false, 'error' => 'El préstamo no está activo o no existe.'];
        }

        $nuevoSaldoCapital = max(0, (int)$prestamo['saldo_capital'] - $abonoCapital);
        $nuevoInteresRecibido = (int)$prestamo['interes_recibido'] + $abonoInteres;
        $nuevaMoraRecibida = (int)($prestamo['mora_recibida'] ?? 0) + $abonoMora;

        $tipoPago = 'mixto';
        if ($nuevoSaldoCapital === 0) {
            $tipoPago = 'liquidacion';
        } elseif ($abonoCapital > 0 && $abonoInteres === 0 && $abonoMora === 0) {
            $tipoPago = 'capital';
        } elseif ($abonoInteres > 0 && $abonoCapital === 0 && $abonoMora === 0) {
            $tipoPago = 'interes';
        } elseif ($abonoMora > 0 && $abonoCapital === 0 && $abonoInteres === 0) {
            $tipoPago = 'mora';
        }

        $this->db->beginTransaction();
        try {
            $stmtPago = $this->db->prepare(
                'INSERT INTO prestamo_pagos (prestamo_id, registrado_por, fecha_pago, tipo_pago,
                                             abono_capital, abono_interes, abono_mora, total_pagado, observacion)
                 VALUES (:prestamo_id, :registrado_por, :fecha_pago, :tipo_pago,
                         :abono_capital, :abono_interes, :abono_mora, :total_pagado, :observacion)'
            );
            $stmtPago->execute([
                'prestamo_id' => $prestamoId,
                'registrado_por' => $usuarioId,
                'fecha_pago' => $fechaPago,
                'tipo_pago' => $tipoPago,
                'abono_capital' => $abonoCapital,
                'abono_interes' => $abonoInteres,
                'abono_mora' => $abonoMora,
                'total_pagado' => $totalPagado,
                'observacion' => $observacion !== '' ? $observacion : null,
            ]);

            $nuevoEstado = $nuevoSaldoCapital === 0 ? 'pagado' : 'activo';
            $fechaLiquidacion = $nuevoSaldoCapital === 0 ? $fechaPago : null;

            $stmtPrestamo = $this->db->prepare(
                'UPDATE prestamos
                 SET saldo_capital = :saldo_capital,
                     interes_recibido = :interes_recibido,
                     mora_recibida = :mora_recibida,
                     estado = :estado,
                     fecha_liquidacion = :fecha_liquidacion
                 WHERE id = :id'
            );
            $stmtPrestamo->execute([
                'saldo_capital' => $nuevoSaldoCapital,
                'interes_recibido' => $nuevoInteresRecibido,
                'mora_recibida' => $nuevaMoraRecibida,
                'estado' => $nuevoEstado,
                'fecha_liquidacion' => $fechaLiquidacion,
                'id' => $prestamoId,
            ]);

            $this->db->commit();
            return [
                'ok' => true,
                'saldo_restante' => $nuevoSaldoCapital,
                'pagado_completamente' => $nuevoEstado === 'pagado'
            ];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function pagosPorPrestamo(int $prestamoId): array
    {
        $statement = $this->db->prepare(
            'SELECT prestamo_pagos.*, usuarios.nombre AS registrado_por_nombre, usuarios.rol AS registrado_por_rol
             FROM prestamo_pagos
             INNER JOIN usuarios ON usuarios.id = prestamo_pagos.registrado_por
             WHERE prestamo_pagos.prestamo_id = :prestamo_id
             ORDER BY prestamo_pagos.fecha_pago DESC, prestamo_pagos.id DESC'
        );
        $statement->execute(['prestamo_id' => $prestamoId]);
        return $statement->fetchAll();
    }

    public function interesesRecibidos(int $socioId, int $ano): int
    {
        $statement = $this->db->prepare(
            'SELECT COALESCE(SUM(interes_recibido + mora_recibida), 0)
             FROM prestamos
             WHERE socio_id = :socio_id
               AND fecha_entrega >= :desde
               AND fecha_entrega < :hasta'
        );
        $statement->execute([
            'socio_id' => $socioId,
            'desde' => sprintf('%04d-01-01', $ano),
            'hasta' => sprintf('%04d-01-01', $ano + 1),
        ]);

        return (int) $statement->fetchColumn();
    }

    public function guardarConfiguracion(int $socioId, int $limite, int $tasaBp): void
    {
        $statement = $this->db->prepare(
            'INSERT INTO creditos_socio (socio_id, limite, tasa_mensual_bp, updated_at)
             VALUES (:socio_id, :limite, :tasa_mensual_bp, CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE
                limite = VALUES(limite),
                tasa_mensual_bp = VALUES(tasa_mensual_bp),
                updated_at = CURRENT_TIMESTAMP'
        );
        $statement->execute([
            'socio_id' => $socioId,
            'limite' => $limite,
            'tasa_mensual_bp' => $tasaBp,
        ]);
    }

    public function calcularMeses(DateTimeImmutable $inicio, DateTimeImmutable $fin): int
    {
        $meses = ((int) $fin->format('Y') - (int) $inicio->format('Y')) * 12
            + (int) $fin->format('n') - (int) $inicio->format('n');
        if ((int) $fin->format('j') < (int) $inicio->format('j')) {
            $meses--;
        }

        return max(0, $meses);
    }
}
