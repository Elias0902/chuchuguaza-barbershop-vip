<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\ValidacionException;
use App\Helpers\Correo;
use Throwable;

/**
 * Recuperación de contraseña con enlace de un solo uso.
 * En la tabla tokens solo se guarda el hash SHA-256 del token; el token real viaja únicamente en el correo.
 */
class RecuperacionClave extends BaseModel
{
    /**
     * Envía el enlace si el usuario o correo existe y está activo. No informa si existe:
     * quien llama siempre muestra el mismo mensaje.
     */
    public function solicitar(string $identificador): void
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT id_usuario, nombre_usuario, email_usuario FROM usuarios
             WHERE (nombre_usuario = :usuario OR email_usuario = :correo) AND status = 1 LIMIT 1'
        );
        $stmt->execute([':usuario' => $identificador, ':correo' => $identificador]);
        $usuario = $stmt->fetch();
        if (!$usuario || $this->solicitudesRecientes((int) $usuario['id_usuario']) >= RECUPERACION_MAX_POR_HORA) {
            return;
        }

        $token = bin2hex(random_bytes(32));
        $db = $this->seguridad();
        // Un enlace nuevo anula los anteriores que no se usaron.
        $db->prepare('UPDATE tokens SET usado = 1 WHERE id_usuario = :id AND usado = 0')->execute([':id' => $usuario['id_usuario']]);
        $db->prepare(
            "INSERT INTO tokens (id_usuario, codigo, token, expira, usado, fecha_creacion)
             VALUES (:id, 'clave', :token, DATE_ADD(NOW(), INTERVAL " . (int) RECUPERACION_MINUTOS . ' MINUTE), 0, NOW())'
        )->execute([':id' => $usuario['id_usuario'], ':token' => hash('sha256', $token)]);

        $enlace = APP_URL . '/index.php?url=autenticator&action=restablecer&token=' . $token;
        $nombre = htmlspecialchars($usuario['nombre_usuario'], ENT_QUOTES, 'UTF-8');
        $minutos = RECUPERACION_MINUTOS;
        $html = "<p>Hola, $nombre.</p><p>Recibimos una solicitud para restablecer tu contraseña de " . APP_NOMBRE . '.</p>'
            . '<p><a href="' . htmlspecialchars($enlace, ENT_QUOTES, 'UTF-8') . '">Crear una contraseña nueva</a></p>'
            . "<p>El enlace vence en $minutos minutos y solo se puede usar una vez. Si no lo pediste, ignora este correo.</p>";
        $texto = "Hola, {$usuario['nombre_usuario']}.\n\nPara crear una contraseña nueva abre este enlace:\n$enlace\n\n"
            . "Vence en $minutos minutos y solo se puede usar una vez. Si no lo pediste, ignora este correo.";

        Correo::enviar($usuario['email_usuario'], 'Restablecer contraseña · ' . APP_NOMBRE, $html, $texto);
        $this->registrarBitacora('Seguridad', 'Recuperar contraseña', "Se envió un enlace de recuperación a {$usuario['nombre_usuario']}.", (int) $usuario['id_usuario']);
    }

    /** Usuario dueño de un token vigente, o null si no existe, venció o ya se usó. */
    public function usuarioDelToken(string $token): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }
        $stmt = $this->seguridad()->prepare(
            "SELECT t.id_tokens, u.id_usuario, u.nombre_usuario
             FROM tokens t INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.token = :token AND t.codigo = 'clave' AND t.usado = 0 AND t.expira > NOW() AND u.status = 1
             LIMIT 1"
        );
        $stmt->execute([':token' => hash('sha256', $token)]);

        return $stmt->fetch() ?: null;
    }

    public function restablecer(string $token, string $clave, string $confirmacion): void
    {
        if ($clave !== $confirmacion) {
            throw new ValidacionException('Las contraseñas no coinciden.');
        }
        if (strlen($clave) < 8 || strlen($clave) > 72 || !preg_match('/[A-Za-z]/', $clave) || !preg_match('/\d/', $clave)) {
            throw new ValidacionException('La contraseña debe tener entre 8 y 72 caracteres, con letras y números.');
        }

        $db = $this->seguridad();
        $db->beginTransaction();
        try {
            $usuario = $this->usuarioDelToken($token);
            if ($usuario === null) {
                throw new ValidacionException('El enlace no es válido o ya venció. Pide uno nuevo.');
            }
            // Se marca usado primero y se comprueba que nadie lo usó en paralelo.
            $marcar = $db->prepare('UPDATE tokens SET usado = 1 WHERE id_tokens = :id AND usado = 0');
            $marcar->execute([':id' => $usuario['id_tokens']]);
            if ($marcar->rowCount() !== 1) {
                throw new ValidacionException('El enlace no es válido o ya venció. Pide uno nuevo.');
            }
            $db->prepare(
                'UPDATE usuarios SET password_usuario = :clave, intentos_login = 0, bloqueado_hasta = NULL WHERE id_usuario = :id'
            )->execute([':clave' => password_hash($clave, PASSWORD_DEFAULT), ':id' => $usuario['id_usuario']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        $this->registrarBitacora('Seguridad', 'Restablecer contraseña', "{$usuario['nombre_usuario']} restableció su contraseña con un enlace de recuperación.", (int) $usuario['id_usuario']);
    }

    private function solicitudesRecientes(int $idUsuario): int
    {
        $stmt = $this->seguridad()->prepare(
            "SELECT COUNT(*) FROM tokens WHERE id_usuario = :id AND codigo = 'clave' AND fecha_creacion > DATE_SUB(NOW(), INTERVAL 1 HOUR)"
        );
        $stmt->execute([':id' => $idUsuario]);

        return (int) $stmt->fetchColumn();
    }
}
