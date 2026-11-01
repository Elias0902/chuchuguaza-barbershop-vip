/**
 * Atención: citas, tickets de atención y pagos.
 */
import { after, describe, test } from 'node:test';
import { sesionCompartida, fechaHora, probarCrud, unico } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

describe('Atención', () => {
  test('Citas', async (t) => {
    const panel = await sesion.obtener();
    const observacion = `Cita Selenium ${unico()}`;
    // Fecha lejana y al azar para no chocar con otras citas del mismo barbero.
    const dias = 30 + Math.floor(Math.random() * 300);
    const hora = 9 + Math.floor(Math.random() * 10);
    await probarCrud(t, panel, {
      ruta: 'citas',
      nuevo: {
        id_cliente: { primera: true }, cedula_barbero: { indice: Math.floor(Math.random() * 4) },
        fecha_cita: fechaHora(dias, hora), estado_cita: 'Pendiente', observacion,
      },
      buscar: observacion,
      modificar: { observacion: `${observacion} editada` },
      nuevaBusqueda: `${observacion} editada`,
    });
  });

  test('Tickets de atención', async (t) => {
    const panel = await sesion.obtener();
    await probarCrud(t, panel, {
      ruta: 'tickets',
      nuevo: {
        id_cliente: { primera: true }, cedula_barbero: { primera: true },
        // El precio se llena solo al elegir el servicio.
        detalles: [{ item: { prefijo: 's:' }, cantidad: 1 }],
      },
      // El ticket nuevo es el más reciente: queda en la primera fila. Se busca por su número.
      identificar: (p) => p.textoPrimeraCelda(),
      modificar: { detalles: [{ cantidad: 2 }] },
      despuesDeModificar: '2 × ',
    });
  });

  test('Pagos', async (t) => {
    const panel = await sesion.obtener();
    const referencia = `REF${Date.now().toString().slice(-7)}`;
    await probarCrud(t, panel, {
      ruta: 'pagos',
      // Solo aparecen tickets con saldo; el monto se llena solo con el saldo pendiente.
      nuevo: { id_ticket: { primera: true }, id_metodo: { primera: true }, referencia },
      buscar: referencia,
      modificar: { referencia: `${referencia}9` },
      nuevaBusqueda: `${referencia}9`,
    });
  });
});
