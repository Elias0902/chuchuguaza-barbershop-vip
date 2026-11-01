<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;

/**
 * Nómina por barbero y período.
 * Cortes y comisiones salen de los servicios de sus tickets pagados en el período;
 * total a pagar = comisiones + sueldo base del barbero.
 */
class Nomina extends CrudModel
{
    protected string $tabla = 'nomina';
    protected string $clave = 'id_nomina';
    protected string $modulo = 'Nómina';
    protected string $singular = 'nómina';
    protected bool $femenino = true;

    public function campos(): array
    {
        return [
            'cedula_barbero' => [
                'etiqueta' => 'Barbero', 'tipo' => 'relacion', 'requerido' => true, 'ancho' => 'completo',
                'relacion' => ['tabla' => 'barberos', 'clave' => 'cedula', 'claveTexto' => true, 'texto' => "CONCAT(nombre, ' ', apellido, ' · ', porcentaje_comision, '%')"],
            ],
            'fecha_inicio' => ['etiqueta' => 'Desde', 'tipo' => 'fecha', 'requerido' => true],
            'fecha_cierre' => ['etiqueta' => 'Hasta', 'tipo' => 'fecha', 'requerido' => true, 'defecto' => 'hoy'],
            'estado_pago' => [
                'etiqueta' => 'Estado', 'tipo' => 'opcion', 'requerido' => true, 'opciones' => ['Pendiente', 'Pagado'], 'defecto' => 'Pendiente',
                'ayuda' => 'Cortes, comisiones y total se calculan al guardar con los tickets pagados del período.',
            ],
            'total_cortes' => ['etiqueta' => 'Cortes', 'tipo' => 'entero', 'formulario' => false],
            'monto_comisiones' => ['etiqueta' => 'Comisiones', 'tipo' => 'decimal', 'formulario' => false],
            'sueldo_base' => ['etiqueta' => 'Sueldo base', 'tipo' => 'decimal', 'formulario' => false],
            'total_pagar' => ['etiqueta' => 'Total a pagar', 'tipo' => 'decimal', 'formulario' => false],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT n.*, CONCAT(b.nombre, ' ', b.apellido) AS barbero
                FROM nomina n
                LEFT JOIN barberos b ON b.cedula = n.cedula_barbero
                ORDER BY n.status DESC, n.fecha_cierre DESC";
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        if ($datos['fecha_cierre'] < $datos['fecha_inicio']) {
            throw new ValidacionException('La fecha final no puede ser anterior a la inicial.', 'fecha_cierre');
        }

        $sql = 'SELECT COUNT(*) FROM nomina
                WHERE cedula_barbero = :barbero AND status = 1 AND fecha_inicio <= :fin AND fecha_cierre >= :inicio';
        $parametros = [':barbero' => $datos['cedula_barbero'], ':fin' => $datos['fecha_cierre'], ':inicio' => $datos['fecha_inicio']];
        if ($actual !== null) {
            $sql .= ' AND id_nomina <> :id';
            $parametros[':id'] = $actual['id_nomina'];
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($parametros);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('Ese barbero ya tiene una nómina que se cruza con ese período.', 'fecha_inicio');
        }

        $datos += $this->calcular($datos['cedula_barbero'], $datos['fecha_inicio'], $datos['fecha_cierre']);
    }

    protected function antesDeModificar(array $actual): void
    {
        if ($actual['estado_pago'] === 'Pagado') {
            throw new ValidacionException('La nómina ya fue pagada y no se puede modificar.');
        }
    }

    /** Cortes, comisiones, sueldo y total de un barbero en un período. */
    public function calcular(string $cedula, string $inicio, string $fin): array
    {
        $stmt = $this->db()->prepare(
            "SELECT COALESCE(SUM(d.cantidad), 0) AS cortes, COALESCE(SUM(d.cantidad * d.precio_unitario), 0) AS vendido
             FROM detalle_atencion d
             JOIN ticket_atencion t ON t.id_ticket = d.id_ticket
             WHERE t.cedula_barbero = :cedula AND t.status = 1 AND t.estado_pago = 'Pagado'
               AND d.id_servicio IS NOT NULL AND DATE(t.fecha_ticket) BETWEEN :inicio AND :fin"
        );
        $stmt->execute([':cedula' => $cedula, ':inicio' => $inicio, ':fin' => $fin]);
        $servicios = $stmt->fetch();

        $stmt = $this->db()->prepare('SELECT porcentaje_comision, sueldo_base FROM barberos WHERE cedula = :cedula');
        $stmt->execute([':cedula' => $cedula]);
        $barbero = $stmt->fetch() ?: ['porcentaje_comision' => 0, 'sueldo_base' => 0];

        $comisiones = round((float) $servicios['vendido'] * (float) $barbero['porcentaje_comision'] / 100, 2);
        $sueldo = (float) $barbero['sueldo_base'];

        return [
            'total_cortes' => (int) $servicios['cortes'],
            'monto_comisiones' => number_format($comisiones, 2, '.', ''),
            'sueldo_base' => number_format($sueldo, 2, '.', ''),
            'total_pagar' => number_format($comisiones + $sueldo, 2, '.', ''),
        ];
    }

    public function marcarPagada(int $id): void
    {
        $actual = $this->obtenerOFallar($id);
        if ((int) $actual['status'] !== 1 || $actual['estado_pago'] !== 'Pendiente') {
            throw new ValidacionException('Solo se puede pagar una nómina pendiente y activa.');
        }
        $this->db()->prepare("UPDATE nomina SET estado_pago = 'Pagado' WHERE id_nomina = :id")->execute([':id' => $id]);
        $this->bitacora('Pagar nómina', 'Marcó como pagada la nómina #' . $id . ' de ' . $actual['cedula_barbero'] . '.');
    }

    protected function describir(array $fila): string
    {
        return 'nómina #' . $fila['id_nomina'] . ' de ' . $fila['cedula_barbero'] . ' (' . $fila['fecha_inicio'] . ' a ' . $fila['fecha_cierre'] . ')';
    }
}
