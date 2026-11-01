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

    public function proximasCitas(int $limite = 6): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT ci.id_cita, ci.fecha_cita, CONCAT(c.nombre, ' ', c.apellido) AS cliente, CONCAT(b.nombre, ' ', b.apellido) AS barbero
             FROM citas ci
             LEFT JOIN cliente c ON c.id_cliente = ci.id_cliente
             LEFT JOIN barberos b ON b.cedula = ci.cedula_barbero
             WHERE ci.status = 1 AND ci.estado_cita = 'Pendiente' AND ci.fecha_cita >= NOW()
             ORDER BY ci.fecha_cita
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
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
