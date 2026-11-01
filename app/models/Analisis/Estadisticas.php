<?php

namespace App\Models\Analisis;

use App\Core\BaseModel;
use PDO;

/**
 * Series de datos del negocio para los gráficos del módulo de análisis.
 * Cada método devuelve puntos con el formato de App\Helpers\Grafico.
 */
class Estadisticas extends BaseModel
{
    /** Horario del local (landing: 9:00 a. m. a 8:00 p. m.). */
    private const HORA_APERTURA = 9;
    private const HORA_CIERRE = 20;

    /** Totales del período y del período anterior de la misma duración (para comparar). */
    public function resumenComparado(int $dias): array
    {
        return ['actual' => $this->resumen($dias, 0), 'anterior' => $this->resumen($dias, $dias)];
    }

    /** Atenciones, clientes, ticket promedio e ingresos de $dias días que terminan hace $desfase días. */
    public function resumen(int $dias, int $desfase = 0): array
    {
        $stmt = $this->negocio()->prepare(
            'SELECT COUNT(*) AS atenciones, COUNT(DISTINCT id_cliente) AS clientes, COALESCE(AVG(total), 0) AS ticket_promedio
             FROM ticket_atencion
             WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL :desde DAY) AND fecha_ticket < DATE_SUB(NOW(), INTERVAL :hasta DAY)'
        );
        $stmt->execute([':desde' => $dias + $desfase, ':hasta' => $desfase]);
        $tickets = $stmt->fetch();

        $stmt = $this->negocio()->prepare(
            'SELECT COALESCE(SUM(monto), 0) FROM pagos
             WHERE status = 1 AND fecha_pago >= DATE_SUB(NOW(), INTERVAL :desde DAY) AND fecha_pago < DATE_SUB(NOW(), INTERVAL :hasta DAY)'
        );
        $stmt->execute([':desde' => $dias + $desfase, ':hasta' => $desfase]);

        return $tickets + ['ingresos' => (float) $stmt->fetchColumn()];
    }

    public function productosMasVendidos(int $dias, int $limite = 6): array
    {
        return $this->ranking(
            'SELECT p.nombre_producto AS etiqueta, SUM(d.cantidad) AS valor
             FROM detalle_atencion d
             INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
             INNER JOIN producto p ON p.id_producto = d.id_producto
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY p.id_producto, p.nombre_producto ORDER BY valor DESC LIMIT :limite',
            $dias,
            $limite
        );
    }

    public function ingresosPorMetodo(int $dias): array
    {
        return $this->ranking(
            'SELECT m.nombre_metodo AS etiqueta, SUM(p.monto) AS valor
             FROM pagos p INNER JOIN metodo_pago m ON m.id_metodo = p.id_metodo
             WHERE p.status = 1 AND p.fecha_pago >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY m.id_metodo, m.nombre_metodo ORDER BY valor DESC LIMIT :limite',
            $dias,
            10
        );
    }

    public function atencionesPorBarbero(int $dias): array
    {
        return $this->ranking(
            "SELECT CONCAT(b.nombre, ' ', b.apellido) AS etiqueta, COUNT(*) AS valor
             FROM ticket_atencion t INNER JOIN barberos b ON b.cedula = t.cedula_barbero
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY b.cedula, b.nombre, b.apellido ORDER BY valor DESC LIMIT :limite",
            $dias,
            10
        );
    }

    /** Atenciones por día de la semana, de lunes a domingo. */
    public function porDiaSemana(int $dias): array
    {
        $conteo = $this->contar('DAYOFWEEK(fecha_ticket)', $dias);
        $nombresDias = [2 => ['Lun', 'lunes'], 3 => ['Mar', 'martes'], 4 => ['Mié', 'miércoles'], 5 => ['Jue', 'jueves'], 6 => ['Vie', 'viernes'], 7 => ['Sáb', 'sábado'], 1 => ['Dom', 'domingo']];

        $puntos = [];
        foreach ($nombresDias as $numero => [$corto, $largo]) {
            $puntos[] = ['etiqueta' => $corto, 'detalle' => ucfirst($largo), 'valor' => (int) ($conteo[$numero] ?? 0)];
        }

        return $puntos;
    }

    /** Atenciones por hora de llegada dentro del horario (más las horas fuera de él que tengan datos). */
    public function porHora(int $dias): array
    {
        $conteo = $this->contar('HOUR(fecha_ticket)', $dias);
        $desde = min(self::HORA_APERTURA, ...array_keys($conteo ?: [self::HORA_APERTURA => 0]));
        $hasta = max(self::HORA_CIERRE - 1, ...array_keys($conteo ?: [self::HORA_APERTURA => 0]));

        $puntos = [];
        for ($hora = $desde; $hora <= $hasta; $hora++) {
            $doce = $hora % 12 ?: 12;
            $puntos[] = [
                'etiqueta' => (string) $doce,
                'detalle' => "$doce:00 " . ($hora < 12 ? 'a. m.' : 'p. m.'),
                'valor' => (int) ($conteo[$hora] ?? 0),
            ];
        }

        return $puntos;
    }

    public function serviciosMasVendidos(int $dias, int $limite = 6): array
    {
        return $this->ranking(
            'SELECT s.nombre_servicio AS etiqueta, SUM(d.cantidad) AS valor
             FROM detalle_atencion d
             INNER JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
             INNER JOIN servicio s ON s.id_servicio = d.id_servicio
             WHERE t.status = 1 AND t.fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY)
             GROUP BY s.id_servicio, s.nombre_servicio ORDER BY valor DESC LIMIT :limite',
            $dias,
            $limite
        );
    }

    /** Ejecuta una consulta de ranking (etiqueta, valor) con :dias y :limite. */
    private function ranking(string $sql, int $dias, int $limite): array
    {
        $stmt = $this->negocio()->prepare($sql);
        $stmt->bindValue(':dias', $dias, PDO::PARAM_INT);
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn(array $f): array => ['etiqueta' => $f['etiqueta'], 'valor' => (float) $f['valor']], $stmt->fetchAll());
    }

    /** Ingresos (pagos) por mes, incluidos los meses sin ingresos. */
    public function ingresosPorMes(int $meses = 6): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT DATE_FORMAT(fecha_pago, '%Y-%m') AS mes, SUM(monto) AS total FROM pagos
             WHERE status = 1 AND fecha_pago >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL :meses MONTH)
             GROUP BY mes"
        );
        $stmt->execute([':meses' => $meses - 1]);
        $totales = array_column($stmt->fetchAll(), 'total', 'mes');

        $nombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        $puntos = [];
        for ($i = $meses - 1; $i >= 0; $i--) {
            $mes = date('Y-m', strtotime("first day of -$i month"));
            $nombre = $nombres[(int) substr($mes, 5, 2) - 1];
            $puntos[] = ['etiqueta' => $nombre, 'detalle' => $nombre . ' ' . substr($mes, 0, 4), 'valor' => (float) ($totales[$mes] ?? 0)];
        }

        return $puntos;
    }

    /** @return array<int, int> valor de la expresión => cantidad de tickets */
    private function contar(string $expresion, int $dias): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT $expresion AS grupo, COUNT(*) AS cantidad FROM ticket_atencion
             WHERE status = 1 AND fecha_ticket >= DATE_SUB(NOW(), INTERVAL :dias DAY) GROUP BY grupo"
        );
        $stmt->execute([':dias' => $dias]);

        return array_map('intval', array_column($stmt->fetchAll(), 'cantidad', 'grupo'));
    }
}
