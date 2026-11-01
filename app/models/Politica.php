<?php

namespace App\Models;

use App\Core\CrudModel;

/**
 * Políticas de servicio de la barbería. Las activas se publican en la landing.
 */
class Politica extends CrudModel
{
    public const CATEGORIAS = ['Citas', 'Atención', 'Pagos', 'Garantía', 'Bebidas', 'Productos', 'Cursos', 'General'];

    protected string $tabla = 'politicas';
    protected string $clave = 'id_politica';
    protected string $modulo = 'Políticas de servicio';
    protected string $singular = 'política';
    protected bool $femenino = true;
    protected ?string $campoDescriptivo = 'titulo';

    public function campos(): array
    {
        return [
            'titulo' => [
                'etiqueta' => 'Título', 'tipo' => 'texto', 'requerido' => true, 'max' => 100, 'unico' => true,
                'ancho' => 'completo', 'placeholder' => 'Citas y puntualidad',
            ],
            'categoria' => ['etiqueta' => 'Categoría', 'tipo' => 'opcion', 'requerido' => true, 'opciones' => self::CATEGORIAS, 'defecto' => 'General'],
            'orden' => [
                'etiqueta' => 'Orden en la página', 'tipo' => 'entero', 'requerido' => true, 'min' => 1, 'maxValor' => 99, 'defecto' => 1,
                'ayuda' => 'Las políticas se muestran de menor a mayor.',
            ],
            'contenido' => [
                'etiqueta' => 'Texto de la política', 'tipo' => 'textoLargo', 'requerido' => true, 'max' => 1000,
                'ayuda' => 'Se publica tal cual en la sección «Políticas de servicio» de la página pública.',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT * FROM politicas ORDER BY status DESC, orden, id_politica';
    }

    /** Políticas activas en el orden en que se publican. */
    public function publicas(): array
    {
        return $this->db()->query(
            'SELECT categoria, titulo, contenido, fecha_actualizacion FROM politicas WHERE status = 1 ORDER BY orden, id_politica'
        )->fetchAll();
    }
}
