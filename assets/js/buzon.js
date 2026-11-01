/**
 * Buzón de sugerencias: «Analizar ahora» y cambio de estado de cada sugerencia.
 */
(() => {
  'use strict';

  const { api, aviso } = window.Panel;

  const recargar = () => setTimeout(() => window.location.reload(), 700);

  document.querySelector('[data-analizar]')?.addEventListener('click', async (evento) => {
    const boton = evento.currentTarget;
    boton.disabled = true;
    try {
      const respuesta = await api('index.php?url=buzon&action=analizar', { metodo: 'POST' });
      aviso(respuesta.mensaje);
      recargar();
    } catch (error) {
      aviso(error.message, 'error');
      boton.disabled = false;
    }
  });

  document.querySelectorAll('[data-estado]').forEach((boton) => boton.addEventListener('click', async () => {
    boton.disabled = true;
    try {
      const respuesta = await api('index.php?url=buzon&action=estado', {
        metodo: 'POST',
        datos: { id: Number(boton.dataset.id), estado: boton.dataset.estado },
      });
      aviso(respuesta.mensaje);
      recargar();
    } catch (error) {
      aviso(error.message, 'error');
      boton.disabled = false;
    }
  }));
})();
