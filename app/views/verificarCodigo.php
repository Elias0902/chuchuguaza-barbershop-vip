<?php
/**
 * Paso 2 del acceso (verificación en dos pasos): código de 6 dígitos enviado al correo.
 * @var string $correo
 * @var string $mensaje
 * @var bool $mensajeOk
 * @var bool $modoDesarrollo
 */
$e = static fn(string $texto): string => htmlspecialchars($texto, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#060f26">
  <title>Verificación en dos pasos | Chuchuguaza Barbershop</title>
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
        <div class="eyebrow">Verificación en dos pasos</div>
        <h1>Revisa tu correo</h1>
        <p class="intro">Por seguridad, la cuenta de administrador necesita un código además de la contraseña. Lo enviamos a <strong><?= $e($correo) ?></strong>.</p>
      </div>
    </div>

    <?php if ($mensaje !== ''): ?>
      <p class="notice<?= $mensajeOk ? ' ok' : '' ?>" role="<?= $mensajeOk ? 'status' : 'alert' ?>"><?= $e($mensaje) ?></p>
    <?php endif; ?>
    <?php if ($modoDesarrollo): ?>
      <p class="notice ok" role="note">Modo desarrollo: el correo no se envía todavía (falta configurar SMTP). Abre el último archivo de <strong>storage/correos</strong> para ver el código.</p>
    <?php endif; ?>

    <form action="index.php?url=autenticator&amp;action=confirmar_codigo" method="post">
      <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
      <div class="field">
        <label for="codigo">Código de 6 dígitos</label>
        <input id="codigo" name="codigo" class="code-input" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="\d{6}" maxlength="6" placeholder="000000" required autofocus>
        <p class="hint">Vence en <?= (int) DOSFA_MINUTOS ?> minutos y solo sirve una vez.</p>
      </div>
      <button class="submit" type="submit">Verificar y entrar</button>
    </form>

    <form action="index.php?url=autenticator&amp;action=reenviar_codigo" method="post" class="resend">
      <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
      <button class="link-button" type="submit">No me llegó, enviar otro código</button>
    </form>

    <div class="links">
      <a class="back" href="index.php?url=autenticator&amp;action=acceso_interno">Cancelar y volver</a>
    </div>
    <?php require __DIR__ . '/partials/firma.php'; ?>
  </main>
</body>
</html>
