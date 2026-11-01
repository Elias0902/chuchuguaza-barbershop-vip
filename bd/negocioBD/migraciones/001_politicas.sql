-- ==========================================================
-- Migración 001: políticas de servicio (base chuchuguasa_db)
-- Se puede ejecutar varias veces: crea la tabla si no existe y carga
-- el borrador de políticas solo si la tabla está vacía.
--   mysql -u root --default-character-set=utf8mb4 chuchuguasa_db < bd/negocioBD/migraciones/001_politicas.sql
-- ==========================================================

CREATE TABLE IF NOT EXISTS politicas (
    id_politica INT AUTO_INCREMENT PRIMARY KEY,
    categoria VARCHAR(50) NOT NULL DEFAULT 'General',
    titulo VARCHAR(100) NOT NULL,
    contenido TEXT NOT NULL,
    orden INT NOT NULL DEFAULT 1,
    fecha_actualizacion DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status TINYINT DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Borrador para revisar y ajustar desde el módulo «Políticas de servicio».
INSERT INTO politicas (categoria, titulo, contenido, orden)
SELECT * FROM (
    SELECT 'Citas' AS categoria, 'Citas y puntualidad' AS titulo,
           'Agenda tu cita por WhatsApp al 0426-551 4525. Te esperamos hasta 10 minutos después de la hora acordada; pasado ese tiempo, la cita se reprograma o se atiende según la disponibilidad del barbero.' AS contenido, 1 AS orden
    UNION ALL SELECT 'Citas', 'Cancelaciones y cambios',
           'Si no puedes asistir, avísanos con al menos 2 horas de anticipación para reprogramar. Si faltas varias veces sin avisar, podremos pedirte que confirmes tus próximas citas.', 2
    UNION ALL SELECT 'Atención', 'Atención sin cita',
           'También atendemos por orden de llegada de 9:00 a. m. a 8:00 p. m. Las personas con cita tienen prioridad en su horario.', 3
    UNION ALL SELECT 'Pagos', 'Precios y cotizaciones',
           'Los precios de los servicios están publicados en el local. Los diseños especiales, el color y los cambios de look se cotizan antes de empezar, según el trabajo y el largo del cabello.', 4
    UNION ALL SELECT 'Pagos', 'Formas de pago',
           'Aceptamos los métodos de pago indicados en caja. El servicio se paga al terminar; si pagas por pago móvil o transferencia, muestra el comprobante antes de retirarte.', 5
    UNION ALL SELECT 'Garantía', 'Garantía de satisfacción',
           'Si algo de tu corte no quedó como lo pediste, avísanos antes de salir o dentro de los 3 días siguientes y lo retocamos sin costo. No aplica a un estilo distinto al acordado.', 6
    UNION ALL SELECT 'Atención', 'Niños',
           'Los menores de 12 años deben estar acompañados por un adulto responsable durante todo el servicio.', 7
    UNION ALL SELECT 'Bebidas', 'Bebidas alcohólicas',
           'Las bebidas alcohólicas solo se venden a mayores de 18 años y se consumen con moderación dentro del local. Podemos no atender a personas en estado de ebriedad.', 8
    UNION ALL SELECT 'Productos', 'Cambios de productos',
           'Los productos sin abrir y en buen estado se pueden cambiar dentro de los 3 días siguientes a la compra, presentando el comprobante.', 9
    UNION ALL SELECT 'Cursos', 'Inscripciones a cursos',
           'La inscripción reserva tu cupo. Lo abonado no se reembolsa una vez iniciado el curso, pero puedes pasar tu cupo a la siguiente cohorte si avisas antes del inicio.', 10
    UNION ALL SELECT 'General', 'Higiene',
           'Desinfectamos las herramientas y las estaciones entre cada cliente y usamos material desechable cuando corresponde.', 11
) AS borrador
WHERE NOT EXISTS (SELECT 1 FROM politicas);
