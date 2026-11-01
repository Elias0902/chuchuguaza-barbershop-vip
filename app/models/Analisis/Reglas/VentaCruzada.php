<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;

/** Cómo vender más a cada cliente: productos adicionales, combos y producto estrella. */
class VentaCruzada extends ReglaAnalisis
{
    private const MINIMO_TICKETS = 15;
    /** Por debajo de este porcentaje de tickets con producto se sugiere ofrecerlos más. */
    private const META_ADICIONAL = 40;

    public function evaluar(): array
    {
        $hallazgos = [];

        $tickets = $this->fila(
            'SELECT COUNT(*) AS total,
                    SUM(EXISTS (SELECT 1 FROM detalle_atencion d WHERE d.id_ticket = t.id_ticket AND d.id_producto IS NOT NULL)) AS con_producto
             FROM ticket_atencion t WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
        );
        $total = (int) ($tickets['total'] ?? 0);
        if ($total >= self::MINIMO_TICKETS) {
            $porcentaje = (int) round((int) $tickets['con_producto'] / $total * 100);
            if ($porcentaje < self::META_ADICIONAL) {
                $promedioLinea = (float) ($this->fila(
                    'SELECT AVG(d.cantidad * d.precio_unitario) AS promedio FROM detalle_atencion d
                     INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
                     WHERE d.id_producto IS NOT NULL AND t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL 30 DAY)'
                )['promedio'] ?? 0);
                $faltan = (int) ceil($total / 2) - (int) $tickets['con_producto'];
                $estimado = $faltan > 0 && $promedioLinea > 0
                    ? ' Si la mitad de los clientes llevara algo, venderías unos ' . $this->moneda($faltan * $promedioLinea) . ' más al mes.'
                    : '';
                $hallazgos[] = new Hallazgo(
                    'venta-adicional', 'Ventas', Hallazgo::BAJA,
                    "Solo el $porcentaje% de los clientes compra una bebida o producto",
                    'Ofrécelos mientras esperan o al terminar el corte.' . $estimado,
                    'fa-shopping-basket',
                    'productos'
                );
            }
        }

        $combo = $this->fila(
            'SELECT s1.nombre_servicio AS primero, s2.nombre_servicio AS segundo, COUNT(*) AS veces
             FROM detalle_atencion d1
             INNER JOIN detalle_atencion d2 ON d2.id_ticket = d1.id_ticket AND d1.id_servicio < d2.id_servicio
             INNER JOIN servicio s1 ON s1.id_servicio = d1.id_servicio
             INNER JOIN servicio s2 ON s2.id_servicio = d2.id_servicio
             INNER JOIN ticket_atencion t ON t.id_ticket = d1.id_ticket
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY d1.id_servicio, d2.id_servicio, s1.nombre_servicio, s2.nombre_servicio
             ORDER BY veces DESC LIMIT 1',
            [':dias' => self::VENTANA_DIAS]
        );
        if ((int) ($combo['veces'] ?? 0) >= 5) {
            $hallazgos[] = new Hallazgo(
                'combo-servicios', 'Ventas', Hallazgo::BAJA,
                "Crea el combo «{$combo['primero']} + {$combo['segundo']}»",
                "Se pidieron juntos {$combo['veces']} veces en los últimos " . self::VENTANA_DIAS
                . ' días. Un precio especial por los dos anima a más clientes a tomarlos.',
                'fa-layer-group',
                'promociones',
                'Crear promoción'
            );
        }

        $estrella = $this->fila(
            'SELECT p.nombre_producto, p.stock, SUM(d.cantidad) AS unidades
             FROM detalle_atencion d
             INNER JOIN producto p ON p.id_producto = d.id_producto
             INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY p.id_producto, p.nombre_producto, p.stock ORDER BY unidades DESC LIMIT 1'
        );
        if ((int) ($estrella['unidades'] ?? 0) >= 5) {
            $hallazgos[] = new Hallazgo(
                'producto-estrella', 'Ventas', Hallazgo::POSITIVA,
                "«{$estrella['nombre_producto']}» es lo que más se vende",
                "Vendiste {$estrella['unidades']} unidades en 30 días y quedan {$estrella['stock']}. Tenlo siempre a la vista y que no falte.",
                'fa-star',
                'productos'
            );
        }

        return $hallazgos;
    }
}
