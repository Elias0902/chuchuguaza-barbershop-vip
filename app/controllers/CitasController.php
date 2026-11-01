<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Cita;

class CitasController extends CrudController
{
    protected function ruta(): string
    {
        return 'citas';
    }

    protected function crearModelo(): CrudModel
    {
        return new Cita();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'fecha_cita', 'etiqueta' => 'Fecha y hora', 'formato' => 'fechahora'],
            ['campo' => 'cliente', 'etiqueta' => 'Cliente', 'sub' => 'telefono_cliente'],
            ['campo' => 'barbero', 'etiqueta' => 'Barbero'],
            [
                'campo' => 'estado_cita', 'etiqueta' => 'Estado de la cita', 'formato' => 'insignia',
                'colores' => ['Pendiente' => 'amarillo', 'Completada' => 'verde', 'Cancelada' => 'rojo'],
            ],
            ['campo' => 'observacion', 'etiqueta' => 'Observación'],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'acciones' => [
                ['clave' => 'completar', 'etiqueta' => 'Completar', 'icono' => 'fa-check', 'si' => ['estado_cita' => 'Pendiente'],
                    'confirmar' => '¿Marcar la cita como completada?'],
                ['clave' => 'cancelar', 'etiqueta' => 'Cancelar', 'icono' => 'fa-ban', 'si' => ['estado_cita' => 'Pendiente'],
                    'confirmar' => '¿Cancelar esta cita?', 'peligro' => true],
            ],
        ];
    }

    protected function accionPersonalizada(string $accion): void
    {
        $estados = ['completar' => 'Completada', 'cancelar' => 'Cancelada'];
        if (!isset($estados[$accion])) {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        $this->modelo->cambiarEstado($entrada['id'], $estados[$accion]);
        $this->responderJson(['ok' => true, 'mensaje' => 'Cita marcada como ' . mb_strtolower($estados[$accion], 'UTF-8') . '.']);
    }
}
