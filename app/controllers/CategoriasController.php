<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Categoria;

class CategoriasController extends CrudController
{
    protected function ruta(): string
    {
        return 'categorias';
    }

    protected function crearModelo(): CrudModel
    {
        return new Categoria();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_categoria', 'etiqueta' => 'Categoría'],
            ['campo' => 'productos', 'etiqueta' => 'Productos activos', 'formato' => 'entero'],
        ];
    }
}
