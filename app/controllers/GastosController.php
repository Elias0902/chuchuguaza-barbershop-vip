<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\CierreCaja;
use App\Models\Gasto;

class GastosController extends CrudController
{
    protected function ruta(): string
    {
        return 'gastos';
    }

    protected function crearModelo(): CrudModel
    {
        return new Gasto();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha_gasto', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'descripcion', 'etiqueta' => 'Descripción'],
            ['campo' => 'caja', 'etiqueta' => 'Caja'],
            ['campo' => 'monto', 'etiqueta' => 'Monto', 'formato' => 'moneda'],
        ];
    }

    protected function configuracionVista(): array
    {
        return ['defectos' => ['id_cierre' => (new CierreCaja())->idCajaAbierta()]];
    }
}
