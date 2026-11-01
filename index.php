<?php

/**
 * Punto de entrada único del sistema interno de Chuchuguaza Barbershop.
 * Carga Composer (autoload PSR-4 y librerías), la configuración y despacha
 * la petición con el FrontController.
 */

use App\Core\FrontController;

// Compresión gzip del HTML y del JSON.
if (!ob_get_level() && extension_loaded('zlib') && !ini_get('zlib.output_compression')) {
    ob_start('ob_gzhandler');
}

// Cabeceras de seguridad.
header("Content-Security-Policy: default-src 'self' https: data: blob:; "
     . "script-src 'self' 'unsafe-inline'; "
     . "style-src 'self' https://fonts.googleapis.com 'unsafe-inline'; "
     . "img-src 'self' data: blob:; "
     . "font-src 'self' https://fonts.gstatic.com data:; "
     . "connect-src 'self'; "
     . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Las páginas autenticadas no se guardan en caché (evita verlas con "Atrás" tras cerrar sesión).
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Lax',
        'use_strict_mode' => true,
    ]);
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/config.php';

$frontController = new FrontController(require __DIR__ . '/config/route.php');
$frontController->despachar();
