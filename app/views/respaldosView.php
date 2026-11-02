<?php

use App\Helpers\Formato;
use App\Helpers\Vista;
use App\Models\Respaldo;

/**
 * @var array $respaldos
 * @var string|null $ultimoAutomatico
 * @var bool $puedeGenerar
 * @var bool $puedeRestaurar
 */
$tituloPagina = 'Respaldos';
$grupoPagina = 'Configuración';
$coloresTipo = ['Manual' => 'azul', 'Automático' => 'verde', 'Antes de restaurar' => 'amarillo'];
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
            <div class="eyebrow">Configuración</div>
            <h1><i class="fas fa-database" aria-hidden="true"></i>Respaldos</h1>
            <p class="intro">Copias de seguridad de toda la información del sistema. Se hacen solas cada <?= Respaldo::HORAS_ENTRE_AUTOMATICOS ?> horas y puedes crear una cuando quieras, por ejemplo antes de un cambio grande.</p>
          </div>
          <div class="heading-actions">
            <?php if ($puedeGenerar): ?>
              <button class="btn btn-primary" type="button" data-generar><i class="fas fa-plus" aria-hidden="true"></i>Respaldar ahora</button>
            <?php endif; ?>
          </div>
        </section>
        <p class="analysis-meta">
          Último respaldo automático: <?= $ultimoAutomatico ? Vista::e(Formato::valor($ultimoAutomatico, 'fechahora')) : 'todavía ninguno' ?> ·
          Se guardan los últimos <?= Respaldo::MAX_AUTOMATICOS ?> automáticos de cada base; los manuales se conservan siempre.
          Descarga uno de vez en cuando y guárdalo fuera de esta computadora.
        </p>

        <section class="panel">
          <div class="table-wrap">
            <table class="data-table">
              <thead>
                <tr><th>Fecha</th><th>Base</th><th>Tipo</th><th>Hecho por</th><th class="num">Registros</th><th class="num">Tamaño</th><th class="num">Acciones</th></tr>
              </thead>
              <tbody>
                <?php if (!$respaldos): ?>
                  <tr><td class="empty" colspan="7">Todavía no hay respaldos. El primero automático se hace en unos segundos.</td></tr>
                <?php endif; ?>
                <?php foreach ($respaldos as $r): ?>
                  <tr>
                    <td><?= Vista::e(Formato::valor($r['fecha_creacion'], 'fechahora')) ?></td>
                    <td><?= Vista::e($r['tipo_bd']) ?></td>
                    <td><span class="badge <?= Vista::e($coloresTipo[$r['tipo_respaldo']] ?? 'gris') ?>"><?= Vista::e($r['tipo_respaldo']) ?></span></td>
                    <td><?= Vista::e($r['nombre_usuario'] ?? '—') ?></td>
                    <td class="num"><?= Vista::e(Formato::valor($r['total_registros'], 'entero')) ?></td>
                    <td class="num"><?= Vista::e(number_format((float) $r['peso_kb'], 1, ',', '.')) ?> KB</td>
                    <td class="num">
                      <div class="row-actions">
                        <a class="icon-button" href="index.php?url=respaldos&amp;action=descargar&amp;id=<?= (int) $r['id_respaldo'] ?>" title="Descargar" aria-label="Descargar respaldo"><i class="fas fa-download" aria-hidden="true"></i></a>
                        <?php if ($puedeRestaurar): ?>
                          <button class="icon-button danger" type="button" title="Restaurar" aria-label="Restaurar este respaldo" data-restaurar="<?= (int) $r['id_respaldo'] ?>" data-descripcion="<?= Vista::e($r['tipo_bd'] . ' del ' . Formato::valor($r['fecha_creacion'], 'fechahora')) ?>"><i class="fas fa-undo" aria-hidden="true"></i></button>
                        <?php endif; ?>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
      <?php require __DIR__ . '/partials/firma.php'; ?>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
  <script src="<?= Vista::asset('assets/js/respaldos.js') ?>"></script>
</body>
</html>
