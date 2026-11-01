<?php

namespace App\Models\Analisis;

use App\Core\BaseModel;
use App\Helpers\Formato;

/**
 * Regla del motor de análisis. Cada regla mira una parte del negocio y devuelve
 * cero o más hallazgos; el motor no sabe qué revisa cada una (polimorfismo).
 */
abstract class ReglaAnalisis extends BaseModel
{
    /** Ventana de tiempo (días) que usan las reglas que comparan comportamiento. */
    protected const VENTANA_DIAS = 60;

    /** @return Hallazgo[] */
    abstract public function evaluar(): array;

    /** Primera fila de una consulta con parámetros. */
    protected function fila(string $sql, array $parametros = []): array
    {
        $stmt = $this->negocio()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetch() ?: [];
    }

    protected function filas(string $sql, array $parametros = []): array
    {
        $stmt = $this->negocio()->prepare($sql);
        $stmt->execute($parametros);

        return $stmt->fetchAll();
    }

    protected function moneda(float $monto): string
    {
        return Formato::valor($monto, 'moneda');
    }

    /** "A, B y 3 más". */
    protected function lista(array $nombres, int $maximo = 3): string
    {
        $visibles = array_slice($nombres, 0, $maximo);
        $resto = count($nombres) - count($visibles);
        $texto = implode(', ', $visibles);

        return $resto > 0 ? "$texto y $resto más" : $texto;
    }
}
