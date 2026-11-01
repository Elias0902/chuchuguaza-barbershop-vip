<?php

namespace App\Models;

use App\Core\BaseModel;
use RuntimeException;
use Throwable;

/**
 * Inicio y cierre de sesión del sistema interno (base de seguridad),
 * con bloqueo temporal tras varios intentos fallidos.
 */
class Autenticator extends BaseModel
{
    public function autenticarInterno(string $usuario, string $clave): ?array
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, u.id_rol_usuario, u.avatar,
                    u.password_usuario, r.nombre_rol
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol_usuario
             WHERE u.nombre_usuario = :usuario
               AND u.status = 1
               AND u.email_verificado = 1
               AND r.status = 1
               AND (u.bloqueado_hasta IS NULL OR u.bloqueado_hasta <= NOW())
             LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        $fila = $stmt->fetch();

        if (!$fila || !password_verify($clave, (string) $fila['password_usuario'])) {
            return null;
        }

        unset($fila['password_usuario']);

        return $fila;
    }

    /** Minutos que faltan para que termine el bloqueo (0 si no está bloqueado). */
    public function verificarBloqueoLogin(string $usuario): int
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT TIMESTAMPDIFF(MINUTE, NOW(), bloqueado_hasta)
             FROM usuarios
             WHERE nombre_usuario = :usuario AND bloqueado_hasta IS NOT NULL AND bloqueado_hasta > NOW()
             LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario]);
        $restante = $stmt->fetchColumn();

        return $restante === false ? 0 : max(1, (int) $restante);
    }

    /** Suma un intento fallido y bloquea la cuenta al llegar al máximo. Devuelve los minutos de bloqueo. */
    public function registrarIntentoFallido(string $usuario): int
    {
        $db = $this->seguridad();
        $db->beginTransaction();

        try {
            $db->prepare('UPDATE usuarios SET intentos_login = intentos_login + 1 WHERE nombre_usuario = :usuario AND status = 1')
                ->execute([':usuario' => $usuario]);

            $stmt = $db->prepare('SELECT intentos_login FROM usuarios WHERE nombre_usuario = :usuario LIMIT 1');
            $stmt->execute([':usuario' => $usuario]);
            $intentos = $stmt->fetchColumn();

            $bloqueo = 0;
            if ($intentos !== false && (int) $intentos >= MAX_INTENTOS_LOGIN) {
                $db->prepare(
                    'UPDATE usuarios
                     SET intentos_login = 0,
                         bloqueado_hasta = DATE_ADD(NOW(), INTERVAL ' . (int) BLOQUEO_MINUTOS_LOGIN . ' MINUTE)
                     WHERE nombre_usuario = :usuario'
                )->execute([':usuario' => $usuario]);
                $bloqueo = (int) BLOQUEO_MINUTOS_LOGIN;
            }

            $db->commit();

            return $bloqueo;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    public function registrarAcceso(int $idUsuario, string $usuario): void
    {
        $stmt = $this->seguridad()->prepare(
            'UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL, ultimo_acceso = NOW()
             WHERE id_usuario = :id AND status = 1'
        );
        $stmt->execute([':id' => $idUsuario]);
        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('No se pudo actualizar el último acceso del usuario.');
        }

        $this->registrarBitacora('Seguridad', 'Inicio de sesión', "El usuario $usuario inició sesión en el sistema interno.", $idUsuario);
    }

    public function registrarCierreSesion(int $idUsuario, string $usuario): void
    {
        $this->registrarBitacora('Seguridad', 'Cierre de sesión', "El usuario $usuario cerró sesión en el sistema interno.", $idUsuario);
    }
}
