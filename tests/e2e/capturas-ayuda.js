/**
 * Genera los pantallazos del manual (botón «?» de cada módulo):
 * assets/img/ayuda/{ruta}/listado.png y formulario.png.
 *   npm run capturas-ayuda             (todos los módulos)
 *   npm run capturas-ayuda -- pagos    (solo los indicados; TEMA=claro para el modo claro)
 * No guarda nada en la base: abre el formulario de registro y lo cierra sin guardar.
 */
import { mkdirSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { By } from 'selenium-webdriver';
import { Panel } from './lib/panel.js';

const MODULOS = [
  'citas', 'tickets', 'pagos', 'clientes', 'barberos', 'servicios', 'productos', 'categorias', 'promociones',
  'metodos_pago', 'cierres', 'gastos', 'retiros', 'nomina', 'cursos', 'inscripciones', 'politicas',
  'usuarios', 'roles', 'bitacora',
];
const destino = process.env.DESTINO || join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'assets', 'img', 'ayuda');
const elegidos = process.argv.slice(2).length ? process.argv.slice(2) : MODULOS;

const panel = await Panel.abrir();
const { driver } = panel;
// TEMA=claro u oscuro: tema del panel en las capturas (por defecto, el que tenga guardado).
if (process.env.TEMA) await driver.executeScript('localStorage.setItem("panel.tema", arguments[0])', process.env.TEMA);
// El botón de ayuda no debe salir en su propia captura con el foco puesto.
const sinFoco = () => driver.executeScript('document.activeElement?.blur()');

try {
  for (const ruta of elegidos) {
    const carpeta = join(destino, ruta);
    mkdirSync(carpeta, { recursive: true });
    await panel.irAModulo(ruta);
    await panel.esperarTabla();
    await sinFoco();

    const listado = await driver.findElement(By.css('main.content'));
    writeFileSync(join(carpeta, 'listado.png'), await listado.takeScreenshot(), 'base64');

    const botonesNuevo = await driver.findElements(By.css('[data-nuevo]'));
    if (botonesNuevo.length) {
      await panel.abrirNuevo();
      const modal = await panel.esperarModal();
      await driver.sleep(300); // animación de entrada
      await sinFoco();
      writeFileSync(join(carpeta, 'formulario.png'), await modal.takeScreenshot(), 'base64');
      await driver.executeScript('document.querySelector(".modal-backdrop")?.remove()');
    }
    console.log(`✓ ${ruta}`);
  }
} finally {
  await panel.cerrar();
}
