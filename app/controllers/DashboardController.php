<?php

namespace App\Controllers;

use App\Core\Autorizacion;
use App\Core\BaseController;
use App\Models\Buzon;
use App\Models\Dashboard;

class DashboardController extends BaseController
{
    public function ejecutar(string $accion): void
    {
        $modelo = new Dashboard();
        $verBuzon = isset($this->permisosSesion()['buzon']);
        // Un usuario vinculado a un barbero ve primero sus propios cortes y solo sus citas.
        $barbero = (new Autorizacion())->barberoSesion();

        $this->render('dashboardHome', [
            'activePage' => 'dashboard',
            'resumen' => $modelo->resumen(),
            'misCortes' => $barbero !== null ? $modelo->resumenBarbero($barbero) : [],
            'proximasCitas' => $modelo->proximasCitas(6, $barbero),
            'stockBajo' => $modelo->productosStockBajo(),
            'ingresosSemana' => $modelo->ingresosUltimosDias(),
            'sugerencias' => $verBuzon ? (new Buzon())->destacadas(3) : [],
        ]);
    }
}
