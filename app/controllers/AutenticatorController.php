<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\ValidacionException;
use App\Models\Autenticator;
use App\Models\RecuperacionClave;
use Throwable;

/**
 * Acceso al sistema interno: formulario, inicio y cierre de sesión.
 */
class AutenticatorController extends BaseController
{
    private const RUTA_ACCESO = 'index.php?url=autenticator&action=acceso_interno';

    /** Es la única ruta del panel que se puede abrir sin sesión. */
    public function requiereSesion(): bool
    {
        return false;
    }

    public function ejecutar(string $accion): void
    {
        match ($accion) {
            '', 'acceso_interno' => $this->mostrarAcceso(),
            'ingresar_interno' => $this->iniciarSesion(),
            'cerrar_interno' => $this->cerrarSesion(),
            'recuperar' => $this->mostrarRecuperacion('solicitar'),
            'enviar_recuperacion' => $this->enviarRecuperacion(),
            'restablecer' => $this->mostrarRestablecer(),
            'guardar_clave' => $this->guardarClave(),
            default => $this->redirigir(self::RUTA_ACCESO),
        };
    }

    private function mostrarAcceso(): void
    {
        if ($this->usuario() !== null) {
            $this->redirigir('index.php?url=dashboard');
        }

        $mensajeAcceso = $_SESSION['mensaje_acceso_interno'] ?? '';
        unset($_SESSION['mensaje_acceso_interno']);
        $this->render('accesoInterno', ['mensajeAcceso' => $mensajeAcceso]);
    }

    private function iniciarSesion(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir(self::RUTA_ACCESO);
        }
        if (!$this->csrfValido($_POST['csrf_token'] ?? null)) {
            $this->volverConMensaje('La sesión del formulario venció. Intenta de nuevo.');
        }

        $usuario = trim((string) ($_POST['username'] ?? ''));
        $clave = (string) ($_POST['password'] ?? '');
        if ($usuario === '' || strlen($usuario) > 100 || $clave === '' || strlen($clave) > 4096) {
            $this->volverConMensaje('Escribe tu usuario y contraseña.');
        }

        try {
            $modelo = new Autenticator();
            $minutosBloqueo = $modelo->verificarBloqueoLogin($usuario);
            if ($minutosBloqueo > 0) {
                $this->volverConMensaje("Cuenta temporalmente bloqueada. Intenta en $minutosBloqueo minuto(s).");
            }

            $datos = $modelo->autenticarInterno($usuario, $clave);
            if ($datos === null) {
                $this->volverConMensaje($modelo->registrarIntentoFallido($usuario) > 0
                    ? 'Demasiados intentos. Cuenta bloqueada temporalmente.'
                    : 'Usuario o contraseña incorrectos, o cuenta inactiva.');
            }

            $modelo->registrarAcceso((int) $datos['id_usuario'], $datos['nombre_usuario']);
        } catch (Throwable $e) {
            error_log('Error de acceso interno: ' . $e->getMessage());
            $this->volverConMensaje('No se pudo completar el acceso. Revisa la conexión a la base de datos e intenta de nuevo.');
        }

        session_regenerate_id(true);
        $_SESSION['s_usuario'] = [
            'id_usuario' => (int) $datos['id_usuario'],
            'nombre_usuario' => $datos['nombre_usuario'],
            'email_usuario' => $datos['email_usuario'],
            'id_rol_usuario' => (int) $datos['id_rol_usuario'],
            'rol_usuario' => $datos['nombre_rol'],
            'img_usuario' => $datos['avatar'],
        ];
        $_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
        $this->redirigir('index.php?url=dashboard');
    }

    private function cerrarSesion(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$this->csrfValido($_POST['csrf_token'] ?? null)) {
            $this->redirigir($this->usuario() !== null ? 'index.php?url=dashboard' : self::RUTA_ACCESO);
        }

        $usuario = $this->usuario();
        if ($usuario !== null) {
            (new Autenticator())->registrarCierreSesion((int) $usuario['id_usuario'], (string) $usuario['nombre_usuario']);
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
        $this->redirigir('landing-chuchuguaza/index.html');
    }

    private function mostrarRecuperacion(string $paso, array $datos = []): void
    {
        if ($this->usuario() !== null) {
            $this->redirigir('index.php?url=dashboard');
        }
        $this->render('recuperarClave', $datos + ['paso' => $paso, 'mensaje' => '']);
    }

    /** Siempre responde lo mismo para no revelar qué usuarios o correos existen. */
    private function enviarRecuperacion(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir('index.php?url=autenticator&action=recuperar');
        }
        if (!$this->csrfValido($_POST['csrf_token'] ?? null)) {
            $this->mostrarRecuperacion('solicitar', ['mensaje' => 'La sesión del formulario venció. Intenta de nuevo.']);
            return;
        }

        $identificador = trim((string) ($_POST['identificador'] ?? ''));
        if ($identificador !== '' && strlen($identificador) <= 100) {
            try {
                (new RecuperacionClave())->solicitar($identificador);
            } catch (Throwable $e) {
                error_log('Error al solicitar recuperación: ' . $e->getMessage());
            }
        }
        $this->mostrarRecuperacion('enviado');
    }

    private function mostrarRestablecer(): void
    {
        $token = (string) ($_GET['token'] ?? '');
        $usuario = (new RecuperacionClave())->usuarioDelToken($token);
        if ($usuario === null) {
            $this->mostrarRecuperacion('invalido');
            return;
        }
        $this->mostrarRecuperacion('restablecer', ['token' => $token, 'nombreUsuario' => $usuario['nombre_usuario']]);
    }

    private function guardarClave(): void
    {
        $token = (string) ($_POST['token'] ?? '');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirigir(self::RUTA_ACCESO);
        }

        $modelo = new RecuperacionClave();
        try {
            if (!$this->csrfValido($_POST['csrf_token'] ?? null)) {
                throw new ValidacionException('La sesión del formulario venció. Intenta de nuevo.');
            }
            $modelo->restablecer($token, (string) ($_POST['clave'] ?? ''), (string) ($_POST['confirmacion'] ?? ''));
        } catch (ValidacionException $e) {
            $usuario = $modelo->usuarioDelToken($token);
            if ($usuario === null) {
                $this->mostrarRecuperacion('invalido');
                return;
            }
            $this->mostrarRecuperacion('restablecer', ['token' => $token, 'nombreUsuario' => $usuario['nombre_usuario'], 'mensaje' => $e->getMessage()]);
            return;
        }

        $this->volverConMensaje('Tu contraseña se cambió. Ya puedes iniciar sesión.');
    }

    private function volverConMensaje(string $mensaje): never
    {
        $_SESSION['mensaje_acceso_interno'] = $mensaje;
        $this->redirigir(self::RUTA_ACCESO);
    }
}
