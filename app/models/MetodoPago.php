<?php

namespace App\Models;

use App\Core\CrudModel;

class MetodoPago extends CrudModel
{
    protected string $tabla = 'metodo_pago';
    protected string $clave = 'id_metodo';
    protected string $modulo = 'Métodos de pago';
    protected string $singular = 'método de pago';
    protected ?string $campoDescriptivo = 'nombre_metodo';

    public function campos(): array
    {
        return [
            'nombre_metodo' => [
                'etiqueta' => 'Nombre del método', 'tipo' => 'texto', 'requerido' => true, 'max' => 50,
                'unico' => true, 'ancho' => 'completo', 'placeholder' => 'Efectivo, pago móvil, punto de venta...',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT m.*, (SELECT COUNT(*) FROM pagos p WHERE p.id_metodo = m.id_metodo AND p.status = 1) AS pagos
                FROM metodo_pago m ORDER BY m.status DESC, m.nombre_metodo';
    }
}
