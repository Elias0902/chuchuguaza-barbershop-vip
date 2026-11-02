<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Respaldos de las dos bases de datos, hechos con PDO (no hace falta mysqldump).
 *
 * El sistema hace uno automático cada HORAS_ENTRE_AUTOMATICOS al abrir cualquier página
 * y conserva solo los últimos MAX_AUTOMATICOS; los manuales no se borran solos.
 * Los archivos quedan en storage/respaldos (fuera del repositorio).
 */
class Respaldo extends BaseModel
{
    public const HORAS_ENTRE_AUTOMATICOS = 24;
    public const MAX_AUTOMATICOS = 14;
    public const TIPOS = ['Manual', 'Automático', 'Antes de restaurar'];

    /** La propia tabla de respaldos no se respalda: así restaurar no borra el historial. */
    private const TABLAS_EXCLUIDAS = ['respaldos'];

    private function carpeta(): string
    {
        $carpeta = dirname(__DIR__, 2) . '/storage/respaldos';
        if (!is_dir($carpeta) && !mkdir($carpeta, 0775, true) && !is_dir($carpeta)) {
            throw new RuntimeException('No se pudo crear la carpeta de respaldos.');
        }

        return $carpeta;
    }

    public function listar(): array
    {
        return $this->seguridad()->query(
            'SELECT r.*, u.nombre_usuario FROM respaldos r LEFT JOIN usuarios u ON u.id_usuario = r.id_usuario
             ORDER BY r.fecha_creacion DESC, r.id_respaldo DESC'
        )->fetchAll();
    }

    public function ultimoAutomatico(): ?string
    {
        $fecha = $this->seguridad()->query("SELECT MAX(fecha_creacion) FROM respaldos WHERE tipo_respaldo = 'Automático'")->fetchColumn();

        return $fecha ?: null;
    }

    /** Se llama al mostrar cualquier página: si ya toca, respalda las dos bases. */
    public function automaticoSiCorresponde(int $idUsuario): void
    {
        $ultimo = $this->ultimoAutomatico();
        if ($ultimo !== null && strtotime($ultimo) > time() - self::HORAS_ENTRE_AUTOMATICOS * 3600) {
            return;
        }
        // Un candado evita que dos pestañas abiertas a la vez hagan el mismo respaldo.
        $candado = fopen($this->carpeta() . '/.candado', 'c');
        if ($candado === false || !flock($candado, LOCK_EX | LOCK_NB)) {
            return;
        }
        try {
            $this->generar('Automático', $idUsuario);
            $this->rotarAutomaticos();
        } finally {
            flock($candado, LOCK_UN);
            fclose($candado);
        }
    }

    /** Respalda las dos bases; devuelve cuántos archivos se crearon. */
    public function generar(string $tipo, int $idUsuario): int
    {
        if (!in_array($tipo, self::TIPOS, true)) {
            throw new ValidacionException('Tipo de respaldo no válido.');
        }
        $marca = date('Ymd_His');
        $sufijo = ['Manual' => 'manual', 'Automático' => 'auto', 'Antes de restaurar' => 'previo'][$tipo];
        foreach ([self::NEGOCIO => DB_NAME_NEGOCIO, self::SEGURIDAD => DB_NAME_SECURITY] as $base => $nombreBd) {
            $archivo = "{$nombreBd}_{$marca}_{$sufijo}.sql";
            $registros = $this->volcar($this->conexion($base), $nombreBd, $this->carpeta() . '/' . $archivo);
            $this->seguridad()->prepare(
                'INSERT INTO respaldos (nombre_archivo, tipo_respaldo, ruta_almacenamiento, peso_kb, total_registros, id_usuario, fecha_creacion, tipo_bd, formato)
                 VALUES (:archivo, :tipo, :ruta, :peso, :registros, :usuario, NOW(), :bd, :formato)'
            )->execute([
                ':archivo' => $archivo,
                ':tipo' => $tipo,
                ':ruta' => 'storage/respaldos/' . $archivo,
                ':peso' => round(filesize($this->carpeta() . '/' . $archivo) / 1024, 2),
                ':registros' => $registros,
                ':usuario' => $idUsuario,
                ':bd' => $base === self::NEGOCIO ? 'Negocio' : 'Seguridad',
                ':formato' => 'SQL',
            ]);
        }
        $this->registrarBitacora('Respaldos', 'Generar', "Respaldo $tipo de las bases de negocio y seguridad.", $idUsuario);

        return 2;
    }

    /** Archivo de un respaldo, comprobando que esté dentro de la carpeta de respaldos. */
    public function archivo(int $id): array
    {
        $stmt = $this->seguridad()->prepare('SELECT * FROM respaldos WHERE id_respaldo = :id');
        $stmt->execute([':id' => $id]);
        $respaldo = $stmt->fetch();
        if (!$respaldo) {
            throw new RegistroNoEncontradoException('El respaldo no existe.');
        }
        $ruta = $this->carpeta() . '/' . basename($respaldo['nombre_archivo']);
        if (!is_file($ruta)) {
            throw new RegistroNoEncontradoException('El archivo de este respaldo ya no está en el servidor.');
        }

        return [$respaldo, $ruta];
    }

    /**
     * Restaura una base desde un respaldo. Antes hace un respaldo «Antes de restaurar»
     * de las dos bases, para poder deshacerlo.
     */
    public function restaurar(int $id, int $idUsuario): void
    {
        [$respaldo, $ruta] = $this->archivo($id);
        $this->generar('Antes de restaurar', $idUsuario);

        $db = $this->conexion($respaldo['tipo_bd'] === 'Negocio' ? self::NEGOCIO : self::SEGURIDAD);
        $sentencia = '';
        $manejador = fopen($ruta, 'r');
        try {
            $db->exec('SET FOREIGN_KEY_CHECKS = 0');
            while (($linea = fgets($manejador)) !== false) {
                if ($sentencia === '' && (str_starts_with($linea, '--') || trim($linea) === '')) {
                    continue;
                }
                $sentencia .= $linea;
                // Cada sentencia del volcado termina en «;» al final de la línea (los saltos dentro de textos van escapados).
                if (str_ends_with(rtrim($linea, "\r\n"), ';')) {
                    $db->exec($sentencia);
                    $sentencia = '';
                }
            }
        } finally {
            fclose($manejador);
            $db->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
        $this->registrarBitacora('Respaldos', 'Restaurar', "Restauró la base {$respaldo['tipo_bd']} desde {$respaldo['nombre_archivo']}.", $idUsuario);
    }

    /** Escribe CREATE TABLE + INSERT de cada tabla; devuelve el total de filas. */
    private function volcar(PDO $db, string $nombreBd, string $ruta): int
    {
        $salida = fopen($ruta, 'w');
        if ($salida === false) {
            throw new RuntimeException('No se pudo escribir el archivo del respaldo.');
        }
        $total = 0;
        try {
            fwrite($salida, "-- Respaldo de $nombreBd · " . APP_NOMBRE . ' · ' . date('Y-m-d H:i:s') . "\n");
            fwrite($salida, "SET NAMES utf8mb4;\n\n");
            $tablas = $db->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_COLUMN);
            foreach (array_diff($tablas, self::TABLAS_EXCLUIDAS) as $tabla) {
                $crear = $db->query("SHOW CREATE TABLE `$tabla`")->fetch(PDO::FETCH_NUM)[1];
                fwrite($salida, "-- Tabla $tabla\nDROP TABLE IF EXISTS `$tabla`;\n" . str_replace("\n", ' ', $crear) . ";\n");

                $filas = $db->query("SELECT * FROM `$tabla`", PDO::FETCH_NUM);
                $lote = [];
                foreach ($filas as $fila) {
                    $lote[] = '(' . implode(',', array_map(fn($v) => $this->literal($db, $v), $fila)) . ')';
                    $total++;
                    if (count($lote) === 200) {
                        fwrite($salida, "INSERT INTO `$tabla` VALUES " . implode(',', $lote) . ";\n");
                        $lote = [];
                    }
                }
                if ($lote) {
                    fwrite($salida, "INSERT INTO `$tabla` VALUES " . implode(',', $lote) . ";\n");
                }
                fwrite($salida, "\n");
            }
        } catch (Throwable $e) {
            fclose($salida);
            @unlink($ruta);
            throw $e;
        }
        fclose($salida);

        return $total;
    }

    private function literal(PDO $db, mixed $valor): string
    {
        if ($valor === null) {
            return 'NULL';
        }

        return str_replace(["\r", "\n"], ['\\r', '\\n'], $db->quote((string) $valor));
    }

    /** Conserva los últimos MAX_AUTOMATICOS respaldos automáticos de cada base; borra el resto. */
    private function rotarAutomaticos(): void
    {
        $db = $this->seguridad();
        foreach (['Negocio', 'Seguridad'] as $bd) {
            $stmt = $db->prepare(
                "SELECT id_respaldo, nombre_archivo FROM respaldos WHERE tipo_respaldo = 'Automático' AND tipo_bd = :bd
                 ORDER BY fecha_creacion DESC, id_respaldo DESC LIMIT 1000 OFFSET " . self::MAX_AUTOMATICOS
            );
            $stmt->execute([':bd' => $bd]);
            foreach ($stmt->fetchAll() as $viejo) {
                @unlink($this->carpeta() . '/' . basename($viejo['nombre_archivo']));
                $db->prepare('DELETE FROM respaldos WHERE id_respaldo = :id')->execute([':id' => $viejo['id_respaldo']]);
            }
        }
    }
}
