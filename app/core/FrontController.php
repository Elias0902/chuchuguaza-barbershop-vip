<?php

namespace App\Core;

/**
 * Controlador frontal: toda petición entra por index.php y se despacha aquí.
 *
 * Lee "url" para elegir el controlador (config/route.php) y "action" para la acción.
 * Valida la sesión antes de crear el controlador y responde 404 si la ruta no existe.
 */
final class FrontController
{
    private const LANDING = 'landing-chuchuguaza/index.html';

    /** @param array<string, class-string<BaseController>> $rutas */
    public function __construct(private readonly array $rutas)
    {
    }

    public function despachar(): void
    {
        $url = (string) ($_GET['url'] ?? '');
        $accion = (string) ($_GET['action'] ?? '');
        $autenticado = !empty($_SESSION['s_usuario']['id_usuario']);

        if ($url === '') {
            $this->redirigir($autenticado ? 'index.php?url=dashboard' : self::LANDING);
        }
        if ($url === 'landing') {
            $this->redirigir(self::LANDING);
        }

        $clase = $this->rutas[$url] ?? null;
        if ($clase === null || !is_subclass_of($clase, BaseController::class)) {
            $this->error(404);
        }

        $controlador = new $clase();
        if ($controlador->requiereSesion() && !$autenticado) {
            $esPeticionFetch = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
            if ($accion !== '' && $esPeticionFetch) {
                http_response_code(401);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'mensaje' => 'Tu sesión expiró. Vuelve a iniciar sesión.'], JSON_UNESCAPED_UNICODE);
                exit();
            }
            $this->redirigir('index.php?url=autenticator&action=acceso_interno');
        }

        $modulo = $controlador->moduloProtegido();
        if ($modulo !== null) {
            $permiso = $controlador->permisoRequerido($accion);
            if (!(new Autorizacion())->puede($modulo, $permiso)) {
                $this->prohibido($accion, $permiso);
            }
        }

        $controlador->ejecutar($accion);
    }

    /** Sin permiso: JSON 403 para las peticiones fetch y la página 403 para el resto. */
    private function prohibido(string $accion, string $permiso): never
    {
        if ($accion !== '' && str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'mensaje' => "Tu rol no tiene permiso para $permiso en este módulo."], JSON_UNESCAPED_UNICODE);
            exit();
        }
        $this->error(403);
    }

    private function error(int $codigo): never
    {
        http_response_code($codigo);
        require dirname(__DIR__) . "/views/error/$codigo.php";
        exit();
    }

    private function redirigir(string $url): never
    {
        header('Location: ' . $url);
        exit();
    }
}
