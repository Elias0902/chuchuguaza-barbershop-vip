<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
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
            'acciones' => [
                ['clave' => 'desbloquear', 'etiqueta' => 'Desbloquear', 'icono' => 'fa-unlock', 'si' => ['bloqueado' => 1],
                    'confirmar' => '¿Desbloquear este usuario para que pueda volver a iniciar sesión?'],
            ],
        ];
    }

    protected function accionPersonalizada(string $accion): void
    {
        if ($accion !== 'desbloquear') {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        $this->modelo->desbloquear($entrada['id']);
        $this->responderJson(['ok' => true, 'mensaje' => 'Usuario desbloqueado.']);
    }
}
