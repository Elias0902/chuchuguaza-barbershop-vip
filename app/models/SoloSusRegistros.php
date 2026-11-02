<?php

namespace App\Models;

use App\Core\Autorizacion;
use App\Core\RegistroNoEncontradoException;

/**
 * Para tickets y citas: un usuario vinculado a un barbero solo ve, abre y registra
 * los suyos (el barbero se asigna solo al guardar). Los demás usuarios ven todo.
 */
trait SoloSusRegistros
{
    protected function barberoSesion(): ?string
    {
        return (new Autorizacion())->barberoSesion();
    }

    /** Condición SQL para el listado («1 = 1» si el usuario no es un barbero vinculado). */
    protected function condicionBarbero(string $alias): string
    {
        $cedula = $this->barberoSesion();

        return $cedula === null ? '1 = 1' : "$alias.cedula_barbero = " . $this->db()->quote($cedula);
    }

    /** Un barbero vinculado no puede abrir registros de otro barbero. */
    protected function verificarDueno(?array $registro): ?array
    {
        $cedula = $this->barberoSesion();
        if ($registro !== null && $cedula !== null && $registro['cedula_barbero'] !== $cedula) {
            throw new RegistroNoEncontradoException('Solo puedes ver tus propios registros.');
        }

        return $registro;
    }

    protected function asignarBarbero(array &$datos): void
    {
        $cedula = $this->barberoSesion();
        if ($cedula !== null) {
            $datos['cedula_barbero'] = $cedula;
        }
    }
}
