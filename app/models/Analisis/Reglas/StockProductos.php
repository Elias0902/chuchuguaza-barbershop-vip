<?php

namespace App\Models\Analisis\Reglas;

use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\ReglaAnalisis;
use App\Models\Producto;

/** Productos agotados o por agotarse. */
class StockProductos extends ReglaAnalisis
{
    public function evaluar(): array
    {
        $hallazgos = [];

        $agotados = array_column($this->filas('SELECT nombre_producto FROM producto WHERE status = 1 AND stock = 0 ORDER BY nombre_producto'), 'nombre_producto');
        if ($agotados) {
            $hallazgos[] = new Hallazgo(
                'stock-agotado', 'Inventario', Hallazgo::ALTA,
                count($agotados) === 1 ? '1 producto agotado' : count($agotados) . ' productos agotados',
                'No quedan existencias de ' . $this->lista($agotados) . '. Repón el inventario: cada cliente que lo pide y no lo encuentra es una venta perdida.',
                'fa-box-open',
                'productos'
            );
        }

        $bajos = $this->filas(
            'SELECT nombre_producto, stock FROM producto WHERE status = 1 AND stock BETWEEN 1 AND :minimo ORDER BY stock, nombre_producto',
            [':minimo' => Producto::STOCK_MINIMO]
        );
        if ($bajos) {
            $nombres = array_map(static fn(array $f): string => "{$f['nombre_producto']} ({$f['stock']})", $bajos);
            $hallazgos[] = new Hallazgo(
                'stock-bajo', 'Inventario', Hallazgo::MEDIA,
                count($bajos) === 1 ? '1 producto con stock bajo' : count($bajos) . ' productos con stock bajo',
                'Están por agotarse: ' . $this->lista($nombres, 4) . '. Pide reposición antes de quedarte sin existencias.',
                'fa-boxes',
                'productos'
            );
        }

        return $hallazgos;
    }
}
