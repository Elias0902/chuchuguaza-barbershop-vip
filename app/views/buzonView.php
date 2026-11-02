<?php

use App\Helpers\Formato;
use App\Helpers\Vista;
use App\Models\Analisis\Hallazgo;
use App\Models\Buzon;

/**
 * @var string $grupo
 * @var array $sugerencias
 * @var array $conteo
 * @var array|null $ultimaEjecucion
 * @var bool $puedeModificar
 * @var array $permisosSesion
 */
$tituloPagina = 'Buzón de sugerencias';
$grupoPagina = 'Análisis';
$pestanas = ['pendientes' => 'Pendientes', 'Aplicada' => 'Aplicadas', 'Descartada' => 'Descartadas', 'Resuelta' => 'Resueltas solas', 'todas' => 'Todas'];
$coloresEstado = ['Nueva' => 'rojo', 'Leída' => 'gris', 'Aplicada' => 'verde', 'Descartada' => 'gris', 'Resuelta' => 'azul'];
$vacios = [
    'pendientes' => 'No hay sugerencias pendientes. El sistema vuelve a revisar los datos cada ' . Buzon::HORAS_ENTRE_ANALISIS . ' horas.',
    'Aplicada' => 'Todavía no marcaste ninguna sugerencia como aplicada.',
    'Descartada' => 'No hay sugerencias descartadas.',
    'Resuelta' => 'Ninguna sugerencia se ha resuelto sola todavía.',
    'todas' => 'El buzón está vacío. Pulsa «Analizar ahora» para que el sistema revise los datos.',
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
            <h1><i class="fas fa-inbox" aria-hidden="true"></i>Buzón de sugerencias</h1>
            <p class="intro">Nadie escribe estas sugerencias: el sistema revisa tus ventas, clientes, agenda, inventario y caja cada cierto tiempo, y deja aquí ideas para mejorar y vender más. Cuando una situación se arregla, la sugerencia se marca como resuelta sola.</p>
          </div>
          <div class="heading-actions">
            <?php if (isset($permisosSesion['analisis'])): ?>
              <a class="btn btn-secondary" href="index.php?url=analisis"><i class="fas fa-chart-line" aria-hidden="true"></i>Ver análisis</a>
            <?php endif; ?>
            <button class="btn btn-primary" type="button" data-analizar><i class="fas fa-sync-alt" aria-hidden="true"></i>Analizar ahora</button>
          </div>
        </section>
        <p class="analysis-meta">
          <?php if ($ultimaEjecucion): ?>
            Último análisis: <?= Vista::e(Formato::valor($ultimaEjecucion['fecha_ejecucion'], 'fechahora')) ?>
            (<?= Vista::e(mb_strtolower($ultimaEjecucion['origen'], 'UTF-8')) ?>) ·
          <?php endif; ?>
          El sistema revisa los datos automáticamente cada <?= Buzon::HORAS_ENTRE_ANALISIS ?> horas.
        </p>

        <section class="panel">
          <div class="toolbar">
            <nav class="tabs" aria-label="Filtrar sugerencias">
              <?php foreach ($pestanas as $clave => $nombre): ?>
                <?php $cantidad = $conteo[$clave] ?? 0; ?>
                <a class="tab<?= $grupo === $clave ? ' active' : '' ?>" href="index.php?url=buzon&amp;grupo=<?= Vista::e(rawurlencode($clave)) ?>"<?= $grupo === $clave ? ' aria-current="page"' : '' ?>>
                  <?= Vista::e($nombre) ?> <span><?= (int) $cantidad ?></span>
                </a>
              <?php endforeach; ?>
            </nav>
            <?php if ($conteo['Nueva'] > 0): ?>
              <span class="badge rojo"><i class="fas fa-circle" aria-hidden="true" style="font-size: 7px"></i><?= Vista::e(Formato::cantidad($conteo['Nueva'], 'nueva', 'nuevas')) ?></span>
            <?php endif; ?>
          </div>

          <?php if (!$sugerencias): ?>
            <p class="empty-note"><i class="fas fa-check-circle" aria-hidden="true"></i> <?= Vista::e($vacios[$grupo]) ?></p>
          <?php else: ?>
            <div class="findings inbox-list">
              <?php foreach ($sugerencias as $sugerencia): ?>
                <?php $abierta = in_array($sugerencia['estado'], Buzon::ABIERTAS, true); ?>
                <article class="finding <?= Vista::e($sugerencia['prioridad']) ?><?= $sugerencia['estado'] === 'Nueva' ? ' is-new' : '' ?><?= $abierta ? '' : ' is-closed' ?>">
                  <span class="finding-icon"><i class="fas <?= Vista::e($sugerencia['icono']) ?>" aria-hidden="true"></i></span>
                  <div>
                    <div class="finding-tags">
                      <span class="badge <?= Vista::e(Hallazgo::colorDe($sugerencia['prioridad'])) ?>"><i class="fas <?= Vista::e(Hallazgo::iconoDe($sugerencia['prioridad'])) ?>" aria-hidden="true"></i><?= Vista::e(Hallazgo::etiquetaDe($sugerencia['prioridad'])) ?></span>
                      <span class="badge gris"><?= Vista::e($sugerencia['categoria']) ?></span>
                      <span class="badge <?= Vista::e($coloresEstado[$sugerencia['estado']] ?? 'gris') ?>"><?= Vista::e($sugerencia['estado']) ?></span>
                    </div>
                    <h3><?= Vista::e($sugerencia['titulo']) ?></h3>
                    <p><?= Vista::e($sugerencia['detalle']) ?></p>
                    <p class="finding-date">
                      Detectada el <?= Vista::e(Formato::valor($sugerencia['fecha_generada'], 'fechahora')) ?>
                      <?php if ($abierta && $sugerencia['fecha_actualizada'] !== $sugerencia['fecha_generada']): ?>
                        · revisada de nuevo el <?= Vista::e(Formato::valor($sugerencia['fecha_actualizada'], 'fechahora')) ?>
                      <?php elseif (!$abierta && $sugerencia['fecha_estado']): ?>
                        · <?= Vista::e(mb_strtolower($sugerencia['estado'], 'UTF-8')) ?> el <?= Vista::e(Formato::valor($sugerencia['fecha_estado'], 'fechahora')) ?>
                      <?php endif; ?>
                    </p>
                  </div>
                  <div class="finding-actions">
                    <?php if ($sugerencia['ruta'] && isset($permisosSesion[$sugerencia['ruta']])): ?>
                      <a class="btn btn-secondary" href="index.php?url=<?= Vista::e($sugerencia['ruta']) ?>"><?= Vista::e($sugerencia['texto_enlace'] ?: 'Ver módulo') ?></a>
                    <?php endif; ?>
                    <?php if ($puedeModificar && $abierta): ?>
                      <?php if ($sugerencia['estado'] === 'Nueva'): ?>
                        <button class="icon-button" type="button" title="Marcar como leída" aria-label="Marcar como leída" data-estado="Leída" data-id="<?= (int) $sugerencia['id_sugerencia'] ?>"><i class="fas fa-eye" aria-hidden="true"></i></button>
                      <?php endif; ?>
                      <button class="icon-button success" type="button" title="Ya la apliqué" aria-label="Ya la apliqué" data-estado="Aplicada" data-id="<?= (int) $sugerencia['id_sugerencia'] ?>"><i class="fas fa-check" aria-hidden="true"></i></button>
                      <button class="icon-button danger" type="button" title="Descartar" aria-label="Descartar" data-estado="Descartada" data-id="<?= (int) $sugerencia['id_sugerencia'] ?>"><i class="fas fa-times" aria-hidden="true"></i></button>
                    <?php elseif ($puedeModificar && in_array($sugerencia['estado'], ['Aplicada', 'Descartada'], true)): ?>
                      <button class="icon-button" type="button" title="Reabrir" aria-label="Reabrir" data-estado="Reabrir" data-id="<?= (int) $sugerencia['id_sugerencia'] ?>"><i class="fas fa-undo" aria-hidden="true"></i></button>
                    <?php endif; ?>
                  </div>
                </article>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
      </main>
      <?php require __DIR__ . '/partials/firma.php'; ?>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
  <script src="<?= Vista::asset('assets/js/buzon.js') ?>"></script>
</body>
</html>
