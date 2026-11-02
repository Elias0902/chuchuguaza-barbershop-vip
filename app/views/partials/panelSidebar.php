<?php

use App\Helpers\Vista;

/**
 * Menú lateral generado desde config/modulos.php.
 * Arriba van los módulos del negocio; abajo, aparte, Configuración (con el manual de usuario)
 * y Ayuda. El manual y la ayuda los ve cualquier usuario con sesión.
 * @var array $menu
 * @var string $activePage
 */
$enlaceMenu = static function (string $ruta, string $titulo, string $icono, string $activePage): string {
    $activo = $activePage === $ruta;

    return '<a class="sidebar-link' . ($activo ? ' active' : '') . '" href="index.php?url=' . Vista::e($ruta) . '"' . ($activo ? ' aria-current="page"' : '') . '>'
        . '<i class="fas ' . Vista::e($icono) . ' sidebar-icon" aria-hidden="true"></i><span>' . Vista::e($titulo) . '</span></a>';
};
$grupoMenu = static function (string $clave, string $titulo, string $enlaces, bool $activo): string {
    return '<section class="sidebar-group' . ($activo ? ' has-active' : '') . '" data-grupo="' . Vista::e($clave) . '">'
        . '<button class="sidebar-group-toggle" type="button" aria-expanded="true" aria-controls="grupo-' . Vista::e($clave) . '">'
        . '<span>' . Vista::e($titulo) . '</span><i class="fas fa-chevron-down" aria-hidden="true"></i></button>'
        . '<div class="sidebar-group-links" id="grupo-' . Vista::e($clave) . '">' . $enlaces . '</div></section>';
};

$gruposNegocio = array_filter($menu, static fn(array $g): bool => empty($g['aparte']));
$configuracion = $menu['seguridad'] ?? ['titulo' => 'Configuración', 'modulos' => []];
?>
<button class="menu-overlay" type="button" aria-label="Cerrar menú" data-menu-close></button>
<aside class="sidebar" id="panel-sidebar" aria-label="Menú principal">
  <a class="sidebar-brand" href="index.php?url=dashboard">
    <img src="landing-chuchuguaza/img/logo.png" alt="" width="46" height="46">
    <span>Chuchuguaza<small>Gestión interna</small></span>
  </a>

  <nav class="sidebar-nav" aria-label="Módulos del sistema">
    <?= $enlaceMenu('dashboard', 'Inicio', 'fa-home', $activePage) ?>
    <?php foreach ($gruposNegocio as $claveGrupo => $grupo): ?>
      <?php
        $enlaces = '';
        foreach ($grupo['modulos'] as $ruta => $item) {
            $enlaces .= $enlaceMenu($ruta, $item['titulo'], $item['icono'], $activePage);
        }
        echo $grupoMenu($claveGrupo, $grupo['titulo'], $enlaces, isset($grupo['modulos'][$activePage]));
      ?>
    <?php endforeach; ?>
  </nav>

  <nav class="sidebar-nav sidebar-bottom" aria-label="Configuración y ayuda">
    <?php
      $enlaces = '';
      foreach ($configuracion['modulos'] as $ruta => $item) {
          $enlaces .= $enlaceMenu($ruta, $item['titulo'], $item['icono'], $activePage);
      }
      $enlaces .= $enlaceMenu('manual', 'Manual de usuario', 'fa-book', $activePage);
      echo $grupoMenu('seguridad', $configuracion['titulo'], $enlaces, isset($configuracion['modulos'][$activePage]) || $activePage === 'manual');
      echo $grupoMenu('ayuda', 'Ayuda', $enlaceMenu('ayuda', 'Centro de ayuda', 'fa-life-ring', $activePage), $activePage === 'ayuda');
    ?>
  </nav>
</aside>
