<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;

class Inscripcion extends CrudModel
{
    protected string $tabla = 'inscripcion_curso';
    protected string $clave = 'id_inscripcion';
    protected string $modulo = 'Inscripciones';
    protected string $singular = 'inscripción';
    protected bool $femenino = true;

    public function campos(): array
    {
        return [
            'id_curso' => [
                'etiqueta' => 'Curso', 'tipo' => 'relacion', 'requerido' => true,
                'autollenar' => ['campo' => 'monto_pagado', 'dato' => 'costo'],
                'relacion' => [
                    'tabla' => 'cursos', 'clave' => 'id_curso', 'texto' => "CONCAT(nombre_curso, ' · ', COALESCE(dias_imparte, ''))",
                    'extra' => ['costo' => 'costo'],
                ],
            ],
            'id_cliente' => [
                'etiqueta' => 'Alumno (cliente)', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'cliente', 'clave' => 'id_cliente', 'texto' => "CONCAT(nombre, ' ', apellido, ' · ', telefono)"],
            ],
            'fecha_inscripcion' => ['etiqueta' => 'Fecha de inscripción', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
            'monto_pagado' => [
                'etiqueta' => 'Monto pagado', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99,
                'ayuda' => 'Se propone el costo del curso; cámbialo si el alumno abonó una parte.',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT i.*, cu.nombre_curso, cu.costo, cu.costo - i.monto_pagado AS saldo,
                    CONCAT(c.nombre, ' ', c.apellido) AS alumno, c.telefono
                FROM inscripcion_curso i
                LEFT JOIN cursos cu ON cu.id_curso = i.id_curso
                LEFT JOIN cliente c ON c.id_cliente = i.id_cliente
                ORDER BY i.status DESC, i.fecha_inscripcion DESC";
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $sql = 'SELECT COUNT(*) FROM inscripcion_curso WHERE id_curso = :curso AND id_cliente = :cliente AND status = 1';
        $parametros = [':curso' => $datos['id_curso'], ':cliente' => $datos['id_cliente']];
        if ($actual !== null) {
            $sql .= ' AND id_inscripcion <> :id';
            $parametros[':id'] = $actual['id_inscripcion'];
        }
        $stmt = $this->db()->prepare($sql);
        $stmt->execute($parametros);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('Ese cliente ya está inscrito en el curso.', 'id_cliente');
        }

        $stmt = $this->db()->prepare('SELECT costo FROM cursos WHERE id_curso = :id');
        $stmt->execute([':id' => $datos['id_curso']]);
        $costo = (float) $stmt->fetchColumn();
        if ((float) $datos['monto_pagado'] > $costo) {
            throw new ValidacionException(
                'El monto pagado no puede superar el costo del curso (' . MONEDA_SIMBOLO . ' ' . number_format($costo, 2, ',', '.') . ').',
                'monto_pagado'
            );
        }
    }

    protected function describir(array $fila): string
    {
        return 'inscripción #' . $fila['id_inscripcion'] . ' del cliente #' . $fila['id_cliente'] . ' al curso #' . $fila['id_curso'];
    }
}
