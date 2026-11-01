<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Pago;
use App\Models\Ticket;

class TicketsController extends CrudController
{
    protected function ruta(): string
    {
        return 'tickets';
    }

    protected function crearModelo(): CrudModel
    {
        return new Ticket();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id_ticket', 'etiqueta' => 'N.º', 'formato' => 'numero'],
            ['campo' => 'fecha_ticket', 'etiqueta' => 'Fecha', 'formato' => 'fechahora'],
            ['campo' => 'cliente', 'etiqueta' => 'Cliente', 'sub' => 'barbero'],
            ['campo' => 'conceptos', 'etiqueta' => 'Servicios y productos'],
            ['campo' => 'total', 'etiqueta' => 'Total', 'formato' => 'moneda'],
            ['campo' => 'saldo', 'etiqueta' => 'Saldo', 'formato' => 'moneda'],
            [
                'campo' => 'estado_pago', 'etiqueta' => 'Pago', 'formato' => 'insignia',
                'colores' => ['Pendiente' => 'amarillo', 'Pagado' => 'verde', 'Anulado' => 'rojo'],
            ],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'opciones' => ['metodos' => (new Pago())->opcionesCampo('id_metodo')],
            'acciones' => [
                [
                    'clave' => 'cobrar', 'etiqueta' => 'Cobrar', 'icono' => 'fa-money-bill-wave', 'si' => ['estado_pago' => 'Pendiente'],
                    'titulo' => 'Registrar pago del ticket',
                    'pedir' => [
                        'id_metodo' => ['etiqueta' => 'Método de pago', 'tipo' => 'relacion', 'fuente' => 'metodos', 'requerido' => true],
                        'monto' => ['etiqueta' => 'Monto', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0.01, 'defectoFila' => 'saldo'],
                        'referencia' => ['etiqueta' => 'Referencia (opcional)', 'tipo' => 'texto', 'max' => 50],
                    ],
                ],
            ],
        ];
    }

    /** Cobrar: registra un pago del ticket desde la misma tabla. */
    protected function accionPersonalizada(string $accion): void
    {
        if ($accion !== 'cobrar') {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        (new Pago())->registrar([
            'id_ticket' => $entrada['id'],
            'id_metodo' => $entrada['id_metodo'] ?? null,
            'monto' => $entrada['monto'] ?? null,
            'referencia' => $entrada['referencia'] ?? null,
            'fecha_pago' => date('Y-m-d H:i:s'),
        ]);
        $this->responderJson(['ok' => true, 'mensaje' => 'Pago registrado.']);
    }
}
