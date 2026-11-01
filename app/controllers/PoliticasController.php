<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Politica;

class PoliticasController extends CrudController
{
    protected function ruta(): string
    {
        return 'politicas';
    }

    protected function crearModelo(): CrudModel
    {
        return new Politica();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'orden', 'etiqueta' => 'Orden', 'formato' => 'entero'],
            ['campo' => 'titulo', 'etiqueta' => 'Política', 'sub' => 'categoria'],
            ['campo' => 'contenido', 'etiqueta' => 'Texto'],
            ['campo' => 'fecha_actualizacion', 'etiqueta' => 'Actualizada', 'formato' => 'fechahora'],
        ];
    }
}
