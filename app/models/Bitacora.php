<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;

/**
 * Bitácora de seguridad: solo consulta.
 * Reescribe las operaciones de escritura para impedirlas (polimorfismo).
 */
class Bitacora extends CrudModel
{
    /** Cantidad máxima de movimientos que se consultan. */
    private const LIMITE = 1000;

    protected string $tabla = 'bitacoras';
    protected string $clave = 'id_bitacora';
    protected string $baseDatos = self::SEGURIDAD;
    protected bool $tieneEstado = false;
    protected string $modulo = 'Bitácora';
    protected string $singular = 'movimiento';

    public function campos(): array
    {
        return [];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT b.id_bitacora, b.modulo, b.accion, b.descripcion, b.fecha_bitacora,
                    COALESCE(u.nombre_usuario, \'Sistema\') AS usuario
                FROM bitacoras b
                LEFT JOIN usuarios u ON u.id_usuario = b.id_usuario
                ORDER BY b.fecha_bitacora DESC, b.id_bitacora DESC
                LIMIT ' . self::LIMITE;
    }

    public function registrar(array $entrada): int|string
    {
        throw new ValidacionException('La bitácora es de solo consulta.');
    }

    public function modificar(int|string $id, array $entrada): void
    {
        throw new ValidacionException('La bitácora es de solo consulta.');
    }
}
