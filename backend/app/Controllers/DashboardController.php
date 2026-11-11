<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Liquidacion;
use Natillera\App\Models\Reunion;
use Natillera\App\Models\Socio;
use Natillera\Core\Controller;

final class DashboardController extends Controller
{
    public function index(): void
    {
        if (!isset($_SESSION['usuario'])) {
            $this->redirect('/login');
        }
        if (
            ($_SESSION['usuario']['rol'] ?? null) === 'Presidente'
            && ($_SESSION['vista_presidencial'] ?? false)
            && is_int($_SESSION['usuario']['socio_id'] ?? null)
        ) {
            $this->redirect('/socio');
        }
        if (($_SESSION['usuario']['rol'] ?? null) === 'Socio') {
            $this->redirect('/socio');
        }

        $socios = new Socio($this->db);
        $reuniones = new Reunion($this->db);
        $balance = (new Liquidacion($this->db))->balanceGeneral();
        $this->render('dashboard/index', [
            'pageTitle' => 'Resumen',
            'activePage' => 'panel',
            'sociosActivos' => $socios->cantidad(),
            'proximaReunion' => $reuniones->proxima(),
            'reunionesRegistradas' => $reuniones->cantidad(),
            'balance' => $balance,
        ]);
    }

    public function socio(): void
    {
        $usuario = $_SESSION['usuario'] ?? null;
        if (!is_array($usuario)) {
            $this->redirect('/login');
        }
        if (!is_int($usuario['socio_id'] ?? null)) {
            http_response_code(403);
            $this->render('errors/403', ['pageTitle' => 'Cuenta sin socio asociado']);
            return;
        }

        $this->render('socio/dashboard', [
            'pageTitle' => 'Mi resumen y ahorro',
            'activePage' => 'socio',
            'resumen' => (new Socio($this->db))->resumenPersonal((int) $usuario['socio_id']),
        ]);
    }
}
