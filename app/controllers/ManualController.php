<?php

namespace App\Controllers;

use App\Core\Autorizacion;
use App\Core\BaseController;

/**
 * Manual de usuario: una sección por cada módulo que el usuario puede abrir,
 * con su descripción, los consejos de config/ayuda.php y las capturas de pantalla.
 * Lo ve cualquier usuario con sesión (no exige permisos).
 */
class ManualController extends BaseController
{
    public function ejecutar(string $accion): void
    {
        $raiz = dirname(__DIR__, 2);
        $consejos = require $raiz . '/config/ayuda.php';
        $capitulos = [];
        foreach ((new Autorizacion())->menuPermitido() as $grupo) {
            $modulos = [];
            foreach ($grupo['modulos'] as $ruta => $modulo) {
                $capturas = [];
                foreach (['listado' => 'Pantalla principal', 'formulario' => 'Formulario de registro'] as $parte => $texto) {
                    $archivo = "assets/img/ayuda/$ruta/$parte.png";
                    if (is_file("$raiz/$archivo")) {
                        $capturas[] = ['src' => $archivo . '?v=' . filemtime("$raiz/$archivo"), 'texto' => $texto];
                    }
                }
                $modulos[] = $modulo + ['ruta' => $ruta, 'consejos' => $consejos[$ruta] ?? [], 'capturas' => $capturas];
            }
            $capitulos[] = ['titulo' => $grupo['titulo'], 'modulos' => $modulos];
        }

        $this->render('manualView', ['activePage' => 'manual', 'capitulos' => $capitulos]);
    }
}
