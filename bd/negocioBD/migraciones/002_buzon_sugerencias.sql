-- ==========================================================
-- Migración 002: buzón de sugerencias del sistema (base chuchuguasa_db)
-- Nadie escribe estas sugerencias: el motor de análisis revisa los datos cada
-- cierto tiempo y deja aquí ideas para mejorar y vender más. Cada situación se
-- identifica por su «clave» para no repetirla mientras siga abierta.
--   mysql -u root --default-character-set=utf8mb4 chuchuguasa_db < bd/negocioBD/migraciones/002_buzon_sugerencias.sql
-- ==========================================================

CREATE TABLE IF NOT EXISTS sugerencias (
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
CREATE TABLE IF NOT EXISTS analisis_ejecuciones (
    id_ejecucion INT AUTO_INCREMENT PRIMARY KEY,
    fecha_ejecucion DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    origen ENUM('Automático', 'Manual') NOT NULL,
    id_usuario INT NULL,
    hallazgos INT NOT NULL DEFAULT 0,
    nuevas INT NOT NULL DEFAULT 0,
    resueltas INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
