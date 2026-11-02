<?php

use App\Helpers\Vista;

/**
 * Centro de ayuda con buscador (el filtro se hace en el navegador).
 * @var array $temas
 * @var array $permisosSesion
 */
$tituloPagina = 'Centro de ayuda';
$grupoPagina = 'Ayuda';
$puedeAbrir = static fn(?string $ruta): bool => $ruta !== null && ($ruta === 'perfil' || isset($permisosSesion[$ruta]));
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
            <div class="eyebrow">Ayuda</div>
            <h1><i class="fas fa-life-ring" aria-hidden="true"></i>Centro de ayuda</h1>
            <p class="intro">Respuestas rápidas sobre cómo funciona todo el sistema. Escribe lo que buscas: «cobrar», «caja», «contraseña», «respaldo»...</p>
          </div>
          <div class="heading-actions">
            <a class="btn btn-secondary" href="index.php?url=manual"><i class="fas fa-book" aria-hidden="true"></i>Manual de usuario</a>
          </div>
        </section>

        <label class="search help-search">
          <i class="fas fa-search" aria-hidden="true"></i>
          <span class="sr-only">Buscar en la ayuda</span>
          <input type="search" placeholder="¿Qué necesitas hacer?" autocomplete="off" autofocus data-buscar-ayuda>
        </label>
        <p class="empty-note" data-sin-resultados hidden><i class="fas fa-search" aria-hidden="true"></i> No encontramos nada con esa búsqueda. Prueba con otra palabra o revisa el manual de usuario.</p>

        <div class="faq">
          <?php foreach ($temas as $tema): ?>
            <section class="panel faq-topic" data-tema>
              <h2><i class="fas <?= Vista::e($tema['icono']) ?>" aria-hidden="true"></i><?= Vista::e($tema['titulo']) ?></h2>
              <?php foreach ($tema['preguntas'] as $p): ?>
                <details class="faq-item" data-pregunta>
                  <summary><?= Vista::e($p[0]) ?></summary>
                  <p><?= Vista::e($p[1]) ?></p>
                  <?php if ($puedeAbrir($p[2] ?? null)): ?>
                    <a class="doc-link" href="index.php?url=<?= Vista::e($p[2]) ?>">Ir al módulo <i class="fas fa-arrow-right" aria-hidden="true"></i></a>
                  <?php endif; ?>
                </details>
              <?php endforeach; ?>
            </section>
          <?php endforeach; ?>
        </div>
      </main>
      <?php require __DIR__ . '/partials/firma.php'; ?>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
  <script>
    (() => {
      const normalizar = (t) => t.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
      const buscador = document.querySelector('[data-buscar-ayuda]');
      const vacio = document.querySelector('[data-sin-resultados]');
      buscador.addEventListener('input', () => {
        const palabras = normalizar(buscador.value.trim()).split(/\s+/).filter(Boolean);
        let visibles = 0;
        document.querySelectorAll('[data-tema]').forEach((tema) => {
          let enTema = 0;
          tema.querySelectorAll('[data-pregunta]').forEach((item) => {
            const coincide = palabras.every((p) => normalizar(item.textContent).includes(p));
            item.hidden = !coincide;
            item.open = palabras.length > 0 && coincide;
            if (coincide) enTema++;
          });
          tema.hidden = enTema === 0;
          visibles += enTema;
        });
        vacio.hidden = visibles > 0;
      });
    })();
  </script>
</body>
</html>
