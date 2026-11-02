<?php

/**
 * Configuración general. Los valores se pueden sobrescribir en un archivo .env
 * en la raíz del proyecto (no se sube al repositorio).
 */

// ── Cargador de variables de entorno (.env) ────────────────────────────────
$archivoEnv = dirname(__DIR__) . '/.env';
if (is_file($archivoEnv)) {
    foreach (file($archivoEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) {
            continue;
        }
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        $valor = trim($valor, "\"'");
        if (!array_key_exists($clave, $_ENV)) {
            $_ENV[$clave] = $valor;
            putenv("$clave=$valor");
        }
    }
}

function env(string $clave, string $porDefecto = ''): string
{
    $valor = $_ENV[$clave] ?? getenv($clave);

    return ($valor !== false && $valor !== '') ? (string) $valor : $porDefecto;
}

// ── Aplicación ─────────────────────────────────────────────────────────────
define('APP_NOMBRE', env('APP_NOMBRE', 'Chuchuguaza Barbershop'));
define('MONEDA_SIMBOLO', env('MONEDA_SIMBOLO', '$'));
date_default_timezone_set(env('ZONA_HORARIA', 'America/Caracas'));

// ── Base de datos del negocio ──────────────────────────────────────────────
define('DB_HOST_NEGOCIO', env('DB_HOST_NEGOCIO', 'localhost'));
define('DB_NAME_NEGOCIO', env('DB_NAME_NEGOCIO', 'chuchuguasa_db'));
define('DB_USER_NEGOCIO', env('DB_USER_NEGOCIO', 'root'));
define('DB_PASS_NEGOCIO', env('DB_PASS_NEGOCIO', ''));

// ── Base de datos de seguridad ─────────────────────────────────────────────
define('DB_HOST_SECURITY', env('DB_HOST_SECURITY', 'localhost'));
define('DB_NAME_SECURITY', env('DB_NAME_SECURITY', 'chuchuguasa_seguridad'));
define('DB_USER_SECURITY', env('DB_USER_SECURITY', 'root'));
define('DB_PASS_SECURITY', env('DB_PASS_SECURITY', ''));

// ── Limitador de intentos de inicio de sesión ──────────────────────────────
define('MAX_INTENTOS_LOGIN', 3);
define('BLOQUEO_MINUTOS_LOGIN', 30);

// ── Correo (recuperación de contraseña) ────────────────────────────────────
// Sin SMTP_HOST los correos no se envían: se guardan en storage/correos para revisarlos.
define('APP_URL', rtrim(env('APP_URL', 'http://localhost/chuchuguaza-barber-shop'), '/'));
define('SMTP_HOST', env('SMTP_HOST'));
define('SMTP_PUERTO', (int) env('SMTP_PUERTO', '587'));
define('SMTP_USUARIO', env('SMTP_USUARIO'));
define('SMTP_CLAVE', env('SMTP_CLAVE'));
define('SMTP_SEGURIDAD', env('SMTP_SEGURIDAD', 'tls'));
define('CORREO_REMITENTE', env('CORREO_REMITENTE', 'no-responder@chuchuguaza.local'));

// ── Recuperación de contraseña ─────────────────────────────────────────────
define('RECUPERACION_MINUTOS', 30);
define('RECUPERACION_MAX_POR_HORA', 3);
