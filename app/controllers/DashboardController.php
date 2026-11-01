<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Buzon;
use App\Models\Dashboard;

class DashboardController extends BaseController
{
    public function ejecutar(string $accion): void
    {
        $modelo = new Dashboard();
        $verBuzon = isset($this->permisosSesion()['buzon']);

        $this->render('dashboardHome', [
            'activePage' => 'dashboard',
            'resumen' => $modelo->resumen(),
            'proximasCitas' => $modelo->proximasCitas(),
            'stockBajo' => $modelo->productosStockBajo(),
            'ingresosSemana' => $modelo->ingresosUltimosDias(),
            'sugerencias' => $verBuzon ? (new Buzon())->destacadas(3) : [],
        ]);
    }
}
