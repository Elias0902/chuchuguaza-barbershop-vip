/**
 * Configuración y ayuda (sidebar de abajo, manual, centro de ayuda, respaldos)
 * y la vista del rol Barbero (solo sus tickets y citas).
 */
import assert from 'node:assert/strict';
import { after, describe, test } from 'node:test';
import { By, until } from 'selenium-webdriver';
import { Panel, sesionCompartida } from '../lib/panel.js';
import { config } from '../lib/config.js';

const sesion = sesionCompartida();
after(() => sesion.cerrar());

const textos = async (driver, css) => Promise.all((await driver.findElements(By.css(css))).map((e) => e.getText()));

describe('Configuración y ayuda', () => {
  test('el sidebar tiene Configuración y Ayuda abajo, aparte', async () => {
    const panel = await sesion.obtener();
    await panel.ir('dashboard');
    const abajo = await textos(panel.driver, '.sidebar-bottom .sidebar-link');
    for (const enlace of ['Usuarios', 'Roles', 'Bitácora', 'Respaldos', 'Manual de usuario', 'Centro de ayuda']) {
      assert.ok(abajo.some((t) => t.includes(enlace)), `Falta «${enlace}» abajo en el sidebar`);
    }
    const arriba = await textos(panel.driver, '.sidebar-nav:not(.sidebar-bottom) .sidebar-link');
    assert.ok(!arriba.some((t) => t.includes('Usuarios')), 'Usuarios no debe estar entre los módulos del negocio');
  });

  test('el manual muestra un capítulo por módulo con pantallazos', async () => {
    const panel = await sesion.obtener();
    await panel.ir('manual');
    await panel.esperar(By.css('#manual-pagos'));
    assert.ok((await panel.driver.findElements(By.css('.doc-chapter'))).length > 15);
    assert.ok((await panel.driver.findElements(By.css('#manual-pagos .help-shot img'))).length >= 1, 'Pagos debe tener pantallazos');
  });

  test('el centro de ayuda filtra con el buscador', async () => {
    const panel = await sesion.obtener();
    await panel.ir('ayuda');
    const buscador = await panel.esperar(By.css('[data-buscar-ayuda]'));
    await panel.escribir(buscador, 'respaldo');
    const visibles = await panel.driver.findElements(By.css('[data-pregunta]:not([hidden])'));
    assert.ok(visibles.length >= 1 && visibles.length < 6, `Se esperaban pocas preguntas sobre respaldos y hay ${visibles.length}`);
    await panel.escribir(buscador, 'zzzz');
    assert.equal(await panel.driver.executeScript('return document.querySelector("[data-sin-resultados]").hidden'), false, 'Debe avisar que no hay resultados');
  });

  test('respaldar ahora agrega los respaldos de las dos bases', async () => {
    const panel = await sesion.obtener();
    await panel.ir('respaldos');
    const antes = (await panel.driver.findElements(By.css('main tbody tr'))).length;
    await panel.clic(await panel.esperar(By.css('[data-generar]')));
    await panel.driver.wait(async () => (await panel.driver.findElements(By.css('main tbody tr td.num'))).length > 0
      && (await panel.driver.findElements(By.css('main tbody tr'))).length >= antes + 2, config.espera, 'No aparecieron los respaldos nuevos');
    const primera = await (await panel.driver.findElement(By.css('main tbody tr'))).getText();
    assert.match(primera, /Manual/);
  });
});

describe('Rol Barbero', { skip: !config.barbero.usuario && 'falta TEST_BARBERO_USUARIO en tests/e2e/.env' }, () => {
  let panel;
  after(async () => panel?.cerrar());

  test('ve solo sus módulos y su resumen de cortes', async () => {
    panel = await Panel.abrir(config.barbero.usuario, config.barbero.clave);
    await panel.esperar(By.xpath("//*[contains(@class,'stat-label') and contains(., 'Mis cortes de hoy')]"));
    const menu = await textos(panel.driver, '.sidebar-nav:not(.sidebar-bottom) .sidebar-link');
    for (const prohibido of ['Pagos', 'Cierres de caja', 'Nómina', 'Análisis de ventas']) {
      assert.ok(!menu.some((t) => t.includes(prohibido)), `El barbero no debe ver «${prohibido}»`);
    }
    assert.ok(menu.some((t) => t.includes('Tickets')), 'El barbero debe ver Tickets');
  });

  test('no puede abrir módulos de finanzas', async () => {
    await panel.ir('pagos');
    await panel.driver.wait(until.elementLocated(By.css('body')));
    assert.ok(!(await panel.driver.findElements(By.css('[data-cuerpo]'))).length, 'Pagos no debe cargar para el barbero');
  });

  test('solo ve sus tickets y su nombre viene puesto al registrar', async () => {
    await panel.irAModulo('tickets');
    const barberos = await panel.enPagina(async () => {
      const config = JSON.parse(document.getElementById('modulo-config').textContent);
      const datos = (await (await fetch(`${config.url}&action=consultar`, { headers: { Accept: 'application/json' } })).json()).datos;
      return [...new Set(datos.map((t) => t.cedula_barbero))];
    });
    assert.ok(barberos.every((c) => c === config.barbero.cedula), `Ve tickets de otros barberos: ${barberos.join(', ')}`);
    await panel.abrirNuevo();
    const select = await panel.esperar(By.css('.modal form [name="cedula_barbero"]'));
    assert.equal(await select.getAttribute('value'), config.barbero.cedula);
  });
});
