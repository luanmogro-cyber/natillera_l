<?php
declare(strict_types=1);

use Natillera\App\Controllers\LlamadoListaController;
use Natillera\App\Controllers\LiquidacionController;
use Natillera\App\Controllers\NotificacionController;
use Natillera\App\Controllers\AuthController;
use Natillera\App\Controllers\ActividadController;
use Natillera\App\Controllers\DashboardController;
use Natillera\App\Controllers\ReunionController;
use Natillera\App\Controllers\PrestamoController;
use Natillera\App\Controllers\SocioController;
use Natillera\App\Controllers\UsuarioController;
use Natillera\Core\Router;

$projectRoot = dirname(__DIR__, 2);
$backendPath = $projectRoot . '/backend';

if (PHP_SAPI === 'cli-server') {
    $requestedPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (
        is_string($requestedPath)
        && $requestedPath !== '/'
        && is_file(__DIR__ . $requestedPath)
    ) {
        return false;
    }
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
date_default_timezone_set('America/Bogota');

// ── Sesiones independientes por pestaña ──────────────────────────────────────
// Cada pestaña usa su propio identificador `_tab` (almacenado en sessionStorage del
// navegador y enviado en la URL/formularios), lo que selecciona una cookie de sesión
// distinta (NLSESS_<tab>). Nunca se usa una cookie compartida para _tab.
if (isset($_COOKIE['_nl_tab'])) {
    setcookie('_nl_tab', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
}

$tabId = preg_replace('/[^a-zA-Z0-9]/', '', (string)($_GET['_tab'] ?? $_POST['_tab'] ?? ''));
if ($tabId !== '' && strlen($tabId) >= 8 && strlen($tabId) <= 48) {
    session_name('NLSESS_' . $tabId);
} else {
    $tabId = '';
}
$GLOBALS['_nl_tab'] = $tabId;

$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

session_set_cookie_params([
    'httponly' => true,
    'secure' => $isHttps,
    'samesite' => 'Lax',
]);
session_start();

$composerAutoload = $backendPath . '/vendor/autoload.php';
if (!is_file($composerAutoload)) {
    throw new RuntimeException('Faltan las dependencias PHP. Ejecuta composer install desde la carpeta backend.');
}
require $composerAutoload;

$db = require $backendPath . '/config/database.php';
$router = new Router();
$auth = new AuthController($db);
$dashboard = new DashboardController($db);
$socios = new SocioController($db);
$reuniones = new ReunionController($db);
$prestamos = new PrestamoController($db);
$equipo = new UsuarioController($db);
$actividades = new ActividadController($db);
$liquidacion = new LiquidacionController($db);
$notificaciones = new NotificacionController($db);
$llamadoLista = new LlamadoListaController($db);

$router->get('/', [$auth, 'inicio']);
$router->get('/instalar', [$auth, 'instalar']);
$router->post('/instalar', [$auth, 'guardarInstalacion']);
$router->get('/login', [$auth, 'login']);
$router->post('/login', [$auth, 'autenticar']);
$router->post('/logout', [$auth, 'salir']);
$router->post('/presidente/vista', [$auth, 'cambiarVistaPresidencia']);
$router->get('/panel', [$dashboard, 'index']);
$router->get('/socio', [$dashboard, 'socio']);
$router->get('/admin/socios', [$socios, 'index']);
$router->post('/admin/socios', [$socios, 'crear']);
$router->post('/admin/socios/numero', [$socios, 'actualizarNumero']);
$router->get('/admin/equipo', [$equipo, 'index']);
$router->post('/admin/equipo', [$equipo, 'crear']);
$router->get('/admin/reuniones', [$reuniones, 'index']);
$router->post('/admin/reuniones', [$reuniones, 'crear']);
$router->get('/admin/prestamos', [$prestamos, 'index']);
$router->get('/admin/prestamos/exportar', [$prestamos, 'exportar']);
$router->post('/admin/prestamos', [$prestamos, 'crear']);
$router->post('/admin/prestamos/pago', [$prestamos, 'pago']);
$router->post('/admin/prestamos/evidencia', [$prestamos, 'adjuntarEvidencia']);
$router->post('/admin/prestamos/aprobar', [$prestamos, 'aprobarPrestamo']);
$router->post('/admin/prestamos/rechazar', [$prestamos, 'rechazarPrestamo']);
$router->get('/admin/creditos', [$prestamos, 'creditos']);
$router->post('/admin/creditos', [$prestamos, 'guardarCredito']);
$router->get('/admin/actividades', [$actividades, 'index']);
$router->post('/admin/actividades', [$actividades, 'guardar']);
$router->get('/admin/liquidacion', [$liquidacion, 'index']);
$router->get('/admin/liquidacion/exportar', [$liquidacion, 'exportar']);
$router->post('/admin/liquidacion', [$liquidacion, 'guardar']);
$router->get('/admin/notificaciones', [$notificaciones, 'index']);
$router->post('/admin/notificaciones', [$notificaciones, 'enviar']);
$router->post('/api/push/suscribir', [$notificaciones, 'suscribir']);
$router->post('/api/push/cancelar', [$notificaciones, 'cancelarSuscripcion']);
$router->get('/admin/llamado-lista', [$llamadoLista, 'index']);
$router->post('/admin/llamado-lista', [$llamadoLista, 'guardar']);
$router->dispatch($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
