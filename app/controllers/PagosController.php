<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Pago;

class PagosController extends CrudController
{
    protected function ruta(): string
    {
        return 'pagos';
    }

    protected function crearModelo(): CrudModel
    {
        return new Pago();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha_pago', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'id_ticket', 'etiqueta' => 'Ticket', 'formato' => 'numero', 'sub' => 'cliente'],
            ['campo' => 'nombre_metodo', 'etiqueta' => 'Método'],
            ['campo' => 'referencia', 'etiqueta' => 'Referencia'],
            ['campo' => 'monto', 'etiqueta' => 'Monto', 'formato' => 'moneda'],
        ];
    }
}
