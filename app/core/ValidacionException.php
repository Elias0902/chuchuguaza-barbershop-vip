<?php

namespace App\Core;

use InvalidArgumentException;

/**
 * Error de validación que el usuario puede corregir.
 * Guarda el campo afectado para resaltarlo en el formulario.
 */
class ValidacionException extends InvalidArgumentException
{
    public function __construct(string $mensaje, private ?string $campo = null)
    {
        parent::__construct($mensaje);
    }

    public function campo(): ?string
    {
        return $this->campo;
    }
}
