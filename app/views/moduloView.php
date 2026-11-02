<?php

use App\Helpers\Vista;

/**
 * Vista común de los módulos: encabezado, filtros, tabla y formulario modal.
 * La tabla y el formulario los arma assets/js/modulo.js con la configuración del controlador.
 * @var array $modulo
 */
$tituloPagina = $modulo['titulo'];
$grupoPagina = $modulo['grupo'];
$articuloNuevo = $modulo['femenino'] ? 'Nueva' : 'Nuevo';
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
            <div class="eyebrow"><?= Vista::e($modulo['grupo']) ?></div>
            <h1><i class="fas <?= Vista::e($modulo['icono']) ?>" aria-hidden="true"></i><?= Vista::e($modulo['titulo']) ?></h1>
            <p class="intro"><?= Vista::e($modulo['descripcion']) ?></p>
          </div>
          <div class="heading-actions">
            <a class="btn btn-secondary" href="<?= Vista::e($modulo['url']) ?>&amp;action=reporte&amp;filtro=activos" target="_blank" rel="noopener" data-reporte>
              <i class="fas fa-file-pdf" aria-hidden="true"></i>Reporte PDF
            </a>
            <?php if (!$modulo['soloConsulta'] && $modulo['permisos']['registrar']): ?>
              <button class="btn btn-primary" type="button" data-nuevo>
                <i class="fas fa-plus" aria-hidden="true"></i><?= Vista::e($articuloNuevo . ' ' . $modulo['singular']) ?>
              </button>
            <?php endif; ?>
            <button class="icon-button help-button" type="button" title="Ayuda: cómo usar este módulo" aria-label="Ayuda: cómo usar este módulo" data-ayuda>
              <i class="fas fa-question" aria-hidden="true"></i>
            </button>
          </div>
        </section>

        <section class="panel" aria-labelledby="titulo-listado">
          <div class="toolbar">
            <h2 class="sr-only" id="titulo-listado">Listado de <?= Vista::e(mb_strtolower($modulo['titulo'], 'UTF-8')) ?></h2>
            <?php if ($modulo['tieneEstado']): ?>
              <div class="tabs" role="tablist" aria-label="Filtrar por estado">
                <button class="tab active" type="button" role="tab" aria-selected="true" data-filtro="activos">Activos <span data-contador="activos">0</span></button>
                <button class="tab" type="button" role="tab" aria-selected="false" data-filtro="eliminados">Eliminados <span data-contador="eliminados">0</span></button>
                <button class="tab" type="button" role="tab" aria-selected="false" data-filtro="todos">Todos <span data-contador="todos">0</span></button>
              </div>
            <?php else: ?>
              <div class="toolbar-count"><span data-contador="todos">0</span> movimientos</div>
            <?php endif; ?>
            <div class="quick-filters" data-filtros-rapidos hidden></div>
            <label class="search">
              <i class="fas fa-search" aria-hidden="true"></i>
              <span class="sr-only">Buscar</span>
              <input type="search" placeholder="Buscar..." autocomplete="off" data-buscar>
            </label>
          </div>

          <div class="table-wrap">
            <table class="data-table">
              <thead data-cabecera></thead>
              <tbody data-cuerpo>
                <tr><td class="empty"><i class="fas fa-sync-alt fa-spin" aria-hidden="true"></i> Cargando registros...</td></tr>
              </tbody>
            </table>
          </div>

          <div class="table-footer">
            <span data-resumen></span>
            <div class="pager">
              <button class="icon-button" type="button" aria-label="Página anterior" data-pagina="-1"><i class="fas fa-chevron-left" aria-hidden="true"></i></button>
              <span data-pagina-actual>1 / 1</span>
              <button class="icon-button" type="button" aria-label="Página siguiente" data-pagina="1"><i class="fas fa-chevron-right" aria-hidden="true"></i></button>
            </div>
          </div>
        </section>
      </main>
    </div>
  </div>

  <script type="application/json" id="modulo-config"><?= Vista::json($modulo) ?></script>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
  <?php foreach ($modulo['scripts'] as $script): ?>
    <script src="<?= Vista::asset($script) ?>"></script>
  <?php endforeach; ?>
  <script src="<?= Vista::asset('assets/js/modulo.js') ?>"></script>
</body>
</html>
