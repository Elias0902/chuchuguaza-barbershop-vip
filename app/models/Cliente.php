<?php

namespace App\Models;

use App\Core\CrudModel;

class Cliente extends CrudModel
{
    protected string $tabla = 'cliente';
    protected string $clave = 'id_cliente';
    protected string $modulo = 'Clientes';
    protected string $singular = 'cliente';

    public function campos(): array
    {
        return [
            'nombre' => ['etiqueta' => 'Nombre', 'tipo' => 'nombre', 'requerido' => true, 'max' => 50],
            'apellido' => ['etiqueta' => 'Apellido', 'tipo' => 'nombre', 'requerido' => true, 'max' => 50],
            'telefono' => ['etiqueta' => 'Teléfono', 'tipo' => 'telefono', 'requerido' => true, 'max' => 15, 'placeholder' => '0414-1234567'],
            'email' => ['etiqueta' => 'Correo', 'tipo' => 'email', 'max' => 50, 'unico' => true, 'ayuda' => 'Opcional.'],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT c.*, CONCAT(c.nombre, ' ', c.apellido) AS nombre_completo,
                    (SELECT COUNT(*) FROM ticket_atencion t WHERE t.id_cliente = c.id_cliente AND t.status = 1) AS visitas
                FROM cliente c
                ORDER BY c.status DESC, c.apellido, c.nombre";
    }

    protected function describir(array $fila): string
    {
        return 'cliente #' . $fila['id_cliente'] . ' (' . trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido'] ?? '')) . ')';
    }
}
