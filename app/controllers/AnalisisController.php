<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Analisis\Estadisticas;
use App\Models\Buzon;

/**
 * Análisis de ventas: indicadores del período comparados con el anterior,
 * gráficos de lo que más se vende y las sugerencias destacadas del buzón.
 */
class AnalisisController extends BaseController
{
    private const PERIODOS = [30, 90, 365];

    public function moduloProtegido(): ?string
    {
        return 'analisis';
    }

    public function ejecutar(string $accion): void
    {
        $dias = (int) ($_GET['dias'] ?? 30);
        if (!in_array($dias, self::PERIODOS, true)) {
            $dias = 30;
        }

        $estadisticas = new Estadisticas();
        $buzon = new Buzon();

        $this->render('analisisView', [
            'activePage' => 'analisis',
            'dias' => $dias,
            'periodos' => self::PERIODOS,
            'resumen' => $estadisticas->resumenComparado($dias),
            'destacadas' => $buzon->destacadas(3),
            'pendientes' => $buzon->contarPorEstado()['pendientes'],
            'ingresosMes' => $estadisticas->ingresosPorMes(),
            'porDia' => $estadisticas->porDiaSemana($dias),
            'porHora' => $estadisticas->porHora($dias),
            'servicios' => $estadisticas->serviciosMasVendidos($dias),
            'productos' => $estadisticas->productosMasVendidos($dias),
            'metodos' => $estadisticas->ingresosPorMetodo($dias),
            'barberos' => $estadisticas->atencionesPorBarbero($dias),
        ]);
    }
}
