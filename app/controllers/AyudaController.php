<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Centro de ayuda: preguntas frecuentes y documentación general del sistema
 * (config/documentacion.php) con un buscador. Lo ve cualquier usuario con sesión.
 */
class AyudaController extends BaseController
{
    public function ejecutar(string $accion): void
    {
        $this->render('ayudaView', [
            'activePage' => 'ayuda',
            'temas' => require dirname(__DIR__, 2) . '/config/documentacion.php',
        ]);
    }
}
