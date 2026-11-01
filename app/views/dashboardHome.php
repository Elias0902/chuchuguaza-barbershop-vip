<?php

use App\Helpers\Formato;
use App\Helpers\Grafico;
use App\Helpers\Vista;
use App\Models\Analisis\Hallazgo;

/**
 * @var array $resumen
 * @var array $proximasCitas
 * @var array $stockBajo
 * @var array $ingresosSemana
 * @var array $sugerencias
 * @var array $menu
 * @var array|null $usuarioSesion
 */
$tituloPagina = 'Inicio';
$grupoPagina = 'Panel de control';
$diasSemana = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$puntosSemana = array_map(static fn(array $dia): array => [
    'etiqueta' => $diasSemana[(int) date('w', strtotime($dia['dia']))],
    'detalle' => Formato::valor($dia['dia'], 'fecha'),
    'valor' => $dia['total'],
], $ingresosSemana);
$tarjetas = [
    ['Ingresos de hoy', Formato::valor($resumen['ingresos_hoy'] ?? 0, 'moneda'), Formato::cantidad((int) ($resumen['tickets_hoy'] ?? 0), 'ticket atendido hoy', 'tickets atendidos hoy'), 'fa-money-bill-wave', 'pagos'],
    ['Citas pendientes hoy', (int) ($resumen['citas_hoy'] ?? 0), 'Agenda del día', 'fa-calendar-check', 'citas'],
    ['Tickets por cobrar', (int) ($resumen['tickets_pendientes'] ?? 0), 'Con saldo pendiente', 'fa-receipt', 'tickets'],
    ['Clientes activos', (int) ($resumen['clientes'] ?? 0), Formato::cantidad((int) ($resumen['barberos'] ?? 0), 'barbero activo', 'barberos activos'), 'fa-users', 'clientes'],
];
// Solo se muestran los indicadores de los módulos que el rol puede consultar.
$tarjetas = array_filter($tarjetas, static fn(array $tarjeta): bool => isset($permisosSesion[$tarjeta[4]]));
$verPagos = isset($permisosSesion['pagos']);
$verCitas = isset($permisosSesion['citas']);
$verProductos = isset($permisosSesion['productos']);
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
            <div class="eyebrow">Panel de control</div>
            <h1>Hola, <?= Vista::e($usuarioSesion['nombre_usuario'] ?? 'equipo') ?></h1>
            <p class="intro">Resumen del día en Chuchuguaza · <?= Vista::e(date('d/m/Y')) ?></p>
          </div>
          <div class="heading-actions">
            <?php if (isset($permisosSesion['cierres'])): ?>
              <?php if (!empty($resumen['caja_abierta'])): ?>
                <a class="btn btn-secondary" href="index.php?url=cierres"><i class="fas fa-coins" aria-hidden="true"></i>Caja #<?= (int) $resumen['caja_abierta'] ?> abierta</a>
              <?php else: ?>
                <a class="btn btn-secondary" href="index.php?url=cierres"><i class="fas fa-lock" aria-hidden="true"></i>Caja cerrada · abrir</a>
              <?php endif; ?>
            <?php endif; ?>
            <?php if (in_array('registrar', $permisosSesion['tickets'] ?? [], true)): ?>
              <a class="btn btn-primary" href="index.php?url=tickets"><i class="fas fa-plus" aria-hidden="true"></i>Nuevo ticket</a>
            <?php endif; ?>
          </div>
        </section>

        <?php if ($tarjetas): ?>
        <section class="stats" aria-label="Indicadores del día">
          <?php foreach ($tarjetas as [$etiqueta, $valor, $detalle, $icono, $ruta]): ?>
            <a class="stat" href="index.php?url=<?= Vista::e($ruta) ?>">
              <span class="stat-icon"><i class="fas <?= Vista::e($icono) ?>" aria-hidden="true"></i></span>
              <span class="stat-label"><?= Vista::e($etiqueta) ?></span>
              <strong class="stat-value"><?= Vista::e($valor) ?></strong>
              <span class="stat-meta"><?= Vista::e($detalle) ?></span>
            </a>
          <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if (isset($permisosSesion['buzon']) && $sugerencias): ?>
          <section class="panel">
            <div class="panel-heading"><h2><i class="fas fa-inbox" aria-hidden="true"></i> Sugerencias del sistema</h2><a href="index.php?url=buzon">Ver buzón</a></div>
            <div class="findings inbox-list">
              <?php foreach ($sugerencias as $sugerencia): ?>
                <article class="finding <?= Vista::e($sugerencia['prioridad']) ?>">
                  <span class="finding-icon"><i class="fas <?= Vista::e($sugerencia['icono']) ?>" aria-hidden="true"></i></span>
                  <div>
                    <div class="finding-tags">
                      <span class="badge <?= Vista::e(Hallazgo::colorDe($sugerencia['prioridad'])) ?>"><i class="fas <?= Vista::e(Hallazgo::iconoDe($sugerencia['prioridad'])) ?>" aria-hidden="true"></i><?= Vista::e(Hallazgo::etiquetaDe($sugerencia['prioridad'])) ?></span>
                      <span class="badge gris"><?= Vista::e($sugerencia['categoria']) ?></span>
                    </div>
                    <h3><?= Vista::e($sugerencia['titulo']) ?></h3>
                    <p><?= Vista::e($sugerencia['detalle']) ?></p>
                  </div>
                  <?php if ($sugerencia['ruta'] && isset($permisosSesion[$sugerencia['ruta']])): ?>
                    <a class="btn btn-secondary" href="index.php?url=<?= Vista::e($sugerencia['ruta']) ?>"><?= Vista::e($sugerencia['texto_enlace'] ?: 'Ver módulo') ?></a>
                  <?php endif; ?>
                </article>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <?php if ($verPagos || $verCitas || $verProductos): ?>
        <div class="dashboard-grid">
          <?php if ($verPagos): ?>
          <section class="panel">
            <div class="panel-heading"><h2>Ingresos de los últimos 7 días</h2><a href="index.php?url=pagos">Ver pagos</a></div>
            <?= Grafico::columnas('Ingresos por día de la última semana', $puntosSemana, 'moneda') ?>
          </section>
          <?php endif; ?>

          <?php if ($verCitas): ?>
          <section class="panel">
            <div class="panel-heading"><h2>Próximas citas</h2><a href="index.php?url=citas">Ver agenda</a></div>
            <?php if (!$proximasCitas): ?>
              <p class="empty-note">No hay citas pendientes próximamente.</p>
            <?php else: ?>
              <ul class="list">
                <?php foreach ($proximasCitas as $cita): ?>
                  <li>
                    <span class="list-date"><?= Vista::e(date('d/m', strtotime($cita['fecha_cita']))) ?><small><?= Vista::e(date('h:i a', strtotime($cita['fecha_cita']))) ?></small></span>
                    <span><strong><?= Vista::e($cita['cliente'] ?? 'Cliente') ?></strong><small>con <?= Vista::e($cita['barbero'] ?? '—') ?></small></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
          <?php endif; ?>

          <?php if ($verProductos): ?>
          <section class="panel">
            <div class="panel-heading"><h2>Stock bajo</h2><a href="index.php?url=productos">Ver productos</a></div>
            <?php if (!$stockBajo): ?>
              <p class="empty-note">Todos los productos tienen stock suficiente.</p>
            <?php else: ?>
              <ul class="list">
                <?php foreach ($stockBajo as $producto): ?>
                  <li>
                    <span><strong><?= Vista::e($producto['nombre_producto']) ?></strong></span>
                    <span class="badge <?= (int) $producto['stock'] === 0 ? 'rojo' : 'amarillo' ?>"><?= (int) $producto['stock'] === 0 ? 'Agotado' : (int) $producto['stock'] . ' en stock' ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </section>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$menu): ?>
          <p class="empty-note">Tu rol todavía no tiene módulos asignados. Pide a un administrador que configure sus permisos en Seguridad → Roles.</p>
        <?php endif; ?>

        <section aria-labelledby="accesos-titulo"<?= $menu ? '' : ' hidden' ?>>
          <h2 class="section-title" id="accesos-titulo">Módulos del sistema</h2>
          <div class="module-grid">
            <?php foreach ($menu as $grupo): ?>
              <?php foreach ($grupo['modulos'] as $ruta => $modulo): ?>
                <a class="module-card" href="index.php?url=<?= Vista::e($ruta) ?>">
                  <i class="fas <?= Vista::e($modulo['icono']) ?>" aria-hidden="true"></i>
                  <span><strong><?= Vista::e($modulo['titulo']) ?></strong><small><?= Vista::e($grupo['titulo']) ?></small></span>
                </a>
              <?php endforeach; ?>
            <?php endforeach; ?>
          </div>
        </section>
      </main>
    </div>
  </div>
  <script src="<?= Vista::asset('assets/js/panel.js') ?>"></script>
</body>
</html>
