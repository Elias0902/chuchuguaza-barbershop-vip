<?php

namespace App\Models;

use App\Core\Autorizacion;
use App\Core\BaseModel;
use App\Core\RegistroNoEncontradoException;
use App\Core\ValidacionException;
use PDO;
use Throwable;

/**
 * Matriz de permisos de un rol: qué puede hacer en cada módulo.
 * Los accesos que se quitan quedan con status = 0 (eliminación lógica).
 */
class Permiso extends BaseModel
{
    /** Módulo que administra los permisos: un rol no puede quitárselo a sí mismo. */
    private const MODULO_ROLES = 'roles';

    public function __construct(private readonly Autorizacion $autorizacion = new Autorizacion())
    {
    }

    /** Grupos del menú con los permisos marcados para el rol. */
    public function matriz(int $idRol): array
    {
        $rol = $this->rol($idRol);

        return ['rol' => $rol, 'grupos' => $this->grupos($this->autorizacion->permisosDeRol($idRol)), 'acciones' => Autorizacion::PERMISOS];
    }

    /**
     * Matriz de un usuario: sus permisos efectivos y, aparte, los de su rol,
     * para resaltar lo que es una excepción.
     */
    public function matrizUsuario(int $idUsuario): array
    {
        $usuario = $this->usuario($idUsuario);
        $deRol = $this->autorizacion->permisosDeRol((int) $usuario['id_rol_usuario']);
        $efectivos = $this->autorizacion->aplicarExcepciones($deRol, $this->autorizacion->excepcionesDeUsuario($idUsuario));

        return [
            'rol' => ['nombre_rol' => $usuario['nombre_usuario'] . ' (rol ' . $usuario['nombre_rol'] . ')'],
            'grupos' => $this->grupos($efectivos, $deRol),
            'acciones' => Autorizacion::PERMISOS,
            'modoUsuario' => true,
        ];
    }

    /**
     * Guarda los permisos efectivos elegidos para un usuario como excepciones a su rol:
     * lo marcado que el rol no tiene se concede; lo desmarcado que el rol sí tiene se quita.
     */
    public function guardarUsuario(int $idUsuario, mixed $seleccion, int $idUsuarioSesion): void
    {
        $usuario = $this->usuario($idUsuario);
        if ((int) $usuario['status'] !== 1) {
            throw new ValidacionException('El usuario está eliminado. Restáuralo antes de cambiar sus permisos.');
        }
        if ($idUsuario === $idUsuarioSesion) {
            throw new ValidacionException('No puedes cambiar tus propios permisos; pídeselo a otro administrador.');
        }

        $elegidos = $this->limpiarSeleccion($seleccion);
        $deRol = $this->autorizacion->permisosDeRol((int) $usuario['id_rol_usuario']);

        $excepciones = [];
        foreach (Autorizacion::PERMISOS as $permiso => $nombre) {
            foreach ($this->rutasValidas() as $ruta => $modulo) {
                $enRol = in_array($permiso, $deRol[$ruta] ?? [], true);
                $marcado = in_array($permiso, $elegidos[$ruta] ?? [], true);
                if ($enRol !== $marcado) {
                    $excepciones[] = [$ruta, $nombre, $marcado ? 'Conceder' : 'Quitar'];
                }
            }
        }

        $db = $this->seguridad();
        $idsModulo = $db->query('SELECT ruta, id_modulo FROM modulos WHERE status = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $idsPermiso = $db->query('SELECT nombre_permiso, id_permisos FROM permisos')->fetchAll(PDO::FETCH_KEY_PAIR);

        $db->beginTransaction();
        try {
            // Las excepciones anteriores quedan inactivas (eliminación lógica) y se activan las vigentes.
            $db->prepare('UPDATE permisos_usuario SET status = 0 WHERE id_usuario = :usuario')->execute([':usuario' => $idUsuario]);
            $reactivar = $db->prepare(
                'UPDATE permisos_usuario SET status = 1 WHERE id_usuario = :usuario AND id_modulo = :modulo AND id_permiso = :permiso AND tipo = :tipo'
            );
            $insertar = $db->prepare(
                'INSERT INTO permisos_usuario (id_usuario, id_modulo, id_permiso, tipo, status) VALUES (:usuario, :modulo, :permiso, :tipo, 1)'
            );
            foreach ($excepciones as [$ruta, $nombre, $tipo]) {
                if (!isset($idsModulo[$ruta], $idsPermiso[$nombre])) {
                    throw new ValidacionException('Los módulos todavía no están sincronizados. Recarga la página e intenta de nuevo.');
                }
                $parametros = [':usuario' => $idUsuario, ':modulo' => $idsModulo[$ruta], ':permiso' => $idsPermiso[$nombre], ':tipo' => $tipo];
                $reactivar->execute($parametros);
                if ($reactivar->rowCount() === 0) {
                    $insertar->execute($parametros);
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        Autorizacion::reiniciar();
        $this->registrarBitacora('Usuarios', 'Permisos', "Actualizó los permisos del usuario {$usuario['nombre_usuario']}: " . count($excepciones) . ' excepciones a su rol.');
    }

    /** Grupos del menú con los permisos marcados (y los del rol, si se comparan). */
    private function grupos(array $marcados, ?array $base = null): array
    {
        $grupos = [];
        foreach (Autorizacion::menu() as $grupo) {
            $modulos = [];
            foreach ($grupo['modulos'] as $ruta => $modulo) {
                $fila = [
                    'ruta' => $ruta,
                    'titulo' => $modulo['titulo'],
                    'icono' => $modulo['icono'],
                    'permisos' => array_values($marcados[$ruta] ?? []),
                ];
                if ($base !== null) {
                    $fila['rol'] = array_values($base[$ruta] ?? []);
                }
                $modulos[] = $fila;
            }
            $grupos[] = ['titulo' => $grupo['titulo'], 'modulos' => $modulos];
        }

        return $grupos;
    }

    private function rutasValidas(): array
    {
        $rutas = [];
        foreach (Autorizacion::menu() as $grupo) {
            $rutas += $grupo['modulos'];
        }

        return $rutas;
    }

    /** Valida la matriz enviada; registrar, modificar o eliminar implican consultar. */
    private function limpiarSeleccion(mixed $seleccion): array
    {
        if (!is_array($seleccion)) {
            throw new ValidacionException('Los permisos enviados no tienen un formato válido.');
        }
        $rutasValidas = $this->rutasValidas();
        $limpia = [];
        foreach ($seleccion as $ruta => $permisos) {
            if (!isset($rutasValidas[$ruta]) || !is_array($permisos)) {
                throw new ValidacionException('Hay un módulo o permiso que no existe en el sistema.');
            }
            $permisos = array_values(array_intersect(array_keys(Autorizacion::PERMISOS), $permisos));
            if ($permisos && !in_array('consultar', $permisos, true)) {
                $permisos[] = 'consultar';
            }
            if ($permisos) {
                $limpia[$ruta] = $permisos;
            }
        }

        return $limpia;
    }

    private function usuario(int $idUsuario): array
    {
        $stmt = $this->seguridad()->prepare(
            'SELECT u.id_usuario, u.nombre_usuario, u.id_rol_usuario, u.status, r.nombre_rol
             FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol_usuario WHERE u.id_usuario = :id'
        );
        $stmt->execute([':id' => $idUsuario]);
        $usuario = $stmt->fetch();
        if (!$usuario) {
            throw new RegistroNoEncontradoException('El usuario solicitado no existe.');
        }

        return $usuario;
    }

    /**
     * Guarda la matriz enviada: ['clientes' => ['consultar', 'registrar'], ...].
     * Registrar, modificar o eliminar implican consultar.
     */
    public function guardar(int $idRol, mixed $seleccion, int $idUsuarioSesion): void
    {
        $rol = $this->rol($idRol);
        $stmt = $this->seguridad()->prepare('SELECT id_rol_usuario FROM usuarios WHERE id_usuario = :id');
        $stmt->execute([':id' => $idUsuarioSesion]);
        $idRolSesion = (int) $stmt->fetchColumn();
        if ((int) $rol['status'] !== 1) {
            throw new ValidacionException('El rol está eliminado. Restáuralo antes de cambiar sus permisos.');
        }
        $limpia = $this->limpiarSeleccion($seleccion);

        if ($idRol === $idRolSesion) {
            $propios = $limpia[self::MODULO_ROLES] ?? [];
            if (!in_array('consultar', $propios, true) || !in_array('modificar', $propios, true)) {
                throw new ValidacionException(
                    'No puedes quitarle a tu propio rol los permisos de consultar y modificar Roles; te quedarías sin poder administrar permisos.'
                );
            }
        }

        $db = $this->seguridad();
        $idsModulo = $db->query('SELECT ruta, id_modulo FROM modulos WHERE status = 1')->fetchAll(PDO::FETCH_KEY_PAIR);
        $idsPermiso = $db->query('SELECT nombre_permiso, id_permisos FROM permisos')->fetchAll(PDO::FETCH_KEY_PAIR);

        $db->beginTransaction();
        try {
            $db->prepare('UPDATE accesos SET status = 0 WHERE id_rol = :rol')->execute([':rol' => $idRol]);
            $reactivar = $db->prepare('UPDATE accesos SET status = 1 WHERE id_rol = :rol AND id_modulo = :modulo AND id_permiso = :permiso');
            $insertar = $db->prepare('INSERT INTO accesos (id_rol, id_modulo, id_permiso, status) VALUES (:rol, :modulo, :permiso, 1)');

            foreach ($limpia as $ruta => $permisos) {
                foreach ($permisos as $permiso) {
                    $parametros = [':rol' => $idRol, ':modulo' => $idsModulo[$ruta] ?? 0, ':permiso' => $idsPermiso[Autorizacion::PERMISOS[$permiso]] ?? 0];
                    if ($parametros[':modulo'] === 0 || $parametros[':permiso'] === 0) {
                        throw new ValidacionException('Los módulos todavía no están sincronizados. Recarga la página e intenta de nuevo.');
                    }
                    // Todos los accesos del rol quedaron en 0: si el UPDATE no cambia nada, la fila no existe.
                    $reactivar->execute($parametros);
                    if ($reactivar->rowCount() === 0) {
                        $insertar->execute($parametros);
                    }
                }
            }
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }

        Autorizacion::reiniciar();
        $total = array_sum(array_map('count', $limpia));
        $this->registrarBitacora('Roles', 'Permisos', "Actualizó los permisos del rol {$rol['nombre_rol']}: $total permisos en " . count($limpia) . ' módulos.');
    }

    private function rol(int $idRol): array
    {
        $stmt = $this->seguridad()->prepare('SELECT id_rol, nombre_rol, descripcion, status FROM roles WHERE id_rol = :id');
        $stmt->execute([':id' => $idRol]);
        $rol = $stmt->fetch();
        if (!$rol) {
            throw new RegistroNoEncontradoException('El rol solicitado no existe.');
        }

        return $rol;
    }
}
