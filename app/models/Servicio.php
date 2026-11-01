<?php

namespace App\Models;

use App\Core\CrudModel;

class Servicio extends CrudModel
{
    protected string $tabla = 'servicio';
    protected string $clave = 'id_servicio';
    protected string $modulo = 'Servicios';
    protected string $singular = 'servicio';
    protected ?string $campoDescriptivo = 'nombre_servicio';

    public function campos(): array
    {
        return [
            'nombre_servicio' => [
                'etiqueta' => 'Nombre del servicio', 'tipo' => 'texto', 'requerido' => true, 'max' => 100,
                'unico' => true, 'ancho' => 'completo', 'placeholder' => 'Corte clásico, barba, cejas...',
            ],
            'precio' => ['etiqueta' => 'Precio', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99],
            'duracion_minutos' => ['etiqueta' => 'Duración (minutos)', 'tipo' => 'entero', 'requerido' => true, 'min' => 1, 'maxValor' => 1440, 'defecto' => 30],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT * FROM servicio ORDER BY status DESC, nombre_servicio';
    }
}
