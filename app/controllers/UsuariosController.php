<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Permiso;
use App\Models\Usuario;

class UsuariosController extends CrudController
{
    protected function ruta(): string
    {
        return 'usuarios';
    }

    protected function crearModelo(): CrudModel
    {
        return new Usuario();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'nombre_usuario', 'etiqueta' => 'Usuario', 'sub' => 'email_usuario'],
            ['campo' => 'nombre_rol', 'etiqueta' => 'Rol'],
            ['campo' => 'ultimo_acceso', 'etiqueta' => 'Último acceso', 'formato' => 'fechahora'],
            ['campo' => 'fecha_registro', 'etiqueta' => 'Registrado', 'formato' => 'fecha'],
            ['campo' => 'bloqueado', 'etiqueta' => 'Bloqueado', 'formato' => 'sino'],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'scripts' => ['assets/js/permisos.js'],
            'acciones' => [
                // Excepciones a los permisos del rol: verlas exige consultar; guardarlas, modificar.
                ['clave' => 'permisos', 'etiqueta' => 'Permisos', 'icono' => 'fa-shield-alt', 'extension' => 'permisos', 'permiso' => 'consultar'],
                ['clave' => 'desbloquear', 'etiqueta' => 'Desbloquear', 'icono' => 'fa-unlock', 'si' => ['bloqueado' => 1],
                    'confirmar' => '¿Desbloquear este usuario para que pueda volver a iniciar sesión?'],
            ],
        ];
    }

    protected function permisoAccionPropia(string $accion): string
    {
        return $accion === 'guardar_permisos' ? 'modificar' : parent::permisoAccionPropia($accion);
    }

    protected function accionPersonalizada(string $accion): void
    {
        if ($accion === 'permisos') {
            $id = $this->modelo->normalizarId($_GET['id'] ?? null);
            $this->responderJson(['ok' => true, 'datos' => (new Permiso())->matrizUsuario($id)]);
        }
        if ($accion === 'guardar_permisos') {
            $entrada = $this->entradaAccion();
            (new Permiso())->guardarUsuario($entrada['id'], $entrada['permisos'] ?? null, $this->idUsuario());
            $this->responderJson(['ok' => true, 'mensaje' => 'Permisos del usuario guardados.']);
        }
        if ($accion !== 'desbloquear') {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        $this->modelo->desbloquear($entrada['id']);
        $this->responderJson(['ok' => true, 'mensaje' => 'Usuario desbloqueado.']);
    }
}
