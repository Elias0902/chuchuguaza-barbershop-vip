<?php

namespace App\Helpers;

/**
 * Utilidades para las vistas: escape de HTML, rutas de recursos con versión y avatar.
 */
final class Vista
{
    private function __construct()
    {
    }

    /** Escapa texto para imprimirlo en HTML. */
    public static function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
    }

    /** Ruta de un CSS/JS con su fecha de modificación para que el navegador no use una copia vieja. */
    public static function asset(string $ruta): string
    {
        $archivo = dirname(__DIR__, 2) . '/' . $ruta;
        $version = is_file($archivo) ? filemtime($archivo) : 0;

        return self::e($ruta . '?v=' . $version);
    }

    /** JSON seguro para incrustarlo en un <script type="application/json">. */
    public static function json(mixed $datos): string
    {
        return json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /** Foto del usuario si existe en el servidor; si no, null para mostrar sus iniciales. */
    public static function avatar(?string $ruta): ?string
    {
        if ($ruta === null || $ruta === '' || !preg_match('#\Aassets/img/perfiles/[A-Za-z0-9_.-]+\z#', $ruta)) {
            return null;
        }

        return is_file(dirname(__DIR__, 2) . '/' . $ruta) ? self::asset($ruta) : null;
    }

    /** Iniciales para el avatar por defecto ("demo_barberia" → "DB"). */
    public static function iniciales(string $nombre): string
    {
        $partes = preg_split('/[\s._-]+/', trim($nombre), -1, PREG_SPLIT_NO_EMPTY) ?: ['?'];
        $iniciales = mb_substr($partes[0], 0, 1, 'UTF-8') . (isset($partes[1]) ? mb_substr($partes[1], 0, 1, 'UTF-8') : '');

        return mb_strtoupper($iniciales, 'UTF-8');
    }
}
