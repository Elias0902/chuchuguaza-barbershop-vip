<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Barbero;

class BarberosController extends CrudController
{
    protected function ruta(): string
    {
        return 'barberos';
    }

    protected function crearModelo(): CrudModel
    {
        return new Barbero();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_completo', 'etiqueta' => 'Barbero', 'sub' => 'cedula'],
            ['campo' => 'telefono', 'etiqueta' => 'Teléfono'],
            ['campo' => 'porcentaje_comision', 'etiqueta' => 'Comisión', 'formato' => 'porcentaje'],
            ['campo' => 'sueldo_base', 'etiqueta' => 'Sueldo base', 'formato' => 'moneda'],
            ['campo' => 'es_principal', 'etiqueta' => 'Principal', 'formato' => 'sino'],
            ['campo' => 'citas_pendientes', 'etiqueta' => 'Citas pendientes', 'formato' => 'entero'],
        ];
    }
}
