<?php

namespace App\Helpers;

use FPDF;

/**
 * Reporte PDF de los listados, generado con FPDF (paquete setasign/fpdf de Composer).
 *
 * Herencia y polimorfismo: extiende FPDF y reescribe Header() y Footer(),
 * que FPDF llama automáticamente en cada página.
 */
class ReportePdf extends FPDF
{
    private const AZUL = [6, 15, 38];
    private const ROJO = [211, 32, 47];
    private const GRIS_FILA = [244, 245, 248];
    private const MARGEN = 12;

    public function __construct(
        private readonly string $titulo,
        private readonly string $subtitulo,
        private readonly string $usuario
    ) {
        parent::__construct('L', 'mm', 'Letter');
        $this->SetMargins(self::MARGEN, self::MARGEN, self::MARGEN);
        $this->SetAutoPageBreak(true, 18);
        $this->AliasNbPages();
        $this->SetTitle($this->texto($titulo . ' - ' . APP_NOMBRE));
        $this->SetAuthor($this->texto(APP_NOMBRE));
        $this->AddPage();
    }

    public function Header(): void
    {
        $this->SetFillColor(...self::AZUL);
        $this->Rect(0, 0, $this->GetPageWidth(), 24, 'F');
        $this->SetFillColor(...self::ROJO);
        $this->Rect(0, 24, $this->GetPageWidth(), 1.2, 'F');

        $this->SetTextColor(255, 255, 255);
        $this->SetXY(self::MARGEN, 6);
        $this->SetFont('Helvetica', 'B', 15);
        $this->Cell(0, 7, $this->texto(APP_NOMBRE));
        $this->SetXY(self::MARGEN, 14);
        $this->SetFont('Helvetica', '', 10);
        $this->Cell(0, 5, $this->texto('Reporte de ' . mb_strtolower($this->titulo, 'UTF-8') . ' · ' . $this->subtitulo));

        $this->SetXY(-80, 9);
        $this->SetFont('Helvetica', '', 8);
        $this->Cell(68, 5, $this->texto('Emitido: ' . date('d/m/Y h:i a')), 0, 0, 'R');

        $this->SetTextColor(30, 30, 30);
        $this->SetY(31);
    }

    public function Footer(): void
    {
        $this->SetY(-12);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(120, 120, 120);
        $generado = $this->usuario !== '' ? 'Generado por ' . $this->usuario : 'Generado por el sistema';
        $this->Cell(0, 6, $this->texto($generado), 0, 0, 'L');
        $this->Cell(0, 6, $this->texto('Página ' . $this->PageNo() . ' de {nb}'), 0, 0, 'R');
    }

    /**
     * Dibuja la tabla con las columnas del módulo. Los anchos se reparten según el contenido
     * y las columnas de moneda se totalizan al final.
     */
    public function tabla(array $columnas, array $filas, bool $incluirEstado = false): void
    {
        if ($incluirEstado) {
            $columnas[] = ['campo' => 'status', 'etiqueta' => 'Estado', 'formato' => 'estado'];
        }

        $celdas = array_map(fn(array $fila): array => array_map(fn(array $col): string => $this->celda($fila, $col), $columnas), $filas);
        $anchos = $this->anchos($columnas, $celdas);

        $this->encabezadoTabla($columnas, $anchos);
        $this->SetFont('Helvetica', '', 8);
        foreach ($celdas as $i => $valores) {
            if ($this->GetY() > $this->GetPageHeight() - 26) {
                $this->AddPage();
                $this->encabezadoTabla($columnas, $anchos);
                $this->SetFont('Helvetica', '', 8);
            }
            $this->SetFillColor(...($i % 2 === 0 ? [255, 255, 255] : self::GRIS_FILA));
            foreach ($valores as $j => $valor) {
                $alineacion = $this->esNumerica($columnas[$j]) ? 'R' : 'L';
                $this->Cell($anchos[$j], 7, $this->recortar($this->texto($valor), $anchos[$j] - 2), 0, 0, $alineacion, true);
            }
            $this->Ln();
        }

        if ($filas === []) {
            $this->SetFont('Helvetica', 'I', 9);
            $this->Cell(array_sum($anchos), 12, $this->texto('No hay registros para mostrar.'), 0, 1, 'C');
            return;
        }

        $this->totales($columnas, $filas, $anchos);
    }

    /** Envía el PDF al navegador para verlo o descargarlo. */
    public function descargar(string $nombreArchivo): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->Output('I', $nombreArchivo);
        exit();
    }

    private function celda(array $fila, array $columna): string
    {
        $texto = Formato::valor($fila[$columna['campo']] ?? null, $columna['formato'] ?? 'texto');
        if (!empty($columna['sub']) && !empty($fila[$columna['sub']])) {
            $texto .= ' · ' . $fila[$columna['sub']];
        }

        return $texto;
    }

    private function encabezadoTabla(array $columnas, array $anchos): void
    {
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetFillColor(...self::ROJO);
        $this->SetTextColor(255, 255, 255);
        foreach ($columnas as $j => $columna) {
            $this->Cell($anchos[$j], 8, $this->recortar($this->texto($columna['etiqueta']), $anchos[$j] - 2), 0, 0, $this->esNumerica($columna) ? 'R' : 'L', true);
        }
        $this->Ln();
        $this->SetTextColor(30, 30, 30);
    }

    private function totales(array $columnas, array $filas, array $anchos): void
    {
        $hayMoneda = false;
        $this->SetFont('Helvetica', 'B', 8);
        $this->SetDrawColor(...self::ROJO);
        $this->Line(self::MARGEN, $this->GetY(), self::MARGEN + array_sum($anchos), $this->GetY());
        foreach ($columnas as $j => $columna) {
            $texto = '';
            if ($j === 0) {
                $texto = 'Total';
            } elseif (($columna['formato'] ?? '') === 'moneda') {
                $hayMoneda = true;
                $texto = Formato::valor(array_sum(array_map(static fn(array $f): float => (float) ($f[$columna['campo']] ?? 0), $filas)), 'moneda');
            }
            $this->Cell($anchos[$j], 8, $this->recortar($this->texto($texto), $anchos[$j] - 2), 0, 0, $j === 0 ? 'L' : 'R');
        }
        $this->Ln();
        $this->SetFont('Helvetica', 'I', 7);
        $this->SetTextColor(120, 120, 120);
        $nota = count($filas) . (count($filas) === 1 ? ' registro listado.' : ' registros listados.');
        if ($hayMoneda) {
            $nota .= ' Los montos de la fila Total son la suma de esos registros.';
        }
        $this->Cell(0, 5, $this->texto($nota));
    }

    /** Reparte el ancho útil de la página según el largo del contenido de cada columna. */
    private function anchos(array $columnas, array $celdas): array
    {
        $pesos = [];
        foreach ($columnas as $j => $columna) {
            $largo = mb_strlen($columna['etiqueta'], 'UTF-8');
            foreach (array_slice($celdas, 0, 200) as $valores) {
                $largo = max($largo, min(mb_strlen($valores[$j], 'UTF-8'), 45));
            }
            $pesos[] = max($largo, 6);
        }
        $disponible = $this->GetPageWidth() - 2 * self::MARGEN;
        $total = array_sum($pesos);

        return array_map(static fn(int $peso): float => $disponible * $peso / $total, $pesos);
    }

    private function esNumerica(array $columna): bool
    {
        return in_array($columna['formato'] ?? 'texto', ['moneda', 'entero', 'porcentaje'], true);
    }

    private function recortar(string $texto, float $ancho): string
    {
        if ($this->GetStringWidth($texto) <= $ancho) {
            return $texto;
        }
        while ($texto !== '' && $this->GetStringWidth($texto . '...') > $ancho) {
            $texto = substr($texto, 0, -1);
        }

        return $texto . '...';
    }

    /** FPDF trabaja en Windows-1252: convierte los acentos y la ñ desde UTF-8. */
    private function texto(string $texto): string
    {
        $convertido = @iconv('UTF-8', 'windows-1252//TRANSLIT', $texto);

        return $convertido !== false ? $convertido : $texto;
    }
}
