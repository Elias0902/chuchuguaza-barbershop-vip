/**
 * Página pública y seguridad: políticas de servicio, usuarios, roles y bitácora.
 */
import { after, describe, test } from 'node:test';
import { sesionCompartida, probarCrud, unico } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

const codigo = unico();

describe('Página pública y seguridad', () => {
  test('Políticas de servicio', async (t) => {
    const panel = await sesion.obtener();
    const titulo = `Política Selenium ${codigo}`;
    await probarCrud(t, panel, {
      ruta: 'politicas',
      nuevo: { titulo, categoria: 'General', orden: 99, contenido: 'Texto creado por la prueba automática.' },
      buscar: titulo,
      modificar: { contenido: 'Texto editado por la prueba automática.' },
      despuesDeModificar: 'editado',
    });
  });

  test('Usuarios', async (t) => {
    const panel = await sesion.obtener();
    const usuario = `selenium${codigo}`;
    await probarCrud(t, panel, {
      ruta: 'usuarios',
      nuevo: { nombre_usuario: usuario, email_usuario: `${usuario}@ejemplo.com`, id_rol_usuario: { primera: true }, password_usuario: 'Prueba1234' },
      buscar: usuario,
      modificar: { email_usuario: `editado.${usuario}@ejemplo.com` },
      despuesDeModificar: `editado.${usuario}@ejemplo.com`,
    });
  });

  test('Roles', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Rol Selenium ${codigo}`;
    await probarCrud(t, panel, {
      ruta: 'roles',
      nuevo: { nombre_rol: nombre, descripcion: 'Rol creado por la prueba automática.' },
      buscar: nombre,
      modificar: { descripcion: 'Rol editado por la prueba automática.' },
      despuesDeModificar: 'editado',
    });
  });

  test('Bitácora (solo consulta)', async (t) => {
    const panel = await sesion.obtener();
    await t.test('registra lo que hicieron las pruebas anteriores', async () => {
      await panel.irAModulo('bitacora');
      await panel.buscar(`Rol Selenium ${codigo}`);
      for (const accion of ['Registrar', 'Modificar', 'Eliminar']) {
        await panel.fila([`Rol Selenium ${codigo}`, accion]);
      }
    });
  });
});
