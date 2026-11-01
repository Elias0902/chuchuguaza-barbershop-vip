<?php

namespace App\Models\Analisis;

use App\Models\Analisis\Reglas\AgendaYCaja;
use App\Models\Analisis\Reglas\ClientesInactivos;
use App\Models\Analisis\Reglas\CobrosPendientes;
use App\Models\Analisis\Reglas\DemandaSemanal;
use App\Models\Analisis\Reglas\ServiciosYPromociones;
use App\Models\Analisis\Reglas\StockProductos;
use App\Models\Analisis\Reglas\VentaCruzada;
use App\Models\Analisis\Reglas\VentasTendencia;
use Throwable;

/**
 * Motor de análisis propio (sin servicios externos): ejecuta cada regla,
 * junta sus hallazgos y los ordena de más a menos urgente.
 * Para agregar un análisis nuevo basta con crear otra ReglaAnalisis y sumarla aquí.
 */
final class MotorAnalisis
{
    /** @param ReglaAnalisis[] $reglas */
    public function __construct(private readonly array $reglas)
    {
    }

    public static function predeterminado(): self
    {
        return new self([
            new VentasTendencia(),
            new CobrosPendientes(),
            new StockProductos(),
            new VentaCruzada(),
            new ClientesInactivos(),
            new DemandaSemanal(),
            new ServiciosYPromociones(),
            new AgendaYCaja(),
        ]);
    }

    /** @return Hallazgo[] */
    public function analizar(): array
    {
        $hallazgos = [];
        foreach ($this->reglas as $regla) {
            try {
                array_push($hallazgos, ...$regla->evaluar());
            } catch (Throwable $e) {
                // Una regla con error no debe impedir que se muestren las demás.
                error_log('Falló la regla de análisis ' . $regla::class . ': ' . $e->getMessage());
            }
        }
        usort($hallazgos, static fn(Hallazgo $a, Hallazgo $b): int => $a->orden() <=> $b->orden());

        return $hallazgos;
    }
}
