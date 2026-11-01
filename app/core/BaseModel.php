<?php

namespace App\Core;

use Throwable;

/**
 * Modelo base: hereda la conexión PDO y agrega lo que comparten todos los modelos.
 */
abstract class BaseModel extends Conexion
{
    /** Guarda una operación en la bitácora de seguridad sin interrumpir el flujo si falla. */
    protected function registrarBitacora(string $modulo, string $accion, string $descripcion, ?int $idUsuario = null): void
    {
        try {
            $stmt = $this->seguridad()->prepare(
                'INSERT INTO bitacoras (id_usuario, modulo, accion, descripcion, fecha_bitacora)
                 VALUES (:id_usuario, :modulo, :accion, :descripcion, NOW(6))'
            );
            $stmt->execute([
                ':id_usuario' => $idUsuario ?? ($_SESSION['s_usuario']['id_usuario'] ?? null),
                ':modulo' => $modulo,
                ':accion' => $accion,
                ':descripcion' => mb_substr($descripcion, 0, 500, 'UTF-8'),
            ]);
        } catch (Throwable $e) {
            error_log('No se pudo registrar la bitácora: ' . $e->getMessage());
        }
    }
}
