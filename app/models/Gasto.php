<?php

namespace App\Models;

use App\Core\CrudModel;

class Gasto extends CrudModel
{
    protected string $tabla = 'gastos_generales';
    protected string $clave = 'id_gasto';
    protected string $modulo = 'Gastos generales';
    protected string $singular = 'gasto';
    protected ?string $campoDescriptivo = 'descripcion';

    public function campos(): array
    {
        return [
            'descripcion' => [
                'etiqueta' => 'Descripción', 'tipo' => 'texto', 'requerido' => true, 'max' => 255, 'ancho' => 'completo',
                'placeholder' => 'Pago de luz, compra de toallas, mantenimiento...',
            ],
            'monto' => ['etiqueta' => 'Monto', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0.01, 'maxValor' => 99999999.99],
            'fecha_gasto' => ['etiqueta' => 'Fecha y hora', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
            'id_cierre' => [
                'etiqueta' => 'Caja', 'tipo' => 'relacion', 'ancho' => 'completo',
                'ayuda' => 'Asócialo a la caja abierta para descontarlo en el cierre. Opcional.',
                'relacion' => [
                    'tabla' => 'cierre_caja', 'clave' => 'id_cierre',
                    'texto' => "CONCAT('Caja #', id_cierre, ' · ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' (', estado, ')')",
                    'activo' => "status = 1 AND estado = 'Abierta'",
                    'orden' => 'valor DESC',
                ],
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT g.*, IF(g.id_cierre IS NULL, NULL, CONCAT('Caja #', g.id_cierre)) AS caja
                FROM gastos_generales g
                ORDER BY g.status DESC, g.fecha_gasto DESC";
    }
}
