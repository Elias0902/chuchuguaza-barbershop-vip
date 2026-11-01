<?php

namespace App\Controllers;

use App\Core\CrudController;
use App\Core\CrudModel;
use App\Models\Nomina;

class NominaController extends CrudController
{
    protected function ruta(): string
    {
        return 'nomina';
    }

    protected function crearModelo(): CrudModel
    {
        return new Nomina();
    }

    protected function columnas(): array
    {
        return [
            ['campo' => 'barbero', 'etiqueta' => 'Barbero', 'sub' => 'cedula_barbero'],
            ['campo' => 'fecha_inicio', 'etiqueta' => 'Desde', 'formato' => 'fecha'],
            ['campo' => 'fecha_cierre', 'etiqueta' => 'Hasta', 'formato' => 'fecha'],
            ['campo' => 'total_cortes', 'etiqueta' => 'Cortes', 'formato' => 'entero'],
            ['campo' => 'monto_comisiones', 'etiqueta' => 'Comisiones', 'formato' => 'moneda'],
            ['campo' => 'sueldo_base', 'etiqueta' => 'Sueldo base', 'formato' => 'moneda'],
            ['campo' => 'total_pagar', 'etiqueta' => 'Total a pagar', 'formato' => 'moneda'],
            ['campo' => 'estado_pago', 'etiqueta' => 'Pago', 'formato' => 'insignia', 'colores' => ['Pendiente' => 'amarillo', 'Pagado' => 'verde']],
        ];
    }

    protected function configuracionVista(): array
    {
        return [
            'edicionSi' => ['estado_pago' => 'Pendiente'],
            'acciones' => [
                ['clave' => 'pagar', 'etiqueta' => 'Marcar pagada', 'icono' => 'fa-check', 'si' => ['estado_pago' => 'Pendiente'],
                    'confirmar' => '¿Confirmas que esta nómina ya fue pagada? Después no se podrá modificar.'],
            ],
        ];
    }

    protected function accionPersonalizada(string $accion): void
    {
        if ($accion !== 'pagar') {
            parent::accionPersonalizada($accion);
        }

        $entrada = $this->entradaAccion();
        $this->modelo->marcarPagada($entrada['id']);
        $this->responderJson(['ok' => true, 'mensaje' => 'Nómina marcada como pagada.']);
    }
}
