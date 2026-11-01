<?php

namespace App\Helpers;

/**
 * Gráficos de una sola serie en HTML/CSS (sin librerías):
 *  - columnas(): verticales, para series ordenadas (días, horas, meses).
 *  - barras(): horizontales, para rankings o categorías con nombre largo.
 * Cada marca tiene tooltip al pasar el mouse o enfocar con teclado, el valor
 * mayor va rotulado y debajo hay una tabla con todos los datos.
 *
 * $puntos = [['etiqueta' => 'Lun', 'valor' => 3, 'detalle' => 'lunes'], ...]
 */
final class Grafico
{
    private function __construct()
    {
    }

    public static function columnas(string $titulo, array $puntos, string $formato = 'entero', string $unidad = ''): string
    {
        if (!self::hayDatos($puntos)) {
            return self::vacio();
        }

        $maximo = max(array_column($puntos, 'valor'));
        $indiceMaximo = array_search($maximo, array_column($puntos, 'valor'), true);
        $columnas = '';
        $ejes = '';
        foreach (array_values($puntos) as $i => $punto) {
            $alto = $maximo > 0 && $punto['valor'] > 0 ? max(1, round($punto['valor'] / $maximo * 100, 1)) : 0;
            $texto = self::texto($punto, $formato, $unidad);
            $rotulo = $i === $indiceMaximo && $punto['valor'] > 0
                ? '<span class="chart-col-value" style="bottom: ' . $alto . '%">' . Vista::e(Formato::valor($punto['valor'], $formato)) . '</span>'
                : '';
            $columnas .= '<div class="chart-col" tabindex="0" aria-label="' . Vista::e($texto) . '">'
                . $rotulo
                . '<span class="chart-col-bar' . ($alto === 0 ? ' is-zero' : '') . '" style="height: ' . $alto . '%"></span>'
                . '<span class="chart-tip">' . Vista::e($texto) . '</span>'
                . '</div>';
            $ejes .= '<span>' . Vista::e($punto['etiqueta']) . '</span>';
        }

        return '<figure class="chart" aria-label="' . Vista::e($titulo) . '">'
            . '<div class="chart-cols">' . $columnas . '</div>'
            . '<div class="chart-axis" aria-hidden="true">' . $ejes . '</div>'
            . self::tabla($puntos, $formato)
            . '</figure>';
    }

    public static function barras(string $titulo, array $puntos, string $formato = 'entero', string $unidad = ''): string
    {
        if (!self::hayDatos($puntos)) {
            return self::vacio();
        }

        $maximo = max(array_column($puntos, 'valor'));
        $filas = '';
        foreach ($puntos as $punto) {
            $ancho = $maximo > 0 ? max(1, round($punto['valor'] / $maximo * 100, 1)) : 0;
            $texto = self::texto($punto, $formato, $unidad);
            $filas .= '<div class="chart-bar-row" tabindex="0" aria-label="' . Vista::e($texto) . '">'
                . '<span class="chart-bar-label" title="' . Vista::e($punto['etiqueta']) . '">' . Vista::e($punto['etiqueta']) . '</span>'
                . '<span class="chart-bar-value">' . Vista::e(Formato::valor($punto['valor'], $formato)) . '</span>'
                . '<span class="chart-bar-track"><span class="chart-bar-fill" style="width: ' . $ancho . '%"></span></span>'
                . '<span class="chart-tip">' . Vista::e($texto) . '</span>'
                . '</div>';
        }

        return '<figure class="chart" aria-label="' . Vista::e($titulo) . '"><div class="chart-bars">' . $filas . '</div></figure>';
    }

    private static function texto(array $punto, string $formato, string $unidad): string
    {
        $valor = Formato::valor($punto['valor'], $formato) . ($unidad !== '' ? " $unidad" : '');

        return ($punto['detalle'] ?? $punto['etiqueta']) . ': ' . $valor;
    }

    private static function tabla(array $puntos, string $formato): string
    {
        $filas = '';
        foreach ($puntos as $punto) {
            $filas .= '<tr><td>' . Vista::e($punto['detalle'] ?? $punto['etiqueta']) . '</td><td>' . Vista::e(Formato::valor($punto['valor'], $formato)) . '</td></tr>';
        }

        return '<details class="chart-data"><summary>Ver datos</summary><table><tbody>' . $filas . '</tbody></table></details>';
    }

    private static function hayDatos(array $puntos): bool
    {
        return $puntos !== [] && array_sum(array_column($puntos, 'valor')) > 0;
    }

    private static function vacio(): string
    {
        return '<p class="chart-empty">Todavía no hay datos suficientes para este gráfico.</p>';
    }
}
