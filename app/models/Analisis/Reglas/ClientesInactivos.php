<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Clientes que dejaron de venir y los más fieles. */
class ClientesInactivos extends ReglaAnalisis
{
    private const DIAS_SIN_VENIR = 45;
    private const VISITAS_FIEL = 4;

    public function evaluar(): array
    {
        $hallazgos = [];

        $inactivos = $this->filas(
            "SELECT CONCAT(c.nombre, ' ', c.apellido) AS cliente, MAX(t.fecha_ticket) AS ultima
             FROM cliente c INNER JOIN ticket_atencion t ON t.id_cliente = c.id_cliente AND t.status = 1
             WHERE c.status = 1
             GROUP BY c.id_cliente, c.nombre, c.apellido
             HAVING ultima < DATE_SUB(NOW(), INTERVAL :dias DAY)
             ORDER BY ultima DESC",
            [':dias' => self::DIAS_SIN_VENIR]
        );
        if ($inactivos) {
            $hallazgos[] = new Hallazgo(
                'clientes-inactivos', 'Clientes', Hallazgo::MEDIA,
                count($inactivos) . ' ' . (count($inactivos) === 1 ? 'cliente no vuelve' : 'clientes no vuelven') . ' hace más de ' . self::DIAS_SIN_VENIR . ' días',
                'Entre ellos ' . $this->lista(array_column($inactivos, 'cliente')) . '. Escríbeles por WhatsApp con una promoción para invitarlos a volver.',
                'fa-user-clock',
                'clientes'
            );
        }

        $fieles = $this->filas(
            "SELECT CONCAT(c.nombre, ' ', c.apellido) AS cliente, COUNT(*) AS visitas
             FROM ticket_atencion t INNER JOIN cliente c ON c.id_cliente = t.id_cliente
             WHERE t.status = 1 AND c.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL 90 DAY)
             GROUP BY c.id_cliente, c.nombre, c.apellido
             HAVING visitas >= :minimo
             ORDER BY visitas DESC LIMIT 5",
            [':minimo' => self::VISITAS_FIEL]
        );
        if ($fieles) {
            $nombres = array_map(static fn(array $f): string => "{$f['cliente']} ({$f['visitas']})", $fieles);
            $hallazgos[] = new Hallazgo(
                'clientes-fieles', 'Clientes', Hallazgo::POSITIVA,
                'Tienes clientes muy fieles',
                'En los últimos 90 días vinieron varias veces: ' . $this->lista($nombres, 5) . '. Premiarlos (por ejemplo, el décimo corte gratis) ayuda a que sigan viniendo y te recomienden.',
                'fa-heart',
                'clientes'
            );
        }

        return $hallazgos;
    }
}
