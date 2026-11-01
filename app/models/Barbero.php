<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

class Barbero extends CrudModel
{
    protected string $tabla = 'barberos';
    protected string $clave = 'cedula';
    protected bool $claveAutoincremental = false;
    protected string $modulo = 'Barberos';
    protected string $singular = 'barbero';

    public function campos(): array
    {
        return [
            'cedula' => [
                'etiqueta' => 'Cédula', 'tipo' => 'cedula', 'requerido' => true, 'max' => 15, 'unico' => true,
                'fijo' => true, 'placeholder' => 'V-12345678', 'ayuda' => 'No se puede cambiar después de registrarla.',
            ],
            'telefono' => ['etiqueta' => 'Teléfono', 'tipo' => 'telefono', 'max' => 15, 'placeholder' => '0414-1234567', 'ayuda' => 'Opcional.'],
            'nombre' => ['etiqueta' => 'Nombre', 'tipo' => 'nombre', 'requerido' => true, 'max' => 50],
            'apellido' => ['etiqueta' => 'Apellido', 'tipo' => 'nombre', 'requerido' => true, 'max' => 50],
            'porcentaje_comision' => [
                'etiqueta' => 'Comisión (%)', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 100,
                'defecto' => '50.00', 'ayuda' => 'Porcentaje de cada servicio que gana el barbero.',
            ],
            'sueldo_base' => ['etiqueta' => 'Sueldo base', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99, 'defecto' => '0.00'],
            'es_principal' => [
                'etiqueta' => 'Barbero principal / dueño', 'tipo' => 'booleano', 'ancho' => 'completo',
                'ayuda' => 'Solo puede haber uno; al marcarlo se desmarca el anterior.',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT b.*, CONCAT(b.nombre, ' ', b.apellido) AS nombre_completo,
                    (SELECT COUNT(*) FROM citas c WHERE c.cedula_barbero = b.cedula AND c.status = 1
                        AND c.estado_cita = 'Pendiente' AND c.fecha_cita >= NOW()) AS citas_pendientes
                FROM barberos b
                ORDER BY b.status DESC, b.es_principal DESC, b.apellido, b.nombre";
    }

    protected function despuesDeGuardar(PDO $db, int|string $id, array $datos, ?array $actual): void
    {
        if ((int) ($datos['es_principal'] ?? 0) === 1) {
            $db->prepare('UPDATE barberos SET es_principal = 0 WHERE cedula <> :cedula')->execute([':cedula' => $id]);
        }
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        if ((int) $actual['es_principal'] === 1) {
            throw new ValidacionException('No puedes eliminar al barbero principal. Marca a otro como principal primero.');
        }
    }

    protected function describir(array $fila): string
    {
        return 'barbero ' . $fila['cedula'] . ' (' . trim(($fila['nombre'] ?? '') . ' ' . ($fila['apellido'] ?? '')) . ')';
    }
}
