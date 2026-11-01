/**
 * Análisis y cuenta: buzón de sugerencias del sistema, análisis de ventas, inicio,
 * modo claro/oscuro y Mi perfil.
 */
import { after, describe, test } from 'node:test';
import { config } from '../lib/config.js';
import { sesionCompartida } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

describe('Análisis y cuenta', () => {
  test('Buzón de sugerencias', async (t) => {
    const panel = await sesion.obtener();
    let id;

    // En el buzón nadie registra: el sistema genera las sugerencias al analizar.
    await t.test('analizar ahora (genera sugerencias)', async () => {
      await panel.ir('buzon');
      const boton = await panel.elemento('[data-analizar]');
      await panel.limpiarAvisos();
      await panel.clic(boton);
      await panel.resultado(null);
      await panel.esperarRecarga(boton);
      await panel.elemento('.inbox-list .finding');
    });

    await t.test('marcar como leída (modificar)', async () => {
      const boton = await panel.elemento('button[data-estado="Leída"]');
      id = await boton.getAttribute('data-id');
      await panel.limpiarAvisos();
      await panel.clic(boton);
      await panel.resultado(null);
      await panel.esperarRecarga(boton);
      await panel.elemento(`button[data-estado="Descartada"][data-id="${id}"]`);
    });

    await t.test('descartar (eliminación lógica)', async () => {
      const boton = await panel.elemento(`button[data-estado="Descartada"][data-id="${id}"]`);
      await panel.limpiarAvisos();
      await panel.clic(boton);
      await panel.resultado(null);
      await panel.esperarRecarga(boton);
      await panel.ir('buzon&grupo=Descartada');
      await panel.elemento(`button[data-estado="Reabrir"][data-id="${id}"]`);
    });

    await t.test('reabrir (restaurar)', async () => {
      const boton = await panel.elemento(`button[data-estado="Reabrir"][data-id="${id}"]`);
      await panel.limpiarAvisos();
      await panel.clic(boton);
      await panel.resultado(null);
      await panel.esperarRecarga(boton);
      await panel.ir('buzon');
      await panel.elemento(`button[data-estado="Descartada"][data-id="${id}"]`);
    });
  });

  test('Análisis de ventas', async (t) => {
    const panel = await sesion.obtener();
    await t.test('muestra indicadores y gráficos', async () => {
      await panel.ir('analisis');
      await panel.esperarTexto('Análisis de ventas');
      await panel.elemento('.stats .stat');
      await panel.elemento('.chart');
    });

    await t.test('cambia el período a 90 días', async () => {
      await panel.clic(await panel.elemento('a.tab[href*="dias=90"]'));
      await panel.driver.wait(async () => (await panel.driver.getCurrentUrl()).includes('dias=90'), config.espera);
      await panel.elemento('a.tab.active[href*="dias=90"]');
    });
  });

  test('Inicio y modo claro/oscuro', async (t) => {
    const panel = await sesion.obtener();
    await t.test('el inicio saluda al usuario', async () => {
      await panel.ir('dashboard');
      await panel.esperarTexto(`Hola, ${config.usuario}`);
    });

    await t.test('el botón cambia el tema y lo recuerda', async () => {
      const tema = () => panel.driver.executeScript('return document.documentElement.dataset.tema;');
      const inicial = await tema();
      await panel.clic(await panel.elemento('[data-tema-toggle]'));
      const cambiado = await tema();
      if (cambiado === inicial) throw new Error('El tema no cambió al pulsar el botón');
      await panel.ir('dashboard');
      if ((await tema()) !== cambiado) throw new Error('El tema elegido no se recordó al recargar');
      await panel.clic(await panel.elemento('[data-tema-toggle]')); // se deja como estaba
    });
  });

  test('Mi perfil', async (t) => {
    const panel = await sesion.obtener();
    await t.test('muestra los datos de la cuenta', async () => {
      await panel.ir('perfil');
      await panel.esperarTexto(`@${config.usuario}`);
    });
  });
});
