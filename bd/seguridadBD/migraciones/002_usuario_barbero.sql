-- ==========================================================
-- Migración 002 (seguridad): vincular un usuario con un barbero
-- Un usuario vinculado solo ve y registra sus propios tickets y citas.
-- Se puede ejecutar varias veces.
--   mysql -u root --default-character-set=utf8mb4 chuchuguasa_seguridad < bd/seguridadBD/migraciones/002_usuario_barbero.sql
-- ==========================================================

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS cedula_barbero VARCHAR(15) DEFAULT NULL AFTER id_rol_usuario;
