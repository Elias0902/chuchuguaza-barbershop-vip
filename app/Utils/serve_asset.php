<?php
/**
 * ============================================================
 * serve_asset.php — Compresión y optimización automática de estáticos
 * ------------------------------------------------------------
 * Conectado vía .htaccess: TODAS las peticiones de .css/.js/.png/.jpg/.gif
 * del proyecto pasan por aquí de forma transparente (la URL no cambia).
 *
 *  - CSS / JS / SVG → se sirven comprimidos con gzip con caché en disco.
 *  - Imágenes PNG/JPG/GIF → se redimensionan (máx 1000px) y se
 *    recomprimen automáticamente, con caché en disco.
 *  - ETag + 304 Not Modified + Cache-Control largo.
 *
 * La caché vive en /cache_assets (en la raíz del proyecto).
 * Este archivo vive en app/Utils/, por eso la raíz se calcula con
 * dirname(__DIR__, 2) y la base de la URL quitando su sufijo.
 * ============================================================
 */

// Raíz del proyecto en disco (app/Utils/ -> raíz)
$RAIZ = dirname(__DIR__, 2);

// ── Resolver el archivo solicitado a partir de la URL original ──
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$uri  = rawurldecode($uri ?? '');

// Base URL del proyecto: SCRIPT_NAME sin el sufijo de ubicación de este script.
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$sufijo     = '/app/Utils/serve_asset.php';
if (substr($scriptName, -strlen($sufijo)) === $sufijo) {
    $base = substr($scriptName, 0, -strlen($sufijo));
} else {
    $base = rtrim(dirname($scriptName), '/');
}
$base = rtrim($base, '/'); // p.ej. /chuchuguaza-barber-shop

if ($base !== '' && strpos($uri, $base) === 0) {
    $rel = substr($uri, strlen($base));
} else {
    $rel = $uri;
}
$rel = ltrim($rel, '/');

$proyecto = str_replace('\\', '/', $RAIZ);
$ruta     = realpath($RAIZ . '/' . $rel);

// Seguridad: dentro del proyecto, existente, extensión permitida
$ext = strtolower(pathinfo($rel, PATHINFO_EXTENSION));
$permitidas = ['css','js','svg','png','jpg','jpeg','gif'];
if (!$ruta || strpos(str_replace('\\','/',$ruta), $proyecto) !== 0 || !is_file($ruta) || !in_array($ext, $permitidas, true)) {
    http_response_code(404);
    exit;
}

$mimes = [
    'css' => 'text/css', 'js' => 'application/javascript', 'svg' => 'image/svg+xml',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'gif' => 'image/gif',
];
$mime  = $mimes[$ext];
$mtime = filemtime($ruta);

// ── ETag / 304 ──
// v4: forzar re-fetch tras el cambio de rutas Assets/ -> assets/ (cache busting)
$etag = '"' . md5($ruta . '|' . $mtime . '|v4') . '"';
header('ETag: ' . $etag);
header('Cache-Control: public, max-age=2592000'); // 30 días
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

if ((isset($_SERVER['HTTP_IF_NONE_MATCH']) && trim($_SERVER['HTTP_IF_NONE_MATCH']) === $etag) ||
    (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) >= $mtime)) {
    http_response_code(304);
    exit;
}

// ── Carpeta de caché (en la raíz del proyecto) ──
$cacheDir = $RAIZ . '/cache_assets';
if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775);

$clienteAceptaGzip = strpos($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '', 'gzip') !== false;

function enviar($contenido, $mime, $gzip = false) {
    header('Content-Type: ' . $mime);
    if ($gzip) header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . strlen($contenido));
    echo $contenido;
    exit;
}

function enviarArchivo($archivo, $mime, $gzip = false) {
    header('Content-Type: ' . $mime);
    if ($gzip) header('Content-Encoding: gzip');
    header('Vary: Accept-Encoding');
    header('Content-Length: ' . filesize($archivo));
    readfile($archivo);
    exit;
}

// ════════════════════════════════════════════
// CSS / JS / SVG → gzip con caché en disco
// ════════════════════════════════════════════
if (in_array($ext, ['css','js','svg'], true)) {

    if (!$clienteAceptaGzip || !function_exists('gzencode')) {
        enviarArchivo($ruta, $mime);
    }

    $cacheFile = $cacheDir . '/' . md5($ruta . '|' . $mtime) . '.gz';

    if (!is_file($cacheFile)) {
        $contenido = file_get_contents($ruta);
        $gz = gzencode($contenido, 6);
        if ($gz !== false) {
            @file_put_contents($cacheFile, $gz, LOCK_EX);
        } else {
            enviar($contenido, $mime);
        }
    }

    if (is_file($cacheFile)) enviarArchivo($cacheFile, $mime, true);
    enviarArchivo($ruta, $mime);
}

// ════════════════════════════════════════════
// PNG / JPG / GIF → optimización automática con caché
// ════════════════════════════════════════════
const IMG_MAX_DIM = 1000; // lado máximo
const IMG_JPG_Q   = 82;
const IMG_PNG_C   = 8;

if (!extension_loaded('gd')) enviarArchivo($ruta, $mime);

$info = @getimagesize($ruta);
if (!$info) enviarArchivo($ruta, $mime);
list($w, $h) = $info;
$tipo = $info[2];

// Si ya es pequeña y liviana, servir tal cual
if (max($w, $h) <= IMG_MAX_DIM && filesize($ruta) < 120 * 1024) {
    enviarArchivo($ruta, $mime);
}

$cacheFile = $cacheDir . '/' . md5($ruta . '|' . $mtime . '|img' . IMG_MAX_DIM) . '.' . ($tipo === IMAGETYPE_JPEG ? 'jpg' : 'png');
$cacheMime = ($tipo === IMAGETYPE_JPEG) ? 'image/jpeg' : 'image/png';

if (is_file($cacheFile)) enviarArchivo($cacheFile, $cacheMime);

// Generar versión optimizada
$img = null;
switch ($tipo) {
    case IMAGETYPE_JPEG: $img = @imagecreatefromjpeg($ruta); break;
    case IMAGETYPE_PNG:  $img = @imagecreatefrompng($ruta);  break;
    case IMAGETYPE_GIF:  $img = @imagecreatefromgif($ruta);  break;
}
if (!$img) enviarArchivo($ruta, $mime);

$escala = min(1, IMG_MAX_DIM / max($w, $h));
$nw = max(1, (int)round($w * $escala));
$nh = max(1, (int)round($h * $escala));

$nuevo = imagecreatetruecolor($nw, $nh);
if ($tipo !== IMAGETYPE_JPEG) {
    imagealphablending($nuevo, false);
    imagesavealpha($nuevo, true);
    $transparente = imagecolorallocatealpha($nuevo, 0, 0, 0, 127);
    imagefilledrectangle($nuevo, 0, 0, $nw, $nh, $transparente);
}
imagecopyresampled($nuevo, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

if ($tipo === IMAGETYPE_JPEG) {
    imagejpeg($nuevo, $cacheFile, IMG_JPG_Q);
} else {
    imagepng($nuevo, $cacheFile, IMG_PNG_C);
}
imagedestroy($img);
imagedestroy($nuevo);

// Si la optimización no redujo el peso, conservar el original
if (is_file($cacheFile) && filesize($cacheFile) >= filesize($ruta)) {
    @copy($ruta, $cacheFile); // cachear el original para no reprocesar
}

if (is_file($cacheFile)) enviarArchivo($cacheFile, $cacheMime);
enviarArchivo($ruta, $mime);