<?php
/**
 * Sello de autoría y enlaces legales. Se usa en el pie del panel, en el acceso y en las páginas legales.
 */
?>
<footer class="firma">
  <a class="firma-autor" href="index.php?url=legal&amp;action=autoria" title="Autoría y propiedad del software">
    <span class="firma-sello" aria-hidden="true">EA</span>
    <span>Sistema creado por <strong><?= htmlspecialchars(APP_AUTOR_WEB, ENT_QUOTES, 'UTF-8') ?></strong></span>
  </a>
  <nav class="firma-legal" aria-label="Información legal">
    <a href="index.php?url=legal&amp;action=terminos">Términos de uso</a>
    <a href="index.php?url=legal&amp;action=privacidad">Privacidad</a>
    <span>© <?= date('Y') ?> <?= htmlspecialchars(APP_AUTOR, ENT_QUOTES, 'UTF-8') ?> · v<?= htmlspecialchars(APP_VERSION, ENT_QUOTES, 'UTF-8') ?></span>
  </nav>
</footer>
