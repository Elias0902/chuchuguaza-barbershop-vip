<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Curso;

class CursosController extends CrudController
{
    protected function ruta(): string
    {
        return 'cursos';
    }

    protected function crearModelo(): CrudModel
    {
        return new Curso();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_curso', 'etiqueta' => 'Curso', 'sub' => 'descripcion'],
            ['campo' => 'dias_imparte', 'etiqueta' => 'Días'],
            ['campo' => 'horario', 'etiqueta' => 'Horario'],
            ['campo' => 'costo', 'etiqueta' => 'Costo', 'formato' => 'moneda'],
            ['campo' => 'inscritos', 'etiqueta' => 'Inscritos', 'formato' => 'entero'],
        ];
    }
}
