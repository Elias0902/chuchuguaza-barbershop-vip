<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

/**
 * Apertura y cierre de caja.
 * Monto del sistema = monto inicial + pagos + inscripciones del período − gastos − retiros de la caja.
 */
class CierreCaja extends CrudModel
{
    protected string $tabla = 'cierre_caja';
    protected string $clave = 'id_cierre';
    protected string $modulo = 'Cierres de caja';
    protected string $singular = 'caja';
    protected bool $femenino = true;

    public function campos(): array
    {
        return [
            'fecha_inicio' => ['etiqueta' => 'Fecha y hora de apertura', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
            'monto_inicial' => [
                'etiqueta' => 'Monto inicial en caja', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99,
                'ayuda' => 'Dinero con el que se abre la caja.',
            ],
            'fecha_cierre' => ['etiqueta' => 'Fecha de cierre', 'tipo' => 'fechahora', 'formulario' => false],
            'monto_final_sistema' => ['etiqueta' => 'Monto según sistema', 'tipo' => 'decimal', 'formulario' => false],
            'monto_real_caja' => ['etiqueta' => 'Monto contado', 'tipo' => 'decimal', 'formulario' => false],
            'diferencia' => ['etiqueta' => 'Diferencia', 'tipo' => 'decimal', 'formulario' => false],
            'estado' => ['etiqueta' => 'Estado', 'tipo' => 'opcion', 'opciones' => ['Abierta', 'Cerrada'], 'formulario' => false],
        ];
    }

    protected function sqlConsulta(): string
    {
        $ingresos = $this->sqlIngresos('c');
        $egresos = $this->sqlEgresos('c');

        return "SELECT c.*, $ingresos AS ingresos, $egresos AS egresos,
                    IF(c.estado = 'Cerrada', c.monto_final_sistema, c.monto_inicial + $ingresos - $egresos) AS monto_sistema,
                    IF(c.estado = 'Cerrada', c.monto_real_caja, NULL) AS contado,
                    IF(c.estado = 'Cerrada', c.diferencia, NULL) AS descuadre
                FROM cierre_caja c
                ORDER BY c.status DESC, (c.estado = 'Abierta') DESC, c.fecha_inicio DESC";
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        if (strtotime($datos['fecha_inicio']) > time() + 300) {
            throw new ValidacionException('La fecha de apertura no puede estar en el futuro.', 'fecha_inicio');
        }
        if ($actual === null) {
            if ($this->idCajaAbierta() !== null) {
                throw new ValidacionException('Ya hay una caja abierta. Ciérrala antes de abrir otra.');
            }
            $datos['estado'] = 'Abierta';
        }
    }

    protected function antesDeModificar(array $actual): void
    {
        if ($actual['estado'] === 'Cerrada') {
            throw new ValidacionException('La caja ya está cerrada y no se puede modificar.');
        }
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare(
            'SELECT (SELECT COUNT(*) FROM gastos_generales WHERE id_cierre = :id_gasto AND status = 1)
                  + (SELECT COUNT(*) FROM retiro_dinero WHERE id_cierre = :id_retiro AND status = 1)'
        );
        $stmt->execute([':id_gasto' => $actual['id_cierre'], ':id_retiro' => $actual['id_cierre']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('La caja tiene gastos o retiros activos asociados. Elimínalos o muévelos primero.');
        }
    }

    protected function antesDeRestaurar(PDO $db, array $actual): void
    {
        if ($actual['estado'] === 'Abierta' && $this->idCajaAbierta() !== null) {
            throw new ValidacionException('Ya hay otra caja abierta. Ciérrala antes de restaurar esta.');
        }
    }

    /** Cierra la caja: calcula el monto del sistema y la diferencia con lo contado. */
    /**
     * $fechaCierre permite registrar un cierre olvidado con su hora real (entre la apertura y ahora);
     * si no se indica, la caja se cierra en este momento.
     */
    public function cerrar(int $id, mixed $montoReal, ?string $fechaCierre = null): void
    {
        $montoReal = $this->limpiarCampo('monto_real_caja', [
            'etiqueta' => 'Monto contado', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99,
        ], $montoReal, null);

        $actual = $this->obtenerOFallar($id);
        if ((int) $actual['status'] !== 1 || $actual['estado'] !== 'Abierta') {
            throw new ValidacionException('Solo se puede cerrar una caja abierta y activa.');
        }

        $cierre = date('Y-m-d H:i:s');
        if ($fechaCierre !== null) {
            $cierre = $this->limpiarCampo('fecha_cierre', ['etiqueta' => 'Fecha de cierre', 'tipo' => 'fechahora', 'requerido' => true], $fechaCierre, null);
            if ($cierre < $actual['fecha_inicio'] || strtotime($cierre) > time()) {
                throw new ValidacionException('La fecha de cierre debe estar entre la apertura de la caja y este momento.', 'fecha_cierre');
            }
        }

        $this->transaccion(function (PDO $db) use ($id, $montoReal, $cierre): void {
            // $cierre ya pasó por la validación de fecha y hora: es seguro incrustarla en el SQL.
            $ingresos = $this->sqlIngresos('c', "'$cierre'");
            $egresos = $this->sqlEgresos('c');
            $stmt = $db->prepare("SELECT c.monto_inicial + $ingresos - $egresos FROM cierre_caja c WHERE c.id_cierre = :id");
            $stmt->execute([':id' => $id]);
            $sistema = round((float) $stmt->fetchColumn(), 2);

            $db->prepare(
                "UPDATE cierre_caja
                 SET fecha_cierre = :cierre, monto_final_sistema = :sistema, monto_real_caja = :real,
                     diferencia = :diferencia, estado = 'Cerrada'
                 WHERE id_cierre = :id"
            )->execute([
                ':cierre' => $cierre,
                ':sistema' => $sistema,
                ':real' => $montoReal,
                ':diferencia' => round((float) $montoReal - $sistema, 2),
                ':id' => $id,
            ]);
        });

        $this->bitacora('Cerrar caja', 'Cerró la caja #' . $id . ' con ' . MONEDA_SIMBOLO . ' ' . $montoReal . ' contados.');
    }

    public function idCajaAbierta(): ?int
    {
        $id = $this->db()->query("SELECT id_cierre FROM cierre_caja WHERE estado = 'Abierta' AND status = 1 ORDER BY id_cierre DESC LIMIT 1")
            ->fetchColumn();

        return $id === false ? null : (int) $id;
    }

    /** Pagos e inscripciones cobrados entre la apertura y el cierre (o el momento actual). */
    private function sqlIngresos(string $alias, string $hasta = ''): string
    {
        $fin = $hasta !== '' ? $hasta : "COALESCE($alias.fecha_cierre, NOW())";

        return "(COALESCE((SELECT SUM(p.monto) FROM pagos p WHERE p.status = 1
                    AND p.fecha_pago BETWEEN $alias.fecha_inicio AND $fin), 0)
               + COALESCE((SELECT SUM(i.monto_pagado) FROM inscripcion_curso i WHERE i.status = 1
                    AND i.fecha_inscripcion BETWEEN $alias.fecha_inicio AND $fin), 0))";
    }

    /** Gastos y retiros activos asociados a la caja. */
    private function sqlEgresos(string $alias): string
    {
        return "(COALESCE((SELECT SUM(g.monto) FROM gastos_generales g WHERE g.status = 1 AND g.id_cierre = $alias.id_cierre), 0)
               + COALESCE((SELECT SUM(r.monto_total) FROM retiro_dinero r WHERE r.status = 1 AND r.id_cierre = $alias.id_cierre), 0))";
    }
}
