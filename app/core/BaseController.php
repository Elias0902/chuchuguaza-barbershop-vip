<?php

namespace App\Core;

use App\Models\Buzon;
use RuntimeException;
use Throwable;

/**
 * Controlador base.
 *
 * Abstracción: el FrontController solo conoce este tipo y llama ejecutar();
 * cada controlador hijo decide qué hacer con la acción (polimorfismo).
 */
abstract class BaseController
{
    /** Punto de entrada que llama el FrontController con el parámetro "action". */
    abstract public function ejecutar(string $accion): void;

    /** Las rutas internas exigen sesión; el autenticador lo reescribe. */
    public function requiereSesion(): bool
    {
        return true;
    }

    /** Ruta del módulo cuyos permisos aplican; null = basta con tener sesión (inicio, perfil). */
    public function moduloProtegido(): ?string
    {
        return null;
    }

    /** Permiso que exige la acción: consultar, registrar, modificar o eliminar. */
    public function permisoRequerido(string $accion): string
    {
        return 'consultar';
    }

    /**
     * Muestra una vista de app/views. A las vistas del panel les llegan siempre
     * el menú permitido, los permisos, el usuario en sesión y el token CSRF.
     */
    protected function render(string $vista, array $datos = []): void
    {
        if (!preg_match('/\A[a-zA-Z0-9_-]+(\/[a-zA-Z0-9_-]+)?\z/', $vista)) {
            throw new RuntimeException('Nombre de vista inválido.');
        }
        $ruta = dirname(__DIR__) . '/views/' . $vista . '.php';
        if (!is_file($ruta)) {
            throw new RuntimeException("No existe la vista $vista.");
        }

        $autorizacion = new Autorizacion();
        $permisos = $autorizacion->permisosUsuario();
        $datos += [
            'menu' => $autorizacion->menuPermitido(),
            'permisosSesion' => $permisos,
            'sugerenciasNuevas' => isset($permisos['buzon']) ? $this->sugerenciasNuevas() : 0,
            'usuarioSesion' => $this->usuario(),
            'csrf' => $this->tokenCsrf(),
            'activePage' => '',
        ];
        extract($datos, EXTR_SKIP);
        require $ruta;
    }

    protected function responderJson(array $datos, int $codigo = 200): never
    {
        http_response_code($codigo);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit();
    }

    protected function redirigir(string $url): never
    {
        header('Location: ' . $url);
        exit();
    }

    protected function usuario(): ?array
    {
        return $_SESSION['s_usuario'] ?? null;
    }

    protected function idUsuario(): int
    {
        return (int) ($_SESSION['s_usuario']['id_usuario'] ?? 0);
    }

    /** ['clientes' => ['consultar', ...], ...] del usuario en sesión. */
    protected function permisosSesion(): array
    {
        return (new Autorizacion())->permisosUsuario();
    }

    /**
     * El buzón se alimenta solo: al mostrar una página se revisa si ya toca un análisis
     * (como máximo cada pocas horas) y se cuenta lo nuevo para el ícono de la barra superior.
     */
    private function sugerenciasNuevas(): int
    {
        try {
            $buzon = new Buzon();
            $buzon->analizarSiCorresponde();

            return $buzon->nuevas();
        } catch (Throwable $e) {
            error_log('No se pudo revisar el buzón de sugerencias: ' . $e->getMessage());

            return 0;
        }
    }

    /** Token CSRF único del panel; lo usan los formularios y las peticiones fetch. */
    protected function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf_panel'])) {
            $_SESSION['csrf_panel'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_panel'];
    }

    protected function csrfValido(?string $token): bool
    {
        return !empty($_SESSION['csrf_panel']) && is_string($token) && hash_equals($_SESSION['csrf_panel'], $token);
    }

    /** Exige POST con token CSRF (cabecera X-CSRF-Token o campo csrf_token). */
    protected function exigirPostSeguro(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->responderJson(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
        }
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? null);
        if (!$this->csrfValido($token)) {
            $this->responderJson(['ok' => false, 'mensaje' => 'La sesión del formulario venció. Recarga la página.'], 419);
        }
    }

    /** Lee el cuerpo JSON de una petición fetch. */
    protected function entradaJson(): array
    {
        $cuerpo = (string) file_get_contents('php://input');
        if (trim($cuerpo) === '') {
            return [];
        }
        $entrada = json_decode($cuerpo, true);
        if (!is_array($entrada)) {
            throw new ValidacionException('Los datos enviados no tienen un formato válido.');
        }

        return $entrada;
    }
}
