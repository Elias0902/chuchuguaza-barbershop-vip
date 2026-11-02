<?php

/**
 * Crea o actualiza los roles base de config/roles.php con sus permisos.
 * Si un rol ya existe (por su nombre) se le reemplazan los permisos por los de la configuración.
 *
 *   php bd/seguridadBD/semillas/roles.php
 */

use App\Core\Autorizacion;
use App\Models\Permiso;

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la consola.\n");
}

$raiz = dirname(__DIR__, 3);
require $raiz . '/vendor/autoload.php';
require $raiz . '/config/config.php';

$seguridad = new PDO('mysql:host=' . DB_HOST_SECURITY . ';dbname=' . DB_NAME_SECURITY . ';charset=utf8mb4', DB_USER_SECURITY, DB_PASS_SECURITY, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$letras = ['C' => 'consultar', 'R' => 'registrar', 'M' => 'modificar', 'E' => 'eliminar'];
$rutas = [];
foreach (Autorizacion::menu() as $grupo) {
    $rutas = array_merge($rutas, array_keys($grupo['modulos']));
}

// Los permisos se guardan en nombre de un administrador activo (queda en la bitácora).
$idAdmin = (int) $seguridad->query(
    "SELECT u.id_usuario FROM usuarios u INNER JOIN roles r ON r.id_rol = u.id_rol_usuario
     WHERE r.nombre_rol = 'Administrador' AND u.status = 1 ORDER BY u.id_usuario LIMIT 1"
)->fetchColumn();
$_SESSION['s_usuario']['id_usuario'] = $idAdmin ?: null;

$permiso = new Permiso();
foreach (require $raiz . '/config/roles.php' as $nombre => $rol) {
    $buscar = $seguridad->prepare('SELECT id_rol FROM roles WHERE nombre_rol = :nombre ORDER BY status DESC, id_rol LIMIT 1');
    $buscar->execute([':nombre' => $nombre]);
    $idRol = (int) $buscar->fetchColumn();
    if ($idRol === 0) {
        $seguridad->prepare('INSERT INTO roles (nombre_rol, descripcion, status) VALUES (:nombre, :descripcion, 1)')
            ->execute([':nombre' => $nombre, ':descripcion' => $rol['descripcion']]);
        $idRol = (int) $seguridad->lastInsertId();
        $accion = 'creado';
    } else {
        $seguridad->prepare('UPDATE roles SET descripcion = :descripcion, status = 1 WHERE id_rol = :id')
            ->execute([':descripcion' => $rol['descripcion'], ':id' => $idRol]);
        $accion = 'actualizado';
    }

    $seleccion = [];
    foreach ($rutas as $ruta) {
        $codigo = $rol['permisos'] === '*' ? 'CRME' : ($rol['permisos'][$ruta] ?? '');
        foreach (str_split($codigo) as $letra) {
            if ($letra !== '') {
                $seleccion[$ruta][] = $letras[$letra];
            }
        }
    }
    // Con id de sesión 0 no se aplica la protección «no te quites Roles a ti mismo».
    $permiso->guardar($idRol, $seleccion, 0);
    echo "Rol $nombre $accion: " . count($seleccion) . " módulos.\n";
}
