<?php

/**
 * Roles base del sistema y lo que puede hacer cada uno por módulo.
 * C = consultar, R = registrar, M = modificar, E = eliminar (incluye restaurar).
 * Se cargan con: php bd/seguridadBD/semillas/roles.php
 * Después se pueden ajustar desde Configuración → Roles → «Permisos».
 * Explicación para el cliente: docs/roles-y-permisos.txt
 */
return [
    'Administrador' => [
        'descripcion' => 'Acceso total al sistema',
        'permisos' => '*',
    ],
    'Finanzas' => [
        'descripcion' => 'Controla la caja, los pagos, la nómina, los productos y el análisis del negocio',
        'permisos' => [
            'citas' => 'C', 'tickets' => 'CM', 'pagos' => 'CRME',
            'clientes' => 'C', 'barberos' => 'CM', 'servicios' => 'CRME', 'productos' => 'CRME', 'categorias' => 'CRME',
            'promociones' => 'CRME', 'metodos_pago' => 'CRME',
            'cierres' => 'CRME', 'gastos' => 'CRME', 'retiros' => 'CRME', 'nomina' => 'CRME',
            'cursos' => 'CRME', 'inscripciones' => 'CRME',
            'buzon' => 'CM', 'analisis' => 'C',
            'bitacora' => 'C', 'respaldos' => 'CR',
        ],
    ],
    'Recepción' => [
        'descripcion' => 'Atiende a los clientes: agenda citas, registra tickets y cobra',
        'permisos' => [
            'citas' => 'CRME', 'tickets' => 'CRM', 'pagos' => 'CR',
            'clientes' => 'CRM', 'barberos' => 'C', 'servicios' => 'C', 'productos' => 'C', 'promociones' => 'C',
            'cierres' => 'C', 'cursos' => 'C', 'inscripciones' => 'CR',
        ],
    ],
    'Barbero' => [
        'descripcion' => 'Registra sus propios cortes y ve su agenda',
        'permisos' => [
            'tickets' => 'CR', 'citas' => 'C', 'clientes' => 'CR',
        ],
    ],
];
