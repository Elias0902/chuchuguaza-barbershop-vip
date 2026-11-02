<?php

/**
 * Manual de usuario por módulo (botón «?» junto a «Nuevo»).
 * Los pasos generales (consultar, registrar, modificar, eliminar, reporte) los arma
 * assets/js/modulo.js con la configuración del módulo; aquí van solo los consejos propios.
 * Las capturas se generan con: cd tests/e2e && npm run capturas-ayuda
 */
return [
    'citas' => [
        'Elige el cliente, el barbero y la fecha y hora; el sistema avisa si el barbero ya tiene una cita a esa hora.',
        'Cuando el cliente sea atendido cambia el estado a «Completada»; si no viene, a «Cancelada».',
    ],
    'tickets' => [
        'Agrega una línea por cada servicio o producto con el botón «Agregar línea»; el total se calcula solo.',
        'Los productos vendidos se descuentan del inventario al guardar el ticket.',
        'El ticket queda «Pendiente» hasta que sus pagos cubran el total.',
    ],
    'pagos' => [
        'Para encontrar un pago rápido escribe su número en el campo «N.º de pago» de la barra de búsqueda.',
        'Al elegir el ticket el monto se llena con el saldo pendiente; cámbialo si el cliente paga una parte.',
        'Escribe la referencia del pago móvil o la transferencia para poder verificarlo después.',
    ],
    'clientes' => [
        'En la cédula elige primero V (venezolano) o E (extranjero) y luego escribe solo los números.',
        'El teléfono sirve para contactar al cliente y para buscarlo rápido en las citas.',
    ],
    'barberos' => [
        'En la cédula elige primero V (venezolano) o E (extranjero) y luego escribe solo los números. No se puede cambiar después.',
        'La comisión es el porcentaje de cada servicio que gana el barbero; se usa para calcular la nómina.',
        'Solo puede haber un barbero principal; al marcar otro, el anterior se desmarca.',
    ],
    'servicios' => [
        'El precio que pongas aquí es el que se propone al agregar el servicio a un ticket.',
    ],
    'productos' => [
        'El stock baja solo con cada venta en un ticket; súbelo aquí cuando llegue mercancía.',
        'Asigna una categoría para encontrar el producto más rápido.',
    ],
    'categorias' => [
        'Las categorías agrupan los productos; no se puede eliminar una que tenga productos activos.',
    ],
    'promociones' => [
        'Define las fechas de inicio y fin; fuera de ese rango la promoción no se muestra.',
    ],
    'metodos_pago' => [
        'Estos son los métodos que aparecen al registrar un pago (efectivo, pago móvil, divisas...).',
    ],
    'cierres' => [
        'Abre la caja al iniciar el día con el monto inicial; los gastos y retiros se asocian a la caja abierta.',
        'Al cerrarla el sistema calcula lo que debería haber; compáralo con lo que contaste.',
    ],
    'gastos' => [
        'El gasto se asigna a la caja abierta. Usa el campo «N.º de gasto» para encontrar uno rápido.',
    ],
    'retiros' => [
        'Registra el dinero que se saca de la caja y quién lo retiró, con su desglose.',
    ],
    'nomina' => [
        'Elige el barbero y el período; los cortes y la comisión se calculan con los tickets de esas fechas.',
    ],
    'cursos' => [
        'En «Días de clase» toca los días en que se dicta el curso; puedes marcar varios.',
        'Usa el filtro «Día» de la barra para ver solo los cursos de un día.',
    ],
    'inscripciones' => [
        'Usa los filtros «Curso» y «Alumno» de la barra para ver las inscripciones de un curso o de una persona.',
        'Al elegir el curso el monto se llena con su costo; cámbialo si el alumno abonó una parte.',
        'Un mismo cliente no puede inscribirse dos veces en el mismo curso.',
    ],
    'politicas' => [
        'Las políticas activas se muestran en la página principal en el orden indicado.',
    ],
    'usuarios' => [
        'El rol decide qué puede hacer cada usuario. Con el botón «Permisos» le das o quitas permisos solo a esa persona.',
        'Si un usuario se bloquea por intentos fallidos, usa «Desbloquear».',
    ],
    'roles' => [
        'Con el botón «Permisos» marcas qué puede consultar, registrar, modificar o eliminar el rol en cada módulo.',
    ],
    'respaldos' => [
        'El sistema respalda solo las dos bases de datos cada 24 horas; con «Respaldar ahora» haces uno cuando quieras.',
        'Descarga un respaldo de vez en cuando y guárdalo fuera de esta computadora (pendrive o la nube).',
        'Restaurar devuelve la base a la fecha del respaldo; antes el sistema guarda otro respaldo del estado actual.',
    ],
    'bitacora' => [
        'Aquí queda registrado quién hizo cada operación y cuándo. Solo se puede consultar.',
    ],
];
