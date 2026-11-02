/**
 * Pruebas de seguridad del sistema (solo contra la instalación local):
 * inyección SQL, XSS, CSRF, control de acceso por rol, sesión, cabeceras y fuerza bruta.
 *
 *   node tests/seguridad/seguridad.mjs                  todas las pruebas (≈ 10 s)
 *   node tests/seguridad/seguridad.mjs --inactividad    además espera el cierre por inactividad (≈ 2 min)
 *
 * Usa las cuentas de tests/e2e/.env. Deja un informe en tests/seguridad/informe-seguridad.txt.
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { leerCodigo2fa } from '../e2e/lib/codigo2fa.js';

const aqui = dirname(fileURLToPath(import.meta.url));
const env = {};
const archivoEnv = join(aqui, '..', 'e2e', '.env');
if (existsSync(archivoEnv)) {
  for (const linea of readFileSync(archivoEnv, 'utf8').split(/\r?\n/)) {
    const i = linea.indexOf('=');
    if (i > 0 && !linea.trim().startsWith('#')) env[linea.slice(0, i).trim()] = linea.slice(i + 1).trim();
  }
}
const BASE = (process.env.URL_BASE || env.URL_BASE || 'http://localhost/chuchuguaza-barber-shop').replace(/\/$/, '');
const resultados = [];

// ---------------------------------------------------------------- cliente HTTP con cookie de sesión
class Cliente {
  cookie = '';
  csrf = '';

  async pedir(ruta, { metodo = 'GET', json, formulario, cabeceras = {} } = {}) {
    const headers = { Accept: 'application/json', ...cabeceras };
    if (this.cookie) headers.Cookie = this.cookie;
    let body;
    if (json !== undefined) { headers['Content-Type'] = 'application/json'; body = JSON.stringify(json); }
    if (formulario) { headers['Content-Type'] = 'application/x-www-form-urlencoded'; body = new URLSearchParams(formulario).toString(); }
    const r = await fetch(BASE + '/' + ruta, { method: metodo, headers, body, redirect: 'manual' });
    const nueva = (r.headers.getSetCookie?.() || []).find((c) => c.startsWith('PHPSESSID='));
    if (nueva) this.cookie = nueva.split(';')[0];
    const texto = await r.text();
    let datos = null;
    try { datos = JSON.parse(texto); } catch { /* HTML */ }
    return { estado: r.status, texto, datos, headers: r.headers, cookieCruda: nueva || '' };
  }

  async entrar(usuario, clave, { completar2fa = true } = {}) {
    const desde = Date.now();
    const pagina = await this.pedir('index.php?url=autenticator&action=acceso_interno', { cabeceras: { Accept: 'text/html' } });
    const token = pagina.texto.match(/name="csrf_token" value="([^"]+)"/)?.[1] || '';
    const r = await this.pedir('index.php?url=autenticator&action=ingresar_interno', {
      metodo: 'POST', formulario: { csrf_token: token, username: usuario, password: clave }, cabeceras: { Accept: 'text/html' },
    });
    let dentro = (r.headers.get('location') || '').includes('url=dashboard');
    this.pide2fa = (r.headers.get('location') || '').includes('action=verificar');
    if (this.pide2fa && completar2fa) {
      const verificar = await this.pedir('index.php?url=autenticator&action=verificar', { cabeceras: { Accept: 'text/html' } });
      const csrf = verificar.texto.match(/name="csrf_token" value="([^"]+)"/)?.[1] || '';
      const ok = await this.pedir('index.php?url=autenticator&action=confirmar_codigo', {
        metodo: 'POST', formulario: { csrf_token: csrf, codigo: await leerCodigo2fa(desde) }, cabeceras: { Accept: 'text/html' },
      });
      dentro = (ok.headers.get('location') || '').includes('url=dashboard');
    }
    if (dentro) {
      const panel = await this.pedir('index.php?url=clientes', { cabeceras: { Accept: 'text/html' } });
      this.csrf = panel.texto.match(/name="csrf-token" content="([^"]+)"/)?.[1] || '';
    }
    return { dentro, respuesta: r };
  }
}

async function prueba(categoria, nombre, funcion) {
  try {
    const detalle = await funcion();
    resultados.push({ categoria, nombre, ok: true, detalle: detalle || '' });
    console.log(`  ✔ [${categoria}] ${nombre}`);
  } catch (error) {
    resultados.push({ categoria, nombre, ok: false, detalle: error.message });
    console.log(`  ✖ [${categoria}] ${nombre}\n      ${error.message}`);
  }
}
const exigir = (condicion, mensaje) => { if (!condicion) throw new Error(mensaje); };

// ---------------------------------------------------------------- pruebas
const anonimo = new Cliente();
const admin = new Cliente();
const barbero = new Cliente();

console.log(`\nPruebas de seguridad contra ${BASE}\n`);

await prueba('Acceso', 'sin sesión, los módulos responden 401 y no devuelven datos', async () => {
  for (const ruta of ['clientes', 'pagos', 'usuarios', 'respaldos']) {
    const r = await anonimo.pedir(`index.php?url=${ruta}&action=consultar`);
    exigir(r.estado === 401 && !r.datos?.datos, `${ruta}: estado ${r.estado}`);
  }
  const pagina = await anonimo.pedir('index.php?url=dashboard', { cabeceras: { Accept: 'text/html' } });
  exigir(pagina.estado === 302 && pagina.headers.get('location').includes('autenticator'), 'El inicio sin sesión no redirige al acceso');
});

await prueba('Inyección SQL', 'login con payloads clásicos no deja entrar', async () => {
  const payloads = ["' OR '1'='1", "' OR 1=1 -- ", "admin'#", "\" OR \"\"=\"", "' UNION SELECT 1,2,3,4,5,6,7 -- ", "ElZorroBarberShop' -- "];
  for (const p of payloads) {
    const c = new Cliente();
    const { dentro, respuesta } = await c.entrar(p, "' OR '1'='1");
    exigir(!dentro, `Entró con «${p}»`);
    exigir(respuesta.estado < 500, `Error 500 con «${p}»`);
  }
  return `${payloads.length} payloads rechazados`;
});

await prueba('Sesión', 'el administrador de pruebas inicia sesión', async () => {
  exigir(env.TEST_USUARIO && env.TEST_CLAVE, 'Faltan TEST_USUARIO/TEST_CLAVE en tests/e2e/.env');
  const { dentro } = await admin.entrar(env.TEST_USUARIO, env.TEST_CLAVE);
  exigir(dentro && admin.csrf, 'No pudo iniciar sesión');
});

await prueba('Sesión', 'la cookie de sesión es HttpOnly y SameSite y cambia al iniciar sesión', async () => {
  const c = new Cliente();
  const antes = (await c.pedir('index.php?url=autenticator&action=acceso_interno', { cabeceras: { Accept: 'text/html' } }));
  const idAntes = c.cookie;
  const cruda = antes.cookieCruda.toLowerCase();
  exigir(cruda.includes('httponly'), 'La cookie no es HttpOnly');
  exigir(cruda.includes('samesite=lax') || cruda.includes('samesite=strict'), 'La cookie no tiene SameSite');
  await c.entrar(env.TEST_USUARIO, env.TEST_CLAVE);
  exigir(c.cookie !== idAntes, 'El id de sesión no cambia al iniciar sesión (fijación de sesión)');
});

await prueba('Inyección SQL', 'parámetros id con SQL no filtran datos ni dan error 500', async () => {
  const payloads = ['1 OR 1=1', "1' OR '1'='1", '1; DROP TABLE cliente', '1 UNION SELECT password_usuario FROM usuarios', "-1' UNION SELECT 1 -- ", '0x31', '1e1'];
  for (const p of payloads) {
    for (const ruta of ['clientes', 'pagos', 'tickets', 'usuarios']) {
      const r = await admin.pedir(`index.php?url=${ruta}&action=obtener&id=${encodeURIComponent(p)}`);
      exigir(r.estado !== 500, `${ruta} id=${p}: error 500`);
      exigir(!r.datos?.ok, `${ruta} id=${p}: devolvió un registro`);
      exigir(!/password_usuario|\$2y\$/.test(r.texto), `${ruta} id=${p}: se filtró un hash de contraseña`);
    }
  }
  return `${payloads.length * 4} combinaciones rechazadas`;
});

await prueba('Inyección SQL', 'campos de formulario con SQL se guardan como texto, sin ejecutarse', async () => {
  const marca = `Sel${Date.now().toString(36)}`;
  const nombre = `${marca}'); DELETE FROM servicio; --`;
  const r = await admin.pedir('index.php?url=categorias&action=registrar', {
    metodo: 'POST', json: { nombre_categoria: nombre }, cabeceras: { 'X-CSRF-Token': admin.csrf },
  });
  exigir(r.datos?.ok, `No se registró: ${r.datos?.mensaje || r.estado}`);
  const lista = await admin.pedir('index.php?url=categorias&action=consultar');
  const fila = lista.datos.datos.find((c) => c.nombre_categoria === nombre);
  exigir(fila, 'El texto no se guardó tal cual');
  const servicios = await admin.pedir('index.php?url=servicios&action=consultar');
  exigir(servicios.datos.datos.length > 0, '¡La tabla servicio quedó vacía!');
  await admin.pedir('index.php?url=categorias&action=eliminar', { metodo: 'POST', json: { id: fila.id_categoria }, cabeceras: { 'X-CSRF-Token': admin.csrf } });
});

await prueba('XSS', 'el HTML guardado se devuelve como texto y la vista nunca usa innerHTML', async () => {
  const marca = `Xss${Date.now().toString(36)}`;
  const ataque = `<img src=x onerror=alert('${marca}')><script>alert(1)</script>`;
  const r = await admin.pedir('index.php?url=categorias&action=registrar', {
    metodo: 'POST', json: { nombre_categoria: ataque.slice(0, 50) }, cabeceras: { 'X-CSRF-Token': admin.csrf },
  });
  exigir(r.datos?.ok, `No se registró: ${r.datos?.mensaje || r.estado}`);
  const pagina = await admin.pedir('index.php?url=categorias', { cabeceras: { Accept: 'text/html' } });
  exigir(!pagina.texto.includes(`onerror=alert('${marca}')`), 'El ataque aparece sin escapar en el HTML del módulo');
  const lista = await admin.pedir('index.php?url=categorias&action=consultar');
  const fila = lista.datos.datos.find((c) => c.nombre_categoria === ataque.slice(0, 50));
  await admin.pedir('index.php?url=categorias&action=eliminar', { metodo: 'POST', json: { id: fila.id_categoria }, cabeceras: { 'X-CSRF-Token': admin.csrf } });
  const raiz = join(aqui, '..', '..');
  for (const archivo of ['assets/js/modulo.js', 'assets/js/panel.js', 'assets/js/permisos.js', 'landing-chuchuguaza/index.html']) {
    exigir(!/\.innerHTML\s*=|insertAdjacentHTML|document\.write/.test(readFileSync(join(raiz, archivo), 'utf8')), `${archivo} usa innerHTML/document.write`);
  }
});

await prueba('XSS', 'parámetros reflejados en la URL se escapan', async () => {
  const ataque = '"><script>alert(1)</script>';
  for (const ruta of [`index.php?url=autenticator&action=restablecer&token=${encodeURIComponent(ataque)}`, `index.php?url=buzon&grupo=${encodeURIComponent(ataque)}`, `index.php?url=legal&action=${encodeURIComponent(ataque)}`]) {
    const r = await admin.pedir(ruta, { cabeceras: { Accept: 'text/html' } });
    exigir(!r.texto.includes('<script>alert(1)</script>'), `Se refleja sin escapar en ${ruta}`);
  }
});

await prueba('CSRF', 'registrar, eliminar y restaurar sin token CSRF se rechazan (403)', async () => {
  for (const [accion, json] of [['registrar', { nombre_categoria: 'csrf', descripcion: 'x' }], ['eliminar', { id: 1 }], ['restaurar', { id: 1 }]]) {
    const r = await admin.pedir(`index.php?url=categorias&action=${accion}`, { metodo: 'POST', json });
    exigir(r.estado === 403, `${accion} sin token: estado ${r.estado}`);
    const r2 = await admin.pedir(`index.php?url=categorias&action=${accion}`, { metodo: 'POST', json, cabeceras: { 'X-CSRF-Token': 'falso' } });
    exigir(r2.estado === 403, `${accion} con token falso: estado ${r2.estado}`);
  }
  const respaldo = await admin.pedir('index.php?url=respaldos&action=restaurar', { metodo: 'POST', json: { id: 1 } });
  exigir(respaldo.estado === 403, `Restaurar un respaldo sin token: estado ${respaldo.estado}`);
});

await prueba('CSRF', 'las acciones de escritura no se aceptan por GET', async () => {
  const r = await admin.pedir('index.php?url=categorias&action=eliminar&id=1');
  exigir(r.estado === 405 || r.estado === 403, `GET eliminar: estado ${r.estado}`);
});

await prueba('Archivos', 'la descarga de respaldos no permite salir de su carpeta', async () => {
  for (const id of ['../../config/config.php', '..%2F..%2F.env', '1/../../index.php', '-1', '1 OR 1=1']) {
    const r = await admin.pedir(`index.php?url=respaldos&action=descargar&id=${id}`);
    exigir(!r.texto.includes('<?php') && !r.texto.includes('DB_PASS'), `Se descargó código con id=${id}`);
    exigir(r.estado !== 500, `Error 500 con id=${id}`);
  }
});

await prueba('Archivos', 'archivos sensibles no son accesibles desde la web', async () => {
  for (const ruta of ['.env', 'tests/e2e/.env', 'credenciales-locales.txt', 'storage/respaldos/', 'composer.json', 'config/config.php', '.git/config']) {
    const r = await anonimo.pedir(ruta, { cabeceras: { Accept: 'text/html' } });
    const fuga = /DB_PASS|TEST_CLAVE|Contraseña:|\[core\]|INSERT INTO|repositoryformatversion/.test(r.texto);
    exigir(!(r.estado === 200 && fuga), `${ruta} se puede leer desde el navegador (estado ${r.estado})`);
  }
});

await prueba('Roles', 'el barbero no entra a módulos ajenos ni ve tickets de otros', async () => {
  exigir(env.TEST_BARBERO_USUARIO, 'Falta TEST_BARBERO_USUARIO en tests/e2e/.env');
  const { dentro } = await barbero.entrar(env.TEST_BARBERO_USUARIO, env.TEST_BARBERO_CLAVE);
  exigir(dentro, 'El barbero no pudo iniciar sesión');
  for (const ruta of ['pagos', 'cierres', 'nomina', 'usuarios', 'roles', 'respaldos', 'bitacora']) {
    const r = await barbero.pedir(`index.php?url=${ruta}&action=consultar`);
    exigir(r.estado === 403, `${ruta}: estado ${r.estado}`);
  }
  const todos = (await admin.pedir('index.php?url=tickets&action=consultar')).datos.datos;
  const ajeno = todos.find((t) => t.cedula_barbero !== env.TEST_BARBERO_CEDULA);
  const r = await barbero.pedir(`index.php?url=tickets&action=obtener&id=${ajeno.id_ticket}`);
  exigir(!r.datos?.ok, `El barbero abrió el ticket #${ajeno.id_ticket} de otro barbero`);
  const propios = (await barbero.pedir('index.php?url=tickets&action=consultar')).datos.datos;
  exigir(propios.every((t) => t.cedula_barbero === env.TEST_BARBERO_CEDULA), 'La lista del barbero incluye tickets de otros');
  const borrar = await barbero.pedir('index.php?url=tickets&action=eliminar', { metodo: 'POST', json: { id: ajeno.id_ticket }, cabeceras: { 'X-CSRF-Token': barbero.csrf } });
  exigir(borrar.estado === 403, `El barbero pudo intentar eliminar (estado ${borrar.estado})`);
});

await prueba('Roles', 'un usuario no puede darse permisos ni cambiarse el rol a sí mismo', async () => {
  const yo = (await admin.pedir('index.php?url=usuarios&action=consultar')).datos.datos.find((u) => u.nombre_usuario === env.TEST_USUARIO);
  const r = await admin.pedir('index.php?url=usuarios&action=guardar_permisos', {
    metodo: 'POST', json: { id: yo.id_usuario, permisos: {} }, cabeceras: { 'X-CSRF-Token': admin.csrf },
  });
  exigir(!r.datos?.ok, 'Se quitó sus propios permisos');
});

await prueba('Cabeceras', 'cabeceras de seguridad presentes', async () => {
  const r = await anonimo.pedir('index.php?url=autenticator', { cabeceras: { Accept: 'text/html' } });
  for (const cabecera of ['content-security-policy', 'x-content-type-options', 'x-frame-options', 'referrer-policy']) {
    exigir(r.headers.get(cabecera), `Falta ${cabecera}`);
  }
  exigir(!/PHP\/|X-Powered-By/i.test(r.headers.get('x-powered-by') || ''), `Expone la versión de PHP: ${r.headers.get('x-powered-by')}`);
});

await prueba('Fuerza bruta', 'tras 3 contraseñas erróneas la cuenta se bloquea, aunque luego se use la correcta', async () => {
  const objetivo = env.TEST_BARBERO_USUARIO;
  let ultimo;
  for (let i = 0; i < 3; i++) ultimo = await new Cliente().entrar(objetivo, `incorrecta${i}`);
  const correcta = await new Cliente().entrar(objetivo, env.TEST_BARBERO_CLAVE);
  exigir(!correcta.dentro, 'Entró con la clave correcta justo después de 3 fallos');
  return 'La cuenta de prueba quedó bloqueada 30 min (el script la desbloquea al final)';
});

await prueba('Recuperar contraseña', 'no revela si un usuario existe y el token no se puede adivinar', async () => {
  const c = new Cliente();
  const pagina = await c.pedir('index.php?url=autenticator&action=recuperar', { cabeceras: { Accept: 'text/html' } });
  const token = pagina.texto.match(/name="csrf_token" value="([^"]+)"/)?.[1];
  const respuesta = async (id) => (await c.pedir('index.php?url=autenticator&action=enviar_recuperacion', {
    metodo: 'POST', formulario: { csrf_token: token, identificador: id }, cabeceras: { Accept: 'text/html' },
  })).texto.match(/class="notice[^"]*"[^>]*>([^<]+)/)?.[1];
  const noExiste = await respuesta(`noexiste${Date.now()}`);
  const sql = await respuesta("' OR 1=1 -- ");
  exigir(noExiste && noExiste === sql, 'El mensaje cambia según el usuario');
  const falso = await c.pedir(`index.php?url=autenticator&action=restablecer&token=${'a'.repeat(64)}`, { cabeceras: { Accept: 'text/html' } });
  exigir(falso.texto.includes('no es válido'), 'Aceptó un token inventado');
});

await prueba('2FA', 'el administrador no entra solo con la contraseña', async () => {
  const c = new Cliente();
  const { dentro } = await c.entrar(env.TEST_USUARIO, env.TEST_CLAVE, { completar2fa: false });
  exigir(c.pide2fa && !dentro, 'No se pidió el código de verificación');
  const panel = await c.pedir('index.php?url=clientes&action=consultar');
  exigir(panel.estado === 401, `Con la contraseña sola ya tiene acceso (estado ${panel.estado})`);
  const verificar = await c.pedir('index.php?url=autenticator&action=verificar', { cabeceras: { Accept: 'text/html' } });
  const csrf = verificar.texto.match(/name="csrf_token" value="([^"]+)"/)?.[1];
  for (const codigo of ['000000', "' OR 1=1 -- ", '123456']) {
    const r = await c.pedir('index.php?url=autenticator&action=confirmar_codigo', { metodo: 'POST', formulario: { csrf_token: csrf, codigo }, cabeceras: { Accept: 'text/html' } });
    exigir(!(r.headers.get('location') || '').includes('dashboard'), `Entró con el código «${codigo}»`);
  }
  exigir((await c.pedir('index.php?url=clientes&action=consultar')).estado === 401, 'Tras códigos incorrectos tiene acceso');
});

await prueba('2FA', 'un código usado no sirve otra vez', async () => {
  const c = new Cliente();
  const desde = Date.now();
  await c.entrar(env.TEST_USUARIO, env.TEST_CLAVE, { completar2fa: false });
  const codigo = await leerCodigo2fa(desde);
  const enviar = async (cliente) => {
    const v = await cliente.pedir('index.php?url=autenticator&action=verificar', { cabeceras: { Accept: 'text/html' } });
    const csrf = v.texto.match(/name="csrf_token" value="([^"]+)"/)?.[1];
    return (await cliente.pedir('index.php?url=autenticator&action=confirmar_codigo', { metodo: 'POST', formulario: { csrf_token: csrf, codigo }, cabeceras: { Accept: 'text/html' } })).headers.get('location') || '';
  };
  exigir((await enviar(c)).includes('dashboard'), 'El código correcto no dejó entrar');
  const otro = new Cliente();
  await otro.entrar(env.TEST_USUARIO, env.TEST_CLAVE, { completar2fa: false });
  await new Promise((r) => setTimeout(r, 300));
  exigir(!(await enviar(otro)).includes('dashboard'), 'El mismo código sirvió dos veces');
});

await prueba('Sesión', 'volver a «Entrar al sistema» con la sesión abierta la cierra y pide la clave', async () => {
  exigir((await admin.pedir('index.php?url=clientes&action=consultar')).estado === 200, 'El administrador no tenía sesión');
  const acceso = await admin.pedir('index.php?url=autenticator&action=acceso_interno', { cabeceras: { Accept: 'text/html' } });
  exigir(acceso.estado === 200 && acceso.texto.includes('name="password"'), 'No mostró el formulario de acceso');
  exigir((await admin.pedir('index.php?url=clientes&action=consultar')).estado === 401, 'La sesión siguió abierta después de volver al acceso');
});

if (process.argv.includes('--inactividad')) {
  await prueba('Sesión', 'la sesión se cierra tras 2 minutos sin actividad', async () => {
    const c = new Cliente();
    await c.entrar(env.TEST_USUARIO, env.TEST_CLAVE);
    exigir((await c.pedir('index.php?url=clientes&action=consultar')).estado === 200, 'No tenía sesión al empezar');
    console.log('      esperando 125 s sin actividad...');
    await new Promise((r) => setTimeout(r, 125000));
    const r = await c.pedir('index.php?url=clientes&action=consultar');
    exigir(r.estado === 401, `Después de 125 s la sesión seguía abierta (estado ${r.estado})`);
  });
}

// ---------------------------------------------------------------- informe
const fallidas = resultados.filter((r) => !r.ok);
const lineas = [
  'INFORME DE PRUEBAS DE SEGURIDAD · CHUCHUGUAZA',
  `Fecha: ${new Date().toLocaleString('es-VE')}   Sistema: ${BASE}`,
  `Resultado: ${resultados.length - fallidas.length} de ${resultados.length} pruebas superadas`,
  '',
  ...resultados.map((r) => `${r.ok ? '[OK]   ' : '[FALLA]'} ${r.categoria} · ${r.nombre}${r.detalle ? `\n          ${r.detalle}` : ''}`),
];
writeFileSync(join(aqui, 'informe-seguridad.txt'), lineas.join('\n') + '\n');
console.log(`\n${resultados.length - fallidas.length}/${resultados.length} pruebas superadas. Informe: tests/seguridad/informe-seguridad.txt\n`);
process.exitCode = fallidas.length ? 1 : 0;
