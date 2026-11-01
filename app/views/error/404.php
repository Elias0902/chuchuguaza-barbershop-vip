<?php

use App\Helpers\Vista;

$tituloPagina = 'Página no encontrada';
?>
<!doctype html>
<html lang="es">
<head>
  <?php require dirname(__DIR__) . '/partials/panelHead.php'; ?>
</head>
<body class="error-page">
  <main class="error-card">
    <img src="landing-chuchuguaza/img/logo.png" alt="Chuchuguaza" width="120" height="120">
    <p class="eyebrow">Error 404</p>
    <h1>Esta página no existe</h1>
    <p class="intro">La dirección que abriste no corresponde a ningún módulo del sistema.</p>
    <a class="btn btn-primary" href="index.php?url=dashboard"><i class="fas fa-home" aria-hidden="true"></i>Volver al inicio</a>
  </main>
</body>
</html>
