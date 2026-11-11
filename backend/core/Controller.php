<?php
declare(strict_types=1);

namespace Natillera\Core;

use PDO;
use RuntimeException;

abstract class Controller
{
    public function __construct(protected PDO $db)
    {
    }

    protected function render(string $view, array $data = []): void
    {
        $viewsPath = dirname(__DIR__, 2) . '/frontend/views';
        $viewPath = $viewsPath . '/' . $view . '.php';
        if (!is_file($viewPath)) {
            throw new RuntimeException('No se encontró la vista solicitada.');
        }

        $vapidConfig = require dirname(__DIR__) . '/config/vapid_keys.php';
        $data += [
            'currentUser' => $_SESSION['usuario'] ?? null,
            'currentTabId' => (string) ($GLOBALS['_nl_tab'] ?? ''),
            'activePage' => '',
            'pageTitle' => 'Natillera',
            'vapidPublicKey' => $vapidConfig['publicKey'],
        ];
        extract($data, EXTR_SKIP);
        require $viewsPath . '/layouts/header.php';
        require $viewPath;
        require $viewsPath . '/layouts/footer.php';
    }

    protected function redirect(string $path): never
    {
        // Propagar el identificador de pestaña en cada redirección para
        // que las sesiones por pestaña se mantengan independientes.
        $tabId = $GLOBALS['_nl_tab'] ?? '';
        if ($tabId !== '' && preg_match('/^[a-zA-Z0-9]{8,48}$/', $tabId) && !str_contains($path, '_tab=')) {
            $sep = str_contains($path, '?') ? '&' : '?';
            $path .= $sep . '_tab=' . rawurlencode($tabId);
        }
        header('Location: ' . $path, true, 303);
        exit;
    }

    protected function requireRoles(array $roles): bool
    {
        $usuario = $_SESSION['usuario'] ?? null;
        if (!is_array($usuario) || !in_array($usuario['rol'] ?? null, $roles, true)) {
            if (!is_array($usuario)) {
                $this->redirect('/login');
            }

            http_response_code(403);
            $this->render('errors/403', ['pageTitle' => 'Acceso restringido']);
            return false;
        }

        return true;
    }

    protected function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && isset($_SESSION['csrf_token'])
            && hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function flash(string $type, string $message): void
    {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    protected function takeFlash(): ?array
    {
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return is_array($flash) ? $flash : null;
    }

    protected function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    protected function denyAccess(bool $asJson = false): void
    {
        if ($asJson) {
            $this->json(['error' => 'No tiene permiso para realizar esta acción.'], 403);
            return;
        }

        http_response_code(403);
        echo 'No tiene permiso para acceder a esta página.';
    }
}
