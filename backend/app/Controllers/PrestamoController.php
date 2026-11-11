<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use DateTimeImmutable;
use Natillera\App\Models\Prestamo;
use Natillera\App\Models\Socio;
use Natillera\Core\Controller;

final class PrestamoController extends Controller
{
    private const ADMIN_ROLES = ['Presidente', 'Tesorera'];
    private const CREDIT_ROLES = ['Presidente', 'Secretaria General'];
    private const APPROVAL_ROLES = ['Presidente']; // Solo el Presidente puede aprobar préstamos

    public function index(): void
    {
        if (!$this->requireRoles(self::ADMIN_ROLES)) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $modelo = new Prestamo($this->db);
        $socios = (new Socio($this->db))->activos();
        $ano = (int) date('Y');
        foreach ($socios as &$socio) {
            $id = (int) $socio['id'];
            $socio['credito'] = $modelo->datosCredito($id);
            $socio['saldo_activo'] = $modelo->saldoComprometido($id);
            $socio['intereses_ano'] = $modelo->interesesRecibidos($id, $ano);
        }
        unset($socio);

        $prestamos = $modelo->listar();
        foreach ($prestamos as &$p) {
            $p['pagos'] = $modelo->pagosPorPrestamo((int) $p['id']);
        }
        unset($p);

        $this->render('admin/prestamos', [
            'pageTitle' => 'Préstamos',
            'activePage' => 'prestamos',
            'socios' => $socios,
            'prestamos' => $prestamos,
            'metaInteres' => 400000,
            'ano' => $ano,
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function crear(): void
    {
        if (!$this->requireRoles(self::ADMIN_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/prestamos');
        }

        $socioId = filter_var($_POST['socio_id'] ?? null, FILTER_VALIDATE_INT);
        $montoRaw = str_replace(['.', ',', ' '], '', (string) ($_POST['monto'] ?? ''));
        $monto = filter_var($montoRaw, FILTER_VALIDATE_INT);
        $fecha = (string) ($_POST['fecha_entrega'] ?? '');
        $plazoMeses = filter_var($_POST['plazo_meses'] ?? 1, FILTER_VALIDATE_INT);
        if ($plazoMeses === false || $plazoMeses < 1) {
            $plazoMeses = 1;
        }
        $tercero = trim((string) ($_POST['prestatario_tercero'] ?? ''));
        $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);

        if (
            !is_int($socioId) || !is_int($monto) || $monto <= 0
            || $fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha
            || $fecha > date('Y-m-d')
            || ($tercero !== '' && mb_strlen($tercero, 'UTF-8') > 120)
        ) {
            $this->flash('danger', 'Verifica el socio responsable, el monto, la fecha y el nombre del tercero.');
            $this->redirect('/admin/prestamos');
        }

        $idsActivos = array_map(
            static fn(array $socio): int => (int) $socio['id'],
            (new Socio($this->db))->activos()
        );
        if (!in_array($socioId, $idsActivos, true)) {
            $this->flash('danger', 'El socio responsable seleccionado no está activo.');
            $this->redirect('/admin/prestamos');
        }

        $modelo = new Prestamo($this->db);
        $credito = $modelo->datosCredito($socioId);
        $disponible = max(0, $credito['limite'] - $modelo->saldoComprometido($socioId));
        if ($disponible <= 0 || $monto > $disponible) {
            $this->flash(
                'danger',
                'No se puede registrar el préstamo: el monto ($' . number_format($monto, 0, ',', '.')
                . ') supera el cupo disponible del socio ($' . number_format($disponible, 0, ',', '.') . ').'
            );
            $this->redirect('/admin/prestamos');
        }

        // Procesar evidencia: Foto
        $fotoPath = $this->guardarFotoSubida($_FILES['foto_evidencia'] ?? null);

        // Procesar evidencia: Firma Digital
        $firmaPath = $this->guardarFirmaBase64((string) ($_POST['firma_evidencia'] ?? ''));

        $prestamoId = $modelo->crear(
            $socioId,
            $tercero === '' ? null : $tercero,
            $monto,
            $credito['tasa_mensual_bp'],
            $fecha,
            $plazoMeses,
            $fotoPath,
            $firmaPath
        );

        // Auto-aprobar si es el Presidente quien registra; Tesorera lo deja pendiente de confirmación
        $rolActual = $_SESSION['usuario']['rol'] ?? '';
        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 1);
        if ($rolActual === 'Presidente' && $prestamoId > 0) {
            $modelo->aprobar($prestamoId, $usuarioId, 'Aprobado al registrar por Presidencia');
            $this->flash('success', 'Préstamo de $' . number_format($monto, 0, ',', '.') . " registrado y aprobado automáticamente. Plazo: {$plazoMeses} mes(es).");
        } else {
            $this->flash('info', 'Préstamo #' . $prestamoId . ' de $' . number_format($monto, 0, ',', '.') . ' registrado. Queda pendiente de confirmación del Presidente.');
        }
        $this->redirect('/admin/prestamos');
    }

    public function aprobarPrestamo(): void
    {
        if (!$this->requireRoles(self::APPROVAL_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/prestamos');
        }

        $prestamoId = filter_var($_POST['prestamo_id'] ?? null, FILTER_VALIDATE_INT);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if (!is_int($prestamoId)) {
            $this->flash('danger', 'Préstamo inválido.');
            $this->redirect('/admin/prestamos');
        }

        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 1);
        $modelo = new Prestamo($this->db);
        $prestamo = $modelo->buscar($prestamoId);
        if ($prestamo === null || $prestamo['estado'] !== 'pendiente') {
            $this->flash('warning', 'No se pudo aprobar el préstamo. Puede que ya esté aprobado o no exista.');
            $this->redirect('/admin/prestamos');
        }

        $credito = $modelo->datosCredito((int) $prestamo['socio_id']);
        $disponible = max(0, $credito['limite'] - $modelo->saldoComprometido((int) $prestamo['socio_id'], $prestamoId));
        if ((int) $prestamo['monto'] > $disponible) {
            $this->flash(
                'danger',
                'No se puede aprobar el préstamo #' . $prestamoId . ': el monto ($'
                . number_format((int) $prestamo['monto'], 0, ',', '.')
                . ') supera el cupo disponible del socio ($' . number_format($disponible, 0, ',', '.') . ').'
            );
            $this->redirect('/admin/prestamos');
        }

        $ok = $modelo->aprobar($prestamoId, $usuarioId, $observacion);

        if ($ok) {
            $this->flash('success', "Préstamo #$prestamoId aprobado exitosamente. El préstamo ya está activo.");
        } else {
            $this->flash('warning', 'No se pudo aprobar el préstamo. Puede que ya esté aprobado o no exista.');
        }
        $this->redirect('/admin/prestamos');
    }

    public function rechazarPrestamo(): void
    {
        if (!$this->requireRoles(self::APPROVAL_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/prestamos');
        }

        $prestamoId = filter_var($_POST['prestamo_id'] ?? null, FILTER_VALIDATE_INT);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if (!is_int($prestamoId)) {
            $this->flash('danger', 'Préstamo inválido.');
            $this->redirect('/admin/prestamos');
        }

        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 1);
        $ok = (new Prestamo($this->db))->rechazar($prestamoId, $usuarioId, $observacion);

        if ($ok) {
            $this->flash('warning', "Préstamo #$prestamoId rechazado. Se notificará al solicitante.");
        } else {
            $this->flash('warning', 'No se pudo rechazar. Puede que ya esté procesado.');
        }
        $this->redirect('/admin/prestamos');
    }

    public function pago(): void
    {
        if (!$this->requireRoles(self::ADMIN_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/prestamos');
        }

        $prestamoId = filter_var($_POST['prestamo_id'] ?? null, FILTER_VALIDATE_INT);
        $fechaPago = (string) ($_POST['fecha_pago'] ?? date('Y-m-d'));
        $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fechaPago);

        $abonoCapital = (int) filter_var(str_replace(['.', ',', ' '], '', (string)($_POST['abono_capital'] ?? '0')), FILTER_VALIDATE_INT);
        $abonoInteres = (int) filter_var(str_replace(['.', ',', ' '], '', (string)($_POST['abono_interes'] ?? '0')), FILTER_VALIDATE_INT);
        $abonoMora = (int) filter_var(str_replace(['.', ',', ' '], '', (string)($_POST['abono_mora'] ?? '0')), FILTER_VALIDATE_INT);
        $observacion = trim((string) ($_POST['observacion'] ?? ''));

        if (!is_int($prestamoId) || $fechaValida === false) {
            $this->flash('danger', 'Préstamo o fecha de pago inválida.');
            $this->redirect('/admin/prestamos');
        }

        if (($abonoCapital + $abonoInteres + $abonoMora) <= 0) {
            $this->flash('danger', 'Debe ingresar al menos un valor de pago o abono mayor a cero.');
            $this->redirect('/admin/prestamos');
        }

        $usuarioId = (int) ($_SESSION['usuario']['id'] ?? 1);
        $res = (new Prestamo($this->db))->registrarPago(
            $prestamoId,
            $usuarioId,
            $fechaPago,
            $abonoCapital,
            $abonoInteres,
            $abonoMora,
            $observacion
        );

        if (!$res['ok']) {
            $this->flash('danger', $res['error'] ?? 'No fue posible registrar el pago.');
        } else {
            $msg = 'Pago registrado exitosamente.';
            if ($res['pagado_completamente']) {
                $msg .= ' El préstamo quedó completamente pagado y liquidado.';
            } else {
                $msg .= ' Saldo restante de capital: $' . number_format((int)$res['saldo_restante'], 0, ',', '.') . '.';
            }
            $this->flash('success', $msg);
        }

        $this->redirect('/admin/prestamos');
    }

    public function exportar(): void
    {
        if (!$this->requireRoles(self::ADMIN_ROLES)) {
            return;
        }

        $prestamos = (new Prestamo($this->db))->listar();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cartera_prestamos_' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, [
            'ID', 'Socio Fiador', 'Prestatario Tercero', 'Estado', 'Monto Inicial',
            'Saldo Capital', 'Tasa Mensual', 'Fecha Entrega', 'Plazo (meses)',
            'Fecha Vencimiento', 'Interés Pagado', 'Mora Pagada', 'Total Pendiente Hoy'
        ], ';');

        foreach ($prestamos as $p) {
            fputcsv($out, [
                (int) $p['id'],
                $p['socio_nombre'],
                $p['prestatario_tercero'] ?? '',
                $p['estado'],
                (int) $p['monto'],
                (int) $p['saldo_capital'],
                number_format((int) $p['tasa_mensual_bp'] / 100, 2, ',', '') . '%',
                $p['fecha_entrega'],
                (int) $p['plazo_meses'],
                $p['fecha_vencimiento'] ?? '',
                (int) $p['interes_recibido'],
                (int) ($p['mora_recibida'] ?? 0),
                (int) ($p['total_a_liquidar'] ?? 0),
            ], ';');
        }
        fclose($out);
    }

    public function adjuntarEvidencia(): void
    {
        if (!$this->requireRoles(self::ADMIN_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció.');
            $this->redirect('/admin/prestamos');
        }

        $prestamoId = filter_var($_POST['prestamo_id'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($prestamoId)) {
            $this->flash('danger', 'Préstamo inválido.');
            $this->redirect('/admin/prestamos');
        }

        $fotoPath = $this->guardarFotoSubida($_FILES['foto_evidencia'] ?? null);
        $firmaPath = $this->guardarFirmaBase64((string) ($_POST['firma_evidencia'] ?? ''));

        if ($fotoPath === null && $firmaPath === null) {
            $this->flash('warning', 'No se envió ninguna foto ni firma para guardar.');
            $this->redirect('/admin/prestamos');
        }

        $updates = [];
        $params = ['id' => $prestamoId];
        if ($fotoPath !== null) {
            $updates[] = 'foto_evidencia = :foto';
            $params['foto'] = $fotoPath;
        }
        if ($firmaPath !== null) {
            $updates[] = 'firma_evidencia = :firma';
            $params['firma'] = $firmaPath;
        }

        $sql = 'UPDATE prestamos SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $this->flash('success', 'Evidencia del préstamo actualizada.');
        $this->redirect('/admin/prestamos');
    }

    public function creditos(): void
    {
        if (!$this->requireRoles(self::CREDIT_ROLES)) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $modelo = new Prestamo($this->db);
        $socios = (new Socio($this->db))->activos();
        foreach ($socios as &$socio) {
            $socio['credito'] = $modelo->datosCredito((int) $socio['id']);
            $socio['saldo_activo'] = $modelo->saldoComprometido((int) $socio['id']);
        }
        unset($socio);

        $this->render('admin/creditos', [
            'pageTitle' => 'Topes de crédito',
            'activePage' => 'creditos',
            'socios' => $socios,
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function guardarCredito(): void
    {
        if (!$this->requireRoles(self::CREDIT_ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/creditos');
        }

        $socioId = filter_var($_POST['socio_id'] ?? null, FILTER_VALIDATE_INT);
        $limiteRaw = str_replace(['.', ',', ' '], '', (string) ($_POST['limite'] ?? '0'));
        $limite = filter_var($limiteRaw, FILTER_VALIDATE_INT);
        $tasa = trim((string) ($_POST['tasa'] ?? ''));
        if (!is_int($socioId) || !is_int($limite) || $limite < 0 || !preg_match('/^\d{1,2}(?:[.,]\d{1,2})?$/', $tasa)) {
            $this->flash('danger', 'Ingresa un socio, un límite no negativo y una tasa entre 0,00% y 50,00%.');
            $this->redirect('/admin/creditos');
        }

        $tasa = str_replace(',', '.', $tasa);
        [$entero, $decimal] = array_pad(explode('.', $tasa, 2), 2, '');
        $tasaBp = (int) $entero * 100 + (int) str_pad($decimal, 2, '0');
        $activos = (new Socio($this->db))->activos();
        $sociosPorId = [];
        foreach ($activos as $s) {
            $sociosPorId[(int) $s['id']] = (string) $s['nombre_completo'];
        }
        if ($tasaBp > 5000 || !isset($sociosPorId[$socioId])) {
            $this->flash('danger', 'El socio debe estar activo y la tasa no puede superar el 50%.');
            $this->redirect('/admin/creditos');
        }

        (new Prestamo($this->db))->guardarConfiguracion($socioId, $limite, $tasaBp);
        $tasaTexto = number_format($tasaBp / 100, 2, ',', '.');
        $limiteTexto = number_format($limite, 0, ',', '.');
        $this->flash(
            'success',
            "✓ Crédito actualizado para {$sociosPorId[$socioId]}: límite máximo de \${$limiteTexto} con tasa del {$tasaTexto}% mensual."
        );
        $this->redirect('/admin/creditos');
    }

    private function guardarFotoSubida(?array $archivo): ?string
    {
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return null;
        }

        $permitidos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $archivo['tmp_name']);
        finfo_close($finfo);

        if (!isset($permitidos[$mime])) {
            return null;
        }

        if (($archivo['size'] ?? 0) > 8 * 1024 * 1024) {
            return null;
        }

        $destDir = $this->uploadsPrestamosDir();

        $nombreArchivo = 'foto_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $permitidos[$mime];
        $rutaCompleta = $destDir . '/' . $nombreArchivo;

        if (move_uploaded_file($archivo['tmp_name'], $rutaCompleta)) {
            return '/uploads/prestamos/' . $nombreArchivo;
        }

        return null;
    }

    private function guardarFirmaBase64(string $dataUri): ?string
    {
        $dataUri = trim($dataUri);
        if (!str_starts_with($dataUri, 'data:image/png;base64,')) {
            return null;
        }

        $base64 = substr($dataUri, strlen('data:image/png;base64,'));
        $binario = base64_decode($base64, true);
        if ($binario === false || strlen($binario) < 200) {
            return null;
        }

        $destDir = $this->uploadsPrestamosDir();

        $nombreArchivo = 'firma_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.png';
        $rutaCompleta = $destDir . '/' . $nombreArchivo;

        if (file_put_contents($rutaCompleta, $binario) !== false) {
            return '/uploads/prestamos/' . $nombreArchivo;
        }

        return null;
    }

    private function uploadsPrestamosDir(): string
    {
        $destDir = dirname(__DIR__, 3) . '/frontend/public/uploads/prestamos';
        if (!is_dir($destDir)) {
            mkdir($destDir, 0755, true);
        }
        return $destDir;
    }
}
