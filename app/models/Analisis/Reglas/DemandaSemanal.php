<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Día más flojo de la semana, hora pico y reparto de clientes entre barberos. */
class DemandaSemanal extends ReglaAnalisis
{
    /** Mínimo de atenciones en la ventana para que las conclusiones tengan sentido. */
    private const MINIMO_ATENCIONES = 20;

    private const DIAS = [1 => 'domingos', 2 => 'lunes', 3 => 'martes', 4 => 'miércoles', 5 => 'jueves', 6 => 'viernes', 7 => 'sábados'];

    public function evaluar(): array
    {
        $porDia = $this->filas(
            'SELECT DAYOFWEEK(fecha_ticket) AS dia, COUNT(*) AS cantidad FROM ticket_atencion
             WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY) GROUP BY dia',
            [':dias' => self::VENTANA_DIAS]
        );
        $total = array_sum(array_column($porDia, 'cantidad'));
        if ($total < self::MINIMO_ATENCIONES) {
            return [];
        }

        $hallazgos = [];
        $conteo = array_column($porDia, 'cantidad', 'dia');
        $promedio = $total / count($conteo); // solo días con atención: los días cerrados no cuentan
        $diaFlojo = array_search(min($conteo), $conteo);
        $diferencia = (int) round((1 - $conteo[$diaFlojo] / $promedio) * 100);
        if ($diferencia >= 25) {
            $hallazgos[] = new Hallazgo(
                'dia-flojo', 'Ventas', Hallazgo::BAJA,
                'Los ' . self::DIAS[$diaFlojo] . ' son el día más flojo',
                "Tienen un $diferencia% menos de atenciones que el promedio de los últimos " . self::VENTANA_DIAS
                . ' días. Una promoción solo para ese día (por ejemplo, corte + barba con descuento) puede llenarlo.',
                'fa-calendar-alt',
                'promociones',
                'Crear promoción'
            );
        }

        $pico = $this->fila(
            'SELECT HOUR(fecha_ticket) AS hora, COUNT(*) AS cantidad FROM ticket_atencion
             WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY hora ORDER BY cantidad DESC LIMIT 1',
            [':dias' => self::VENTANA_DIAS]
        );
        if ($pico) {
            $hora = (int) $pico['hora'];
            $porcentaje = (int) round($pico['cantidad'] / $total * 100);
            $hallazgos[] = new Hallazgo(
                'hora-pico', 'Agenda', Hallazgo::BAJA,
                'La hora con más clientes es de ' . $this->hora($hora) . ' a ' . $this->hora($hora + 1),
                "Concentra el $porcentaje% de las atenciones: ten a todos los barberos disponibles en esa franja y ofrece citas en las horas más tranquilas para repartir la demanda.",
                'fa-clock',
                'citas',
                'Ver agenda'
            );
        }

        $barberos = $this->filas(
            "SELECT CONCAT(b.nombre, ' ', b.apellido) AS barbero, COUNT(*) AS cantidad
             FROM ticket_atencion t INNER JOIN barberos b ON b.cedula = t.cedula_barbero
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY b.cedula, b.nombre, b.apellido ORDER BY cantidad DESC"
        );
        $atendidos = array_sum(array_column($barberos, 'cantidad'));
        if (count($barberos) >= 2 && $atendidos > 0) {
            $participacion = (int) round($barberos[0]['cantidad'] / $atendidos * 100);
            if ($participacion >= 45) {
                $hallazgos[] = new Hallazgo(
                    'carga-barbero', 'Equipo', Hallazgo::BAJA,
                    "{$barberos[0]['barbero']} atiende el $participacion% de los clientes",
                    'En los últimos 30 días concentra la mayoría de los tickets. Reparte las citas entre el equipo para no sobrecargarlo y reducir la espera.',
                    'fa-cut',
                    'citas',
                    'Ver agenda'
                );
            }
        }

        return $hallazgos;
    }

    private function hora(int $hora): string
    {
        $hora %= 24;

        return ($hora % 12 ?: 12) . ($hora < 12 ? ' a. m.' : ' p. m.');
    }
}
