/**
 * Academia: cursos e inscripciones.
 */
import { after, describe, test } from 'node:test';
import { sesionCompartida, probarCrud, unico } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

describe('Academia', () => {
  test('Cursos', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Curso Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'cursos',
      nuevo: { nombre_curso: nombre, costo: 30, dias_imparte: 'Sábados', horario: '9:00 am a 11:00 am', descripcion: 'Curso creado por la prueba automática.' },
      buscar: nombre,
      modificar: { costo: 35 },
      despuesDeModificar: '35,00',
    });
  });

  test('Inscripciones', async (t) => {
    const panel = await sesion.obtener();
    let elegidos;
    await probarCrud(t, panel, {
      ruta: 'inscripciones',
      // Un cliente no puede inscribirse dos veces al mismo curso: se elige una pareja libre.
      antesDeRegistrar: async (p) => {
        elegidos = await p.enPagina(async () => {
          const config = JSON.parse(document.getElementById('modulo-config').textContent);
          const respuesta = await fetch(`${config.url}&action=consultar`, { headers: { Accept: 'application/json' } });
          const activas = new Set((await respuesta.json()).datos
            .filter((i) => Number(i.status) === 1)
            .map((i) => `${i.id_curso}-${i.id_cliente}`));
          for (const curso of config.opciones.id_curso.filter((o) => o.activo)) {
            for (const cliente of config.opciones.id_cliente.filter((o) => o.activo)) {
              if (!activas.has(`${curso.valor}-${cliente.valor}`)) {
                return { curso: curso.valor, cliente: cliente.valor, nombreCurso: curso.texto.split(' · ')[0], alumno: cliente.texto.split(' · ')[0] };
              }
            }
          }
          return { error: 'No hay ninguna pareja curso-cliente libre' };
        });
        if (elegidos.error) throw new Error(elegidos.error);
        return { id_curso: elegidos.curso, id_cliente: elegidos.cliente, monto_pagado: 10 };
      },
      identificar: () => [elegidos.alumno, elegidos.nombreCurso],
      modificar: { monto_pagado: 15 },
      despuesDeModificar: '15,00',
    });
  });
});
