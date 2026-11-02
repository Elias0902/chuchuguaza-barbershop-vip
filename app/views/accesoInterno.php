<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#060f26">
  <title>Acceso interno | Chuchuguaza Barbershop</title>
  <meta name="description" content="Acceso al sistema interno de gestión de Chuchuguaza Barbershop.">
  <link rel="icon" href="landing-chuchuguaza/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/acceso.css">
</head>
<body>
  <main class="card">
    <div class="brand">
      <img src="landing-chuchuguaza/img/logo.png" alt="Chuchuguaza Barbershop VIP">
      <div>
        <div class="eyebrow">Sistema interno</div>
        <?php if (isset($_SESSION['s_usuario'])): ?>
          <h1>Bienvenido</h1>
          <p class="intro"><?= htmlspecialchars($_SESSION['s_usuario']['nombre_usuario'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php else: ?>
          <h1>Bienvenido</h1>
          <p class="intro">Acceso para el equipo de Chuchuguaza Barbershop.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (isset($_SESSION['s_usuario'])): ?>
      <p class="notice" role="status">Tu sesión está activa. Ya puedes administrar el sistema.</p>
      <a class="submit" href="index.php?url=dashboard" style="display:grid;place-items:center;text-decoration:none">Abrir inicio</a>
      <form action="index.php?url=autenticator&amp;action=cerrar_interno" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <button class="submit" type="submit">Cerrar sesión</button>
      </form>
    <?php else: ?>
      <form action="index.php?url=autenticator&amp;action=ingresar_interno" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <?php if (!empty($mensajeAcceso)): ?>
          <p class="notice" role="alert"><?= htmlspecialchars($mensajeAcceso, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <div class="field">
          <label for="username">Usuario</label>
          <input id="username" name="username" type="text" autocomplete="username" maxlength="100" placeholder="Tu usuario" required autofocus>
        </div>
        <div class="field">
          <label for="password">Contraseña</label>
          <input id="password" name="password" type="password" autocomplete="current-password" maxlength="4096" placeholder="Tu contraseña" required>
        </div>
        <button class="submit" type="submit">Iniciar sesión</button>
      </form>
    <?php endif; ?>

    <div class="links">
      <?php if (!isset($_SESSION['s_usuario'])): ?><a class="back" href="index.php?url=autenticator&amp;action=recuperar">¿Olvidaste tu contraseña?</a><?php endif; ?>
      <a class="back" href="landing-chuchuguaza/index.html">Volver a la página principal</a>
    </div>
  </main>
</body>
</html>
