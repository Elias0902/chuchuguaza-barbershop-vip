<?php

namespace App\Core;

use PDO;
use Throwable;

/**
 * Permisos por rol (base de seguridad: modulos, permisos y accesos).
 *
 * Cada módulo del menú tiene cuatro permisos: consultar, registrar, modificar y eliminar
 * (eliminar incluye restaurar). Los permisos se leen del rol actual del usuario en la base,
 * así un cambio de rol o de permisos se aplica en la siguiente petición.
 */
final class Autorizacion extends BaseModel
{
    /** Clave interna → nombre guardado en la tabla permisos. */
    public const PERMISOS = [
        'consultar' => 'Consultar',
        'registrar' => 'Registrar',
        'modificar' => 'Modificar',
        'eliminar' => 'Eliminar',
    ];

    /** Grupos del menú que se guardan como módulos de tipo Seguridad. */
    private const GRUPOS_SEGURIDAD = ['seguridad'];

    /** Permisos del usuario en sesión, calculados una vez por petición. */
    private static ?array $permisosSesion = null;

    /** Menú completo de config/modulos.php. */
    public static function menu(): array
    {
        return require dirname(__DIR__, 2) . '/config/modulos.php';
    }

    public function puede(string $ruta, string $permiso): bool
    {
        return in_array($permiso, $this->permisosUsuario()[$ruta] ?? [], true);
    }

    /** ['clientes' => ['consultar', 'registrar'], ...] del usuario en sesión. */
    public function permisosUsuario(): array
    {
        if (self::$permisosSesion !== null) {
            return self::$permisosSesion;
        }

        $idUsuario = (int) ($_SESSION['s_usuario']['id_usuario'] ?? 0);
        if ($idUsuario === 0) {
            return self::$permisosSesion = [];
        }

        $stmt = $this->seguridad()->prepare('SELECT id_rol_usuario FROM usuarios WHERE id_usuario = :id AND status = 1');
        $stmt->execute([':id' => $idUsuario]);
        $idRol = (int) $stmt->fetchColumn();
        if ($idRol === 0) {
            return self::$permisosSesion = [];
        }

        $this->asegurarConfiguracion($idRol);

        return self::$permisosSesion = $this->aplicarExcepciones($this->permisosDeRol($idRol), $this->excepcionesDeUsuario($idUsuario));
    }

    /** Menú sin los módulos que el usuario no puede consultar (y sin grupos vacíos). */
    public function menuPermitido(): array
    {
        $permisos = $this->permisosUsuario();
        $menu = [];
        foreach (self::menu() as $clave => $grupo) {
            $grupo['modulos'] = array_filter(
                $grupo['modulos'],
                static fn(string $ruta): bool => in_array('consultar', $permisos[$ruta] ?? [], true),
                ARRAY_FILTER_USE_KEY
            );
            if ($grupo['modulos']) {
                $menu[$clave] = $grupo;
            }
        }

        return $menu;
    }

    /**
     * Excepciones activas de un usuario: ['Conceder' => [ruta => [...]], 'Quitar' => [ruta => [...]]].
     */
    public function excepcionesDeUsuario(int $idUsuario): array
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT m.ruta, p.nombre_permiso, pu.tipo
             FROM permisos_usuario pu
             INNER JOIN modulos m ON m.id_modulo = pu.id_modulo
             INNER JOIN permisos p ON p.id_permisos = pu.id_permiso
             WHERE pu.id_usuario = :usuario AND pu.status = 1 AND m.status = 1'
        );
        $stmt->execute([':usuario' => $idUsuario]);

        $claves = array_flip(self::PERMISOS);
        $excepciones = ['Conceder' => [], 'Quitar' => []];
        foreach ($stmt->fetchAll() as $fila) {
            if (isset($claves[$fila['nombre_permiso']])) {
                $excepciones[$fila['tipo']][$fila['ruta']][] = $claves[$fila['nombre_permiso']];
            }
        }

        return $excepciones;
    }

    /** Permisos del rol + los concedidos al usuario − los que se le quitaron. */
    public function aplicarExcepciones(array $permisos, array $excepciones): array
    {
        foreach ($excepciones['Conceder'] as $ruta => $lista) {
            $permisos[$ruta] = array_values(array_unique(array_merge($permisos[$ruta] ?? [], $lista)));
        }
        foreach ($excepciones['Quitar'] as $ruta => $lista) {
            $permisos[$ruta] = array_values(array_diff($permisos[$ruta] ?? [], $lista));
            // Sin consultar no se puede hacer nada más en el módulo.
            if ($permisos[$ruta] === [] || !in_array('consultar', $permisos[$ruta], true)) {
                unset($permisos[$ruta]);
            }
        }

        return $permisos;
    }

    public function permisosDeRol(int $idRol): array
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT m.ruta, p.nombre_permiso
             FROM accesos a
             INNER JOIN modulos m ON m.id_modulo = a.id_modulo
             INNER JOIN permisos p ON p.id_permisos = a.id_permiso
             WHERE a.id_rol = :rol AND a.status = 1 AND m.status = 1'
        );
        $stmt->execute([':rol' => $idRol]);

        $claves = array_flip(self::PERMISOS);
        $permisos = [];
        foreach ($stmt->fetchAll() as $fila) {
            if (isset($claves[$fila['nombre_permiso']])) {
                $permisos[$fila['ruta']][] = $claves[$fila['nombre_permiso']];
            }
        }

        return $permisos;
    }

    /** Olvida los permisos calculados (después de guardar cambios en la misma petición). */
    public static function reiniciar(): void
    {
        self::$permisosSesion = null;
    }

    /**
     * Mantiene las tablas modulos y permisos al día con config/modulos.php.
     * Si todavía no existe ningún acceso, da acceso total al rol que entra primero
     * para que el sistema no quede bloqueado.
     */
    private function asegurarConfiguracion(int $idRol): void
    {
        $menu = self::menu();
        $huella = md5(serialize($menu) . serialize(self::PERMISOS));
        if (($_SESSION['permisos_sincronizados'] ?? '') === $huella) {
            return;
        }

        $db = $this->seguridad();
        $db->beginTransaction();
        try {
            $this->sincronizarPermisos($db);
            $this->sincronizarModulos($db, $menu);
            if ((int) $db->query('SELECT COUNT(*) FROM accesos')->fetchColumn() === 0) {
                $this->darAccesoTotal($db, $idRol);
            }
            $db->commit();
            $_SESSION['permisos_sincronizados'] = $huella;
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('No se pudieron sincronizar los permisos: ' . $e->getMessage());
        }
    }

    private function sincronizarPermisos(PDO $db): void
    {
        $existe = $db->prepare('SELECT 1 FROM permisos WHERE nombre_permiso = :nombre');
        $insertar = $db->prepare("INSERT INTO permisos (nombre_permiso, tipo) VALUES (:nombre, 'CRUD')");
        foreach (self::PERMISOS as $nombre) {
            $existe->execute([':nombre' => $nombre]);
            if ($existe->fetchColumn() === false) {
                $insertar->execute([':nombre' => $nombre]);
            }
        }
    }

    private function sincronizarModulos(PDO $db, array $menu): void
    {
        $buscar = $db->prepare('SELECT id_modulo FROM modulos WHERE ruta = :ruta ORDER BY id_modulo LIMIT 1');
        $insertar = $db->prepare(
            'INSERT INTO modulos (nombre_modulo, descripcion, status, tipo_modulo, ruta, icono)
             VALUES (:nombre, :descripcion, 1, :tipo, :ruta, :icono)'
        );
        $actualizar = $db->prepare(
            'UPDATE modulos SET nombre_modulo = :nombre, descripcion = :descripcion, status = 1, tipo_modulo = :tipo, icono = :icono
             WHERE id_modulo = :id'
        );

        $rutas = [];
        $nuevos = [];
        foreach ($menu as $claveGrupo => $grupo) {
            foreach ($grupo['modulos'] as $ruta => $modulo) {
                $rutas[] = $ruta;
                $datos = [
                    ':nombre' => $modulo['titulo'],
                    ':descripcion' => mb_substr($modulo['descripcion'], 0, 255, 'UTF-8'),
                    ':tipo' => in_array($claveGrupo, self::GRUPOS_SEGURIDAD, true) ? 'Seguridad' : 'Negocio',
                    ':icono' => $modulo['icono'],
                ];
                $buscar->execute([':ruta' => $ruta]);
                $id = $buscar->fetchColumn();
                if ($id === false) {
                    $insertar->execute($datos + [':ruta' => $ruta]);
                    $nuevos[] = (int) $db->lastInsertId();
                } else {
                    $actualizar->execute($datos + [':id' => $id]);
                }
            }
        }

        // Módulos que ya no están en el menú: eliminación lógica.
        $marcas = implode(', ', array_fill(0, count($rutas), '?'));
        $db->prepare("UPDATE modulos SET status = 0 WHERE ruta NOT IN ($marcas)")->execute($rutas);

        foreach ($nuevos as $idModulo) {
            $this->darModuloAAdministradores($db, $idModulo);
        }
    }

    /**
     * Un módulo recién agregado al menú se da completo a los roles que pueden modificar Roles
     * (quienes administran permisos); los demás roles lo reciben cuando se les asigne.
     */
    private function darModuloAAdministradores(PDO $db, int $idModulo): void
    {
        $db->prepare(
            "INSERT INTO accesos (id_rol, id_modulo, id_permiso, status)
             SELECT administradores.id_rol, :modulo, p.id_permisos, 1
             FROM (SELECT DISTINCT a.id_rol
                   FROM accesos a
                   INNER JOIN modulos m ON m.id_modulo = a.id_modulo AND m.ruta = 'roles'
                   INNER JOIN permisos pr ON pr.id_permisos = a.id_permiso AND pr.nombre_permiso = 'Modificar'
                   WHERE a.status = 1) administradores
             CROSS JOIN permisos p"
        )->execute([':modulo' => $idModulo]);
    }

    private function darAccesoTotal(PDO $db, int $idRol): void
    {
        $db->prepare(
            'INSERT INTO accesos (id_rol, id_modulo, id_permiso, status)
             SELECT :rol, m.id_modulo, p.id_permisos, 1 FROM modulos m CROSS JOIN permisos p WHERE m.status = 1'
        )->execute([':rol' => $idRol]);
        $this->registrarBitacora('Seguridad', 'Permisos', "Configuración inicial: acceso total para el rol #$idRol.");
    }
}
