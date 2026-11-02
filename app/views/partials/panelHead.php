<?php

use App\Helpers\Vista;

/** @var string $tituloPagina */
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#060f26">
<meta name="author" content="<?= Vista::e(APP_AUTOR . " · " . APP_AUTOR_WEB) ?>">
<meta name="csrf-token" content="<?= Vista::e($csrf ?? '') ?>">
<script>
  // Tema antes de pintar: el guardado por el usuario o, si no hay, el del sistema operativo.
  (function () {
    var tema = null;
    try { tema = localStorage.getItem('panel.tema'); } catch (e) {}
    if (tema !== 'claro' && tema !== 'oscuro') {
      tema = window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'claro' : 'oscuro';
    }
    document.documentElement.dataset.tema = tema;
  })();
</script>
<title><?= Vista::e($tituloPagina) ?> | <?= Vista::e(APP_NOMBRE) ?></title>
<link rel="icon" href="landing-chuchuguaza/img/favicon.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= Vista::asset('assets/css/fonts.min.css') ?>">
<link rel="stylesheet" href="<?= Vista::asset('assets/css/panel.css') ?>">
