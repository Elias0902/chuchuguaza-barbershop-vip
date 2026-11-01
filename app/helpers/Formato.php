<?php

namespace App\Helpers;

/**
 * Formatos de presentación compartidos por las vistas, los gráficos y los reportes PDF.
 */
final class Formato
{
    private function __construct()
    {
    }

    public static function valor(mixed $valor, string $formato = 'texto'): string
    {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($formato) {
            'moneda' => MONEDA_SIMBOLO . ' ' . number_format((float) $valor, 2, ',', '.'),
            'entero' => number_format((float) $valor, 0, ',', '.'),
            'decimal' => number_format((float) $valor, 1, ',', '.'),
            'porcentaje' => number_format((float) $valor, 2, ',', '.') . '%',
            'minutos' => (int) $valor . ' min',
            'numero' => '#' . $valor,
            'fecha' => date('d/m/Y', strtotime((string) $valor)),
            'fechahora' => date('d/m/Y h:i a', strtotime((string) $valor)),
            'sino' => (int) $valor === 1 ? 'Sí' : 'No',
            'estado' => (int) $valor === 1 ? 'Activo' : 'Eliminado',
            default => (string) $valor,
        };
    }

    /** "1 cita", "3 citas". */
    public static function cantidad(int $numero, string $singular, string $plural): string
    {
        return number_format($numero, 0, ',', '.') . ' ' . ($numero === 1 ? $singular : $plural);
    }
}
