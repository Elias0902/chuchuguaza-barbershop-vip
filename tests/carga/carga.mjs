/**
 * Pruebas de carga y estrés con Apache Bench (viene con XAMPP: C:\xampp\apache\bin\ab.exe).
 *
 *   node tests/carga/carga.mjs            carga normal + estrés (≈ 2 min)
 *   node tests/carga/carga.mjs --limite   además sube hasta 600 usuarios para encontrar el límite (≈ 5 min)
 *
 * 1) Carga: páginas típicas con usuarios simultáneos realistas para una barbería.
 * 2) Estrés: sube la concurrencia hasta ver dónde empieza a fallar o a ponerse lento.
 * Usa la cuenta de administrador de tests/e2e/.env (con su código 2FA del correo local).
 * Deja el informe en tests/carga/informe-carga.txt.
 */
import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { leerCodigo2fa } from '../e2e/lib/codigo2fa.js';

const aqui = dirname(fileURLToPath(import.meta.url));
const AB = process.env.AB || 'C:\\xampp\\apache\\bin\\ab.exe';
const env = Object.fromEntries(readFileSync(join(aqui, '..', 'e2e', '.env'), 'utf8').split(/\r?\n/)
  .filter((l) => l.includes('=') && !l.trim().startsWith('#'))
  .map((l) => [l.slice(0, l.indexOf('=')).trim(), l.slice(l.indexOf('=') + 1).trim()]));
// ab no entiende «localhost» con IPv6 en algunos Windows: se usa 127.0.0.1.
const BASE = (env.URL_BASE || 'http://localhost/chuchuguaza-barber-shop').replace('localhost', '127.0.0.1').replace(/\/$/, '');

// ---------------------------------------------------------------- sesión de administrador
let cookie = '';
async function pedir(ruta, opciones = {}) {
  const r = await fetch(`${BASE}/${ruta}`, { redirect: 'manual', ...opciones, headers: { Cookie: cookie, Accept: 'text/html', ...(opciones.headers || {}) } });
  const nueva = (r.headers.getSetCookie?.() || []).find((c) => c.startsWith('PHPSESSID='));
  if (nueva) cookie = nueva.split(';')[0];
  return { r, texto: await r.text() };
}
const formulario = (datos) => ({ method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(datos).toString() });

const desde = Date.now();
let { texto } = await pedir('index.php?url=autenticator&action=acceso_interno');
let csrf = texto.match(/name="csrf_token" value="([^"]+)"/)[1];
let { r } = await pedir('index.php?url=autenticator&action=ingresar_interno', formulario({ csrf_token: csrf, username: env.TEST_USUARIO, password: env.TEST_CLAVE }));
if ((r.headers.get('location') || '').includes('verificar')) {
  ({ texto } = await pedir('index.php?url=autenticator&action=verificar'));
  csrf = texto.match(/name="csrf_token" value="([^"]+)"/)[1];
  ({ r } = await pedir('index.php?url=autenticator&action=confirmar_codigo', formulario({ csrf_token: csrf, codigo: await leerCodigo2fa(desde) })));
}
if (!(r.headers.get('location') || '').includes('dashboard')) throw new Error('No se pudo iniciar sesión para la prueba de carga.');

// ---------------------------------------------------------------- Apache Bench
function ab(nombre, ruta, peticiones, concurrencia, { conSesion = false } = {}) {
  const args = ['-n', String(peticiones), '-c', String(concurrencia), '-s', '30', '-H', 'Accept: application/json, text/html'];
  if (conSesion) args.push('-C', cookie);
  args.push(`${BASE}/${ruta}`);
  let salida = '';
  try {
    salida = execFileSync(AB, args, { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024, stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (error) {
    salida = String(error.stdout || '') + String(error.stderr || '');
  }
  const numero = (re) => Number((salida.match(re) || [])[1] || 0);
  const resultado = {
    nombre, peticiones, concurrencia,
    completas: numero(/Complete requests:\s+(\d+)/),
    fallidas: numero(/Failed requests:\s+(\d+)/) + numero(/Non-2xx responses:\s+(\d+)/),
    porSegundo: numero(/Requests per second:\s+([\d.]+)/),
    media: numero(/Time per request:\s+([\d.]+) \[ms\] \(mean\)/),
    p95: numero(/\s95%\s+(\d+)/),
    maximo: numero(/100%\s+(\d+)/),
  };
  console.log(`  ${nombre.padEnd(42)} c=${String(concurrencia).padStart(3)}  ${resultado.porSegundo.toFixed(1).padStart(7)} pet/s  media ${String(Math.round(resultado.media)).padStart(5)} ms  p95 ${String(resultado.p95).padStart(5)} ms  fallas ${resultado.fallidas}`);
  return resultado;
}

console.log(`\nPrueba de carga contra ${BASE}\n\n1) CARGA (uso normal)`);
const carga = [
  ab('Página principal (estática)', 'landing-chuchuguaza/index.html', 1000, 50),
  ab('Formulario de acceso (PHP)', 'index.php?url=autenticator&action=acceso_interno', 500, 20),
  ab('Políticas públicas (PHP + BD)', 'index.php?url=publico&action=politicas', 500, 20),
  ab('Inicio del panel (con sesión)', 'index.php?url=dashboard', 300, 10, { conSesion: true }),
  ab('Listado de pagos JSON (con sesión)', 'index.php?url=pagos&action=consultar', 300, 10, { conSesion: true }),
  ab('Listado de tickets JSON (con sesión)', 'index.php?url=tickets&action=consultar', 300, 10, { conSesion: true }),
];

console.log('\n2) ESTRÉS (subiendo usuarios simultáneos en el listado de pagos)');
const niveles = process.argv.includes('--limite') ? [10, 25, 50, 100, 150, 200, 300, 400, 600] : [10, 25, 50, 100, 150];
const estres = niveles.map((c) => ab('Listado de pagos JSON (con sesión)', 'index.php?url=pagos&action=consultar', c * 10, c, { conSesion: true }));

// ---------------------------------------------------------------- informe
const tabla = (filas) => filas.map((x) => `  ${x.nombre.padEnd(40)} ${String(x.concurrencia).padStart(4)} usuarios  ${x.porSegundo.toFixed(1).padStart(7)} pet/s  media ${String(Math.round(x.media)).padStart(5)} ms  p95 ${String(x.p95).padStart(5)} ms  máx ${String(x.maximo).padStart(5)} ms  fallas ${x.fallidas}/${x.peticiones}`).join('\n');
const lento = estres.find((x) => x.p95 > 3000);
const quiebre = estres.find((x) => x.fallidas > 0);
const informe = [
  'INFORME DE CARGA Y ESTRÉS · CHUCHUGUAZA',
  `Fecha: ${new Date().toLocaleString('es-VE')}   Sistema: ${BASE}   Herramienta: Apache Bench`,
  'Equipo: XAMPP local (servidor y pruebas en la misma computadora; en un servidor real los números cambian).',
  '',
  '1) CARGA (uso normal)',
  tabla(carga),
  '',
  '2) ESTRÉS (listado de pagos, ~300 registros, con sesión)',
  tabla(estres),
  '',
  lento ? `Se pone lento (más de 3 s para el 5 % más lento) desde ${lento.concurrencia} usuarios simultáneos.` : `Hasta ${estres.at(-1).concurrencia} usuarios simultáneos, el 95 % respondió en menos de 3 s.`,
  quiebre ? `Límite: desde ${quiebre.concurrencia} usuarios simultáneos empiezan a fallar peticiones (${quiebre.fallidas} de ${quiebre.peticiones}).` : `Sin fallas hasta ${estres.at(-1).concurrencia} usuarios simultáneos (no se alcanzó el límite).`,
  'Referencia: una barbería usa el sistema con 2 a 6 personas a la vez.',
];
writeFileSync(join(aqui, 'informe-carga.txt'), informe.join('\n') + '\n');
console.log(`\n${informe.at(-2)}\nInforme: tests/carga/informe-carga.txt\n`);
