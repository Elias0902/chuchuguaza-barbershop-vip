<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\MetodoPago;

class MetodosPagoController extends CrudController
{
    protected function ruta(): string
    {
        return 'metodos_pago';
    }

    protected function crearModelo(): CrudModel
    {
        return new MetodoPago();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_metodo', 'etiqueta' => 'Método de pago'],
            ['campo' => 'pagos', 'etiqueta' => 'Pagos registrados', 'formato' => 'entero'],
        ];
    }
}
