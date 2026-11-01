<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Producto;

class ProductosController extends CrudController
{
    protected function ruta(): string
    {
        return 'productos';
    }

    protected function crearModelo(): CrudModel
    {
        return new Producto();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_producto', 'etiqueta' => 'Producto', 'sub' => 'nombre_categoria'],
            ['campo' => 'precio', 'etiqueta' => 'Precio', 'formato' => 'moneda'],
            ['campo' => 'stock', 'etiqueta' => 'Stock', 'formato' => 'entero'],
            [
                'campo' => 'nivel_stock', 'etiqueta' => 'Nivel', 'formato' => 'insignia',
                'colores' => ['Disponible' => 'verde', 'Bajo' => 'amarillo', 'Agotado' => 'rojo'],
            ],
        ];
    }
}
