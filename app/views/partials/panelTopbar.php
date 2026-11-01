<?php

use App\Helpers\Vista;

/**
 * Barra superior: botón de menú (móvil), título de la página y menú del perfil.
 * @var array|null $usuarioSesion
 * @var string $csrf
 * @var string $tituloPagina
 * @var string $grupoPagina
 */
$nombreUsuario = (string) ($usuarioSesion['nombre_usuario'] ?? 'Usuario');
$fotoUsuario = Vista::avatar($usuarioSesion['img_usuario'] ?? null);
?>
<header class="topbar">
  <button class="menu-toggle" type="button" aria-label="Abrir menú" aria-controls="panel-sidebar" aria-expanded="false" data-menu-toggle>
    <i class="fas fa-bars" aria-hidden="true"></i>
  </button>
  <div class="topbar-title">
    <?php if (!empty($grupoPagina)): ?><span class="topbar-group"><?= Vista::e($grupoPagina) ?></span><?php endif; ?>
    <span class="topbar-page"><?= Vista::e($tituloPagina) ?></span>
  </div>

  <div class="topbar-actions">
  <?php if (isset($permisosSesion['buzon'])): ?>
    <?php $nuevasBuzon = (int) ($sugerenciasNuevas ?? 0); ?>
    <a class="theme-toggle inbox-button" href="index.php?url=buzon" title="Buzón de sugerencias"
       aria-label="Buzón de sugerencias<?= $nuevasBuzon > 0 ? ': ' . $nuevasBuzon . ($nuevasBuzon === 1 ? ' nueva' : ' nuevas') : '' ?>">
      <i class="fas fa-inbox" aria-hidden="true"></i>
      <?php if ($nuevasBuzon > 0): ?><span class="inbox-badge" aria-hidden="true"><?= $nuevasBuzon > 9 ? '9+' : $nuevasBuzon ?></span><?php endif; ?>
    </a>
  <?php endif; ?>
  <button class="theme-toggle" type="button" aria-label="Cambiar a modo claro" title="Cambiar tema" data-tema-toggle>
    <i class="fas fa-sun" aria-hidden="true"></i>
  </button>

  <div class="profile-menu" data-dropdown>
    <button class="profile-button" type="button" aria-haspopup="true" aria-expanded="false" data-dropdown-toggle>
      <span class="avatar avatar-sm" data-avatar>
        <?php if ($fotoUsuario !== null): ?>
          <img src="<?= $fotoUsuario ?>" alt="">
        <?php else: ?>
          <?= Vista::e(Vista::iniciales($nombreUsuario)) ?>
        <?php endif; ?>
      </span>
      <span class="profile-text">
        <strong data-perfil-nombre><?= Vista::e($nombreUsuario) ?></strong>
        <small><?= Vista::e($usuarioSesion['rol_usuario'] ?? '') ?></small>
      </span>
      <i class="fas fa-chevron-down profile-chevron" aria-hidden="true"></i>
    </button>

    <div class="dropdown" role="menu" hidden data-dropdown-menu>
      <div class="dropdown-header">
        <span class="avatar avatar-md" data-avatar>
          <?php if ($fotoUsuario !== null): ?>
            <img src="<?= $fotoUsuario ?>" alt="">
          <?php else: ?>
            <?= Vista::e(Vista::iniciales($nombreUsuario)) ?>
          <?php endif; ?>
        </span>
        <div>
          <strong data-perfil-nombre><?= Vista::e($nombreUsuario) ?></strong>
          <small data-perfil-correo><?= Vista::e($usuarioSesion['email_usuario'] ?? '') ?></small>
        </div>
      </div>
      <a class="dropdown-item" role="menuitem" href="index.php?url=perfil#mi-perfil"><i class="fas fa-user-circle" aria-hidden="true"></i>Mi perfil</a>
      <a class="dropdown-item" role="menuitem" href="index.php?url=perfil#datos"><i class="fas fa-id-card" aria-hidden="true"></i>Datos de la cuenta</a>
      <a class="dropdown-item" role="menuitem" href="index.php?url=perfil#seguridad"><i class="fas fa-key" aria-hidden="true"></i>Seguridad</a>
      <form class="dropdown-logout" action="index.php?url=autenticator&amp;action=cerrar_interno" method="post">
        <input type="hidden" name="csrf_token" value="<?= Vista::e($csrf) ?>">
        <button class="dropdown-item danger" role="menuitem" type="submit"><i class="fas fa-sign-out-alt" aria-hidden="true"></i>Cerrar sesión</button>
      </form>
    </div>
  </div>
  </div>
</header>
