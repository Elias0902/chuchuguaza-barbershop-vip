<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

/**
 * Pagos de los tickets. Cada operación recalcula el estado de pago del ticket.
 */
class Pago extends CrudModel
{
    protected string $tabla = 'pagos';
    protected string $clave = 'id_pago';
    protected string $modulo = 'Pagos';
    protected string $singular = 'pago';

    public function campos(): array
    {
        $pagadoTicket = '(SELECT COALESCE(SUM(px.monto), 0) FROM pagos px WHERE px.id_ticket = ticket_atencion.id_ticket AND px.status = 1)';
        $clienteTicket = "(SELECT CONCAT(c.nombre, ' ', c.apellido) FROM cliente c WHERE c.id_cliente = ticket_atencion.id_cliente)";

        return [
            'id_ticket' => [
                'etiqueta' => 'Ticket', 'tipo' => 'relacion', 'requerido' => true, 'ancho' => 'completo',
                'ayuda' => 'Solo aparecen los tickets con saldo pendiente.',
                'autollenar' => ['campo' => 'monto', 'dato' => 'saldo'],
                'relacion' => [
                    'tabla' => 'ticket_atencion', 'clave' => 'id_ticket',
                    'texto' => "CONCAT('Ticket #', id_ticket, ' · ', COALESCE($clienteTicket, 'Sin cliente'), ' · Saldo ', FORMAT(total - $pagadoTicket, 2))",
                    'activo' => "status = 1 AND estado_pago = 'Pendiente'",
                    'extra' => ['saldo' => "total - $pagadoTicket"],
                    'orden' => 'valor DESC',
                ],
            ],
            'id_metodo' => [
                'etiqueta' => 'Método de pago', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'metodo_pago', 'clave' => 'id_metodo', 'texto' => 'nombre_metodo'],
            ],
            'monto' => ['etiqueta' => 'Monto', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0.01, 'maxValor' => 99999999.99],
            'referencia' => ['etiqueta' => 'Referencia', 'tipo' => 'texto', 'max' => 50, 'ayuda' => 'Número de operación (pago móvil, transferencia...).'],
            'fecha_pago' => ['etiqueta' => 'Fecha y hora', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT p.*, m.nombre_metodo, t.total AS total_ticket, t.estado_pago,
                    CONCAT(c.nombre, ' ', c.apellido) AS cliente
                FROM pagos p
                LEFT JOIN metodo_pago m ON m.id_metodo = p.id_metodo
                LEFT JOIN ticket_atencion t ON t.id_ticket = p.id_ticket
                LEFT JOIN cliente c ON c.id_cliente = t.id_cliente
                ORDER BY p.status DESC, p.fecha_pago DESC";
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $saldo = $this->saldoTicket((int) $datos['id_ticket'], $actual['id_pago'] ?? null);
        if ((float) $datos['monto'] > $saldo + 0.001) {
            throw new ValidacionException(
                'El monto supera el saldo pendiente del ticket (' . MONEDA_SIMBOLO . ' ' . number_format($saldo, 2, ',', '.') . ').',
                'monto'
            );
        }
    }

    protected function despuesDeGuardar(PDO $db, int|string $id, array $datos, ?array $actual): void
    {
        Ticket::recalcularEstadoPago($db, (int) $datos['id_ticket']);
        if ($actual !== null && (int) $actual['id_ticket'] !== (int) $datos['id_ticket']) {
            Ticket::recalcularEstadoPago($db, (int) $actual['id_ticket']);
        }
    }

    protected function despuesDeEliminar(PDO $db, array $actual): void
    {
        Ticket::recalcularEstadoPago($db, (int) $actual['id_ticket']);
    }

    protected function antesDeRestaurar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare('SELECT status FROM ticket_atencion WHERE id_ticket = :id');
        $stmt->execute([':id' => $actual['id_ticket']]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new ValidacionException('El ticket de este pago está anulado. Restaura primero el ticket.');
        }
        if ((float) $actual['monto'] > $this->saldoTicket((int) $actual['id_ticket'], (int) $actual['id_pago']) + 0.001) {
            throw new ValidacionException('Restaurar este pago superaría el total del ticket.');
        }
    }

    protected function despuesDeRestaurar(PDO $db, array $actual): void
    {
        Ticket::recalcularEstadoPago($db, (int) $actual['id_ticket']);
    }

    protected function describir(array $fila): string
    {
        return 'pago #' . $fila['id_pago'] . ' de ' . MONEDA_SIMBOLO . ' ' . number_format((float) $fila['monto'], 2, ',', '.')
            . ' al ticket #' . $fila['id_ticket'];
    }

    /** Total del ticket menos sus pagos activos, sin contar el pago que se está editando. */
    private function saldoTicket(int $idTicket, ?int $excluirPago): float
    {
        $stmt = $this->db()->prepare(
            'SELECT t.total - COALESCE((SELECT SUM(p.monto) FROM pagos p
                                         WHERE p.id_ticket = t.id_ticket AND p.status = 1 AND p.id_pago <> :excluir), 0)
             FROM ticket_atencion t WHERE t.id_ticket = :id'
        );
        $stmt->execute([':excluir' => $excluirPago ?? 0, ':id' => $idTicket]);

        return (float) $stmt->fetchColumn();
    }
}
