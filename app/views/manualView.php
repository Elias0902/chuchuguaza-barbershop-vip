<?php

use App\Helpers\Vista;

/**
 * Manual de usuario: índice a la izquierda y un capítulo por módulo permitido.
 * @var array $capitulos
 */
$tituloPagina = 'Manual de usuario';
$grupoPagina = 'Configuración';
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
            <div class="eyebrow">Configuración</div>
            <h1><i class="fas fa-book" aria-hidden="true"></i>Manual de usuario</h1>
            <p class="intro">Cómo usar cada parte del sistema, con pantallazos. Solo aparecen los módulos a los que tiene acceso tu rol. En cada módulo también tienes el botón <strong>?</strong> junto a «Nuevo».</p>
          </div>
          <div class="heading-actions">
            <button class="btn btn-secondary" type="button" onclick="window.print()"><i class="fas fa-print" aria-hidden="true"></i>Imprimir</button>
          </div>
        </section>

        <div class="doc-layout">
          <nav class="doc-index panel" aria-label="Índice del manual">
            <a href="#uso-general">Uso general</a>
            <?php foreach ($capitulos as $capitulo): ?>
              <span class="doc-index-group"><?= Vista::e($capitulo['titulo']) ?></span>
              <?php foreach ($capitulo['modulos'] as $modulo): ?>
                <a href="#manual-<?= Vista::e($modulo['ruta']) ?>"><i class="fas <?= Vista::e($modulo['icono']) ?>" aria-hidden="true"></i><?= Vista::e($modulo['titulo']) ?></a>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </nav>

          <div class="doc-body">
            <article class="panel doc-chapter" id="uso-general">
              <h2><i class="fas fa-compass" aria-hidden="true"></i>Uso general</h2>
              <p>Todos los módulos funcionan igual:</p>
              <ol>
                <li><strong>Consultar:</strong> la tabla muestra los registros. Las pestañas «Activos», «Eliminados» y «Todos» filtran por estado y «Buscar...» filtra por cualquier dato visible. En los módulos de movimientos (pagos, tickets, gastos...) puedes buscar directo por el número.</li>
                <li><strong>Registrar:</strong> pulsa el botón «Nuevo» arriba a la derecha, completa el formulario (los campos con * son obligatorios) y pulsa «Registrar».</li>
                <li><strong>Modificar:</strong> pulsa el lápiz de la fila, cambia los datos y guarda.</li>
                <li><strong>Eliminar:</strong> la papelera manda el registro a «Eliminados» (no se pierde). Desde ahí se puede «Restaurar».</li>
                <li><strong>Reporte PDF:</strong> genera un PDF con lo que muestra la pestaña elegida.</li>
              </ol>
              <p>Arriba a la derecha está tu perfil (foto, contraseña y cerrar sesión) y el botón para cambiar entre modo claro y oscuro.</p>
            </article>

            <?php foreach ($capitulos as $capitulo): ?>
              <?php foreach ($capitulo['modulos'] as $modulo): ?>
                <article class="panel doc-chapter" id="manual-<?= Vista::e($modulo['ruta']) ?>">
                  <div class="eyebrow"><?= Vista::e($capitulo['titulo']) ?></div>
                  <h2><i class="fas <?= Vista::e($modulo['icono']) ?>" aria-hidden="true"></i><?= Vista::e($modulo['titulo']) ?></h2>
                  <p><?= Vista::e($modulo['descripcion']) ?></p>
                  <?php if ($modulo['consejos']): ?>
                    <ul>
                      <?php foreach ($modulo['consejos'] as $consejo): ?>
                        <li><?= Vista::e($consejo) ?></li>
                      <?php endforeach; ?>
                    </ul>
                  <?php endif; ?>
                  <?php if ($modulo['capturas']): ?>
                    <div class="doc-shots">
                      <?php foreach ($modulo['capturas'] as $captura): ?>
                        <figure class="help-shot">
                          <img src="<?= Vista::e($captura['src']) ?>" alt="<?= Vista::e($captura['texto'] . ' de ' . $modulo['titulo']) ?>" loading="lazy">
                          <figcaption><?= Vista::e($captura['texto']) ?></figcaption>
                        </figure>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>
                  <a class="doc-link" href="index.php?url=<?= Vista::e($modulo['ruta']) ?>">Abrir <?= Vista::e($modulo['titulo']) ?> <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                </article>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </div>
      </main>
      <?php require __DIR__ . '/partials/firma.php'; ?>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
</body>
</html>
