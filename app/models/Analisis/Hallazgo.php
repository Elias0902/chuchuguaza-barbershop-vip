<?php

namespace App\Models\Analisis;

/**
 * Resultado de una regla de análisis: qué se encontró, qué tan urgente es y a dónde ir.
 * La clave identifica la situación (por ejemplo «stock-agotado») para que el buzón
 * no la repita mientras siga abierta. Objeto inmutable (propiedades readonly).
 */
final class Hallazgo
{
    public const ALTA = 'alta';
    public const MEDIA = 'media';
    public const BAJA = 'baja';
    public const POSITIVA = 'positiva';

    public const CATEGORIAS = ['Ventas', 'Clientes', 'Inventario', 'Agenda', 'Caja', 'Equipo'];

    private const ORDEN = [self::ALTA => 0, self::MEDIA => 1, self::BAJA => 2, self::POSITIVA => 3];
    private const ETIQUETAS = [self::ALTA => 'Urgente', self::MEDIA => 'Atención', self::BAJA => 'Oportunidad', self::POSITIVA => 'Va bien'];
    private const COLORES = [self::ALTA => 'rojo', self::MEDIA => 'amarillo', self::BAJA => 'azul', self::POSITIVA => 'verde'];
    private const ICONOS_PRIORIDAD = [
        self::ALTA => 'fa-exclamation-triangle', self::MEDIA => 'fa-exclamation-circle',
        self::BAJA => 'fa-lightbulb', self::POSITIVA => 'fa-check-circle',
    ];

    public function __construct(
        public readonly string $clave,
        public readonly string $categoria,
        public readonly string $prioridad,
        public readonly string $titulo,
        public readonly string $detalle,
        public readonly string $icono,
        public readonly ?string $ruta = null,
        public readonly string $textoEnlace = 'Ver módulo'
    ) {
    }

    public function orden(): int
    {
        return self::ORDEN[$this->prioridad] ?? 4;
    }

    public static function etiquetaDe(string $prioridad): string
    {
        return self::ETIQUETAS[$prioridad] ?? 'Información';
    }

    public static function colorDe(string $prioridad): string
    {
        return self::COLORES[$prioridad] ?? 'gris';
    }

    public static function iconoDe(string $prioridad): string
    {
        return self::ICONOS_PRIORIDAD[$prioridad] ?? 'fa-info-circle';
    }
}
