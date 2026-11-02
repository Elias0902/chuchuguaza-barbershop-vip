<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

/**
 * Usuarios con acceso al sistema (base de seguridad).
 */
class Usuario extends CrudModel
{
    public const AVATAR_PREDETERMINADO = 'assets/img/perfiles/default.png';

    protected string $tabla = 'usuarios';
    protected string $clave = 'id_usuario';
    protected string $baseDatos = self::SEGURIDAD;
    protected string $modulo = 'Usuarios';
    protected string $singular = 'usuario';
    protected ?string $campoDescriptivo = 'nombre_usuario';
    protected array $ocultos = ['password_usuario', 'intentos_login'];

    public function campos(): array
    {
        return [
            'nombre_usuario' => ['etiqueta' => 'Nombre de usuario', 'tipo' => 'usuario', 'requerido' => true, 'max' => 100, 'unico' => true],
            'email_usuario' => ['etiqueta' => 'Correo', 'tipo' => 'email', 'requerido' => true, 'max' => 100, 'unico' => true],
            'id_rol_usuario' => [
                'etiqueta' => 'Rol', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'roles', 'clave' => 'id_rol', 'texto' => 'nombre_rol', 'bd' => self::SEGURIDAD],
            ],
            'cedula_barbero' => [
                'etiqueta' => 'Barbero vinculado', 'tipo' => 'relacion',
                'ayuda' => 'Solo para cuentas de barberos: verán y registrarán únicamente sus propios tickets y citas.',
                'relacion' => ['tabla' => 'barberos', 'clave' => 'cedula', 'claveTexto' => true, 'texto' => "CONCAT(nombre, ' ', apellido)"],
            ],
            'password_usuario' => [
                'etiqueta' => 'Contraseña', 'tipo' => 'clave', 'requerido' => 'registro', 'max' => 72,
                'ayuda' => 'Mínimo 8 caracteres con letras y números. Al modificar, déjala vacía para conservar la actual.',
            ],
        ];
    }

    protected function columnasExtra(): array
    {
        return ['email_verificado', 'fecha_registro', 'avatar', 'intentos_login', 'bloqueado_hasta'];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT u.id_usuario, u.nombre_usuario, u.email_usuario, u.id_rol_usuario, u.cedula_barbero, u.avatar, u.ultimo_acceso,
                    u.fecha_registro, u.status, r.nombre_rol,
                    IF(u.bloqueado_hasta IS NOT NULL AND u.bloqueado_hasta > NOW(), 1, 0) AS bloqueado
                FROM usuarios u
                LEFT JOIN roles r ON r.id_rol = u.id_rol_usuario
                ORDER BY u.status DESC, u.nombre_usuario';
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $esUsuarioEnSesion = $actual !== null && (int) $actual['id_usuario'] === (int) ($_SESSION['s_usuario']['id_usuario'] ?? 0);
        if ($esUsuarioEnSesion && (int) $datos['id_rol_usuario'] !== (int) $actual['id_rol_usuario']) {
            throw new ValidacionException('No puedes cambiar tu propio rol; pídeselo a otro administrador.', 'id_rol_usuario');
        }

        if ($actual === null) {
            $datos['email_verificado'] = 1;
            $datos['fecha_registro'] = date('Y-m-d H:i:s');
            $datos['avatar'] = self::AVATAR_PREDETERMINADO;
            $datos['intentos_login'] = 0;
        }
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        if ((int) $actual['id_usuario'] === (int) ($_SESSION['s_usuario']['id_usuario'] ?? 0)) {
            throw new ValidacionException('No puedes eliminar tu propio usuario mientras tienes la sesión abierta.');
        }
    }

    /** Al restaurar se limpian los intentos fallidos y el bloqueo. */
    protected function antesDeRestaurar(PDO $db, array $actual): void
    {
        $db->prepare('UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL WHERE id_usuario = :id')
            ->execute([':id' => $actual['id_usuario']]);
    }

    public function desbloquear(int $id): void
    {
        $actual = $this->obtenerOFallar($id);
        $this->db()->prepare('UPDATE usuarios SET intentos_login = 0, bloqueado_hasta = NULL WHERE id_usuario = :id')
            ->execute([':id' => $id]);
        $this->bitacora('Desbloquear', 'Desbloqueó al usuario ' . $actual['nombre_usuario'] . '.');
    }
}
