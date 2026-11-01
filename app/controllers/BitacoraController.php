<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Bitacora;

class BitacoraController extends CrudController
{
    protected function ruta(): string
    {
        return 'bitacora';
    }

    protected function crearModelo(): CrudModel
    {
        return new Bitacora();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha_bitacora', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'usuario', 'etiqueta' => 'Usuario'],
            ['campo' => 'modulo', 'etiqueta' => 'Módulo'],
            [
                'campo' => 'accion', 'etiqueta' => 'Acción', 'formato' => 'insignia',
                'colores' => ['Registrar' => 'verde', 'Modificar' => 'azul', 'Eliminar' => 'rojo', 'Restaurar' => 'morado'],
            ],
            ['campo' => 'descripcion', 'etiqueta' => 'Descripción'],
        ];
    }

    protected function configuracionVista(): array
    {
        return ['soloConsulta' => true];
    }
}
