<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\CierreCaja;
use App\Models\Retiro;

class RetirosController extends CrudController
{
    protected function ruta(): string
    {
        return 'retiros';
    }

    protected function crearModelo(): CrudModel
    {
        return new Retiro();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id_retiro', 'etiqueta' => 'N.º', 'formato' => 'numero'],
            ['campo' => 'fecha_retiro', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'barbero', 'etiqueta' => 'Retirado por'],
            ['campo' => 'desglose', 'etiqueta' => 'Desglose'],
            ['campo' => 'caja', 'etiqueta' => 'Caja'],
            ['campo' => 'monto_total', 'etiqueta' => 'Total', 'formato' => 'moneda'],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'buscarNumero' => ['etiqueta' => 'N.º de retiro'],
            'defectos' => ['id_cierre' => (new CierreCaja())->idCajaAbierta()],
        ];
    }
}
