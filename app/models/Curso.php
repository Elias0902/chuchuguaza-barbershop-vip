<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

class Curso extends CrudModel
{
    protected string $tabla = 'cursos';
    protected string $clave = 'id_curso';
    protected string $modulo = 'Cursos';
    protected string $singular = 'curso';
    protected ?string $campoDescriptivo = 'nombre_curso';

    public function campos(): array
    {
        return [
            'nombre_curso' => [
                'etiqueta' => 'Nombre del curso', 'tipo' => 'texto', 'requerido' => true, 'max' => 100, 'unico' => true,
                'ancho' => 'completo', 'placeholder' => 'Barbería básica, degradados avanzados...',
            ],
            'costo' => ['etiqueta' => 'Costo', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99],
            'dias_imparte' => ['etiqueta' => 'Días de clase', 'tipo' => 'texto', 'max' => 100, 'placeholder' => 'Lunes y miércoles'],
            'horario' => ['etiqueta' => 'Horario', 'tipo' => 'texto', 'max' => 50, 'placeholder' => '6:00 pm a 8:00 pm'],
            'descripcion' => ['etiqueta' => 'Descripción', 'tipo' => 'textoLargo', 'max' => 1000, 'ancho' => 'completo'],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT cu.*, (SELECT COUNT(*) FROM inscripcion_curso i WHERE i.id_curso = cu.id_curso AND i.status = 1) AS inscritos
                FROM cursos cu ORDER BY cu.status DESC, cu.nombre_curso';
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM inscripcion_curso WHERE id_curso = :id AND status = 1');
        $stmt->execute([':id' => $actual['id_curso']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('El curso tiene alumnos inscritos activos. Elimina sus inscripciones primero.');
        }
    }
}
