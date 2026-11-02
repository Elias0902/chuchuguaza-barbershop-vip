<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\ValidacionException;
use App\Helpers\Correo;

/**
 * Verificación en dos pasos: código de 6 dígitos enviado al correo del usuario.
 * En la tabla tokens solo queda el hash del código; vence en DOSFA_MINUTOS y sirve una vez.
 */
class DosFactores extends BaseModel
{
    private const SEGUNDOS_ENTRE_ENVIOS = 60;

    public static function exigidoPara(string $rol): bool
    {
        return in_array($rol, DOSFA_ROLES, true);
    }

    /** Correo con parte oculta para mostrarlo en pantalla: zo***@gmail.com */
    public static function correoOculto(string $correo): string
    {
        [$nombre, $dominio] = array_pad(explode('@', $correo, 2), 2, '');

        return mb_substr($nombre, 0, 2) . str_repeat('*', max(3, mb_strlen($nombre) - 2)) . '@' . $dominio;
    }

    /** $reenvio = true cuando lo pide la persona con «enviar otro código» (máximo uno por minuto). */
    public function enviar(array $usuario, bool $reenvio = false): void
    {
        $db = $this->seguridad();
        $ultimo = $db->prepare("SELECT TIMESTAMPDIFF(SECOND, MAX(fecha_creacion), NOW()) FROM tokens WHERE id_usuario = :id AND codigo = '2fa'");
        $ultimo->execute([':id' => $usuario['id_usuario']]);
        $segundos = $ultimo->fetchColumn();
        if ($reenvio && $segundos !== null && (int) $segundos < self::SEGUNDOS_ENTRE_ENVIOS) {
            throw new ValidacionException('Espera ' . (self::SEGUNDOS_ENTRE_ENVIOS - (int) $segundos) . ' segundos para pedir otro código.');
        }

        $codigo = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $db->prepare("UPDATE tokens SET usado = 1 WHERE id_usuario = :id AND codigo = '2fa' AND usado = 0")->execute([':id' => $usuario['id_usuario']]);
        $db->prepare(
            "INSERT INTO tokens (id_usuario, codigo, token, expira, usado, fecha_creacion)
             VALUES (:id, '2fa', :token, DATE_ADD(NOW(), INTERVAL " . (int) DOSFA_MINUTOS . ' MINUTE), 0, NOW())'
        )->execute([':id' => $usuario['id_usuario'], ':token' => hash('sha256', $codigo)]);

        $minutos = DOSFA_MINUTOS;
        $nombre = htmlspecialchars($usuario['nombre_usuario'], ENT_QUOTES, 'UTF-8');
        Correo::enviar(
            $usuario['email_usuario'],
            "Código de acceso $codigo · " . APP_NOMBRE,
            "<p>Hola, $nombre.</p><p>Tu código para entrar al sistema es:</p><p style=\"font-size:28px;font-weight:700;letter-spacing:6px\">$codigo</p>"
                . "<p>Vence en $minutos minutos. Si no fuiste tú, cambia tu contraseña de inmediato.</p>",
            "Hola, {$usuario['nombre_usuario']}.\n\nTu código para entrar al sistema es: $codigo\n\nVence en $minutos minutos. Si no fuiste tú, cambia tu contraseña de inmediato."
        );
    }

    /** true si el código es el vigente; lo deja usado para que no sirva dos veces. */
    public function verificar(int $idUsuario, string $codigo): bool
    {
        if (!preg_match('/^\d{6}$/', $codigo)) {
            return false;
        }
        $stmt = $this->seguridad()->prepare(
            "UPDATE tokens SET usado = 1
             WHERE id_usuario = :id AND codigo = '2fa' AND usado = 0 AND expira > NOW() AND token = :token"
        );
        $stmt->execute([':id' => $idUsuario, ':token' => hash('sha256', $codigo)]);

        return $stmt->rowCount() === 1;
    }
}
