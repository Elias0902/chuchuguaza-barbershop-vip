<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

class Categoria extends CrudModel
{
    protected string $tabla = 'categoria';
    protected string $clave = 'id_categoria';
    protected string $modulo = 'Categorías';
    protected string $singular = 'categoría';
    protected bool $femenino = true;
    protected ?string $campoDescriptivo = 'nombre_categoria';

    public function campos(): array
    {
        return [
            'nombre_categoria' => [
                'etiqueta' => 'Nombre de la categoría', 'tipo' => 'texto', 'requerido' => true, 'max' => 50,
                'unico' => true, 'ancho' => 'completo', 'placeholder' => 'Cuidado capilar, bebidas, accesorios...',
            ],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT c.*, (SELECT COUNT(*) FROM producto p WHERE p.id_categoria = c.id_categoria AND p.status = 1) AS productos
                FROM categoria c ORDER BY c.status DESC, c.nombre_categoria';
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM producto WHERE id_categoria = :id AND status = 1');
        $stmt->execute([':id' => $actual['id_categoria']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('La categoría tiene productos activos. Muévelos a otra categoría o elimínalos primero.');
        }
    }
}
