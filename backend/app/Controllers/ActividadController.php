<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use DateTimeImmutable;
use Natillera\App\Models\Actividad;
use Natillera\Core\Controller;

final class ActividadController extends Controller
{
    private const ROLES = ['Presidente', 'Secretaria Actividades'];

    public function index(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $modelo = new Actividad($this->db);
        $actividades = $modelo->todas();
        $actividadId = filter_input(INPUT_GET, 'actividad_id', FILTER_VALIDATE_INT);
        $actividad = null;
        if (is_int($actividadId)) {
            foreach ($actividades as $item) {
                if ((int) $item['id'] === $actividadId) {
                    $actividad = $item;
                    break;
                }
            }
        } elseif ($actividades !== []) {
            $actividad = $actividades[0];
        }

        $this->render('admin/actividades', [
            'pageTitle' => 'Actividades',
            'activePage' => 'actividades',
            'actividades' => $actividades,
            'participantes' => $modelo->participantes(),
            'actividadSeleccionada' => $actividad,
            'movimientos' => $actividad === null ? [] : $modelo->movimientos((int) $actividad['id']),
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/actividades');
        }

        $accion = (string) ($_POST['accion'] ?? '');
        if ($accion === 'movimiento') {
            $this->guardarMovimiento();
            return;
        }

        if ($accion === 'eliminar_movimiento') {
            $this->eliminarMovimiento();
            return;
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $fecha = (string) ($_POST['fecha'] ?? '');
        $ids = $_POST['participantes'] ?? [];
        $fechaValida = DateTimeImmutable::createFromFormat('!Y-m-d', $fecha);
        if (
            $nombre === '' || mb_strlen($nombre, 'UTF-8') > 100
            || $fechaValida === false || $fechaValida->format('Y-m-d') !== $fecha
            || $fecha > date('Y-m-d')
            || !is_array($ids) || $ids === []
        ) {
            $this->flash('danger', 'Escribe un nombre, una fecha válida y marca al menos un participante.');
            $this->redirect('/admin/actividades');
        }

        $ids = array_values(array_unique(array_filter(
            array_map(static fn(mixed $id): int|false => filter_var($id, FILTER_VALIDATE_INT), $ids),
            static fn(int|false $id): bool => is_int($id)
        )));
        $activos = array_fill_keys(array_map(
            static fn(array $socio): int => (int) $socio['id'],
            (new Actividad($this->db))->participantes()
        ), true);
        if ($ids === [] || array_diff($ids, array_keys($activos)) !== []) {
            $this->flash('danger', 'La selección contiene participantes inválidos o inactivos.');
            $this->redirect('/admin/actividades');
        }

        (new Actividad($this->db))->crear($nombre, $fecha, $ids);
        $this->flash('success', 'La actividad quedó creada con sus participantes.');
        $this->redirect('/admin/actividades');
    }

    private function guardarMovimiento(): void
    {
        $actividadId = filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT);
        $valorRaw = str_replace(['.', ',', ' '], '', (string) ($_POST['valor'] ?? ''));
        $valor = filter_var($valorRaw, FILTER_VALIDATE_INT);
        $tipo = (string) ($_POST['tipo'] ?? '');
        $concepto = trim((string) ($_POST['concepto'] ?? ''));
        $idsValidos = array_map(
            static fn(array $actividad): int => (int) $actividad['id'],
            (new Actividad($this->db))->todas()
        );

        if (
            !is_int($actividadId) || !in_array($actividadId, $idsValidos, true)
            || !is_int($valor) || $valor <= 0
            || !in_array($tipo, ['ingreso', 'gasto'], true)
            || $concepto === '' || mb_strlen($concepto, 'UTF-8') > 120
        ) {
            $this->flash('danger', 'Verifica la actividad, tipo, concepto y valor del movimiento.');
            $this->redirect('/admin/actividades' . (is_int($actividadId) ? '?actividad_id=' . $actividadId : ''));
        }

        (new Actividad($this->db))->agregarMovimiento($actividadId, $tipo, $concepto, $valor);
        $this->flash('success', 'Movimiento registrado correctamente.');
        $this->redirect('/admin/actividades?actividad_id=' . $actividadId);
    }

    private function eliminarMovimiento(): void
    {
        $actividadId = filter_var($_POST['actividad_id'] ?? null, FILTER_VALIDATE_INT);
        $movimientoId = filter_var($_POST['movimiento_id'] ?? null, FILTER_VALIDATE_INT);

        if (is_int($movimientoId) && (new Actividad($this->db))->eliminarMovimiento($movimientoId)) {
            $this->flash('success', 'Movimiento eliminado.');
        } else {
            $this->flash('danger', 'No se pudo eliminar el movimiento.');
        }

        $this->redirect('/admin/actividades' . (is_int($actividadId) ? '?actividad_id=' . $actividadId : ''));
    }
}
