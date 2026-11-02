<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Inscripcion;

class InscripcionesController extends CrudController
{
    protected function ruta(): string
    {
        return 'inscripciones';
    }

    protected function crearModelo(): CrudModel
    {
        return new Inscripcion();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id_inscripcion', 'etiqueta' => 'N.º', 'formato' => 'numero'],
            ['campo' => 'fecha_inscripcion', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'alumno', 'etiqueta' => 'Alumno', 'sub' => 'telefono'],
            ['campo' => 'nombre_curso', 'etiqueta' => 'Curso'],
            ['campo' => 'costo', 'etiqueta' => 'Costo', 'formato' => 'moneda'],
            ['campo' => 'monto_pagado', 'etiqueta' => 'Pagado', 'formato' => 'moneda'],
            ['campo' => 'saldo', 'etiqueta' => 'Saldo', 'formato' => 'moneda'],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'buscarNumero' => ['etiqueta' => 'N.º de inscripción'],
            'filtros' => [
                ['campo' => 'nombre_curso', 'etiqueta' => 'Curso'],
                ['campo' => 'alumno', 'etiqueta' => 'Alumno'],
            ],
        ];
    }
}
