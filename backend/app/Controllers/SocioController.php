<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Socio;
use Natillera\Core\Controller;

final class SocioController extends Controller
{
    public function index(): void
    {
        if (!$this->requireRoles(['Presidente'])) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $modelo = new Socio($this->db);
        $this->render('admin/socios', [
            'pageTitle' => 'Socios',
            'activePage' => 'socios',
            'socios' => $modelo->recientes(),
            'sociosActivos' => $modelo->cantidad(),
            'siguienteNumero' => $modelo->siguienteNumero(),
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function crear(): void
    {
        if (!$this->requireRoles(['Presidente'])) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/socios');
        }

        $raw = trim((string) ($_POST['nombres'] ?? ''));
        $nombres = array_values(array_filter(
            array_map(static fn(string $nombre): string => trim($nombre), preg_split('/\R/u', $raw) ?: []),
            static fn(string $nombre): bool => $nombre !== ''
        ));
        if ($nombres === [] || count($nombres) > 50) {
            $this->flash('danger', 'Escribe entre 1 y 50 nombres, uno por línea.');
            $this->redirect('/admin/socios');
        }
        foreach ($nombres as $nombre) {
            if (mb_strlen($nombre, 'UTF-8') > 120) {
                $this->flash('danger', 'Cada nombre debe tener máximo 120 caracteres.');
                $this->redirect('/admin/socios');
            }
        }

        $numeroInicialRaw = trim((string) ($_POST['numero_inicial'] ?? ''));
        $numeroInicial = $numeroInicialRaw === '' ? null : filter_var($numeroInicialRaw, FILTER_VALIDATE_INT);
        if ($numeroInicialRaw !== '' && (!is_int($numeroInicial) || $numeroInicial < 1 || $numeroInicial > 999)) {
            $this->flash('danger', 'El número de socio debe ser un entero entre 1 y 999.');
            $this->redirect('/admin/socios');
        }

        $modelo = new Socio($this->db);
        $existentes = $modelo->cantidad();
        if ($existentes + count($nombres) > 50) {
            $disponibles = max(0, 50 - $existentes);
            $this->flash('warning', "La natillera tiene un límite de 50 socios. Puedes agregar {$disponibles} más.");
            $this->redirect('/admin/socios');
        }

        if (count($nombres) === 1 && $numeroInicial !== null && $modelo->numeroExiste($numeroInicial)) {
            $this->flash('warning', "El número #{$numeroInicial} ya está asignado a otro socio. Elige otro número.");
            $this->redirect('/admin/socios');
        }

        $modelo->crearVarios($nombres, $numeroInicial);
        $this->flash('success', count($nombres) === 1 ? 'Socio agregado con su número asignado.' : count($nombres) . ' socios agregados.');
        $this->redirect('/admin/socios');
    }

    public function actualizarNumero(): void
    {
        if (!$this->requireRoles(['Presidente'])) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/socios');
        }

        $socioId = filter_var($_POST['socio_id'] ?? null, FILTER_VALIDATE_INT);
        $numero = filter_var($_POST['numero'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($socioId) || !is_int($numero) || $numero < 1 || $numero > 999) {
            $this->flash('danger', 'Ingresa un número válido entre 1 y 999.');
            $this->redirect('/admin/socios');
        }

        $modelo = new Socio($this->db);
        if ($modelo->numeroExiste($numero, $socioId)) {
            $this->flash('warning', "El número #{$numero} ya pertenece a otro socio activo.");
            $this->redirect('/admin/socios');
        }

        $modelo->actualizarNumero($socioId, $numero);
        $this->flash('success', "Número #{$numero} actualizado. El llamado a lista se ordenará de menor a mayor.");
        $this->redirect('/admin/socios');
    }
}
