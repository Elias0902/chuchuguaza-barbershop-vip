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
        $actuales = $this->autorizacion->permisosDeRol($idRol);

        $grupos = [];
        foreach (Autorizacion::menu() as $grupo) {
            $modulos = [];
            foreach ($grupo['modulos'] as $ruta => $modulo) {
                $modulos[] = [
                    'ruta' => $ruta,
                    'titulo' => $modulo['titulo'],
                    'icono' => $modulo['icono'],
                    'permisos' => array_values($actuales[$ruta] ?? []),
                ];
            }
            $grupos[] = ['titulo' => $grupo['titulo'], 'modulos' => $modulos];
        }

        return ['rol' => $rol, 'grupos' => $grupos, 'acciones' => Autorizacion::PERMISOS];
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
        if (!is_array($seleccion)) {
            throw new ValidacionException('Los permisos enviados no tienen un formato válido.');
        }

        $rutasValidas = [];
        foreach (Autorizacion::menu() as $grupo) {
            $rutasValidas += $grupo['modulos'];
        }

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
