<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Servicios que no se venden y falta de promociones vigentes. */
class ServiciosYPromociones extends ReglaAnalisis
{
    public function evaluar(): array
    {
        $hallazgos = [];

        $hayVentas = (int) ($this->fila(
            'SELECT COUNT(*) AS n FROM ticket_atencion WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)',
            [':dias' => self::VENTANA_DIAS]
        )['n'] ?? 0) > 0;

        if ($hayVentas) {
            $sinVentas = array_column($this->filas(
                'SELECT s.nombre_servicio FROM servicio s
                 WHERE s.status = 1 AND NOT EXISTS (
                     SELECT 1 FROM detalle_atencion d INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
                     WHERE d.id_servicio = s.id_servicio AND t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY))
                 ORDER BY s.nombre_servicio',
                [':dias' => self::VENTANA_DIAS]
            ), 'nombre_servicio');
            if ($sinVentas) {
                $hallazgos[] = new Hallazgo(
                    'servicios-sin-ventas', 'Ventas', Hallazgo::BAJA,
                    count($sinVentas) . ' ' . (count($sinVentas) === 1 ? 'servicio sin ventas' : 'servicios sin ventas') . ' en ' . self::VENTANA_DIAS . ' días',
                    $this->lista($sinVentas, 4) . '. Ofrécelos como complemento al terminar un corte, inclúyelos en un combo o desactívalos si ya no se prestan.',
                    'fa-concierge-bell',
                    'servicios'
                );
            }
        }

        $vigentes = (int) ($this->fila(
            'SELECT COUNT(*) AS n FROM promociones WHERE status = 1 AND CURDATE() BETWEEN fecha_inicio AND fecha_cierre'
        )['n'] ?? 0);
        if ($vigentes === 0) {
            $hallazgos[] = new Hallazgo(
                'sin-promociones', 'Ventas', Hallazgo::BAJA,
                'No hay promociones vigentes',
                'Una promoción temporal ayuda a llenar los días flojos y a atraer clientes nuevos. Publícala en tus redes y por WhatsApp.',
                'fa-percent',
                'promociones',
                'Crear promoción'
            );
        }

        return $hallazgos;
    }
}
