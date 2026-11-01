<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Servicio;

class ServiciosController extends CrudController
{
    protected function ruta(): string
    {
        return 'servicios';
    }

    protected function crearModelo(): CrudModel
    {
        return new Servicio();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_servicio', 'etiqueta' => 'Servicio'],
            ['campo' => 'precio', 'etiqueta' => 'Precio', 'formato' => 'moneda'],
            ['campo' => 'duracion_minutos', 'etiqueta' => 'Duración', 'formato' => 'minutos'],
        ];
    }
}
