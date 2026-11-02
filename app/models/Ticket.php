<?php

namespace App\Models;

use App\Core\CrudModel;
use App\Core\ValidacionException;
use PDO;

/**
 * Ticket de atención (cabecera) con su detalle de servicios y productos.
 * El total se calcula con las líneas y el stock de los productos se descuenta
 * al registrar y se devuelve al modificar o anular (eliminación lógica).
 */
class Ticket extends CrudModel
{
    public const ESTADOS_PAGO = ['Pendiente', 'Pagado', 'Anulado'];

    use SoloSusRegistros;

    protected string $tabla = 'ticket_atencion';
    protected string $clave = 'id_ticket';
    protected string $modulo = 'Tickets de atención';
    protected string $singular = 'ticket';

    /** Líneas validadas en reglasNegocio() que se guardan en despuesDeGuardar(). */
    private array $lineas = [];

    public function campos(): array
    {
        return [
            'id_cliente' => [
                'etiqueta' => 'Cliente', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'cliente', 'clave' => 'id_cliente', 'texto' => "CONCAT(nombre, ' ', apellido, ' · ', telefono)"],
            ],
            'cedula_barbero' => [
                'etiqueta' => 'Barbero', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'barberos', 'clave' => 'cedula', 'claveTexto' => true, 'texto' => "CONCAT(nombre, ' ', apellido)"],
            ],
            'fecha_ticket' => ['etiqueta' => 'Fecha y hora', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
            'detalles' => [
                'etiqueta' => 'Servicios y productos', 'tipo' => 'detalle', 'requerido' => true, 'ancho' => 'completo',
                'total' => ['cantidad', 'precio_unitario'],
                'columnas' => [
                    'item' => [
                        'etiqueta' => 'Servicio o producto', 'tipo' => 'relacion', 'fuente' => 'items', 'requerido' => true,
                        'autollenar' => ['campo' => 'precio_unitario', 'dato' => 'precio'],
                    ],
                    'cantidad' => ['etiqueta' => 'Cant.', 'tipo' => 'entero', 'requerido' => true, 'min' => 1, 'maxValor' => 999, 'defecto' => 1],
                    'precio_unitario' => ['etiqueta' => 'Precio unit.', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0, 'maxValor' => 99999999.99],
                ],
            ],
            'total' => ['etiqueta' => 'Total', 'tipo' => 'decimal', 'formulario' => false],
            'estado_pago' => ['etiqueta' => 'Estado de pago', 'tipo' => 'opcion', 'opciones' => self::ESTADOS_PAGO, 'formulario' => false],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT t.*, CONCAT(c.nombre, ' ', c.apellido) AS cliente, CONCAT(b.nombre, ' ', b.apellido) AS barbero,
                    COALESCE(pg.pagado, 0) AS pagado, t.total - COALESCE(pg.pagado, 0) AS saldo,
                    (SELECT GROUP_CONCAT(CONCAT(d.cantidad, ' × ', COALESCE(s.nombre_servicio, pr.nombre_producto)) SEPARATOR ', ')
                       FROM detalle_atencion d
                       LEFT JOIN servicio s ON s.id_servicio = d.id_servicio
                       LEFT JOIN producto pr ON pr.id_producto = d.id_producto
                      WHERE d.id_ticket = t.id_ticket) AS conceptos
                FROM ticket_atencion t
                LEFT JOIN cliente c ON c.id_cliente = t.id_cliente
                LEFT JOIN barberos b ON b.cedula = t.cedula_barbero
                LEFT JOIN (SELECT id_ticket, SUM(monto) AS pagado FROM pagos WHERE status = 1 GROUP BY id_ticket) pg
                       ON pg.id_ticket = t.id_ticket
                WHERE {$this->condicionBarbero('t')}
                ORDER BY t.status DESC, t.fecha_ticket DESC";
    }

    /** Agrega al registro sus líneas con el formato del formulario ("s:3" servicio, "p:5" producto). */
    public function obtener(int|string $id): ?array
    {
        $ticket = $this->verificarDueno(parent::obtener($id));
        if ($ticket === null) {
            return null;
        }

        $ticket['detalles'] = array_map(static fn(array $linea): array => [
            'item' => $linea['id_servicio'] !== null ? 's:' . $linea['id_servicio'] : 'p:' . $linea['id_producto'],
            'cantidad' => $linea['cantidad'],
            'precio_unitario' => $linea['precio_unitario'],
        ], $this->lineasGuardadas((int) $id));

        return $ticket;
    }

    /** Opciones del detalle: servicios y productos en un mismo select, con su precio. */
    public function opcionesFormulario(): array
    {
        $servicios = $this->db()->query(
            "SELECT CONCAT('s:', id_servicio) AS valor, nombre_servicio AS texto, status = 1 AS activo, precio, 'Servicios' AS grupo
             FROM servicio ORDER BY nombre_servicio"
        )->fetchAll();
        $productos = $this->db()->query(
            "SELECT CONCAT('p:', id_producto) AS valor, CONCAT(nombre_producto, ' (stock ', stock, ')') AS texto,
                    status = 1 AND stock > 0 AS activo, precio, 'Productos y bebidas' AS grupo
             FROM producto ORDER BY nombre_producto"
        )->fetchAll();

        $items = array_map(static function (array $fila): array {
            $fila['activo'] = (int) $fila['activo'] === 1;
            return $fila;
        }, array_merge($servicios, $productos));

        return parent::opcionesFormulario() + ['items' => $items];
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $this->asignarBarbero($datos);
        $lineas = $this->limpiarDetalles('detalles', $entrada['detalles'] ?? null);
        $anteriores = $actual !== null ? $this->lineasGuardadas((int) $actual['id_ticket']) : [];

        $serviciosPrevios = array_filter(array_column($anteriores, 'id_servicio'));
        $productosPrevios = [];
        foreach ($anteriores as $linea) {
            if ($linea['id_producto'] !== null) {
                $productosPrevios[$linea['id_producto']] = ($productosPrevios[$linea['id_producto']] ?? 0) + (int) $linea['cantidad'];
            }
        }

        $this->lineas = [];
        $cantidadPorProducto = [];
        $total = 0.0;
        foreach ($lineas as $i => $linea) {
            if (!preg_match('/\A([sp]):(\d+)\z/', (string) $linea['item'], $partes)) {
                throw new ValidacionException('Línea ' . ($i + 1) . ': selecciona un servicio o producto válido.', 'detalles');
            }
            $id = (int) $partes[2];
            $esServicio = $partes[1] === 's';
            $fila = $this->itemCatalogo($esServicio, $id);
            if ($fila === null) {
                throw new ValidacionException('Línea ' . ($i + 1) . ': el servicio o producto no existe.', 'detalles');
            }
            $usadoAntes = $esServicio ? in_array($id, $serviciosPrevios) : isset($productosPrevios[$id]);
            if ((int) $fila['status'] !== 1 && !$usadoAntes) {
                throw new ValidacionException('Línea ' . ($i + 1) . ': «' . $fila['nombre'] . '» está inactivo.', 'detalles');
            }
            if (!$esServicio) {
                $cantidadPorProducto[$id] = ($cantidadPorProducto[$id] ?? 0) + $linea['cantidad'];
                $disponible = (int) $fila['stock'] + ($productosPrevios[$id] ?? 0);
                if ($cantidadPorProducto[$id] > $disponible) {
                    throw new ValidacionException('Stock insuficiente de «' . $fila['nombre'] . '»: hay ' . $disponible . ' disponible(s).', 'detalles');
                }
            }

            $total += $linea['cantidad'] * (float) $linea['precio_unitario'];
            $this->lineas[] = [
                'id_servicio' => $esServicio ? $id : null,
                'id_producto' => $esServicio ? null : $id,
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
            ];
        }

        $datos['total'] = number_format($total, 2, '.', '');
        if ($actual === null) {
            $datos['estado_pago'] = 'Pendiente';
        }
    }

    protected function antesDeModificar(array $actual): void
    {
        if ($actual['estado_pago'] === 'Anulado') {
            throw new ValidacionException('El ticket está anulado y no se puede modificar.');
        }
    }

    protected function despuesDeGuardar(PDO $db, int|string $id, array $datos, ?array $actual): void
    {
        if ($actual !== null) {
            $this->devolverStock($db, (int) $id);
            $db->prepare('DELETE FROM detalle_atencion WHERE id_ticket = :id')->execute([':id' => $id]);
        }

        $insertar = $db->prepare(
            'INSERT INTO detalle_atencion (id_ticket, id_servicio, id_producto, cantidad, precio_unitario)
             VALUES (:id_ticket, :id_servicio, :id_producto, :cantidad, :precio_unitario)'
        );
        foreach ($this->lineas as $linea) {
            $insertar->execute([':id_ticket' => $id] + $this->parametros($linea));
        }
        $this->descontarStock($db, (int) $id);

        if ($actual !== null) {
            self::recalcularEstadoPago($db, (int) $id);
        }
    }

    /** Anular: no se permite con pagos activos y devuelve el stock de los productos. */
    protected function antesDeEliminar(PDO $db, array $actual): void
    {
        $stmt = $db->prepare('SELECT COUNT(*) FROM pagos WHERE id_ticket = :id AND status = 1');
        $stmt->execute([':id' => $actual['id_ticket']]);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new ValidacionException('El ticket tiene pagos registrados. Elimina primero sus pagos en el módulo Pagos.');
        }
        $this->devolverStock($db, (int) $actual['id_ticket']);
    }

    protected function despuesDeEliminar(PDO $db, array $actual): void
    {
        self::recalcularEstadoPago($db, (int) $actual['id_ticket']);
    }

    protected function antesDeRestaurar(PDO $db, array $actual): void
    {
        $this->descontarStock($db, (int) $actual['id_ticket']);
    }

    protected function despuesDeRestaurar(PDO $db, array $actual): void
    {
        self::recalcularEstadoPago($db, (int) $actual['id_ticket']);
    }

    /**
     * Estado de pago según los pagos activos: Anulado si el ticket está eliminado,
     * Pagado si los pagos cubren el total y Pendiente en otro caso.
     */
    public static function recalcularEstadoPago(PDO $db, int $idTicket): void
    {
        $db->prepare(
            "UPDATE ticket_atencion t
             SET t.estado_pago = CASE
                 WHEN t.status = 0 THEN 'Anulado'
                 WHEN (SELECT COALESCE(SUM(p.monto), 0) FROM pagos p WHERE p.id_ticket = t.id_ticket AND p.status = 1) >= t.total THEN 'Pagado'
                 ELSE 'Pendiente' END
             WHERE t.id_ticket = :id"
        )->execute([':id' => $idTicket]);
    }

    protected function describir(array $fila): string
    {
        return 'ticket #' . $fila['id_ticket'] . ' por ' . MONEDA_SIMBOLO . ' ' . number_format((float) ($fila['total'] ?? 0), 2, ',', '.');
    }

    private function lineasGuardadas(int $idTicket): array
    {
        $stmt = $this->db()->prepare(
            'SELECT id_servicio, id_producto, cantidad, precio_unitario FROM detalle_atencion WHERE id_ticket = :id ORDER BY id_detalle_atencion'
        );
        $stmt->execute([':id' => $idTicket]);

        return $stmt->fetchAll();
    }

    private function itemCatalogo(bool $esServicio, int $id): ?array
    {
        $sql = $esServicio
            ? 'SELECT nombre_servicio AS nombre, status, NULL AS stock FROM servicio WHERE id_servicio = :id'
            : 'SELECT nombre_producto AS nombre, status, stock FROM producto WHERE id_producto = :id';
        $stmt = $this->db()->prepare($sql);
        $stmt->execute([':id' => $id]);

        return $stmt->fetch() ?: null;
    }

    private function devolverStock(PDO $db, int $idTicket): void
    {
        $db->prepare(
            'UPDATE producto p
             JOIN (SELECT id_producto, SUM(cantidad) AS cantidad FROM detalle_atencion
                   WHERE id_ticket = :id AND id_producto IS NOT NULL GROUP BY id_producto) d
               ON d.id_producto = p.id_producto
             SET p.stock = p.stock + d.cantidad'
        )->execute([':id' => $idTicket]);
    }

    /** Descuenta el stock de las líneas del ticket; falla si algún producto no alcanza. */
    private function descontarStock(PDO $db, int $idTicket): void
    {
        $stmt = $db->prepare(
            'SELECT d.id_producto, SUM(d.cantidad) AS cantidad, p.nombre_producto
             FROM detalle_atencion d JOIN producto p ON p.id_producto = d.id_producto
             WHERE d.id_ticket = :id GROUP BY d.id_producto, p.nombre_producto'
        );
        $stmt->execute([':id' => $idTicket]);
        $actualizar = $db->prepare('UPDATE producto SET stock = stock - :cantidad WHERE id_producto = :id AND stock >= :minimo');
        foreach ($stmt->fetchAll() as $producto) {
            $actualizar->execute([':cantidad' => $producto['cantidad'], ':id' => $producto['id_producto'], ':minimo' => $producto['cantidad']]);
            if ($actualizar->rowCount() === 0) {
                throw new ValidacionException('No hay stock suficiente de «' . $producto['nombre_producto'] . '».', 'detalles');
            }
        }
    }
}
