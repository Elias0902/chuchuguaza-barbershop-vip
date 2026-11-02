<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;

class Cita extends CrudModel
{
    public const ESTADOS = ['Pendiente', 'Completada', 'Cancelada'];

    /** Minutos mínimos entre dos citas del mismo barbero. */
    private const SEPARACION_MINUTOS = 30;

    use SoloSusRegistros;

    protected string $tabla = 'citas';
    protected string $clave = 'id_cita';
    protected string $modulo = 'Citas';
    protected string $singular = 'cita';
    protected bool $femenino = true;

    public function campos(): array
    {
        return [
            'id_cliente' => [
                'etiqueta' => 'Cliente', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'cliente', 'clave' => 'id_cliente', 'texto' => "CONCAT(nombre, ' ', apellido, ' · ', telefono)"],
            ],
            'cedula_barbero' => [
                'etiqueta' => 'Barbero', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'barberos', 'clave' => 'cedula', 'claveTexto' => true, 'texto' => "CONCAT(nombre, ' ', apellido)"],
            ],
            'fecha_cita' => ['etiqueta' => 'Fecha y hora', 'tipo' => 'fechahora', 'requerido' => true],
            'estado_cita' => ['etiqueta' => 'Estado', 'tipo' => 'opcion', 'requerido' => true, 'opciones' => self::ESTADOS, 'defecto' => 'Pendiente'],
            'observacion' => [
                'etiqueta' => 'Observación', 'tipo' => 'textoLargo', 'max' => 255, 'ancho' => 'completo',
                'placeholder' => 'Servicio que desea, preferencias...',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT ci.*, CONCAT(c.nombre, ' ', c.apellido) AS cliente, c.telefono AS telefono_cliente,
                    CONCAT(b.nombre, ' ', b.apellido) AS barbero
                FROM citas ci
                LEFT JOIN cliente c ON c.id_cliente = ci.id_cliente
                LEFT JOIN barberos b ON b.cedula = ci.cedula_barbero
                WHERE {$this->condicionBarbero('ci')}
                ORDER BY ci.status DESC, (ci.estado_cita = 'Pendiente') DESC, ci.fecha_cita DESC";
    }

    public function obtener(int|string $id): ?array
    {
        return $this->verificarDueno(parent::obtener($id));
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $this->asignarBarbero($datos);
        $cambioFecha = $actual === null || $actual['fecha_cita'] !== $datos['fecha_cita'];
        if ($cambioFecha && $datos['estado_cita'] === 'Pendiente' && strtotime($datos['fecha_cita']) < strtotime('today')) {
            throw new ValidacionException('No puedes agendar una cita pendiente en una fecha pasada.', 'fecha_cita');
        }

        if ($datos['estado_cita'] !== 'Pendiente') {
            return;
        }

        $sql = "SELECT fecha_cita FROM citas
                WHERE cedula_barbero = :barbero AND status = 1 AND estado_cita = 'Pendiente'
                  AND ABS(TIMESTAMPDIFF(MINUTE, fecha_cita, :fecha)) < :separacion";
        $parametros = [':barbero' => $datos['cedula_barbero'], ':fecha' => $datos['fecha_cita'], ':separacion' => self::SEPARACION_MINUTOS];
        if ($actual !== null) {
            $sql .= ' AND id_cita <> :id';
            $parametros[':id'] = $actual['id_cita'];
        }
        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($parametros);
        $choque = $stmt->fetchColumn();
        if ($choque !== false) {
            throw new ValidacionException(
                'El barbero ya tiene una cita pendiente a las ' . date('h:i a', strtotime($choque))
                . '. Deja al menos ' . self::SEPARACION_MINUTOS . ' minutos entre citas.',
                'fecha_cita'
            );
        }
    }

    /** Marca una cita pendiente como Completada o Cancelada. */
    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, ['Completada', 'Cancelada'], true)) {
            throw new ValidacionException('El estado solicitado no es válido.');
        }
        $actual = $this->obtenerOFallar($id);
        if ((int) $actual['status'] !== 1 || $actual['estado_cita'] !== 'Pendiente') {
            throw new ValidacionException('Solo se pueden actualizar citas pendientes y activas.');
        }

        $this->db()->prepare('UPDATE citas SET estado_cita = :estado WHERE id_cita = :id')->execute([':estado' => $estado, ':id' => $id]);
        $this->bitacora('Modificar', 'Marcó como ' . mb_strtolower($estado, 'UTF-8') . ' la cita #' . $id . '.');
    }
}
