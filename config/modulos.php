<?php

/**
 * Módulos del panel interno agrupados como aparecen en el sidebar.
 * La clave de cada módulo es su ruta (index.php?url=clave) y debe existir en config/route.php.
 * Los íconos son clases de Font Awesome 5 (assets/css/fonts.min.css).
 */
return [
    'atencion' => [
        'titulo' => 'Atención',
        'modulos' => [
            'citas' => [
                'titulo' => 'Citas',
                'icono' => 'fa-calendar-check',
                'descripcion' => 'Agenda las citas de los clientes con cada barbero y marca si se completaron o cancelaron.',
            ],
            'tickets' => [
                'titulo' => 'Tickets de atención',
                'icono' => 'fa-receipt',
                'descripcion' => 'Registra cada atención con sus servicios y productos; el total y el stock se calculan solos.',
            ],
            'pagos' => [
                'titulo' => 'Pagos',
                'icono' => 'fa-credit-card',
                'descripcion' => 'Registra los pagos de los tickets; al cubrir el total el ticket queda como pagado.',
            ],
        ],
    ],
    'catalogos' => [
        'titulo' => 'Catálogos',
        'modulos' => [
            'clientes' => [
                'titulo' => 'Clientes',
                'icono' => 'fa-users',
                'descripcion' => 'Administra la información y los datos de contacto de tus clientes.',
            ],
            'barberos' => [
                'titulo' => 'Barberos',
                'icono' => 'fa-cut',
                'descripcion' => 'Administra el equipo, su comisión y su sueldo base.',
            ],
            'servicios' => [
                'titulo' => 'Servicios',
                'icono' => 'fa-concierge-bell',
                'descripcion' => 'Configura los servicios, sus precios y su duración.',
            ],
            'productos' => [
                'titulo' => 'Productos y bebidas',
                'icono' => 'fa-box-open',
                'descripcion' => 'Controla los productos y bebidas a la venta, su precio y su stock.',
            ],
            'categorias' => [
                'titulo' => 'Categorías',
                'icono' => 'fa-tags',
                'descripcion' => 'Agrupa los productos por categoría (cuidado capilar, bebidas, accesorios...).',
            ],
            'promociones' => [
                'titulo' => 'Promociones',
                'icono' => 'fa-percent',
                'descripcion' => 'Define descuentos con su porcentaje y su período de vigencia.',
            ],
            'metodos_pago' => [
                'titulo' => 'Métodos de pago',
                'icono' => 'fa-wallet',
                'descripcion' => 'Formas de pago aceptadas: efectivo, pago móvil, punto de venta, divisas...',
            ],
        ],
    ],
    'finanzas' => [
        'titulo' => 'Caja y finanzas',
        'modulos' => [
            'cierres' => [
                'titulo' => 'Cierres de caja',
                'icono' => 'fa-coins',
                'descripcion' => 'Abre la caja del día y ciérrala comparando lo que dice el sistema con lo contado.',
            ],
            'gastos' => [
                'titulo' => 'Gastos generales',
                'icono' => 'fa-file-invoice-dollar',
                'descripcion' => 'Registra los gastos del local y asócialos a la caja abierta.',
            ],
            'retiros' => [
                'titulo' => 'Retiros de dinero',
                'icono' => 'fa-hand-holding-usd',
                'descripcion' => 'Registra los retiros de dinero de la caja desglosados por método de pago.',
            ],
            'nomina' => [
                'titulo' => 'Nómina',
                'icono' => 'fa-money-bill-wave',
                'descripcion' => 'Calcula el pago de cada barbero según sus cortes, su comisión y su sueldo base.',
            ],
        ],
    ],
    'academia' => [
        'titulo' => 'Academia',
        'modulos' => [
            'cursos' => [
                'titulo' => 'Cursos',
                'icono' => 'fa-chalkboard-teacher',
                'descripcion' => 'Administra los cursos de barbería, su costo, días y horario.',
            ],
            'inscripciones' => [
                'titulo' => 'Inscripciones',
                'icono' => 'fa-user-graduate',
                'descripcion' => 'Inscribe clientes en los cursos y controla lo que han pagado.',
            ],
        ],
    ],
    'opinion' => [
        'titulo' => 'Análisis',
        'modulos' => [
            'buzon' => [
                'titulo' => 'Buzón de sugerencias',
                'icono' => 'fa-inbox',
                'descripcion' => 'Ideas que el propio sistema deja cada cierto tiempo para mejorar y vender más según los datos.',
            ],
            'analisis' => [
                'titulo' => 'Análisis de ventas',
                'icono' => 'fa-chart-line',
                'descripcion' => 'Resultados del negocio: ingresos, servicios y productos más vendidos, días y horas de más clientes.',
            ],
        ],
    ],
    'pagina' => [
        'titulo' => 'Página pública',
        'modulos' => [
            'politicas' => [
                'titulo' => 'Políticas de servicio',
                'icono' => 'fa-file-contract',
                'descripcion' => 'Reglas de citas, pagos, garantía y más que se publican en la página de la barbería.',
            ],
        ],
    ],
    'seguridad' => [
        'titulo' => 'Seguridad',
        'modulos' => [
            'usuarios' => [
                'titulo' => 'Usuarios',
                'icono' => 'fa-users-cog',
                'descripcion' => 'Cuentas con acceso al sistema interno y su rol.',
            ],
            'roles' => [
                'titulo' => 'Roles',
                'icono' => 'fa-user-shield',
                'descripcion' => 'Roles que se asignan a los usuarios del sistema.',
            ],
            'bitacora' => [
                'titulo' => 'Bitácora',
                'icono' => 'fa-history',
                'descripcion' => 'Historial de accesos y de todas las operaciones realizadas en el sistema.',
            ],
        ],
    ],
];
