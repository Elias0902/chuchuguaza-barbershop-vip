/**
 * Page Object del panel: todo lo que las pruebas hacen en el navegador pasa por aquí
 * (iniciar sesión, abrir un módulo, llenar el formulario, guardar, buscar, modificar, eliminar).
 * Las pruebas de cada módulo solo describen los datos; la mecánica es la misma para todos.
 */
import { Builder, By, until } from 'selenium-webdriver';
import { Select } from 'selenium-webdriver/lib/select.js';
import chrome from 'selenium-webdriver/chrome.js';
import edge from 'selenium-webdriver/edge.js';
import { mkdirSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import { config } from './config.js';

// ---------------------------------------------------------------- datos únicos y fechas

/** Letras al azar (para campos que solo aceptan letras, como nombre y apellido). */
export function letras(cantidad = 6) {
  const abecedario = 'abcdefghijklmnopqrstuvwxyz';
  let texto = '';
  for (let i = 0; i < cantidad; i++) texto += abecedario[Math.floor(Math.random() * abecedario.length)];
  return texto.charAt(0).toUpperCase() + texto.slice(1);
}

/** Código corto y único por ejecución, para encontrar el registro creado. */
export function unico() {
  return Date.now().toString(36).slice(-5) + Math.random().toString(36).slice(2, 4);
}

const dos = (n) => String(n).padStart(2, '0');
const isoFecha = (d) => `${d.getFullYear()}-${dos(d.getMonth() + 1)}-${dos(d.getDate())}`;

/** Fecha AAAA-MM-DD desplazada $dias desde hoy. */
export function fecha(dias = 0) {
  const d = new Date();
  d.setDate(d.getDate() + dias);
  return isoFecha(d);
}

/** Fecha y hora para un input datetime-local. */
export function fechaHora(dias, hora, minutos = 0) {
  return `${fecha(dias)}T${dos(hora)}:${dos(minutos)}`;
}

/** Fecha AAAA-MM-DD → dd/mm/aaaa (como la muestra la tabla). */
export function comoTabla(iso) {
  const [a, m, d] = iso.split('-');
  return `${d}/${m}/${a}`;
}

// ---------------------------------------------------------------- Panel

export class Panel {
  constructor(driver) {
    this.driver = driver;
  }

  /** Abre el navegador e inicia sesión (por defecto, con el usuario de pruebas de .env). */
  static async abrir(usuario = config.usuario, clave = config.clave) {
    const builder = new Builder();
    const argumentos = ['--window-size=1400,950', '--lang=es'];
    if (config.oculto) argumentos.push('--headless=new');

    if (config.navegador === 'edge') {
      builder.forBrowser('MicrosoftEdge').setEdgeOptions(new edge.Options().addArguments(...argumentos));
    } else {
      builder.forBrowser('chrome').setChromeOptions(new chrome.Options().addArguments(...argumentos));
    }

    const panel = new Panel(await builder.build());
    await panel.iniciarSesion(usuario, clave);
    return panel;
  }

  async cerrar() {
    await this.driver.quit();
  }

  url(ruta) {
    return `${config.urlBase}/index.php?url=${ruta}`;
  }

  async pausa() {
    if (config.pausa > 0) await this.driver.sleep(config.pausa);
  }

  async iniciarSesion(usuario = config.usuario, clave = config.clave) {
    await this.driver.get(`${config.urlBase}/index.php?url=autenticator&action=acceso_interno`);
    await this.escribir(await this.esperar(By.name('username')), usuario);
    await this.escribir(await this.esperar(By.name('password')), clave);
    await this.clic(await this.driver.findElement(By.css('form button[type="submit"]')));
    await this.driver.wait(until.urlContains('url=dashboard'), config.espera,
      'No se pudo iniciar sesión: revisa TEST_USUARIO y TEST_CLAVE en tests/e2e/.env');
  }

  // ------------------------------------------------------------ utilidades del navegador

  async esperar(localizador, contexto = this.driver) {
    const elemento = await this.driver.wait(async () => {
      const encontrados = await contexto.findElements(localizador);
      for (const el of encontrados) {
        try {
          if (await el.isDisplayed()) return el;
        } catch {
          // El elemento se redibujó mientras se revisaba: se vuelve a intentar.
        }
      }
      return false;
    }, config.espera, `No apareció en pantalla: ${localizador}`);
    return elemento;
  }

  /** Clic con desplazamiento al centro (evita que la barra superior fija tape el botón). */
  async clic(elemento) {
    await this.driver.executeScript('arguments[0].scrollIntoView({ block: "center" });', elemento);
    await elemento.click();
    await this.pausa();
  }

  async escribir(elemento, texto) {
    await elemento.clear();
    await elemento.sendKeys(String(texto));
  }

  async limpiarAvisos() {
    await this.driver.executeScript('document.querySelectorAll(".toasts .toast").forEach((t) => t.remove());');
  }

  async captura(nombre) {
    mkdirSync(config.carpetaCapturas, { recursive: true });
    const archivo = join(config.carpetaCapturas, `${nombre.replace(/[^a-z0-9-]+/gi, '_')}.png`);
    writeFileSync(archivo, await this.driver.takeScreenshot(), 'base64');
    return archivo;
  }

  /** Ejecuta código en la página (por ejemplo, consultar datos por AJAX para preparar una prueba). */
  async enPagina(funcion, ...argumentos) {
    return this.driver.executeAsyncScript(
      `const listo = arguments[arguments.length - 1];
       Promise.resolve((${funcion})(...Array.from(arguments).slice(0, -1))).then(listo, (e) => listo({ error: String(e) }));`,
      ...argumentos
    );
  }

  // ------------------------------------------------------------ páginas propias (inicio, buzón, análisis)

  async ir(ruta) {
    await this.driver.get(this.url(ruta));
  }

  async elemento(css, contexto = this.driver) {
    return this.esperar(By.css(css), contexto);
  }

  async elementos(css, contexto = this.driver) {
    return contexto.findElements(By.css(css));
  }

  /** Espera a que la página se recargue (el elemento indicado deja de existir). */
  async esperarRecarga(elementoAnterior) {
    await this.driver.wait(until.stalenessOf(elementoAnterior), config.espera, 'La página no se recargó');
  }

  /** Espera a que el texto aparezca en la página. */
  async esperarTexto(texto, css = 'main') {
    await this.driver.wait(async () => {
      try {
        return (await this.driver.findElement(By.css(css)).getText()).includes(texto);
      } catch {
        return false;
      }
    }, config.espera, `No apareció el texto «${texto}»`);
  }

  // ------------------------------------------------------------ módulos (moduloView)

  /** Abre un módulo y espera a que la tabla termine de cargar por AJAX. */
  async irAModulo(ruta) {
    await this.driver.get(this.url(ruta));
    await this.esperarTabla();
  }

  async esperarTabla() {
    await this.driver.wait(async () => {
      try {
        const filas = await this.driver.findElements(By.css('[data-cuerpo] tr'));
        return filas.length > 0 && !(await filas[0].getText()).includes('Cargando');
      } catch {
        return false;
      }
    }, config.espera, 'La tabla del módulo no terminó de cargar');
  }

  async abrirNuevo() {
    await this.clic(await this.esperar(By.css('[data-nuevo]')));
    return this.esperarModal();
  }

  async esperarModal() {
    return this.esperar(By.css('.modal-backdrop .modal'));
  }

  /** Completa el formulario del modal abierto: { campo: valor } (detalles: [{ columna: valor }]). */
  async llenar(datos) {
    const modal = await this.esperarModal();
    for (const [campo, valor] of Object.entries(datos)) {
      if (campo === 'detalles') {
        await this.llenarDetalles(modal, valor);
        continue;
      }
      // Los controles compuestos (días de clase) guardan su valor en un input oculto.
      const oculto = await modal.findElements(By.css(`form input[type="hidden"][name="${campo}"]`));
      await this.ponerValor(oculto[0] ?? await this.esperar(By.css(`form [name="${campo}"]`), modal), valor);
    }
  }

  async llenarDetalles(modal, filas) {
    for (let i = 0; i < filas.length; i++) {
      let lineas = await modal.findElements(By.css('.detail-table tbody tr'));
      if (lineas.length <= i) {
        await this.clic(await modal.findElement(By.xpath(".//button[contains(., 'Agregar línea')]")));
        lineas = await modal.findElements(By.css('.detail-table tbody tr'));
      }
      for (const [columna, valor] of Object.entries(filas[i])) {
        await this.ponerValor(await lineas[i].findElement(By.css(`[name="${columna}"]`)), valor);
      }
    }
  }

  async ponerValor(elemento, valor) {
    const etiqueta = await elemento.getTagName();
    const tipo = (await elemento.getAttribute('type')) || '';
    if (etiqueta === 'select') return this.elegir(elemento, valor);
    if (tipo === 'hidden') {
      // Días de clase: valor 'Lunes, Miércoles' → marca esas casillas del selector de días.
      const dias = String(valor).split(',').map((d) => d.trim());
      for (const casilla of await elemento.findElements(By.xpath('./ancestor::div[contains(@class,"day-picker")]//input[@type="checkbox"]'))) {
        if ((await casilla.isSelected()) !== dias.includes(await casilla.getAttribute('value'))) {
          await this.clic(await casilla.findElement(By.xpath('./parent::label')));
        }
      }
      return;
    }
    const grupoCedula = await elemento.findElements(By.xpath('./preceding-sibling::select[contains(@class,"cedula-prefijo")]'));
    if (grupoCedula.length) {
      // Cédula: 'V-12345678' → nacionalidad en el select y los dígitos en el campo.
      const [, letra, numero] = String(valor).match(/^([VE])-?(\d+)$/i);
      await this.elegir(grupoCedula[0], letra.toUpperCase());
      return this.escribir(elemento, numero);
    }
    if (tipo === 'checkbox') {
      if ((await elemento.isSelected()) !== Boolean(valor)) await this.clic(elemento);
      return;
    }
    if (['date', 'datetime-local', 'number'].includes(tipo)) {
      // Estos inputs dependen del idioma del navegador al escribir: se asigna el valor y se avisa el cambio.
      await this.driver.executeScript(
        `arguments[0].value = arguments[1];
         arguments[0].dispatchEvent(new Event('input', { bubbles: true }));
         arguments[0].dispatchEvent(new Event('change', { bubbles: true }));`,
        elemento, String(valor)
      );
      return;
    }
    await this.escribir(elemento, valor);
  }

  /**
   * Elige una opción de un select. $valor puede ser el value o parte del texto,
   * o un objeto: { primera: true }, { indice: n }, { prefijo: 's:' } o { texto: '...' }.
   */
  async elegir(elemento, valor) {
    const opciones = [];
    for (const opcion of await elemento.findElements(By.css('option'))) {
      const v = await opcion.getAttribute('value');
      if (v !== '') opciones.push({ valor: v, texto: await opcion.getText() });
    }

    let elegida;
    if (valor !== null && typeof valor === 'object') {
      if (valor.prefijo) elegida = opciones.find((o) => o.valor.startsWith(valor.prefijo));
      else if (valor.texto) elegida = opciones.find((o) => o.texto.includes(valor.texto));
      else if (valor.indice !== undefined) elegida = opciones[valor.indice % Math.max(opciones.length, 1)];
      else elegida = opciones[0];
    } else {
      elegida = opciones.find((o) => o.valor === String(valor)) || opciones.find((o) => o.texto.includes(String(valor)));
    }
    if (!elegida) throw new Error(`No hay una opción que coincida con ${JSON.stringify(valor)}`);

    await new Select(elemento).selectByValue(elegida.valor);
    return elegida;
  }

  /** Pulsa el botón principal del modal (Registrar / Guardar cambios) y espera la respuesta. */
  async guardar() {
    await this.limpiarAvisos();
    const modal = await this.esperarModal();
    await this.clic(await modal.findElement(By.css('.modal-footer button[type="submit"]')));
    return this.resultado(modal);
  }

  /** Pulsa el último botón del modal de confirmación (Eliminar, Restaurar, Cerrar caja...). */
  async confirmar() {
    await this.limpiarAvisos();
    const modal = await this.esperarModal();
    const botones = await modal.findElements(By.css('.modal-footer button'));
    await this.clic(botones[botones.length - 1]);
    return this.resultado(null);
  }

  /** Espera el aviso del sistema; si es un error (aviso o alerta del formulario) lanza una excepción. */
  async resultado(modal) {
    let respuesta = null;
    await this.driver.wait(async () => {
      const avisos = await this.driver.findElements(By.css('.toasts .toast'));
      if (avisos.length) {
        const ultimo = avisos[avisos.length - 1];
        respuesta = { ok: !(await ultimo.getAttribute('class')).includes('error'), texto: await ultimo.getText() };
        return true;
      }
      if (modal) {
        try {
          const alerta = await modal.findElement(By.css('.form-alert'));
          if (await alerta.isDisplayed()) {
            respuesta = { ok: false, texto: await alerta.getText() };
            return true;
          }
        } catch {
          // El modal ya se cerró: se sigue esperando el aviso.
        }
      }
      return false;
    }, config.espera, 'El sistema no respondió (no apareció ningún aviso)');

    if (!respuesta.ok) throw new Error(`El sistema respondió con un error: ${respuesta.texto}`);
    return respuesta.texto;
  }

  async buscar(texto) {
    await this.escribir(await this.esperar(By.css('[data-buscar]')), texto);
    await this.driver.sleep(350); // la tabla filtra 150 ms después de escribir
  }

  /** Fila de la tabla que contiene todos los textos indicados. */
  async fila(textos) {
    const buscados = [].concat(textos);
    await this.esperarTabla();
    return this.driver.wait(async () => {
      try {
        for (const fila of await this.driver.findElements(By.css('[data-cuerpo] tr'))) {
          const texto = await fila.getText();
          if (buscados.every((b) => texto.includes(b))) return fila;
        }
      } catch {
        // La tabla se redibujó: se vuelve a buscar.
      }
      return false;
    }, config.espera, `No apareció en la tabla una fila con «${buscados.join(' + ')}»`);
  }

  /** Espera a que ninguna fila contenga todos los textos indicados. */
  async sinFila(textos) {
    const buscados = [].concat(textos);
    await this.driver.wait(async () => {
      try {
        for (const fila of await this.driver.findElements(By.css('[data-cuerpo] tr'))) {
          const texto = await fila.getText();
          if (buscados.every((b) => texto.includes(b))) return false;
        }
        return true;
      } catch {
        return false;
      }
    }, config.espera, `La fila «${buscados.join(' + ')}» sigue en la tabla`);
  }

  async textoPrimeraCelda() {
    await this.esperarTabla();
    return (await this.driver.findElement(By.css('[data-cuerpo] tr td'))).getText();
  }

  async accionEnFila(textos, titulo) {
    const fila = await this.fila(textos);
    await this.clic(await fila.findElement(By.css(`button[title="${titulo}"]`)));
  }

  async filtro(nombre) {
    await this.clic(await this.esperar(By.css(`[data-filtro="${nombre}"]`)));
    await this.esperarTabla();
  }
}

/**
 * Sesión de navegador por archivo de pruebas que se abre recién cuando una prueba la pide.
 * Así, al correr un solo módulo, los demás archivos no abren el navegador.
 */
export function sesionCompartida() {
  let abriendo = null;
  return {
    obtener: () => (abriendo ??= Panel.abrir()),
    cerrar: async () => {
      if (abriendo) await (await abriendo).cerrar();
    },
  };
}

// ---------------------------------------------------------------- flujo CRUD

/**
 * Prueba registrar, modificar y eliminar (eliminación lógica) en un módulo.
 * Cada paso es una subprueba; si uno falla, los siguientes se omiten y queda una captura.
 *
 * opciones = {
 *   ruta, nuevo: {campo: valor}, buscar: 'texto único',
 *   modificar: {campo: valor}, despuesDeModificar: 'texto que debe verse',
 *   antesDeRegistrar(panel)  opcional, prepara datos (por ejemplo, elegir opciones libres)
 *   identificar(panel)       opcional, devuelve el texto de búsqueda después de registrar
 * }
 */
export async function probarCrud(t, panel, opciones) {
  const estado = { fallo: false, buscar: opciones.buscar };

  const paso = async (nombre, accion) => {
    await t.test(nombre, async (sub) => {
      if (estado.fallo) {
        sub.skip('se omitió porque falló un paso anterior');
        return;
      }
      try {
        await accion();
      } catch (error) {
        estado.fallo = true;
        const archivo = await panel.captura(`${opciones.ruta}-${nombre}`).catch(() => null);
        if (archivo) error.message += `\n    Captura: ${archivo}`;
        throw error;
      }
    });
  };

  await paso('registrar', async () => {
    await panel.irAModulo(opciones.ruta);
    const nuevo = opciones.antesDeRegistrar ? await opciones.antesDeRegistrar(panel) : opciones.nuevo;
    await panel.abrirNuevo();
    await panel.llenar(nuevo);
    await panel.guardar();
    if (opciones.identificar) estado.buscar = await opciones.identificar(panel);
    await panel.buscar(busqueda(estado.buscar));
    await panel.fila(estado.buscar);
  });

  await paso('modificar', async () => {
    await panel.accionEnFila(estado.buscar, 'Modificar');
    await panel.llenar(opciones.modificar);
    await panel.guardar();
    if (opciones.nuevaBusqueda) {
      estado.buscar = opciones.nuevaBusqueda;
      await panel.buscar(busqueda(estado.buscar));
    }
    await panel.fila([].concat(estado.buscar, opciones.despuesDeModificar ?? []));
  });

  await paso('eliminar', async () => {
    await panel.accionEnFila(estado.buscar, 'Eliminar');
    await panel.confirmar();
    await panel.sinFila(estado.buscar);
    await panel.filtro('eliminados');
    await panel.fila([].concat(estado.buscar, 'Eliminado'));
    await panel.filtro('activos');
  });
}

/** Texto que se escribe en el buscador (el primero si se buscan varios). */
function busqueda(textos) {
  return [].concat(textos)[0];
}
