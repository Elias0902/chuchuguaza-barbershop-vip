<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Permiso;
use App\Models\Rol;

class RolesController extends CrudController
{
    protected function ruta(): string
    {
        return 'roles';
    }

    protected function crearModelo(): CrudModel
    {
        return new Rol();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_rol', 'etiqueta' => 'Rol'],
            ['campo' => 'descripcion', 'etiqueta' => 'Descripción'],
            ['campo' => 'modulos', 'etiqueta' => 'Módulos con acceso', 'formato' => 'entero'],
            ['campo' => 'usuarios', 'etiqueta' => 'Usuarios activos', 'formato' => 'entero'],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'scripts' => ['assets/js/permisos.js'],
            'acciones' => [
                // Ver la matriz solo exige consultar; guardarla exige modificar (ver permisoAccionPropia).
                ['clave' => 'permisos', 'etiqueta' => 'Permisos', 'icono' => 'fa-shield-alt', 'extension' => 'permisos', 'permiso' => 'consultar'],
            ],
        ];
    }

    protected function permisoAccionPropia(string $accion): string
    {
        return $accion === 'guardar_permisos' ? 'modificar' : parent::permisoAccionPropia($accion);
    }

    protected function accionPersonalizada(string $accion): void
    {
        $permisos = new Permiso();

        if ($accion === 'permisos') {
            $id = $this->modelo->normalizarId($_GET['id'] ?? null);
            $this->responderJson(['ok' => true, 'datos' => $permisos->matriz($id)]);
        }

        if ($accion === 'guardar_permisos') {
            $entrada = $this->entradaAccion();
            $permisos->guardar($entrada['id'], $entrada['permisos'] ?? null, $this->idUsuario());
            $this->responderJson(['ok' => true, 'mensaje' => 'Permisos del rol guardados.']);
        }

        parent::accionPersonalizada($accion);
    }
}
