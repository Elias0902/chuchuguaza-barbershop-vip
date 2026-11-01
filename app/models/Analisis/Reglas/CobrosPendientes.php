<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Tickets sin cobrar, saldos de cursos y nóminas por pagar. */
class CobrosPendientes extends ReglaAnalisis
{
    public function evaluar(): array
    {
        $hallazgos = [];

        $tickets = $this->fila(
            "SELECT COUNT(*) AS cantidad, COALESCE(SUM(t.total - COALESCE(p.pagado, 0)), 0) AS saldo
             FROM ticket_atencion t
             LEFT JOIN (SELECT id_ticket, SUM(monto) AS pagado FROM pagos WHERE status = 1 GROUP BY id_ticket) p ON p.id_ticket = t.id_ticket
             WHERE t.status = 1 AND t.estado_pago = 'Pendiente' AND t.fecha_ticket < DATE_SUB(NOW(), INTERVAL 1 DAY)"
        );
        if ((int) ($tickets['cantidad'] ?? 0) > 0) {
            $hallazgos[] = new Hallazgo(
                'tickets-sin-cobrar', 'Caja', Hallazgo::ALTA,
                "{$tickets['cantidad']} " . ((int) $tickets['cantidad'] === 1 ? 'ticket sin cobrar' : 'tickets sin cobrar') . ' desde hace más de un día',
                'Suman ' . $this->moneda((float) $tickets['saldo']) . ' por cobrar. Revisa si se pagaron y no se registró el pago, o contacta a esos clientes.',
                'fa-receipt',
                'tickets'
            );
        }

        $cursos = $this->fila(
            'SELECT COUNT(*) AS cantidad, COALESCE(SUM(cu.costo - i.monto_pagado), 0) AS saldo
             FROM inscripcion_curso i INNER JOIN cursos cu ON cu.id_curso = i.id_curso
             WHERE i.status = 1 AND cu.costo > i.monto_pagado'
        );
        if ((int) ($cursos['cantidad'] ?? 0) > 0) {
            $hallazgos[] = new Hallazgo(
                'saldo-cursos', 'Ventas', Hallazgo::MEDIA,
                "{$cursos['cantidad']} " . ((int) $cursos['cantidad'] === 1 ? 'alumno debe' : 'alumnos deben') . ' parte de su curso',
                'El saldo pendiente de las inscripciones es de ' . $this->moneda((float) $cursos['saldo']) . '. Recuérdales el pago en la próxima clase.',
                'fa-user-graduate',
                'inscripciones'
            );
        }

        $nominas = $this->fila("SELECT COUNT(*) AS cantidad, COALESCE(SUM(total_pagar), 0) AS total FROM nomina WHERE status = 1 AND estado_pago = 'Pendiente'");
        if ((int) ($nominas['cantidad'] ?? 0) > 0) {
            $hallazgos[] = new Hallazgo(
                'nomina-pendiente', 'Equipo', Hallazgo::MEDIA,
                "{$nominas['cantidad']} " . ((int) $nominas['cantidad'] === 1 ? 'nómina por pagar' : 'nóminas por pagar'),
                'El total pendiente a los barberos es de ' . $this->moneda((float) $nominas['total']) . '.',
                'fa-money-bill-wave',
                'nomina'
            );
        }

        return $hallazgos;
    }
}
