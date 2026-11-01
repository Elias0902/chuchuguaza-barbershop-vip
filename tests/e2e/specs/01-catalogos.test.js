/**
 * Catálogos: registrar, modificar y eliminar (eliminación lógica) en cada módulo.
 */
import { after, describe, test } from 'node:test';
import { sesionCompartida, fecha, letras, probarCrud, unico } from '../lib/panel.js';

// El navegador se abre solo si alguna prueba de este archivo se ejecuta.
const sesion = sesionCompartida();
after(() => sesion.cerrar());

describe('Catálogos', () => {
  test('Clientes', async (t) => {
    const panel = await sesion.obtener();
    const apellido = 'Prueba' + letras(5).toLowerCase();
    await probarCrud(t, panel, {
      ruta: 'clientes',
      nuevo: { nombre: 'Selenium', apellido, telefono: '0414-1234567', email: `selenium.${apellido.toLowerCase()}@ejemplo.com` },
      buscar: apellido,
      modificar: { telefono: '0424-7654321' },
      despuesDeModificar: '0424-7654321',
    });
  });

  test('Barberos', async (t) => {
    const panel = await sesion.obtener();
    const cedula = 'V-' + String(Math.floor(10000000 + Math.random() * 89999999));
    await probarCrud(t, panel, {
      ruta: 'barberos',
      nuevo: {
        cedula, telefono: '0412-5550000', nombre: 'Selenium', apellido: 'Prueba' + letras(4).toLowerCase(),
        porcentaje_comision: 40, sueldo_base: 10, es_principal: false,
      },
      buscar: cedula,
      modificar: { porcentaje_comision: 45 },
      despuesDeModificar: '45,00%',
    });
  });

  test('Servicios', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Servicio Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'servicios',
      nuevo: { nombre_servicio: nombre, precio: 5, duracion_minutos: 30 },
      buscar: nombre,
      modificar: { precio: 6.5 },
      despuesDeModificar: '6,50',
    });
  });

  test('Categorías', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Categoría Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'categorias',
      nuevo: { nombre_categoria: nombre },
      buscar: nombre,
      modificar: { nombre_categoria: `${nombre} editada` },
      nuevaBusqueda: `${nombre} editada`,
    });
  });

  test('Productos y bebidas', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Producto Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'productos',
      nuevo: { nombre_producto: nombre, id_categoria: { primera: true }, precio: 2, stock: 10 },
      buscar: nombre,
      modificar: { stock: 12 },
      despuesDeModificar: '12',
    });
  });

  test('Promociones', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Promo Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'promociones',
      nuevo: { nombre_promocion: nombre, descuento_porcentaje: 10, fecha_inicio: fecha(0), fecha_cierre: fecha(30) },
      buscar: nombre,
      modificar: { descuento_porcentaje: 12.5 },
      despuesDeModificar: '12,50%',
    });
  });

  test('Métodos de pago', async (t) => {
    const panel = await sesion.obtener();
    const nombre = `Método Selenium ${unico()}`;
    await probarCrud(t, panel, {
      ruta: 'metodos_pago',
      nuevo: { nombre_metodo: nombre },
      buscar: nombre,
      modificar: { nombre_metodo: `${nombre} editado` },
      nuevaBusqueda: `${nombre} editado`,
    });
  });
});
