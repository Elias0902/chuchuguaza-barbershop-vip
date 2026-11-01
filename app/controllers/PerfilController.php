<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\ValidacionException;
use App\Models\Perfil;
use finfo;
use Throwable;

/**
 * Mi perfil: información de la cuenta, cambio de foto, datos y contraseña.
 */
class PerfilController extends BaseController
{
    private const CARPETA_FOTOS = 'assets/img/perfiles';
    private const TAMANO_MAXIMO = 2 * 1024 * 1024;
    private const TIPOS_PERMITIDOS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    private readonly Perfil $modelo;

    public function __construct()
    {
        $this->modelo = new Perfil();
    }

    public function ejecutar(string $accion): void
    {
        try {
            match ($accion) {
                '' => $this->index(),
                'datos' => $this->actualizarDatos(),
                'clave' => $this->cambiarClave(),
                'foto' => $this->cambiarFoto(),
                default => $this->responderJson(['ok' => false, 'mensaje' => 'La acción solicitada no existe.'], 404),
            };
        } catch (ValidacionException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage(), 'campo' => $e->campo()], 422);
        } catch (Throwable $e) {
            error_log('Error en Mi perfil: ' . $e->getMessage());
            $this->responderJson(['ok' => false, 'mensaje' => 'No se pudo completar la operación. Intenta de nuevo.'], 500);
        }
    }

    private function index(): void
    {
        $this->render('perfilView', [
            'activePage' => 'perfil',
            'perfil' => $this->modelo->obtener($this->idUsuario()),
        ]);
    }

    private function actualizarDatos(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $this->modelo->actualizarDatos($this->idUsuario(), $entrada['nombre_usuario'] ?? '', $entrada['email_usuario'] ?? '');

        $perfil = $this->modelo->obtener($this->idUsuario());
        $_SESSION['s_usuario']['nombre_usuario'] = $perfil['nombre_usuario'];
        $_SESSION['s_usuario']['email_usuario'] = $perfil['email_usuario'];
        $this->responderJson(['ok' => true, 'mensaje' => 'Datos actualizados.', 'datos' => $perfil]);
    }

    private function cambiarClave(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $this->modelo->cambiarClave(
            $this->idUsuario(),
            $entrada['clave_actual'] ?? '',
            $entrada['clave_nueva'] ?? '',
            $entrada['clave_confirmacion'] ?? ''
        );
        $this->responderJson(['ok' => true, 'mensaje' => 'Contraseña actualizada.']);
    }

    /** Recibe la foto (multipart/form-data), la valida y reemplaza la anterior. */
    private function cambiarFoto(): void
    {
        $this->exigirPostSeguro();
        $archivo = $_FILES['foto'] ?? null;
        if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($archivo['tmp_name'])) {
            throw new ValidacionException('Selecciona una imagen para subir.', 'foto');
        }
        if ($archivo['size'] > self::TAMANO_MAXIMO) {
            throw new ValidacionException('La imagen no puede pesar más de 2 MB.', 'foto');
        }

        $tipo = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
        if (!isset(self::TIPOS_PERMITIDOS[$tipo]) || @getimagesize($archivo['tmp_name']) === false) {
            throw new ValidacionException('La foto debe ser una imagen JPG, PNG o WEBP.', 'foto');
        }

        $id = $this->idUsuario();
        $nombre = sprintf('perfil_%d_%d.%s', $id, time(), self::TIPOS_PERMITIDOS[$tipo]);
        $raiz = dirname(__DIR__, 2);
        if (!move_uploaded_file($archivo['tmp_name'], "$raiz/" . self::CARPETA_FOTOS . "/$nombre")) {
            throw new ValidacionException('No se pudo guardar la imagen en el servidor.', 'foto');
        }

        $ruta = self::CARPETA_FOTOS . '/' . $nombre;
        $anterior = $this->modelo->cambiarAvatar($id, $ruta);
        $this->borrarFotoAnterior($anterior, $id);
        $_SESSION['s_usuario']['img_usuario'] = $ruta;

        $this->responderJson(['ok' => true, 'mensaje' => 'Foto de perfil actualizada.', 'datos' => ['avatar' => $ruta]]);
    }

    /** Solo borra fotos subidas por este mismo usuario, nunca la imagen predeterminada. */
    private function borrarFotoAnterior(string $anterior, int $idUsuario): void
    {
        $patron = '#\A' . preg_quote(self::CARPETA_FOTOS, '#') . '/perfil_' . $idUsuario . '_\d+\.(jpg|png|webp|jpeg)\z#';
        if (preg_match($patron, $anterior)) {
            $archivo = dirname(__DIR__, 2) . '/' . $anterior;
            if (is_file($archivo)) {
                @unlink($archivo);
            }
        }
    }
}
