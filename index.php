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

// Cabeceras de seguridad (y sin anunciar la versión de PHP).
header_remove('X-Powered-By');
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
    // La cookie solo lleva un identificador aleatorio; los datos de la sesión quedan en el servidor.
    $https = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    session_start([
        'name' => 'PHPSESSID',
        'cookie_httponly' => true,        // JavaScript no puede leerla (protege contra XSS)
        'cookie_secure' => $https,         // con HTTPS solo viaja cifrada
        'cookie_samesite' => 'Strict',     // no se envía desde otros sitios (protege contra CSRF)
        'cookie_lifetime' => 0,           // se borra al cerrar el navegador
        'use_strict_mode' => true,        // rechaza identificadores inventados (fijación de sesión)
        'use_only_cookies' => true,       // nunca en la URL
        'sid_length' => 48,               // identificador más largo y difícil de adivinar
        'sid_bits_per_character' => 6,
        'gc_maxlifetime' => 1800,
    ]);
}

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/config/config.php';

$frontController = new FrontController(require __DIR__ . '/config/route.php');
$frontController->despachar();
