<?php

/**
 * Carga DATOS DE PRUEBA en la base del negocio para probar el sistema y el análisis.
 * Todo es inventado (clientes, barberos, cédulas, teléfonos, precios).
 *
 * Usa los mismos modelos del sistema, así cada dato pasa por sus validaciones y
 * reglas: stock, totales de tickets, estados de pago, cajas, nómina...
 *
 *   php bd/negocioBD/semillas/datos_prueba.php                  carga los datos (la base debe estar vacía)
 *   php bd/negocioBD/semillas/datos_prueba.php --vaciar VACIAR  borra TODOS los datos del negocio (menos las políticas)
 */

use App\Models\Barbero;
use App\Models\Buzon;
use App\Models\Categoria;
use App\Models\CierreCaja;
use App\Models\Cita;
use App\Models\Cliente;
use App\Models\Curso;
use App\Models\Gasto;
use App\Models\Inscripcion;
use App\Models\MetodoPago;
use App\Models\Nomina;
use App\Models\Pago;
use App\Models\Producto;
use App\Models\Promocion;
use App\Models\Retiro;
use App\Models\Servicio;
use App\Models\Ticket;

if (PHP_SAPI !== 'cli') {
    exit("Este script solo se ejecuta desde la consola.\n");
}

$raiz = dirname(__DIR__, 3);
require $raiz . '/vendor/autoload.php';
require $raiz . '/config/config.php';

$negocio = new PDO('mysql:host=' . DB_HOST_NEGOCIO . ';dbname=' . DB_NAME_NEGOCIO . ';charset=utf8mb4', DB_USER_NEGOCIO, DB_PASS_NEGOCIO, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$seguridad = new PDO('mysql:host=' . DB_HOST_SECURITY . ';dbname=' . DB_NAME_SECURITY . ';charset=utf8mb4', DB_USER_SECURITY, DB_PASS_SECURITY, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tablas = ['detalle_retiro', 'retiro_dinero', 'gastos_generales', 'pagos', 'detalle_atencion', 'ticket_atencion', 'cierre_caja',
    'nomina', 'inscripcion_curso', 'cursos', 'citas', 'promociones', 'producto', 'categoria', 'servicio', 'metodo_pago',
    'barberos', 'cliente', 'sugerencias', 'analisis_ejecuciones'];

// ---------------------------------------------------------------- vaciar
if (($argv[1] ?? '') === '--vaciar') {
    if (($argv[2] ?? '') !== 'VACIAR') {
        exit("Para confirmar escribe: php bd/negocioBD/semillas/datos_prueba.php --vaciar VACIAR\n");
    }
    $negocio->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($tablas as $tabla) {
        $negocio->exec("DELETE FROM `$tabla`");
        $negocio->exec("ALTER TABLE `$tabla` AUTO_INCREMENT = 1");
    }
    $negocio->exec('SET FOREIGN_KEY_CHECKS = 1');
    $seguridad->prepare("INSERT INTO bitacoras (id_usuario, modulo, accion, descripcion, fecha_bitacora) VALUES (NULL, 'Sistema', 'Vaciar datos', 'Se vaciaron los datos del negocio desde la consola.', NOW(6))")->execute();
    exit("Datos del negocio eliminados (las políticas de servicio se conservaron).\n");
}

foreach (['cliente', 'barberos', 'servicio', 'ticket_atencion'] as $tabla) {
    if ((int) $negocio->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn() > 0) {
        exit("La tabla «$tabla» ya tiene datos. Para no mezclar, vacía primero con --vaciar VACIAR.\n");
    }
}

// ---------------------------------------------------------------- utilidades
mt_srand(2009);
$ultimaBitacora = (int) $seguridad->query('SELECT COALESCE(MAX(id_bitacora), 0) FROM bitacoras')->fetchColumn();
$ahora = time();

/** Elige una clave de un arreglo [clave => peso]. */
$elegir = static function (array $pesos): string|int {
    $total = array_sum($pesos);
    $punto = mt_rand(1, (int) ($total * 1000)) / 1000;
    foreach ($pesos as $clave => $peso) {
        $punto -= $peso;
        if ($punto <= 0) {
            return $clave;
        }
    }
    return array_key_last($pesos);
};
$fecha = static fn(int $marca): string => date('Y-m-d H:i:s', $marca);
$aviso = static fn(string $texto) => print("  · $texto\n");

echo "Cargando datos de prueba...\n";

// ---------------------------------------------------------------- catálogos
$metodos = [];
foreach (['Pago móvil' => 3, 'Efectivo (dólares)' => 2.5, 'Punto de venta' => 1.5, 'Efectivo (bolívares)' => 1, 'Zelle' => 0.6, 'Binance' => 0.4] as $nombre => $peso) {
    $metodos[(new MetodoPago())->registrar(['nombre_metodo' => $nombre])] = ['nombre' => $nombre, 'peso' => $peso];
}

$barberos = [
    'V-14256789' => ['nombre' => 'Jesús', 'apellido' => 'Chacón', 'telefono' => '0414-5512233', 'comision' => '60', 'sueldo' => '0', 'principal' => 1, 'peso' => 0.6],
    'V-20345120' => ['nombre' => 'Luis', 'apellido' => 'Pérez', 'telefono' => '0424-5530011', 'comision' => '50', 'sueldo' => '20', 'principal' => 0, 'peso' => 2.4],
    'V-22789456' => ['nombre' => 'Andrés', 'apellido' => 'Rodríguez', 'telefono' => '0412-5579900', 'comision' => '45', 'sueldo' => '20', 'principal' => 0, 'peso' => 1.0],
    'V-26111843' => ['nombre' => 'Kevin', 'apellido' => 'Mendoza', 'telefono' => '0426-5541212', 'comision' => '40', 'sueldo' => '15', 'principal' => 0, 'peso' => 0.8],
];
foreach ($barberos as $cedula => $b) {
    (new Barbero())->registrar([
        'cedula' => $cedula, 'nombre' => $b['nombre'], 'apellido' => $b['apellido'], 'telefono' => $b['telefono'],
        'porcentaje_comision' => $b['comision'], 'sueldo_base' => $b['sueldo'], 'es_principal' => $b['principal'],
    ]);
}

$servicios = [];
$catalogoServicios = [
    ['Corte clásico', '5.00', 30, 3], ['Degradado (fade)', '6.00', 40, 3], ['Corte + barba', '8.00', 50, 2.5],
    ['Arreglo de barba', '3.00', 20, 1.2], ['Corte de niño', '4.00', 30, 1], ['Corte de dama', '7.00', 45, 0.8],
    ['Diseño en el cabello', '2.00', 15, 0], ['Perfilado de cejas', '1.50', 10, 0], ['Tinte o color', '15.00', 90, 0.15],
    ['Lavado y peinado', '3.00', 20, 0],
];
foreach ($catalogoServicios as [$nombre, $precio, $duracion, $peso]) {
    $id = (new Servicio())->registrar(['nombre_servicio' => $nombre, 'precio' => $precio, 'duracion_minutos' => $duracion]);
    $servicios[$id] = ['nombre' => $nombre, 'precio' => $precio, 'peso' => $peso];
}
$idServicio = array_flip(array_map(static fn(array $s): string => $s['nombre'], $servicios));

$categorias = [];
foreach (['Cuidado del cabello', 'Cuidado de la barba', 'Bebidas', 'Accesorios'] as $nombre) {
    $categorias[$nombre] = (new Categoria())->registrar(['nombre_categoria' => $nombre]);
}
$productos = [];
$catalogoProductos = [
    ['Cera mate para cabello', 'Cuidado del cabello', '8.00', 14, 'producto'], ['Gel fijador', 'Cuidado del cabello', '4.00', 20, 'producto'],
    ['Pomada brillante', 'Cuidado del cabello', '9.00', 7, 'producto'], ['Shampoo anticaspa', 'Cuidado del cabello', '7.00', 10, 'producto'],
    ['Aceite para barba', 'Cuidado de la barba', '10.00', 6, 'producto'], ['Bálsamo para barba', 'Cuidado de la barba', '9.00', 3, 'producto'],
    ['Cerveza nacional (tercio)', 'Bebidas', '1.50', 140, 'bebida'], ['Refresco en lata', 'Bebidas', '1.00', 90, 'bebida'],
    ['Agua mineral', 'Bebidas', '0.80', 60, 'bebida'], ['Café', 'Bebidas', '1.00', 120, 'bebida'],
    ['Peine de bolsillo', 'Accesorios', '1.50', 15, 'producto'],
];
foreach ($catalogoProductos as [$nombre, $categoria, $precio, $stock, $tipo]) {
    $id = (new Producto())->registrar(['nombre_producto' => $nombre, 'id_categoria' => $categorias[$categoria], 'precio' => $precio, 'stock' => $stock]);
    $productos[$id] = ['nombre' => $nombre, 'precio' => $precio, 'tipo' => $tipo];
}
$aviso(count($metodos) . ' métodos de pago, ' . count($barberos) . ' barberos, ' . count($servicios) . ' servicios y ' . count($productos) . ' productos');

// ---------------------------------------------------------------- clientes
$nombres = ['Carlos Mendoza', 'José Gutiérrez', 'Daniel Torres', 'Miguel Hernández', 'Alejandro Suárez', 'Ricardo Álvarez', 'Fernando Rivas',
    'Eduardo Parra', 'Jonathan Colmenares', 'Gabriel Linárez', 'Samuel Escalona', 'Diego Montilla', 'Javier Pacheco', 'Rafael Giménez',
    'Oscar Yépez', 'Héctor Arrieche', 'Manuel Mujica', 'Ángel Freitez', 'David Querales', 'Jorge Piña', 'Sebastián Mogollón',
    'Mateo Castillo', 'Antonio Sánchez', 'Víctor Ochoa', 'Pedro Salas', 'Raúl Graterol', 'Gustavo Perdomo', 'Leonardo Rojas',
    'María Fernández', 'Andreína Lucena', 'Valentina Ramos', 'Gabriela Peraza', 'Luisa Vargas', 'Santiago Barrios', 'Emilio Crespo',
    'Iván Duque', 'Armando Briceño', 'Cristian Agüero', 'Ernesto Lugo', 'Franklin Meléndez'];
$prefijos = ['0412', '0414', '0416', '0424', '0426'];
$clientes = [];
foreach ($nombres as $i => $completo) {
    [$nombre, $apellido] = explode(' ', $completo);
    $correo = $i % 3 === 0 ? strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $nombre . '.' . $apellido)) . '@ejemplo.com' : '';
    $id = (new Cliente())->registrar([
        'nombre' => $nombre, 'apellido' => $apellido,
        'telefono' => $prefijos[$i % 5] . '-' . mt_rand(1000000, 9999999),
        'email' => preg_replace('/[^a-z.@]/', '', $correo),
    ]);
    // Los últimos 5 clientes solo vinieron hace tiempo (para el análisis de clientes inactivos).
    $clientes[$id] = ['frecuencia' => $i < 6 ? 3 : ($i >= count($nombres) - 5 ? 1 : 1.2), 'inactivo' => $i >= count($nombres) - 5];
}
$aviso(count($clientes) . ' clientes');

// ---------------------------------------------------------------- operación diaria
$stock = static function (int $id) use ($negocio): int {
    return (int) $negocio->query('SELECT stock FROM producto WHERE id_producto = ' . $id)->fetchColumn();
};
$pesosDia = [1 => 0.6, 2 => 1.0, 3 => 0.55, 4 => 0.9, 5 => 1.0, 6 => 1.5, 7 => 1.8]; // 1 = lunes (N de date)
$pesosHora = [9 => 0.5, 10 => 0.8, 11 => 1.0, 12 => 0.8, 13 => 0.5, 14 => 0.7, 15 => 1.0, 16 => 1.3, 17 => 1.6, 18 => 1.4, 19 => 0.8];
$pesosDia[7] = 0.6; // domingo: medio día
$pesosDia[6] = 1.8; // sábado: el día más fuerte
$pesosDia[5] = 1.5; // viernes
$pesosServicio = array_map(static fn(array $s): float => $s['peso'], $servicios);
$bebidas = array_keys(array_filter($productos, static fn(array $p): bool => $p['tipo'] === 'bebida'));
$articulos = array_keys(array_filter($productos, static fn(array $p): bool => $p['tipo'] === 'producto'));
$pesosMetodo = array_map(static fn(array $m): float => $m['peso'], $metodos);
$pesosBarbero = array_map(static fn(array $b): float => $b['peso'], $barberos);
$pesosCliente = array_map(static fn(array $c): float => $c['frecuencia'], $clientes);
$activos = array_filter($pesosCliente, static fn($k) => !$clientes[$k]['inactivo'], ARRAY_FILTER_USE_KEY);

$cajaModelo = new CierreCaja();
$totalTickets = 0;
$totalPagos = 0;
$diasConCaja = [3, 2, 1, 0]; // los últimos días llevan caja; hoy queda abierta
$descuadres = [3 => 0.0, 2 => -2.0, 1 => 0.5];

for ($hace = 120; $hace >= 0; $hace--) {
    $inicioDia = strtotime("today -$hace day");
    $diaSemana = (int) date('N', $inicioDia);
    $base = $hace > 60 ? 2.2 : 3.2;
    $cantidad = max(0, (int) round($base * $pesosDia[$diaSemana] + mt_rand(-10, 10) / 10));

    $idCaja = null;
    if (in_array($hace, $diasConCaja, true)) {
        $apertura = min($inicioDia + 8 * 3600 + 50 * 60, $ahora - 120);
        $idCaja = (int) $cajaModelo->registrar(['fecha_inicio' => $fecha($apertura), 'monto_inicial' => '20.00']);
    }

    $horas = [];
    for ($t = 0; $t < $cantidad; $t++) {
        $horas[] = $inicioDia + (int) $elegir($pesosHora) * 3600 + mt_rand(0, 55) * 60;
    }
    sort($horas);

    foreach ($horas as $marca) {
        if ($marca > $ahora - 1800) {
            continue; // nada en el futuro ni en la última media hora
        }
        $idCliente = (int) $elegir($hace > 50 ? $pesosCliente : $activos);
        $cedula = (string) $elegir($pesosBarbero);

        $principal = (int) $elegir($pesosServicio);
        $lineas = [['item' => "s:$principal", 'cantidad' => 1, 'precio_unitario' => $servicios[$principal]['precio']]];
        if (mt_rand(1, 100) <= 22) {
            $extra = mt_rand(0, 1) ? $idServicio['Diseño en el cabello'] : $idServicio['Perfilado de cejas'];
            $lineas[] = ['item' => "s:$extra", 'cantidad' => 1, 'precio_unitario' => $servicios[$extra]['precio']];
        }
        if (mt_rand(1, 100) <= 38) {
            $bebida = $bebidas[array_rand($bebidas)];
            $unidades = mt_rand(1, 100) <= 25 ? 2 : 1;
            if ($stock($bebida) >= $unidades) {
                $lineas[] = ['item' => "p:$bebida", 'cantidad' => $unidades, 'precio_unitario' => $productos[$bebida]['precio']];
            }
        }
        if (mt_rand(1, 100) <= 10) {
            $articulo = $articulos[array_rand($articulos)];
            if ($stock($articulo) >= 1) {
                $lineas[] = ['item' => "p:$articulo", 'cantidad' => 1, 'precio_unitario' => $productos[$articulo]['precio']];
            }
        }

        $idTicket = (int) (new Ticket())->registrar([
            'id_cliente' => $idCliente, 'cedula_barbero' => $cedula, 'fecha_ticket' => $fecha($marca), 'detalles' => $lineas,
        ]);
        $totalTickets++;
        $total = array_sum(array_map(static fn(array $l): float => $l['cantidad'] * (float) $l['precio_unitario'], $lineas));

        // La mayoría paga completo al terminar; unos pocos quedan con saldo o sin pagar.
        $azar = mt_rand(1, 100);
        $pagado = $azar <= ($hace === 0 ? 75 : 98) ? $total : ($azar <= 99 ? round($total / 2, 2) : 0);
        if ($pagado > 0) {
            $metodo = (int) $elegir($pesosMetodo);
            $conReferencia = in_array($metodos[$metodo]['nombre'], ['Pago móvil', 'Zelle', 'Binance'], true);
            (new Pago())->registrar([
                'id_ticket' => $idTicket, 'id_metodo' => $metodo, 'monto' => number_format($pagado, 2, '.', ''),
                'referencia' => $conReferencia ? (string) mt_rand(100000, 999999) : '',
                'fecha_pago' => $fecha(min($marca + mt_rand(25, 60) * 60, $ahora - 60)),
            ]);
            $totalPagos++;
        }
    }

    // Gastos del día (con caja si la hay) y algún retiro.
    if ($idCaja !== null || mt_rand(1, 100) <= 18) {
        $gastos = [['Botellón de agua', '2.50'], ['Artículos de limpieza', '6.00'], ['Hojillas y talco', '4.50'], ['Toallas desechables', '5.00'], ['Hielo para la nevera', '3.00']];
        [$descripcion, $monto] = $gastos[array_rand($gastos)];
        $marcaGasto = min($inicioDia + 11 * 3600, $ahora - 600);
        (new Gasto())->registrar(['descripcion' => $descripcion, 'monto' => $monto, 'fecha_gasto' => $fecha($marcaGasto), 'id_cierre' => $idCaja]);
    }
    if ($hace === 2) {
        (new Retiro())->registrar([
            'cedula_barbero' => 'V-22789456', 'fecha_retiro' => $fecha($inicioDia + 18 * 3600), 'id_cierre' => $idCaja,
            'detalles' => [['id_metodo' => array_search('Efectivo (dólares)', array_map(static fn(array $m): string => $m['nombre'], $metodos)), 'monto' => '10.00', 'motivo' => 'Adelanto de pago']],
        ]);
    }

    if ($idCaja !== null && $hace > 0) {
        // Se cuenta el dinero al cerrar: algunos días cuadra, uno falta y otro sobra.
        $cierre = $inicioDia + 20 * 3600 + 10 * 60;
        $sistema = (float) $negocio->query(
            "SELECT c.monto_inicial
               + COALESCE((SELECT SUM(monto) FROM pagos WHERE status = 1 AND fecha_pago BETWEEN c.fecha_inicio AND '" . $fecha($cierre) . "'), 0)
               + COALESCE((SELECT SUM(monto_pagado) FROM inscripcion_curso WHERE status = 1 AND fecha_inscripcion BETWEEN c.fecha_inicio AND '" . $fecha($cierre) . "'), 0)
               - COALESCE((SELECT SUM(monto) FROM gastos_generales WHERE status = 1 AND id_cierre = c.id_cierre), 0)
               - COALESCE((SELECT SUM(monto_total) FROM retiro_dinero WHERE status = 1 AND id_cierre = c.id_cierre), 0)
             FROM cierre_caja c WHERE c.id_cierre = $idCaja"
        )->fetchColumn();
        $cajaModelo->cerrar($idCaja, number_format(max(0, $sistema + $descuadres[$hace]), 2, '.', ''), $fecha($cierre));
    }
}
$aviso("$totalTickets tickets y $totalPagos pagos en 4 meses, con 3 cierres de caja y la caja de hoy abierta");

// ---------------------------------------------------------------- citas
$citas = 0;
$observaciones = ['Corte y barba', 'Degradado bajo', 'Quiere un diseño lateral', '', '', 'Corte para niño de 8 años', ''];
for ($i = 0; $i < 44; $i++) {
    $marca = strtotime('today -' . mt_rand(1, 58) . ' day') + mt_rand(9, 18) * 3600;
    (new Cita())->registrar([
        'id_cliente' => (int) $elegir($activos), 'cedula_barbero' => (string) $elegir($pesosBarbero), 'fecha_cita' => $fecha($marca),
        'estado_cita' => mt_rand(1, 100) <= 28 ? 'Cancelada' : 'Completada', 'observacion' => $observaciones[array_rand($observaciones)],
    ]);
    $citas++;
}
$ocupados = [];
for ($i = 0; $i < 12; $i++) {
    $cedula = (string) $elegir($pesosBarbero);
    $marca = strtotime('today +' . mt_rand(1, 7) . ' day') + mt_rand(9, 18) * 3600;
    if (isset($ocupados[$cedula . $marca])) {
        continue;
    }
    $ocupados[$cedula . $marca] = true;
    (new Cita())->registrar([
        'id_cliente' => (int) $elegir($activos), 'cedula_barbero' => $cedula, 'fecha_cita' => $fecha($marca),
        'estado_cita' => 'Pendiente', 'observacion' => $observaciones[array_rand($observaciones)],
    ]);
    $citas++;
}
$aviso("$citas citas (pasadas y de la próxima semana)");

// ---------------------------------------------------------------- academia
$cursos = [
    (new Curso())->registrar(['nombre_curso' => 'Barbería básica', 'costo' => '120.00', 'dias_imparte' => 'Lunes, Miércoles', 'horario' => '2:00 pm a 5:00 pm', 'descripcion' => 'Herramientas, higiene, cortes clásicos y atención al cliente.']),
    (new Curso())->registrar(['nombre_curso' => 'Degradados y diseños', 'costo' => '80.00', 'dias_imparte' => 'Sábado', 'horario' => '9:00 am a 12:00 pm', 'descripcion' => 'Fades, transiciones y diseños con máquina.']),
    (new Curso())->registrar(['nombre_curso' => 'Perfilado de barba', 'costo' => '50.00', 'dias_imparte' => 'Martes', 'horario' => '3:00 pm a 5:00 pm', 'descripcion' => 'Arreglo, perfilado y cuidado de la barba.']),
];
$alumnos = array_slice(array_keys($activos), 6, 9);
foreach ($alumnos as $i => $idCliente) {
    $curso = $cursos[$i % 3];
    $costo = [120, 80, 50][$i % 3];
    (new Inscripcion())->registrar([
        'id_curso' => $curso, 'id_cliente' => $idCliente,
        'fecha_inscripcion' => $fecha(strtotime('today -' . mt_rand(10, 85) . ' day') + 10 * 3600),
        'monto_pagado' => number_format($i % 4 === 0 ? $costo / 2 : $costo, 2, '.', ''),
    ]);
}
$aviso(count($cursos) . ' cursos y ' . count($alumnos) . ' inscripciones');

// ---------------------------------------------------------------- promociones
(new Promocion())->registrar(['nombre_promocion' => 'Regreso a clases: corte de niño', 'descuento_porcentaje' => '20', 'fecha_inicio' => date('Y-m-d', strtotime('-70 day')), 'fecha_cierre' => date('Y-m-d', strtotime('-40 day'))]);
(new Promocion())->registrar(['nombre_promocion' => 'Martes de corte + barba', 'descuento_porcentaje' => '15', 'fecha_inicio' => date('Y-m-01'), 'fecha_cierre' => date('Y-m-t')]);
(new Promocion())->registrar(['nombre_promocion' => 'Diciembre con estilo', 'descuento_porcentaje' => '10', 'fecha_inicio' => date('Y-12-01'), 'fecha_cierre' => date('Y-12-24')]);
$aviso('3 promociones (vencida, vigente y programada)');

// ---------------------------------------------------------------- nómina de la semana pasada
$lunes = date('Y-m-d', strtotime('monday last week'));
$domingo = date('Y-m-d', strtotime('sunday last week'));
foreach (array_keys($barberos) as $i => $cedula) {
    $id = (int) (new Nomina())->registrar(['cedula_barbero' => $cedula, 'fecha_inicio' => $lunes, 'fecha_cierre' => $domingo, 'estado_pago' => 'Pendiente']);
    if ($i < 3) {
        (new Nomina())->marcarPagada($id);
    }
}
$aviso("nómina del $lunes al $domingo (3 pagadas y 1 pendiente)");

// ---------------------------------------------------------------- primer análisis del sistema
$resultado = (new Buzon())->analizar('Automático');
$aviso("el sistema analizó los datos y dejó {$resultado['nuevas']} sugerencias en el buzón");

// ---------------------------------------------------------------- bitácora
// Los registros de la carga no se dejan uno por uno en la bitácora: se resume en un solo movimiento.
$seguridad->prepare('DELETE FROM bitacoras WHERE id_bitacora > :id AND id_usuario IS NULL')->execute([':id' => $ultimaBitacora]);
$seguridad->prepare("INSERT INTO bitacoras (id_usuario, modulo, accion, descripcion, fecha_bitacora) VALUES (NULL, 'Sistema', 'Datos de prueba', 'Se cargaron los datos de prueba desde la consola.', NOW(6))")->execute();

echo "Listo. Abre el sistema y revisa el «Buzón de sugerencias» y el «Análisis de ventas».\n";
