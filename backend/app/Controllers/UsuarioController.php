<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Socio;
use Natillera\App\Models\Usuario;
use Natillera\Core\Controller;

final class UsuarioController extends Controller
{
    private const ROLES_PERMITIDOS = [
        'Tesorera',
        'Secretaria Actividades',
        'Secretaria General',
        'Socio',
    ];

    public function index(): void
    {
        if (!$this->requireRoles(['Presidente'])) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $socioModel = new Socio($this->db);
        $this->render('admin/usuarios', [
            'pageTitle' => 'Equipo y accesos',
            'activePage' => 'usuarios',
            'usuarios' => (new Usuario($this->db))->todos(),
            'sociosSinCuenta' => $socioModel->sinCuenta(),
            'siguienteNumero' => $socioModel->siguienteNumero(),
            'sociosActivos' => $socioModel->cantidad(),
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
            $this->redirect('/admin/equipo');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rol = (string) ($_POST['rol'] ?? '');
        $socioIdExistenteRaw = trim((string) ($_POST['socio_id_existente'] ?? ''));
        $socioIdExistente = $socioIdExistenteRaw !== '' ? filter_var($socioIdExistenteRaw, FILTER_VALIDATE_INT) : null;
        $numeroRaw = trim((string) ($_POST['numero_socio'] ?? ''));
        $numeroSocio = $numeroRaw !== '' ? filter_var($numeroRaw, FILTER_VALIDATE_INT) : null;

        if (
            $nombre === '' || mb_strlen($nombre, 'UTF-8') > 100
            || !in_array($rol, self::ROLES_PERMITIDOS, true)
            || strlen($password) < 12
        ) {
            $this->flash('danger', 'Revisa el nombre, rol y contraseña (mínimo 12 caracteres).');
            $this->redirect('/admin/equipo');
        }

        $socioModel = new Socio($this->db);
        if ($socioIdExistente !== null) {
            if (!is_int($socioIdExistente) || $socioIdExistente <= 0) {
                $this->flash('danger', 'El socio seleccionado no es válido.');
                $this->redirect('/admin/equipo');
            }
            $idsSinCuenta = array_map(static fn(array $s): int => (int) $s['id'], $socioModel->sinCuenta());
            if (!in_array($socioIdExistente, $idsSinCuenta, true)) {
                $this->flash('danger', 'Ese socio ya tiene una cuenta de acceso o no está activo.');
                $this->redirect('/admin/equipo');
            }
        } else {
            if ($socioModel->cantidad() >= 50) {
                $this->flash('warning', 'La natillera ya alcanzó su límite de 50 socios.');
                $this->redirect('/admin/equipo');
            }
            if ($numeroSocio !== null && (!is_int($numeroSocio) || $numeroSocio < 1 || $numeroSocio > 999 || $socioModel->numeroExiste($numeroSocio))) {
                $this->flash('warning', 'El número asignado debe estar entre 1 y 999 y no estar ocupado por otro socio.');
                $this->redirect('/admin/equipo');
            }
        }

        $usuarios = new Usuario($this->db);
        if ($usuarios->nombreExiste($nombre)) {
            $this->flash('danger', 'Ese nombre ya tiene una cuenta. Usa un nombre distinto para iniciar sesión.');
            $this->redirect('/admin/equipo');
        }

        try {
            $usuarios->crear(
                $nombre,
                $password,
                $rol,
                $socioIdExistente,
                is_int($numeroSocio) ? $numeroSocio : null
            );
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                $this->flash('danger', 'Ese nombre ya tiene una cuenta. Usa un nombre distinto para iniciar sesión.');
                $this->redirect('/admin/equipo');
            }
            throw $error;
        }

        $this->flash('success', 'La cuenta quedó creada y ya puede ingresar con su usuario y contraseña.');
        $this->redirect('/admin/equipo');
    }
}
