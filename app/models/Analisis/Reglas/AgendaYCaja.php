<?php

namespace App\Models\Analisis\Reglas;

use App\Helpers\Formato;
use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Citas canceladas o sin marcar y diferencias en los cierres de caja. */
class AgendaYCaja extends ReglaAnalisis
{
    private const MINIMO_CITAS = 8;
    private const PORCENTAJE_CANCELACION = 20;

    public function evaluar(): array
    {
        $hallazgos = [];

        $citas = $this->fila(
            "SELECT COUNT(*) AS total, SUM(estado_cita = 'Cancelada') AS canceladas FROM citas
             WHERE status = 1 AND fecha_cita BETWEEN DATE_SUB(NOW(), INTERVAL :dias DAY) AND NOW()",
            [':dias' => self::VENTANA_DIAS]
        );
        $total = (int) ($citas['total'] ?? 0);
        if ($total >= self::MINIMO_CITAS) {
            $porcentaje = (int) round((int) $citas['canceladas'] / $total * 100);
            if ($porcentaje >= self::PORCENTAJE_CANCELACION) {
                $hallazgos[] = new Hallazgo(
                    'citas-canceladas', 'Agenda', Hallazgo::MEDIA,
                    "Se cancela el $porcentaje% de las citas",
                    "{$citas['canceladas']} de $total citas de los últimos " . self::VENTANA_DIAS
                    . ' días se cancelaron. Confirma por WhatsApp el día anterior y ofrece el espacio libre a otro cliente.',
                    'fa-calendar-times',
                    'citas'
                );
            }
        }

        $sinMarcar = (int) ($this->fila(
            "SELECT COUNT(*) AS n FROM citas WHERE status = 1 AND estado_cita = 'Pendiente' AND fecha_cita < DATE_SUB(NOW(), INTERVAL 2 HOUR)"
        )['n'] ?? 0);
        if ($sinMarcar > 0) {
            $hallazgos[] = new Hallazgo(
                'citas-sin-marcar', 'Agenda', Hallazgo::MEDIA,
                "$sinMarcar " . ($sinMarcar === 1 ? 'cita pasada sigue pendiente' : 'citas pasadas siguen pendientes'),
                'Márcalas como completadas o canceladas para que la agenda y este análisis sean exactos.',
                'fa-calendar-check',
                'citas'
            );
        }

        $abierta = $this->fila("SELECT id_cierre, fecha_inicio FROM cierre_caja WHERE status = 1 AND estado = 'Abierta' AND fecha_inicio < CURDATE() ORDER BY id_cierre LIMIT 1");
        if ($abierta) {
            $hallazgos[] = new Hallazgo(
                'caja-abierta', 'Caja', Hallazgo::MEDIA,
                "La caja #{$abierta['id_cierre']} sigue abierta desde el " . Formato::valor($abierta['fecha_inicio'], 'fecha'),
                'Ciérrala contando el dinero para que los ingresos de cada día queden separados.',
                'fa-coins',
                'cierres'
            );
        }

        $diferencias = $this->fila(
            "SELECT COUNT(*) AS cierres, SUM(diferencia < 0) AS con_faltante, COALESCE(SUM(LEAST(diferencia, 0)), 0) AS faltante
             FROM cierre_caja WHERE status = 1 AND estado = 'Cerrada' AND fecha_cierre >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        if ((int) ($diferencias['con_faltante'] ?? 0) > 0) {
            $hallazgos[] = new Hallazgo(
                'faltante-caja', 'Caja', Hallazgo::MEDIA,
                "Faltó dinero en {$diferencias['con_faltante']} de {$diferencias['cierres']} cierres de caja",
                'En los últimos 30 días faltaron ' . $this->moneda(abs((float) $diferencias['faltante'])) . ' en total. Revisa los pagos y retiros registrados de esos días.',
                'fa-balance-scale',
                'cierres'
            );
        }

        return $hallazgos;
    }
}
