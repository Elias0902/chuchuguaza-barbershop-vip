<?php

use App\Helpers\Formato;
use App\Helpers\Grafico;
use App\Helpers\Vista;
use App\Models\Analisis\Hallazgo;

/**
 * @var int $dias
 * @var int[] $periodos
 * @var array $resumen  ['actual' => [...], 'anterior' => [...]]
 * @var array $destacadas
 * @var int $pendientes
 * @var array $ingresosMes
 * @var array $porDia
 * @var array $porHora
 * @var array $servicios
 * @var array $productos
 * @var array $metodos
 * @var array $barberos
 * @var array $permisosSesion
 */
$tituloPagina = 'Análisis de ventas';
$grupoPagina = 'Análisis';
$etiquetasPeriodo = [30 => 'Últimos 30 días', 90 => 'Últimos 90 días', 365 => 'Último año'];
$actual = $resumen['actual'];
$anterior = $resumen['anterior'];

/** Variación contra el período anterior: flecha, porcentaje y color (con texto, no solo color). */
$tendencia = static function (float $hoy, float $antes) use ($dias): string {
    if ($antes <= 0) {
        return '<span class="stat-meta">Sin datos del período anterior</span>';
    }
    $cambio = (int) round(($hoy - $antes) / $antes * 100);
    $clase = $cambio > 0 ? 'up' : ($cambio < 0 ? 'down' : '');
    $flecha = $cambio > 0 ? '▲' : ($cambio < 0 ? '▼' : '=');
    return '<span class="stat-meta"><span class="trend ' . $clase . '">' . $flecha . ' ' . abs($cambio) . '%</span> frente a los ' . $dias . ' días anteriores</span>';
};
$tarjetas = [
    ['Atenciones', Formato::valor($actual['atenciones'], 'entero'), $tendencia((float) $actual['atenciones'], (float) $anterior['atenciones']), 'fa-receipt'],
    ['Ingresos cobrados', Formato::valor($actual['ingresos'], 'moneda'), $tendencia((float) $actual['ingresos'], (float) $anterior['ingresos']), 'fa-money-bill-wave'],
    ['Ticket promedio', Formato::valor($actual['ticket_promedio'], 'moneda'), $tendencia((float) $actual['ticket_promedio'], (float) $anterior['ticket_promedio']), 'fa-balance-scale'],
    ['Clientes atendidos', Formato::valor($actual['clientes'], 'entero'), $tendencia((float) $actual['clientes'], (float) $anterior['clientes']), 'fa-users'],
];
?>
<!doctype html>
<html lang="es">
<head>
  <?php require __DIR__ . '/partials/panelHead.php'; ?>
</head>
<body>
  <div class="app-shell">
    <?php require __DIR__ . '/partials/panelSidebar.php'; ?>
    <div class="workspace">
      <?php require __DIR__ . '/partials/panelTopbar.php'; ?>
      <main class="content" id="contenido">
        <section class="page-heading">
          <div>
            <div class="eyebrow">Análisis</div>
            <h1><i class="fas fa-chart-line" aria-hidden="true"></i>Análisis de ventas</h1>
            <p class="intro">Resultados del negocio calculados con tus datos: cuánto vendes, qué se vende más y cuándo llegan los clientes.</p>
          </div>
          <div class="heading-actions">
            <nav class="period" aria-label="Período del análisis">
              <?php foreach ($periodos as $periodo): ?>
                <a class="tab<?= $periodo === $dias ? ' active' : '' ?>" href="index.php?url=analisis&amp;dias=<?= $periodo ?>"<?= $periodo === $dias ? ' aria-current="page"' : '' ?>><?= Vista::e($etiquetasPeriodo[$periodo]) ?></a>
              <?php endforeach; ?>
            </nav>
          </div>
        </section>

        <section class="stats" aria-label="Indicadores del período">
          <?php foreach ($tarjetas as [$etiqueta, $valor, $detalle, $icono]): ?>
            <div class="stat">
              <span class="stat-icon"><i class="fas <?= Vista::e($icono) ?>" aria-hidden="true"></i></span>
              <span class="stat-label"><?= Vista::e($etiqueta) ?></span>
              <strong class="stat-value"><?= Vista::e($valor) ?></strong>
              <?= $detalle ?>
            </div>
          <?php endforeach; ?>
        </section>

        <?php if (isset($permisosSesion['buzon'])): ?>
          <section class="panel">
            <div class="panel-heading">
              <h2>Lo que sugiere el sistema</h2>
              <a href="index.php?url=buzon">Ir al buzón (<?= Vista::e(Formato::cantidad($pendientes, 'pendiente', 'pendientes')) ?>)</a>
            </div>
            <?php if (!$destacadas): ?>
              <p class="empty-note"><i class="fas fa-check-circle" aria-hidden="true"></i> No hay sugerencias pendientes en este momento.</p>
            <?php else: ?>
              <div class="findings inbox-list">
                <?php foreach ($destacadas as $sugerencia): ?>
                  <article class="finding <?= Vista::e($sugerencia['prioridad']) ?>">
                    <span class="finding-icon"><i class="fas <?= Vista::e($sugerencia['icono']) ?>" aria-hidden="true"></i></span>
                    <div>
                      <div class="finding-tags">
                        <span class="badge <?= Vista::e(Hallazgo::colorDe($sugerencia['prioridad'])) ?>"><i class="fas <?= Vista::e(Hallazgo::iconoDe($sugerencia['prioridad'])) ?>" aria-hidden="true"></i><?= Vista::e(Hallazgo::etiquetaDe($sugerencia['prioridad'])) ?></span>
                        <span class="badge gris"><?= Vista::e($sugerencia['categoria']) ?></span>
                      </div>
                      <h3><?= Vista::e($sugerencia['titulo']) ?></h3>
                      <p><?= Vista::e($sugerencia['detalle']) ?></p>
                    </div>
                    <?php if ($sugerencia['ruta'] && isset($permisosSesion[$sugerencia['ruta']])): ?>
                      <a class="btn btn-secondary" href="index.php?url=<?= Vista::e($sugerencia['ruta']) ?>"><?= Vista::e($sugerencia['texto_enlace'] ?: 'Ver módulo') ?></a>
                    <?php endif; ?>
                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </section>
        <?php endif; ?>

        <h2 class="section-title">Ventas <small><?= Vista::e($etiquetasPeriodo[$dias]) ?></small></h2>
        <div class="analysis-grid">
          <section class="panel">
            <div class="panel-heading"><h2>Ingresos por mes</h2><small class="analysis-meta">Últimos 6 meses</small></div>
            <?= Grafico::columnas('Ingresos cobrados por mes', $ingresosMes, 'moneda') ?>
          </section>
          <section class="panel">
            <div class="panel-heading"><h2>Servicios más vendidos</h2><?php if (isset($permisosSesion['servicios'])): ?><a href="index.php?url=servicios">Ver servicios</a><?php endif; ?></div>
            <?= Grafico::barras('Servicios más vendidos', $servicios, 'entero', 'veces') ?>
          </section>
          <section class="panel">
            <div class="panel-heading"><h2>Productos y bebidas más vendidos</h2><?php if (isset($permisosSesion['productos'])): ?><a href="index.php?url=productos">Ver productos</a><?php endif; ?></div>
            <?= Grafico::barras('Productos y bebidas más vendidos', $productos, 'entero', 'unidades') ?>
          </section>
          <section class="panel">
            <div class="panel-heading"><h2>Ingresos por método de pago</h2></div>
            <?= Grafico::barras('Ingresos por método de pago', $metodos, 'moneda') ?>
          </section>
        </div>

        <h2 class="section-title">Clientes y equipo <small><?= Vista::e($etiquetasPeriodo[$dias]) ?></small></h2>
        <div class="analysis-grid">
          <section class="panel">
            <div class="panel-heading"><h2>Atenciones por día de la semana</h2></div>
            <?= Grafico::columnas('Atenciones por día de la semana', $porDia, 'entero', 'atenciones') ?>
          </section>
          <section class="panel">
            <div class="panel-heading"><h2>Atenciones por hora de llegada</h2></div>
            <?= Grafico::columnas('Atenciones por hora de llegada', $porHora, 'entero', 'atenciones') ?>
          </section>
          <section class="panel">
            <div class="panel-heading"><h2>Atenciones por barbero</h2><?php if (isset($permisosSesion['barberos'])): ?><a href="index.php?url=barberos">Ver barberos</a><?php endif; ?></div>
            <?= Grafico::barras('Atenciones por barbero', $barberos, 'entero', 'atenciones') ?>
          </section>
        </div>
      </main>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
</body>
</html>
