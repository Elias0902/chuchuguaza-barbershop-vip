<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;

class Promocion extends CrudModel
{
    protected string $tabla = 'promociones';
    protected string $clave = 'id_promocion';
    protected string $modulo = 'Promociones';
    protected string $singular = 'promoción';
    protected bool $femenino = true;
    protected ?string $campoDescriptivo = 'nombre_promocion';

    public function campos(): array
    {
        return [
            'nombre_promocion' => [
                'etiqueta' => 'Nombre de la promoción', 'tipo' => 'texto', 'requerido' => true, 'max' => 100,
                'ancho' => 'completo', 'placeholder' => 'Martes de corte + barba',
            ],
            'descuento_porcentaje' => ['etiqueta' => 'Descuento (%)', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0.01, 'maxValor' => 100],
            'fecha_inicio' => ['etiqueta' => 'Fecha de inicio', 'tipo' => 'fecha', 'requerido' => true, 'defecto' => 'hoy'],
            'fecha_cierre' => ['etiqueta' => 'Fecha de cierre', 'tipo' => 'fecha', 'requerido' => true],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT p.*,
                    CASE WHEN CURDATE() < p.fecha_inicio THEN 'Programada'
                         WHEN CURDATE() > p.fecha_cierre THEN 'Vencida'
                         ELSE 'Vigente' END AS vigencia
                FROM promociones p
                ORDER BY p.status DESC, p.fecha_inicio DESC";
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        if ($datos['fecha_cierre'] < $datos['fecha_inicio']) {
            throw new ValidacionException('La fecha de cierre no puede ser anterior a la fecha de inicio.', 'fecha_cierre');
        }
    }
}
