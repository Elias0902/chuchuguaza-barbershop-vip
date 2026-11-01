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
  <style>
    :root {
      color-scheme: dark;
      font-family: 'DM Sans', system-ui, sans-serif;
      color: #fff;
      background: #060f26;
      font-synthesis: none;
      text-rendering: optimizeLegibility;
      -webkit-font-smoothing: antialiased;
    }
    * { box-sizing: border-box; }
    body {
      min-height: 100vh;
      min-height: 100svh;
      margin: 0;
      display: grid;
      place-items: center;
      padding: 24px;
      background:
        linear-gradient(135deg, rgba(6, 15, 38, .92), rgba(10, 24, 56, .78)),
        url("landing-chuchuguaza/img/entrada.jpg") center / cover fixed;
    }
    a { color: inherit; }
    .card {
      width: min(100%, 430px);
      padding: clamp(28px, 7vw, 48px);
      border: 1px solid rgba(255,255,255,.14);
      border-radius: 22px;
      background: rgba(6, 15, 38, .93);
      box-shadow: 0 24px 80px rgba(0,0,0,.4);
      backdrop-filter: blur(14px);
    }
    .brand { display: grid; justify-items: center; gap: 18px; margin-bottom: 32px; text-align: center; }
    .brand img { width: 168px; height: auto; }
    .eyebrow { color: #ef3b47; font-size: 12px; font-weight: 700; letter-spacing: .18em; text-transform: uppercase; }
    h1 { margin: 0; font-size: clamp(28px, 8vw, 36px); line-height: 1.1; }
    .intro { margin: 10px 0 0; color: #b9c1d6; font-size: 15px; line-height: 1.6; }
    .notice {
      margin: 0 0 24px;
      padding: 14px 16px;
      border: 1px solid rgba(239,59,71,.34);
      border-radius: 12px;
      background: rgba(211,32,47,.12);
      color: #f4d6d9;
      font-size: 14px;
      line-height: 1.55;
    }
    .field { display: grid; gap: 8px; margin-top: 18px; }
    label { color: #e8ebf3; font-size: 14px; font-weight: 600; }
    input {
      width: 100%;
      min-height: 48px;
      padding: 0 14px;
      border: 1px solid rgba(255,255,255,.18);
      border-radius: 10px;
      outline: none;
      background: #0a1838;
      color: #fff;
      font: inherit;
    }
    input:focus { border-color: #ef3b47; box-shadow: 0 0 0 3px rgba(239,59,71,.16); }
    input::placeholder { color: #8993ad; }
    .submit {
      width: 100%;
      min-height: 48px;
      margin-top: 24px;
      border: 0;
      border-radius: 10px;
      background: #d3202f;
      color: #fff;
      font: inherit;
      font-weight: 700;
      cursor: pointer;
    }
    .submit:hover { background: #ef3b47; }
    .back { display: block; margin-top: 24px; color: #b9c1d6; font-size: 14px; text-align: center; text-decoration: none; }
    .back:hover { color: #fff; }
    :focus-visible { outline: 3px solid #ef3b47; outline-offset: 3px; }
    @media (prefers-reduced-motion: reduce) { *, *::before, *::after { scroll-behavior: auto !important; } }
  </style>
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

    <a class="back" href="landing-chuchuguaza/index.html">Volver a la página principal</a>
  </main>
</body>
</html>
