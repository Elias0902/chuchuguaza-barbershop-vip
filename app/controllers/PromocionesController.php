<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Promocion;

class PromocionesController extends CrudController
{
    protected function ruta(): string
    {
        return 'promociones';
    }

    protected function crearModelo(): CrudModel
    {
        return new Promocion();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_promocion', 'etiqueta' => 'Promoción'],
            ['campo' => 'descuento_porcentaje', 'etiqueta' => 'Descuento', 'formato' => 'porcentaje'],
            ['campo' => 'fecha_inicio', 'etiqueta' => 'Desde', 'formato' => 'fecha'],
            ['campo' => 'fecha_cierre', 'etiqueta' => 'Hasta', 'formato' => 'fecha'],
            [
                'campo' => 'vigencia', 'etiqueta' => 'Vigencia', 'formato' => 'insignia',
                'colores' => ['Vigente' => 'verde', 'Programada' => 'azul', 'Vencida' => 'gris'],
            ],
        ];
    }
}
