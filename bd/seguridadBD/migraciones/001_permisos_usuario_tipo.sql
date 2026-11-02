-- ==========================================================
-- Migración 001 (seguridad): excepciones de permisos por usuario
-- «Conceder» da un permiso que su rol no tiene; «Quitar» le retira uno que sí tiene.
-- Se puede ejecutar varias veces.
--   mysql -u root --default-character-set=utf8mb4 chuchuguasa_seguridad < bd/seguridadBD/migraciones/001_permisos_usuario_tipo.sql
-- ==========================================================

ALTER TABLE permisos_usuario
    ADD COLUMN IF NOT EXISTS tipo ENUM('Conceder', 'Quitar') NOT NULL DEFAULT 'Conceder' AFTER id_permiso;
