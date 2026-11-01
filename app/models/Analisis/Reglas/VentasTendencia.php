<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Compara los últimos 30 días con los 30 anteriores: ingresos y ticket promedio. */
class VentasTendencia extends ReglaAnalisis
{
    public function evaluar(): array
    {
        $hallazgos = [];

        $actual = (float) ($this->fila('SELECT COALESCE(SUM(monto), 0) AS total FROM pagos WHERE status = 1 AND fecha_pago >= DATE_SUB(NOW(), INTERVAL 30 DAY)')['total'] ?? 0);
        $anterior = (float) ($this->fila(
            'SELECT COALESCE(SUM(monto), 0) AS total FROM pagos
             WHERE status = 1 AND fecha_pago >= DATE_SUB(NOW(), INTERVAL 60 DAY) AND fecha_pago < DATE_SUB(NOW(), INTERVAL 30 DAY)'
        )['total'] ?? 0);

        if ($anterior > 0) {
            $cambio = (int) round(($actual - $anterior) / $anterior * 100);
            $comparacion = 'Cobraste ' . $this->moneda($actual) . ' en los últimos 30 días frente a ' . $this->moneda($anterior) . ' en los 30 anteriores.';
            if ($cambio <= -5) {
                $hallazgos[] = new Hallazgo(
                    'ingresos-bajan', 'Ventas', $cambio <= -15 ? Hallazgo::ALTA : Hallazgo::MEDIA,
                    'Los ingresos bajaron un ' . abs($cambio) . '%',
                    "$comparacion Revisa los días más flojos, contacta a los clientes que no vuelven y lanza una promoción.",
                    'fa-chart-line',
                    'analisis',
                    'Ver análisis'
                );
            } elseif ($cambio >= 10) {
                $hallazgos[] = new Hallazgo(
                    'ingresos-suben', 'Ventas', Hallazgo::POSITIVA,
                    "Los ingresos subieron un $cambio%",
                    "$comparacion ¡Buen trabajo! Aprovecha para fidelizar a los clientes nuevos.",
                    'fa-chart-line',
                    'analisis',
                    'Ver análisis'
                );
            }
        }

        $tickets = $this->fila(
            'SELECT AVG(CASE WHEN fecha_ticket >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total END) AS actual,
                    AVG(CASE WHEN fecha_ticket < DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total END) AS anterior
             FROM ticket_atencion WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL 60 DAY)'
        );
        $promedioActual = (float) ($tickets['actual'] ?? 0);
        $promedioAnterior = (float) ($tickets['anterior'] ?? 0);
        if ($promedioAnterior > 0 && $promedioActual > 0 && ($promedioAnterior - $promedioActual) / $promedioAnterior >= 0.1) {
            $hallazgos[] = new Hallazgo(
                'ticket-promedio', 'Ventas', Hallazgo::MEDIA,
                'El ticket promedio bajó de ' . $this->moneda($promedioAnterior) . ' a ' . $this->moneda($promedioActual),
                'Cada cliente está gastando menos. Ofrece complementos (barba, cejas, diseño) o un producto al terminar el servicio.',
                'fa-receipt',
                'tickets'
            );
        }

        return $hallazgos;
    }
}
