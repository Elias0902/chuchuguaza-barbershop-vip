<?php

namespace App\Controllers;

use App\Core\BaseController;

/**
 * Páginas legales públicas (no requieren sesión):
 *   index.php?url=legal&action=terminos     Términos de uso
 *   index.php?url=legal&action=privacidad   Política de privacidad
 *   index.php?url=legal&action=autoria      Autoría y propiedad del software
 */
class LegalController extends BaseController
{
    private const PAGINAS = [
        'terminos' => 'Términos de uso',
        'privacidad' => 'Política de privacidad',
        'autoria' => 'Autoría y propiedad del software',
    ];

    public function requiereSesion(): bool
    {
        return false;
    }

    public function ejecutar(string $accion): void
    {
        $pagina = isset(self::PAGINAS[$accion]) ? $accion : 'terminos';
        $this->render('legalView', ['pagina' => $pagina, 'paginas' => self::PAGINAS]);
    }
}
