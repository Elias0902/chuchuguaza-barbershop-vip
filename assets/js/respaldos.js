/**
 * Respaldos: «Respaldar ahora» y restaurar uno (con confirmación).
 */
(() => {
  'use strict';

  const { api, aviso, confirmar } = window.Panel;

  const recargar = () => setTimeout(() => window.location.reload(), 900);

  document.querySelector('[data-generar]')?.addEventListener('click', async (evento) => {
    const boton = evento.currentTarget;
    boton.disabled = true;
    try {
      aviso((await api('index.php?url=respaldos&action=generar', { metodo: 'POST' })).mensaje);
      recargar();
    } catch (error) {
      aviso(error.message, 'error');
      boton.disabled = false;
    }
  });

  document.querySelectorAll('[data-restaurar]').forEach((boton) => boton.addEventListener('click', async () => {
    const ok = await confirmar({
      titulo: 'Restaurar respaldo',
      mensaje: `¿Restaurar la base ${boton.dataset.descripcion}? Todo lo registrado después de esa fecha en esa base se perderá. Antes se guardará un respaldo del estado actual.`,
      textoConfirmar: 'Restaurar',
      peligro: true,
    });
    if (!ok) return;
    boton.disabled = true;
    try {
      aviso((await api('index.php?url=respaldos&action=restaurar', { metodo: 'POST', datos: { id: Number(boton.dataset.restaurar) } })).mensaje);
      recargar();
    } catch (error) {
      aviso(error.message, 'error');
      boton.disabled = false;
    }
  }));
})();
