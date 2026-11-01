<?php

namespace App\Core;

/**
 * Contrato de los módulos con Consultar, Registrar, Modificar y Eliminación lógica.
 * Todo modelo de módulo lo cumple a través de CrudModel.
 */
interface CrudInterface
{
    /** Listado completo de la tabla (activos y eliminados). */
    public function consultar(): array;

    public function obtener(int|string $id): ?array;

    /** Devuelve la clave del registro creado. */
    public function registrar(array $entrada): int|string;

    public function modificar(int|string $id, array $entrada): void;

    /** Eliminación lógica: status = 0. */
    public function eliminar(int|string $id): void;

    /** Vuelve a activar un registro eliminado: status = 1. */
    public function restaurar(int|string $id): void;
}
