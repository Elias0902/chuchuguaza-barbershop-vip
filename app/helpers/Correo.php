<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use Throwable;

/**
 * Envío de correos con PHPMailer por SMTP.
 * Si SMTP no está configurado (desarrollo), el correo se guarda como .eml en storage/correos.
 */
final class Correo
{
    public static function enviar(string $para, string $asunto, string $html, string $texto): bool
    {
        if (SMTP_HOST === '') {
            return self::guardarLocal($para, $asunto, $texto);
        }

        try {
            $correo = new PHPMailer(true);
            $correo->isSMTP();
            $correo->Host = SMTP_HOST;
            $correo->Port = SMTP_PUERTO;
            $correo->SMTPAuth = SMTP_USUARIO !== '';
            $correo->Username = SMTP_USUARIO;
            $correo->Password = SMTP_CLAVE;
            $correo->SMTPSecure = SMTP_SEGURIDAD;
            $correo->CharSet = PHPMailer::CHARSET_UTF8;
            $correo->setFrom(CORREO_REMITENTE, APP_NOMBRE);
            $correo->addAddress($para);
            $correo->Subject = $asunto;
            $correo->isHTML();
            $correo->Body = $html;
            $correo->AltBody = $texto;
            $correo->send();

            return true;
        } catch (Throwable $e) {
            error_log('No se pudo enviar el correo: ' . $e->getMessage());

            return false;
        }
    }

    private static function guardarLocal(string $para, string $asunto, string $texto): bool
    {
        $carpeta = dirname(__DIR__, 2) . '/storage/correos';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            return false;
        }
        $contenido = "To: $para\r\nSubject: $asunto\r\nDate: " . date(DATE_RFC2822) . "\r\nContent-Type: text/plain; charset=UTF-8\r\n\r\n$texto\r\n";

        return file_put_contents($carpeta . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.eml', $contenido) !== false;
    }
}
