-- ==========================================================
-- BASE DE DATOS: Barber Shop VIP Chuchuguaza
-- ==========================================================
CREATE DATABASE IF NOT EXISTS chuchuguasa_db;
USE chuchuguasa_db;

-- ----------------------------------------------------------
-- MÓDULO DE SEGURIDAD Y ACCESOS
-- ----------------------------------------------------------

CREATE TABLE roles (
    id_rol INT AUTO_INCREMENT PRIMARY KEY,
    nombre_rol VARCHAR(100) NOT NULL,
    descripcion VARCHAR(255) NOT NULL,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE usuarios (
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre_usuario VARCHAR(100) NOT NULL,
    password_usuario MEDIUMTEXT NOT NULL,
    email_usuario VARCHAR(100) NOT NULL,
    id_rol_usuario INT,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_rol_usuario) REFERENCES roles(id_rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bitacoras (
    id_bitacora INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario INT,
    modulo VARCHAR(100) NOT NULL,
    accion VARCHAR(100) NOT NULL,
    descripcion VARCHAR(500) NOT NULL,
    fecha_bitacora DATETIME(6) NOT NULL,
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------
-- MÓDULO DE NEGOCIO (Las 18 Entidades de la Barbería)
-- ----------------------------------------------------------

-- 1. CLIENTE
CREATE TABLE cliente (
    id_cliente INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    telefono VARCHAR(15) NOT NULL,
    email VARCHAR(50),
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. BARBEROS (Usa Cédula como PK e incluye al dueño principal)
CREATE TABLE barberos (
    cedula VARCHAR(15) PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL,
    apellido VARCHAR(50) NOT NULL,
    telefono VARCHAR(15),
    es_principal TINYINT DEFAULT 0,
    porcentaje_comision DECIMAL(5,2) DEFAULT 50.00,
    sueldo_base DECIMAL(10,2) DEFAULT 0.00,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. CATEGORIA
CREATE TABLE categoria (
    id_categoria INT AUTO_INCREMENT PRIMARY KEY,
    nombre_categoria VARCHAR(50) NOT NULL,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. PRODUCTO
CREATE TABLE producto (
    id_producto INT AUTO_INCREMENT PRIMARY KEY,
    id_categoria INT,
    nombre_producto VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    stock INT DEFAULT 0,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_categoria) REFERENCES categoria(id_categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. SERVICIO
CREATE TABLE servicio (
    id_servicio INT AUTO_INCREMENT PRIMARY KEY,
    nombre_servicio VARCHAR(100) NOT NULL,
    precio DECIMAL(10,2) NOT NULL,
    duracion_minutos INT DEFAULT 30,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. CITAS
CREATE TABLE citas (
    id_cita INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    cedula_barbero VARCHAR(15),
    fecha_cita DATETIME NOT NULL,
    observacion VARCHAR(255),
    estado_cita ENUM('Pendiente', 'Completada', 'Cancelada') DEFAULT 'Pendiente',
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (cedula_barbero) REFERENCES barberos(cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CURSOS
CREATE TABLE cursos (
    id_curso INT AUTO_INCREMENT PRIMARY KEY,
    nombre_curso VARCHAR(100) NOT NULL,
    descripcion TEXT,
    costo DECIMAL(10,2) NOT NULL,
    dias_imparte VARCHAR(100),
    horario VARCHAR(50),
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. INSCRIPCION CURSO
CREATE TABLE inscripcion_curso (
    id_inscripcion INT AUTO_INCREMENT PRIMARY KEY,
    id_curso INT,
    id_cliente INT,
    fecha_inscripcion DATETIME DEFAULT CURRENT_TIMESTAMP,
    monto_pagado DECIMAL(10,2) NOT NULL,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_curso) REFERENCES cursos(id_curso),
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. METODO DE PAGO
CREATE TABLE metodo_pago (
    id_metodo INT AUTO_INCREMENT PRIMARY KEY,
    nombre_metodo VARCHAR(50) NOT NULL,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. TICKET ATENCION (Cabecera Principal)
CREATE TABLE ticket_atencion (
    id_ticket INT AUTO_INCREMENT PRIMARY KEY,
    id_cliente INT,
    cedula_barbero VARCHAR(15),
    fecha_ticket DATETIME DEFAULT CURRENT_TIMESTAMP,
    total DECIMAL(10,2) NOT NULL,
    estado_pago ENUM('Pendiente', 'Pagado', 'Anulado') DEFAULT 'Pendiente',
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_cliente) REFERENCES cliente(id_cliente),
    FOREIGN KEY (cedula_barbero) REFERENCES barberos(cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. DETALLE ATENCION (Desglose del Ticket)
CREATE TABLE detalle_atencion (
    id_detalle_atencion INT AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT,
    id_servicio INT NULL,
    id_producto INT NULL,
    cantidad INT DEFAULT 1,
    precio_unitario DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (id_ticket) REFERENCES ticket_atencion(id_ticket),
    FOREIGN KEY (id_servicio) REFERENCES servicio(id_servicio),
    FOREIGN KEY (id_producto) REFERENCES producto(id_producto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. PAGOS
CREATE TABLE pagos (
    id_pago INT AUTO_INCREMENT PRIMARY KEY,
    id_ticket INT,
    id_metodo INT,
    monto DECIMAL(10,2) NOT NULL,
    referencia VARCHAR(50),
    fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_ticket) REFERENCES ticket_atencion(id_ticket),
    FOREIGN KEY (id_metodo) REFERENCES metodo_pago(id_metodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. CIERRE DE CAJA
CREATE TABLE cierre_caja (
    id_cierre INT AUTO_INCREMENT PRIMARY KEY,
    fecha_inicio DATETIME NOT NULL,
    fecha_cierre DATETIME NULL,
    monto_inicial DECIMAL(10,2) NOT NULL,
    monto_final_sistema DECIMAL(10,2) DEFAULT 0.00,
    monto_real_caja DECIMAL(10,2) DEFAULT 0.00,
    diferencia DECIMAL(10,2) DEFAULT 0.00,
    estado ENUM('Abierta', 'Cerrada') DEFAULT 'Abierta',
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. GASTOS GENERALES
CREATE TABLE gastos_generales (
    id_gasto INT AUTO_INCREMENT PRIMARY KEY,
    id_cierre INT,
    descripcion VARCHAR(255) NOT NULL,
    monto DECIMAL(10,2) NOT NULL,
    fecha_gasto DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (id_cierre) REFERENCES cierre_caja(id_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. RETIRO DE DINERO (Cabecera de Retiros)
CREATE TABLE retiro_dinero (
    id_retiro INT AUTO_INCREMENT PRIMARY KEY,
    cedula_barbero VARCHAR(15),
    id_cierre INT,
    monto_total DECIMAL(10,2) NOT NULL,
    fecha_retiro DATETIME DEFAULT CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1,
    FOREIGN KEY (cedula_barbero) REFERENCES barberos(cedula),
    FOREIGN KEY (id_cierre) REFERENCES cierre_caja(id_cierre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. DETALLE RETIRO (Desglose del Retiro)
CREATE TABLE detalle_retiro (
    id_detalle_retiro INT AUTO_INCREMENT PRIMARY KEY,
    id_retiro INT,
    id_metodo INT,
    monto DECIMAL(10,2) NOT NULL,
    motivo VARCHAR(255),
    FOREIGN KEY (id_retiro) REFERENCES retiro_dinero(id_retiro),
    FOREIGN KEY (id_metodo) REFERENCES metodo_pago(id_metodo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 17. NOMINA
CREATE TABLE nomina (
    id_nomina INT AUTO_INCREMENT PRIMARY KEY,
    cedula_barbero VARCHAR(15),
    fecha_inicio DATE NOT NULL,
    fecha_cierre DATE NOT NULL,
    total_cortes INT DEFAULT 0,
    monto_comisiones DECIMAL(10,2) DEFAULT 0.00,
    sueldo_base DECIMAL(10,2) DEFAULT 0.00,
    total_pagar DECIMAL(10,2) NOT NULL,
    estado_pago ENUM('Pendiente', 'Pagado') DEFAULT 'Pendiente',
    status TINYINT DEFAULT 1,
    FOREIGN KEY (cedula_barbero) REFERENCES barberos(cedula)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. PROMOCIONES
CREATE TABLE promociones (
    id_promocion INT AUTO_INCREMENT PRIMARY KEY,
    nombre_promocion VARCHAR(100) NOT NULL,
    descuento_porcentaje DECIMAL(5,2) DEFAULT 0.00,
    fecha_inicio DATE NOT NULL,
    fecha_cierre DATE NOT NULL,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- 19. POLÍTICAS DE SERVICIO (se muestran en la landing; borrador en bd/negocioBD/migraciones/001_politicas.sql)
CREATE TABLE politicas (
    id_politica INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL DEFAULT 'General',
    titulo VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. BUZÓN DE SUGERENCIAS DEL SISTEMA (las genera el motor de análisis; ver migraciones/002)
CREATE TABLE sugerencias (
    id_sugerencia INT AUTO_INCREMENT PRIMARY KEY,
    clave VARCHAR(80) NOT NULL,
    categoria VARCHAR(30) NOT NULL,
    prioridad ENUM('alta', 'media', 'baja', 'positiva') NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    detalle VARCHAR(600) NOT NULL,
    icono VARCHAR(40) NOT NULL DEFAULT 'fa-lightbulb',
    ruta VARCHAR(40) NULL,
    texto_enlace VARCHAR(40) NULL,
    estado ENUM('Nueva', 'Leída', 'Aplicada', 'Descartada', 'Resuelta') NOT NULL DEFAULT 'Nueva',
    fecha_generada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_actualizada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    fecha_estado DATETIME NULL,
    status TINYINT DEFAULT 1,
    INDEX idx_sugerencias_clave (clave, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cada vez que el motor revisa los datos (automático o al pulsar «Analizar ahora»).
CREATE TABLE analisis_ejecuciones (
    id_ejecucion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    origen ENUM('Automático', 'Manual') NOT NULL,
    id_usuario INT NULL,
    hallazgos INT NOT NULL DEFAULT 0,
    nuevas INT NOT NULL DEFAULT 0,
    resueltas INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
