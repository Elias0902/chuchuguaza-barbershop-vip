<?php

namespace App\Models;

use App\Core\BaseModel;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;
use App\Models\Analisis\Hallazgo;
use App\Models\Analisis\MotorAnalisis;
use PDO;
use Throwable;

/**
 * Buzón de sugerencias del sistema. Nadie las escribe: el motor de análisis revisa
 * los datos cada cierto tiempo y deja aquí ideas para mejorar y vender más.
 *
 * Ciclo de una sugerencia: Nueva → Leída → Aplicada o Descartada. Si la situación
 * desaparece sola, el sistema la marca como Resuelta. Mientras una sugerencia está
 * abierta no se repite; si se aplicó o descartó, no vuelve a aparecer en DIAS_ESPERA días.
 */
class Buzon extends BaseModel
{
    public const HORAS_ENTRE_ANALISIS = 6;
    private const DIAS_ESPERA = 15;

    public const ABIERTAS = ['Nueva', 'Leída'];
    public const ESTADOS = ['Nueva', 'Leída', 'Aplicada', 'Descartada', 'Resuelta'];
    /** Estados que puede elegir el usuario (Resuelta la pone el sistema). */
    private const ESTADOS_USUARIO = ['Leída', 'Aplicada', 'Descartada'];

    /** Se puede inyectar otro motor (por ejemplo, con menos reglas); si no, se usa el predeterminado. */
    public function __construct(private readonly ?MotorAnalisis $motor = null)
    {
    }

    /** Ejecuta el análisis si pasó el tiempo mínimo desde el último. */
    public function analizarSiCorresponde(): void
    {
        $ultima = $this->ultimaEjecucion();
        if ($ultima === null || strtotime($ultima['fecha_ejecucion']) < time() - self::HORAS_ENTRE_ANALISIS * 3600) {
            $this->analizar('Automático');
        }
    }

    /** @return array{hallazgos: int, nuevas: int, resueltas: int} */
    public function analizar(string $origen = 'Manual', ?int $idUsuario = null): array
    {
        $motor = $this->motor ?? MotorAnalisis::predeterminado();
        $hallazgos = [];
        foreach ($motor->analizar() as $hallazgo) {
            $hallazgos[$hallazgo->clave] = $hallazgo; // una sugerencia por situación
        }

        $db = $this->negocio();
        $db->beginTransaction();
        try {
            $resultado = $this->sincronizar($db, $hallazgos);
            $db->prepare(
                'INSERT INTO analisis_ejecuciones (origen, id_usuario, hallazgos, nuevas, resueltas) VALUES (:origen, :usuario, :hallazgos, :nuevas, :resueltas)'
            )->execute([
                ':origen' => $origen, ':usuario' => $idUsuario, ':hallazgos' => count($hallazgos),
                ':nuevas' => $resultado['nuevas'], ':resueltas' => $resultado['resueltas'],
            ]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        if ($origen === 'Manual') {
            $this->registrarBitacora('Buzón de sugerencias', 'Analizar', "Ejecutó el análisis: {$resultado['nuevas']} sugerencias nuevas y {$resultado['resueltas']} resueltas.");
        }

        return ['hallazgos' => count($hallazgos)] + $resultado;
    }

    /** @param array<string, Hallazgo> $hallazgos */
    private function sincronizar(PDO $db, array $hallazgos): array
    {
        $nuevas = 0;
        $resueltas = 0;

        $abiertas = $db->query("SELECT id_sugerencia, clave FROM sugerencias WHERE status = 1 AND estado IN ('Nueva', 'Leída')")->fetchAll();
        $actualizar = $db->prepare(
            'UPDATE sugerencias SET categoria = :categoria, prioridad = :prioridad, titulo = :titulo, detalle = :detalle,
                    icono = :icono, ruta = :ruta, texto_enlace = :texto_enlace, fecha_actualizada = NOW()
             WHERE id_sugerencia = :id'
        );
        $resolver = $db->prepare("UPDATE sugerencias SET estado = 'Resuelta', fecha_estado = NOW() WHERE id_sugerencia = :id");

        $atendidas = [];
        foreach ($abiertas as $abierta) {
            if (isset($hallazgos[$abierta['clave']])) {
                $actualizar->execute($this->parametros($hallazgos[$abierta['clave']]) + [':id' => $abierta['id_sugerencia']]);
                $atendidas[$abierta['clave']] = true;
            } else {
                $resolver->execute([':id' => $abierta['id_sugerencia']]);
                $resueltas++;
            }
        }

        $enEspera = $db->prepare(
            "SELECT 1 FROM sugerencias WHERE clave = :clave AND status = 1 AND estado IN ('Aplicada', 'Descartada')
               AND fecha_estado >= DATE_SUB(NOW(), INTERVAL :dias DAY) LIMIT 1"
        );
        $insertar = $db->prepare(
            "INSERT INTO sugerencias (clave, categoria, prioridad, titulo, detalle, icono, ruta, texto_enlace, estado, status)
             VALUES (:clave, :categoria, :prioridad, :titulo, :detalle, :icono, :ruta, :texto_enlace, 'Nueva', 1)"
        );
        foreach ($hallazgos as $clave => $hallazgo) {
            if (isset($atendidas[$clave])) {
                continue;
            }
            $enEspera->execute([':clave' => $clave, ':dias' => self::DIAS_ESPERA]);
            if ($enEspera->fetchColumn() !== false) {
                continue;
            }
            $insertar->execute($this->parametros($hallazgo) + [':clave' => $clave]);
            $nuevas++;
        }

        return ['nuevas' => $nuevas, 'resueltas' => $resueltas];
    }

    public function cambiarEstado(int $id, string $estado): void
    {
        if (!in_array($estado, self::ESTADOS_USUARIO, true) && $estado !== 'Reabrir') {
            throw new ValidacionException('El estado solicitado no es válido.');
        }

        $stmt = $this->negocio()->prepare('SELECT titulo, estado FROM sugerencias WHERE id_sugerencia = :id AND status = 1');
        $stmt->execute([':id' => $id]);
        $actual = $stmt->fetch();
        if (!$actual) {
            throw new RegistroNoEncontradoException('La sugerencia no existe.');
        }
        if ($actual['estado'] === 'Resuelta') {
            throw new ValidacionException('Esta sugerencia ya se resolvió sola: la situación cambió.');
        }

        $nuevo = $estado === 'Reabrir' ? 'Leída' : $estado;
        $this->negocio()->prepare('UPDATE sugerencias SET estado = :estado, fecha_estado = NOW() WHERE id_sugerencia = :id')
            ->execute([':estado' => $nuevo, ':id' => $id]);
        $this->registrarBitacora('Buzón de sugerencias', 'Modificar', "Marcó como «{$nuevo}» la sugerencia «{$actual['titulo']}».");
    }

    // ------------------------------------------------------------------
    // Consultas
    // ------------------------------------------------------------------

    /** Sugerencias de un grupo: pendientes (Nueva y Leída), un estado concreto o todas. */
    public function listar(string $grupo = 'pendientes'): array
    {
        $donde = match (true) {
            $grupo === 'pendientes' => "estado IN ('Nueva', 'Leída')",
            in_array($grupo, self::ESTADOS, true) => 'estado = :estado',
            default => '1 = 1',
        };
        $stmt = $this->negocio()->prepare(
            "SELECT * FROM sugerencias WHERE status = 1 AND $donde
             ORDER BY (estado = 'Nueva') DESC, FIELD(prioridad, 'alta', 'media', 'baja', 'positiva'), fecha_actualizada DESC
             LIMIT 200"
        );
        $stmt->execute(in_array($grupo, self::ESTADOS, true) ? [':estado' => $grupo] : []);

        return $stmt->fetchAll();
    }

    /** @return array<string, int> estado => cantidad, más 'pendientes' y 'todas' */
    public function contarPorEstado(): array
    {
        $conteo = array_fill_keys(self::ESTADOS, 0);
        foreach ($this->negocio()->query('SELECT estado, COUNT(*) AS n FROM sugerencias WHERE status = 1 GROUP BY estado')->fetchAll() as $fila) {
            $conteo[$fila['estado']] = (int) $fila['n'];
        }

        return $conteo + ['pendientes' => $conteo['Nueva'] + $conteo['Leída'], 'todas' => array_sum($conteo)];
    }

    public function nuevas(): int
    {
        return (int) $this->negocio()->query("SELECT COUNT(*) FROM sugerencias WHERE status = 1 AND estado = 'Nueva'")->fetchColumn();
    }

    /** Las sugerencias abiertas más importantes (para el inicio y el análisis). */
    public function destacadas(int $limite = 3): array
    {
        $stmt = $this->negocio()->prepare(
            "SELECT * FROM sugerencias WHERE status = 1 AND estado IN ('Nueva', 'Leída')
             ORDER BY FIELD(prioridad, 'alta', 'media', 'baja', 'positiva'), (estado = 'Nueva') DESC, fecha_actualizada DESC
             LIMIT :limite"
        );
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function ultimaEjecucion(): ?array
    {
        $fila = $this->negocio()->query('SELECT * FROM analisis_ejecuciones ORDER BY id_ejecucion DESC LIMIT 1')->fetch();

        return $fila ?: null;
    }

    private function parametros(Hallazgo $hallazgo): array
    {
        return [
            ':categoria' => $hallazgo->categoria,
            ':prioridad' => $hallazgo->prioridad,
            ':titulo' => mb_substr($hallazgo->titulo, 0, 150, 'UTF-8'),
            ':detalle' => mb_substr($hallazgo->detalle, 0, 600, 'UTF-8'),
            ':icono' => $hallazgo->icono,
            ':ruta' => $hallazgo->ruta,
            ':texto_enlace' => $hallazgo->textoEnlace,
        ];
    }
}
