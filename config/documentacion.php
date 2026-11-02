<?php

/**
 * Centro de ayuda: preguntas frecuentes y documentación general del sistema.
 * Cada tema tiene preguntas con su respuesta; «ruta» (opcional) enlaza al módulo.
 */
return [
    [
        'titulo' => 'Primeros pasos',
        'icono' => 'fa-flag-checkered',
        'preguntas' => [
            ['¿Qué es este sistema?', 'Es el sistema interno de Chuchuguaza Barbershop: agenda de citas, tickets de atención, pagos, caja, nómina de los barberos, inventario de productos, academia y la página pública.'],
            ['¿Cómo entro y salgo?', 'Entra con tu usuario y contraseña en «Acceso interno». Para salir abre tu perfil arriba a la derecha y pulsa «Cerrar sesión». Si te equivocas tres veces seguidas, la cuenta se bloquea 30 minutos.'],
            ['Olvidé mi contraseña', 'En la pantalla de inicio pulsa «¿Olvidaste tu contraseña?», escribe tu usuario o correo y abre el enlace que te llega. El enlace vence en 30 minutos y solo sirve una vez.'],
            ['¿Por qué no veo algunos módulos?', 'Cada usuario ve solo lo que permite su rol. Si necesitas otro módulo, pídele al administrador que cambie los permisos de tu rol o que te dé un permiso solo a ti.', 'roles'],
            ['¿Cómo cambio entre modo claro y oscuro?', 'Con el botón del sol o la luna en la barra superior. El sistema recuerda tu elección en esa computadora.'],
            ['¿Cómo cambio mi foto o mi contraseña?', 'Abre tu perfil arriba a la derecha y entra en «Mi perfil».', 'perfil'],
        ],
    ],
    [
        'titulo' => 'Día a día en la barbería',
        'icono' => 'fa-cut',
        'preguntas' => [
            ['¿Cuál es el orden de trabajo de un día?', '1) Abrir la caja con el monto inicial. 2) Atender las citas y a los clientes que llegan. 3) Registrar un ticket por cada atención. 4) Registrar los pagos. 5) Al final del día, cerrar la caja comparando con lo contado.', 'cierres'],
            ['¿Cómo registro un corte?', 'En «Tickets de atención» pulsa «Nuevo ticket», elige el cliente y el barbero, agrega el servicio (y los productos si vendiste) y guarda. El total se calcula solo.', 'tickets'],
            ['¿Cómo cobro un ticket?', 'En «Pagos» pulsa «Nuevo pago» y elige el ticket: el monto se llena con lo que falta. Si paga en partes, registra un pago por cada parte.', 'pagos'],
            ['¿Cómo encuentro un pago rápido?', 'En «Pagos» escribe el número del pago en «N.º de pago». También puedes buscar por la referencia del pago móvil.', 'pagos'],
            ['Un cliente no vino a su cita', 'Abre la cita con el lápiz y cambia el estado a «Cancelada».', 'citas'],
            ['Me equivoqué en un ticket', 'Si todavía no tiene pagos puedes modificarlo con el lápiz. Si hay que anularlo, usa la papelera: el stock de los productos vuelve al inventario.', 'tickets'],
        ],
    ],
    [
        'titulo' => 'Caja y finanzas',
        'icono' => 'fa-coins',
        'preguntas' => [
            ['¿Qué pasa si no abro la caja?', 'Los gastos y retiros se asocian a la caja abierta; sin caja abierta no se pueden registrar.', 'cierres'],
            ['¿Cómo se calcula la nómina?', 'Por cada barbero: los servicios que atendió en el período por su porcentaje de comisión, más su sueldo base.', 'nomina'],
            ['¿Dónde veo cuánto se vendió?', 'En «Análisis de ventas» ves ingresos, servicios y productos más vendidos y los días y horas de más clientes.', 'analisis'],
            ['¿Qué es el buzón de sugerencias?', 'Son ideas que el propio sistema genera revisando tus datos: productos que se agotan, clientes que no vuelven, horas flojas... Nadie las escribe a mano.', 'buzon'],
        ],
    ],
    [
        'titulo' => 'Inventario y academia',
        'icono' => 'fa-box-open',
        'preguntas' => [
            ['¿Cómo subo el stock cuando llega mercancía?', 'En «Productos y bebidas» abre el producto con el lápiz y cambia el stock.', 'productos'],
            ['¿Cómo inscribo un alumno?', 'En «Inscripciones» pulsa «Nueva inscripción», elige el curso y el cliente y escribe lo que pagó. Con los filtros «Curso» y «Alumno» ves las inscripciones de cada uno.', 'inscripciones'],
            ['¿Cómo elijo los días de un curso?', 'En el formulario del curso toca los días de clase (Lun a Dom); puedes marcar varios.', 'cursos'],
        ],
    ],
    [
        'titulo' => 'Configuración y seguridad',
        'icono' => 'fa-cog',
        'preguntas' => [
            ['¿Qué roles hay?', 'Administrador (todo el sistema), Finanzas (caja, pagos, nómina, productos y análisis), Recepción (citas, clientes, tickets y pagos) y Barbero (registra sus cortes y ve su agenda). El administrador puede ajustar cada rol en «Roles».', 'roles'],
            ['¿Cómo le doy a un usuario un permiso que su rol no tiene?', 'En «Usuarios» pulsa «Permisos» en su fila y marca o desmarca lo que necesites. Las casillas resaltadas son excepciones solo para esa persona.', 'usuarios'],
            ['¿Cómo vinculo un usuario con un barbero?', 'En «Usuarios», al modificar la cuenta, elige el barbero en «Barbero vinculado». Así ese usuario solo ve y registra sus propios tickets.', 'usuarios'],
            ['¿Se respalda la información?', 'Sí. El sistema hace un respaldo automático de las dos bases de datos cada 24 horas y guarda los últimos 14. También puedes pulsar «Respaldar ahora» y descargar el archivo.', 'respaldos'],
            ['¿Cómo restauro un respaldo?', 'En «Respaldos» pulsa el botón de restaurar en la fila. Antes de restaurar el sistema guarda otro respaldo del estado actual por si quieres deshacerlo.', 'respaldos'],
            ['¿Quién hizo un cambio?', 'En «Bitácora» queda cada operación con el usuario, el módulo y la fecha.', 'bitacora'],
            ['¿Qué significa «eliminado»?', 'Nada se borra de verdad: lo eliminado pasa a la pestaña «Eliminados» y se puede restaurar.'],
        ],
    ],
];
