<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;
use App\Models\Buzon;
use Throwable;

/**
 * Buzón de sugerencias del sistema:
 *   (vacía)    GET   bandeja con filtro ?grupo=pendientes|Aplicada|Descartada|Resuelta|todas
 *   analizar   POST  ejecuta el análisis en este momento
 *   estado     POST  marca una sugerencia como Leída, Aplicada, Descartada o la reabre
 */
class BuzonController extends BaseController
{
    private const GRUPOS = ['pendientes', 'Aplicada', 'Descartada', 'Resuelta', 'todas'];

    private readonly Buzon $buzon;

    public function __construct()
    {
        $this->buzon = new Buzon();
    }

    public function moduloProtegido(): ?string
    {
        return 'buzon';
    }

    public function permisoRequerido(string $accion): string
    {
        return $accion === 'estado' ? 'modificar' : 'consultar';
    }

    public function ejecutar(string $accion): void
    {
        try {
            match ($accion) {
                '' => $this->index(),
                'analizar' => $this->analizar(),
                'estado' => $this->cambiarEstado(),
                default => $this->responderJson(['ok' => false, 'mensaje' => 'La acción solicitada no existe.'], 404),
            };
        } catch (ValidacionException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage()], 422);
        } catch (RegistroNoEncontradoException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (Throwable $e) {
            error_log('Error en el buzón de sugerencias: ' . $e->getMessage());
            $this->responderJson(['ok' => false, 'mensaje' => 'No se pudo completar la operación. Intenta de nuevo.'], 500);
        }
    }

    private function index(): void
    {
        $grupo = (string) ($_GET['grupo'] ?? 'pendientes');
        if (!in_array($grupo, self::GRUPOS, true)) {
            $grupo = 'pendientes';
        }

        $this->render('buzonView', [
            'activePage' => 'buzon',
            'grupo' => $grupo,
            'sugerencias' => $this->buzon->listar($grupo),
            'conteo' => $this->buzon->contarPorEstado(),
            'ultimaEjecucion' => $this->buzon->ultimaEjecucion(),
            'puedeModificar' => in_array('modificar', $this->permisosSesion()['buzon'] ?? [], true),
        ]);
    }

    private function analizar(): void
    {
        $this->exigirPostSeguro();
        $resultado = $this->buzon->analizar('Manual', $this->idUsuario());
        $this->responderJson([
            'ok' => true,
            'mensaje' => "Análisis listo: {$resultado['nuevas']} sugerencias nuevas y {$resultado['resueltas']} resueltas.",
        ]);
    }

    private function cambiarEstado(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $id = filter_var($entrada['id'] ?? null, FILTER_VALIDATE_INT);
        if ($id === false || $id < 1) {
            throw new ValidacionException('La sugerencia indicada no es válida.');
        }
        $this->buzon->cambiarEstado($id, (string) ($entrada['estado'] ?? ''));
        $this->responderJson(['ok' => true, 'mensaje' => 'Sugerencia actualizada.']);
    }
}
