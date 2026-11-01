<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;

/**
 * Datos de la cuenta del usuario en sesión: información, foto y contraseña.
 */
class Perfil extends BaseModel
{
    public function obtener(int $idUsuario): array
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, u.avatar, u.ultimo_acceso, u.fecha_registro,
                    r.nombre_rol, r.descripcion AS descripcion_rol
             FROM usuarios u
             LEFT JOIN roles r ON r.id_rol = u.id_rol_usuario
             WHERE u.id_usuario = :id AND u.status = 1'
        );
        $stmt->execute([':id' => $idUsuario]);
        $perfil = $stmt->fetch();
        if (!$perfil) {
            throw new RegistroNoEncontradoException('No se encontró la cuenta en sesión.');
        }

        return $perfil;
    }

    public function actualizarDatos(int $idUsuario, mixed $usuario, mixed $correo): void
    {
        $usuario = trim(is_string($usuario) ? $usuario : '');
        $correo = mb_strtolower(trim(is_string($correo) ? $correo : ''), 'UTF-8');

        if (!preg_match('/\A[a-zA-Z0-9._-]{4,30}\z/', $usuario)) {
            throw new ValidacionException('El nombre de usuario debe tener de 4 a 30 caracteres: letras, números, punto, guion o guion bajo.', 'nombre_usuario');
        }
        if (strlen($correo) > 100 || !filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            throw new ValidacionException('Escribe un correo válido.', 'email_usuario');
        }

        $stmt = $this->seguridad()->prepare(
            'SELECT nombre_usuario = :usuario AS mismo_usuario FROM usuarios
             WHERE id_usuario <> :id AND (nombre_usuario = :usuario_buscar OR email_usuario = :correo) LIMIT 1'
        );
        $stmt->execute([':usuario' => $usuario, ':id' => $idUsuario, ':usuario_buscar' => $usuario, ':correo' => $correo]);
        $repetido = $stmt->fetch();
        if ($repetido) {
            throw $repetido['mismo_usuario']
                ? new ValidacionException('Ese nombre de usuario ya está en uso.', 'nombre_usuario')
                : new ValidacionException('Ese correo ya está en uso.', 'email_usuario');
        }

        $this->seguridad()->prepare('UPDATE usuarios SET nombre_usuario = :usuario, email_usuario = :correo WHERE id_usuario = :id')
            ->execute([':usuario' => $usuario, ':correo' => $correo, ':id' => $idUsuario]);
        $this->registrarBitacora('Mi perfil', 'Modificar', "Actualizó sus datos de cuenta ($usuario).");
    }

    public function cambiarClave(int $idUsuario, mixed $actual, mixed $nueva, mixed $confirmacion): void
    {
        $actual = is_string($actual) ? $actual : '';
        $nueva = is_string($nueva) ? $nueva : '';

        $stmt = $this->seguridad()->prepare('SELECT password_usuario FROM usuarios WHERE id_usuario = :id');
        $stmt->execute([':id' => $idUsuario]);
        $guardada = (string) $stmt->fetchColumn();
        if (!password_verify($actual, $guardada)) {
            throw new ValidacionException('La contraseña actual no es correcta.', 'clave_actual');
        }
        if (strlen($nueva) < 8 || strlen($nueva) > 72 || !preg_match('/[A-Za-z]/', $nueva) || !preg_match('/\d/', $nueva)) {
            throw new ValidacionException('La nueva contraseña debe tener entre 8 y 72 caracteres e incluir letras y números.', 'clave_nueva');
        }
        if ($nueva !== $confirmacion) {
            throw new ValidacionException('La confirmación no coincide con la nueva contraseña.', 'clave_confirmacion');
        }
        if (password_verify($nueva, $guardada)) {
            throw new ValidacionException('La nueva contraseña debe ser distinta de la actual.', 'clave_nueva');
        }

        $this->seguridad()->prepare('UPDATE usuarios SET password_usuario = :clave WHERE id_usuario = :id')
            ->execute([':clave' => password_hash($nueva, PASSWORD_DEFAULT), ':id' => $idUsuario]);
        $this->registrarBitacora('Mi perfil', 'Cambiar contraseña', 'Cambió su contraseña.');
    }

    /** Guarda la ruta de la nueva foto y devuelve la anterior para poder borrarla. */
    public function cambiarAvatar(int $idUsuario, string $ruta): string
    {
        $anterior = (string) $this->obtener($idUsuario)['avatar'];
        $this->seguridad()->prepare('UPDATE usuarios SET avatar = :avatar WHERE id_usuario = :id')
            ->execute([':avatar' => $ruta, ':id' => $idUsuario]);
        $this->registrarBitacora('Mi perfil', 'Cambiar foto', 'Actualizó su foto de perfil.');

        return $anterior;
    }
}
