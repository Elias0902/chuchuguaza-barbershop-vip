<?php

namespace App\Models;

use App\Core\CrudModel;
use PDO;

/**
 * Retiro de dinero (cabecera) con su desglose por método de pago.
 */
class Retiro extends CrudModel
{
    protected string $tabla = 'retiro_dinero';
    protected string $clave = 'id_retiro';
    protected string $modulo = 'Retiros de dinero';
    protected string $singular = 'retiro';

    /** Líneas validadas en reglasNegocio() que se guardan en despuesDeGuardar(). */
    private array $lineas = [];

    public function campos(): array
    {
        return [
            'cedula_barbero' => [
                'etiqueta' => 'Retirado por', 'tipo' => 'relacion', 'requerido' => true,
                'relacion' => ['tabla' => 'barberos', 'clave' => 'cedula', 'claveTexto' => true, 'texto' => "CONCAT(nombre, ' ', apellido)"],
            ],
            'fecha_retiro' => ['etiqueta' => 'Fecha y hora', 'tipo' => 'fechahora', 'requerido' => true, 'defecto' => 'ahora'],
            'id_cierre' => [
                'etiqueta' => 'Caja', 'tipo' => 'relacion', 'ancho' => 'completo',
                'ayuda' => 'Asócialo a la caja abierta para descontarlo en el cierre. Opcional.',
                'relacion' => [
                    'tabla' => 'cierre_caja', 'clave' => 'id_cierre',
                    'texto' => "CONCAT('Caja #', id_cierre, ' · ', DATE_FORMAT(fecha_inicio, '%d/%m/%Y'), ' (', estado, ')')",
                    'activo' => "status = 1 AND estado = 'Abierta'",
                    'orden' => 'valor DESC',
                ],
            ],
            'detalles' => [
                'etiqueta' => 'Desglose del retiro', 'tipo' => 'detalle', 'requerido' => true, 'ancho' => 'completo',
                'total' => ['monto'],
                'columnas' => [
                    'id_metodo' => [
                        'etiqueta' => 'Método', 'tipo' => 'relacion', 'requerido' => true, 'fuente' => 'metodos',
                        'relacion' => ['tabla' => 'metodo_pago', 'clave' => 'id_metodo', 'texto' => 'nombre_metodo'],
                    ],
                    'monto' => ['etiqueta' => 'Monto', 'tipo' => 'decimal', 'requerido' => true, 'min' => 0.01, 'maxValor' => 99999999.99],
                    'motivo' => ['etiqueta' => 'Motivo', 'tipo' => 'texto', 'max' => 255],
                ],
            ],
            'monto_total' => ['etiqueta' => 'Monto total', 'tipo' => 'decimal', 'formulario' => false],
        ];
    }

    protected function sqlConsulta(): string
    {
        return "SELECT r.*, CONCAT(b.nombre, ' ', b.apellido) AS barbero,
                    IF(r.id_cierre IS NULL, NULL, CONCAT('Caja #', r.id_cierre)) AS caja,
                    (SELECT GROUP_CONCAT(CONCAT(m.nombre_metodo, COALESCE(CONCAT(': ', d.motivo), '')) SEPARATOR ' · ')
                       FROM detalle_retiro d LEFT JOIN metodo_pago m ON m.id_metodo = d.id_metodo
                      WHERE d.id_retiro = r.id_retiro) AS desglose
                FROM retiro_dinero r
                LEFT JOIN barberos b ON b.cedula = r.cedula_barbero
                ORDER BY r.status DESC, r.fecha_retiro DESC";
    }

    public function obtener(int|string $id): ?array
    {
        $retiro = parent::obtener($id);
        if ($retiro !== null) {
            $stmt = $this->db()->prepare('SELECT id_metodo, monto, motivo FROM detalle_retiro WHERE id_retiro = :id ORDER BY id_detalle_retiro');
            $stmt->execute([':id' => $id]);
            $retiro['detalles'] = $stmt->fetchAll();
        }

        return $retiro;
    }

    protected function reglasNegocio(array &$datos, array $entrada, ?array $actual): void
    {
        $this->lineas = $this->limpiarDetalles('detalles', $entrada['detalles'] ?? null, 20);
        $total = array_sum(array_map(static fn(array $linea): float => (float) $linea['monto'], $this->lineas));
        $datos['monto_total'] = number_format($total, 2, '.', '');
    }

    protected function despuesDeGuardar(PDO $db, int|string $id, array $datos, ?array $actual): void
    {
        if ($actual !== null) {
            $db->prepare('DELETE FROM detalle_retiro WHERE id_retiro = :id')->execute([':id' => $id]);
        }
        $insertar = $db->prepare(
            'INSERT INTO detalle_retiro (id_retiro, id_metodo, monto, motivo) VALUES (:id_retiro, :id_metodo, :monto, :motivo)'
        );
        foreach ($this->lineas as $linea) {
            $insertar->execute([':id_retiro' => $id] + $this->parametros($linea));
        }
    }

    protected function describir(array $fila): string
    {
        return 'retiro #' . $fila['id_retiro'] . ' por ' . MONEDA_SIMBOLO . ' ' . number_format((float) ($fila['monto_total'] ?? 0), 2, ',', '.');
    }
}
