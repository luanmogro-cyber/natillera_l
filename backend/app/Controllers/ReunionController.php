<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Reunion;
use Natillera\Core\Controller;

final class ReunionController extends Controller
{
    private const ROLES = ['Presidente', 'Secretaria General'];

    public function index(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('admin/reuniones', [
            'pageTitle' => 'Reuniones',
            'activePage' => 'reuniones',
            'reuniones' => (new Reunion($this->db))->todas(),
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function crear(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/reuniones');
        }

        $fecha = (string) ($_POST['fecha'] ?? '');
        $hora = (string) ($_POST['hora'] ?? '');
        $valorCuota = filter_var($_POST['valor_cuota'] ?? null, FILTER_VALIDATE_INT);
        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $fechaValida = \DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        $horaValida = \DateTimeImmutable::createFromFormat('!H:i', $hora);

        if (
            $fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha
            || $horaValida === false || $horaValida->format('H:i') !== $hora
            || !in_array($valorCuota, [55000, 60000, 65000], true)
            || !in_array($concepto, ['Cuota regular', 'Cuota + Rifa', 'Cuota + Ronda'], true)
        ) {
            $this->flash('danger', 'Verifica la fecha, hora, concepto y valor de la cuota.');
            $this->redirect('/admin/reuniones');
        }

        $valoresPorConcepto = [
            'Cuota regular' => 55000,
            'Cuota + Rifa' => 60000,
            'Cuota + Ronda' => 65000,
        ];
        if ($valoresPorConcepto[$concepto] !== $valorCuota) {
            $this->flash('danger', 'El valor no coincide con la cuota del concepto seleccionado.');
            $this->redirect('/admin/reuniones');
        }

        (new Reunion($this->db))->crear($fecha, $hora, $valorCuota, $concepto);
        $this->flash('success', 'La reunión quedó programada.');
        $this->redirect('/admin/reuniones');
    }
}
