<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

class Rol extends CrudModel
{
    protected string $tabla = 'roles';
    protected string $clave = 'id_rol';
    protected string $baseDatos = self::SEGURIDAD;
    protected string $modulo = 'Roles';
    protected string $singular = 'rol';
    protected ?string $campoDescriptivo = 'nombre_rol';

    public function campos(): array
    {
        return [
            'nombre_rol' => [
                'etiqueta' => 'Nombre del rol', 'tipo' => 'texto', 'requerido' => true, 'max' => 100, 'unico' => true,
                'ancho' => 'completo', 'placeholder' => 'Administrador, recepción, barbero...',
            ],
            'descripcion' => ['etiqueta' => 'Descripción', 'tipo' => 'texto', 'requerido' => true, 'max' => 255, 'ancho' => 'completo'],
        ];
    }

    protected function sqlConsulta(): string
    {
        return 'SELECT r.*, (SELECT COUNT(*) FROM usuarios u WHERE u.id_rol_usuario = r.id_rol AND u.status = 1) AS usuarios,
                    (SELECT COUNT(DISTINCT a.id_modulo) FROM accesos a
                       INNER JOIN modulos m ON m.id_modulo = a.id_modulo AND m.status = 1
                      WHERE a.id_rol = r.id_rol AND a.status = 1) AS modulos
                FROM roles r ORDER BY r.status DESC, r.nombre_rol';
    }

    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM usuarios WHERE id_rol_usuario = :id AND status = 1');
        $stmt->execute([':id' => $actual['id_rol']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('Hay usuarios activos con este rol. Asígnales otro rol antes de eliminarlo.');
        }
    }
}
