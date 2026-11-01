<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\CierreCaja;

class CierresCajaController extends CrudController
{
    protected function ruta(): string
    {
        return 'cierres';
    }

    protected function crearModelo(): CrudModel
    {
        return new CierreCaja();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'id_cierre', 'etiqueta' => 'Caja', 'formato' => 'numero'],
            ['campo' => 'fecha_inicio', 'etiqueta' => 'Apertura', 'formato' => 'fechahora'],
            ['campo' => 'fecha_cierre', 'etiqueta' => 'Cierre', 'formato' => 'fechahora'],
            ['campo' => 'monto_inicial', 'etiqueta' => 'Inicial', 'formato' => 'moneda'],
            ['campo' => 'ingresos', 'etiqueta' => 'Ingresos', 'formato' => 'moneda'],
            ['campo' => 'egresos', 'etiqueta' => 'Gastos y retiros', 'formato' => 'moneda'],
            ['campo' => 'monto_sistema', 'etiqueta' => 'Según sistema', 'formato' => 'moneda'],
            ['campo' => 'contado', 'etiqueta' => 'Contado', 'formato' => 'moneda'],
            ['campo' => 'descuadre', 'etiqueta' => 'Diferencia', 'formato' => 'moneda'],
            ['campo' => 'estado', 'etiqueta' => 'Caja', 'formato' => 'insignia', 'colores' => ['Abierta' => 'verde', 'Cerrada' => 'gris']],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'edicionSi' => ['estado' => 'Abierta'],
            'acciones' => [
                [
                    'clave' => 'cerrar', 'etiqueta' => 'Cerrar caja', 'icono' => 'fa-lock', 'si' => ['estado' => 'Abierta'],
                    'titulo' => 'Cerrar caja',
                    'pedir' => [
                        'monto_real_caja' => [
                            'etiqueta' => 'Monto contado en caja', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0,
                            'defectoFila' => 'monto_sistema',
                            'ayuda' => 'Cuenta el dinero y escribe el total; se compara con lo que indica el sistema.',
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function accionPersonalizada(string $accion): void
    {
        if ($accion !== 'cerrar') {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        $this->modelo->cerrar($entrada['id'], $entrada['monto_real_caja'] ?? null);
        $this->responderJson(['ok' => true, 'mensaje' => 'Caja cerrada correctamente.']);
    }
}
