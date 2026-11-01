<?php

namespace App\Core;

use App\Helpers\ReportePdf;
use PDOException;
use Throwable;

/**
 * Controlador base de los módulos con Consultar, Registrar, Modificar y Eliminación lógica.
 *
 * Sin "action" muestra la vista del módulo; el resto de acciones responden JSON:
 *   consultar  (GET)  listado completo de la tabla
 *   obtener    (GET)  un registro por id
 *   registrar  (POST) inserta un registro nuevo
 *   modificar  (POST) actualiza un registro activo
 *   eliminar   (POST) eliminación lógica (status = 0)
 *   restaurar  (POST) vuelve a activar un registro eliminado
 *   reporte    (GET)  descarga el listado en PDF
 * Cada módulo puede agregar acciones propias reescribiendo accionPersonalizada().
 */
abstract class CrudController extends BaseController
{
    protected readonly CrudModel $modelo;

    public function __construct()
    {
        $this->modelo = $this->crearModelo();
    }

    /** Clave de la ruta (index.php?url=...). Debe existir en config/modulos.php. */
    abstract protected function ruta(): string;

    /** Modelo del módulo (cada controlador devuelve el suyo: polimorfismo). */
    abstract protected function crearModelo(): CrudModel;

    /**
     * Columnas de la tabla: campo, etiqueta, formato (texto, moneda, entero, porcentaje,
     * fecha, fechahora, insignia, sino), sub (segunda línea) y colores de insignia.
     */
    abstract protected function columnas(): array;

    /**
     * Configuración adicional de la vista: acciones propias, opciones extra para selects,
     * valores por defecto, condición de edición (edicionSi) o soloConsulta.
     */
    protected function configuracionVista(): array
    {
        return [];
    }

    public function moduloProtegido(): ?string
    {
        return $this->ruta();
    }

    /** Ver, consultar, obtener y reporte → consultar; eliminar y restaurar → eliminar. */
    public function permisoRequerido(string $accion): string
    {
        return match ($accion) {
            '', 'consultar', 'obtener', 'reporte' => 'consultar',
            'registrar' => 'registrar',
            'modificar' => 'modificar',
            'eliminar', 'restaurar' => 'eliminar',
            default => $this->permisoAccionPropia($accion),
        };
    }

    /** Las acciones propias exigen el permiso que declaren en configuracionVista() (por defecto, modificar). */
    protected function permisoAccionPropia(string $accion): string
    {
        foreach ($this->configuracionVista()['acciones'] ?? [] as $definicion) {
            if ($definicion['clave'] === $accion) {
                return $definicion['permiso'] ?? 'modificar';
            }
        }

        return 'modificar';
    }

    public function ejecutar(string $accion): void
    {
        $escritura = ['registrar', 'modificar', 'eliminar', 'restaurar'];
        if (($this->configuracionVista()['soloConsulta'] ?? false) && in_array($accion, $escritura, true)) {
            $this->responderJson(['ok' => false, 'mensaje' => 'Este módulo es de solo consulta.'], 403);
        }

        try {
            match ($accion) {
                '' => $this->index(),
                'consultar' => $this->responderJson(['ok' => true, 'datos' => $this->modelo->consultar()]),
                'obtener' => $this->obtener(),
                'registrar' => $this->registrar(),
                'modificar' => $this->modificar(),
                'eliminar' => $this->eliminar(),
                'restaurar' => $this->restaurar(),
                'reporte' => $this->reporte(),
                default => $this->accionPersonalizada($accion),
            };
        } catch (ValidacionException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage(), 'campo' => $e->campo()], 422);
        } catch (RegistroNoEncontradoException $e) {
            $this->responderJson(['ok' => false, 'mensaje' => $e->getMessage()], 404);
        } catch (PDOException $e) {
            error_log('Error de base de datos en ' . static::class . ': ' . $e->getMessage());
            $mensaje = $e->getCode() === '23000'
                ? 'El registro está relacionado con otros datos o ya existe uno igual.'
                : 'No se pudo completar la operación en la base de datos.';
            $this->responderJson(['ok' => false, 'mensaje' => $mensaje], 500);
        } catch (Throwable $e) {
            error_log('Error inesperado en ' . static::class . ': ' . $e->getMessage());
            $this->responderJson(['ok' => false, 'mensaje' => 'Ocurrió un error inesperado. Intenta de nuevo.'], 500);
        }
    }

    // ------------------------------------------------------------------
    // Acciones
    // ------------------------------------------------------------------

    protected function index(): void
    {
        $info = $this->infoModulo();
        $config = $this->configuracionVista();
        $soloConsulta = $config['soloConsulta'] ?? false;
        $autorizacion = new Autorizacion();
        $permisos = [];
        foreach (array_keys(Autorizacion::PERMISOS) as $permiso) {
            $permisos[$permiso] = !$soloConsulta || $permiso === 'consultar'
                ? $autorizacion->puede($this->ruta(), $permiso)
                : false;
        }

        $this->render('moduloView', [
            'activePage' => $this->ruta(),
            'modulo' => [
                'ruta' => $this->ruta(),
                'url' => 'index.php?url=' . $this->ruta(),
                'titulo' => $info['titulo'],
                'descripcion' => $info['descripcion'],
                'grupo' => $info['grupo'],
                'icono' => $info['icono'],
                'singular' => $this->modelo->singular(),
                'femenino' => $this->modelo->femenino(),
                'clave' => $this->modelo->clave(),
                'tieneEstado' => $this->modelo->tieneEstado(),
                'soloConsulta' => $soloConsulta,
                'columnas' => $this->columnas(),
                'campos' => $soloConsulta ? [] : $this->modelo->camposParaVista(),
                'opciones' => $soloConsulta ? [] : $this->modelo->opcionesFormulario() + ($config['opciones'] ?? []),
                'defectos' => $config['defectos'] ?? [],
                'acciones' => $config['acciones'] ?? [],
                'edicionSi' => $config['edicionSi'] ?? null,
                'permisos' => $permisos,
                'scripts' => $config['scripts'] ?? [],
                'csrf' => $this->tokenCsrf(),
                'moneda' => MONEDA_SIMBOLO,
            ],
        ]);
    }

    protected function obtener(): void
    {
        $id = $this->modelo->normalizarId($_GET['id'] ?? null);
        $registro = $this->modelo->obtenerOFallar($id);
        $this->responderJson(['ok' => true, 'datos' => $this->modelo->paraVista($registro)]);
    }

    protected function registrar(): void
    {
        $this->exigirPostSeguro();
        $this->modelo->registrar($this->entradaJson());
        $this->responderJson(['ok' => true, 'mensaje' => $this->mensaje('registrado', 'registrada') . ' correctamente.']);
    }

    protected function modificar(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $this->modelo->modificar($this->modelo->normalizarId($entrada['id'] ?? null), $entrada);
        $this->responderJson(['ok' => true, 'mensaje' => $this->mensaje('modificado', 'modificada') . ' correctamente.']);
    }

    protected function eliminar(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $this->modelo->eliminar($this->modelo->normalizarId($entrada['id'] ?? null));
        $this->responderJson([
            'ok' => true,
            'mensaje' => $this->mensaje('eliminado', 'eliminada') . '. Puedes restaurarl' . $this->modelo->genero('o', 'a') . ' desde «Eliminados».',
        ]);
    }

    protected function restaurar(): void
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $this->modelo->restaurar($this->modelo->normalizarId($entrada['id'] ?? null));
        $this->responderJson(['ok' => true, 'mensaje' => $this->mensaje('restaurado', 'restaurada') . ' correctamente.']);
    }

    /** Descarga el listado en PDF respetando el filtro de la vista (activos, eliminados o todos). */
    protected function reporte(): void
    {
        $filtro = (string) ($_GET['filtro'] ?? 'activos');
        $filas = $this->modelo->consultar();
        if ($this->modelo->tieneEstado() && $filtro !== 'todos') {
            $estado = $filtro === 'eliminados' ? 0 : 1;
            $filas = array_values(array_filter($filas, static fn(array $fila): bool => (int) $fila['status'] === $estado));
        }

        $info = $this->infoModulo();
        $subtitulo = match (true) {
            !$this->modelo->tieneEstado() => 'Listado completo',
            $filtro === 'eliminados' => 'Registros eliminados',
            $filtro === 'todos' => 'Todos los registros',
            default => 'Registros activos',
        };

        $pdf = new ReportePdf($info['titulo'], $subtitulo, (string) ($this->usuario()['nombre_usuario'] ?? ''));
        $pdf->tabla($this->columnasReporte(), $filas, $this->modelo->tieneEstado() && $filtro === 'todos');
        $pdf->descargar('reporte_' . $this->ruta() . '_' . date('Ymd_His') . '.pdf');
    }

    /** Columnas del PDF: por defecto las mismas de la tabla. */
    protected function columnasReporte(): array
    {
        return $this->columnas();
    }

    /** Acciones propias del módulo (cerrar caja, cobrar ticket...). */
    protected function accionPersonalizada(string $accion): void
    {
        $this->responderJson(['ok' => false, 'mensaje' => 'La acción solicitada no existe.'], 404);
    }

    // ------------------------------------------------------------------
    // Utilidades
    // ------------------------------------------------------------------

    /** "Cliente registrado", "Cita registrada"... según el género del módulo. */
    protected function mensaje(string $masculino, string $femenino): string
    {
        return ucfirst($this->modelo->singular()) . ' ' . $this->modelo->genero($masculino, $femenino);
    }

    /** Lee el id de una acción propia, validando POST y CSRF. */
    protected function entradaAccion(): array
    {
        $this->exigirPostSeguro();
        $entrada = $this->entradaJson();
        $entrada['id'] = $this->modelo->normalizarId($entrada['id'] ?? null);

        return $entrada;
    }

    private function infoModulo(): array
    {
        foreach (Autorizacion::menu() as $grupo) {
            if (isset($grupo['modulos'][$this->ruta()])) {
                return $grupo['modulos'][$this->ruta()] + ['grupo' => $grupo['titulo']];
            }
        }

        return ['titulo' => ucfirst($this->ruta()), 'descripcion' => '', 'icono' => 'fa-circle', 'grupo' => ''];
    }
}
