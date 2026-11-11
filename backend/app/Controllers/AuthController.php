<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Socio;
use Natillera\App\Models\Usuario;
use Natillera\Core\Controller;

final class AuthController extends Controller
{
    public function inicio(): void
    {
        if ((new Usuario($this->db))->cantidad() === 0) {
            $this->redirect('/instalar');
        }

        if (isset($_SESSION['usuario'])) {
            $this->redirect('/panel');
        }

        $this->redirect('/login');
    }

    public function instalar(): void
    {
        $usuarios = new Usuario($this->db);
        if ($usuarios->cantidad() > 0) {
            $this->redirect('/login');
        }

        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('auth/instalar', [
            'pageTitle' => 'Configurar natillera',
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function guardarInstalacion(): void
    {
        if ((new Usuario($this->db))->cantidad() > 0) {
            $this->redirect('/login');
        }

        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/instalar');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        if ($nombre === '' || mb_strlen($nombre, 'UTF-8') > 100) {
            $this->flash('danger', 'Ingresa tu nombre (máximo 100 caracteres).');
            $this->redirect('/instalar');
        }
        if (strlen($password) < 12) {
            $this->flash('danger', 'La contraseña debe tener al menos 12 caracteres.');
            $this->redirect('/instalar');
        }

        try {
            $usuarioId = (new Usuario($this->db))->crearPresidenteInicial($nombre, $password);
            if (!is_int($usuarioId)) {
                $this->redirect('/login');
            }
        } catch (\PDOException $error) {
            if (str_contains(strtolower($error->getMessage()), 'unique')) {
                $this->flash('danger', 'Ya existe una cuenta con ese nombre.');
                $this->redirect('/instalar');
            }
            throw $error;
        }

        $presidente = (new Usuario($this->db))->buscarPorNombre($nombre);
        if ($presidente === null) {
            throw new \RuntimeException('No se pudo cargar la cuenta inicial de Presidencia.');
        }
        $this->iniciarSesionPestana([
            'id' => $usuarioId,
            'socio_id' => (int) $presidente['socio_id'],
            'nombre' => $nombre,
            'rol' => 'Presidente',
        ]);
        $this->redirect('/panel');
    }

    public function login(): void
    {
        if ((new Usuario($this->db))->cantidad() === 0) {
            $this->redirect('/instalar');
        }
        if (isset($_SESSION['usuario'])) {
            $this->redirect('/panel');
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $this->render('auth/login', [
            'pageTitle' => 'Iniciar sesión',
            'csrfToken' => $_SESSION['csrf_token'],
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function autenticar(): void
    {
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/login');
        }

        $nombre = trim((string) ($_POST['nombre'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $ahora = time();
        $intentos = $_SESSION['login_attempts'] ?? [];
        $intentos = array_values(array_filter(
            is_array($intentos) ? $intentos : [],
            static fn(mixed $fecha): bool => is_int($fecha) && $fecha > $ahora - 900
        ));

        if (count($intentos) >= 8) {
            $this->flash('warning', 'Demasiados intentos. Espera 15 minutos antes de volver a intentar.');
            $this->redirect('/login');
        }

        $usuario = (new Usuario($this->db))->buscarPorNombre($nombre);
        if ($usuario === null || !password_verify($password, $usuario['password_hash'])) {
            $intentos[] = $ahora;
            $_SESSION['login_attempts'] = $intentos;
            $this->flash('danger', 'Nombre o contraseña incorrectos.');
            $this->redirect('/login');
        }

        unset($_SESSION['login_attempts']);
        $this->iniciarSesionPestana([
            'id' => (int) $usuario['id'],
            'socio_id' => $usuario['socio_id'] === null ? null : (int) $usuario['socio_id'],
            'nombre' => $usuario['nombre'],
            'rol' => $usuario['rol'],
        ]);
        $this->redirect('/panel');
    }

    private function iniciarSesionPestana(array $datosUsuario): void
    {
        $tabId = (string) ($GLOBALS['_nl_tab'] ?? '');
        if ($tabId === '' || !preg_match('/^[a-zA-Z0-9]{8,48}$/', $tabId)) {
            $tabId = bin2hex(random_bytes(8));
            $GLOBALS['_nl_tab'] = $tabId;
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_name('NLSESS_' . $tabId);
            session_start();
        }
        session_regenerate_id(true);
        $_SESSION['usuario'] = $datosUsuario;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    public function cambiarVistaPresidencia(): void
    {
        if (!$this->requireRoles(['Presidente'])) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/panel');
        }
        if (!is_int($_SESSION['usuario']['socio_id'] ?? null)) {
            $this->flash('warning', 'La cuenta de Presidencia todavía no está vinculada a un socio.');
            $this->redirect('/panel');
        }

        $_SESSION['vista_presidencial'] = !($_SESSION['vista_presidencial'] ?? false);
        $this->redirect($_SESSION['vista_presidencial'] ? '/socio' : '/panel');
    }

    public function salir(): void
    {
        if (!isset($_SESSION['usuario'])) {
            $this->redirect('/login');
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            echo 'La sesión venció. Recarga la página antes de salir.';
            return;
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => 'Lax',
            ]);
        }
        session_destroy();
        $this->redirect('/login');
    }
}
