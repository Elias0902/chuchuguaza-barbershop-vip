<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;
use App\Models\Respaldo;
use Throwable;

/**
 * Respaldos de la base de datos:
 *   (vacía)    GET   lista de respaldos
 *   generar    POST  respalda las dos bases ahora (registrar)
 *   descargar  GET   descarga el archivo .sql (consultar)
 *   restaurar  POST  restaura una base desde un respaldo (modificar)
 */
class RespaldosController extends BaseController
{
    private readonly Respaldo $respaldo;

    public function __construct()
    {
        $this->respaldo = new Respaldo();
    }

    public function moduloProtegido(): ?string
    {
        return 'respaldos';
    }

    public function permisoRequerido(string $accion): string
    {
        return match ($accion) {
            'generar' => 'registrar',
            'restaurar' => 'modificar',
            default => 'consultar',
        };
    }

    public function ejecutar(string $accion): void
    {
        try {
            match ($accion) {
                '' => $this->index(),
                'generar' => $this->generar(),
                'descargar' => $this->descargar(),
                'restaurar' => $this->restaurar(),
                default => $this->responderJson(['ok' => false, 'mensaje' => 'La acción solicitada no existe.'], 404),
            };
        } catch (ValidacionException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (RegistroNoEncontradoException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            error_log('Error en respaldos: ' . $e->getMessage());
            $this->responderJson(['ok' => false, 'mensaje' => 'No se pudo completar la operación con los respaldos.'], 500);
        }
    }

    private function index(): void
    {
        $permisos = $this->permisosSesion()['respaldos'] ?? [];
        $this->render('respaldosView', [
            'activePage' => 'respaldos',
            'respaldos' => $this->respaldo->listar(),
            'ultimoAutomatico' => $this->respaldo->ultimoAutomatico(),
            'puedeGenerar' => in_array('registrar', $permisos, true),
            'puedeRestaurar' => in_array('modificar', $permisos, true),
        ]);
    }

    private function generar(): void
    {
        $this->exigirPostSeguro();
        $this->respaldo->generar('Manual', $this->idUsuario());
        $this->responderJson(['ok' => true, 'mensaje' => 'Respaldo creado de las bases de negocio y seguridad.']);
    }

    private function descargar(): void
    {
        $id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new ValidacionException('El respaldo indicado no es válido.');
        }
        [$respaldo, $ruta] = $this->respaldo->archivo($id);
        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($respaldo['nombre_archivo']) . '"');
        header('Content-Length: ' . filesize($ruta));
        header('X-Content-Type-Options: nosniff');
        readfile($ruta);
        exit();
    }

    private function restaurar(): void
    {
        $this->exigirPostSeguro();
        $id = filter_var($this->entradaJson()['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new ValidacionException('El respaldo indicado no es válido.');
        }
        $this->respaldo->restaurar($id, $this->idUsuario());
        $this->responderJson(['ok' => true, 'mensaje' => 'Base restaurada. Antes se guardó un respaldo del estado anterior por si necesitas deshacerlo.']);
    }
}
