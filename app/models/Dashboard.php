<?php

namespace App\Models;

use App\Core\BaseModel;
use PDO;

/**
 * Indicadores del inicio: cifras del día, próximas citas y alertas de stock.
 */
class Dashboard extends BaseModel
{
    public function resumen(): array
    {
        $fila = $this->negocio()->query(
            "SELECT
                (SELECT COUNT(*) FROM cliente WHERE status = 1) AS clientes,
                (SELECT COUNT(*) FROM barberos WHERE status = 1) AS barberos,
                (SELECT COUNT(*) FROM citas WHERE status = 1 AND estado_cita = 'Pendiente' AND DATE(fecha_cita) = CURDATE()) AS citas_hoy,
                (SELECT COUNT(*) FROM ticket_atencion WHERE status = 1 AND DATE(fecha_ticket) = CURDATE()) AS tickets_hoy,
                (SELECT COALESCE(SUM(monto), 0) FROM pagos WHERE status = 1 AND DATE(fecha_pago) = CURDATE()) AS ingresos_hoy,
                (SELECT COUNT(*) FROM ticket_atencion WHERE status = 1 AND estado_pago = 'Pendiente') AS tickets_pendientes,
                (SELECT COUNT(*) FROM producto WHERE status = 1 AND stock <= " . Producto::STOCK_MINIMO . ") AS stock_bajo,
                (SELECT COUNT(*) FROM inscripcion_curso WHERE status = 1) AS alumnos,
                (SELECT id_cierre FROM cierre_caja WHERE status = 1 AND estado = 'Abierta' ORDER BY id_cierre DESC LIMIT 1) AS caja_abierta"
        )->fetch();

        return $fila ?: [];
    }

    /**
     * Resumen de un barbero vinculado: sus cortes de hoy y de esta semana (desde el lunes)
     * y la comisión estimada por los servicios de la semana.
     */
    public function resumenBarbero(string $cedula): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT b.nombre, b.porcentaje_comision,
                (SELECT COUNT(*) FROM ticket_atencion t WHERE t.cedula_barbero = b.cedula AND t.status = 1 AND DATE(t.fecha_ticket) = CURDATE()) AS cortes_hoy,
                (SELECT COUNT(*) FROM ticket_atencion t WHERE t.cedula_barbero = b.cedula AND t.status = 1
                    AND t.fecha_ticket >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)) AS cortes_semana,
                (SELECT COALESCE(SUM(d.cantidad * d.precio_unitario), 0) FROM detalle_atencion d
                    INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
                    WHERE t.cedula_barbero = b.cedula AND t.status = 1 AND d.id_servicio IS NOT NULL
                      AND t.fecha_ticket >= DATE_SUB(CURDATE(), INTERVAL WEEKDAY(CURDATE()) DAY)) AS servicios_semana,
                (SELECT COUNT(*) FROM citas c WHERE c.cedula_barbero = b.cedula AND c.status = 1 AND c.estado_cita = 'Pendiente'
                    AND DATE(c.fecha_cita) = CURDATE()) AS citas_hoy
             FROM barberos b WHERE b.cedula = :cedula"
        );
        $stmt->execute([':cedula' => $cedula]);
        $fila = $stmt->fetch();
        if (!$fila) {
            return [];
        }
        $fila['comision_semana'] = round((float) $fila['servicios_semana'] * (float) $fila['porcentaje_comision'] / 100, 2);

        return $fila;
    }

    public function proximasCitas(int $limite = 6, ?string $cedulaBarbero = null): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT ci.id_cita, ci.fecha_cita, CONCAT(c.nombre, ' ', c.apellido) AS cliente, CONCAT(b.nombre, ' ', b.apellido) AS barbero
             FROM citas ci
             LEFT JOIN cliente c ON c.id_cliente = ci.id_cliente
             LEFT JOIN barberos b ON b.cedula = ci.cedula_barbero
             WHERE ci.status = 1 AND ci.estado_cita = 'Pendiente' AND ci.fecha_cita >= NOW()
               AND (:barbero IS NULL OR ci.cedula_barbero = :barbero2)
             ORDER BY ci.fecha_cita
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':barbero', $cedulaBarbero);
        $stmt->bindValue(':barbero2', $cedulaBarbero);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function productosStockBajo(int $limite = 6): array
    {
        $stmt = $this->negocio()->prepare(
            'SELECT nombre_producto, stock FROM producto WHERE status = 1 AND stock <= :minimo ORDER BY stock, nombre_producto LIMIT :limite'
        );
        $stmt->bindValue(':minimo', Producto::STOCK_MINIMO, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /** Ingresos por pagos de los últimos días (incluye los días sin ventas). */
    public function ingresosUltimosDias(int $dias = 7): array
    {
        $stmt = $this->negocio()->prepare(
            'SELECT DATE(fecha_pago) AS dia, SUM(monto) AS total FROM pagos
             WHERE status = 1 AND fecha_pago >= DATE_SUB(CURDATE(), INTERVAL :dias DAY)
             GROUP BY DATE(fecha_pago)'
        );
        $stmt->bindValue(':dias', $dias - 1, PDO::PARAM_INT);
        $stmt->execute();
        $totales = array_column($stmt->fetchAll(), 'total', 'dia');

        $serie = [];
        for ($i = $dias - 1; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i day"));
            $serie[] = ['dia' => $dia, 'total' => (float) ($totales[$dia] ?? 0)];
        }

        return $serie;
    }
}
