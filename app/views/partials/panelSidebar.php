<?php

use App\Helpers\Vista;

/**
 * Menú lateral generado desde config/modulos.php.
 * @var array $menu
 * @var string $activePage
 */
?>
<button class="menu-overlay" type="button" aria-label="Cerrar menú" data-menu-close></button>
<aside class="sidebar" id="panel-sidebar" aria-label="Menú principal">
  <a class="sidebar-brand" href="index.php?url=dashboard">
    <img src="landing-chuchuguaza/img/logo.png" alt="" width="46" height="46">
    <span>Chuchuguaza<small>Gestión interna</small></span>
  </a>

  <nav class="sidebar-nav" aria-label="Módulos del sistema">
    <a class="sidebar-link<?= $activePage === 'dashboard' ? ' active' : '' ?>" href="index.php?url=dashboard"<?= $activePage === 'dashboard' ? ' aria-current="page"' : '' ?>>
      <i class="fas fa-home sidebar-icon" aria-hidden="true"></i><span>Inicio</span>
    </a>

    <?php foreach ($menu as $claveGrupo => $grupoMenu): ?>
      <?php $grupoActivo = isset($grupoMenu['modulos'][$activePage]); ?>
      <section class="sidebar-group<?= $grupoActivo ? ' has-active' : '' ?>" data-grupo="<?= Vista::e($claveGrupo) ?>">
        <button class="sidebar-group-toggle" type="button" aria-expanded="true" aria-controls="grupo-<?= Vista::e($claveGrupo) ?>">
          <span><?= Vista::e($grupoMenu['titulo']) ?></span>
          <i class="fas fa-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="sidebar-group-links" id="grupo-<?= Vista::e($claveGrupo) ?>">
          <?php foreach ($grupoMenu['modulos'] as $rutaMenu => $itemMenu): ?>
            <?php $activo = $activePage === $rutaMenu; ?>
            <a class="sidebar-link<?= $activo ? ' active' : '' ?>" href="index.php?url=<?= Vista::e($rutaMenu) ?>"<?= $activo ? ' aria-current="page"' : '' ?>>
              <i class="fas <?= Vista::e($itemMenu['icono']) ?> sidebar-icon" aria-hidden="true"></i><span><?= Vista::e($itemMenu['titulo']) ?></span>
            </a>
          <?php endforeach; ?>
        </div>
      </section>
    <?php endforeach; ?>
  </nav>
</aside>
