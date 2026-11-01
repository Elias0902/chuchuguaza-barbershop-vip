-- ==========================================================
-- BASE DE DATOS: Módulo de Seguridad (Barbería Chuchuguaza)
-- ==========================================================
CREATE DATABASE IF NOT EXISTS chuchuguasa_seguridad;
USE chuchuguasa_seguridad;

-- 1. ROLES
CREATE TABLE roles (
    id_rol INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    status INT(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 2. MODULOS
CREATE TABLE modulos (
    id_modulo INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre_modulo VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL DEFAULT '',
    status INT(11) NOT NULL DEFAULT 1,
    tipo_modulo ENUM('Seguridad','Negocio') NOT NULL,
    ruta VARCHAR(100) NOT NULL DEFAULT '',
    icono VARCHAR(50) NOT NULL DEFAULT 'fa-circle'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 3. PERMISOS
CREATE TABLE permisos (
    id_permisos INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre_permiso VARCHAR(100) NOT NULL,
    tipo ENUM('CRUD','Especial') NOT NULL DEFAULT 'Especial'
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 4. ACCESOS (Relación Rol - Módulo - Permiso)
CREATE TABLE accesos (
    id_accesos INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_rol INT(11) NOT NULL,
    id_modulo INT(11) NOT NULL,
    id_permiso INT(11) NOT NULL,
    status INT(11) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_rol) REFERENCES roles(id_rol) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_modulo) REFERENCES modulos(id_modulo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_permiso) REFERENCES permisos(id_permisos) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 5. USUARIOS
CREATE TABLE usuarios (
    id_usuario INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_cliente VARCHAR(11) DEFAULT NULL,
    nombre_usuario VARCHAR(100) NOT NULL,
    password_usuario MEDIUMTEXT NOT NULL,
    email_usuario VARCHAR(100) NOT NULL,
    email_verificado INT(11) NOT NULL DEFAULT 0,
    id_rol_usuario INT(11) NOT NULL,
    avatar VARCHAR(100) NOT NULL DEFAULT 'Assets/img/default.PNG',
    ultimo_acceso DATETIME(6) DEFAULT NULL,
    fecha_registro DATETIME(6) DEFAULT NULL,
    status INT(11) NOT NULL DEFAULT 1,
    intentos_login INT(10) UNSIGNED NOT NULL DEFAULT 0,
    bloqueado_hasta DATETIME DEFAULT NULL,
    FOREIGN KEY (id_rol_usuario) REFERENCES roles(id_rol) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 6. PERMISOS DE USUARIO (Excepciones o permisos directos)
CREATE TABLE permisos_usuario (
    id_permiso_usuario INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT(11) NOT NULL,
    id_modulo INT(11) NOT NULL,
    id_permiso INT(11) NOT NULL,
    status INT(11) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_modulo) REFERENCES modulos(id_modulo) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_permiso) REFERENCES permisos(id_permisos) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 7. BITACORAS
CREATE TABLE bitacoras (
    id_bitacora INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT(11) DEFAULT NULL,
    modulo VARCHAR(100) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    fecha_bitacora DATETIME(6) NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 8. NOTIFICACIONES
CREATE TABLE notificaciones (
    id_notificaciones INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT(11) NOT NULL,
    titulo VARCHAR(80) NOT NULL,
    descripcion_notificacion VARCHAR(400) NOT NULL,
    enlace VARCHAR(100) NOT NULL,
    alcance VARCHAR(50) NOT NULL,
    fecha_notificacion DATETIME(6) NOT NULL,
    leido INT(11) NOT NULL DEFAULT 1,
    tipo VARCHAR(50) NOT NULL,
    status INT(11) NOT NULL DEFAULT 1,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 9. RESPALDOS
CREATE TABLE respaldos (
    id_respaldo INT(11) AUTO_INCREMENT PRIMARY KEY,
    nombre_archivo VARCHAR(100) NOT NULL,
    tipo_respaldo VARCHAR(50) NOT NULL,
    ruta_almacenamiento VARCHAR(255) NOT NULL,
    peso_kb DECIMAL(10,2) NOT NULL,
    total_registros INT(11) NOT NULL,
    id_usuario INT(11) NOT NULL,
    fecha_creacion DATETIME DEFAULT NULL,
    tipo_bd VARCHAR(100) NOT NULL,
    formato VARCHAR(50) NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- 10. TOKENS (Para recuperación de contraseña y sesiones)
CREATE TABLE tokens (
    id_tokens INT(11) AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT(11) NOT NULL,
    codigo VARCHAR(11) NOT NULL,
    token VARCHAR(100) NOT NULL,
    expira DATETIME DEFAULT NULL,
    usado INT(11) NOT NULL DEFAULT 0,
    fecha_creacion DATETIME DEFAULT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;