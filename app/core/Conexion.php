<?php

namespace App\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Conexión PDO a las dos bases de datos del sistema (negocio y seguridad).
 *
 * Encapsulamiento: las conexiones y las credenciales son privadas; las clases
 * hijas solo pueden pedir la conexión mediante negocio() y seguridad().
 * Cada base de datos se abre una sola vez por petición y se comparte entre modelos,
 * así una transacción puede abarcar operaciones de varios modelos.
 */
abstract class Conexion
{
    public const NEGOCIO = 'negocio';
    public const SEGURIDAD = 'seguridad';

    /** @var array<string, PDO> */
    private static array $conexiones = [];

    protected function negocio(): PDO
    {
        return self::conectar(self::NEGOCIO);
    }

    protected function seguridad(): PDO
    {
        return self::conectar(self::SEGURIDAD);
    }

    protected function conexion(string $base): PDO
    {
        return $base === self::SEGURIDAD ? $this->seguridad() : $this->negocio();
    }

    private static function conectar(string $base): PDO
    {
        if (isset(self::$conexiones[$base])) {
            return self::$conexiones[$base];
        }

        [$host, $nombre, $usuario, $clave] = $base === self::SEGURIDAD
            ? [DB_HOST_SECURITY, DB_NAME_SECURITY, DB_USER_SECURITY, DB_PASS_SECURITY]
            : [DB_HOST_NEGOCIO, DB_NAME_NEGOCIO, DB_USER_NEGOCIO, DB_PASS_NEGOCIO];

        try {
            self::$conexiones[$base] = new PDO(
                "mysql:host=$host;dbname=$nombre;charset=utf8mb4",
                $usuario,
                $clave,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de $base: " . $e->getMessage());
            throw new RuntimeException('No se pudo conectar con la base de datos. Verifica que MySQL esté encendido.');
        }

        return self::$conexiones[$base];
    }
}
