<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\LlamadoLista;
use Natillera\App\Models\Reunion;
use Natillera\App\Models\Socio;
use Natillera\Core\Controller;

final class LlamadoListaController extends Controller
{
    public function index(): void
    {
        if (!$this->requireRoles(['Presidente', 'Tesorera'])) {
            return;
        }

        $reunionModel = new Reunion($this->db);
        $llamadoModel = new LlamadoLista($this->db);
        $reuniones = $reunionModel->todas();
        $reunionesGuardadas = $llamadoModel->reunionesGuardadasIds();
        $reunionId = filter_input(INPUT_GET, 'reunion_id', FILTER_VALIDATE_INT);

        if ($reunionId) {
            $reunion = $reunionModel->buscar($reunionId);
        } else {
            // Si no se eligió una reunión específica, abrir primero la próxima reunión pendiente (si existe)
            $pendienteId = $llamadoModel->siguienteReunionPendiente();
            $reunion = $pendienteId !== null ? $reunionModel->buscar($pendienteId) : ($reuniones[0] ?? null);
        }

        $socios = [];
        $yaGuardado = false;

        if ($reunion !== null) {
            $valoresGuardados = $llamadoModel->porReunion((int) $reunion['id']);
            $yaGuardado = $valoresGuardados !== [];
            foreach ((new Socio($this->db))->activos() as $socio) {
                $socioId = (int) $socio['id'];
                $guardado = $valoresGuardados[$socioId] ?? [];
                $socios[] = [
                    'id' => $socioId,
                    'numero' => $socio['numero'] !== null ? (int) $socio['numero'] : null,
                    'nombre_completo' => $socio['nombre_completo'],
                    'rol' => $socio['rol'] ?? 'Socio',
                    'pago_cuota' => (bool) ($guardado['pago_cuota'] ?? false),
                    'ahorro_voluntario' => (int) ($guardado['ahorro_voluntario'] ?? 0),
                    'autoprestamo' => (bool) ($guardado['autoprestamo'] ?? false),
                ];
            }
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('admin/llamado_lista', [
            'pageTitle' => 'Llamado a lista',
            'activePage' => 'llamado',
            'reuniones' => $reuniones,
            'reunionesGuardadas' => $reunionesGuardadas,
            'reunion' => $reunion,
            'yaGuardado' => $yaGuardado,
            'valorCuota' => $reunion === null ? 0 : (int) $reunion['valor_cuota'],
            'socios' => $socios,
            'csrfToken' => $_SESSION['csrf_token'],
        ]);
    }

    public function guardar(): void
    {
        if (!$this->requireRoles(['Presidente', 'Tesorera'])) {
            return;
        }

        $csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$this->verifyCsrf(is_string($csrfToken) ? $csrfToken : null)) {
            $this->json(['error' => 'La sesión venció. Recargue la página e intente de nuevo.'], 419);
            return;
        }

        try {
            $body = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->json(['error' => 'La solicitud no contiene datos JSON válidos.'], 400);
            return;
        }

        if (!is_array($body) || !is_int($body['reunion_id'] ?? null) || !is_array($body['entries'] ?? null)) {
            $this->json(['error' => 'Los datos enviados no tienen el formato esperado.'], 422);
            return;
        }

        $reunionId = $body['reunion_id'];
        if ((new Reunion($this->db))->buscar($reunionId) === null) {
            $this->json(['error' => 'La reunión seleccionada ya no está disponible.'], 404);
            return;
        }

        $socios = (new Socio($this->db))->activos();
        if (count($body['entries']) !== count($socios)) {
            $this->json(['error' => 'La lista de socios cambió. Recargue la página antes de guardar.'], 409);
            return;
        }

        $idsActivos = array_fill_keys(array_map(static fn(array $socio): int => (int) $socio['id'], $socios), true);
        $registros = [];
        foreach ($body['entries'] as $entrada) {
            if (
                !is_array($entrada)
                || !is_int($entrada['socio_id'] ?? null)
                || !is_bool($entrada['pago_cuota'] ?? null)
                || !is_int($entrada['ahorro_voluntario'] ?? null)
                || !is_bool($entrada['autoprestamo'] ?? null)
            ) {
                $this->json(['error' => 'Hay datos de socios incompletos o inválidos.'], 422);
                return;
            }

            $socioId = $entrada['socio_id'];
            if (
                !isset($idsActivos[$socioId])
                || isset($registros[$socioId])
                || $entrada['ahorro_voluntario'] < 0
                || $entrada['autoprestamo'] === $entrada['pago_cuota']
            ) {
                $this->json(['error' => 'Revise los datos: el autopréstamo corresponde a quienes no pagaron la cuota.'], 422);
                return;
            }

            $registros[$socioId] = [
                'socio_id' => $socioId,
                'pago_cuota' => $entrada['pago_cuota'],
                'ahorro_voluntario' => $entrada['ahorro_voluntario'],
                'autoprestamo' => $entrada['autoprestamo'],
            ];
        }

        if (count($registros) !== count($socios)) {
            $this->json(['error' => 'Debe incluir a todos los socios activos en el guardado.'], 422);
            return;
        }

        $llamadoModel = new LlamadoLista($this->db);
        $existentes = $llamadoModel->porReunion($reunionId);
        $yaGuardado = $existentes !== [];
        $modoEdicion = (bool) ($body['modo_edicion'] ?? false);

        if ($yaGuardado) {
            if (!$modoEdicion) {
                $this->json([
                    'error' => 'Llamado a lista ya consolidado: esta reunión ya cuenta con un registro almacenado. Habilite el modo de edición si necesita modificarlo.'
                ], 409);
                return;
            }

            // Verificar que exista al menos una modificación real frente al registro guardado
            $huboCambios = count($existentes) !== count($registros);
            if (!$huboCambios) {
                foreach ($registros as $sid => $reg) {
                    $prev = $existentes[$sid] ?? null;
                    if (
                        $prev === null
                        || (bool) $prev['pago_cuota'] !== $reg['pago_cuota']
                        || (int) $prev['ahorro_voluntario'] !== $reg['ahorro_voluntario']
                        || (bool) $prev['autoprestamo'] !== $reg['autoprestamo']
                    ) {
                        $huboCambios = true;
                        break;
                    }
                }
            }
            if (!$huboCambios) {
                $this->json([
                    'error' => 'Sin modificaciones detectadas: los datos ingresados coinciden con el llamado a lista ya almacenado.'
                ], 409);
                return;
            }
        }

        $llamadoModel->guardarLote($reunionId, array_values($registros));
        $siguientePendiente = $llamadoModel->siguienteReunionPendiente($reunionId);

        $mensaje = $yaGuardado
            ? 'Modificaciones del llamado a lista actualizadas correctamente.'
            : 'Llamado a lista consolidado y guardado correctamente.';
        if ($siguientePendiente !== null) {
            $mensaje .= ' Se cargará la siguiente reunión pendiente.';
        } else {
            $mensaje .= ' No quedan reuniones pendientes por registrar.';
        }

        $this->json([
            'message' => $mensaje,
            'next_reunion_id' => $siguientePendiente,
        ]);
    }

}
