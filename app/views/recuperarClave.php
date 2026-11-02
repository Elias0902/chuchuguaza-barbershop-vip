<?php
/**
 * Recuperación de contraseña. $paso: solicitar | enviado | restablecer | invalido.
 */
$e = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#060f26">
  <meta name="referrer" content="no-referrer">
  <title>Recuperar contraseña | Chuchuguaza Barbershop</title>
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
        <h1><?= $paso === 'restablecer' ? 'Nueva contraseña' : 'Recuperar contraseña' ?></h1>
        <?php if ($paso === 'solicitar'): ?>
          <p class="intro">Escribe tu usuario o correo y te enviaremos un enlace para crear una contraseña nueva.</p>
        <?php elseif ($paso === 'restablecer'): ?>
          <p class="intro">Hola, <?= $e($nombreUsuario) ?>. Elige tu nueva contraseña.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($mensaje)): ?>
      <p class="notice" role="alert"><?= $e($mensaje) ?></p>
    <?php endif; ?>

    <?php if ($paso === 'solicitar'): ?>
      <form action="index.php?url=autenticator&amp;action=enviar_recuperacion" method="post">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <div class="field">
          <label for="identificador">Usuario o correo</label>
          <input id="identificador" name="identificador" type="text" autocomplete="username" maxlength="100" required autofocus>
        </div>
        <button class="submit" type="submit">Enviar enlace</button>
      </form>

    <?php elseif ($paso === 'enviado'): ?>
      <p class="notice ok" role="status">Si los datos corresponden a una cuenta activa, enviamos un enlace a su correo. Vence en <?= (int) RECUPERACION_MINUTOS ?> minutos.</p>

    <?php elseif ($paso === 'restablecer'): ?>
      <form action="index.php?url=autenticator&amp;action=guardar_clave" method="post">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <input type="hidden" name="token" value="<?= $e($token) ?>">
        <div class="field">
          <label for="clave">Contraseña nueva</label>
          <input id="clave" name="clave" type="password" autocomplete="new-password" minlength="8" maxlength="72" required autofocus>
          <p class="hint">Mínimo 8 caracteres, con letras y números.</p>
        </div>
        <div class="field">
          <label for="confirmacion">Repite la contraseña</label>
          <input id="confirmacion" name="confirmacion" type="password" autocomplete="new-password" minlength="8" maxlength="72" required>
        </div>
        <button class="submit" type="submit">Guardar contraseña</button>
      </form>

    <?php else: ?>
      <p class="notice" role="alert">El enlace no es válido, ya se usó o venció.</p>
      <a class="submit" href="index.php?url=autenticator&amp;action=recuperar" style="display:grid;place-items:center;text-decoration:none">Pedir un enlace nuevo</a>
    <?php endif; ?>

    <div class="links">
      <a class="back" href="index.php?url=autenticator&amp;action=acceso_interno">Volver a iniciar sesión</a>
    </div>
  </main>
</body>
</html>
