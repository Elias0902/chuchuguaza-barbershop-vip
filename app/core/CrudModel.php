<?php

namespace App\Core;

use DateTime;
use PDO;
use Throwable;

/**
 * Modelo base de los módulos con Consultar, Registrar, Modificar y Eliminación lógica.
 *
 * Abstracción: cada modelo hijo solo declara su tabla, su clave y sus campos (campos()).
 * Herencia: toda la lógica común (validación, SQL preparado, eliminación lógica,
 * restauración y bitácora) vive aquí y la heredan los módulos.
 * Polimorfismo: los hijos reescriben sqlConsulta(), reglasNegocio(), antesDeEliminar()...
 * para agregar sus reglas sin cambiar el flujo general.
 *
 * Tipos de campo admitidos: texto, textoLargo, nombre, email, telefono, cedula, dias,
 * usuario, clave, entero, decimal, fecha, fechahora, opcion, relacion, booleano y detalle.
 */
abstract class CrudModel extends BaseModel implements CrudInterface
{
    /** Tabla de la base de datos que administra el módulo. */
    protected string $tabla;

    /** Columna de la clave primaria. */
    protected string $clave;

    /** false cuando la clave la escribe el usuario (por ejemplo, la cédula). */
    protected bool $claveAutoincremental = true;

    /** Conexion::NEGOCIO o Conexion::SEGURIDAD. */
    protected string $baseDatos = self::NEGOCIO;

    /** false para tablas sin columna status (solo consulta, sin eliminación lógica). */
    protected bool $tieneEstado = true;

    /** Nombre del módulo que se guarda en la bitácora. */
    protected string $modulo;

    /** Nombre en singular para los mensajes ("cliente", "barbero"...). */
    protected string $singular;

    /** true si el singular es femenino ("cita", "categoría") para concordar los mensajes. */
    protected bool $femenino = false;

    /** Columna que describe el registro en mensajes y bitácora. */
    protected ?string $campoDescriptivo = null;

    /** Columnas que nunca se envían a la vista (por ejemplo, contraseñas). */
    protected array $ocultos = [];

    /**
     * Definición de los campos del formulario y su validación.
     * La clave del arreglo es el nombre de la columna.
     */
    abstract public function campos(): array;

    // ------------------------------------------------------------------
    // Consultar
    // ------------------------------------------------------------------

    /** SQL del listado. Los módulos con relaciones lo reescriben con sus JOIN. */
    protected function sqlConsulta(): string
    {
        $orden = $this->tieneEstado ? 'status DESC, ' : '';

        return "SELECT * FROM `{$this->tabla}` ORDER BY {$orden}`{$this->clave}` DESC";
    }

    public function consultar(): array
    {
        $filas = $this->db()->query($this->sqlConsulta())->fetchAll();

        return array_map(fn(array $fila): array => $this->paraVista($fila), $filas);
    }

    public function obtener(int|string $id): ?array
    {
        $stmt = $this->db()->prepare("SELECT * FROM `{$this->tabla}` WHERE `{$this->clave}` = :id");
        $stmt->execute([':id' => $id]);
        $fila = $stmt->fetch();

        return $fila ?: null;
    }

    public function obtenerOFallar(int|string $id): array
    {
        $fila = $this->obtener($id);
        if ($fila === null) {
            throw new RegistroNoEncontradoException(
                ucfirst($this->genero('el', 'la')) . " {$this->singular} solicitad{$this->genero('o', 'a')} no existe."
            );
        }

        return $fila;
    }

    /** Quita las columnas sensibles antes de enviar un registro al navegador. */
    public function paraVista(array $fila): array
    {
        foreach ($this->ocultos as $columna) {
            unset($fila[$columna]);
        }

        return $fila;
    }

    // ------------------------------------------------------------------
    // Registrar, modificar, eliminar (lógico) y restaurar
    // ------------------------------------------------------------------

    public function registrar(array $entrada): int|string
    {
        $datos = $this->validar($entrada, null);
        $this->reglasNegocio($datos, $entrada, null);
        if ($this->tieneEstado) {
            $datos['status'] = 1;
        }

        $id = $this->transaccion(function (PDO $db) use ($datos) {
            $columnas = $this->soloColumnas($datos);
            $nombres = array_keys($columnas);
            $sql = sprintf(
                'INSERT INTO `%s` (%s) VALUES (%s)',
                $this->tabla,
                implode(', ', array_map(static fn(string $c): string => "`$c`", $nombres)),
                implode(', ', array_map(static fn(string $c): string => ":$c", $nombres))
            );
            $db->prepare($sql)->execute($this->parametros($columnas));

            $id = $this->claveAutoincremental ? (int) $db->lastInsertId() : $columnas[$this->clave];
            $this->despuesDeGuardar($db, $id, $datos, null);

            return $id;
        });

        $this->bitacora('Registrar', 'Registró ' . $this->describir($this->obtener($id) ?? $datos + [$this->clave => $id]) . '.');

        return $id;
    }

    public function modificar(int|string $id, array $entrada): void
    {
        $actual = $this->obtenerOFallar($id);
        if ($this->tieneEstado && (int) $actual['status'] !== 1) {
            throw new ValidacionException(
                "No puedes modificar {$this->genero('un', 'una')} {$this->singular} eliminad{$this->genero('o', 'a')}. "
                . "Restáural{$this->genero('o', 'a')} primero."
            );
        }
        $this->antesDeModificar($actual);

        $datos = $this->validar($entrada, $actual);
        $this->reglasNegocio($datos, $entrada, $actual);

        $this->transaccion(function (PDO $db) use ($id, $datos, $actual): void {
            $columnas = $this->soloColumnas($datos);
            unset($columnas[$this->clave], $columnas['status']);
            if ($columnas) {
                $asignaciones = implode(', ', array_map(static fn(string $c): string => "`$c` = :$c", array_keys($columnas)));
                $stmt = $db->prepare("UPDATE `{$this->tabla}` SET $asignaciones WHERE `{$this->clave}` = :id_registro");
                $stmt->execute($this->parametros($columnas) + [':id_registro' => $id]);
            }
            $this->despuesDeGuardar($db, $id, $datos, $actual);
        });

        $this->bitacora('Modificar', 'Modificó ' . $this->describir($this->obtener($id) ?? $actual) . '.');
    }

    /** Eliminación lógica: el registro queda con status = 0 y se puede restaurar. */
    public function eliminar(int|string $id): void
    {
        if (!$this->tieneEstado) {
            throw new ValidacionException('Este módulo no permite eliminar registros.');
        }
        $actual = $this->obtenerOFallar($id);
        if ((int) $actual['status'] === 0) {
            throw new ValidacionException(ucfirst($this->genero('el', 'la')) . " {$this->singular} ya está eliminad{$this->genero('o', 'a')}.");
        }

        $this->transaccion(function (PDO $db) use ($id, $actual): void {
            $this->antesDeEliminar($db, $actual);
            $db->prepare("UPDATE `{$this->tabla}` SET status = 0 WHERE `{$this->clave}` = :id")->execute([':id' => $id]);
            $this->despuesDeEliminar($db, $actual);
        });

        $this->bitacora('Eliminar', 'Eliminó (eliminación lógica) ' . $this->describir($actual) . '.');
    }

    public function restaurar(int|string $id): void
    {
        if (!$this->tieneEstado) {
            throw new ValidacionException('Este módulo no permite restaurar registros.');
        }
        $actual = $this->obtenerOFallar($id);
        if ((int) $actual['status'] === 1) {
            throw new ValidacionException(ucfirst($this->genero('el', 'la')) . " {$this->singular} ya está activ{$this->genero('o', 'a')}.");
        }

        $this->transaccion(function (PDO $db) use ($id, $actual): void {
            $this->antesDeRestaurar($db, $actual);
            $db->prepare("UPDATE `{$this->tabla}` SET status = 1 WHERE `{$this->clave}` = :id")->execute([':id' => $id]);
            $this->despuesDeRestaurar($db, $actual);
        });

        $this->bitacora('Restaurar', 'Restauró ' . $this->describir($actual) . '.');
    }

    // ------------------------------------------------------------------
    // Puntos de extensión (polimorfismo): cada módulo reescribe los que necesita
    // ------------------------------------------------------------------

    /** Validaciones propias del módulo y cálculo de columnas derivadas. */
    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
    }

    /** Se ejecuta antes de validar una modificación (por ejemplo, bloquear cajas cerradas). */
    protected function antesDeModificar(array $actual): void
    {
    }

    /** Se ejecuta dentro de la transacción después del INSERT o del UPDATE. */
    protected function despuesDeGuardar(PDO $db, int|string $id, array $datos, ?array $actual): void
    {
    }

    /** Se ejecuta dentro de la transacción antes de marcar status = 0. */
    protected function antesDeEliminar(PDO $db, array $actual): void
    {
    }

    /** Se ejecuta dentro de la transacción después de marcar status = 0. */
    protected function despuesDeEliminar(PDO $db, array $actual): void
    {
    }

    /** Se ejecuta dentro de la transacción antes de marcar status = 1. */
    protected function antesDeRestaurar(PDO $db, array $actual): void
    {
    }

    /** Se ejecuta dentro de la transacción después de marcar status = 1. */
    protected function despuesDeRestaurar(PDO $db, array $actual): void
    {
    }

    /** Texto con el que el registro aparece en mensajes y bitácora. */
    protected function describir(array $fila): string
    {
        $texto = $this->singular . ' #' . ($fila[$this->clave] ?? '');
        if ($this->campoDescriptivo !== null && !empty($fila[$this->campoDescriptivo])) {
            $texto .= ' (' . $fila[$this->campoDescriptivo] . ')';
        }

        return $texto;
    }

    /** Columnas que el módulo escribe sin mostrarlas en el formulario. */
    protected function columnasExtra(): array
    {
        return [];
    }

    // ------------------------------------------------------------------
    // Validación
    // ------------------------------------------------------------------

    public function normalizarId(mixed $valor): int|string
    {
        if ($this->claveAutoincremental) {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new ValidacionException('El identificador del registro no es válido.');
            }

            return $id;
        }

        $id = is_string($valor) || is_numeric($valor) ? trim((string) $valor) : '';
        if ($id === '' || strlen($id) > 30) {
            throw new ValidacionException('El identificador del registro no es válido.');
        }

        return $id;
    }

    /** Valida la entrada del formulario y devuelve solo columnas limpias. */
    public function validar(array $entrada, ?array $actual): array
    {
        $datos = [];
        foreach ($this->campos() as $campo => $def) {
            if (($def['formulario'] ?? true) === false || ($def['tipo'] ?? 'texto') === 'detalle') {
                continue;
            }
            if ($actual !== null && !empty($def['fijo'])) {
                continue;
            }

            $valor = $this->limpiarCampo($campo, $def, $entrada[$campo] ?? null, $actual);
            if (($def['tipo'] ?? '') === 'clave' && $valor === null) {
                continue; // Contraseña vacía al modificar: se conserva la actual.
            }
            if (!empty($def['unico']) && $valor !== null) {
                $this->verificarUnico($campo, $def, $valor, $actual);
            }
            $datos[$campo] = $valor;
        }

        return $datos;
    }

    protected function limpiarCampo(string $campo, array $def, mixed $valor, ?array $actual): mixed
    {
        $etiqueta = $def['etiqueta'] ?? $campo;
        $tipo = $def['tipo'] ?? 'texto';
        $requerido = ($def['requerido'] ?? false) === true
            || (($def['requerido'] ?? false) === 'registro' && $actual === null);

        if ($tipo === 'booleano') {
            return in_array($valor, [true, 1, '1', 'on', 'true'], true) ? 1 : 0;
        }
        if (is_array($valor) || is_object($valor)) {
            throw new ValidacionException("$etiqueta no tiene un formato válido.", $campo);
        }

        $texto = is_string($valor) || is_numeric($valor) ? trim((string) $valor) : '';
        if ($texto === '') {
            if ($requerido) {
                throw new ValidacionException("$etiqueta es obligatorio.", $campo);
            }

            return null;
        }

        $max = $def['max'] ?? null;
        if ($max !== null && mb_strlen($texto, 'UTF-8') > $max) {
            throw new ValidacionException("$etiqueta no puede superar $max caracteres.", $campo);
        }

        switch ($tipo) {
            case 'nombre':
                if (!preg_match("/\A\p{L}[\p{L}\s'.-]*\z/u", $texto)) {
                    throw new ValidacionException("$etiqueta solo puede contener letras y espacios.", $campo);
                }
                return preg_replace('/\s+/u', ' ', $texto);

            case 'email':
                if (!filter_var($texto, FILTER_VALIDATE_EMAIL)) {
                    throw new ValidacionException("Escribe un correo válido en $etiqueta.", $campo);
                }
                return mb_strtolower($texto, 'UTF-8');

            case 'telefono':
                if (!preg_match('/\A\+?[0-9\s()-]{7,15}\z/', $texto)) {
                    throw new ValidacionException("$etiqueta debe tener entre 7 y 15 dígitos (ej. 0414-1234567).", $campo);
                }
                return $texto;

            case 'cedula':
                $cedula = strtoupper(str_replace([' ', '.'], '', $texto));
                if (preg_match('/\A\d{6,9}\z/', $cedula)) {
                    return 'V-' . $cedula;
                }
                if (preg_match('/\A([VE])-?(\d{6,9})\z/', $cedula, $partes)) {
                    return $partes[1] . '-' . $partes[2];
                }
                throw new ValidacionException("$etiqueta debe tener el formato V-12345678 o E-12345678.", $campo);

            case 'dias':
                $dias = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
                $elegidos = array_map('trim', explode(',', $texto));
                if (array_diff($elegidos, $dias)) {
                    throw new ValidacionException("Selecciona días válidos en $etiqueta.", $campo);
                }
                // Siempre en el orden de la semana.
                return implode(', ', array_values(array_intersect($dias, $elegidos)));

            case 'usuario':
                if (!preg_match('/\A[a-zA-Z0-9._-]{4,30}\z/', $texto)) {
                    throw new ValidacionException(
                        "$etiqueta debe tener de 4 a 30 caracteres: letras, números, punto, guion o guion bajo.",
                        $campo
                    );
                }
                return $texto;

            case 'clave':
                if (strlen($texto) < 8 || strlen($texto) > 72 || !preg_match('/[A-Za-z]/', $texto) || !preg_match('/\d/', $texto)) {
                    throw new ValidacionException("$etiqueta debe tener entre 8 y 72 caracteres e incluir letras y números.", $campo);
                }
                return password_hash($texto, PASSWORD_DEFAULT);

            case 'entero':
                $numero = filter_var($texto, FILTER_VALIDATE_INT);
                if ($numero === false) {
                    throw new ValidacionException("$etiqueta debe ser un número entero.", $campo);
                }
                $this->verificarRango($campo, $etiqueta, $numero, $def);
                return $numero;

            case 'decimal':
                $texto = str_replace(',', '.', $texto);
                if (!is_numeric($texto) || !is_finite((float) $texto)) {
                    throw new ValidacionException("$etiqueta debe ser un número válido.", $campo);
                }
                $this->verificarRango($campo, $etiqueta, (float) $texto, $def);
                return number_format((float) $texto, 2, '.', '');

            case 'fecha':
                $fecha = DateTime::createFromFormat('!Y-m-d', $texto);
                if (!$fecha || $fecha->format('Y-m-d') !== $texto) {
                    throw new ValidacionException("$etiqueta no es una fecha válida.", $campo);
                }
                return $texto;

            case 'fechahora':
                foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i:s', 'Y-m-d H:i'] as $formato) {
                    $fecha = DateTime::createFromFormat('!' . $formato, $texto);
                    if ($fecha && $fecha->format($formato) === $texto) {
                        return $fecha->format('Y-m-d H:i:s');
                    }
                }
                throw new ValidacionException("$etiqueta no es una fecha y hora válida.", $campo);

            case 'opcion':
                if (!in_array($texto, array_map('strval', $def['opciones'] ?? []), true)) {
                    throw new ValidacionException("Selecciona una opción válida en $etiqueta.", $campo);
                }
                return $texto;

            case 'relacion':
                return $this->verificarRelacion($campo, $def, $texto, $actual[$campo] ?? null);

            default:
                return $texto;
        }
    }

    protected function verificarRango(string $campo, string $etiqueta, int|float $numero, array $def): void
    {
        if (isset($def['min']) && $numero < $def['min']) {
            throw new ValidacionException("$etiqueta no puede ser menor que {$def['min']}.", $campo);
        }
        if (isset($def['maxValor']) && $numero > $def['maxValor']) {
            throw new ValidacionException("$etiqueta no puede ser mayor que {$def['maxValor']}.", $campo);
        }
    }

    /**
     * Verifica que el valor de una llave foránea exista y esté activo.
     * Se permite conservar un valor inactivo si no cambió al modificar.
     */
    protected function verificarRelacion(string $campo, array $def, string $valor, mixed $valorActual): int|string
    {
        $rel = $def['relacion'] ?? null;
        if ($rel === null) {
            return $valor;
        }

        $etiqueta = mb_strtolower($def['etiqueta'] ?? $campo, 'UTF-8');
        if (empty($rel['claveTexto'])) {
            $id = filter_var($valor, FILTER_VALIDATE_INT);
            if ($id === false || $id < 1) {
                throw new ValidacionException("Selecciona un valor válido en $etiqueta.", $campo);
            }
            $valor = $id;
        }

        $activo = $rel['activo'] ?? 'status = 1';
        $stmt = $this->conexion($rel['bd'] ?? self::NEGOCIO)
            ->prepare("SELECT ($activo) AS activo FROM `{$rel['tabla']}` WHERE `{$rel['clave']}` = :valor");
        $stmt->execute([':valor' => $valor]);
        $fila = $stmt->fetch();

        if ($fila === false) {
            throw new ValidacionException("El valor seleccionado en $etiqueta no existe.", $campo);
        }
        if ((int) $fila['activo'] !== 1 && (string) $valorActual !== (string) $valor) {
            throw new ValidacionException("El valor seleccionado en $etiqueta está inactivo o no disponible.", $campo);
        }

        return $valor;
    }

    protected function verificarUnico(string $campo, array $def, mixed $valor, ?array $actual): void
    {
        $sql = "SELECT 1 FROM `{$this->tabla}` WHERE `$campo` = :valor";
        $parametros = [':valor' => $valor];
        if ($actual !== null) {
            $sql .= " AND `{$this->clave}` <> :id";
            $parametros[':id'] = $actual[$this->clave];
        }
        $stmt = $this->db()->prepare($sql . ' LIMIT 1');
        $stmt->execute($parametros);
        if ($stmt->fetchColumn() !== false) {
            $etiqueta = mb_strtolower($def['etiqueta'] ?? $campo, 'UTF-8');
            $o = $this->genero('o', 'a');
            throw new ValidacionException(
                "Ya existe {$this->genero('un', 'una')} {$this->singular} con ese dato ($etiqueta). "
                . "Si fue eliminad$o, búscal$o en «Eliminados» y restáural$o.",
                $campo
            );
        }
    }

    /**
     * Valida las líneas de un campo tipo detalle (por ejemplo, los servicios de un ticket)
     * con las mismas reglas por tipo de campo.
     */
    protected function limpiarDetalles(string $campo, mixed $lineas, int $maximo = 50): array
    {
        $def = $this->campos()[$campo];
        $etiqueta = $def['etiqueta'] ?? $campo;
        if (!is_array($lineas) || $lineas === []) {
            throw new ValidacionException("Agrega al menos una línea en $etiqueta.", $campo);
        }
        if (count($lineas) > $maximo) {
            throw new ValidacionException("$etiqueta admite como máximo $maximo líneas.", $campo);
        }

        $limpias = [];
        foreach (array_values($lineas) as $i => $linea) {
            if (!is_array($linea)) {
                throw new ValidacionException('La línea ' . ($i + 1) . " de $etiqueta no es válida.", $campo);
            }
            $fila = [];
            foreach ($def['columnas'] as $columna => $defColumna) {
                try {
                    $fila[$columna] = $this->limpiarCampo($columna, $defColumna, $linea[$columna] ?? null, null);
                } catch (ValidacionException $e) {
                    throw new ValidacionException('Línea ' . ($i + 1) . ': ' . $e->getMessage(), $campo);
                }
            }
            $limpias[] = $fila;
        }

        return $limpias;
    }

    // ------------------------------------------------------------------
    // Opciones de los selects y datos para la vista
    // ------------------------------------------------------------------

    /**
     * Opciones de todos los campos tipo relacion (incluidos los de detalle).
     * Cada opción trae valor, texto, si está activa y datos extra (precio, costo...).
     */
    public function opcionesFormulario(): array
    {
        $opciones = [];
        foreach ($this->campos() as $campo => $def) {
            $definiciones = ($def['tipo'] ?? '') === 'detalle' ? ($def['columnas'] ?? []) : [$campo => $def];
            foreach ($definiciones as $nombre => $defCampo) {
                if (($defCampo['tipo'] ?? '') === 'relacion' && isset($defCampo['relacion'])) {
                    $opciones[$defCampo['fuente'] ?? $nombre] = $this->opcionesRelacion($defCampo['relacion']);
                }
            }
        }

        return $opciones;
    }

    /** Opciones de un solo campo tipo relacion (para selects de acciones propias). */
    public function opcionesCampo(string $campo): array
    {
        $rel = $this->campos()[$campo]['relacion'] ?? null;

        return $rel === null ? [] : $this->opcionesRelacion($rel);
    }

    protected function opcionesRelacion(array $rel): array
    {
        $activo = $rel['activo'] ?? 'status = 1';
        $extras = '';
        foreach ($rel['extra'] ?? [] as $alias => $expresion) {
            $extras .= ", $expresion AS `$alias`";
        }
        $sql = "SELECT `{$rel['clave']}` AS valor, {$rel['texto']} AS texto, ($activo) AS activo{$extras}
                FROM `{$rel['tabla']}` ORDER BY activo DESC, " . ($rel['orden'] ?? 'texto');

        return array_map(static function (array $fila): array {
            $fila['activo'] = (int) $fila['activo'] === 1;
            return $fila;
        }, $this->conexion($rel['bd'] ?? self::NEGOCIO)->query($sql)->fetchAll());
    }

    /** Definición de campos sin detalles internos (SQL de relaciones) para el navegador. */
    public function camposParaVista(?array $campos = null): array
    {
        $publicos = ['etiqueta', 'tipo', 'requerido', 'max', 'min', 'maxValor', 'paso', 'opciones', 'ayuda', 'ancho',
            'fijo', 'defecto', 'autollenar', 'fuente', 'total', 'formulario', 'placeholder'];
        $resultado = [];
        foreach ($campos ?? $this->campos() as $campo => $def) {
            $limpio = array_intersect_key($def, array_flip($publicos));
            if (isset($def['columnas'])) {
                $limpio['columnas'] = $this->camposParaVista($def['columnas']);
            }
            if (($def['tipo'] ?? '') === 'relacion' && !isset($limpio['fuente'])) {
                $limpio['fuente'] = $campo;
            }
            $resultado[$campo] = $limpio;
        }

        return $resultado;
    }

    // ------------------------------------------------------------------
    // Getters (encapsulamiento: las propiedades solo se leen desde fuera)
    // ------------------------------------------------------------------

    public function clave(): string
    {
        return $this->clave;
    }

    public function modulo(): string
    {
        return $this->modulo;
    }

    public function singular(): string
    {
        return $this->singular;
    }

    public function femenino(): bool
    {
        return $this->femenino;
    }

    public function tieneEstado(): bool
    {
        return $this->tieneEstado;
    }

    /** Devuelve la palabra que concuerda con el género del singular. */
    public function genero(string $masculino, string $femenino): string
    {
        return $this->femenino ? $femenino : $masculino;
    }

    // ------------------------------------------------------------------
    // Utilidades internas
    // ------------------------------------------------------------------

    protected function db(): PDO
    {
        return $this->conexion($this->baseDatos);
    }

    /** Ejecuta la operación en una transacción y la revierte si algo falla. */
    protected function transaccion(callable $operacion): mixed
    {
        $db = $this->db();
        $db->beginTransaction();
        try {
            $resultado = $operacion($db);
            $db->commit();

            return $resultado;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /** Filtra los datos para que solo lleguen al SQL columnas declaradas en campos(). */
    protected function soloColumnas(array $datos): array
    {
        $permitidas = [$this->clave => true, 'status' => true];
        foreach ($this->campos() as $campo => $def) {
            if (($def['tipo'] ?? '') !== 'detalle' && ($def['columna'] ?? true) !== false) {
                $permitidas[$campo] = true;
            }
        }
        foreach ($this->columnasExtra() as $columna) {
            $permitidas[$columna] = true;
        }

        return array_intersect_key($datos, $permitidas);
    }

    protected function parametros(array $datos): array
    {
        $parametros = [];
        foreach ($datos as $columna => $valor) {
            $parametros[':' . $columna] = $valor;
        }

        return $parametros;
    }

    protected function bitacora(string $accion, string $descripcion): void
    {
        $this->registrarBitacora($this->modulo, $accion, $descripcion);
    }
}
