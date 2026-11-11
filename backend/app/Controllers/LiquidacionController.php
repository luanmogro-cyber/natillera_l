<?php
declare(strict_types=1);

namespace Natillera\App\Controllers;

use Natillera\App\Models\Liquidacion;
use Natillera\Core\Controller;

final class LiquidacionController extends Controller
{
    private const ROLES = ['Presidente', 'Tesorera'];

    public function index(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        $anoActual = (int) date('Y');
        $ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
        if (!is_int($ano) || $ano < 2000 || $ano > $anoActual) {
            $ano = $anoActual;
        }
        $modelo = new Liquidacion($this->db);
        $cierre = $modelo->cierre($ano);
        $socios = $cierre === null ? $this->calcularReparto($modelo->resumenAnual($ano)) : [];

        $this->render('admin/liquidacion', [
            'pageTitle' => 'Liquidación anual',
            'activePage' => 'liquidacion',
            'ano' => $ano,
            'anoActual' => $anoActual,
            'socios' => $socios,
            'cierre' => $cierre,
            'totalAhorros' => $cierre === null
                ? array_sum(array_column($socios, 'ahorro_base'))
                : (int) $cierre['total_ahorros'],
            'totalIntereses' => $cierre === null
                ? array_sum(array_column($socios, 'intereses_generados'))
                : (int) $cierre['total_intereses'],
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
            $this->redirect('/admin/liquidacion');
        }

        $ano = filter_var($_POST['ano'] ?? null, FILTER_VALIDATE_INT);
        if (!is_int($ano) || $ano < 2000 || $ano >= (int) date('Y')) {
            $this->flash('warning', 'El año en curso solo está disponible como vista previa. La liquidación se confirma al siguiente año.');
            $this->redirect('/admin/liquidacion');
        }

        $modelo = new Liquidacion($this->db);
        if ($modelo->cierre($ano) !== null) {
            $this->flash('warning', 'La liquidación de ese año ya fue registrada y no se puede duplicar.');
            $this->redirect('/admin/liquidacion?ano=' . $ano);
        }

        $socios = $modelo->resumenAnual($ano);
        $totalAhorros = array_sum(array_column($socios, 'ahorro_base'));
        $totalIntereses = array_sum(array_column($socios, 'intereses_generados'));
        if ($totalAhorros <= 0) {
            $this->flash('warning', 'No hay ahorros registrados en ese año para calcular un reparto proporcional.');
            $this->redirect('/admin/liquidacion?ano=' . $ano);
        }

        $modelo->guardarCierre($ano, $totalAhorros, $totalIntereses, $this->calcularReparto($socios));
        $this->flash('success', 'La liquidación anual quedó guardada como un corte de solo lectura.');
        $this->redirect('/admin/liquidacion?ano=' . $ano);
    }

    public function exportar(): void
    {
        if (!$this->requireRoles(self::ROLES)) {
            return;
        }

        $anoActual = (int) date('Y');
        $ano = filter_input(INPUT_GET, 'ano', FILTER_VALIDATE_INT);
        if (!is_int($ano) || $ano < 2000 || $ano > $anoActual) {
            $ano = $anoActual;
        }

        $modelo = new Liquidacion($this->db);
        $cierre = $modelo->cierre($ano);
        $filas = $cierre !== null ? $cierre['socios'] : $this->calcularReparto($modelo->resumenAnual($ano));
        $totalAhorros = $cierre === null ? array_sum(array_column($filas, 'ahorro_base')) : (int) $cierre['total_ahorros'];
        $totalIntereses = $cierre === null ? array_sum(array_column($filas, 'intereses_generados')) : (int) $cierre['total_intereses'];

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="liquidacion_natillera_' . $ano . '.csv"');
        header('Cache-Control: no-store');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
        fputcsv($out, ['N°', 'Socio', 'Ahorro del año (COP)', 'Participación (%)', 'Utilidad a recibir (COP)', 'Total a entregar (COP)'], ';');

        foreach ($filas as $i => $socio) {
            $ahorro = (int) ($socio['ahorro_base'] ?? 0);
            $utilidad = (int) ($socio['utilidad_repartida'] ?? 0);
            $porcentaje = $totalAhorros > 0 ? round($ahorro * 100 / $totalAhorros, 2) : 0;
            $num = $socio['numero'] ?? ($i + 1);
            fputcsv($out, [
                $num,
                $socio['nombre_completo'],
                $ahorro,
                number_format($porcentaje, 2, ',', '') . '%',
                $utilidad,
                $ahorro + $utilidad,
            ], ';');
        }

        fputcsv($out, ['', 'TOTALES', $totalAhorros, '100%', $totalIntereses, $totalAhorros + $totalIntereses], ';');
        fclose($out);
    }

    private function calcularReparto(array $socios): array
    {
        $totalAhorros = array_sum(array_column($socios, 'ahorro_base'));
        $totalIntereses = array_sum(array_column($socios, 'intereses_generados'));
        $restos = [];
        $asignado = 0;

        foreach ($socios as $indice => &$socio) {
            $numerador = $totalAhorros > 0
                ? $totalIntereses * (int) $socio['ahorro_base']
                : 0;
            $socio['utilidad_repartida'] = $totalAhorros > 0 ? intdiv($numerador, $totalAhorros) : 0;
            $asignado += $socio['utilidad_repartida'];
            $restos[] = [
                'indice' => $indice,
                'resto' => $totalAhorros > 0 ? $numerador % $totalAhorros : 0,
                'ahorro_base' => (int) $socio['ahorro_base'],
            ];
        }
        unset($socio);

        $faltante = max(0, $totalIntereses - $asignado);
        usort($restos, static fn(array $a, array $b): int => $b['resto'] <=> $a['resto']);
        for ($i = 0; $i < $faltante && $i < count($restos); $i++) {
            $indice = $restos[$i]['indice'];
            $socios[$indice]['utilidad_repartida']++;
        }

        return $socios;
    }
}
