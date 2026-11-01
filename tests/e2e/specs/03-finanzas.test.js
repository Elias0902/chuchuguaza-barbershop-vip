/**
 * Caja y finanzas: cierres de caja, gastos, retiros y nómina.
 */
import { after, describe, test } from 'node:test';
import { By } from 'selenium-webdriver';
import { sesionCompartida, comoTabla, probarCrud, unico } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

/** Solo puede haber una caja abierta: si hay una, se cierra con «Cerrar caja» (monto propuesto). */
async function cerrarCajaAbierta(p) {
  for (const fila of await p.elementos('[data-cuerpo] tr')) {
    const botones = await fila.findElements(By.css('button[title="Cerrar caja"]'));
    if (botones.length) {
      await p.clic(botones[0]);
      await p.guardar();
      await p.esperarTabla();
      return true;
    }
  }
  return false;
}

describe('Caja y finanzas', () => {
  test('Cierres de caja', async (t) => {
    const panel = await sesion.obtener();
    await probarCrud(t, panel, {
      ruta: 'cierres',
      antesDeRegistrar: async (p) => {
        await cerrarCajaAbierta(p);
        return { monto_inicial: 15 };
      },
      // La caja abierta va primero en la tabla: se busca por su número.
      identificar: (p) => p.textoPrimeraCelda(),
      modificar: { monto_inicial: 25 },
      despuesDeModificar: '25,00',
    });

    await t.test('dejar una caja abierta para el día', async () => {
      await panel.irAModulo('cierres');
      await panel.abrirNuevo();
      await panel.llenar({ monto_inicial: 20 });
      await panel.guardar();
      await panel.fila(['Abierta', '20,00']);
    });
  });

  test('Gastos generales', async (t) => {
    const panel = await sesion.obtener();
    const descripcion = `Gasto Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'gastos',
      nuevo: { descripcion, monto: 1.5 },
      buscar: descripcion,
      modificar: { monto: 2 },
      despuesDeModificar: '2,00',
    });
  });

  test('Retiros de dinero', async (t) => {
    const panel = await sesion.obtener();
    const motivo = `Retiro Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'retiros',
      nuevo: { cedula_barbero: { primera: true }, detalles: [{ id_metodo: { primera: true }, monto: 2, motivo }] },
      buscar: motivo,
      modificar: { detalles: [{ monto: 3 }] },
      despuesDeModificar: '3,00',
    });
  });

  test('Nómina', async (t) => {
    const panel = await sesion.obtener();
    // Un período de 2024 al azar para no cruzarse con otra nómina del mismo barbero.
    const mes = 1 + Math.floor(Math.random() * 12);
    const dia = 1 + Math.floor(Math.random() * 18);
    const inicio = `2024-${String(mes).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
    const finDe = (dias) => {
      const d = new Date(`${inicio}T12:00:00`);
      d.setDate(d.getDate() + dias);
      return d.toISOString().slice(0, 10);
    };
    await probarCrud(t, panel, {
      ruta: 'nomina',
      nuevo: { cedula_barbero: { indice: Math.floor(Math.random() * 4) }, fecha_inicio: inicio, fecha_cierre: finDe(6), estado_pago: 'Pendiente' },
      buscar: comoTabla(inicio),
      modificar: { fecha_cierre: finDe(7) },
      despuesDeModificar: comoTabla(finDe(7)),
    });
  });
});
