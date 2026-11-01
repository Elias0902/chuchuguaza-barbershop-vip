<?php

namespace App\Models;

use App\Core\CrudModel;

class Producto extends CrudModel
{
    /** Por debajo de esta cantidad el producto se marca con stock bajo. */
    public const STOCK_MINIMO = 5;

    protected string $tabla = 'producto';
    protected string $clave = 'id_producto';
    protected string $modulo = 'Productos';
    protected string $singular = 'producto';
    protected ?string $campoDescriptivo = 'nombre_producto';

    public function campos(): array
    {
        return [
            'nombre_producto' => [
                'etiqueta' => 'Nombre del producto', 'tipo' => 'texto', 'requerido' => true, 'max' => 100,
                'unico' => true, 'ancho' => 'completo', 'placeholder' => 'Cera para cabello, refresco...',
            ],
            'id_categoria' => [
                'etiqueta' => 'Categoría', 'tipo' => 'relacion', 'requerido' => true, 'ancho' => 'completo',
                'relacion' => ['tabla' => 'categoria', 'clave' => 'id_categoria', 'texto' => 'nombre_categoria'],
            ],
            'precio' => ['etiqueta' => 'Precio de venta', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99],
            'stock' => ['etiqueta' => 'Stock disponible', 'tipo' => 'entero', 'requerido' => true, 'min' => 0, 'maxValor' => 999999, 'defecto' => 0],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT p.*, c.nombre_categoria,
                    CASE WHEN p.stock = 0 THEN \'Agotado\' WHEN p.stock <= ' . self::STOCK_MINIMO . ' THEN \'Bajo\' ELSE \'Disponible\' END AS nivel_stock
                FROM producto p
                LEFT JOIN categoria c ON c.id_categoria = p.id_categoria
                ORDER BY p.status DESC, p.nombre_producto';
    }
}
