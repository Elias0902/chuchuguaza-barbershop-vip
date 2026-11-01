<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Cliente;

class ClientesController extends CrudController
{
    protected function ruta(): string
    {
        return 'clientes';
    }

    protected function crearModelo(): CrudModel
    {
        return new Cliente();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_completo', 'etiqueta' => 'Cliente'],
            ['campo' => 'telefono', 'etiqueta' => 'Teléfono'],
            ['campo' => 'email', 'etiqueta' => 'Correo'],
            ['campo' => 'visitas', 'etiqueta' => 'Visitas', 'formato' => 'entero'],
        ];
    }
}
