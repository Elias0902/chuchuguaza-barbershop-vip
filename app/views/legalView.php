<?php

use App\Helpers\Vista;

/**
 * Páginas legales públicas: términos de uso, política de privacidad y autoría.
 * @var string $pagina
 * @var array $paginas
 */
$autor = APP_AUTOR;
$web = APP_AUTOR_WEB;
$negocio = 'Chuchuguaza Barbershop';
$contacto = 'el WhatsApp de la barbería (0426-551 4525) o en el local (Calle 6, Barquisimeto, Lara)';
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="theme-color" content="#060f26">
  <meta name="author" content="<?= Vista::e("$autor · $web") ?>">
  <title><?= Vista::e($paginas[$pagina]) ?> | <?= Vista::e($negocio) ?></title>
  <link rel="icon" href="landing-chuchuguaza/img/favicon.png">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= Vista::asset('assets/css/acceso.css') ?>">
</head>
<body class="legal-page">
  <main class="card legal">
    <a class="legal-brand" href="landing-chuchuguaza/index.html"><img src="landing-chuchuguaza/img/logo.png" alt="Chuchuguaza Barbershop VIP"></a>
    <nav class="legal-tabs" aria-label="Documentos legales">
      <?php foreach ($paginas as $clave => $titulo): ?>
        <a href="index.php?url=legal&amp;action=<?= Vista::e($clave) ?>"<?= $clave === $pagina ? ' aria-current="page"' : '' ?>><?= Vista::e($titulo) ?></a>
      <?php endforeach; ?>
    </nav>

    <h1><?= Vista::e($paginas[$pagina]) ?></h1>
    <p class="legal-date">Última actualización: <?= Vista::e(LEGAL_ACTUALIZACION) ?></p>

    <?php if ($pagina === 'terminos'): ?>
      <p>Estos términos regulan el uso de la página web de <?= Vista::e($negocio) ?> y de su sistema interno de gestión. Al usarlos aceptas estas condiciones.</p>
      <h2>1. Página web pública</h2>
      <p>La página informa sobre los servicios, cursos y políticas de la barbería. Los precios, horarios y promociones pueden cambiar sin aviso; los vigentes son los que confirme la barbería al momento de atenderte. Las citas que se solicitan por WhatsApp quedan sujetas a disponibilidad.</p>
      <h2>2. Sistema interno</h2>
      <p>El sistema interno es solo para el personal autorizado de la barbería. Cada usuario es responsable de su cuenta y de mantener su contraseña en secreto, y solo puede usar los módulos que le asigna su rol. Todas las operaciones quedan registradas en la bitácora con el usuario, la fecha y la hora.</p>
      <h2>3. Uso no permitido</h2>
      <ul>
        <li>Intentar entrar con cuentas ajenas o saltarse los permisos del rol.</li>
        <li>Copiar, descargar o divulgar datos de clientes fuera de las tareas del negocio.</li>
        <li>Copiar, modificar, revender o redistribuir el software (ver «Autoría y propiedad del software»).</li>
      </ul>
      <h2>4. Políticas de servicio</h2>
      <p>Las reglas de citas, pagos, garantía y cancelaciones son las publicadas en la sección «Políticas» de la página web.</p>
      <h2>5. Responsabilidad</h2>
      <p>El sistema hace respaldos automáticos de la información, pero la barbería es responsable de conservar copias fuera del equipo donde funciona. El desarrollador no responde por pérdidas causadas por fallas del equipo, cortes de luz, mal uso o cambios hechos por terceros al software.</p>
      <h2>6. Cambios y contacto</h2>
      <p>Estos términos pueden actualizarse; la fecha de arriba indica la última versión. Para dudas escribe a <?= Vista::e($contacto) ?>.</p>

    <?php elseif ($pagina === 'privacidad'): ?>
      <p><?= Vista::e($negocio) ?> cuida los datos personales de sus clientes, alumnos y personal. Esta política explica qué datos se guardan, para qué y qué derechos tienes.</p>
      <h2>1. Datos que guardamos</h2>
      <ul>
        <li><strong>Clientes:</strong> nombre, apellido, teléfono y, si lo das, cédula; historial de citas, servicios, compras y pagos.</li>
        <li><strong>Alumnos de la academia:</strong> sus inscripciones y los montos pagados.</li>
        <li><strong>Personal:</strong> datos de su cuenta de usuario, rol, accesos y operaciones realizadas.</li>
        <li><strong>Pagos:</strong> monto, método y referencia de la operación. No guardamos números de tarjetas ni claves bancarias.</li>
      </ul>
      <h2>2. Para qué los usamos</h2>
      <p>Para agendar y confirmar citas, registrar la atención y los pagos, calcular la nómina de los barberos, llevar la caja y mejorar el servicio con estadísticas internas. No vendemos ni cedemos tus datos a terceros ni los usamos para publicidad de otras empresas.</p>
      <h2>3. Quién puede verlos</h2>
      <p>Solo el personal autorizado de la barbería, según los permisos de su rol. Por ejemplo, un barbero solo ve sus propias citas y tickets.</p>
      <h2>4. Cómo los protegemos</h2>
      <p>Las contraseñas se guardan cifradas, las cuentas se bloquean tras varios intentos fallidos, cada acción queda en una bitácora y la información se respalda automáticamente cada día.</p>
      <h2>5. Cuánto tiempo los guardamos</h2>
      <p>Mientras seas cliente y el tiempo necesario para la contabilidad del negocio. Los registros eliminados en el sistema se conservan marcados como eliminados para mantener el historial y pueden borrarse definitivamente si lo solicitas, salvo los que deban conservarse por obligaciones contables.</p>
      <h2>6. Tus derechos</h2>
      <p>Puedes pedir ver, corregir o eliminar tus datos escribiendo a <?= Vista::e($contacto) ?>.</p>
      <h2>7. Página web y cookies</h2>
      <p>La página pública no usa cookies de publicidad ni de seguimiento. El sistema interno usa una cookie de sesión, necesaria para mantener iniciada la sesión del personal, que se borra al cerrar sesión.</p>

    <?php else: ?>
      <div class="legal-seal">
        <span class="legal-seal-mark">EA</span>
        <div>
          <strong><?= Vista::e($autor) ?></strong>
          <a href="https://<?= Vista::e($web) ?>" target="_blank" rel="noopener"><?= Vista::e($web) ?></a>
        </div>
      </div>
      <p>El sistema de gestión de <?= Vista::e($negocio) ?> (versión <?= Vista::e(APP_VERSION) ?>) y su página web fueron ideados, diseñados y programados por <strong><?= Vista::e($autor) ?></strong> (<?= Vista::e($web) ?>).</p>
      <h2>Derechos de autor</h2>
      <p>© <?= date('Y') ?> <?= Vista::e($autor) ?>. Todos los derechos reservados. El código fuente, el diseño de las pantallas, la estructura de la base de datos, el sistema de sugerencias y la documentación son obra de su autor.</p>
      <h2>Licencia de uso</h2>
      <p><?= Vista::e($negocio) ?> tiene licencia para usar el sistema en su negocio. Esa licencia no permite copiarlo, revenderlo, entregarlo a terceros, publicarlo ni crear versiones derivadas sin autorización escrita del autor.</p>
      <h2>Datos y marca del negocio</h2>
      <p>La información registrada en el sistema (clientes, ventas, inventario) y la marca, el logo y las fotos de Chuchuguaza pertenecen a <?= Vista::e($negocio) ?>.</p>
      <h2>Contacto del desarrollador</h2>
      <p>Soporte, mejoras o un sistema para tu negocio: <a href="https://<?= Vista::e($web) ?>" target="_blank" rel="noopener"><?= Vista::e($web) ?></a>.</p>
    <?php endif; ?>

    <div class="links">
      <a class="back" href="landing-chuchuguaza/index.html">Volver a la página principal</a>
      <a class="back" href="index.php?url=autenticator&amp;action=acceso_interno">Acceso interno</a>
    </div>
    <?php require __DIR__ . '/partials/firma.php'; ?>
  </main>
</body>
</html>
