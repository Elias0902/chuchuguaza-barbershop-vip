<?php

use App\Helpers\Vista;

$tituloPagina = 'Acceso restringido';
?>
<!doctype html>
<html lang="es">
<head>
  <?php require dirname(__DIR__) . '/partials/panelHead.php'; ?>
</head>
<body class="error-page">
  <main class="error-card">
    <img src="landing-chuchuguaza/img/logo.png" alt="Chuchuguaza" width="120" height="120">
    <p class="eyebrow">Error 403</p>
    <h1>No tienes acceso a esta sección</h1>
    <p class="intro">Si crees que es un error, pide a un administrador que revise tu rol.</p>
    <a class="btn btn-primary" href="index.php?url=dashboard"><i class="fas fa-home" aria-hidden="true"></i>Volver al inicio</a>
  </main>
</body>
</html>
