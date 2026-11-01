<?php

use App\Helpers\Formato;
use App\Helpers\Vista;

/**
 * Mi perfil: información, cambio de foto, datos de la cuenta y contraseña.
 * @var array $perfil
 */
$tituloPagina = 'Mi perfil';
$grupoPagina = 'Cuenta';
$foto = Vista::avatar($perfil['avatar'] ?? null);
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
            <div class="eyebrow">Cuenta</div>
            <h1><i class="fas fa-user-circle" aria-hidden="true"></i>Mi perfil</h1>
            <p class="intro">Consulta tu información, cambia tu foto, tus datos y tu contraseña.</p>
          </div>
        </section>

        <div class="profile-layout">
          <aside class="panel profile-card">
            <div class="profile-photo">
              <span class="avatar avatar-xl" data-avatar>
                <?php if ($foto !== null): ?>
                  <img src="<?= $foto ?>" alt="Foto de perfil">
                <?php else: ?>
                  <?= Vista::e(Vista::iniciales($perfil['nombre_usuario'])) ?>
                <?php endif; ?>
              </span>
              <label class="photo-button" title="Cambiar foto">
                <i class="fas fa-camera" aria-hidden="true"></i>
                <span class="sr-only">Cambiar foto de perfil</span>
                <input type="file" accept="image/jpeg,image/png,image/webp" data-foto>
              </label>
            </div>
            <h2 data-perfil-nombre>@<?= Vista::e($perfil['nombre_usuario']) ?></h2>
            <span class="badge azul"><?= Vista::e($perfil['nombre_rol'] ?? 'Sin rol') ?></span>
            <p class="profile-hint">JPG, PNG o WEBP de hasta 2 MB.</p>
            <nav class="profile-tabs" aria-label="Secciones del perfil">
              <a href="#mi-perfil" data-tab="mi-perfil" class="active"><i class="fas fa-user" aria-hidden="true"></i>Mi perfil</a>
              <a href="#datos" data-tab="datos"><i class="fas fa-id-card" aria-hidden="true"></i>Datos de la cuenta</a>
              <a href="#seguridad" data-tab="seguridad"><i class="fas fa-key" aria-hidden="true"></i>Seguridad</a>
            </nav>
          </aside>

          <div>
            <section class="panel profile-section" id="mi-perfil" data-seccion="mi-perfil">
              <div class="panel-heading"><h2>Información de tu cuenta</h2></div>
              <dl class="info-grid">
                <div><dt>Nombre de usuario</dt><dd data-perfil-nombre>@<?= Vista::e($perfil['nombre_usuario']) ?></dd></div>
                <div><dt>Correo electrónico</dt><dd data-perfil-correo><?= Vista::e($perfil['email_usuario']) ?></dd></div>
                <div><dt>Rol</dt><dd><?= Vista::e($perfil['nombre_rol'] ?? '—') ?></dd></div>
                <div><dt>Descripción del rol</dt><dd><?= Vista::e($perfil['descripcion_rol'] ?? '—') ?></dd></div>
                <div><dt>Último acceso</dt><dd><?= Vista::e(Formato::valor($perfil['ultimo_acceso'], 'fechahora')) ?></dd></div>
                <div><dt>Miembro desde</dt><dd><?= Vista::e(Formato::valor($perfil['fecha_registro'], 'fecha')) ?></dd></div>
              </dl>
            </section>

            <section class="panel profile-section" id="datos" data-seccion="datos" hidden>
              <div class="panel-heading"><h2>Datos de la cuenta</h2></div>
              <form class="form-grid" data-form-perfil="datos" novalidate>
                <div class="field">
                  <label for="nombre_usuario">Nombre de usuario</label>
                  <input id="nombre_usuario" name="nombre_usuario" maxlength="30" required value="<?= Vista::e($perfil['nombre_usuario']) ?>">
                  <small class="field-help">De 4 a 30 caracteres: letras, números, punto, guion o guion bajo.</small>
                </div>
                <div class="field">
                  <label for="email_usuario">Correo electrónico</label>
                  <input id="email_usuario" name="email_usuario" type="email" maxlength="100" required value="<?= Vista::e($perfil['email_usuario']) ?>">
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-save" aria-hidden="true"></i>Guardar cambios</button></div>
              </form>
            </section>

            <section class="panel profile-section" id="seguridad" data-seccion="seguridad" hidden>
              <div class="panel-heading"><h2>Cambiar contraseña</h2></div>
              <form class="form-grid" data-form-perfil="clave" novalidate>
                <div class="field full">
                  <label for="clave_actual">Contraseña actual</label>
                  <input id="clave_actual" name="clave_actual" type="password" autocomplete="current-password" required>
                </div>
                <div class="field">
                  <label for="clave_nueva">Nueva contraseña</label>
                  <input id="clave_nueva" name="clave_nueva" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
                  <small class="field-help">Mínimo 8 caracteres con letras y números.</small>
                </div>
                <div class="field">
                  <label for="clave_confirmacion">Confirmar nueva contraseña</label>
                  <input id="clave_confirmacion" name="clave_confirmacion" type="password" autocomplete="new-password" required>
                </div>
                <div class="form-actions"><button class="btn btn-primary" type="submit"><i class="fas fa-key" aria-hidden="true"></i>Actualizar contraseña</button></div>
              </form>
            </section>
          </div>
        </div>
      </main>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
  <script src="<?= Vista::asset('assets/js/perfil.js') ?>"></script>
</body>
</html>
