<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Models\Politica;
use Throwable;

/**
 * Datos de solo lectura para la landing pública (no requiere sesión).
 *   index.php?url=publico&action=politicas
 */
class PublicoController extends BaseController
{
    public function requiereSesion(): bool
    {
        return false;
    }

    public function ejecutar(string $accion): void
    {
        if ($accion !== 'politicas') {
            $this->responderJson(['ok' => false, 'mensaje' => 'Recurso no encontrado.'], 404);
        }

        try {
            $this->responderJson(['ok' => true, 'datos' => (new Politica())->publicas()]);
        } catch (Throwable $e) {
            error_log('No se pudieron leer las políticas públicas: ' . $e->getMessage());
            $this->responderJson(['ok' => false, 'mensaje' => 'No se pudieron cargar las políticas.'], 500);
        }
    }
}
