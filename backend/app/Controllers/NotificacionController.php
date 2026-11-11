<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Notificacion;
use Natillera\App\Models\PushSubscription;
use Natillera\App\Models\Socio;
use Natillera\App\Services\PushService;
use Natillera\Core\Controller;
use RuntimeException;

final class NotificacionController extends Controller
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

        $vapid = require dirname(__DIR__, 2) . '/config/vapid_keys.php';
        $currentSocioId = $_SESSION['usuario']['socio_id'] ?? null;
        $todosSocios = (new Socio($this->db))->activos();
        $destinatarios = array_values(array_filter(
            $todosSocios,
            static fn(array $socio): bool => !is_int($currentSocioId) || (int) $socio['id'] !== (int) $currentSocioId
        ));

        $this->render('admin/notificaciones', [
            'pageTitle' => 'Notificaciones',
            'activePage' => 'notificaciones',
            'socios' => $destinatarios,
            'notificaciones' => (new Notificacion($this->db))->recientes(),
            'csrfToken' => $_SESSION['csrf_token'],
            'vapidPublicKey' => $vapid['publicKey'],
            'pushConfigurado' => $vapid['publicKey'] !== '' && $vapid['privateKey'] !== '',
            'flashMessage' => $this->takeFlash(),
        ]);
    }

    public function enviar(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!$this->verifyCsrf($_POST['_csrf'] ?? null)) {
            $this->flash('danger', 'La sesión venció. Vuelve a cargar la página.');
            $this->redirect('/admin/notificaciones');
        }

        $titulo = trim((string) ($_POST['titulo'] ?? ''));
        $mensaje = trim((string) ($_POST['mensaje'] ?? ''));
        $destinatario = (string) ($_POST['destinatario'] ?? 'todos');
        if ($titulo === '' || mb_strlen($titulo, 'UTF-8') > 100 || $mensaje === '' || mb_strlen($mensaje, 'UTF-8') > 500) {
            $this->flash('danger', 'El título admite 100 caracteres y el mensaje hasta 500.');
            $this->redirect('/admin/notificaciones');
        }

        $currentUserId = (int) $_SESSION['usuario']['id'];
        $currentSocioId = $_SESSION['usuario']['socio_id'] ?? null;
        $socioId = null;
        if ($destinatario !== 'todos') {
            $socioId = filter_var($destinatario, FILTER_VALIDATE_INT);
            $idsActivos = array_map(
                static fn(array $socio): int => (int) $socio['id'],
                (new Socio($this->db))->activos()
            );
            if (!is_int($socioId) || !in_array($socioId, $idsActivos, true)) {
                $this->flash('danger', 'Selecciona un socio activo o elige el envío a todos.');
                $this->redirect('/admin/notificaciones');
            }
            if (is_int($currentSocioId) && $socioId === (int) $currentSocioId) {
                $this->flash('danger', 'No puedes enviarte una notificación a ti mismo.');
                $this->redirect('/admin/notificaciones');
            }
        }

        try {
            $resultado = (new PushService($this->db))->enviarASocios([
                'title' => $titulo,
                'body' => $mensaje,
                'url' => $socioId === null ? '/panel' : '/socio',
            ], $socioId, $currentUserId);
        } catch (RuntimeException $error) {
            $this->flash('danger', $error->getMessage());
            $this->redirect('/admin/notificaciones');
        }

        if ($resultado['intentados'] === 0) {
            $this->flash('warning', 'No hay socios con notificaciones push activadas para ese destinatario.');
            $this->redirect('/admin/notificaciones');
        }

        (new Notificacion($this->db))->guardar(
            $titulo,
            $mensaje,
            $socioId,
            (int) $_SESSION['usuario']['id'],
            $resultado['enviados'],
            $resultado['fallidos']
        );
        $tipo = $resultado['fallidos'] === 0 ? 'success' : 'warning';
        $texto = "Enviadas: {$resultado['enviados']}. Fallidas: {$resultado['fallidos']}.";
        if ($resultado['vencidos'] > 0) {
            $texto .= " Se quitaron {$resultado['vencidos']} suscripciones vencidas.";
        }
        $this->flash($tipo, $texto);
        $this->redirect('/admin/notificaciones');
    }

    public function suscribir(): void
    {
        if (!isset($_SESSION['usuario']['id'])) {
            $this->json(['error' => 'Inicia sesión para activar las notificaciones.'], 401);
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$this->verifyCsrf(is_string($token) ? $token : null)) {
            $this->json(['error' => 'La sesión venció. Recarga la página e inténtalo de nuevo.'], 419);
            return;
        }

        try {
            $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->json(['error' => 'Los datos de suscripción no son JSON válido.'], 400);
            return;
        }

        if (!$this->suscripcionValida($payload)) {
            $this->json(['error' => 'El navegador envió una suscripción push incompleta o no compatible.'], 422);
            return;
        }

        (new PushSubscription($this->db))->guardar((int) $_SESSION['usuario']['id'], $payload);
        $this->json(['message' => 'Este dispositivo quedó suscrito a las notificaciones.']);
    }

    public function cancelarSuscripcion(): void
    {
        if (!isset($_SESSION['usuario']['id'])) {
            $this->json(['error' => 'Inicia sesión para administrar las notificaciones.'], 401);
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if (!$this->verifyCsrf(is_string($token) ? $token : null)) {
            $this->json(['error' => 'La sesión venció. Recarga la página e inténtalo de nuevo.'], 419);
            return;
        }

        try {
            $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $this->json(['error' => 'Los datos enviados no son JSON válido.'], 400);
            return;
        }
        if (!is_array($payload) || !is_string($payload['endpoint'] ?? null) || !$this->endpointValido($payload['endpoint'])) {
            $this->json(['error' => 'El endpoint de notificación no es válido.'], 422);
            return;
        }

        (new PushSubscription($this->db))->eliminarDeUsuario((int) $_SESSION['usuario']['id'], $payload['endpoint']);
        $this->json(['message' => 'Se desactivaron las notificaciones en este dispositivo.']);
    }

    private function suscripcionValida(mixed $payload): bool
    {
        return is_array($payload)
            && is_string($payload['endpoint'] ?? null)
            && $this->endpointValido($payload['endpoint'])
            && is_array($payload['keys'] ?? null)
            && is_string($payload['keys']['p256dh'] ?? null)
            && preg_match('/^[A-Za-z0-9_-]{20,200}$/', $payload['keys']['p256dh']) === 1
            && is_string($payload['keys']['auth'] ?? null)
            && preg_match('/^[A-Za-z0-9_-]{10,100}$/', $payload['keys']['auth']) === 1
            && (!isset($payload['contentEncoding']) || in_array($payload['contentEncoding'], ['aes128gcm', 'aesgcm'], true))
            && (!isset($payload['expirationTime']) || $payload['expirationTime'] === null || is_int($payload['expirationTime']));
    }

    private function endpointValido(string $endpoint): bool
    {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        return $host === 'fcm.googleapis.com'
            || $host === 'web.push.apple.com'
            || $host === 'push.services.mozilla.com'
            || str_ends_with($host, '.push.services.mozilla.com')
            || str_ends_with($host, '.notify.windows.com');
    }
}
